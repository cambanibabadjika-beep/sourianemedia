<?php
/**
 * SOURIANE — Contrôleur du site public
 *
 * Vitrine, résolution des codes courts, page annonceur enrichie et pages de
 * contenu. Aucune donnée personnelle n’est conservée en clair (ch. 8).
 */

declare(strict_types=1);

namespace Souriane\Controllers;

use Souriane\Core\Csrf;
use Souriane\Core\Flash;
use Souriane\Core\Request;
use Souriane\Core\Response;
use Souriane\Core\View;
use Souriane\Models\Annonceur;
use Souriane\Models\Campagne;
use Souriane\Models\CodeCourt;
use Souriane\Models\Demande;
use Souriane\Models\Evenement;
use Souriane\Models\Page;
use Souriane\Models\Panneau;
use Souriane\Models\Visite;
use Souriane\Services\QrService;

final class PublicController
{
    private const CLE_SESSION = '_visite';

    // -----------------------------------------------------------------------
    // Vitrine
    // -----------------------------------------------------------------------

    public function accueil(Request $request): Response
    {
        $codes     = CodeCourt::tous();
        $codeDemo  = (string) ($codes[0]['code'] ?? 'b7k2');

        $qr = QrService::svg(url_courte($codeDemo), 'Q', 6, 4, '#4C1D95');

        $html = View::rendre('public/vitrine', [
            'titre'       => APP_NAME . ' — ' . APP_SLOGAN,
            'description' => 'Souriane relie chaque panneau d’affichage à son audience : un QR code, une page enrichie, des statistiques mesurées.',
            'canonique'   => url('/'),
            'qrDemo'      => $qr,
            'codeDemo'    => $codeDemo,
            'chiffres'    => [
                'panneaux'   => Panneau::compter(),
                'villes'     => count(Panneau::villes()),
                'annonceurs' => Annonceur::compter(),
                'campagnes'  => array_sum(Campagne::compterParStatut()),
            ],
            'villesListe'   => Panneau::villes(),
            'configuration' => $this->configuration(),
        ], 'public');

        return Response::html($html)->avecCache(300);
    }

    // -----------------------------------------------------------------------
    // Résolution d’un code court — l’adresse encodée dans le QR (EF-C02)
    // -----------------------------------------------------------------------

    public function resoudreCode(Request $request, array $params): Response
    {
        $codeCourt = CodeCourt::resoudre((string) ($params['code'] ?? ''));

        if ($codeCourt === null) {
            return $this->erreur(
                404,
                'Code inconnu',
                'Ce code n’est pas reconnu. Vérifiez le code de secours imprimé sous le QR code, ou contactez la régie.'
            );
        }

        // Campagne annulée : le code reste lisible mais ne renvoie plus rien
        if (($codeCourt['campagne_statut'] ?? '') === 'annulee') {
            return $this->erreur(
                410,
                'Campagne terminée',
                'Cette campagne est terminée. Le code ne mène plus à aucune page.'
            );
        }

        $session = $this->sessionVisite();
        $source  = $request->query['s'] ?? 'qr';
        $source  = in_array($source, ['qr', 'nfc', 'direct', 'lien'], true) ? $source : 'qr';

        $pageId      = isset($codeCourt['page_id']) ? (int) $codeCourt['page_id'] : null;
        $annonceurId = (int) $codeCourt['annonceur_id'];

        // Journalisation du scan (ch. 5.7) — jamais l’adresse réseau en clair
        Evenement::enregistrer([
            'type'             => $source === 'nfc' ? 'nfc' : 'scan',
            'code_court_id'    => (int) $codeCourt['id'],
            'page_id'          => $pageId,
            'annonceur_id'     => $annonceurId,
            'campagne_id'      => (int) $codeCourt['campagne_id'],
            'panneau_id'       => (int) $codeCourt['panneau_id'],
            'session_id'       => $session,
            'empreinte_reseau' => empreinte_reseau($request->ip()),
            'agent'            => $request->agent(),
            'referent'         => $request->referent(),
            'meta'             => ['source' => $source],
        ]);

        Visite::ouvrir(
            $session,
            $pageId,
            $annonceurId,
            (int) $codeCourt['id'],
            $this->appareil($request),
            $source
        );

        $destination = CodeCourt::destination($codeCourt);
        $separateur  = str_contains($destination, '?') ? '&' : '?';

        return Response::redirect($destination . $separateur . 'c=' . rawurlencode((string) $codeCourt['code']));
    }

