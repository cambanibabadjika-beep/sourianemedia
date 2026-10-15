<?php
/**
 * SOURIANE — Notifications push Web (ch. 5.5)
 *
 * Implémentation autonome du protocole Web Push :
 *   – signature VAPID (JWT ES256) ;
 *   – chiffrement du contenu selon la RFC 8291 (aes128gcm) ;
 *   – envoi vers le point de terminaison de l’abonné.
 *
 * Le serveur signe ses envois à l’aide d’une paire de clés dédiée, conservée
 * hors du dépôt de code (table parametres ou variables d’environnement).
 */

declare(strict_types=1);

namespace Souriane\Services;

use RuntimeException;
use Souriane\Models\Parametre;

final class PushService
{
    /** Durée de vie d’un message en file d’attente du service d’envoi (4 semaines). */
    private const TTL = 2419200;

    /** Taille maximale d’un enregistrement de contenu. */
    private const TAILLE_ENREGISTREMENT = 4096;

    private static ?string $cheminConf = null;

    // =======================================================================
    // Clés VAPID
    // =======================================================================

    /**
     * Génère une paire de clés VAPID (courbe P-256).
     *
     * @return array{publique:string, privee:string} Valeurs encodées en base64url
     */
    public static function genererClesVapid(): array
    {
        $cle = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
            'config'           => self::cheminConf(),
        ]);

        if ($cle === false) {
            throw new RuntimeException('Génération de clé EC impossible : ' . self::derniereErreur());
        }

        $details = openssl_pkey_get_details($cle);
        $x = $details['ec']['x'];
        $y = $details['ec']['y'];
        $d = $details['ec']['d'];

        return [
            'publique' => self::base64Url(("\x04") . $x . $y),
            'privee'   => self::base64Url($d),
        ];
    }

    /** Clé publique VAPID active. */
    public static function clePublique(): string
    {
        $cle = VAPID_PUBLIC_KEY !== '' ? VAPID_PUBLIC_KEY : (string) Parametre::lire('cle_publique_vapid', '');

        return trim($cle);
    }

    /** Clé privée VAPID active. */
    private static function clePrivee(): string
    {
        $cle = VAPID_PRIVATE_KEY !== '' ? VAPID_PRIVATE_KEY : (string) Parametre::lire('cle_privee_vapid', '');

        return trim($cle);
    }

    /** Indique si les notifications peuvent être signées. */
    public static function configure(): bool
    {
        return self::clePublique() !== '' && self::clePrivee() !== '';
    }

    // =======================================================================
    // Envoi
    // =======================================================================

    /**
     * Envoie une notification à un abonné.
     *
     * @param array $abonne  Ligne de la table abonnes
     * @param array $charge  {titre, corps, icone, url}
     *
     * @return array{ok:bool, statut:string, erreur:?string}
     */
    public static function envoyer(array $abonne, array $charge): array
    {
        if (!self::configure()) {
            return ['ok' => false, 'statut' => 'echec', 'erreur' => 'Clés VAPID non configurées.'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'statut' => 'echec', 'erreur' => 'Extension cURL indisponible.'];
        }

        try {
            $corps = self::chiffrer($charge, (string) $abonne['cle_p256dh'], (string) $abonne['cle_auth']);
        } catch (\Throwable $e) {
            return ['ok' => false, 'statut' => 'echec', 'erreur' => 'Chiffrement : ' . $e->getMessage()];
        }

        $endpoint = (string) $abonne['endpoint'];

        try {
            $jwt = self::jetonVapid($endpoint);
        } catch (\Throwable $e) {
            return ['ok' => false, 'statut' => 'echec', 'erreur' => 'Signature : ' . $e->getMessage()];
        }

        $entetes = [
            'Authorization: vapid t=' . $jwt . ', k=' . self::clePublique(),
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: ' . self::TTL,
            'Urgency: normal',
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $corps,
            CURLOPT_HTTPHEADER     => $entetes,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => APP_NAME . '/' . APP_VERSION,
        ]);

        $reponse = curl_exec($ch);
        $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreur  = curl_error($ch);
        curl_close($ch);

        if ($reponse === false) {
            return ['ok' => false, 'statut' => 'echec', 'erreur' => 'Réseau : ' . $erreur];
        }

        return match (true) {
            $code >= 200 && $code < 300 => ['ok' => true, 'statut' => 'envoye', 'erreur' => null],
            $code === 404 || $code === 410 => ['ok' => false, 'statut' => 'expire', 'erreur' => 'Abonnement expiré (HTTP ' . $code . ')'],
            $code === 429                  => ['ok' => false, 'statut' => 'echec', 'erreur' => 'Quota du service d’envoi dépassé (HTTP 429)'],
            $code === 413                  => ['ok' => false, 'statut' => 'echec', 'erreur' => 'Message trop volumineux (HTTP 413)'],
            default                        => ['ok' => false, 'statut' => 'echec', 'erreur' => 'HTTP ' . $code . ' — ' . mb_substr((string) $reponse, 0, 180)],
        };
    }

    // =======================================================================
    // Chiffrement (RFC 8291 / RFC 8188)
    // =======================================================================

    /** Chiffre la charge utile pour un abonné donné. */
    public static function chiffrer(array $charge, string $p256dhBase64, string $authBase64): string
    {
        $uaPublic = self::base64UrlDecode($p256dhBase64);
        $authSecret = self::base64UrlDecode($authBase64);

        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04") {
            throw new RuntimeException('Clé publique de l’abonné invalide.');
        }
        if (strlen($authSecret) !== 16) {
            throw new RuntimeException('Secret d’authentification invalide.');
        }

        // Paire éphémère du serveur
        $ephemere = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
            'config'           => self::cheminConf(),
        ]);
        if ($ephemere === false) {
            throw new RuntimeException('Génération de clé éphémère impossible.');
        }

        $details = openssl_pkey_get_details($ephemere);
        $asPublic = "\x04" . $details['ec']['x'] . $details['ec']['y'];

        $uaKey = self::clePubliqueDepuisPoint($uaPublic);

        // Ordre attendu par PHP : (clé publique, clé privée)
        self::viderErreurs();
        $secretPartage = openssl_pkey_derive($uaKey, $ephemere, 32);
        if ($secretPartage === false || $secretPartage === '') {
            throw new RuntimeException('Échange de clés impossible : ' . self::derniereErreur());
        }

        // Dérivation des clés (RFC 8291)
        $info = "WebPush: info\x00" . $uaPublic . $asPublic;
        $ikm  = self::hkdfExpand(self::hkdfExtract($authSecret, $secretPartage), $info, 32);

        $sel       = random_bytes(16);
        $prk       = self::hkdfExtract($sel, $ikm);
        $cek       = self::hkdfExpand($prk, "Content-Encoding: aes128gcm\x00", 16);
        $nonce     = self::hkdfExpand($prk, "Content-Encoding: nonce\x00", 12);

        $clair = json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($clair === false) {
            throw new RuntimeException('Sérialisation du message impossible.');
        }

        // Délimiteur de dernier enregistrement
        $clair .= "\x02";

        $tag = '';
        $chiffre = openssl_encrypt($clair, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($chiffre === false) {
            throw new RuntimeException('Chiffrement AES-GCM impossible.');
        }

        // En-tête RFC 8188 : sel(16) | taille enregistrement(4) | longueur clé(1) | clé
        return $sel
            . pack('N', self::TAILLE_ENREGISTREMENT)
            . chr(strlen($asPublic))
            . $asPublic
            . $chiffre
            . $tag;
    }

    // =======================================================================
    // Jeton VAPID
    // =======================================================================

    /** Construit le jeton JWT signé en ES256 pour un point de terminaison. */
    public static function jetonVapid(string $endpoint): string
    {
        $parties = parse_url($endpoint);
        if ($parties === false || empty($parties['scheme']) || empty($parties['host'])) {
            throw new RuntimeException('Point de terminaison invalide.');
        }
        $audience = $parties['scheme'] . '://' . $parties['host'];

        $entete  = self::base64Url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $charge  = self::base64Url(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => VAPID_SUBJECT,
        ]));

        $aSigner = $entete . '.' . $charge;

        $privee = self::clePriveeDepuisD(self::base64UrlDecode(self::clePrivee()));

        $signatureDer = '';
        $ok = openssl_sign($aSigner, $signatureDer, $privee, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new RuntimeException('Signature JWT impossible : ' . self::derniereErreur());
        }

        return $aSigner . '.' . self::base64Url(self::derVersBrut($signatureDer));
    }

    // =======================================================================
    // Encodage DER et utilitaires cryptographiques
    // =======================================================================

    private static function clePubliqueDepuisPoint(string $point): \OpenSSLAsymmetricKey
    {
        $spki = self::derSequence(
            self::derSequence(
                self::derOid('2A8648CE3D0201') . self::derOid('2A8648CE3D030107')
            )
            . self::derBitString($point)
        );

        $pem = self::pem('PUBLIC KEY', $spki);
        $cle = openssl_pkey_get_public($pem);
        if ($cle === false) {
            throw new RuntimeException('Clé publique illisible.');
        }

        return $cle;
    }

    private static function clePriveeDepuisD(string $d): \OpenSSLAsymmetricKey
    {
        if (strlen($d) !== 32) {
            throw new RuntimeException('Clé privée VAPID invalide.');
        }

        // Reconstruit la clé publique à partir de la privée
        $priveeSec1 = self::derSequence(
            self::derInteger("\x01")
            . self::derOctetString($d)
            . self::derTagged(0, self::derOid('2A8648CE3D030107'))
        );

        $pkcs8 = self::derSequence(
            self::derInteger("\x00")
            . self::derSequence(self::derOid('2A8648CE3D0201') . self::derOid('2A8648CE3D030107'))
            . self::derOctetString($priveeSec1)
        );

        $cle = openssl_pkey_get_private(self::pem('PRIVATE KEY', $pkcs8));
        if ($cle === false) {
            throw new RuntimeException('Clé privée VAPID illisible : ' . self::derniereErreur());
        }

        return $cle;
    }

    /** Convertit une signature DER (r, s) en concaténation brute de 64 octets. */
    private static function derVersBrut(string $der): string
    {
        $offset = 0;
        if (($der[$offset] ?? '') !== "\x30") {
            throw new RuntimeException('Signature DER inattendue.');
        }
        $offset++;
        self::longueurDer($der, $offset); // avance l’offset au-delà des octets de longueur

        $parties = [];
        for ($k = 0; $k < 2; $k++) {
            if (($der[$offset] ?? '') !== "\x02") {
                throw new RuntimeException('Entier DER attendu.');
            }
            $offset++;
            $longueur = self::longueurDer($der, $offset);
            $valeur = substr($der, $offset, $longueur);
            $offset += $longueur;

            // Retire les zéros de tête puis complète à 32 octets
            $valeur = ltrim($valeur, "\x00");
            $parties[] = str_pad($valeur, 32, "\x00", STR_PAD_LEFT);
        }

        return $parties[0] . $parties[1];
    }

    private static function longueurDer(string $der, int &$offset): int
    {
        $octet = ord($der[$offset]);
        $offset++;
        if ($octet < 0x80) {
            return $octet;
        }
        $nb = $octet & 0x7F;
        $longueur = 0;
        for ($i = 0; $i < $nb; $i++) {
            $longueur = ($longueur << 8) | ord($der[$offset]);
            $offset++;
        }

        return $longueur;
    }

    private static function derLongueur(int $longueur): string
    {
        if ($longueur < 0x80) {
            return chr($longueur);
        }
        $octets = '';
        while ($longueur > 0) {
            $octets = chr($longueur & 0xFF) . $octets;
            $longueur >>= 8;
        }

        return chr(0x80 | strlen($octets)) . $octets;
    }

    private static function derSequence(string $contenu): string
    {
        return "\x30" . self::derLongueur(strlen($contenu)) . $contenu;
    }

    private static function derInteger(string $octets): string
    {
        if ($octets !== '' && (ord($octets[0]) & 0x80)) {
            $octets = "\x00" . $octets;
        }

        return "\x02" . self::derLongueur(strlen($octets)) . $octets;
    }

    private static function derOctetString(string $octets): string
    {
        return "\x04" . self::derLongueur(strlen($octets)) . $octets;
    }

    private static function derBitString(string $octets): string
    {
        $contenu = "\x00" . $octets;

        return "\x03" . self::derLongueur(strlen($contenu)) . $contenu;
    }

    private static function derTagged(int $tag, string $contenu): string
    {
        return chr(0xA0 | $tag) . self::derLongueur(strlen($contenu)) . $contenu;
    }

    private static function derOid(string $hex): string
    {
        return "\x06" . self::derLongueur((int) (strlen($hex) / 2)) . hex2bin($hex);
    }

    private static function pem(string $etiquette, string $der): string
    {
        return "-----BEGIN {$etiquette}-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END {$etiquette}-----\n";
    }

    // =======================================================================
    // HKDF
    // =======================================================================

    private static function hkdfExtract(string $sel, string $ikm): string
    {
        return hash_hmac('sha256', $ikm, $sel, true);
    }

    private static function hkdfExpand(string $prk, string $info, int $longueur): string
    {
        $resultat = '';
        $bloc = '';
        $compteur = 1;
        while (strlen($resultat) < $longueur) {
            $bloc = hash_hmac('sha256', $bloc . $info . chr($compteur), $prk, true);
            $resultat .= $bloc;
            $compteur++;
        }

        return substr($resultat, 0, $longueur);
    }

    // =======================================================================
    // base64url
    // =======================================================================

    public static function base64Url(string $donnees): string
    {
        return rtrim(strtr(base64_encode($donnees), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $texte): string
    {
        $texte = strtr($texte, '-_', '+/');
        $reste = strlen($texte) % 4;
        if ($reste > 0) {
            $texte .= str_repeat('=', 4 - $reste);
        }

        $decode = base64_decode($texte, true);

        return $decode === false ? '' : $decode;
    }

    // =======================================================================
    // Divers
    // =======================================================================

    /** Localise le fichier openssl.cnf utilisé par le binaire PHP courant. */
    private static function cheminConf(): ?string
    {
        if (self::$cheminConf !== null) {
            return self::$cheminConf === '' ? null : self::$cheminConf;
        }

        $candidats = [];

        $env = getenv('OPENSSL_CONF');
        if (is_string($env) && $env !== '') {
            $candidats[] = $env;
        }

        $binaire = PHP_BINARY;
        if ($binaire !== '') {
            $candidats[] = dirname($binaire) . '/extras/ssl/openssl.cnf';
        }
        $candidats[] = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        $candidats[] = 'C:/wamp64/bin/php/php8.3.14/extras/ssl/openssl.cnf';

        foreach ($candidats as $chemin) {
            if (is_file($chemin)) {
                self::$cheminConf = $chemin;

                return $chemin;
            }
        }

        self::$cheminConf = '';

        return null;
    }

    private static function derniereErreur(): string
    {
        $messages = [];
        while ($e = openssl_error_string()) {
            $messages[] = $e;
        }

        return $messages === [] ? 'cause inconnue' : implode(' | ', array_slice($messages, -3));
    }

    /** Vide la file d’erreurs OpenSSL (évite les messages périmés). */
    private static function viderErreurs(): void
    {
        while (openssl_error_string()) {
            // volontairement vide
        }
    }
}
