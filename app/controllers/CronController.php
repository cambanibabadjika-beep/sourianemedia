<?php
/**
 * SOURIANE — Déclencheur HTTP de la tâche planifiée
 *
 * Certains hébergements mutualisés n’offrent pas d’accès à crontab. Ce point
 * d’entrée permet à un planificateur en ligne d’exécuter le traitement de la
 * file des notifications. Il est protégé par un jeton partagé (CRON_TOKEN)
 * comparé en temps constant, et par une limitation de débit.
 *
 * Le dossier cron/ reste totalement inaccessible par le web : c’est ici, et
 * ici seulement, que la tâche peut être déclenchée à distance.
 *
 *   GET /tache/notifications?cle=<CRON_TOKEN>
 */

declare(strict_types=1);

namespace Souriane\Controllers;

use Souriane\Core\RateLimiter;
use Souriane\Core\Request;
use Souriane\Core\Response;
use Souriane\Services\DiffusionService;

final class CronController
{
    /**
     * Traite la file des notifications programmées arrivées à échéance.
     * Réponse en texte brut, lisible dans le journal du planificateur.
     */
    public function notifications(Request $request): Response
    {
        // Sans jeton configuré, la porte reste fermée : mieux vaut une tâche
        // planifiée en ligne de commande qu’un point d’entrée ouvert.
        if (CRON_TOKEN === '') {
            journal_technique('avertissement', 'Déclencheur HTTP appelé sans CRON_TOKEN configuré');

            return Response::texte("Tâche non configurée.\n", 503);
        }

        $fourni = $request->string('cle');
        if ($fourni === '' || !hash_equals(CRON_TOKEN, $fourni)) {
            journal_technique('avertissement', 'Tentative d’accès au déclencheur de tâche avec un jeton invalide');

            return Response::texte("Accès refusé.\n", 403)
                ->avecEntete('Cache-Control', 'no-store');
        }

        // Un planificateur peut rappeler la tâche à la suite d’un délai dépassé :
        // la limite laisse passer un passage par minute.
        $limite = RateLimiter::verifier('tache-notifications', 'planificateur', 10, 60);
        if (!$limite['autorise']) {
            return Response::texte("Trop d’appels.\n", 429)
                ->avecEntete('Retry-After', (string) $limite['reessayer_dans']);
        }

        $demande = $request->int('limite', 20);
        $limiteTraitement = max(1, min(200, $demande));

        $lignes = [];
        $lignes[] = '[' . date('Y-m-d H:i:s') . '] Souriane — file des notifications (limite ' . $limiteTraitement . ')';

        try {
            $resultat = (new DiffusionService())->traiterFile($limiteTraitement);
            $lignes[] = sprintf(
                'traitées : %d · envois réussis : %d · échecs : %d',
                $resultat['traitees'],
                $resultat['envoyes'],
                $resultat['echecs']
            );

            foreach ($resultat['erreurs'] as $erreur) {
                $lignes[] = '  refus — ' . $erreur;
            }
        } catch (\Throwable $e) {
            journal_technique('erreur', 'Déclencheur de tâche en échec', [
                'message' => $e->getMessage(),
            ]);
            $lignes[] = 'erreur : ' . $e->getMessage();

            return Response::texte(implode("\n", $lignes) . "\n", 500)
                ->avecEntete('Cache-Control', 'no-store');
        }

        return Response::texte(implode("\n", $lignes) . "\n")
            ->avecEntete('Cache-Control', 'no-store');
    }
}