    // -----------------------------------------------------------------------
    // Page publique de l’annonceur (ch. 5.2)
    // -----------------------------------------------------------------------

    public function page(Request $request, array $params): Response
    {
        $adresse = (string) ($params['adresse'] ?? '');
        $page    = Page::parSlug($adresse);

        if ($page === null || ($page['annonceur_statut'] ?? '') !== 'actif') {
            return $this->erreur(
                404,
                'Page introuvable',
                'Cette page n’existe pas ou n’est plus publiée. Scannez à nouveau le QR code du panneau.'
            );
        }

        if (($page['statut'] ?? '') !== 'publiee') {
            return $this->erreur(
                503,
                'Page en préparation',
                'Cette page n’est pas encore ouverte au public. Revenez dans quelques jours.'
            );
        }

        // Code court à l’origine de la visite (attribution des statistiques)
        $codeCourt = null;
        $codeBrut  = (string) ($request->query['c'] ?? '');
        if ($codeBrut !== '') {
            $trouve = CodeCourt::resoudre($codeBrut);
            if ($trouve !== null && (int) $trouve['annonceur_id'] === (int) $page['annonceur_id']) {
                $codeCourt = $trouve;
            }
        }

        $sections = Page::sections((int) $page['id']);
        $session  = $this->sessionVisite();

        // Un seul affichage compté par session et par page
        $vues = $_SESSION['_vues'] ?? [];
        if (!isset($vues[(int) $page['id']])) {
            $vues[(int) $page['id']] = time();
            $_SESSION['_vues'] = $vues;

            Evenement::enregistrer([
                'type'             => 'affichage',
                'code_court_id'    => $codeCourt !== null ? (int) $codeCourt['id'] : null,
                'page_id'          => (int) $page['id'],
                'annonceur_id'     => (int) $page['annonceur_id'],
                'campagne_id'      => $codeCourt !== null ? (int) $codeCourt['campagne_id'] : null,
                'panneau_id'       => $codeCourt !== null ? (int) $codeCourt['panneau_id'] : null,
                'session_id'       => $session,
                'empreinte_reseau' => empreinte_reseau($request->ip()),
                'agent'            => $request->agent(),
                'referent'         => $request->referent(),
            ]);
        }

        Visite::ouvrir(
            $session,
            (int) $page['id'],
            (int) $page['annonceur_id'],
            $codeCourt !== null ? (int) $codeCourt['id'] : null,
            $this->appareil($request),
            $codeCourt !== null ? 'qr' : 'direct'
        );

        $titre = (string) ($page['titre'] ?: $page['nom']);

        $html = View::rendre('public/page-annonceur', [
            'titre'       => $titre . ' — ' . APP_NAME,
            'description' => extrait((string) ($page['bandeau_offre'] ?: $page['description']), 155),
            'canonique'   => url_page((string) $page['adresse_courte']),
            'page'        => $page,
            'sections'    => $sections,
            'code'        => $codeCourt,
            'classeCorps' => 'page-annonceur-corps',
            'entete'      => false,
            'pied'        => false,
            'donneesCorps' => [
                'page'      => (int) $page['id'],
                'annonceur' => (int) $page['annonceur_id'],
                'campagne'  => $codeCourt !== null ? (int) $codeCourt['campagne_id'] : null,
                'panneau'   => $codeCourt !== null ? (int) $codeCourt['panneau_id'] : null,
                'code'      => $codeCourt !== null ? (string) $codeCourt['code'] : null,
                'code-id'   => $codeCourt !== null ? (int) $codeCourt['id'] : null,
                'nom'       => (string) $page['nom'],
            ],
            'configuration' => $this->configuration([
                'page'      => (int) $page['id'],
                'annonceur' => (int) $page['annonceur_id'],
                'campagne'  => $codeCourt !== null ? (int) $codeCourt['campagne_id'] : null,
                'panneau'   => $codeCourt !== null ? (int) $codeCourt['panneau_id'] : null,
                'codeId'    => $codeCourt !== null ? (int) $codeCourt['id'] : null,
                'abonne'    => $this->abonneConnu((int) $page['annonceur_id']),
            ]),
        ], 'public');

        // Le contenu d’une page annonceur change peu : cache court, strictement privé
        // (la page porte un jeton CSRF propre à la session du visiteur)
        return Response::html($html)->avecEntete('Cache-Control', 'private, max-age=60, must-revalidate');
    }

