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
define('WHATSAPP_REGIE', (string) env('WHATSAPP_REGIE', ''));

// Jeton d’accès à la tâche planifiée, pour les hébergements sans crontab.
define('CRON_TOKEN', (string) env('CRON_TOKEN', ''));

define('STORAGE_PATH', SOURIANE_ROOT . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
define('LOG_PATH', STORAGE_PATH . '/logs');

// Réglages d’exploitation issus du cahier des charges
define('PLAFOND_NOTIFICATIONS_MOIS', 4);   // ch. 5.5
define('DUREE_SESSION_MINUTES', 30);       // fenêtre de décompte d’une visite

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
