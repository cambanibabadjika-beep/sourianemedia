<?php
/**
 * SOURIANE — Contrôleur du back-office
 *
 * Couvre le pilotage (tableau de bord, statistiques), le commercial (annonceurs,
 * campagnes, panneaux), la diffusion (pages, QR codes, notifications),
 * l’exploitation (preuves de pose, journal) et le système (comptes, paramètres).
 *
 * Contrôle d’accès (EF-G01) :
 *   regie     → accès complet
 *   agent     → terrain : parc, poses, QR codes, lecture des campagnes
 *   annonceur → ses seules campagnes et leurs statistiques
 */

declare(strict_types=1);

namespace Souriane\Controllers;

use Souriane\Core\Auth;
use Souriane\Core\Csrf;
use Souriane\Core\Database as Db;
use Souriane\Core\Flash;
use Souriane\Core\Request;
use Souriane\Core\Response;
use Souriane\Core\View;
use Souriane\Models\Abonne;
use Souriane\Models\Annonceur;
use Souriane\Models\Campagne;
use Souriane\Models\CodeCourt;
use Souriane\Models\Demande;
use Souriane\Models\Evenement;
use Souriane\Models\Journal;
use Souriane\Models\Notification;
use Souriane\Models\Page;
use Souriane\Models\Panneau;
use Souriane\Models\Parametre;
use Souriane\Models\Utilisateur;
use Souriane\Models\Visite;
use Souriane\Services\DiffusionService;
use Souriane\Services\QrService;
use Souriane\Services\SecondFacteur;
use Souriane\Services\UploadService;

final class AdminController
{
    /** Rôles autorisés par rubrique. */
    private const ACCES = [
        'tableau-de-bord' => ['regie', 'agent', 'annonceur'],
        'statistiques'    => ['regie', 'agent', 'annonceur'],
        'annonceurs'      => ['regie'],
        'demandes'        => ['regie'],
        'campagnes'       => ['regie', 'agent', 'annonceur'],
        'panneaux'        => ['regie', 'agent'],
        'pages'           => ['regie'],
        'codes'           => ['regie', 'agent'],
        'notifications'   => ['regie'],
        'poses'           => ['regie', 'agent', 'annonceur'],
        'journal'         => ['regie'],
        'utilisateurs'    => ['regie'],
        'parametres'      => ['regie'],
        // Chacun règle la sécurité de son propre compte : la rubrique est
        // ouverte à tous les rôles, y compris l’annonceur.
        'securite'        => ['regie', 'agent', 'annonceur'],
    ];

    // =======================================================================
    // Pilotage
    // =======================================================================