    // -----------------------------------------------------------------------
    // Pages de contenu
    // -----------------------------------------------------------------------

    /**
     * GET /le-parc — parc de panneaux, filtrable (EF-A05).
     *
     * Le filtrage est assuré par le serveur et non par le navigateur. Trois
     * raisons, dans l’ordre d’importance :
     *   — l’adresse obtenue est partageable telle quelle, ce qui compte pour
     *     une vitrine commerciale : un prospect peut envoyer le lien d’une
     *     sélection ;
     *   — la page fonctionne sans JavaScript, y compris le compteur ;
     *   — le décompte annoncé est celui de ce qui est affiché, sans dépendre
     *     de l’exécution d’un script.
     *
     * Les valeurs reçues sont confrontées à celles réellement présentes dans
     * le parc : une valeur inventée dans l’adresse est ignorée plutôt que de
     * produire une page vide sans explication.
     */
    public function parc(Request $request): Response
    {
        $villes  = Panneau::villes();
        $formats = Panneau::formats();
        $statuts = ['disponible', 'reserve', 'occupe', 'maintenance', 'archive'];

        $ville      = $this->choixFiltre($request->query['ville'] ?? null, $villes);
        $format     = $this->choixFiltre($request->query['format'] ?? null, $formats);
        $statut     = $this->choixFiltre($request->query['statut'] ?? null, $statuts);
        $disponible = ($request->query['disponible'] ?? '') === '1';

        $filtres = [
            'ville'  => $ville,
            'format' => $format,
            'statut' => $statut,
            // Un support retiré du parc n’a pas sa place dans une vitrine :
            // il n’apparaît que si le visiteur le demande explicitement.
            'exclure_archive' => $statut !== 'archive',
        ];
        if ($disponible) {
            $filtres['disponible_seulement'] = true;
        }

        $html = View::rendre('public/parc', [
            'titre'         => 'Le parc de panneaux — ' . APP_NAME,
            'description'   => 'Références, emplacements, formats, trafic estimé et disponibilité des supports d’affichage Souriane.',
            'canonique'     => url('le-parc'),
            'panneaux'      => Panneau::tous($filtres),
            'villes'        => Panneau::parVille(),
            'villesParc'    => $villes,
            'formatsParc'   => $formats,
            'statutsParc'   => $statuts,
            'filtres'       => [
                'ville'      => $ville,
                'format'     => $format,
                'statut'     => $statut,
                'disponible' => $disponible,
            ],
            'configuration' => $this->configuration(),
            'entetesExtra'  => '<link rel="stylesheet" href="' . e(asset('lib/leaflet/leaflet.css')) . '">',
            'scripts'       => '<script src="' . e(asset('lib/leaflet/leaflet.js')) . '" defer></script>'
                . '<script src="' . e(asset('js/parc-carte.js')) . '" defer></script>',
        ], 'public');

        return Response::html($html)->avecCache(300);
    }

    /**
     * Retient une valeur de filtre si elle figure parmi les valeurs possibles.
     *
     * Une valeur absente, vide ou inconnue est ignorée : le filtre est alors
     * simplement inactif, et le parc s’affiche en entier.
     */
    private function choixFiltre(mixed $valeur, array $possibles): string
    {
        $valeur = trim((string) $valeur);

        return in_array($valeur, array_map('strval', $possibles), true) ? $valeur : '';
    }

