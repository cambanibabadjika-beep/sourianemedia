<?php
/**
 * SOURIANE — Contrôleur de l’API JSON
 *
 * Reçoit les événements mesurés côté navigateur, gère les abonnements aux
 * notifications et expose les statistiques de campagne.
 *
 * Règles appliquées (ch. 8) :
 *   · jeton CSRF exigé sur toute écriture ;
 *   · limitation de débit par adresse ;
 *   · adresse réseau jamais conservée en clair (empreinte HMAC seulement) ;
 *   · aucune donnée personnelle demandée au visiteur.
 */

declare(strict_types=1);

namespace Souriane\Controllers;

use Souriane\Core\Auth;
use Souriane\Core\Csrf;
use Souriane\Core\Database;
use Souriane\Core\RateLimiter;
use Souriane\Core\Request;
use Souriane\Core\Response;
use Souriane\Models\Abonne;
use Souriane\Models\Evenement;

final class ApiController
{
    /** Types qu’un navigateur est autorisé à déclarer lui-même. */
    private const TYPES_CLIENT = [
        'clic_appel', 'clic_whatsapp', 'clic_itineraire',
        'clic_reseau', 'clic_rester_informe', 'installation',
    ];

    private const TAILLE_LOT_MAX = 25;

    // -----------------------------------------------------------------------
    // POST /api/evenements
    // -----------------------------------------------------------------------

    public function evenements(Request $request): Response
    {
        if ($refus = $this->garde($request, 'evenements', 90)) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $corps = $request->corps();

        $lot  = $corps['evenements'] ?? null;
        $lot  = is_array($lot) ? array_slice($lot, 0, self::TAILLE_LOT_MAX) : [];
        if ($lot === []) {
            return Response::json(['ok' => true, 'enregistres' => 0]);
        }

        $contexte = [
            'code_court_id' => $this->entierOuNull($corps['code_court_id'] ?? null),
            'page_id'       => $this->entierOuNull($corps['page_id'] ?? null),
            'annonceur_id'  => $this->entierOuNull($corps['annonceur_id'] ?? null),
            'campagne_id'   => $this->entierOuNull($corps['campagne_id'] ?? null),
            'panneau_id'    => $this->entierOuNull($corps['panneau_id'] ?? null),
        ];

        $session = $this->session($corps['session_id'] ?? null);

        $prepares = [];
        foreach ($lot as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = (string) ($item['type'] ?? '');
            if (!in_array($type, self::TYPES_CLIENT, true)) {
                continue;
            }

            $meta = $item['meta'] ?? null;

            $prepares[] = array_merge($contexte, [
                'type'             => $type,
                'session_id'       => $session,
                'empreinte_reseau' => empreinte_reseau($request->ip()),
                'agent'            => $request->agent(),
                'referent'         => $request->referent(),
                'meta'             => is_array($meta) ? $meta : null,
                'date'             => date('Y-m-d H:i:s'),
            ]);
        }

        if ($prepares === []) {
            return Response::json(['ok' => true, 'enregistres' => 0]);
        }

        $total = Evenement::enregistrerLot($prepares);

        return Response::json(['ok' => true, 'enregistres' => $total]);
    }

    // -----------------------------------------------------------------------
    // POST /api/abonnements
    // -----------------------------------------------------------------------

    public function abonnement(Request $request): Response
    {
        if ($refus = $this->garde($request, 'abonnements', 20)) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $corps = $request->corps();

        $annonceurId = (int) ($corps['annonceur_id'] ?? 0);
        $endpoint    = trim((string) ($corps['endpoint'] ?? ''));
        $cles        = is_array($corps['keys'] ?? null) ? $corps['keys'] : [];
        $p256dh      = trim((string) ($cles['p256dh'] ?? ''));
        $auth        = trim((string) ($cles['auth'] ?? ''));
        $frequence   = (string) ($corps['frequence'] ?? 'chaque_annonce');

        if ($annonceurId <= 0) {
            return Response::json(['erreur' => 'Annonceur manquant.'], 422);
        }
        if ($endpoint === '' || mb_strlen($endpoint) > 500 || !str_starts_with($endpoint, 'https://')) {
            return Response::json(['erreur' => 'Adresse d’abonnement invalide.'], 422);
        }
        if ($p256dh === '' || $auth === '') {
            return Response::json(['erreur' => 'Clés de chiffrement manquantes.'], 422);
        }

        // L’annonceur doit exister et être actif
        $existe = (int) Database::instance()->selectValue(
            "SELECT COUNT(*) FROM annonceurs WHERE id = :id AND statut = 'actif'",
            ['id' => $annonceurId],
            0
        );
        if ($existe === 0) {
            return Response::json(['erreur' => 'Annonceur inconnu.'], 404);
        }

        $abonneId = Abonne::enregistrer($annonceurId, $endpoint, $p256dh, $auth, $frequence);

        // Trace de l’abonnement côté mesure
        Evenement::enregistrer([
            'type'             => 'abonnement',
            'code_court_id'    => $this->entierOuNull($corps['code_court_id'] ?? null),
            'page_id'          => $this->entierOuNull($corps['page_id'] ?? null),
            'annonceur_id'     => $annonceurId,
            'campagne_id'      => $this->entierOuNull($corps['campagne_id'] ?? null),
            'panneau_id'       => $this->entierOuNull($corps['panneau_id'] ?? null),
            'session_id'       => $this->session($corps['session_id'] ?? null),
            'empreinte_reseau' => empreinte_reseau($request->ip()),
            'agent'            => $request->agent(),
            'meta'             => ['frequence' => $frequence],
        ]);

        // Mémorise l’état pour l’affichage de la page
        $_SESSION['_abonnements'][$annonceurId] = true;

        return Response::json([
            'ok'        => true,
            'abonne'    => $abonneId,
            'frequence' => in_array($frequence, ['chaque_annonce', 'hebdomadaire'], true) ? $frequence : 'chaque_annonce',
            'plafond'   => PLAFOND_NOTIFICATIONS_MOIS,
        ], 201);
    }

