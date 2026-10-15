<?php
/**
 * SOURIANE — Amorçage de l’application
 * Autoload, configuration, session et gestion des erreurs.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers.php';

// ---------------------------------------------------------------------------
// Autoload PSR-4 : Souriane\Core\Database → app/core/Database.php
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $classe): void {
    $prefixe = 'Souriane\\';
    if (!str_starts_with($classe, $prefixe)) {
        return;
    }

    $relatif = substr($classe, strlen($prefixe));
    $chemin  = __DIR__ . '/' . str_replace('\\', '/', $relatif) . '.php';

    if (is_file($chemin)) {
        require_once $chemin;
    }
});

// ---------------------------------------------------------------------------
// Gestion des erreurs
// ---------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php-error.log');

// Toute sortie est tamponnée : un avertissement ne peut pas corrompre les
// en-têtes HTTP. Le tampon est vidé par Response::envoyer().
ob_start();

set_exception_handler(static function (\Throwable $e): void {
    journal_technique('erreur', 'Exception non interceptée : ' . $e->getMessage(), [
        'fichier' => $e->getFile() . ':' . $e->getLine(),
    ]);

    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    if (APP_DEBUG) {
        echo '<div style="font-family:monospace;padding:24px;background:#1A1033;color:#f8fafc">'
            . '<h1 style="color:#FBBF24">Erreur fatale</h1>'
            . '<p>' . e($e->getMessage()) . '</p>'
            . '<p style="color:#94a3b8">' . e($e->getFile() . ':' . $e->getLine()) . '</p>'
            . '<pre style="white-space:pre-wrap">' . e($e->getTraceAsString()) . '</pre></div>';
    } else {
        echo '<h1>Une erreur est survenue</h1>';
    }
});

// ---------------------------------------------------------------------------
// Session et en-têtes de sécurité
// ---------------------------------------------------------------------------
\Souriane\Core\Session::demarrer();

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=(self)');
    if (est_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

date_default_timezone_set('Africa/Dakar');
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'french');

// Le fuseau de la base doit correspondre au fuseau applicatif
\Souriane\Core\Database::instance()->query("SET time_zone = '+00:00'");