    public function commentCaMarche(Request $request): Response
    {
        return $this->contenu(
            'Comment ça marche',
            'Trois temps',
            'Du panneau au téléphone : ce qui se passe concrètement, et ce que chaque partie y gagne.',
            [
                [
                    'titre' => '1 · Sur le panneau',
                    'paragraphes' => [
                        'Chaque visuel porte un QR code unique, associé à un couple campagne × panneau. Un même visuel posé sur deux supports comporte donc deux codes distincts : c’est ce qui permet d’attribuer les scans au bon emplacement.',
                        'Le code encode une adresse courte, jamais l’adresse directe de la page. Cette adresse reste modifiable : on peut changer la destination d’un panneau sans réimprimer l’affiche.',
                    ],
                    'liste' => [
                        'Niveau de correction d’erreur élevé : le code reste lisible même partiellement abîmé.',
                        'Taille d’impression calculée selon la distance de lecture, avec une marge de silence autour du motif.',
                        'Un code de secours lisible est imprimé sous le QR, pour les téléphones qui ne parviennent pas à le lire.',
                    ],
                ],
                [
                    'titre' => '2 · Sur le téléphone du passant',
                    'paragraphes' => [
                        'La page s’ouvre en moins de trois secondes. Le visiteur voit d’abord l’offre, puis le nom de l’annonceur, puis deux boutons : appeler et écrire sur WhatsApp. Le reste — informations pratiques, photos, adresse, horaires, réseaux sociaux — vient ensuite.',
                        'Aucune application n’est nécessaire, aucun compte n’est demandé, aucune donnée personnelle n’est collectée pour consulter la page.',
                    ],
                ],
                [
                    'titre' => '3 · Après la visite',
                    'paragraphes' => [
                        'Le visiteur qui le souhaite peut installer la page sur son écran d’accueil et accepter de recevoir les prochaines offres. Sur iPhone, cette possibilité est soumise à l’ajout préalable de la page à l’écran d’accueil.',
                        'Pour les appareils qui ne prennent pas en charge les notifications, un canal WhatsApp de repli est proposé. Un annonceur ne peut pas envoyer plus de quatre notifications par mois.',
                    ],
                ],
            ]
        );
    }

    public function annonceurs(Request $request): Response
    {
        return $this->contenu(
            'Devenir annonceur',
            'Pour les annonceurs',
            'Réserver un support, mesurer ce qu’il produit, et garder le contact avec l’audience après l’affichage.',
            [
                [
                    'titre' => 'Choisir ses supports',
                    'paragraphes' => [
                        'Le parc est consultable en ligne : référence, emplacement, format, nombre de faces, trafic estimé et statut. La disponibilité se lit sur un calendrier, mois par mois, avant toute réservation.',
                        'Le prix est calculé automatiquement à partir de la formule choisie, de la durée d’affichage et du nombre de panneaux retenus, avec les remises par palier appliquées.',
                    ],
                ],
                [
                    'titre' => 'Pendant la campagne',
                    'paragraphes' => [
                        'Chaque pose fait l’objet d’une preuve photographique horodatée et géolocalisée. Un contrôle de conformité est programmé à quinze jours puis à trente jours.',
                        'Les interactions sont comptées en continu : scans, visiteurs uniques, appels, messages WhatsApp, demandes d’itinéraire, installations et abonnements.',
                    ],
                ],
                [
                    'titre' => 'Après la campagne',
                    'paragraphes' => [
                        'Le bilan distingue clairement trois natures de données : ce qui est mesuré, ce qui est estimé à partir du trafic déclaré, et ce qui est déclaré par la régie. Aucune estimation n’est présentée comme une mesure.',
                        'Les visiteurs abonnés reçoivent les annonces suivantes, dans la limite de quatre par mois et par annonceur.',
                    ],
                ],
                [
                    'titre' => 'Engagements',
                    'liste' => [
                        'Les adresses réseau ne sont jamais conservées en clair : seule une empreinte non réversible est enregistrée.',
                        'Le décompte des visiteurs uniques repose sur un identifiant de session anonyme, réinitialisé à chaque nouvelle visite.',
                        'Toute personne peut demander la suppression de son abonnement, à tout moment et sans justification.',
                    ],
                ],
            ],
            'annonceurs'
        );
    }

