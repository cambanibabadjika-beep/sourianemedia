<?php
/**
 * SOURIANE — Diffusion des notifications (ch. 5.5)
 *
 * Logique partagée par le back-office (envoi immédiat) et la tâche planifiée
 * (traitement de la file programmée). Applique le plafond de fréquence, met à
 * jour le journal d’envoi et désactive les abonnements devenus caducs.
 */

declare(strict_types=1);

namespace Souriane\Services;

use RuntimeException;
use Souriane\Core\Database as Db;
use Souriane\Models\Abonne;
use Souriane\Models\Notification;

final class DiffusionService
{
    /**
     * Envoie une notification à tous ses abonnés actifs.
     *
     * @return array{envoyes:int, echecs:int, expires:int, total:int, ignores:int}
     */
    public function envoyer(int $notificationId): array
    {
        $db = Db::instance();

        $notification = $db->selectOne(
            'SELECT n.*, a.nom AS annonceur_nom FROM notifications n
             JOIN annonceurs a ON a.id = n.annonceur_id
             WHERE n.id = :id',
            ['id' => $notificationId]
        );

        if ($notification === null) {
            throw new RuntimeException('Notification introuvable.');
        }
        if ($notification['statut'] === 'envoyee') {
            throw new RuntimeException('Cette notification a déjà été envoyée.');
        }
        if ($notification['statut'] === 'annulee') {
            throw new RuntimeException('Cette notification a été annulée.');
        }

        $annonceurId = (int) $notification['annonceur_id'];

        // Plafond : quatre envois par mois et par annonceur (ch. 5.5)
        $deja = Abonne::envoisMoisCourant($annonceurId);
        if ($deja >= PLAFOND_NOTIFICATIONS_MOIS) {
            throw new RuntimeException(sprintf(
                'Plafond atteint : %d notification(s) déjà envoyée(s) ce mois-ci (maximum %d).',
                $deja,
                PLAFOND_NOTIFICATIONS_MOIS
            ));
        }

        // Prépare une ligne d’envoi par abonné actif
        Notification::preparerEnvois($notificationId, $annonceurId);

        $envois = $db->select(
            'SELECT e.id AS envoi_id, e.statut AS envoi_statut, ab.*
             FROM envois e
             JOIN abonnes ab ON ab.id = e.abonne_id
             WHERE e.notification_id = :id',
            ['id' => $notificationId]
        );

        if ($envois === []) {
            Notification::modifier($notificationId, [
                'statut'     => 'envoyee',
                'envoyee_le' => date('Y-m-d H:i:s'),
            ]);

            return ['envoyes' => 0, 'echecs' => 0, 'expires' => 0, 'total' => 0, 'ignores' => 0];
        }

        Notification::modifier($notificationId, ['statut' => 'en_cours']);

        $charge = [
            'titre' => (string) $notification['titre'],
            'corps' => (string) $notification['corps'],
            'icone' => $notification['icone'] !== null ? url((string) $notification['icone']) : url('assets/img/icons/icone-192.png'),
            'url'   => $notification['adresse_ouverture'] !== null
                ? url((string) $notification['adresse_ouverture'])
                : url('/'),
            'etiquette' => 'souriane-' . $notificationId,
        ];

        $envoyes = 0;
        $echecs  = 0;
        $expires = 0;
        $ignores = 0;

        foreach ($envois as $envoi) {
            if (($envoi['envoi_statut'] ?? 'en_attente') !== 'en_attente') {
                $ignores++;
                continue;
            }

            $resultat = PushService::envoyer($envoi, $charge);

            if ($resultat['ok']) {
                $envoyes++;
                $db->update('envois', [
                    'statut'     => 'envoye',
                    'date_envoi' => date('Y-m-d H:i:s'),
                    'erreur'     => null,
                ], 'id = :id', ['id' => (int) $envoi['envoi_id']]);

                $db->update('abonnes', [
                    'dernier_envoi' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => (int) $envoi['id']]);

                continue;
            }

            if ($resultat['statut'] === 'expire') {
                $expires++;
                // L’abonnement n’existe plus côté service d’envoi : on le désactive
                $db->update('envois', [
                    'statut' => 'expire',
                    'erreur' => $resultat['erreur'],
                ], 'id = :id', ['id' => (int) $envoi['envoi_id']]);

                Abonne::desactiver((int) $envoi['id']);
                continue;
            }

            $echecs++;
            $db->update('envois', [
                'statut' => 'echec',
                'erreur' => mb_substr((string) $resultat['erreur'], 0, 255),
            ], 'id = :id', ['id' => (int) $envoi['envoi_id']]);
        }

        Notification::modifier($notificationId, [
            'statut'     => 'envoyee',
            'envoyee_le' => date('Y-m-d H:i:s'),
        ]);

        return [
            'envoyes'  => $envoyes,
            'echecs'   => $echecs,
            'expires'  => $expires,
            'total'    => count($envois),
            'ignores'  => $ignores,
        ];
    }

    /**
     * Traite la file des notifications programmées arrivées à échéance.
     *
     * @return array{traitees:int, envoyes:int, echecs:int, erreurs:array<int,string>}
     */
    public function traiterFile(int $limite = 20): array
    {
        $aEnvoyer = Notification::aEnvoyer($limite);

        $traitees = 0;
        $envoyes  = 0;
        $echecs   = 0;
        $erreurs  = [];

        foreach ($aEnvoyer as $notification) {
            try {
                $resultat = $this->envoyer((int) $notification['id']);
                $traitees++;
                $envoyes += $resultat['envoyes'];
                $echecs  += $resultat['echecs'];
            } catch (\Throwable $e) {
                $echecs++;
                $erreurs[] = '#' . $notification['id'] . ' : ' . $e->getMessage();
                journal_technique('erreur', 'Diffusion impossible', [
                    'notification' => (int) $notification['id'],
                    'message'      => $e->getMessage(),
                ]);
            }
        }

        return ['traitees' => $traitees, 'envoyes' => $envoyes, 'echecs' => $echecs, 'erreurs' => $erreurs];
    }
}
