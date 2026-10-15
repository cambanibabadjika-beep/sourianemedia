<?php
/**
 * SOURIANE — Modèle Campagne, réservation et formules tarifaires
 */

declare(strict_types=1);

namespace Souriane\Models;

use RuntimeException;
use Souriane\Core\Database as Db;

final class Campagne
{
    public const STATUTS = ['brouillon', 'reservee', 'en_cours', 'terminee', 'annulee'];

    public static function tous(array $filtres = []): array
    {
        $sql = 'SELECT c.*, a.nom AS annonceur_nom, a.adresse_courte AS annonceur_slug, f.nom AS formule_nom,
                       (SELECT COUNT(*) FROM campagne_panneaux cp WHERE cp.campagne_id = c.id) AS nb_panneaux,
                       (SELECT COUNT(*) FROM codes_courts cc WHERE cc.campagne_id = c.id) AS nb_codes
                FROM campagnes c
                JOIN annonceurs a ON a.id = c.annonceur_id
                LEFT JOIN formules f ON f.id = c.formule_id
                WHERE 1=1';
        $params = [];

        if (!empty($filtres['statut'])) {
            $sql .= ' AND c.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }
        if (!empty($filtres['annonceur_id'])) {
            $sql .= ' AND c.annonceur_id = :aid';
            $params['aid'] = (int) $filtres['annonceur_id'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= ' AND (c.nom LIKE :q OR a.nom LIKE :q)';
            $params['q'] = '%' . $filtres['recherche'] . '%';
        }

        $sql .= ' ORDER BY c.date_debut DESC, c.id DESC';

        return Db::instance()->select($sql, $params);
    }

    public static function trouver(int $id): ?array
    {
        return Db::instance()->selectOne(
            'SELECT c.*, a.nom AS annonceur_nom, a.adresse_courte AS annonceur_slug,
                    f.nom AS formule_nom, f.code AS formule_code
             FROM campagnes c
             JOIN annonceurs a ON a.id = c.annonceur_id
             LEFT JOIN formules f ON f.id = c.formule_id
             WHERE c.id = :id',
            ['id' => $id]
        );
    }

    public static function panneaux(int $campagneId): array
    {
        return Db::instance()->select(
            'SELECT cp.*, p.reference, p.libelle, p.ville, p.format, p.statut AS panneau_statut
             FROM campagne_panneaux cp
             JOIN panneaux p ON p.id = cp.panneau_id
             WHERE cp.campagne_id = :id
             ORDER BY p.reference',
            ['id' => $campagneId]
        );
    }

    public static function creer(array $donnees): int
    {
        return Db::instance()->insert('campagnes', $donnees);
    }

    public static function modifier(int $id, array $donnees): void
    {
        Db::instance()->update('campagnes', $donnees, 'id = :id', ['id' => $id]);
    }

    public static function compterParStatut(): array
    {
        $lignes = Db::instance()->select('SELECT statut, COUNT(*) AS total FROM campagnes GROUP BY statut');
        $resultat = array_fill_keys(self::STATUTS, 0);
        foreach ($lignes as $l) {
            $resultat[$l['statut']] = (int) $l['total'];
        }

        return $resultat;
    }

    // -----------------------------------------------------------------------
    // Formules et calcul du prix (EF-B03)
    // -----------------------------------------------------------------------

    public static function formules(bool $activesSeulement = true): array
    {
        $sql = 'SELECT * FROM formules';
        if ($activesSeulement) {
            $sql .= ' WHERE actif = 1';
        }

        return Db::instance()->select($sql . ' ORDER BY prix_base ASC');
    }

    public static function formule(int $id): ?array
    {
        return Db::instance()->selectOne('SELECT * FROM formules WHERE id = :id', ['id' => $id]);
    }

    /**
     * Calcule automatiquement le prix selon la formule, la durée et le nombre
     * de panneaux (EF-B03).
     *
     * @return array{prix_unitaire:float, brut:float, remise:float, total:float, duree_jours:int, taux_remise:float}
     */
    public static function calculerPrix(int $formuleId, string $debut, string $fin, int $nbPanneaux): array
    {
        $formule = self::formule($formuleId);
        if ($formule === null) {
            throw new RuntimeException('Formule tarifaire introuvable.');
        }

        $tsDebut = strtotime($debut);
        $tsFin   = strtotime($fin);
        if ($tsDebut === false || $tsFin === false || $tsFin < $tsDebut) {
            throw new RuntimeException('Période invalide.');
        }

        $dureeJours = (int) floor(($tsFin - $tsDebut) / 86400) + 1;
        $reference  = max(1, (int) $formule['duree_reference_jours']);
        $nbPanneaux = max(1, $nbPanneaux);

        // Prix d’une ligne panneau, proratisé sur la durée
        $prixUnitaire = round(((float) $formule['prix_base']) * ($dureeJours / $reference), 2);
        $brut = round($prixUnitaire * $nbPanneaux, 2);

        // Remise par palier de quantité
        $taux = 0.0;
        $paliers = json_lire($formule['remise_panneaux'] ?? null);
        if ($paliers !== []) {
            ksort($paliers, SORT_NUMERIC);
            foreach ($paliers as $seuil => $tauxPalier) {
                if ($nbPanneaux >= (int) $seuil) {
                    $taux = (float) $tauxPalier;
                }
            }
        }

        $remise = round($brut * $taux, 2);

        return [
            'prix_unitaire' => $prixUnitaire,
            'brut'          => $brut,
            'remise'        => $remise,
            'total'         => round($brut - $remise, 2),
            'duree_jours'   => $dureeJours,
            'taux_remise'   => $taux,
        ];
    }

    // -----------------------------------------------------------------------
    // Réservation
    // -----------------------------------------------------------------------

    /**
     * Réserve une campagne sur une liste de panneaux.
     * Empêche toute double réservation : verrouillage explicite de chaque
     * panneau puis contrôle de chevauchement (EF-B05).
     *
     * @param int[] $panneauIds
     */
    public static function reserver(int $campagneId, array $panneauIds, string $debut, string $fin): void
    {
        $db = Db::instance();

        $db->transaction(function () use ($db, $campagneId, $panneauIds, $debut, $fin): void {
            $campagne = $db->selectOne('SELECT * FROM campagnes WHERE id = :id', ['id' => $campagneId]);
            if ($campagne === null) {
                throw new RuntimeException('Campagne introuvable.');
            }

            // Recalcule le prix avec le nombre réel de panneaux
            $calcul = self::calculerPrix(
                (int) ($campagne['formule_id'] ?? 0),
                $debut,
                $fin,
                count($panneauIds)
            );

            foreach ($panneauIds as $pid) {
                $pid = (int) $pid;

                // Verrouillage de la ligne panneau pour sérialiser les réservations
                $db->selectOne('SELECT id FROM panneaux WHERE id = :id FOR UPDATE', ['id' => $pid]);

                $conflit = $db->selectOne(
                    "SELECT cp.campagne_id, c.nom
                     FROM campagne_panneaux cp
                     JOIN campagnes c ON c.id = cp.campagne_id
                     WHERE cp.panneau_id = :pid
                       AND cp.campagne_id <> :cid
                       AND c.statut IN ('reservee','en_cours')
                       AND NOT (cp.date_fin < :debut OR cp.date_debut > :fin)
                     LIMIT 1",
                    ['pid' => $pid, 'cid' => $campagneId, 'debut' => $debut, 'fin' => $fin]
                );

                if ($conflit !== null) {
                    throw new RuntimeException(sprintf(
                        'Le panneau #%d est déjà réservé sur cette période (campagne « %s »).',
                        $pid,
                        $conflit['nom']
                    ));
                }

                $db->query(
                    'INSERT INTO campagne_panneaux (campagne_id, panneau_id, visuel, date_debut, date_fin, prix_ligne)
                     VALUES (:cid, :pid, NULL, :debut, :fin, :prix)
                     ON DUPLICATE KEY UPDATE date_debut = VALUES(date_debut),
                                             date_fin = VALUES(date_fin),
                                             prix_ligne = VALUES(prix_ligne)',
                    [
                        'cid'   => $campagneId,
                        'pid'   => $pid,
                        'debut' => $debut,
                        'fin'   => $fin,
                        'prix'  => $calcul['prix_unitaire'],
                    ]
                );

                $db->update('panneaux', ['statut' => 'reserve'], 'id = :id AND statut = :s', [
                    'id' => $pid, 's' => 'disponible',
                ]);
            }

            $db->update('campagnes', [
                'prix'   => $calcul['total'],
                'statut' => 'reservee',
            ], 'id = :id', ['id' => $campagneId]);
        });
    }

    /** Retire un panneau d’une campagne. */
    public static function retirerPanneau(int $campagneId, int $panneauId): void
    {
        $db = Db::instance();
        $db->delete('campagne_panneaux', 'campagne_id = :c AND panneau_id = :p', [
            'c' => $campagneId, 'p' => $panneauId,
        ]);

        $restant = (int) $db->selectValue(
            'SELECT COUNT(*) FROM campagne_panneaux WHERE panneau_id = :p',
            ['p' => $panneauId],
            0
        );
        if ($restant === 0) {
            $db->update('panneaux', ['statut' => 'disponible'], 'id = :id', ['id' => $panneauId]);
        }
    }

    /** Fait avancer les statuts selon les dates (tâche planifiée). */
    public static function actualiserStatuts(): int
    {
        $db = Db::instance();

        $a = $db->query(
            "UPDATE campagnes SET statut = 'en_cours'
             WHERE statut = 'reservee' AND date_debut <= CURDATE() AND date_fin >= CURDATE()"
        )->rowCount();

        $b = $db->query(
            "UPDATE campagnes SET statut = 'terminee'
             WHERE statut IN ('reservee','en_cours') AND date_fin < CURDATE()"
        )->rowCount();

        return $a + $b;
    }
}