    /**
     * GET /annonceurs/demande — Formulaire public de demande de proposition.
     */
    public function demande(Request $request): Response
    {
        $html = View::rendre('public/demande', [
            'titre'         => 'Demande de proposition — ' . APP_NAME,
            'description'   => 'Décrivez votre projet d’affichage extérieur : la régie vous recontacte avec une proposition.',
            'canonique'     => url('annonceurs/demande'),
            'titrePage'     => 'Demande de proposition',
            'villes'        => Panneau::villes(),
            'formats'       => Panneau::formats(),
            'valeurs'       => [],
            'erreurs'       => [],
            'configuration' => $this->configuration(),
        ], 'public');

        return Response::html($html)->avecEntete('Cache-Control', 'private, no-cache, must-revalidate');
    }

    /**
     * POST /annonceurs/demande — Enregistrement de la demande (CSRF + validation).
     */
    public function demandeEnregistrer(Request $request): Response
    {
        if ($reponse = Csrf::verifier($request)) {
            return $reponse;
        }

        $valeurs = [
            'enseigne'  => $request->string('enseigne'),
            'contact'   => $request->string('contact'),
            'email'     => $request->string('email'),
            'telephone' => $request->string('telephone'),
            'ville'     => $request->string('ville'),
            'periode'   => $request->string('periode'),
            'budget'    => $request->string('budget'),
            'message'   => $request->string('message'),
        ];

        $formatsBruts = $request->input('formats', []);
        $formatsAutorises = Panneau::formats();
        $valeurs['formats'] = is_array($formatsBruts)
            ? array_values(array_intersect($formatsBruts, $formatsAutorises))
            : [];

        $erreurs = [];
        if ($valeurs['enseigne'] === '') {
            $erreurs['enseigne'] = 'Indiquez le nom de votre enseigne ou entreprise.';
        }
        if ($valeurs['contact'] === '') {
            $erreurs['contact'] = 'Indiquez la personne à contacter.';
        }
        if ($valeurs['email'] === '') {
            $erreurs['email'] = 'Une adresse e-mail est nécessaire pour vous répondre.';
        } elseif (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Cette adresse e-mail n’est pas valide.';
        }
        if ($valeurs['ville'] === '' || !in_array($valeurs['ville'], Panneau::villes(), true)) {
            $erreurs['ville'] = 'Choisissez une ville couverte par le parc.';
        }

        if ($erreurs !== []) {
            $html = View::rendre('public/demande', [
                'titre'         => 'Demande de proposition — ' . APP_NAME,
                'description'   => 'Décrivez votre projet d’affichage extérieur : la régie vous recontacte avec une proposition.',
                'canonique'     => url('annonceurs/demande'),
                'titrePage'     => 'Demande de proposition',
                'villes'        => Panneau::villes(),
                'formats'       => Panneau::formats(),
                'valeurs'       => $valeurs,
                'erreurs'       => $erreurs,
                'configuration' => $this->configuration(),
            ], 'public');

            return Response::html($html, 422)->avecEntete('Cache-Control', 'private, no-cache, must-revalidate');
        }

        Demande::creer([
            'enseigne'  => $valeurs['enseigne'],
            'contact'   => $valeurs['contact'],
            'email'     => $valeurs['email'],
            'telephone' => $valeurs['telephone'] !== '' ? $valeurs['telephone'] : null,
            'ville'     => $valeurs['ville'],
            'formats'   => $valeurs['formats'],
            'periode'   => $valeurs['periode'] !== '' ? $valeurs['periode'] : null,
            'budget'    => $valeurs['budget'] !== '' ? $valeurs['budget'] : null,
            'message'   => $valeurs['message'] !== '' ? $valeurs['message'] : null,
        ]);

        Flash::succes('Votre demande a bien été envoyée. La régie vous recontacte très vite.');

        return Response::redirect(url('annonceurs/demande'));
    }

