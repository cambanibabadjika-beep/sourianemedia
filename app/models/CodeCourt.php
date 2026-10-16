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

    /**
     * Colonnes du contexte d’un code, partagées par ses deux lectures :
     * la résolution d’un scan et la préparation d’une affiche. Les garder
     * en un seul endroit évite que les deux vues du même objet divergent.
     */
    private const COLONNES_CONTEXTE = 'cc.*,
            c.nom AS campagne_nom, c.id AS campagne_id, c.statut AS campagne_statut,
            c.date_debut, c.date_fin,
            p.id AS panneau_id, p.reference AS panneau_reference, p.libelle AS panneau_libelle,
            p.ville,
            a.id AS annonceur_id, a.nom AS annonceur_nom, a.adresse_courte AS annonceur_slug,
            pg.id AS page_id, pg.adresse_courte AS page_slug, pg.statut AS page_statut';

    /** Jointures communes aux deux lectures de contexte. */
    private const JOINTURES_CONTEXTE = 'FROM codes_courts cc
             JOIN campagnes c ON c.id = cc.campagne_id
             JOIN panneaux p ON p.id = cc.panneau_id
             JOIN annonceurs a ON a.id = c.annonceur_id
             LEFT JOIN pages pg ON pg.annonceur_id = a.id';

    /** Résout un code court et retourne le contexte complet du scan. */
    public static function resoudre(string $code): ?array
    {
        return Db::instance()->selectOne(
            'SELECT ' . self::COLONNES_CONTEXTE . ' ' . self::JOINTURES_CONTEXTE . '
             WHERE cc.code = :code AND cc.actif = 1
             LIMIT 1',
            ['code' => $code]
        );
    }

    /**
     * Contexte d’un code désigné par son identifiant, qu’il soit actif ou non.
     *
     * `resoudre()` filtre sur `actif = 1` : c’est juste pour le parcours de
     * scan, un code désactivé ne devant plus mener nulle part. L’affiche, en
     * revanche, doit rester régénérable — on peut vouloir réimprimer le visuel
     * d’un code momentanément suspendu, ou simplement le consulter avant de le
     * réactiver. D’où cette seconde lecture, sans le filtre.
     */
    public static function contexte(int $id): ?array
    {
        return Db::instance()->selectOne(
            'SELECT ' . self::COLONNES_CONTEXTE . ' ' . self::JOINTURES_CONTEXTE . '
             WHERE cc.id = :id
             LIMIT 1',
            ['id' => $id]
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

    /**
     * Une destination de code court n’est acceptée que sous deux formes :
     * un chemin interne — commençant par « / » mais pas par « // », qui
     * désignerait un hôte externe — ou une adresse « https:// ».
     *
     * Toute autre valeur est refusée : schéma « javascript: » ou « data: »,
     * adresse « http:// » non chiffrée, ou caractère de contrôle. La
     * destination est en effet recopiée telle quelle dans un en-tête Location
     * (EF-C03, ch. 8) ; une valeur non filtrée y ouvrirait une redirection
     * vers un site tiers.
     */
    public static function destinationValide(?string $valeur): bool
    {
        $valeur = trim((string) $valeur);

        if ($valeur === '' || mb_strlen($valeur) > 255) {
            return false;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $valeur) === 1) {
            return false;
        }
        if (str_starts_with($valeur, '//')) {
            return false;
        }
        if (str_starts_with($valeur, '/')) {
            return true;
        }

        return preg_match('#^https://[^\s]+$#i', $valeur) === 1;
    }

    /**
     * Destination finale d’un scan.
     *
     * La surcharge enregistrée n’est retenue que si elle est valide : une
     * valeur refusée à la saisie, ou devenue invalide après coup, ne doit
     * jamais être renvoyée au navigateur. Le repli est la page de l’annonceur.
     */
    public static function destination(array $code): string
    {
        $surcharge = (string) ($code['destination'] ?? '');
        if (self::destinationValide($surcharge)) {
            return $surcharge;
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
