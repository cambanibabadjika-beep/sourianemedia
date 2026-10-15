<?php
/**
 * SOURIANE — Modèle Code court (QR codes et codes de secours)
 *
 * Un code distinct par couple campagne × panneau (EF-C01). La destination est
 * modifiable sans réimprimer le visuel (EF-C03).
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database as Db;

final class CodeCourt
{
    /** Caractères utilisés pour les codes : sans ambiguïté de lecture. */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public static function tous(array $filtres = []): array
    {
        $sql = 'SELECT cc.*, c.nom AS campagne_nom, c.date_debut, c.date_fin, c.statut AS campagne_statut,
                       p.reference AS panneau_reference, p.libelle AS panneau_libelle, p.ville,
                       a.nom AS annonceur_nom, a.adresse_courte AS annonceur_slug,
                       (SELECT COUNT(*) FROM evenements e WHERE e.code_court_id = cc.id AND e.type = \'scan\') AS scans,
                       (SELECT COUNT(*) FROM evenements e WHERE e.code_court_id = cc.id AND e.type = \'scan\'
                         AND e.session_id IS NOT NULL) AS scans_session
                FROM codes_courts cc
                JOIN campagnes c ON c.id = cc.campagne_id
                JOIN panneaux p ON p.id = cc.panneau_id
                JOIN annonceurs a ON a.id = c.annonceur_id
                WHERE 1=1';
        $params = [];

        if (!empty($filtres['campagne_id'])) {
            $sql .= ' AND cc.campagne_id = :cid';
            $params['cid'] = (int) $filtres['campagne_id'];
        }
        if (!empty($filtres['panneau_id'])) {
            $sql .= ' AND cc.panneau_id = :pid';
            $params['pid'] = (int) $filtres['panneau_id'];
        }

        $sql .= ' ORDER BY c.date_debut DESC, p.reference ASC';

        return Db::instance()->select($sql, $params);
    }

    public static function trouver(int $id): ?array
    {
        return Db::instance()->selectOne('SELECT * FROM codes_courts WHERE id = :id', ['id' => $id]);
    }

    /** Résout un code court et retourne le contexte complet du scan. */
    public static function resoudre(string $code): ?array
    {
        return Db::instance()->selectOne(
            'SELECT cc.*,
                    c.nom AS campagne_nom, c.id AS campagne_id, c.statut AS campagne_statut,
                    c.date_debut, c.date_fin,
                    p.id AS panneau_id, p.reference AS panneau_reference, p.libelle AS panneau_libelle,
                    a.id AS annonceur_id, a.nom AS annonceur_nom, a.adresse_courte AS annonceur_slug,
                    pg.id AS page_id, pg.adresse_courte AS page_slug, pg.statut AS page_statut
             FROM codes_courts cc
             JOIN campagnes c ON c.id = cc.campagne_id
             JOIN panneaux p ON p.id = cc.panneau_id
             JOIN annonceurs a ON a.id = c.annonceur_id
             LEFT JOIN pages pg ON pg.annonceur_id = a.id
             WHERE cc.code = :code AND cc.actif = 1
             LIMIT 1',
            ['code' => $code]
        );
    }

    /** Génère un code unique non ambigu. */
    public static function genererCode(int $longueur = 5): string
    {
        $db = Db::instance();
        do {
            $code = '';
            for ($i = 0; $i < $longueur; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $existe = (int) $db->selectValue('SELECT COUNT(*) FROM codes_courts WHERE code = :c', ['c' => $code], 0);
        } while ($existe > 0);

        return $code;
    }

    /**
     * Génère les codes manquants d’une campagne, un par panneau associé (EF-C01).
     *
     * @return int Nombre de codes créés
     */
    public static function genererPourCampagne(int $campagneId): int
    {
        $db = Db::instance();

        $panneaux = $db->select(
            'SELECT cp.panneau_id FROM campagne_panneaux cp
             LEFT JOIN codes_courts cc ON cc.campagne_id = cp.campagne_id AND cc.panneau_id = cp.panneau_id
             WHERE cp.campagne_id = :cid AND cc.id IS NULL',
            ['cid' => $campagneId]
        );

        $crees = 0;
        foreach ($panneaux as $ligne) {
            $code = self::genererCode();
            $db->insert('codes_courts', [
                'code'         => $code,
                'campagne_id'  => $campagneId,
                'panneau_id'   => (int) $ligne['panneau_id'],
                'code_secours' => COURT_DOMAINE . '/' . $code,
                'actif'        => 1,
            ]);
            $crees++;
        }

        return $crees;
    }

    public static function modifier(int $id, array $donnees): void
    {
        Db::instance()->update('codes_courts', $donnees, 'id = :id', ['id' => $id]);
    }

    /** URL courte encodée dans le QR code (jamais l’adresse directe). */
    public static function urlCourte(array $code): string
    {
        return url_courte((string) $code['code']);
    }

    /** Destination finale d’un scan. */
    public static function destination(array $code): string
    {
        if (!empty($code['destination'])) {
            return (string) $code['destination'];
        }

        $slug = $code['page_slug'] ?? $code['annonceur_slug'] ?? '';

        return $slug !== '' ? url_page((string) $slug) : url('/');
    }

    /** Statistiques d’un code. */
    public static function statistiques(int $codeId): array
    {
        return Db::instance()->selectOne(
            "SELECT
                SUM(type = 'scan')           AS scans,
                COUNT(DISTINCT session_id)   AS sessions,
                SUM(type = 'clic_appel')     AS clics_appel,
                SUM(type = 'clic_whatsapp')  AS clics_whatsapp,
                SUM(type = 'clic_itineraire') AS clics_itineraire,
                SUM(type = 'abonnement')     AS abonnements
             FROM evenements WHERE code_court_id = :id",
            ['id' => $codeId]
        ) ?? [];
    }
}
