<?php
/**
 * SOURIANE — Coffre des secrets relus par l’application
 *
 * Un mot de passe se hache : on ne veut jamais pouvoir le relire. Un secret de
 * second facteur, lui, doit être relu à chaque connexion pour recalculer le
 * code attendu. Il ne peut donc pas être haché — il est chiffré.
 *
 * La clé est dérivée de HASH_SECRET par HKDF, avec une information de
 * contexte propre à ce coffre. La dérivation sépare les usages : l’empreinte
 * non réversible des adresses réseau d’un côté, ce coffre de l’autre. Une clé
 * qui servirait aux deux usages les lierait indûment, et un changement de
 * l’un obligerait à tout réécrire.
 *
 * AES-256-GCM est retenu plutôt que le chiffrement authentifié de sodium :
 * l’extension sodium n’est pas garantie sur toutes les installations, tandis
 * qu’openssl l’est. Le mode GCM apporte l’authentification — un bloc modifié
 * ne se déchiffre pas, il est rejeté.
 */

declare(strict_types=1);

namespace Souriane\Core;

use RuntimeException;

final class Coffre
{
    /** Information de contexte : change si le format du coffre change. */
    private const CONTEXTE = 'souriane/coffre/v1';

    private const METHODE = 'aes-256-gcm';

    /** Taille du vecteur d’initialisation et de l’étiquette d’authentification. */
    private const TAILLE_NONCE = 12;
    private const TAILLE_ETIQUETTE = 16;

    /** Version du format, écrite en tête du bloc. */
    private const VERSION = 'v1';

    /** Clé de chiffrement, dérivée de HASH_SECRET. */
    private static function cle(): string
    {
        return hash_hkdf('sha256', HASH_SECRET, 32, self::CONTEXTE);
    }

    /**
     * Chiffre une valeur courte et retourne un bloc de la forme
     * « v1.base64(nonce | étiquette | chiffré) ».
     *
     * Le nonce est tiré au hasard à chaque appel : chiffrer deux fois la même
     * valeur produit deux blocs différents, ce qui évite d’apprendre quelque
     * chose en comparant deux lignes de la base.
     */
    public static function sceller(string $clair): string
    {
        $nonce   = random_bytes(self::TAILLE_NONCE);
        $etiquette = '';

        $chiffre = openssl_encrypt(
            $clair,
            self::METHODE,
            self::cle(),
            OPENSSL_RAW_DATA,
            $nonce,
            $etiquette,
            '',
            self::TAILLE_ETIQUETTE
        );

        if ($chiffre === false) {
            throw new RuntimeException('Chiffrement impossible : ' . openssl_error_string());
        }

        return self::VERSION . '.' . base64_encode($nonce . $etiquette . $chiffre);
    }

    /**
     * Déchiffre un bloc produit par sceller().
     *
     * Retourne null si le bloc est absent, tronqué, d’une version inconnue ou
     * modifié. L’appelant doit traiter null comme « second facteur
     * indisponible » et non comme un succès.
     */
    public static function ouvrir(?string $bloc): ?string
    {
        $bloc = (string) $bloc;
        if (!str_starts_with($bloc, self::VERSION . '.')) {
            return null;
        }

        $brut = base64_decode(substr($bloc, strlen(self::VERSION) + 1), true);
        $minimum = self::TAILLE_NONCE + self::TAILLE_ETIQUETTE + 1;
        if ($brut === false || strlen($brut) < $minimum) {
            return null;
        }

        $nonce     = substr($brut, 0, self::TAILLE_NONCE);
        $etiquette = substr($brut, self::TAILLE_NONCE, self::TAILLE_ETIQUETTE);
        $chiffre   = substr($brut, self::TAILLE_NONCE + self::TAILLE_ETIQUETTE);

        $clair = openssl_decrypt(
            $chiffre,
            self::METHODE,
            self::cle(),
            OPENSSL_RAW_DATA,
            $nonce,
            $etiquette
        );

        return $clair === false ? null : $clair;
    }
}
