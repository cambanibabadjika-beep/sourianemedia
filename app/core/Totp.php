<?php
/**
 * SOURIANE — Codes à usage unique fondés sur le temps (RFC 6238)
 *
 * Le chapitre 8 impose une authentification à deux facteurs sur les comptes
 * d’administration. L’algorithme retenu est le TOTP, celui que mettent en
 * œuvre les applications d’authentification courantes : un secret partagé,
 * un compteur de pas de trente secondes, un code de six chiffres.
 *
 * Aucune dépendance externe : le calcul tient en une vingtaine de lignes et
 * repose sur HMAC-SHA1, disponible partout. Ajouter une bibliothèque pour cela
 * alourdirait l’installation sans rien apporter — le projet se passe de
 * gestionnaire de dépendances.
 *
 * Paramètres : SHA-1, 6 chiffres, pas de 30 secondes, secret de 160 bits. Ce
 * sont les valeurs par défaut de la RFC 6238 et celles que tout le monde
 * suppose ; s’en écarter sans raison romprait la compatibilité des
 * applications d’authentification.
 */

declare(strict_types=1);

namespace Souriane\Core;

use InvalidArgumentException;

final class Totp
{
    /** Durée d’un intervalle, en secondes. */
    public const PERIODE = 30;

    /** Nombre de chiffres du code. */
    public const CHIFFRES = 6;

    /**
     * Tolérance, exprimée en intervalles de part et d’autre du pas courant.
     *
     * Une horloge de téléphone dérive de quelques secondes. Sans tolérance,
     * un code valide serait refusé à la seconde près du changement de pas. Un
     * intervalle en arrière et un en avant suffisent : au-delà, on élargit
     * inutilement la fenêtre d’un code intercepté.
     */
    public const TOLERANCE = 1;

    /** Alphabet base32 sans les caractères ambigus (RFC 4648). */
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Longueur du secret en octets : 20 octets = 160 bits, recommandation RFC 4226. */
    private const OCTETS_SECRET = 20;

    /** Génère un secret aléatoire, en base32 sans remplissage. */
    public static function secret(int $octets = self::OCTETS_SECRET): string
    {
        if ($octets < 10) {
            throw new InvalidArgumentException('Un secret de moins de 80 bits est trop court.');
        }

        return self::encoderBase32(random_bytes($octets));
    }

    /** Intervalle courant depuis l’origine Unix. */
    public static function pasCourant(?int $horodatage = null): int
    {
        return intdiv($horodatage ?? time(), self::PERIODE);
    }

    /** Code attendu pour un intervalle donné. */
    public static function code(string $secret, ?int $pas = null): string
    {
        $pas = $pas ?? self::pasCourant();
        $cle = self::decoderBase32($secret);

        if ($cle === '') {
            throw new InvalidArgumentException('Secret illisible.');
        }

        // Compteur sur huit octets, poids fort en tête (RFC 4226, § 5.1).
        // Sur un entier 32 bits, le décalage de 32 donnerait toujours zéro :
        // la valeur reste donc découpée en deux moitiés de 32 bits.
        $compteur = pack('N2', ($pas >> 32) & 0xFFFFFFFF, $pas & 0xFFFFFFFF);

        $empreinte = hash_hmac('sha1', $compteur, $cle, true);

        // Troncature dynamique (RFC 4226, § 5.3) : les quatre derniers bits de
        // l’empreinte désignent l’offset des quatre octets à lire, dont le bit
        // de poids fort est masqué pour rester positif.
        $offset = ord($empreinte[19]) & 0x0F;
        $binaire = ((ord($empreinte[$offset]) & 0x7F) << 24)
            | ((ord($empreinte[$offset + 1]) & 0xFF) << 16)
            | ((ord($empreinte[$offset + 2]) & 0xFF) << 8)
            | (ord($empreinte[$offset + 3]) & 0xFF);

        $module = 10 ** self::CHIFFRES;

        return str_pad((string) ($binaire % $module), self::CHIFFRES, '0', STR_PAD_LEFT);
    }

    /**
     * Vérifie un code et retourne l’intervalle consommé, ou null si le code
     * n’est pas valide.
     *
     * `$dernierPas` est l’intervalle déjà consommé par ce compte. Tout code
     * dont l’intervalle lui est antérieur ou égal est refusé : sans cela, un
     * code observé par-dessus une épaule reste utilisable pendant les trente
     * secondes qui suivent, et un même code peut être rejoué. C’est la
     * propriété d’usage unique, que la RFC 6238 § 5.2 impose de respecter.
     */
    public static function verifier(
        string $secret,
        string $saisie,
        ?int $dernierPas = null,
        ?int $horodatage = null
    ): ?int {
        // Les applications affichent volontiers « 123 456 » : les séparateurs
        // sont retirés avant comparaison.
        $saisie = (string) preg_replace('/\D/', '', $saisie);
        if (strlen($saisie) !== self::CHIFFRES) {
            return null;
        }

        $pas = self::pasCourant($horodatage);

        for ($ecart = -self::TOLERANCE; $ecart <= self::TOLERANCE; $ecart++) {
            $candidat = $pas + $ecart;

            if ($dernierPas !== null && $candidat <= $dernierPas) {
                continue;
            }
            if (hash_equals(self::code($secret, $candidat), $saisie)) {
                return $candidat;
            }
        }

        return null;
    }

    /**
     * URI d’enrôlement, à encoder dans le QR code que lit l’application
     * d’authentification.
     *
     * Les paramètres sont écrits explicitement, bien qu’ils correspondent aux
     * valeurs par défaut : certaines applications les exigent, et les écrire
     * évite qu’une évolution des constantes ci-dessus ne désynchronise
     * silencieusement les comptes déjà enrôlés.
     */
    public static function uri(string $secret, string $compte, string $emetteur): string
    {
        return 'otpauth://totp/' . rawurlencode($emetteur . ':' . $compte)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($emetteur)
            . '&algorithm=SHA1'
            . '&digits=' . self::CHIFFRES
            . '&period=' . self::PERIODE;
    }

    /** Encode en base32 sans remplissage (RFC 4648 § 6). */
    private static function encoderBase32(string $donnees): string
    {
        $bits = '';
        for ($i = 0, $n = strlen($donnees); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($donnees[$i])), 8, '0', STR_PAD_LEFT);
        }

        $sortie = '';
        // Le dernier groupe, s’il est incomplet, est complété à droite par des
        // zéros : c’est la convention de la RFC, et c’est ce que font les
        // applications d’authentification en relisant le secret.
        foreach (str_split($bits, 5) as $groupe) {
            $sortie .= self::ALPHABET[bindec(str_pad($groupe, 5, '0'))];
        }

        return $sortie;
    }

    /** Décode un secret base32, en ignorant espaces, tirets et remplissage. */
    private static function decoderBase32(string $valeur): string
    {
        $valeur = strtoupper((string) preg_replace('/[^A-Za-z2-7]/', '', $valeur));

        $bits = '';
        for ($i = 0, $n = strlen($valeur); $i < $n; $i++) {
            $position = strpos(self::ALPHABET, $valeur[$i]);
            if ($position === false) {
                return '';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $sortie = '';
        foreach (str_split($bits, 8) as $groupe) {
            if (strlen($groupe) < 8) {
                // Reste incomplet : ce sont les bits de remplissage ajoutés à
                // l’encodage, ils ne portent aucune donnée.
                break;
            }
            $sortie .= chr(bindec($groupe));
        }

        return $sortie;
    }
}
