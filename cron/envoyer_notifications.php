<?php
/**
 * SOURIANE — Envoi des notifications programmées (ch. 5.5)
 *
 * Traite la file des notifications arrivées à échéance : sélection des envois
 * dus, signature VAPID, remise au service de push, mise à jour du journal et
 * désactivation des abonnements devenus caducs.
 *
 * Utilisation :
 *   php cron/envoyer_notifications.php [--limite=20] [--silencieux]
 *
 * Planification (toutes les cinq minutes) :
 *   * /usr/bin/php /chemin/vers/cron/envoyer_notifications.php >> /var/log/souriane-cron.log 2>&1
 *
 * Sur un hébergement mutualisé sans accès à crontab, un planificateur HTTP peut
 * appeler le script avec la clé définie par CRON_TOKEN dans .env :
 *   https://exemple.tld/cron/envoyer_notifications.php?cle=<CRON_TOKEN>
 *
 * Le plafond de quatre notifications par mois et par annonceur est appliqué par
 * DiffusionService : une notification qui le dépasserait n'est pas envoyée et
 * l'incident est consigné dans le journal technique.
 */

declare(strict_types=1);

$racine = dirname(__DIR__);
require_once $racine . '/app/bootstrap.php';

use Souriane\Services\DiffusionService;

// ---------------------------------------------------------------------------
// Contexte d’exécution
// ---------------------------------------------------------------------------

$enLigneDeCommande = PHP_SAPI === 'cli';

$limite     = 20;
$silencieux = false;
$verbeux    = false;

if ($enLigneDeCommande) {
    $options = getopt('', ['limite::', 'silencieux', 'verbeux', 'aide']);

    if (isset($options['aide'])) {
        echo "Usage : php cron/envoyer_notifications.php [options]\n\n";
        echo "  --limite=N     Nombre maximal de notifications traitées par passage (défaut 20, max 200)\n";
        echo "  --silencieux   N’écrit rien hors erreur\n";
        echo "  --verbeux      Détaille chaque notification traitée\n";
        echo "  --aide         Affiche cette aide\n";
        exit(0);
    }

    if (isset($options['limite'])) {
        $limite = max(1, min(200, (int) $options['limite']));
    }
    $silencieux = isset($options['silencieux']);
    $verbeux    = isset($options['verbeux']);
} else {
    // Appel HTTP : exige la clé partagée, comparee en temps constant.
    header('Content-Type: text/plain; charset=utf-8');

    $attendue = (string) (defined('CRON_TOKEN') ? CRON_TOKEN : '');
    $fournie  = (string) ($_GET['cle'] ?? '');

    if ($attendue === '' || $fournie === '' || !hash_equals($attendue, $fournie)) {
        http_response_code(403);
        echo "Accès refusé.\n";
        exit(1);
    }

    if (isset($_GET['limite'])) {
        $limite = max(1, min(200, (int) $_GET['limite']));
    }
    $silencieux = isset($_GET['silencieux']);
}

/** Écrit une ligne horodatée sur la sortie standard. */
function ligne(string $texte): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $texte . "\n";
}

// ---------------------------------------------------------------------------
// Verrou d’exclusion mutuelle
// ---------------------------------------------------------------------------

/**
 * Deux passages simultanés enverraient deux fois les mêmes notifications.
 * Un verrou exclusif, non bloquant, garantit qu’un seul traitement s’exécute.
 */
$cheminVerrou = (defined('STORAGE_PATH') ? STORAGE_PATH : $racine . '/storage') . '/cron-envois.lock';
$verrou = @fopen($cheminVerrou, 'c');

if ($verrou === false) {
    ligne('Verrou indisponible : ' . $cheminVerrou);
    if (!$enLigneDeCommande) {
        http_response_code(500);
    }
    exit(1);
}

if (!flock($verrou, LOCK_EX | LOCK_NB)) {
    if (!$silencieux) {
        ligne('Un traitement est déjà en cours, passage ignoré.');
    }
    fclose($verrou);
    exit(0);
}

ftruncate($verrou, 0);
fwrite($verrou, (string) getmypid() . "\n");
fflush($verrou);

// ---------------------------------------------------------------------------
// Traitement de la file
// ---------------------------------------------------------------------------

if (!$silencieux) {
    ligne('Souriane — traitement de la file des notifications (limite ' . $limite . ')');
}

$debut  = microtime(true);
$sortie = 0;

try {
    $resultat = (new DiffusionService())->traiterFile($limite);

    if (!$silencieux) {
        ligne(sprintf(
            'Notifications traitées : %d · envois réussis : %d · échecs : %d',
            $resultat['traitees'],
            $resultat['envoyes'],
            $resultat['echecs']
        ));

        if ($verbeux && $resultat['erreurs'] !== []) {
            foreach ($resultat['erreurs'] as $erreur) {
                ligne('  refus — ' . $erreur);
            }
        }
    }

    if ($resultat['traitees'] === 0 && !$silencieux) {
        ligne('Aucune notification à échéance.');
    }

    // Un échec de remise n’est pas une erreur du planificateur : les abonnements
    // concernés sont marqués et le passage suivant reprendra la file.
    $sortie = 0;
} catch (Throwable $e) {
    ligne('Erreur fatale : ' . $e->getMessage());
    journal_technique('erreur', 'Traitement de la file interrompu', [
        'message' => $e->getMessage(),
        'fichier' => $e->getFile(),
        'ligne'   => $e->getLine(),
    ]);
    $sortie = 1;
} finally {
    if (!$silencieux) {
        ligne(sprintf('Terminé en %.0f ms.', (microtime(true) - $debut) * 1000));
    }

    flock($verrou, LOCK_UN);
    fclose($verrou);
    @unlink($cheminVerrou);
}

exit($sortie);
