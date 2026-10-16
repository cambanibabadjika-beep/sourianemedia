<?php
/**
 * SOURIANE — Modèle Événement et visite (journalisation, ch. 5.7)
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database as Db;

final class Evenement
{
    public const TYPES = [
        'scan', 'nfc', 'affichage', 'clic_appel', 'clic_whatsapp', 'clic_itineraire',
        'clic_reseau', 'clic_rester_informe', 'installation', 'abonnement', 'desabonnement',
    ];

    /**
     * Indicateurs réellement mesurés, dans l’ordre de présentation du
     * chapitre 5.7.
     *
     * La définition est unique et vit ici : le tableau de bord, le rapport de
     * fin de campagne et son export lus depuis la même source ne peuvent pas
     * afficher des libellés ou des clés divergents. Les clés correspondent
     * exactement aux alias renvoyés par `statistiquesCampagne()`.
     */
    public const INDICATEURS_MESURES = [
        ['cle' => 'scans_bruts',         'libelle' => 'Scans bruts',                   'icone' => 'qr'],
        ['cle' => 'visiteurs_uniques',   'libelle' => 'Visiteurs uniques',             'icone' => 'utilisateurs'],
        ['cle' => 'affichages',          'libelle' => 'Pages affichées',               'icone' => 'site'],
        ['cle' => 'clics_appel',         'libelle' => 'Clics sur « Appeler »',         'icone' => 'telephone'],
        ['cle' => 'clics_whatsapp',      'libelle' => 'Clics WhatsApp',                'icone' => 'whatsapp'],
        ['cle' => 'clics_itineraire',    'libelle' => 'Demandes d’itinéraire',         'icone' => 'itineraire'],
        ['cle' => 'clic_reseau',         'libelle' => 'Clics réseaux sociaux',         'icone' => 'partage'],
        ['cle' => 'clic_rester_informe', 'libelle' => 'Ouvertures « Rester informé »', 'icone' => 'cloche'],
        ['cle' => 'installations',       'libelle' => 'Installations',                 'icone' => 'telecharger'],
        ['cle' => 'abonnements',         'libelle' => 'Abonnements',                   'icone' => 'coche-cercle'],
        ['cle' => 'desabonnements',      'libelle' => 'Désabonnements',                'icone' => 'fermer'],
    ];

    public static function enregistrer(array $donnees): int
    {
        if (!in_array($donnees['type'] ?? '', self::TYPES, true)) {
            throw new \InvalidArgumentException('Type d’événement inconnu.');
        }

        $donnees['date'] = $donnees['date'] ?? date('Y-m-d H:i:s');

        return Db::instance()->insert('evenements', $donnees);
    }

    /** Enregistre un lot d’événements (POST /api/evenements). */
    public static function enregistrerLot(array $lot): int
    {
        $db = Db::instance();
        $total = 0;

        $db->transaction(function () use ($db, $lot, &$total): void {
            foreach ($lot as $item) {
                if (!is_array($item) || !in_array($item['type'] ?? '', self::TYPES, true)) {
                    continue;
                }
                $db->insert('evenements', [
                    'type'          => $item['type'],
                    'code_court_id' => isset($item['code_court_id']) ? (int) $item['code_court_id'] : null,
                    'page_id'       => isset($item['page_id']) ? (int) $item['page_id'] : null,
                    'annonceur_id'  => isset($item['annonceur_id']) ? (int) $item['annonceur_id'] : null,
                    'campagne_id'   => isset($item['campagne_id']) ? (int) $item['campagne_id'] : null,
                    'panneau_id'    => isset($item['panneau_id']) ? (int) $item['panneau_id'] : null,
                    'session_id'    => $item['session_id'] ?? null,
                    'empreinte_reseau' => $item['empreinte_reseau'] ?? null,
                    'agent'         => $item['agent'] ?? null,
                    'referent'      => $item['referent'] ?? null,
                    'meta'          => isset($item['meta']) ? json_encode($item['meta'], JSON_UNESCAPED_UNICODE) : null,
                    'date'          => $item['date'] ?? date('Y-m-d H:i:s'),
                ]);
                $total++;
            }
        });

        return $total;
    }

    /**
     * Indicateurs d’une campagne, en séparant explicitement mesuré, estimé et
     * déclaré (EF-F07, ch. 12).
     */
    public static function statistiquesCampagne(int $campagneId): array
    {
        $db = Db::instance();

        $mesure = $db->selectOne(
            "SELECT
                SUM(type = 'scan')                                  AS scans_bruts,
                COUNT(DISTINCT CASE WHEN type = 'scan' THEN session_id END) AS visiteurs_uniques,
                SUM(type = 'affichage')                             AS affichages,
                SUM(type = 'clic_appel')                            AS clics_appel,
                SUM(type = 'clic_whatsapp')                         AS clics_whatsapp,
                SUM(type = 'clic_itineraire')                       AS clics_itineraire,
                SUM(type = 'clic_reseau')                           AS clics_reseau,
                SUM(type = 'clic_rester_informe')                   AS clics_rester_informe,
                SUM(type = 'installation')                          AS installations,
                SUM(type = 'abonnement')                            AS abonnements,
                SUM(type = 'desabonnement')                         AS desabonnements
             FROM evenements WHERE campagne_id = :id",
            ['id' => $campagneId]
        ) ?? [];

        // Volume estimé : trafic déclaré des panneaux × durée de la campagne
        $estime = $db->selectOne(
            'SELECT COALESCE(SUM(p.trafic_estime), 0) AS trafic_journalier,
                    COUNT(cp.id) AS nb_panneaux,
                    COALESCE(DATEDIFF(c.date_fin, c.date_debut) + 1, 0) AS duree_jours
             FROM campagnes c
             LEFT JOIN campagne_panneaux cp ON cp.campagne_id = c.id
             LEFT JOIN panneaux p ON p.id = cp.panneau_id
             WHERE c.id = :id',
            ['id' => $campagneId]
        ) ?? [];

        $trafic = (float) ($estime['trafic_journalier'] ?? 0);
        $jours  = (int) ($estime['duree_jours'] ?? 0);

        // Estimation volontairement prudente : 1 % du trafic cumulé
        $passagesEstimes = (int) round($trafic * $jours);
        $scansEstimes    = (int) round($passagesEstimes * 0.01);

        return [
            'mesure'  => array_map(static fn ($v) => (int) ($v ?? 0), $mesure),
            'estime'  => [
                'trafic_journalier' => (int) $trafic,
                'passages_cumules'  => $passagesEstimes,
                'scans_estimes'     => $scansEstimes,
                'nb_panneaux'       => (int) ($estime['nb_panneaux'] ?? 0),
                'duree_jours'       => $jours,
                'methode'           => '1 % du trafic déclaré cumulé sur la période',
            ],
            'declare' => [
                'source' => 'Trafic estimé saisi par la régie sur la fiche panneau',
                'nature' => 'déclaré',
            ],
        ];
    }

    /** Série quotidienne des scans d’une campagne. */
    public static function serieQuotidienne(int $campagneId, int $jours = 30): array
    {
        $jours = max(1, min(180, $jours));

        return Db::instance()->select(
            "SELECT DATE(date) AS jour,
                    SUM(type = 'scan') AS scans,
                    SUM(type = 'clic_appel') AS appels,
                    SUM(type = 'clic_whatsapp') AS whatsapp
             FROM evenements
             WHERE campagne_id = :id
               AND date >= DATE_SUB(CURDATE(), INTERVAL {$jours} DAY)
             GROUP BY DATE(date) ORDER BY jour ASC",
            ['id' => $campagneId]
        );
    }

    /**
     * Série quotidienne des scans sur une période explicite.
     *
     * `serieQuotidienne()` compte à rebours depuis aujourd’hui : c’est ce qu’il
     * faut au tableau de bord, qui annonce « les 30 derniers jours ». Le rapport
     * de fin de campagne, lui, doit couvrir la période de la campagne — une
     * campagne terminée il y a trois mois n’y produirait sinon aucune ligne, et
     * une campagne en cours verrait sa fenêtre décalée par rapport à son propre
     * début. D’où cette seconde lecture, bornée par les dates réelles.
     */
    public static function seriePeriode(int $campagneId, string $debut, string $fin): array
    {
        return Db::instance()->select(
            "SELECT DATE(date) AS jour,
                    SUM(type = 'scan') AS scans,
                    SUM(type = 'clic_appel') AS appels,
                    SUM(type = 'clic_whatsapp') AS whatsapp
             FROM evenements
             WHERE campagne_id = :id
               AND date >= :debut
               AND date < DATE_ADD(:fin, INTERVAL 1 DAY)
             GROUP BY DATE(date) ORDER BY jour ASC",
            ['id' => $campagneId, 'debut' => $debut, 'fin' => $fin]
        );
    }

    /** Compteurs globaux pour le tableau de bord. */
    public static function compteursGlobaux(): array
    {
        $db = Db::instance();

        return [
            'scans_7j'    => (int) $db->selectValue(
                "SELECT COUNT(*) FROM evenements WHERE type = 'scan' AND date >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [], 0),
            'scans_30j'   => (int) $db->selectValue(
                "SELECT COUNT(*) FROM evenements WHERE type = 'scan' AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [], 0),
            'visiteurs_30j' => (int) $db->selectValue(
                "SELECT COUNT(DISTINCT session_id) FROM evenements WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [], 0),
            'installations' => (int) $db->selectValue(
                "SELECT COUNT(*) FROM evenements WHERE type = 'installation'", [], 0),
            'abonnements'  => (int) $db->selectValue(
                "SELECT COUNT(*) FROM abonnes WHERE statut = 'actif'", [], 0),
            'clics_contact' => (int) $db->selectValue(
                "SELECT COUNT(*) FROM evenements WHERE type IN ('clic_appel','clic_whatsapp')", [], 0),
        ];
    }
}
