<?php
/**
 * SOURIANE — Second facteur des comptes d’administration (ch. 8)
 *
 *   « Les comptes d’administration sont protégés par une authentification à
 *     deux facteurs. »
 *
 * Ce service tient le milieu de la chaîne : le calcul cryptographique est
 * dans Totp, le rangement du secret dans Coffre, et ici l’enrôlement, la
 * vérification courante et les codes de secours.
 *
 * Les codes de secours répondent à un problème concret. Un téléphone perdu,
 * changé ou réinitialisé rend le second facteur infranchissable ; sans porte
 * de sortie, l’exploitant se retrouve enfermé dehors de sa propre plateforme,
 * et la tentation serait grande de désactiver le second facteur pour de bon.
 * Huit codes à usage unique, rangés hachés, suffisent à éviter cela.
 *
 * Un code de secours se lit sur une feuille de papier et se recopie à la
 * main : l’alphabet employé écarte donc 0, O, 1, I et L, qui sont exactement
 * les caractères qu’on confond dans cette situation.
 */

declare(strict_types=1);

namespace Souriane\Services;

use Souriane\Core\Coffre;
use Souriane\Core\Totp;
use Souriane\Models\Utilisateur;

final class SecondFacteur
{
    /** Alphabet des codes de secours, sans caractères ambigus. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Longueur d’un code de secours, tiret de présentation non compris. */
    private const LONGUEUR = 8;

    /** Le second facteur est-il obligatoire pour ce rôle ? */
    public static function obligatoire(?string $role): bool
    {
        return $role !== null && in_array($role, SECURITE_2FA_ROLES, true);
    }

    /** Le compte a-t-il un second facteur opérationnel ? */
    public static function actif(?array $utilisateur): bool
    {
        return $utilisateur !== null
            && (int) ($utilisateur['totp_actif'] ?? 0) === 1
            && ($utilisateur['totp_secret'] ?? null) !== null;
    }

    /**
     * Secret en clair, ou null si le coffre refuse de l’ouvrir.
     *
     * Un null signifie « second facteur indisponible », jamais « vérifié » :
     * l’appelant doit refuser la connexion, pas la laisser passer.
     */
    public static function secret(?array $utilisateur): ?string
    {
        return Coffre::ouvrir($utilisateur['totp_secret'] ?? null);
    }

    /** Adresse d’enrôlement, à encoder dans le QR code. */
    public static function uri(string $secret, string $compte): string
    {
        return Totp::uri($secret, $compte, SECURITE_2FA_EMETTEUR);
    }

    /**
     * Active le second facteur après vérification d’un premier code.
     *
     * Retourne les codes de secours en clair — c’est la seule occasion de les
     * lire — ou null si le code ne convient pas.
     *
     * @return list<string>|null
     */
    public static function confirmer(int $utilisateurId, string $secret, string $saisie): ?array
    {
        // Le code d’enrôlement confirme que la clé a été recopiée correctement ;
        // ce n’est pas un code d’authentification. Son intervalle n’est donc pas
        // consommé : un utilisateur qui se reconnecte dans la demi-minute qui
        // suit son enrôlement doit pouvoir utiliser le code qu’il a sous les
        // yeux, sans attendre le suivant. La règle d’usage unique reste
        // entièrement appliquée à la connexion, par verifierCode().
        if (Totp::verifier($secret, $saisie) === null) {
            return null;
        }

        $codes = self::genererCodes(SECURITE_2FA_CODES_SECOURS);

        // C’est ici, et seulement ici, que la clé rejoint la base. Rien n’y est
        // écrit tant que l’application d’authentification n’a pas prouvé
        // qu’elle produit bien les mêmes codes : un enrôlement interrompu ne
        // laisse donc aucun secret orphelin, et un compte activé a toujours un
        // secret utilisable.
        Utilisateur::modifier($utilisateurId, [
            'totp_secret'        => Coffre::sceller($secret),
            'totp_actif'         => 1,
            'totp_confirme_le'   => date('Y-m-d H:i:s'),
            'totp_dernier_pas'   => null,
            'totp_codes_secours' => implode("\n", array_map([self::class, 'empreinte'], $codes)),
        ]);

        return array_map([self::class, 'presenter'], $codes);
    }

