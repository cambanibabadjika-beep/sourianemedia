<?php
/**
 * SOURIANE — Authentification et contrôle d’accès (EF-G01, ch. 8)
 *
 * Mots de passe hachés en Argon2id. Verrouillage temporaire après plusieurs
 * tentatives échouées. Journalisation des connexions.
 *
 * Le chapitre 8 impose un second facteur sur les comptes d’administration.
 * La connexion se fait donc en deux temps, et le mot de passe seul n’ouvre
 * plus de session sur ces comptes : tant que le code n’a pas été fourni, ou
 * tant que l’enrôlement n’a pas été mené à son terme, l’état est « en
 * suspens » — un état intermédiaire qui n’est pas une connexion.
 *
 * Conséquence voulue : Auth::verifie() est faux pendant tout l’état
 * intermédiaire. Toutes les protections de l’application s’appuient dessus,
 * et elles échouent donc en refusant, jamais en laissant passer.
 */

declare(strict_types=1);

namespace Souriane\Core;

use Souriane\Services\SecondFacteur;

final class Auth
{
    /** Session ouverte : l’utilisateur est connecté. */
    private const CLE = '_utilisateur';

    /** Mot de passe accepté, code de second facteur attendu. */
    private const CLE_ATTENTE = '_2fa_attente';

    /** Mot de passe accepté, second facteur à mettre en place. */
    private const CLE_ENROLEMENT = '_2fa_enrolement';

    /** Secret en cours d’enrôlement, chiffré, le temps de la confirmation. */
    private const CLE_SECRET = '_2fa_secret';

    /** Codes de secours neufs, le temps d’une redirection vers leur affichage. */
    private const CLE_CODES = '_2fa_codes_secours';

    private const MAX_TENTATIVES = 5;
    private const DUREE_VERROU_MIN = 15;

    private static ?array $utilisateur = null;

    /**
     * Vérifie le mot de passe et engage la connexion.
     *
     * Retourne l’utilisateur dont le mot de passe est accepté, ou null en cas
     * d’échec. Attention : un retour non nul ne signifie pas « connecté » —
     * il faut interroger codeAttendu() et enrolementRequis() pour savoir si
     * la connexion s’arrête là ou se poursuit par le second facteur.
     */
    public static function connecter(string $email, string $motDePasse, Request $request): ?array
    {
        $db = Database::instance();

        $utilisateur = $db->selectOne(
            'SELECT * FROM utilisateurs WHERE email = :email LIMIT 1',
            ['email' => mb_strtolower(trim($email))]
        );

        if ($utilisateur === null) {
            // Comparaison factice pour uniformiser le temps de réponse
            password_verify($motDePasse, '$argon2id$v=19$m=65536,t=4,p=1$AAAAAAAAAAAAAAAA$AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');
            journal_technique('securite', 'Tentative de connexion sur un compte inexistant', [
                'email' => $email,
                'ip'    => $request->ip(),
            ]);

            return null;
        }

        if ((int) $utilisateur['actif'] !== 1) {
            return null;
        }

        if ($utilisateur['verrouille_jusqua'] !== null && strtotime((string) $utilisateur['verrouille_jusqua']) > time()) {
            journal_technique('securite', 'Connexion refusée : compte verrouillé', [
                'email' => $email,
            ]);

            return null;
        }

        if (!password_verify($motDePasse, (string) $utilisateur['mot_de_passe'])) {
            $tentatives = (int) $utilisateur['tentatives_echec'] + 1;
            $donnees = ['tentatives_echec' => $tentatives];

            if ($tentatives >= self::MAX_TENTATIVES) {
                $donnees['verrouille_jusqua'] = date('Y-m-d H:i:s', time() + self::DUREE_VERROU_MIN * 60);
                $donnees['tentatives_echec'] = 0;
            }
            $db->update('utilisateurs', $donnees, 'id = :id', ['id' => (int) $utilisateur['id']]);

            journal_technique('securite', 'Mot de passe incorrect', ['email' => $email]);

            return null;
        }

        // Ré-hachage si l’algorithme par défaut a évolué
        if (password_needs_rehash((string) $utilisateur['mot_de_passe'], PASSWORD_ARGON2ID)) {
            $db->update('utilisateurs', [
                'mot_de_passe' => password_hash($motDePasse, PASSWORD_ARGON2ID),
            ], 'id = :id', ['id' => (int) $utilisateur['id']]);
        }

        // Le mot de passe est bon : le compteur de tentatives repart de zéro.
        // La date de dernière connexion, elle, n’est écrite qu’une fois la
        // connexion réellement achevée — sinon un second facteur jamais
        // franchi compterait comme une connexion réussie.
        $db->update('utilisateurs', [
            'tentatives_echec'  => 0,
            'verrouille_jusqua' => null,
        ], 'id = :id', ['id' => (int) $utilisateur['id']]);

        // Second facteur déjà en place : le code est attendu.
        if (SecondFacteur::actif($utilisateur)) {
            self::suspendre(self::CLE_ATTENTE, (int) $utilisateur['id']);
            journal_technique('securite', 'Mot de passe accepté, code à deux facteurs attendu', ['email' => $email]);

            return $utilisateur;
        }

        // Compte d’administration sans second facteur : la mise en place est
        // obligatoire avant l’entrée. Le mot de passe seul ne suffit pas.
        if (SecondFacteur::obligatoire((string) $utilisateur['role'])) {
            self::suspendre(self::CLE_ENROLEMENT, (int) $utilisateur['id']);
            journal_technique('securite', 'Connexion suspendue : second facteur à mettre en place', ['email' => $email]);

            return $utilisateur;
        }

        self::ouvrirSession($utilisateur, $request);

        return $utilisateur;
    }

