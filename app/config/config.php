<?php
/**
 * SOURIANE — Chargement de l’environnement et configuration
 *
 * Lit le fichier .env (sans dépendance externe), puis expose la configuration
 * sous forme de constantes et via la fonction config().
 */

declare(strict_types=1);

/**
 * Charge un fichier .env dans $_ENV / $_SERVER.
 */
function env_load(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Retire les guillemets encadrants
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

/**
 * Lit une variable d’environnement avec valeur par défaut.
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        default            => $value,
    };
}

$root = dirname(__DIR__, 2);
env_load($root . '/.env');

// ---------------------------------------------------------------------------
// Constantes applicatives
// ---------------------------------------------------------------------------
define('SOURIANE_ROOT', $root);
define('APP_ENV', (string) env('APP_ENV', 'production'));
define('APP_DEBUG', (bool) env('APP_DEBUG', false));
define('APP_NAME', (string) env('APP_NAME', 'Souriane'));
define('APP_SLOGAN', (string) env('APP_SLOGAN', 'Votre panneau vous parle'));
define('APP_URL', rtrim((string) env('APP_URL', ''), '/'));
define('APP_VERSION', '1.0.0');

define('DB_HOST', (string) env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (string) env('DB_PORT', '3306'));
define('DB_NAME', (string) env('DB_NAME', 'souriane'));
define('DB_USER', (string) env('DB_USER', 'root'));
define('DB_PASS', (string) env('DB_PASS', ''));
define('DB_CHARSET', (string) env('DB_CHARSET', 'utf8mb4'));

define('COURT_DOMAINE', (string) env('COURT_DOMAINE', 'sr.n'));
define('HASH_SECRET', (string) env('HASH_SECRET', 'souriane-dev-secret'));
define('SESSION_NAME', (string) env('SESSION_NAME', 'souriane_session'));

define('VAPID_SUBJECT', (string) env('VAPID_SUBJECT', 'mailto:contact@souriane.sn'));
define('VAPID_PUBLIC_KEY', (string) env('VAPID_PUBLIC_KEY', ''));
define('VAPID_PRIVATE_KEY', (string) env('VAPID_PRIVATE_KEY', ''));

define('RATE_LIMIT_WINDOW', (int) env('RATE_LIMIT_WINDOW', 60));
define('RATE_LIMIT_MAX', (int) env('RATE_LIMIT_MAX', 60));

/*
 * Verrouillage des connexions (ch. 8).
 *
 * Seules les tentatives ÉCHOUÉES comptent, et une connexion réussie remet le
 * compteur à zéro. Cinq mots de passe erronés bloquent donc l’adresse pendant
 * trois minutes : assez pour arrêter une attaque par dictionnaire, assez court
 * pour ne pas punir une faute de frappe sur un clavier de téléphone.
 *
 * Réglable dans .env (SECURITE_CONNEXION_MAX_ECHECS, SECURITE_CONNEXION_FENETRE).
 */
define('SECURITE_CONNEXION_MAX_ECHECS', (int) env('SECURITE_CONNEXION_MAX_ECHECS', 5));
define('SECURITE_CONNEXION_FENETRE', (int) env('SECURITE_CONNEXION_FENETRE', 180));

/** Tentatives de code à deux facteurs avant blocage temporaire. */
define('SECURITE_CODE_MAX_ESSAIS', (int) env('SECURITE_CODE_MAX_ESSAIS', 10));
define('SECURITE_CODE_FENETRE', (int) env('SECURITE_CODE_FENETRE', 300));

define('WHATSAPP_REGIE', (string) env('WHATSAPP_REGIE', ''));

// Jeton d’accès à la tâche planifiée, pour les hébergements sans crontab.
define('CRON_TOKEN', (string) env('CRON_TOKEN', ''));

define('STORAGE_PATH', SOURIANE_ROOT . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
define('LOG_PATH', STORAGE_PATH . '/logs');

// Réglages d’exploitation issus du cahier des charges
define('PLAFOND_NOTIFICATIONS_MOIS', 4);   // ch. 5.5
define('DUREE_SESSION_MINUTES', 30);       // fenêtre de décompte d’une visite

/*
 * Second facteur (ch. 8) : « Les comptes d’administration sont protégés par
 * une authentification à deux facteurs. »
 *
 * La régie pilote toute la plateforme, l’agent terrain agit sur le parc, les
 * poses et les codes : ce sont les deux comptes d’administration, et le second
 * facteur y est obligatoire. L’annonceur est un compte client — il peut
 * l’activer, rien ne l’y contraint.
 */
// Désactivé pour les tests locaux (Nazaire, 16/10) : on vide la liste pour ne
// forcer l’enrôlement de personne. Remettre ['regie', 'agent'] pour réactiver
// la politique de sécurité (les comptes conservent leur état totp_actif).
define('SECURITE_2FA_ROLES', []);

/*
 * Second facteur — dérogation de développement (SECURITE_2FA_SANS_CODE).
 *
 * SECURITE_2FA_ROLES ne couvre que l’enrôlement IMPOSÉ : vider la liste
 * dispense de mettre un second facteur en place, mais ne dispense pas de
 * saisir un code sur un compte DÉJÀ enrôlé. Les deux réglages sont donc
 * distincts, et c’est ce second réglage qui allège une phase de dev.
 *
 * Cette dérogation dispense de saisir le code à la connexion. Elle ne touche
 * à rien de ce qui est rangé : secrets et codes de secours restent en base, et
 * un compte enrôlé le reste. Repasser le réglage à false rétablit l’exigence
 * sans réenrôlement.
 *
 * Elle est refusée en production : une dérogation qui pourrait suivre le code
 * jusqu’en production n’est plus un confort de développement, c’est une porte
 * dérobée — et le chapitre 8 impose le second facteur aux comptes
 * d’administration.
 */
$sansCode2fa = (bool) env('SECURITE_2FA_SANS_CODE', false);
if ($sansCode2fa && APP_ENV === 'production') {
    error_log('SECURITE_2FA_SANS_CODE ignoré : cette dérogation est refusée en production.');
    $sansCode2fa = false;
}
define('SECURITE_2FA_SANS_CODE', $sansCode2fa);

/** Nombre de codes de secours remis à l’activation (téléphone perdu). */
define('SECURITE_2FA_CODES_SECOURS', 8);

/**
 * Délai laissé pour saisir le code, ou pour mener l’enrôlement à son terme.
 * Au-delà, le mot de passe doit être ressaisi : une connexion à moitié faite
 * ne doit pas rester ouverte indéfiniment sur un poste partagé.
 */
define('SECURITE_2FA_DELAI', 600);

/** Émetteur affiché dans l’application d’authentification. */
define('SECURITE_2FA_EMETTEUR', (string) env('SECURITE_2FA_EMETTEUR', 'Souriane'));

/**
 * Clé partagée des comptes de démonstration.
 *
 * Renseignée par cron/preparer_2fa_demo.php, qui refuse de s’exécuter hors
 * mode démonstration. Elle n’est affichée que lorsque APP_DEBUG est vrai —
 * au même titre que le mot de passe de démonstration imprimé sur l’écran de
 * connexion. En production la valeur reste vide et rien n’est divulgué.
 */
define('SECURITE_2FA_SECRET_DEMO', (string) env('SECURITE_2FA_SECRET_DEMO', ''));

/**
 * Détecte l’URL de base si APP_URL n’est pas renseignée.
 */
if (APP_URL === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $script = rtrim($script, '/');
    define('BASE_URL', $scheme . '://' . $host . $script);
} else {
    define('BASE_URL', APP_URL);
}

/**
 * Accès ponctuel à la configuration sous forme de tableau.
 */
function config(string $key, mixed $default = null): mixed
{
    $map = [
        'app.name'    => APP_NAME,
        'app.slogan'  => APP_SLOGAN,
        'app.url'     => BASE_URL,
        'app.env'     => APP_ENV,
        'app.debug'   => APP_DEBUG,
        'db.name'     => DB_NAME,
        'court.domaine' => COURT_DOMAINE,
    ];

    return $map[$key] ?? $default;
}