    public function mentionsLegales(Request $request): Response
    {
        return $this->contenu(
            'Mentions légales',
            'Informations légales',
            'Éditeur, hébergement et conditions d’utilisation de la plateforme.',
            [
                [
                    'titre' => 'Éditeur de la plateforme',
                    'paragraphes' => [
                        'La plateforme ' . APP_NAME . ' est éditée par la régie d’affichage extérieur ' . APP_NAME . ', responsable du parc de panneaux et des pages publiques qui y sont associées.',
                        'Contact : ' . (WHATSAPP_REGIE !== '' ? WHATSAPP_REGIE : 'voir le formulaire de contact de la régie') . '.',
                    ],
                ],
                [
                    'titre' => 'Contenus publiés',
                    'paragraphes' => [
                        'Les pages publiques présentent des offres commerciales d’annonceurs. La régie met à disposition l’outil de publication mais ne saurait être tenue responsable du contenu des offres, de leur exactitude ou de leur disponibilité en magasin.',
                        'Toute offre publiée mentionne sa période de validité et ses éventuelles restrictions. En cas de contradiction entre la page et l’affichage physique, la mention portée sur le panneau prévaut.',
                    ],
                ],
                [
                    'titre' => 'Propriété intellectuelle',
                    'paragraphes' => [
                        'La marque, les éléments graphiques et le code de la plateforme sont protégés. Les logos et visuels des annonceurs restent leur propriété et sont publiés avec leur autorisation.',
                    ],
                ],
            ]
        );
    }

    public function confidentialite(Request $request): Response
    {
        return $this->contenu(
            'Confidentialité des données',
            'Vos données',
            'Ce que la plateforme mesure, ce qu’elle ne collecte pas, et comment exercer vos droits.',
            [
                [
                    'titre' => 'Ce qui est mesuré',
                    'liste' => [
                        'Le type d’interaction : scan, affichage de page, clic sur le bouton d’appel, clic WhatsApp, demande d’itinéraire, installation, abonnement.',
                        'Un identifiant de session anonyme, généré aléatoirement et conservé uniquement le temps nécessaire au décompte des visiteurs uniques.',
                        'Le modèle d’appareil et le navigateur, sous forme abrégée, pour connaître la répartition des visites.',
                        'Une empreinte non réversible de l’adresse réseau, utilisée pour repérer les comptages anormaux. L’adresse elle-même n’est jamais enregistrée.',
                    ],
                ],
                [
                    'titre' => 'Ce qui n’est pas collecté',
                    'liste' => [
                        'Aucun nom, aucune adresse postale, aucun numéro de téléphone n’est demandé pour consulter une page.',
                        'Aucun traceur publicitaire tiers n’est installé. Aucun profil de navigation n’est constitué.',
                        'Aucune géolocalisation n’est enregistrée à votre insu : le bouton d’itinéraire ouvre simplement votre application de cartes.',
                    ],
                ],
                [
                    'titre' => 'Notifications',
                    'paragraphes' => [
                        'L’abonnement aux notifications est un acte volontaire. Il repose sur un identifiant technique fourni par votre navigateur, associé à l’annonceur que vous avez choisi de suivre.',
                        'Chaque annonceur est limité à quatre notifications par mois. La désinscription se fait depuis la page de l’annonceur, en un clic, sans justification à fournir.',
                    ],
                ],
                [
                    'titre' => 'Vos droits',
                    'paragraphes' => [
                        'Vous pouvez demander la suppression de votre abonnement et des données techniques associées. La demande est traitée sans délai et sans condition.',
                        'Les données individuelles de session sont purgées au-delà de la durée de conservation définie par la régie.',
                    ],
                ],
            ],
            'confidentialite'
        );
    }

    public function accessibilite(Request $request): Response
    {
        return $this->contenu(
            'Accessibilité',
            'Accessibilité numérique',
            'Ce que nous mettons en œuvre pour que les pages restent utilisables par tous.',
            [
                [
                    'titre' => 'Principes appliqués',
                    'liste' => [
                        'Contrastes de couleurs vérifiés entre le texte et son fond.',
                        'Navigation entière au clavier, avec un lien d’évitement vers le contenu principal.',
                        'Textes redimensionnables sans perte de lisibilité ni défilement horizontal.',
                        'Images décoratives masquées aux lecteurs d’écran, informations essentielles toujours doublées d’un texte.',
                        'Respect de la préférence système de réduction des animations.',
                    ],
                ],
                [
                    'titre' => 'Signaler une difficulté',
                    'paragraphes' => [
                        'Si vous rencontrez un obstacle sur une page, signalez-le à la régie : la correction est apportée en priorité lorsqu’elle empêche l’accès à une offre.',
                    ],
                ],
            ]
        );
    }