    public static function deconnecter(Request $request): void
    {
        $utilisateur = self::utilisateur();
        if ($utilisateur !== null) {
            journal_operation((int) $utilisateur['id'], 'deconnexion', 'utilisateurs', (int) $utilisateur['id'], 'Déconnexion', $request->ip());
        }
        Session::oublier(self::CLE);
        self::abandonner();
        self::$utilisateur = null;
    }

    // -----------------------------------------------------------------------
    // Second facteur (ch. 8)
    // -----------------------------------------------------------------------

    /** Un code de second facteur est attendu pour achever la connexion. */
    public static function codeAttendu(): bool
    {
        return !self::verifie() && self::attente(self::CLE_ATTENTE) !== null;
    }

    /**
     * Un enrôlement de second facteur est en cours.
     *
     * Vrai aussi bien pour un compte connecté qui active le second facteur
     * depuis ses réglages que pour un compte d’administration qui y est
     * contraint à la connexion.
     */
    public static function enrolementEnCours(): bool
    {
        return self::attente(self::CLE_ENROLEMENT) !== null;
    }

    /** L’entrée est refusée tant que le second facteur n’est pas en place. */
    public static function enrolementRequis(): bool
    {
        return !self::verifie() && self::enrolementEnCours();
    }

    /** Compte concerné par une connexion en suspens, ou null. */
    public static function enSuspens(): ?array
    {
        $attente = self::attente(self::CLE_ATTENTE) ?? self::attente(self::CLE_ENROLEMENT);
        if ($attente === null) {
            return null;
        }

        return Database::instance()->selectOne(
            'SELECT * FROM utilisateurs WHERE id = :id AND actif = 1 LIMIT 1',
            ['id' => (int) $attente['id']]
        );
    }

    /** Secret en cours d’enrôlement, chiffré, ou null. */
    public static function secretEnrolement(): ?string
    {
        $bloc = $_SESSION[self::CLE_SECRET] ?? null;

        return is_string($bloc) ? $bloc : null;
    }

    /** Retient le secret d’enrôlement, chiffré, le temps de la confirmation. */
    public static function retenirSecret(string $bloc): void
    {
        $_SESSION[self::CLE_SECRET] = $bloc;
    }

    /** Oublie le secret d’enrôlement. */
    public static function oublierSecret(): void
    {
        unset($_SESSION[self::CLE_SECRET]);
    }

    /** Met de côté les codes de secours neufs, le temps d’une redirection. */
    public static function retenirCodesSecours(array $codes): void
    {
        $_SESSION[self::CLE_CODES] = $codes;
    }

    /**
     * Rend les codes de secours neufs, et les oublie aussitôt.
     *
     * Ils ne sont affichés qu’une fois : les relire suppose d’en régénérer
     * un jeu, ce qui invalide le précédent.
     *
     * @return list<string>
     */
    public static function prendreCodesSecours(): array
    {
        $codes = $_SESSION[self::CLE_CODES] ?? [];
        unset($_SESSION[self::CLE_CODES]);

        return is_array($codes) ? array_values($codes) : [];
    }