    // -----------------------------------------------------------------------
    // POST /api/abonnements/suppression
    // -----------------------------------------------------------------------

    public function desabonnement(Request $request): Response
    {
        if ($refus = $this->garde($request, 'desabonnements', 30)) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $corps    = $request->corps();
        $endpoint = trim((string) ($corps['endpoint'] ?? ''));

        if ($endpoint === '') {
            return Response::json(['erreur' => 'Adresse d’abonnement manquante.'], 422);
        }

        $touches = Abonne::desactiverParEndpoint($endpoint);

        if (!empty($corps['annonceur_id'])) {
            unset($_SESSION['_abonnements'][(int) $corps['annonceur_id']]);

            Evenement::enregistrer([
                'type'             => 'desabonnement',
                'annonceur_id'     => (int) $corps['annonceur_id'],
                'session_id'       => $this->session($corps['session_id'] ?? null),
                'empreinte_reseau' => empreinte_reseau($request->ip()),
                'agent'            => $request->agent(),
            ]);
        }

        return Response::json(['ok' => true, 'desabonnes' => $touches]);
    }

    // -----------------------------------------------------------------------
    // GET /api/campagnes/{id}/statistiques
    // -----------------------------------------------------------------------

    public function statistiquesCampagne(Request $request, array $params): Response
    {
        $campagneId = (int) ($params['id'] ?? 0);

        if (!Auth::verifie()) {
            return Response::json(['erreur' => 'Authentification requise.'], 401);
        }

        $campagne = Database::instance()->selectOne(
            'SELECT id, annonceur_id, nom FROM campagnes WHERE id = :id',
            ['id' => $campagneId]
        );
        if ($campagne === null) {
            return Response::json(['erreur' => 'Campagne introuvable.'], 404);
        }

        // Un annonceur ne consulte que ses propres campagnes
        if (Auth::role() === 'annonceur') {
            $utilisateur = Auth::utilisateur();
            if ((int) ($utilisateur['annonceur_id'] ?? 0) !== (int) $campagne['annonceur_id']) {
                return Response::json(['erreur' => 'Accès refusé.'], 403);
            }
        }

        return Response::json([
            'campagne'  => ['id' => (int) $campagne['id'], 'nom' => $campagne['nom']],
            'mesure'    => Evenement::statistiquesCampagne($campagneId)['mesure'],
            'estimation' => Evenement::statistiquesCampagne($campagneId)['estime'],
            'serie'     => Evenement::serieQuotidienne($campagneId, 30),
        ]);
    }

    // -----------------------------------------------------------------------
    // GET /api/sante — contrôle de disponibilité
    // -----------------------------------------------------------------------

    public function sante(Request $request): Response
    {
        return Response::json([
            'ok'      => true,
            'service' => APP_NAME,
            'version' => APP_VERSION,
            'heure'   => date('c'),
        ]);
    }

    // -----------------------------------------------------------------------
    // Fabriques internes
    // -----------------------------------------------------------------------

    /** Limitation de débit commune aux points d’écriture. */
    private function garde(Request $request, string $cle, int $max): ?Response
    {
        $resultat = RateLimiter::verifier($cle, $request->ip(), $max, 60);

        if (!$resultat['autorise']) {
            return Response::json([
                'erreur'   => 'Trop de requêtes. Merci de réessayer dans un instant.',
                'reessayer_dans' => $resultat['reessayer_dans'],
            ], 429)->avecEntete('Retry-After', (string) $resultat['reessayer_dans']);
        }

        return null;
    }

    /** Identifiant de session anonyme, validé comme UUID v4. */
    private function session(mixed $valeur): ?string
    {
        $valeur = is_string($valeur) ? trim($valeur) : '';

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $valeur)) {
            return strtolower($valeur);
        }

        return null;
    }

    private function entierOuNull(mixed $valeur): ?int
    {
        return is_numeric($valeur) && (int) $valeur > 0 ? (int) $valeur : null;
    }
}
