<?php
/**
 * SOURIANE — Modèle Visite (session de visite)
 * Le décompte des visiteurs uniques repose sur l’identifiant de session
 * anonyme, jamais sur l’adresse réseau (ch. 8).
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database as Db;

final class Visite
{
    /** Ouvre ou rafraîchit la session de visite courante. */
    public static function ouvrir(
        string $sessionId,
        ?int $pageId,
        ?int $annonceurId,
        ?int $codeCourtId,
        string $appareil,
        string $source
    ): int {
        $db = Db::instance();

        // Une session peut compter plusieurs visites successives : seule la
        // plus récente, si elle est encore ouverte, peut être prolongée.
        $existante = $db->selectOne(
            'SELECT id, debut FROM visites
             WHERE session_id = :s AND fin IS NULL
             ORDER BY id DESC LIMIT 1',
            ['s' => $sessionId]
        );

        if ($existante !== null) {
            // Ferme la visite précédente si la fenêtre de session est dépassée
            $debut = strtotime((string) $existante['debut']);
            if ($debut !== false && (time() - $debut) > DUREE_SESSION_MINUTES * 60) {
                $db->update('visites', ['fin' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $existante['id']]);
            } else {
                $db->update('visites', [
                    'page_id'       => $pageId,
                    'annonceur_id'  => $annonceurId,
                    'code_court_id' => $codeCourtId,
                ], 'id = :id', ['id' => (int) $existante['id']]);

                return (int) $existante['id'];
            }
        }

        return $db->insert('visites', [
            'session_id'    => $sessionId,
            'page_id'       => $pageId,
            'annonceur_id'  => $annonceurId,
            'code_court_id' => $codeCourtId,
            'debut'         => date('Y-m-d H:i:s'),
            'appareil'      => mb_substr($appareil, 0, 120),
            'source'        => $source,
        ]);
    }

    public static function compter(int $jours = 30): int
    {
        $jours = max(1, min(365, $jours));

        return (int) Db::instance()->selectValue(
            "SELECT COUNT(*) FROM visites WHERE debut >= DATE_SUB(CURDATE(), INTERVAL {$jours} DAY)",
            [],
            0
        );
    }

    /** Répartition des visites par appareil (mesure). */
    public static function parAppareil(int $jours = 30): array
    {
        $jours = max(1, min(365, $jours));

        return Db::instance()->select(
            "SELECT appareil, COUNT(*) AS total
             FROM visites
             WHERE debut >= DATE_SUB(CURDATE(), INTERVAL {$jours} DAY)
             GROUP BY appareil ORDER BY total DESC LIMIT 10",
            []
        );
    }

    /** Purge les données individuelles de session au-delà de la durée de conservation. */
    public static function purger(int $mois = 12): int
    {
        $mois = max(1, $mois);

        return Db::instance()->query(
            "UPDATE visites SET appareil = NULL, fin = NULL
             WHERE debut < DATE_SUB(CURDATE(), INTERVAL {$mois} MONTH)"
        )->rowCount();
    }
}
