<?php
/**
 * SOURIANE — Authentification et contrôle d’accès (EF-G01)
 *
 * Mots de passe hachés en Argon2id. Verrouillage temporaire après plusieurs
 * tentatives échouées. Journalisation des connexions.
 */

declare(strict_types=1);

namespace Souriane\Core;

final class Auth
{
    private const CLE = '_utilisateur';
    private const MAX_TENTATIVES = 5;
    private const DUREE_VERROU_MIN = 15;

    private static ?array $utilisateur = null;

    /** Tente une connexion et retourne l’utilisateur ou null. */
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

        $db->update('utilisateurs', [
            'tentatives_echec'  => 0,
            'verrouille_jusqua' => null,
            'derniere_connexion' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => (int) $utilisateur['id']]);

        Session::regenerer();
        $_SESSION[self::CLE] = (int) $utilisateur['id'];
        self::$utilisateur = $utilisateur;

        journal_operation((int) $utilisateur['id'], 'connexion', 'utilisateurs', (int) $utilisateur['id'], 'Connexion réussie', $request->ip());

        return $utilisateur;
    }

    public static function deconnecter(Request $request): void
    {
        $utilisateur = self::utilisateur();
        if ($utilisateur !== null) {
            journal_operation((int) $utilisateur['id'], 'deconnexion', 'utilisateurs', (int) $utilisateur['id'], 'Déconnexion', $request->ip());
        }
        Session::oublier(self::CLE);
        self::$utilisateur = null;
    }

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
        if (!self::verifie()) {
            return Response::redirect(url('admin/connexion?retour=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/')));
        }
        if ($roles !== [] && !self::aRole(...$roles)) {
            return Response::html('<h1>403 — Accès refusé</h1><p>Votre rôle ne permet pas d’accéder à cette page.</p>', 403);
        }

        return null;
    }
}
