<?php
/**
 * SOURIANE — Génération des clés VAPID
 *
 * Les clés de signature des notifications sont conservées hors du dépôt de
 * code (ch. 6.2). Ce script les génère et les écrit dans .env et dans la
 * table parametres.
 *
 * Utilisation :
 *   php cron/generer_cles.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use Souriane\Models\Parametre;
use Souriane\Services\PushService;

echo "Souriane — génération des clés VAPID\n";
echo str_repeat('-', 52), "\n";

try {
    $cles = PushService::genererClesVapid();
} catch (Throwable $e) {
    fwrite(STDERR, "Échec : " . $e->getMessage() . "\n");
    fwrite(STDERR, "Vérifiez que l’extension openssl est active et que openssl.cnf est accessible.\n");
    exit(1);
}

// 1. Écriture dans la table parametres
Parametre::ecrire('cle_publique_vapid', $cles['publique'], 'Clé publique VAPID');
Parametre::ecrire('cle_privee_vapid', $cles['privee'], 'Clé privée VAPID — ne pas versionner');
echo "✓ Clés enregistrées dans la table parametres\n";

// 2. Mise à jour du fichier .env
$cheminEnv = SOURIANE_ROOT . '/.env';
if (is_file($cheminEnv)) {
    $contenu = (string) file_get_contents($cheminEnv);
    $contenu = preg_replace('/^VAPID_PUBLIC_KEY=.*$/m', 'VAPID_PUBLIC_KEY=' . $cles['publique'], $contenu) ?? $contenu;
    $contenu = preg_replace('/^VAPID_PRIVATE_KEY=.*$/m', 'VAPID_PRIVATE_KEY=' . $cles['privee'], $contenu) ?? $contenu;
    file_put_contents($cheminEnv, $contenu, LOCK_EX);
    echo "✓ Fichier .env mis à jour\n";
}

echo "\nClé publique  : {$cles['publique']}\n";
echo "Clé privée    : " . substr($cles['privee'], 0, 8) . '…' . substr($cles['privee'], -6) . " (masquée)\n";
echo "\nCes valeurs seront transmises au navigateur via /api/notifications/cle\n";
echo "et utilisées par cron/envoyer_notifications.php.\n";