    /** GET /admin */
    public function tableauDeBord(Request $request): Response
    {
        if ($refus = $this->garde('tableau-de-bord')) {
            return $refus;
        }

        $compteurs = Evenement::compteursGlobaux();
        $statuts   = Campagne::compterParStatut();

        $dernieresCampagnes = Auth::role() === 'annonceur'
            ? Campagne::tous(['annonceur_id' => $this->annonceurCourant()])
            : Campagne::tous();

        $enLigne = (int) Db::instance()->selectValue(
            "SELECT COUNT(*) FROM panneaux WHERE statut = 'disponible'",
            [],
            0
        );

        return $this->vue('admin/tableau-de-bord', [
            'titrePage' => 'Tableau de bord',
            'section'   => 'tableau-de-bord',
            'compteurs' => $compteurs,
            'statuts'   => $statuts,
            'campagnes' => array_slice($dernieresCampagnes, 0, 6),
            'parc'      => [
                'total'       => Panneau::compter(),
                'disponibles' => $enLigne,
                'villes'      => count(Panneau::villes()),
            ],
            'diffusion' => [
                'annonceurs'   => Annonceur::compter(),
                'abonnes'      => (int) Db::instance()->selectValue("SELECT COUNT(*) FROM abonnes WHERE statut = 'actif'", [], 0),
                'visiteurs'    => Visite::compter(30),
                'codes'        => (int) Db::instance()->selectValue('SELECT COUNT(*) FROM codes_courts WHERE actif = 1', [], 0),
            ],
            'journal'   => Auth::role() === 'regie' ? Journal::dernieres(6) : [],
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** GET /admin/statistiques */
    public function statistiques(Request $request): Response
    {
        if ($refus = $this->garde('statistiques')) {
            return $refus;
        }

        $campagnes = Auth::role() === 'annonceur'
            ? Campagne::tous(['annonceur_id' => $this->annonceurCourant()])
            : Campagne::tous();

        $campagneId = (int) ($request->query['campagne'] ?? ($campagnes[0]['id'] ?? 0));
        $detail     = $campagneId > 0 ? Evenement::statistiquesCampagne($campagneId) : null;
        $serie      = $campagneId > 0 ? Evenement::serieQuotidienne($campagneId, 30) : [];

        return $this->vue('admin/statistiques', [
            'titrePage'  => 'Statistiques',
            'section'    => 'statistiques',
            'campagnes'  => $campagnes,
            'campagneId' => $campagneId,
            'detail'     => $detail,
            'serie'      => $serie,
            'appareils'  => Visite::parAppareil(30),
            'globaux'    => Evenement::compteursGlobaux(),
            'pastilles'  => $this->pastilles(),
        ]);
    }

    // =======================================================================
    // Rapport de fin de campagne (EF-F06, EF-F07)
    // =======================================================================

    /**
     * GET /admin/campagnes/{id}/rapport — rapport de fin de campagne.
     *
     * Rendu sans gabarit d’administration : le rapport est destiné à être
     * imprimé ou transmis à l’annonceur, pas consulté dans le back-office.
     */
    public function campagneRapport(Request $request, array $params): Response
    {
        [$campagne, $refus] = $this->campagneAutorisee((int) ($params['id'] ?? 0));
        if ($refus !== null) {
            return $refus;
        }

        $html = View::rendre('admin/rapport-campagne', $this->donneesRapport((int) $campagne['id']));

        return Response::html($html)->avecEntete('Cache-Control', 'no-store, private');
    }

    /**
     * GET /admin/campagnes/{id}/rapport.csv — le même rapport, en tableur.
     *
     * L’export est produit à partir de l’assemblage qui alimente la page : un
     * second chemin de calcul finirait par diverger, et un export qui
     * contredit l’écran ne vaut rien.
     */
    public function campagneRapportCsv(Request $request, array $params): Response
    {
        [$campagne, $refus] = $this->campagneAutorisee((int) ($params['id'] ?? 0));
        if ($refus !== null) {
            return $refus;
        }

        $donnees = $this->donneesRapport((int) $campagne['id']);

        $nom = strtolower((string) $campagne['nom']);
        $nom = trim((string) preg_replace('/[^a-z0-9]+/', '-', $nom), '-');
        $nom = ($nom !== '' ? $nom : 'campagne') . '-' . date('Ymd') . '.csv';

        return Response::texte($this->csvRapport($donnees))
            ->avecEntete('Content-Type', 'text/csv; charset=utf-8')
            ->avecEntete('Content-Disposition', 'attachment; filename="' . $nom . '"');
    }

    /**
     * Charge une campagne et vérifie que le compte connecté peut la lire.
     *
     * Un annonceur ne doit atteindre que ses propres campagnes : le rôle ouvre
     * la rubrique, il n’ouvre pas les données des autres (EF-G01).
     *
     * @return array{0: ?array, 1: ?Response} La campagne, puis la réponse de
     *         refus lorsqu’il y en a une — la campagne est alors nulle.
     */
    private function campagneAutorisee(int $id): array
    {
        if ($refus = $this->garde('campagnes')) {
            return [null, $refus];
        }

        $campagne = $id > 0 ? Campagne::trouver($id) : null;
        if ($campagne === null) {
            return [null, $this->introuvable('Campagne introuvable.')];
        }

        if (Auth::role() === 'annonceur' && (int) $campagne['annonceur_id'] !== $this->annonceurCourant()) {
            return [null, $this->refuse()];
        }

        return [$campagne, null];
    }

    /**
     * Assemble les données du rapport de fin de campagne.
     *
     * Trois natures de chiffres cohabitent, et le rapport doit les tenir
     * séparées de bout en bout (EF-F07) :
     *   — mesuré,  ce que la Plateforme a réellement enregistré ;
     *   — estimé,  ce qu’elle déduit du trafic déclaré par extrapolation ;
     *   — déclaré, ce que la régie a saisi sur la fiche panneau.
     *
     * L’attribution par panneau vient des codes courts : chaque couple
     * campagne × panneau porte son propre code, donc son propre compteur
     * (EF-C01). C’est ce qui permet de dire quel panneau a produit quel scan.
     */
    private function donneesRapport(int $campagneId): array
    {
        $campagne = Campagne::trouver($campagneId) ?? [];
        $detail   = Evenement::statistiquesCampagne($campagneId);

        $debut = (string) ($campagne['date_debut'] ?? '');
        $fin   = (string) ($campagne['date_fin'] ?? '');
        $duree = 1;
        if ($debut !== '' && $fin !== '') {
            $duree = max(1, min(180, (int) round((strtotime($fin) - strtotime($debut)) / 86400) + 1));
        }

        // Mesures rattachées à chaque code court, donc à chaque panneau
        $parPanneau = [];
        foreach (CodeCourt::tous(['campagne_id' => $campagneId]) as $code) {
            $pid = (int) $code['panneau_id'];
            if (!isset($parPanneau[$pid])) {
                $parPanneau[$pid] = ['scans' => 0, 'visiteurs' => 0, 'codes' => 0];
            }
            $parPanneau[$pid]['scans']     += (int) $code['scans'];
            $parPanneau[$pid]['visiteurs'] += (int) $code['scans_session'];
            $parPanneau[$pid]['codes']++;
        }

        $panneaux = [];
        foreach (Campagne::panneaux($campagneId) as $ligne) {
            $pid    = (int) $ligne['panneau_id'];
            $mesure = $parPanneau[$pid] ?? ['scans' => 0, 'visiteurs' => 0, 'codes' => 0];
            $trafic = (int) ($ligne['trafic_estime'] ?? 0);

            $panneaux[] = [
                'reference'        => (string) $ligne['reference'],
                'libelle'          => (string) $ligne['libelle'],
                'ville'            => (string) ($ligne['ville'] ?? ''),
                'format'           => (string) ($ligne['format'] ?? ''),
                // mesuré
                'scans'            => $mesure['scans'],
                'visiteurs'        => $mesure['visiteurs'],
                'codes'            => $mesure['codes'],
                // déclaré
                'trafic_declare'   => $trafic,
                // estimé, à partir du déclaré et de la durée
                'passages_estimes' => $trafic * $duree,
                'scans_attendus'   => (int) round($trafic * $duree * 0.01),
            ];
        }

        $totaux = [
            'panneaux'         => count($panneaux),
            'scans'            => array_sum(array_column($panneaux, 'scans')),
            'passages_estimes' => array_sum(array_column($panneaux, 'passages_estimes')),
            // Le nombre de visiteurs uniques reste celui de la campagne : la
            // même personne peut avoir scanné deux panneaux, une somme par
            // panneau compterait donc des doublons.
            'visiteurs'        => (int) ($detail['mesure']['visiteurs_uniques'] ?? 0),
        ];

        return [
            'campagne'    => $campagne,
            'duree'       => $duree,
            'indicateurs' => Evenement::INDICATEURS_MESURES,
            'mesure'      => $detail['mesure'],
            'estime'      => $detail['estime'],
            'declare'     => $detail['declare'],
            // La série est bornée par la période de la campagne, pas par les
            // trente derniers jours : un rapport de fin de campagne porte sur
            // la campagne, et une campagne close ne produit plus d’événement.
            'serie'       => ($debut !== '' && $fin !== '')
                ? Evenement::seriePeriode($campagneId, $debut, $fin)
                : Evenement::serieQuotidienne($campagneId, $duree),
            'panneaux'    => $panneaux,
            'totaux'      => $totaux,
            'edite'       => date('d/m/Y à H:i'),
        ];
    }

    /** Échappe un champ CSV : tout est cité, les guillemets sont doublés. */
    private function csvChamp(string $valeur): string
    {
        return '"' . str_replace('"', '""', $valeur) . '"';
    }

    /**
     * Met le rapport en forme pour un tableur.
     *
     * Séparateur point-virgule et marque d’ordre des octets en tête : le
     * tableur français attend le point-virgule, et sans la marque il affiche
     * « Mesuré » en « MesurÃ© ». Un export que son destinataire ne peut pas
     * lire ne remplit pas EF-F06.
     *
     * Chaque ligne porte sa nature dans sa première colonne : le tri, le
     * filtre ou la suppression d’une colonne ne peuvent pas faire passer un
     * chiffre estimé pour une mesure (EF-F07).
     */
    private function csvRapport(array $d): string
    {
        $campagne = $d['campagne'];
        $lignes   = [];

        $lignes[] = ['Rapport de fin de campagne', (string) ($campagne['nom'] ?? '')];
        $lignes[] = ['Annonceur', (string) ($campagne['annonceur_nom'] ?? '')];
        $lignes[] = ['Période', date_fr((string) ($campagne['date_debut'] ?? ''))
            . ' au ' . date_fr((string) ($campagne['date_fin'] ?? ''))];
        $lignes[] = ['Durée', $d['duree'] . ' jour(s)'];
        $lignes[] = ['Panneaux', (string) $d['totaux']['panneaux']];
        $lignes[] = ['Édité le', (string) $d['edite']];
        $lignes[] = [];

        $lignes[] = ['Nature', 'Indicateur', 'Valeur', 'Précision'];
        foreach ($d['indicateurs'] as $indicateur) {
            $lignes[] = [
                'Mesuré',
                $indicateur['libelle'],
                (string) (int) ($d['mesure'][$indicateur['cle']] ?? 0),
                'Interaction enregistrée par la Plateforme',
            ];
        }
        $lignes[] = ['Estimé', 'Trafic journalier retenu', (string) (int) $d['estime']['trafic_journalier'],
            'Somme des trafics déclarés sur les fiches panneaux'];
        $lignes[] = ['Estimé', 'Passages cumulés', (string) (int) $d['estime']['passages_cumules'],
            'Trafic journalier × ' . $d['duree'] . ' jour(s)'];
        $lignes[] = ['Estimé', 'Scans attendus', (string) (int) $d['estime']['scans_estimes'],
            (string) $d['estime']['methode']];
        $lignes[] = ['Déclaré', 'Source du trafic', '',
            (string) $d['declare']['source']];
        $lignes[] = [];

        $lignes[] = [
            'Nature', 'Panneau', 'Emplacement', 'Format',
            'Scans (mesuré)', 'Visiteurs (mesuré)', 'Trafic par jour (déclaré)',
            'Passages cumulés (estimé)', 'Scans attendus (estimé)',
        ];
        foreach ($d['panneaux'] as $p) {
            $lignes[] = [
                'Mixte',
                $p['reference'],
                $p['libelle'] . ($p['ville'] !== '' ? ' — ' . $p['ville'] : ''),
                $p['format'],
                (string) $p['scans'],
                (string) $p['visiteurs'],
                (string) $p['trafic_declare'],
                (string) $p['passages_estimes'],
                (string) $p['scans_attendus'],
            ];
        }
        $lignes[] = ['Mixte', 'Total', '', '', (string) $d['totaux']['scans'],
            (string) $d['totaux']['visiteurs'], '', (string) $d['totaux']['passages_estimes'], ''];

        $sortie = "\xEF\xBB\xBF"; // marque d’ordre des octets, pour le tableur
        foreach ($lignes as $ligne) {
            $sortie .= implode(';', array_map(fn ($v) => $this->csvChamp((string) $v), $ligne)) . "\r\n";
        }

        return $sortie;
    }

    // =======================================================================
    // Annonceurs
    // =======================================================================

    /** GET /admin/annonceurs */
    public function annonceurs(Request $request): Response
    {
        if ($refus = $this->garde('annonceurs')) {
            return $refus;
        }

        return $this->vue('admin/annonceurs', [
            'titrePage' => 'Annonceurs',
            'section'   => 'annonceurs',
            'annonceurs' => Annonceur::tous([
                'recherche' => $request->string('q'),
                'statut'    => $request->string('statut'),
            ]),
            'recherche' => $request->string('q'),
            'statut'    => $request->string('statut'),
            'actionsEntete' => '<a class="btn btn--primaire btn--petit" href="' . e(url('admin/annonceurs/nouveau')) . '">'
                . \Souriane\Core\Icone::svg('ajouter') . 'Nouvel annonceur</a>',
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** GET /admin/annonceurs/nouveau | /admin/annonceurs/{id} */
    public function annonceurFormulaire(Request $request, array $params): Response
    {
        if ($refus = $this->garde('annonceurs')) {
            return $refus;
        }

        $id       = isset($params['id']) ? (int) $params['id'] : 0;
        $annonceur = $id > 0 ? Annonceur::trouver($id) : null;

        if ($id > 0 && $annonceur === null) {
            return $this->introuvable('Annonceur introuvable.');
        }

        return $this->vue('admin/annonceur-form', [
            'titrePage' => $annonceur === null ? 'Nouvel annonceur' : 'Modifier « ' . $annonceur['nom'] . ' »',
            'section'   => 'annonceurs',
            'annonceur' => $annonceur,
            'page'      => $annonceur !== null ? Page::parAnnonceur((int) $annonceur['id']) : null,
            'campagnes' => $annonceur !== null ? Campagne::tous(['annonceur_id' => (int) $annonceur['id']]) : [],
            'abonnes'   => $annonceur !== null ? Abonne::compter((int) $annonceur['id']) : 0,
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** POST /admin/annonceurs/nouveau | /admin/annonceurs/{id} */
    public function annonceurEnregistrer(Request $request, array $params): Response
    {
        if ($refus = $this->garde('annonceurs')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;

        $nom = $request->string('nom');
        if ($nom === '') {
            Flash::erreur('Le nom de l’annonceur est obligatoire.');

            return Response::redirect($id > 0 ? url('admin/annonceurs/' . $id) : url('admin/annonceurs/nouveau'));
        }

        $donnees = [
            'nom'           => $nom,
            'adresse_courte' => $request->string('adresse_courte') ?: $nom,
            'description'   => $request->string('description') ?: null,
            'telephone'     => $request->string('telephone') ?: null,
            'whatsapp'      => $request->string('whatsapp') ?: null,
            'email'         => $request->string('email') ?: null,
            'adresse'       => $request->string('adresse') ?: null,
            'ville'         => $request->string('ville') ?: null,
            'latitude'      => $request->string('latitude') !== '' ? (float) $request->string('latitude') : null,
            'longitude'     => $request->string('longitude') !== '' ? (float) $request->string('longitude') : null,
            'statut'        => in_array($request->string('statut'), ['actif', 'inactif', 'archive'], true)
                ? $request->string('statut') : 'actif',
            'reseaux'       => json_encode([
                'facebook'  => $request->string('facebook'),
                'instagram' => $request->string('instagram'),
                'tiktok'    => $request->string('tiktok'),
                'youtube'   => $request->string('youtube'),
                'site'      => $request->string('site'),
            ], JSON_UNESCAPED_UNICODE),
            'horaires'      => json_encode($this->lireHoraires($request), JSON_UNESCAPED_UNICODE),
        ];

        if ($id > 0) {
            Annonceur::modifier($id, $donnees);
            journal_operation(Auth::id(), 'modification', 'annonceurs', $id, 'Fiche annonceur mise à jour', $request->ip());
            Flash::succes('Fiche annonceur enregistrée.');
        } else {
            $id = Annonceur::creer($donnees);
            journal_operation(Auth::id(), 'creation', 'annonceurs', $id, 'Création de l’annonceur « ' . $nom . ' »', $request->ip());
            Flash::succes('Annonceur créé. Vous pouvez maintenant créer sa page publique.');

            return Response::redirect(url('admin/annonceurs/' . $id));
        }

        return Response::redirect(url('admin/annonceurs/' . $id));
    }

    // =======================================================================
    // Campagnes
    // =======================================================================

    /** GET /admin/campagnes */
    public function campagnes(Request $request): Response
    {
        if ($refus = $this->garde('campagnes')) {
            return $refus;
        }

        $filtres = [
            'statut'    => $request->string('statut'),
            'recherche' => $request->string('q'),
        ];
        if (Auth::role() === 'annonceur') {
            $filtres['annonceur_id'] = $this->annonceurCourant();
        }

        return $this->vue('admin/campagnes', [
            'titrePage' => 'Campagnes',
            'section'   => 'campagnes',
            'campagnes' => Campagne::tous($filtres),
            'statuts'   => Campagne::compterParStatut(),
            'recherche' => $request->string('q'),
            'statut'    => $request->string('statut'),
            'lectureSeule' => Auth::role() === 'annonceur',
            'actionsEntete' => Auth::role() === 'regie'
                ? '<a class="btn btn--primaire btn--petit" href="' . e(url('admin/campagnes/nouveau')) . '">'
                    . \Souriane\Core\Icone::svg('ajouter') . 'Nouvelle campagne</a>'
                : '',
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** GET /admin/campagnes/nouveau | /admin/campagnes/{id} */
    public function campagneFormulaire(Request $request, array $params): Response
    {
        if ($refus = $this->garde('campagnes')) {
            return $refus;
        }

        $id       = isset($params['id']) ? (int) $params['id'] : 0;
        $campagne = $id > 0 ? Campagne::trouver($id) : null;

        if ($id > 0 && $campagne === null) {
            return $this->introuvable('Campagne introuvable.');
        }
        if ($campagne !== null && Auth::role() === 'annonceur' && (int) $campagne['annonceur_id'] !== $this->annonceurCourant()) {
            return $this->refuse();
        }

        $lectureSeule = Auth::role() !== 'regie';
        $tousPanneaux = Panneau::tous();

        // Disponibilité de chaque support sur la période de la campagne
        $disponibilites = [];
        if ($campagne !== null) {
            foreach ($tousPanneaux as $panneau) {
                $disponibilites[(int) $panneau['id']] = Panneau::disponibleSurPeriode(
                    (int) $panneau['id'],
                    (string) $campagne['date_debut'],
                    (string) $campagne['date_fin'],
                    (int) $campagne['id']
                );
            }
        }

        // Preuves de pose associées à cette campagne
        $poses = $campagne !== null ? Db::instance()->select(
            'SELECT po.*, p.reference, p.libelle, p.ville, p.format, u.nom AS agent_nom
             FROM poses po
             JOIN panneaux p ON p.id = po.panneau_id
             LEFT JOIN utilisateurs u ON u.id = po.agent_id
             WHERE po.campagne_id = :cid
             ORDER BY po.horodatage DESC',
            ['cid' => (int) $campagne['id']]
        ) : [];

        return $this->vue('admin/campagne-form', [
            'titrePage'   => $campagne === null ? 'Nouvelle campagne' : 'Campagne « ' . $campagne['nom'] . ' »',
            'section'     => 'campagnes',
            'campagne'    => $campagne,
            'annonceurs'  => Annonceur::tous(['statut' => 'actif']),
            'formules'    => Campagne::formules(),
            'panneaux'    => $tousPanneaux,
            'associes'    => $campagne !== null ? Campagne::panneaux((int) $campagne['id']) : [],
            'disponibilites' => $disponibilites,
            'codes'       => $campagne !== null ? CodeCourt::tous(['campagne_id' => (int) $campagne['id']]) : [],
            'poses'       => $poses,
            'stats'       => $campagne !== null ? Evenement::statistiquesCampagne((int) $campagne['id']) : null,
            'lectureSeule' => $lectureSeule,
            'pastilles'   => $this->pastilles(),
        ]);
    }

    /** POST /admin/campagnes/nouveau | /admin/campagnes/{id} */
    public function campagneEnregistrer(Request $request, array $params): Response
    {
        if ($refus = $this->garde('campagnes')) {
            return $refus;
        }
        if (Auth::role() !== 'regie') {
            return $this->refuse();
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $id  = isset($params['id']) ? (int) $params['id'] : 0;
        $nom = $request->string('nom');
        $annonceurId = $request->int('annonceur_id');
        $debut = $request->string('date_debut');
        $fin   = $request->string('date_fin');

        $retour = $id > 0 ? url('admin/campagnes/' . $id) : url('admin/campagnes/nouveau');

        if ($nom === '' || $annonceurId <= 0 || $debut === '' || $fin === '') {
            Flash::erreur('Nom, annonceur et période sont obligatoires.');

            return Response::redirect($retour);
        }
        if (strtotime($fin) < strtotime($debut)) {
            Flash::erreur('La date de fin doit être postérieure à la date de début.');

            return Response::redirect($retour);
        }

        $formuleId = $request->int('formule_id');
        $donnees = [
            'annonceur_id'   => $annonceurId,
            'formule_id'     => $formuleId > 0 ? $formuleId : null,
            'nom'            => $nom,
            'date_debut'     => $debut,
            'date_fin'       => $fin,
            'moyen_paiement' => in_array($request->string('moyen_paiement'), ['aucun', 'wave', 'orange_money', 'especes', 'virement'], true)
                ? $request->string('moyen_paiement') : 'aucun',
            'reference_paiement' => $request->string('reference_paiement') ?: null,
            'note'           => $request->string('note') ?: null,
        ];

        if ($id > 0) {
            Campagne::modifier($id, $donnees);
            journal_operation(Auth::id(), 'modification', 'campagnes', $id, 'Campagne mise à jour', $request->ip());
            Flash::succes('Campagne enregistrée.');
        } else {
            $id = Campagne::creer($donnees);
            journal_operation(Auth::id(), 'creation', 'campagnes', $id, 'Création de la campagne « ' . $nom . ' »', $request->ip());
            Flash::succes('Campagne créée. Sélectionnez les panneaux puis validez la réservation.');
        }

        return Response::redirect(url('admin/campagnes/' . $id));
    }

    /** POST /admin/campagnes/{id}/reserver */
    public function campagneReserver(Request $request, array $params): Response
    {
        if ($refus = $this->garde('campagnes')) {
            return $refus;
        }
        if (Auth::role() !== 'regie') {
            return $this->refuse();
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $id       = (int) ($params['id'] ?? 0);
        $campagne = Campagne::trouver($id);
        if ($campagne === null) {
            return $this->introuvable('Campagne introuvable.');
        }

        $panneauIds = array_values(array_filter(array_map('intval', (array) $request->input('panneaux', []))));

        if ($panneauIds === []) {
            Flash::erreur('Sélectionnez au moins un panneau.');

            return Response::redirect(url('admin/campagnes/' . $id));
        }

        try {
            Campagne::reserver($id, $panneauIds, (string) $campagne['date_debut'], (string) $campagne['date_fin']);
        } catch (\Throwable $e) {
            Flash::erreur($e->getMessage());

            return Response::redirect(url('admin/campagnes/' . $id));
        }

        $crees = CodeCourt::genererPourCampagne($id);

        journal_operation(
            Auth::id(),
            'reservation',
            'campagnes',
            $id,
            sprintf('%d panneau(x) réservé(s), %d code(s) court(s) généré(s)', count($panneauIds), $crees),
            $request->ip()
        );

        Flash::succes(sprintf(
            'Réservation confirmée : %d panneau(x), %d code(s) QR généré(s).',
            count($panneauIds),
            $crees
        ));

        return Response::redirect(url('admin/campagnes/' . $id));
    }

    /** POST /admin/campagnes/{id}/retirer */
    public function campagneRetirerPanneau(Request $request, array $params): Response
    {
        if ($refus = $this->garde('campagnes')) {
            return $refus;
        }
        if (Auth::role() !== 'regie') {
            return $this->refuse();
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $id         = (int) ($params['id'] ?? 0);
        $panneauId  = $request->int('panneau_id');

        if ($panneauId > 0) {
            Campagne::retirerPanneau($id, $panneauId);
            journal_operation(Auth::id(), 'retrait', 'campagnes', $id, 'Panneau #' . $panneauId . ' retiré', $request->ip());
            Flash::succes('Panneau retiré de la campagne.');
        }

        return Response::redirect(url('admin/campagnes/' . $id));
    }

    // =======================================================================
    // Panneaux
    // =======================================================================

    /** GET /admin/panneaux */
    public function panneaux(Request $request): Response
    {
        if ($refus = $this->garde('panneaux')) {
            return $refus;
        }

        return $this->vue('admin/panneaux', [
            'titrePage' => 'Panneaux',
            'section'   => 'panneaux',
            'panneaux'  => Panneau::tous([
                'statut'    => $request->string('statut'),
                'ville'     => $request->string('ville'),
                'recherche' => $request->string('q'),
            ]),
            'villes'    => Panneau::villes(),
            'formats'   => Panneau::formats(),
            'recherche' => $request->string('q'),
            'statut'    => $request->string('statut'),
            'ville'     => $request->string('ville'),
            'actionsEntete' => Auth::role() === 'regie'
                ? '<a class="btn btn--primaire btn--petit" href="' . e(url('admin/panneaux/nouveau')) . '">'
                    . \Souriane\Core\Icone::svg('ajouter') . 'Nouveau panneau</a>'
                : '',
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** GET /admin/panneaux/nouveau | /admin/panneaux/{id} */
    public function panneauFormulaire(Request $request, array $params): Response
    {
        if ($refus = $this->garde('panneaux')) {
            return $refus;
        }

        $id      = isset($params['id']) ? (int) $params['id'] : 0;
        $panneau = $id > 0 ? Panneau::trouver($id) : null;

        if ($id > 0 && $panneau === null) {
            return $this->introuvable('Panneau introuvable.');
        }

        return $this->vue('admin/panneau-form', [
            'titrePage' => $panneau === null ? 'Nouveau panneau' : 'Panneau ' . $panneau['reference'],
            'section'   => 'panneaux',
            'panneau'   => $panneau,
            'calendrier' => $panneau !== null ? Panneau::calendrier((int) $panneau['id'], 8) : [],
            'poses'     => $panneau !== null ? $this->posesDuPanneau((int) $panneau['id']) : [],
            'lectureSeule' => Auth::role() !== 'regie',
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** POST /admin/panneaux/nouveau | /admin/panneaux/{id} */
    public function panneauEnregistrer(Request $request, array $params): Response
    {
        if ($refus = $this->garde('panneaux')) {
            return $refus;
        }
        if (Auth::role() !== 'regie') {
            return $this->refuse();
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $id        = isset($params['id']) ? (int) $params['id'] : 0;
        $reference = strtoupper($request->string('reference'));
        $libelle   = $request->string('libelle');

        $retour = $id > 0 ? url('admin/panneaux/' . $id) : url('admin/panneaux/nouveau');

        if ($reference === '' || $libelle === '') {
            Flash::erreur('La référence et le libellé sont obligatoires.');

            return Response::redirect($retour);
        }

        $statut = $request->string('statut');
        if (!in_array($statut, ['disponible', 'reserve', 'occupe', 'maintenance', 'archive'], true)) {
            $statut = 'disponible';
        }

        $donnees = [
            'reference'     => $reference,
            'libelle'       => $libelle,
            'adresse'       => $request->string('adresse') ?: null,
            'ville'         => $request->string('ville') ?: null,
            'latitude'      => $request->string('latitude') !== '' ? (float) $request->string('latitude') : null,
            'longitude'     => $request->string('longitude') !== '' ? (float) $request->string('longitude') : null,
            'format'        => $request->string('format') ?: null,
            'faces'         => max(1, min(4, $request->int('faces', 1))),
            'trafic_estime' => $request->int('trafic_estime') ?: null,
            'tarif'         => $request->string('tarif') !== '' ? (float) $request->string('tarif') : null,
            'statut'        => $statut,
        ];

        try {
            if ($id > 0) {
                Panneau::modifier($id, $donnees);
                journal_operation(Auth::id(), 'modification', 'panneaux', $id, 'Fiche panneau mise à jour', $request->ip());
                Flash::succes('Panneau enregistré.');
            } else {
                $id = Panneau::creer($donnees);
                journal_operation(Auth::id(), 'creation', 'panneaux', $id, 'Création du panneau ' . $reference, $request->ip());
                Flash::succes('Panneau créé.');
            }
        } catch (\Throwable $e) {
            Flash::erreur('Enregistrement impossible : référence déjà utilisée ?');

            return Response::redirect($retour);
        }

        return Response::redirect(url('admin/panneaux/' . $id));
    }

    // =======================================================================
    // Pages publiques
    // =======================================================================

    /** GET /admin/pages */
    public function pages(Request $request): Response
    {
        if ($refus = $this->garde('pages')) {
            return $refus;
        }

        return $this->vue('admin/pages', [
            'titrePage' => 'Pages publiques',
            'section'   => 'pages',
            'pages'     => Page::toutes(),
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** GET /admin/pages/{id} */
    public function pageFormulaire(Request $request, array $params): Response
    {
        if ($refus = $this->garde('pages')) {
            return $refus;
        }

        $id   = (int) ($params['id'] ?? 0);
        $page = Page::trouver($id);

        if ($page === null) {
            return $this->introuvable('Page introuvable.');
        }

        $annonceur = Annonceur::trouver((int) $page['annonceur_id']);

        return $this->vue('admin/page-form', [
            'titrePage' => 'Page de ' . ($annonceur['nom'] ?? '—'),
            'section'   => 'pages',
            'page'      => $page,
            'annonceur' => $annonceur,
            'sections'  => Page::toutesSections($id),
            'types'     => Page::ORDRE_IMPOSE,
            'campagnes' => Campagne::tous(['annonceur_id' => (int) $page['annonceur_id']]),
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** POST /admin/pages/{id} */
    public function pageEnregistrer(Request $request, array $params): Response
    {
        if ($refus = $this->garde('pages')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $id   = (int) ($params['id'] ?? 0);
        $page = Page::trouver($id);
        if ($page === null) {
            return $this->introuvable('Page introuvable.');
        }

        Page::modifier($id, [
            'titre'         => $request->string('titre') ?: null,
            'adresse_courte' => $request->string('adresse_courte') ?: $page['adresse_courte'],
            'bandeau_offre' => $request->string('bandeau_offre') ?: null,
            'statut'        => in_array($request->string('statut'), ['brouillon', 'publiee', 'depubliee'], true)
                ? $request->string('statut') : 'brouillon',
        ]);

        // Sections de contenu : toutes celles proposées dans le formulaire sont
        // enregistrées ; la case « affichée » détermine seulement leur visibilité.
        $typesEditables = ['offre', 'presentation', 'infos_complementaires', 'galerie', 'personnalise', 'offres_a_venir'];

        $ordre = 0;
        foreach ($typesEditables as $type) {
            $ordre++;

            $contenu = match ($type) {
                'offre' => [
                    'texte'        => $request->string('offre_texte'),
                    'debut'        => $request->string('offre_debut') ?: null,
                    'fin'          => $request->string('offre_fin') ?: null,
                    'restrictions' => $request->string('offre_restrictions'),
                ],
                'presentation' => ['texte' => $request->string('presentation_texte')],
                'infos_complementaires' => [
                    'texte'      => $request->string('infos_texte'),
                    'conditions' => $request->string('infos_conditions'),
                    'validite'   => $request->string('infos_validite'),
                ],
                'galerie' => [
                    'images' => array_values(array_filter(array_map('trim', explode("\n", $request->string('galerie_images'))))),
                    'videos' => array_values(array_filter(array_map('trim', explode("\n", $request->string('galerie_videos'))))),
                ],
                'personnalise' => ['texte' => $request->string('personnalise_texte')],
                'offres_a_venir' => ['texte' => $request->string('avenir_texte')],
                default => [],
            };

            Page::enregistrerSection(
                $id,
                $type,
                $request->string($type . '_titre') ?: null,
                $contenu,
                $ordre,
                $request->bool('section_' . $type . '_actif')
            );
        }

        journal_operation(Auth::id(), 'modification', 'pages', $id, 'Page publique mise à jour', $request->ip());
        Flash::succes('Page enregistrée.');

        return Response::redirect(url('admin/pages/' . $id));
    }

    // =======================================================================
    // QR codes
    // =======================================================================

    /** GET /admin/codes */
    public function codes(Request $request): Response
    {
        if ($refus = $this->garde('codes')) {
            return $refus;
        }

        $campagneId = $request->int('campagne');

        return $this->vue('admin/codes', [
            'titrePage' => 'QR codes',
            'section'   => 'codes',
            'codes'     => CodeCourt::tous($campagneId > 0 ? ['campagne_id' => $campagneId] : []),
            'campagnes' => Campagne::tous(),
            'campagneId' => $campagneId,
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** POST /admin/codes/generer */
    public function codesGenerer(Request $request): Response
    {
        if ($refus = $this->garde('codes')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $campagneId = $request->int('campagne_id');
        if ($campagneId <= 0) {
            Flash::erreur('Sélectionnez une campagne.');

            return Response::redirect(url('admin/codes'));
        }

        $crees = CodeCourt::genererPourCampagne($campagneId);

        journal_operation(Auth::id(), 'generation_codes', 'codes_courts', null, $crees . ' code(s) généré(s) pour la campagne #' . $campagneId, $request->ip());
        Flash::succes($crees > 0 ? $crees . ' code(s) QR généré(s).' : 'Tous les panneaux de cette campagne ont déjà leur code.');

        return Response::redirect(url('admin/codes?campagne=' . $campagneId));
    }

    /**
     * POST /admin/codes/{id}/destination — change la destination d’un code
     * court sans réimprimer le visuel (EF-C03, AC-03).
     *
     * Le code lui-même, donc le QR imprimé, ne change pas : seule la cible de
     * la redirection est modifiée. C’est tout l’intérêt de l’indirection.
     */
    public function codeDestination(Request $request, array $params): Response
    {
        if ($refus = $this->garde('codes')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $id   = (int) ($params['id'] ?? 0);
        $code = CodeCourt::trouver($id);

        if ($code === null) {
            Flash::erreur('Code introuvable.');

            return Response::redirect(url('admin/codes'));
        }

        $retour = url('admin/codes?campagne=' . (int) $code['campagne_id']);
        $saisie = trim($request->string('destination'));

        // Champ vidé : retour au comportement par défaut, la page de l’annonceur
        if ($saisie === '') {
            CodeCourt::modifier($id, ['destination' => null]);
            journal_operation(
                Auth::id(),
                'modification',
                'codes_courts',
                $id,
                'Destination réinitialisée vers la page de l’annonceur',
                $request->ip()
            );
            Flash::succes('Destination réinitialisée : le code mène de nouveau à la page de l’annonceur.');

            return Response::redirect($retour);
        }

        if (!CodeCourt::destinationValide($saisie)) {
            Flash::erreur(
                'Destination refusée. Indiquez un chemin interne commençant par « / » '
                . '(par exemple /quincaillerie-bignona), ou une adresse complète en « https:// ».'
            );

            return Response::redirect($retour);
        }

        CodeCourt::modifier($id, ['destination' => $saisie]);
        journal_operation(
            Auth::id(),
            'modification',
            'codes_courts',
            $id,
            'Destination du code ' . $code['code'] . ' modifiée',
            $request->ip()
        );
        Flash::succes('Destination modifiée. Le visuel imprimé reste valable : le QR code n’a pas changé.');

        return Response::redirect($retour);
    }

    /**
     * GET /admin/codes/{id}/affiche — affiche imprimable portant le QR code
     * (EF-G06, ch. 5.1).
     *
     * La règle de dimensionnement du cahier des charges est appliquée à la
     * lettre : la largeur du QR code vaut au moins la distance de lecture
     * divisée par dix. Pour cinq mètres, cela donne un symbole de cinquante
     * centimètres. La mesure porte sur le symbole et non sur l’image, la marge
     * de silence ajoutant quatre modules de chaque côté. Le format de papier
     * le plus petit capable de porter l’ensemble — marge comprise — est retenu
     * automatiquement, jusqu’au A0.
     *
     * Deux modèles de visuel sont proposés, conformément à EF-G06 : le grand
     * panneau, lu de loin, et l’affichette de vitrine, lue de près.
     *
     * Paramètres d’adresse : `distance` (mètres, 1 à 20, défaut 5) et `modele`
     * (`panneau` ou `vitrine`).
     */
    public function codeAffiche(Request $request, array $params): Response
    {
        if ($refus = $this->garde('codes')) {
            return $refus;
        }

        $id   = (int) ($params['id'] ?? 0);
        $code = CodeCourt::trouver($id);

        if ($code === null) {
            return $this->introuvable('Code introuvable.');
        }

        $contexte  = CodeCourt::contexte($id) ?? $code;
        $urlCourte = url_courte((string) $code['code']);

        // Distance de lecture visée, en mètres. Sur un panneau grand format en
        // bord de route, le cahier des charges vise 60 à 80 cm de largeur.
        $distance = (float) ($request->query['distance'] ?? 5.0);
        $distance = max(1.0, min(20.0, $distance));

        // Largeur du symbole : distance / 10, en centimètres (ch. 5.1)
        $largeurSymbole = round($distance * 10, 1);

        /*
         * Le code est dessiné en noir sur blanc, et non dans le violet de la
         * charte. Deux raisons, toutes deux tirées du chapitre 5.1 : le code
         * doit être sombre sur fond clair, et il doit rester lisible en plein
         * soleil, après la pluie, sur un support qui vieillit. Le noir pur
         * tolère mieux l’impression bon marché et l’encrassement que n’importe
         * quelle teinte, si contrastée soit-elle à l’écran. Le niveau H — 30 %
         * de redondance, le maximum — est retenu pour la même raison.
         */
        $qr     = QrService::dessiner($urlCourte, 'H', 10, 4, '#000000');
        $facteur = ($qr['modules'] + 2 * $qr['zoneSilence']) / $qr['modules'];

        // Largeur réellement occupée sur le papier : le symbole augmenté de sa
        // marge de silence, qui fait quatre modules de chaque côté.
        $largeurImage = round($largeurSymbole * $facteur, 1);

        // Formats ISO 216, en centimètres, en orientation portrait — donc de
        // largeur égale au plus petit côté. La réserve couvre les marges de la
        // feuille ; la zone de silence, elle, est déjà comprise dans la largeur
        // de l’image et n’est pas comptée deux fois.
        $formats = [
            'A4' => [21.0, 29.7],
            'A3' => [29.7, 42.0],
            'A2' => [42.0, 59.4],
            'A1' => [59.4, 84.1],
            'A0' => [84.1, 118.9],
        ];
        $reserve = 4.0; // marges de la feuille, en centimètres

        $formatRetenu = null;
        foreach ($formats as $nom => [$largeur, $hauteur]) {
            if (min($largeur, $hauteur) - $reserve >= $largeurImage) {
                $formatRetenu = $nom;
                break;
            }
        }

        /*
         * Au-delà du A0, il n’y a plus de format normalisé : la feuille reste
         * en A0 et l’affiche est signalée comme trop petite pour la distance
         * visée. C’est le cas des panneaux grand format du chapitre 5.1, dont
         * la largeur utile de 60 à 80 cm dépasse ce que porte un A0 une fois
         * la marge de silence comptée. Le contrôleur transmet alors les
         * dimensions exactes à commander, plutôt que de laisser imprimer
         * silencieusement un code illisible.
         */
        $formatRetenu = $formatRetenu ?? 'A0';
        [$papierL, $papierH] = $formats[$formatRetenu];
        $utile        = min($papierL, $papierH) - $reserve;
        $assezGrand   = $largeurImage <= $utile;

        // Le code n’est jamais agrandi au-delà du papier : s’il ne tient pas,
        // il est imprimé au maximum disponible et l’avertissement explique
        // quoi faire. Un dessin tronqué serait pire qu’un dessin trop petit.
        $largeurImprimee = min($largeurImage, $utile);

        // Deux compositions : le grand panneau, lu de loin, et l’affichette de
        // vitrine, lue à bout portant. Le modèle demandé est validé ici, afin
        // qu’une valeur inventée dans l’adresse retombe sur le modèle courant.
        $modele = (string) ($request->query['modele'] ?? 'panneau');
        if (!in_array($modele, ['panneau', 'vitrine'], true)) {
            $modele = 'panneau';
        }

        /*
         * L’affiche est rendue sans gabarit d’administration : c’est une
         * feuille destinée au papier. Le menu latéral, l’en-tête et le pied de
         * page n’y ont rien à faire, et les masquer en CSS laisserait des
         * éléments dans le document imprimé. Le gabarit public ne convient pas
         * davantage — son en-tête de site et son bandeau hors-ligne seraient
         * eux aussi imprimés. La vue produit donc son propre document, avec sa
         * règle @page dimensionnée sur le format retenu.
         */
        $html = View::rendre('admin/code-affiche', [
            'code'            => $contexte,
            'destination'     => CodeCourt::destination($contexte),
            'modele'          => $modele,
            'distance'        => $distance,
            'largeurSymbole'  => $largeurSymbole,
            'largeurImage'    => $largeurImage,
            'largeurImprimee' => $largeurImprimee,
            'qr'              => $qr['svg'],
            'format'          => $formatRetenu,
            'papierLargeur'   => $papierL,
            'papierHauteur'   => $papierH,
            'largeurUtile'    => round($utile, 1),
            'assezGrand'      => $assezGrand,
            'urlCourte'       => $urlCourte,
        ]);

        return Response::html($html)->avecEntete('Cache-Control', 'no-store, private');
    }

    /** GET /admin/codes/{id}/qr.svg — planche imprimable */
    public function codeQr(Request $request, array $params): Response
    {
        if ($refus = $this->garde('codes')) {
            return $refus;
        }

        $code = CodeCourt::trouver((int) ($params['id'] ?? 0));
        if ($code === null) {
            return Response::json(['erreur' => 'Code introuvable.'], 404);
        }

        $svg = QrService::svg(url_courte((string) $code['code']), 'H', 10, 4, '#4C1D95');

        return Response::html($svg)->avecEntete('Content-Type', 'image/svg+xml; charset=utf-8');
    }

    // =======================================================================
    // Notifications
    // =======================================================================

    /** GET /admin/notifications */
    public function notifications(Request $request): Response
    {
        if ($refus = $this->garde('notifications')) {
            return $refus;
        }

        return $this->vue('admin/notifications', [
            'titrePage' => 'Notifications',
            'section'   => 'notifications',
            'notifications' => Notification::tous(['statut' => $request->string('statut')]),
            'statut'    => $request->string('statut'),
            'plafond'   => PLAFOND_NOTIFICATIONS_MOIS,
            'pastilles' => $this->pastilles(),
            'actionsEntete' => '<a class="btn btn--primaire btn--petit" href="' . e(url('admin/notifications/nouveau')) . '">'
                . \Souriane\Core\Icone::svg('ajouter') . 'Rédiger</a>',
        ]);
    }

    /** GET /admin/notifications/nouveau | /admin/notifications/{id} */
    public function notificationFormulaire(Request $request, array $params): Response
    {
        if ($refus = $this->garde('notifications')) {
            return $refus;
        }

        $id           = isset($params['id']) ? (int) $params['id'] : 0;
        $notification = $id > 0 ? Notification::trouver($id) : null;

        if ($id > 0 && $notification === null) {
            return $this->introuvable('Notification introuvable.');
        }

        return $this->vue('admin/notification-form', [
            'titrePage' => $notification === null ? 'Nouvelle notification' : 'Notification « ' . $notification['titre'] . ' »',
            'section'   => 'notifications',
            'notification' => $notification,
            'annonceurs' => Annonceur::tous(['statut' => 'actif']),
            'envois'    => $notification !== null ? Notification::statistiquesEnvoi($id) : null,
            'journalEnvois' => $notification !== null ? Notification::envois($id) : [],
            'plafond'   => PLAFOND_NOTIFICATIONS_MOIS,
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** POST /admin/notifications/nouveau | /admin/notifications/{id} */
    public function notificationEnregistrer(Request $request, array $params): Response
    {
        if ($refus = $this->garde('notifications')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $id    = isset($params['id']) ? (int) $params['id'] : 0;
        $titre = $request->string('titre');
        $corps = $request->string('corps');

        $retour = $id > 0 ? url('admin/notifications/' . $id) : url('admin/notifications/nouveau');

        if ($titre === '' || $corps === '') {
            Flash::erreur('Le titre et le message sont obligatoires.');

            return Response::redirect($retour);
        }

        $annonceurId = $request->int('annonceur_id');
        if ($annonceurId <= 0) {
            Flash::erreur('Sélectionnez un annonceur.');

            return Response::redirect($retour);
        }

        $statut      = $request->string('statut');
        $statut      = in_array($statut, ['brouillon', 'programmee'], true) ? $statut : 'brouillon';
        $programmee  = $request->string('programmee_le');

        if ($statut === 'programmee' && $programmee === '') {
            $statut = 'brouillon';
        }

        // Plafond de fréquence : quatre envois par mois et par annonceur (ch. 5.5)
        if ($statut === 'programmee') {
            $deja = Abonne::envoisMoisCourant($annonceurId);
            if ($deja >= PLAFOND_NOTIFICATIONS_MOIS) {
                Flash::erreur(sprintf(
                    'Plafond atteint : %d notification(s) déjà envoyée(s) ce mois-ci pour cet annonceur (maximum %d).',
                    $deja,
                    PLAFOND_NOTIFICATIONS_MOIS
                ));

                return Response::redirect($retour);
            }
        }

        $donnees = [
            'annonceur_id'       => $annonceurId,
            'titre'              => mb_substr($titre, 0, 120),
            'corps'              => mb_substr($corps, 0, 400),
            'icone'              => $request->string('icone') ?: null,
            'adresse_ouverture'  => $request->string('adresse_ouverture') ?: null,
            'statut'             => $statut,
            'programmee_le'      => $statut === 'programmee'
                ? date('Y-m-d H:i:s', (int) strtotime($programmee))
                : null,
        ];

        if ($id > 0) {
            Notification::modifier($id, $donnees);
            journal_operation(Auth::id(), 'modification', 'notifications', $id, 'Notification mise à jour', $request->ip());
            Flash::succes('Notification enregistrée.');
        } else {
            $donnees['cree_par'] = Auth::id();
            $id = Notification::creer($donnees);
            journal_operation(Auth::id(), 'creation', 'notifications', $id, 'Rédaction de « ' . $titre . ' »', $request->ip());
            Flash::succes('Notification enregistrée.');
        }

        return Response::redirect(url('admin/notifications/' . $id));
    }

    /** POST /admin/notifications/{id}/envoyer */
    public function notificationEnvoyer(Request $request, array $params): Response
    {
        if ($refus = $this->garde('notifications')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $id = (int) ($params['id'] ?? 0);

        try {
            $resultat = (new DiffusionService())->envoyer($id);
        } catch (\Throwable $e) {
            Flash::erreur($e->getMessage());

            return Response::redirect(url('admin/notifications/' . $id));
        }

        journal_operation(
            Auth::id(),
            'envoi_notification',
            'notifications',
            $id,
            sprintf('%d envoi(s) — %d échec(s)', $resultat['envoyes'], $resultat['echecs']),
            $request->ip()
        );

        Flash::succes(sprintf(
            'Envoi terminé : %d notification(s) transmise(s), %d échec(s).',
            $resultat['envoyes'],
            $resultat['echecs']
        ));

        return Response::redirect(url('admin/notifications/' . $id));
    }

    /** POST /admin/notifications/{id}/annuler */
    public function notificationAnnuler(Request $request, array $params): Response
    {
        if ($refus = $this->garde('notifications')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $id = (int) ($params['id'] ?? 0);
        Notification::annuler($id);
        journal_operation(Auth::id(), 'annulation', 'notifications', $id, 'Notification annulée', $request->ip());
        Flash::info('Notification annulée.');

        return Response::redirect(url('admin/notifications/' . $id));
    }

    // =======================================================================
    // Exploitation
    // =======================================================================

    /** GET /admin/poses */
    public function poses(Request $request): Response
    {
        if ($refus = $this->garde('poses')) {
            return $refus;
        }

        $filtre = $request->string('controle');
        $estAnnonceur = Auth::role() === 'annonceur';
        $annonceurId  = $this->annonceurCourant();

        $sql = 'SELECT po.*, p.reference, p.libelle, p.ville, p.format, c.nom AS campagne_nom,
                       a.nom AS annonceur_nom, u.nom AS agent_nom
                FROM poses po
                JOIN panneaux p ON p.id = po.panneau_id
                JOIN campagnes c ON c.id = po.campagne_id
                JOIN annonceurs a ON a.id = c.annonceur_id
                LEFT JOIN utilisateurs u ON u.id = po.agent_id
                WHERE 1=1';
        $params = [];

        if ($estAnnonceur) {
            $sql .= ' AND c.annonceur_id = :mon_aid';
            $params['mon_aid'] = $annonceurId;
        }

        if (in_array($filtre, ['non_fait', 'conforme', 'non_conforme'], true)) {
            $sql .= ' AND (po.controle_15j = :c OR po.controle_30j = :c)';
            $params['c'] = $filtre;
        }

        $campagnesFiltre = $estAnnonceur
            ? Campagne::tous(['annonceur_id' => $annonceurId])
            : Db::instance()->select("SELECT * FROM campagnes WHERE statut IN ('en_cours', 'reservee') ORDER BY date_debut ASC");

        $panneauxCampagnes = Db::instance()->select(
            'SELECT cp.campagne_id, cp.panneau_id, p.reference, p.libelle, p.ville
             FROM campagne_panneaux cp
             JOIN panneaux p ON p.id = cp.panneau_id
             ORDER BY p.ville ASC, p.reference ASC'
        );

        return $this->vue('admin/poses', [
            'titrePage' => $estAnnonceur ? 'Preuves de pose de vos panneaux' : 'Preuves de pose',
            'section'   => 'poses',
            'poses'     => Db::instance()->select($sql . ' ORDER BY po.horodatage DESC LIMIT 200', $params),
            'controle'  => $filtre,
            'campagnes' => $campagnesFiltre,
            'panneauxCampagnes' => $panneauxCampagnes,
            'pastilles' => $this->pastilles(),
            'actionsEntete' => Auth::aRole('regie', 'agent')
                ? '<button type="button" class="btn btn--primaire btn--petit" data-ouvrir-dialogue="dialogue-pose">'
                    . \Souriane\Core\Icone::svg('ajouter') . 'Enregistrer une pose</button>'
                : '',
        ]);
    }

    /** POST /admin/poses */
    public function poseEnregistrer(Request $request): Response
    {
        if ($refus = $this->garde('poses')) {
            return $refus;
        }
        if (!Auth::aRole('regie', 'agent')) {
            return $this->refuse();
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $panneauId  = $request->int('panneau_id');
        $campagneId = $request->int('campagne_id');
        $photo      = $request->string('photo');
        $retour     = $request->string('retour');
        $cibleUrl   = $retour !== '' ? $retour : url('admin/poses');

        // Gestion du fichier photo téléversé
        $fichierPhoto = $_FILES['photo_fichier'] ?? null;
        if ($fichierPhoto !== null && !empty($fichierPhoto['name']) && ($fichierPhoto['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $photo = UploadService::enregistrerPhotoPose($fichierPhoto);
            } catch (\Exception $e) {
                Flash::erreur($e->getMessage());

                return Response::redirect($cibleUrl);
            }
        }

        if ($panneauId <= 0 || $campagneId <= 0 || $photo === '') {
            Flash::erreur('Panneau, campagne et photo de preuve sont obligatoires.');

            return Response::redirect($cibleUrl);
        }

        $horodatage = $request->string('horodatage');
        if ($horodatage === '' || strtotime($horodatage) === false) {
            $horodatage = date('Y-m-d H:i:s');
        }

        Db::instance()->insert('poses', [
            'panneau_id'   => $panneauId,
            'campagne_id'  => $campagneId,
            'photo'        => $photo,
            'latitude'     => $request->string('latitude') !== '' ? (float) $request->string('latitude') : null,
            'longitude'    => $request->string('longitude') !== '' ? (float) $request->string('longitude') : null,
            'horodatage'   => $horodatage,
            'agent_id'     => Auth::id(),
            'controle_15j' => 'non_fait',
            'controle_30j' => 'non_fait',
            'note'         => $request->string('note') ?: null,
        ]);

        journal_operation(Auth::id(), 'pose', 'poses', $panneauId, 'Preuve de pose enregistrée', $request->ip());
        Flash::succes('Preuve de pose enregistrée. L’annonceur peut maintenant visualiser la photo de son panneau.');

        return Response::redirect($cibleUrl);
    }


    /** GET /admin/journal */
    public function journal(Request $request): Response
    {
        if ($refus = $this->garde('journal')) {
            return $refus;
        }

        return $this->vue('admin/journal', [
            'titrePage' => 'Journal des opérations',
            'section'   => 'journal',
            'entrees'   => Journal::dernieres(150, ['action' => $request->string('action')]),
            'actions'   => Journal::actions(),
            'action'    => $request->string('action'),
            'pastilles' => $this->pastilles(),
        ]);
    }

    // =======================================================================
    // Système
    // =======================================================================

    /** GET /admin/utilisateurs */
    public function utilisateurs(Request $request): Response
    {
        if ($refus = $this->garde('utilisateurs')) {
            return $refus;
        }

        return $this->vue('admin/utilisateurs', [
            'titrePage' => 'Utilisateurs',
            'section'   => 'utilisateurs',
            'utilisateurs' => Utilisateur::tous(),
            'roles'     => Utilisateur::ROLES,
            'annonceurs' => Annonceur::tous(),
            'pastilles' => $this->pastilles(),
            'actionsEntete' => '<button type="button" class="btn btn--primaire btn--petit" data-ouvrir-dialogue="dialogue-utilisateur">'
                . \Souriane\Core\Icone::svg('ajouter') . 'Nouveau compte</button>',
        ]);
    }

    /** POST /admin/utilisateurs */
    public function utilisateurEnregistrer(Request $request): Response
    {
        if ($refus = $this->garde('utilisateurs')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $id    = $request->int('id');
        $nom   = $request->string('nom');
        $email = $request->string('email');
        $role  = $request->string('role');
        $motDePasse = (string) $request->input('mot_de_passe', '');

        if (!array_key_exists($role, Utilisateur::ROLES)) {
            $role = 'agent';
        }

        if ($nom === '' || $email === '') {
            Flash::erreur('Nom et adresse e-mail sont obligatoires.');

            return Response::redirect(url('admin/utilisateurs'));
        }

        if (Utilisateur::emailExiste($email, $id > 0 ? $id : null)) {
            Flash::erreur('Cette adresse e-mail est déjà utilisée.');

            return Response::redirect(url('admin/utilisateurs'));
        }

        $donnees = [
            'nom'          => $nom,
            'email'        => $email,
            'role'         => $role,
            'annonceur_id' => $role === 'annonceur' ? ($request->int('annonceur_id') ?: null) : null,
            'telephone'    => $request->string('telephone') ?: null,
            'actif'        => $request->bool('actif') ? 1 : 0,
        ];

        if ($motDePasse !== '') {
            if (mb_strlen($motDePasse) < 10) {
                Flash::erreur('Le mot de passe doit comporter au moins 10 caractères.');

                return Response::redirect(url('admin/utilisateurs'));
            }
            $donnees['mot_de_passe'] = password_hash($motDePasse, PASSWORD_ARGON2ID);
        }

        if ($id > 0) {
            Utilisateur::modifier($id, $donnees);
            journal_operation(Auth::id(), 'modification', 'utilisateurs', $id, 'Compte mis à jour', $request->ip());
            Flash::succes('Compte enregistré.');
        } else {
            if ($motDePasse === '') {
                Flash::erreur('Un mot de passe est nécessaire à la création du compte.');

                return Response::redirect(url('admin/utilisateurs'));
            }
            $id = Utilisateur::creer($donnees);
            journal_operation(Auth::id(), 'creation', 'utilisateurs', $id, 'Création du compte ' . $email, $request->ip());
            Flash::succes('Compte créé.');
        }

        return Response::redirect(url('admin/utilisateurs'));
    }

    /** GET /admin/parametres */
    public function parametres(Request $request): Response
    {
        if ($refus = $this->garde('parametres')) {
            return $refus;
        }

        return $this->vue('admin/parametres', [
            'titrePage' => 'Paramètres',
            'section'   => 'parametres',
            'lignes'    => Parametre::lignes(),
            'vapid'     => [
                'publique' => VAPID_PUBLIC_KEY,
                'privee'   => VAPID_PRIVATE_KEY !== '' ? '••••••••••••••••••••' : '',
                'sujet'    => VAPID_SUBJECT,
            ],
            'systeme'   => [
                'php'     => PHP_VERSION,
                'mysql'   => (string) Db::instance()->selectValue('SELECT VERSION()', [], '—'),
                'https'   => est_https(),
                'abonnes' => (int) Db::instance()->selectValue("SELECT COUNT(*) FROM abonnes WHERE statut = 'actif'", [], 0),
            ],
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** POST /admin/parametres */
    public function parametresEnregistrer(Request $request): Response
    {
        if ($refus = $this->garde('parametres')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $valeurs = $request->input('parametres', []);
        if (is_array($valeurs)) {
            foreach ($valeurs as $cle => $valeur) {
                if (!is_string($cle) || !is_scalar($valeur)) {
                    continue;
                }
                Parametre::ecrire($cle, (string) $valeur);
            }
        }

        journal_operation(Auth::id(), 'modification', 'parametres', null, 'Paramètres mis à jour', $request->ip());
        Flash::succes('Paramètres enregistrés.');

        return Response::redirect(url('admin/parametres'));
    }

    // =======================================================================
    // Sécurité du compte — second facteur (ch. 8)
    // =======================================================================

    /** GET /admin/securite */
    public function securite(Request $request): Response
    {
        if ($refus = $this->garde('securite')) {
            return $refus;
        }

        $utilisateur = Auth::utilisateur();

        return $this->vue('admin/securite', [
            'titrePage'     => 'Sécurité du compte',
            'section'       => 'securite',
            'courriel'      => (string) ($utilisateur['email'] ?? ''),
            'role'          => (string) ($utilisateur['role'] ?? ''),
            'actif'         => SecondFacteur::actif($utilisateur),
            'obligatoire'   => SecondFacteur::obligatoire((string) ($utilisateur['role'] ?? '')),
            'confirmeLe'    => $utilisateur['totp_confirme_le'] ?? null,
            'codesRestants' => SecondFacteur::restants($utilisateur),
            // Pris une seule fois : l’appel les retire de la session.
            'codesNeufs'    => Auth::prendreCodesSecours(),
            'pastilles'     => $this->pastilles(),
        ]);
    }

    /** POST /admin/securite/activer */
    public function securiteActiver(Request $request): Response
    {
        if ($refus = $this->garde('securite')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $utilisateur = Auth::utilisateur();
        if ($utilisateur === null) {
            return Response::redirect(url('admin/connexion'));
        }

        // Mettre en place un second facteur engage l’accès au compte : le mot
        // de passe est redemandé, pour qu’une session restée ouverte sur un
        // poste partagé ne suffise pas à s’approprier le compte.
        if (!$this->motDePasseConfirme($request, $utilisateur)) {
            return Response::redirect(url('admin/securite'));
        }

        if (SecondFacteur::actif($utilisateur)) {
            Flash::info('La vérification en deux étapes est déjà active.');

            return Response::redirect(url('admin/securite'));
        }

        Auth::demanderEnrolement((int) $utilisateur['id']);

        return Response::redirect(url('admin/connexion/enrolement'));
    }

    /** POST /admin/securite/desactiver */
    public function securiteDesactiver(Request $request): Response
    {
        if ($refus = $this->garde('securite')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $utilisateur = Auth::utilisateur();
        if ($utilisateur === null) {
            return Response::redirect(url('admin/connexion'));
        }

        if (!$this->motDePasseConfirme($request, $utilisateur)) {
            return Response::redirect(url('admin/securite'));
        }

        // Le contrôle est placé avant la vérification du code : inutile de
        // consommer un intervalle de trente secondes pour une action qui sera
        // refusée de toute façon.
        if (SecondFacteur::obligatoire((string) $utilisateur['role'])) {
            Flash::erreur(
                'Ce compte relève de l’administration : le chapitre 8 impose la vérification en '
                . 'deux étapes. Elle ne peut pas être retirée.'
            );

            return Response::redirect(url('admin/securite'));
        }

        if (!SecondFacteur::verifierCode((int) $utilisateur['id'], $utilisateur, (string) $request->input('code', ''))) {
            Flash::erreur('Code refusé : la vérification en deux étapes reste active.');

            return Response::redirect(url('admin/securite'));
        }

        SecondFacteur::desactiver((int) $utilisateur['id']);
        journal_operation((int) $utilisateur['id'], 'modification', 'utilisateurs', (int) $utilisateur['id'], 'Second facteur désactivé', $request->ip());
        Flash::succes('Vérification en deux étapes désactivée.');

        return Response::redirect(url('admin/securite'));
    }

    /** POST /admin/securite/codes */
    public function securiteRegenererCodes(Request $request): Response
    {
        if ($refus = $this->garde('securite')) {
            return $refus;
        }
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $utilisateur = Auth::utilisateur();
        if ($utilisateur === null) {
            return Response::redirect(url('admin/connexion'));
        }

        if (!$this->motDePasseConfirme($request, $utilisateur)) {
            return Response::redirect(url('admin/securite'));
        }

        if (!SecondFacteur::actif($utilisateur)) {
            Flash::erreur('Activez d’abord la vérification en deux étapes.');

            return Response::redirect(url('admin/securite'));
        }

        if (!SecondFacteur::verifierCode((int) $utilisateur['id'], $utilisateur, (string) $request->input('code', ''))) {
            Flash::erreur('Code refusé : les codes de secours n’ont pas été remplacés.');

            return Response::redirect(url('admin/securite'));
        }

        Auth::retenirCodesSecours(SecondFacteur::regenererCodes((int) $utilisateur['id']));
        journal_operation((int) $utilisateur['id'], 'modification', 'utilisateurs', (int) $utilisateur['id'], 'Codes de secours remplacés', $request->ip());
        Flash::succes('Nouveaux codes de secours. Les précédents ne fonctionnent plus.');

        return Response::redirect(url('admin/securite'));
    }

    /** Confirme le mot de passe du compte connecté, et le signale sinon. */
    private function motDePasseConfirme(Request $request, array $utilisateur): bool
    {
        $saisi = (string) $request->input('mot_de_passe', '');

        if ($saisi !== '' && password_verify($saisi, (string) $utilisateur['mot_de_passe'])) {
            return true;
        }

        journal_technique('securite', 'Mot de passe non confirmé sur une action de sécurité', [
            'email' => (string) ($utilisateur['email'] ?? ''),
        ]);
        Flash::erreur('Mot de passe incorrect.');

        return false;
    }

    // =======================================================================
    // Fabriques internes
    // =======================================================================

    /** Rend une vue d’administration. */
    private function vue(string $vue, array $donnees): Response
    {
        $donnees['compteurs'] = $donnees['compteurs'] ?? $donnees['pastilles'] ?? [];

        $html = View::rendre($vue, $donnees, 'admin');

        return Response::html($html)->avecEntete('Cache-Control', 'no-store, private');
    }

    /** Vérifie le rôle requis pour la rubrique. */
    private function garde(string $section): ?Response
    {
        // Un compte d’administration qui n’a pas encore mis en place son
        // second facteur est conduit là où il peut le faire, plutôt que
        // renvoyé vers un écran de connexion dont il vient de sortir.
        if (Auth::enrolementRequis()) {
            return Response::redirect(url('admin/connexion/enrolement'));
        }

        if (!Auth::verifie()) {
            return Response::redirect(url('admin/connexion?retour=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin')));
        }

        $autorises = self::ACCES[$section] ?? ['regie'];
        if (!Auth::aRole(...$autorises)) {
            return $this->refuse();
        }

        return null;
    }

    private function refuse(): Response
    {
        $html = View::rendre('admin/refus', ['titre' => 'Accès refusé — ' . APP_NAME], 'admin');

        return Response::html($html, 403);
    }

    private function introuvable(string $message): Response
    {
        Flash::erreur($message);

        return Response::redirect(url('admin'));
    }

    /** Identifiant de l’annonceur rattaché au compte connecté. */
    private function annonceurCourant(): int
    {
        $utilisateur = Auth::utilisateur();

        return (int) ($utilisateur['annonceur_id'] ?? 0);
    }

    /** Pastilles numériques du menu latéral. */
    private function pastilles(): array
    {
        $db = Db::instance();
        $annonceurId = Auth::role() === 'annonceur' ? $this->annonceurCourant() : 0;

        $campagnesSql = $annonceurId > 0
            ? "SELECT COUNT(*) FROM campagnes WHERE statut IN ('brouillon','reservee') AND annonceur_id = :aid"
            : "SELECT COUNT(*) FROM campagnes WHERE statut IN ('brouillon','reservee')";
        $campagnesParams = $annonceurId > 0 ? ['aid' => $annonceurId] : [];

        return [
            'campagnes'     => (int) $db->selectValue($campagnesSql, $campagnesParams, 0),
            'panneaux'      => (int) $db->selectValue("SELECT COUNT(*) FROM panneaux WHERE statut = 'maintenance'", [], 0),
            'notifications' => (int) $db->selectValue("SELECT COUNT(*) FROM notifications WHERE statut = 'programmee'", [], 0),
            'poses'         => (int) $db->selectValue("SELECT COUNT(*) FROM poses WHERE controle_15j = 'non_fait'", [], 0),
            'pages'         => (int) $db->selectValue("SELECT COUNT(*) FROM pages WHERE statut = 'brouillon'", [], 0),
            'demandes'      => Demande::compterNouvelles(),
        ];
    }


    /** Horaires saisis dans le formulaire annonceur. */
    private function lireHoraires(Request $request): array
    {
        $horaires = [];
        foreach (['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'] as $jour) {
            $valeur = $request->string('horaire_' . $jour);
            $horaires[$jour] = $valeur !== '' ? $valeur : 'fermé';
        }

        return $horaires;
    }

    /** Preuves de pose d’un panneau. */
    private function posesDuPanneau(int $panneauId): array
    {
        return Db::instance()->select(
            'SELECT po.*, c.nom AS campagne_nom, u.nom AS agent_nom
             FROM poses po
             JOIN campagnes c ON c.id = po.campagne_id
             LEFT JOIN utilisateurs u ON u.id = po.agent_id
             WHERE po.panneau_id = :id ORDER BY po.horodatage DESC',
            ['id' => $panneauId]
        );
    }

    // =======================================================================
    // Demandes de proposition (prospects)
    // =======================================================================

    /** GET /admin/demandes — Liste des demandes reçues. */
    public function demandes(Request $request): Response
    {
        if ($refus = $this->garde('demandes')) {
            return $refus;
        }

        $statut = $request->string('statut');
        $filtres = in_array($statut, Demande::STATUTS, true) ? ['statut' => $statut] : [];

        return $this->vue('admin/demandes', [
            'titrePage' => 'Demandes de proposition',
            'section'   => 'demandes',
            'demandes'  => Demande::tous($filtres),
            'totaux'    => Demande::compterParStatut(),
            'filtre'    => $statut,
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** GET /admin/demandes/{id} — Détail d’une demande. */
    public function demandeDetail(Request $request, array $params): Response
    {
        if ($refus = $this->garde('demandes')) {
            return $refus;
        }

        $id = (int) ($params['id'] ?? 0);
        $demande = Demande::trouver($id);
        if ($demande === null) {
            return $this->introuvable('Demande introuvable.');
        }

        return $this->vue('admin/demande-detail', [
            'titrePage' => 'Demande — ' . $demande['enseigne'],
            'section'   => 'demandes',
            'demande'   => $demande,
            'formats'   => json_lire($demande['formats'] ?? null),
            'statuts'   => Demande::STATUTS,
            'pastilles' => $this->pastilles(),
        ]);
    }

    /** POST /admin/demandes/{id} — Changement de statut. */
    public function demandeStatut(Request $request, array $params): Response
    {
        if ($refus = $this->garde('demandes')) {
            return $refus;
        }
        if ($reponse = Csrf::verifier($request)) {
            return $reponse;
        }

        $id     = (int) ($params['id'] ?? 0);
        $statut = $request->string('statut');

        if (!in_array($statut, Demande::STATUTS, true) || Demande::trouver($id) === null) {
            return $this->introuvable('Demande introuvable ou statut invalide.');
        }

        Demande::changerStatut($id, $statut);
        Flash::succes('Le statut de la demande a été mis à jour.');

        return Response::redirect(url('admin/demandes/' . $id));
    }
}