    /**
     * Achève une connexion en suspens.
     *
     * À n’appeler qu’après vérification du second facteur.
     */
    public static function achever(array $utilisateur, Request $request): void
    {
        self::ouvrirSession($utilisateur, $request);
    }

    /**
     * Engage un enrôlement depuis un compte déjà connecté.
     *
     * Passer par la même voie que l’enrôlement imposé évite d’entretenir deux
     * parcours différents, qui finiraient par diverger.
     */
    public static function demanderEnrolement(int $utilisateurId): void
    {
        self::suspendre(self::CLE_ENROLEMENT, $utilisateurId);
    }

    /** Clôt l’enrôlement sans toucher à la session ouverte. */
    public static function fermerEnrolement(): void
    {
        unset($_SESSION[self::CLE_ENROLEMENT]);
        self::oublierSecret();
    }

    // -----------------------------------------------------------------------
    // Lecture de l’état
    // -----------------------------------------------------------------------

    /** Utilisateur connecté (ou null). */
    public static function utilisateur(): ?array
    {
        if (self::$utilisateur !== null) {
            return self::$utilisateur;
        }
        $id = $_SESSION[self::CLE] ?? null;
        if ($id === null) {
            return null;
        }

        $utilisateur = Database::instance()->selectOne(
            'SELECT * FROM utilisateurs WHERE id = :id AND actif = 1 LIMIT 1',
            ['id' => (int) $id]
        );

        self::$utilisateur = $utilisateur;

        return $utilisateur;
    }

    public static function verifie(): bool
    {
        return self::utilisateur() !== null;
    }

    public static function id(): ?int
    {
        $u = self::utilisateur();

        return $u === null ? null : (int) $u['id'];
    }

    public static function role(): ?string
    {
        $u = self::utilisateur();

        return $u === null ? null : (string) $u['role'];
    }

    public static function aRole(string ...$roles): bool
    {
        $role = self::role();

        return $role !== null && in_array($role, $roles, true);
    }

    /** Répond par une redirection si l’utilisateur n’a pas le rôle requis. */
    public static function exigerRole(array $roles): ?Response
    {
        if (self::enrolementRequis()) {
            return Response::redirect(url('admin/connexion/enrolement'));
        }
        if (!self::verifie()) {
            return Response::redirect(url('admin/connexion?retour=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/')));
        }
        if ($roles !== [] && !self::aRole(...$roles)) {
            return Response::html('<h1>403 — Accès refusé</h1><p>Votre rôle ne permet pas d’accéder à cette page.</p>', 403);
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // Interne
    // -----------------------------------------------------------------------

    /** Ouvre la session et journalise la connexion. */
    private static function ouvrirSession(array $utilisateur, Request $request): void
    {
        Database::instance()->update(
            'utilisateurs',
            ['derniere_connexion' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => (int) $utilisateur['id']]
        );

        Session::regenerer();
        unset($_SESSION[self::CLE_ATTENTE], $_SESSION[self::CLE_ENROLEMENT], $_SESSION[self::CLE_SECRET]);

        $_SESSION[self::CLE] = (int) $utilisateur['id'];
        self::$utilisateur   = $utilisateur;

        journal_operation((int) $utilisateur['id'], 'connexion', 'utilisateurs', (int) $utilisateur['id'], 'Connexion réussie', $request->ip());
    }

    /** Installe un état intermédiaire, horodaté. */
    private static function suspendre(string $cle, int $utilisateurId): void
    {
        Session::regenerer();
        self::abandonner();
        $_SESSION[$cle] = ['id' => $utilisateurId, 'depuis' => time()];
    }

    /** Efface les états intermédiaires. */
    private static function abandonner(): void
    {
        unset($_SESSION[self::CLE_ATTENTE], $_SESSION[self::CLE_ENROLEMENT], $_SESSION[self::CLE_SECRET]);
    }

    /**
     * Lit un état intermédiaire s’il est encore valable.
     *
     * Un état périmé est effacé au passage : une connexion à moitié faite ne
     * doit pas rester ouverte indéfiniment sur un poste partagé.
     */
    private static function attente(string $cle): ?array
    {
        $attente = $_SESSION[$cle] ?? null;
        if (!is_array($attente) || !isset($attente['id'], $attente['depuis'])) {
            return null;
        }

        if (time() - (int) $attente['depuis'] > SECURITE_2FA_DELAI) {
            unset($_SESSION[$cle], $_SESSION[self::CLE_SECRET]);

            return null;
        }

        return $attente;
    }
}