    public function nonTrouvee(Request $request): Response
    {
        return $this->erreur(
            404,
            'Page introuvable',
            'L’adresse demandée n’existe pas. Si vous venez de scanner un panneau, vérifiez le code de secours imprimé sous le QR code.'
        );
    }

    // -----------------------------------------------------------------------
    // Fabriques internes
    // -----------------------------------------------------------------------

    /** Rend une page de contenu statique. */
    private function contenu(string $titre, string $surtitre, string $intro, array $blocs, string $chemin = ''): Response
    {
        $html = View::rendre('public/statique', [
            'titre'         => $titre . ' — ' . APP_NAME,
            'description'   => $intro,
            'canonique'     => url($chemin !== '' ? $chemin : slugify($titre)),
            'titrePage'     => $titre,
            'surtitre'      => $surtitre,
            'intro'         => $intro,
            'blocs'         => $blocs,
            'configuration' => $this->configuration(),
        ], 'public');

        return Response::html($html)->avecCache(3600);
    }

    /** Rend une page d’erreur avec le bon code HTTP. */
    private function erreur(int $code, string $titre, string $message): Response
    {
        $html = View::rendre('public/erreur', [
            'titre'         => $titre . ' — ' . APP_NAME,
            'description'   => $message,
            'codeErreur'    => $code,
            'titreErreur'   => $titre,
            'messageErreur' => $message,
            'configuration' => $this->configuration(),
        ], 'public');

        return Response::html($html, $code);
    }

    /** Identifiant de session anonyme du visiteur (ch. 8). */
    public static function sessionVisite(): string
    {
        if (empty($_SESSION[self::CLE_SESSION])) {
            $_SESSION[self::CLE_SESSION] = uuid4();
        }

        return (string) $_SESSION[self::CLE_SESSION];
    }

    /** Indique si le navigateur a déjà un abonnement actif pour cet annonceur. */
    private function abonneConnu(int $annonceurId): bool
    {
        $abonnes = $_SESSION['_abonnements'] ?? [];

        return !empty($abonnes[$annonceurId]);
    }

    /** Configuration transmise au JavaScript de la page. */
    private function configuration(array $extra = []): array
    {
        return array_merge([
            'base'    => BASE_URL,
            'api'     => url('api'),
            'vapid'   => VAPID_PUBLIC_KEY,
            'csrf'    => Csrf::jeton(),
            'session' => self::sessionVisite(),
            'https'   => est_https(),
            'ios'     => est_ios(),
            'sw'      => url('service-worker.js'),
        ], $extra);
    }

    /** Résumé lisible de l’appareil du visiteur. */
    private function appareil(Request $request): string
    {
        $agent = $request->agent();

        $systeme = 'Inconnu';
        foreach ([
            'Android'    => '/Android\s?([0-9.]+)?/i',
            'iOS'        => '/(?:iPhone|iPad).*?OS\s([0-9_]+)/i',
            'Windows'    => '/Windows NT\s?([0-9.]+)?/i',
            'macOS'      => '/Mac OS X\s?([0-9_.]+)?/i',
            'Linux'      => '/Linux/i',
        ] as $nom => $motif) {
            if (preg_match($motif, $agent, $m)) {
                $version = isset($m[1]) ? str_replace('_', '.', $m[1]) : '';
                $systeme = trim($nom . ($version !== '' ? ' ' . $version : ''));
                break;
            }
        }

        $navigateur = 'Navigateur';
        foreach ([
            'Edge'    => '/Edg\//i',
            'Samsung' => '/SamsungBrowser/i',
            'Opera'   => '/OPR\/|Opera/i',
            'Chrome'  => '/Chrome\//i',
            'Firefox' => '/Firefox\//i',
            'Safari'  => '/Safari\//i',
        ] as $nom => $motif) {
            if (preg_match($motif, $agent)) {
                $navigateur = $nom;
                break;
            }
        }

        return mb_substr($systeme . ' · ' . $navigateur, 0, 120);
    }
}