    /**
     * Vérifie un code de l’application et consomme son intervalle.
     *
     * Le pas consommé est écrit en base : un code observé par-dessus une
     * épaule ne peut pas resservir pendant les trente secondes qui suivent.
     * C’est l’usage unique qu’impose la RFC 6238 § 5.2.
     */
    public static function verifierCode(int $utilisateurId, array $utilisateur, string $saisie): bool
    {
        $secret = self::secret($utilisateur);
        if ($secret === null) {
            return false;
        }

        $dernier = $utilisateur['totp_dernier_pas'] !== null
            ? (int) $utilisateur['totp_dernier_pas']
            : null;

        $pas = Totp::verifier($secret, $saisie, $dernier);
        if ($pas === null) {
            return false;
        }

        Utilisateur::modifier($utilisateurId, ['totp_dernier_pas' => $pas]);

        return true;
    }

    /** Vérifie un code de secours et le consomme. */
    public static function consommerSecours(int $utilisateurId, array $utilisateur, string $saisie): bool
    {
        $normalise = self::normaliser($saisie);
        if (strlen($normalise) !== self::LONGUEUR) {
            return false;
        }

        $fournie  = hash('sha256', $normalise);
        $restants = [];
        $trouve   = false;

        foreach (self::lignesSecours($utilisateur) as $ligne) {
            if (!$trouve && hash_equals($ligne, $fournie)) {
                $trouve = true;
                continue;   // ce code vient d’être consommé, il ne resservira pas
            }
            $restants[] = $ligne;
        }

        if (!$trouve) {
            return false;
        }

        Utilisateur::modifier($utilisateurId, [
            'totp_codes_secours' => $restants === [] ? null : implode("\n", $restants),
        ]);

        return true;
    }

    /** Nombre de codes de secours encore utilisables. */
    public static function restants(?array $utilisateur): int
    {
        return count(self::lignesSecours($utilisateur ?? []));
    }

    /**
     * Remplace les codes de secours et rend les nouveaux.
     *
     * @return list<string>
     */
    public static function regenererCodes(int $utilisateurId): array
    {
        $codes = self::genererCodes(SECURITE_2FA_CODES_SECOURS);

        Utilisateur::modifier($utilisateurId, [
            'totp_codes_secours' => implode("\n", array_map([self::class, 'empreinte'], $codes)),
        ]);

        return array_map([self::class, 'presenter'], $codes);
    }

    /** Retire le second facteur du compte. */
    public static function desactiver(int $utilisateurId): void
    {
        Utilisateur::modifier($utilisateurId, [
            'totp_secret'        => null,
            'totp_actif'         => 0,
            'totp_confirme_le'   => null,
            'totp_dernier_pas'   => null,
            'totp_codes_secours' => null,
        ]);
    }

    // -----------------------------------------------------------------------
    // Interne
    // -----------------------------------------------------------------------

    /**
     * Tire des codes de secours.
     *
     * @return list<string>
     */
    private static function genererCodes(int $nombre): array
    {
        $codes = [];
        $max   = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $nombre; $i++) {
            $code = '';
            for ($c = 0; $c < self::LONGUEUR; $c++) {
                $code .= self::ALPHABET[random_int(0, $max)];
            }
            $codes[] = $code;
        }

        return $codes;
    }

    /** Empreinte d’un code de secours : le clair n’est jamais rangé. */
    private static function empreinte(string $code): string
    {
        return hash('sha256', self::normaliser($code));
    }

    /** Ajoute le tiret de lecture : XXXX-XXXX. */
    private static function presenter(string $code): string
    {
        return substr($code, 0, 4) . '-' . substr($code, 4);
    }

    /** Retire tirets, espaces et minuscules avant comparaison. */
    private static function normaliser(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /**
     * Empreintes rangées pour un compte.
     *
     * @return list<string>
     */
    private static function lignesSecours(array $utilisateur): array
    {
        $brut = (string) ($utilisateur['totp_codes_secours'] ?? '');

        $lignes = [];
        foreach (preg_split('/\R/', $brut) ?: [] as $ligne) {
            $ligne = trim($ligne);
            if ($ligne !== '') {
                $lignes[] = $ligne;
            }
        }

        return $lignes;
    }
}
