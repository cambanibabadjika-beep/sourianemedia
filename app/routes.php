<?php
/**
 * SOURIANE — Table de routage
 *
 * L’ordre de déclaration détermine la priorité. Le motif générique /{adresse}
 * (page publique d’un annonceur) est volontairement déclaré en dernier.
 *
 * Le routeur et la requête courante sont fournis par index.php
 * sous les noms $routeur et $requete.
 *
 * @var \Souriane\Core\Router  $routeur
 * @var \Souriane\Core\Request $requete
 */

declare(strict_types=1);

use Souriane\Controllers\AdminController;
use Souriane\Controllers\ApiController;
use Souriane\Controllers\AuthController;
use Souriane\Controllers\CronController;
use Souriane\Controllers\PublicController;

$public = new PublicController();
$api    = new ApiController();
$auth   = new AuthController();
$admin  = new AdminController();
$cron   = new CronController();

// ===========================================================================
// 1. Site public
// ===========================================================================
$routeur->get('/', [$public, 'accueil']);
$routeur->get('/c/{code}', [$public, 'resoudreCode']);
$routeur->get('/comment-ca-marche', [$public, 'commentCaMarche']);
$routeur->get('/annonceurs', [$public, 'annonceurs']);
$routeur->get('/annonceurs/demande', [$public, 'demande']);
$routeur->post('/annonceurs/demande', [$public, 'demandeEnregistrer']);
$routeur->get('/le-parc', [$public, 'parc']);
$routeur->get('/mentions-legales', [$public, 'mentionsLegales']);
$routeur->get('/confidentialite', [$public, 'confidentialite']);
$routeur->get('/accessibilite', [$public, 'accessibilite']);

// ===========================================================================
// 2. API JSON (ch. 6.4)
// ===========================================================================
$routeur->get('/api/sante', [$api, 'sante']);
$routeur->post('/api/evenements', [$api, 'evenements']);
$routeur->post('/api/abonnements', [$api, 'abonnement']);
$routeur->post('/api/abonnements/suppression', [$api, 'desabonnement']);
$routeur->get('/api/campagnes/{id}/statistiques', [$api, 'statistiquesCampagne']);
$routeur->get('/api/annonceurs/{id}/notifications', [$api, 'notificationsAnnonceur']);

// ===========================================================================
// 3. Tâche planifiée (ch. 5.5)
//    Déclencheur HTTP protégé par CRON_TOKEN, pour les hébergements sans
//    crontab. En ligne de commande, utiliser cron/envoyer_notifications.php.
// ===========================================================================
$routeur->get('/tache/notifications', [$cron, 'notifications']);
$routeur->post('/tache/notifications', [$cron, 'notifications']);

// ===========================================================================
// 4. Authentification du back-office
// ===========================================================================
$routeur->get('/admin/connexion', [$auth, 'formulaire']);
$routeur->post('/admin/connexion', [$auth, 'connecter']);

// Second facteur (ch. 8) : code à usage unique, puis enrôlement.
// Déclarés après la connexion, dont ils prolongent le parcours.
$routeur->get('/admin/connexion/code', [$auth, 'formulaireCode']);
$routeur->post('/admin/connexion/code', [$auth, 'verifierCode']);
$routeur->get('/admin/connexion/enrolement', [$auth, 'formulaireEnrolement']);
$routeur->post('/admin/connexion/enrolement', [$auth, 'activerEnrolement']);

$routeur->post('/admin/deconnexion', [$auth, 'deconnecter']);

// ===========================================================================
// 5. Back-office
// ===========================================================================
$routeur->get('/admin', [$admin, 'tableauDeBord']);
$routeur->get('/admin/statistiques', [$admin, 'statistiques']);

// --- Annonceurs
$routeur->get('/admin/annonceurs', [$admin, 'annonceurs']);
$routeur->get('/admin/annonceurs/nouveau', [$admin, 'annonceurFormulaire']);
$routeur->post('/admin/annonceurs/nouveau', [$admin, 'annonceurEnregistrer']);
$routeur->get('/admin/annonceurs/{id}', [$admin, 'annonceurFormulaire']);
$routeur->post('/admin/annonceurs/{id}', [$admin, 'annonceurEnregistrer']);

// --- Demandes de proposition (prospects)
$routeur->get('/admin/demandes', [$admin, 'demandes']);
$routeur->get('/admin/demandes/{id}', [$admin, 'demandeDetail']);
$routeur->post('/admin/demandes/{id}', [$admin, 'demandeStatut']);

// --- Campagnes
$routeur->get('/admin/campagnes', [$admin, 'campagnes']);
$routeur->get('/admin/campagnes/nouveau', [$admin, 'campagneFormulaire']);
$routeur->post('/admin/campagnes/nouveau', [$admin, 'campagneEnregistrer']);
$routeur->post('/admin/campagnes/{id}/reserver', [$admin, 'campagneReserver']);
$routeur->post('/admin/campagnes/{id}/retirer', [$admin, 'campagneRetirerPanneau']);
// Rapport de fin de campagne : page imprimable et export tableur (EF-F06)
$routeur->get('/admin/campagnes/{id}/rapport', [$admin, 'campagneRapport']);
$routeur->get('/admin/campagnes/{id}/rapport.csv', [$admin, 'campagneRapportCsv']);
$routeur->get('/admin/campagnes/{id}', [$admin, 'campagneFormulaire']);
$routeur->post('/admin/campagnes/{id}', [$admin, 'campagneEnregistrer']);

// --- Panneaux
$routeur->get('/admin/panneaux', [$admin, 'panneaux']);
$routeur->get('/admin/panneaux/nouveau', [$admin, 'panneauFormulaire']);
$routeur->post('/admin/panneaux/nouveau', [$admin, 'panneauEnregistrer']);
$routeur->get('/admin/panneaux/{id}', [$admin, 'panneauFormulaire']);
$routeur->post('/admin/panneaux/{id}', [$admin, 'panneauEnregistrer']);

// --- Pages publiques
$routeur->get('/admin/pages', [$admin, 'pages']);
$routeur->get('/admin/pages/{id}', [$admin, 'pageFormulaire']);
$routeur->post('/admin/pages/{id}', [$admin, 'pageEnregistrer']);

// --- QR codes
$routeur->get('/admin/codes', [$admin, 'codes']);
$routeur->post('/admin/codes/generer', [$admin, 'codesGenerer']);
$routeur->post('/admin/codes/{id}/destination', [$admin, 'codeDestination']);
$routeur->get('/admin/codes/{id}/qr.svg', [$admin, 'codeQr']);
$routeur->get('/admin/codes/{id}/affiche', [$admin, 'codeAffiche']);

// --- Notifications
$routeur->get('/admin/notifications', [$admin, 'notifications']);
$routeur->get('/admin/notifications/nouveau', [$admin, 'notificationFormulaire']);
$routeur->post('/admin/notifications/nouveau', [$admin, 'notificationEnregistrer']);
$routeur->post('/admin/notifications/{id}/envoyer', [$admin, 'notificationEnvoyer']);
$routeur->post('/admin/notifications/{id}/annuler', [$admin, 'notificationAnnuler']);
$routeur->get('/admin/notifications/{id}', [$admin, 'notificationFormulaire']);
$routeur->post('/admin/notifications/{id}', [$admin, 'notificationEnregistrer']);

// --- Exploitation
$routeur->get('/admin/poses', [$admin, 'poses']);
$routeur->post('/admin/poses', [$admin, 'poseEnregistrer']);
$routeur->get('/admin/journal', [$admin, 'journal']);

// --- Système
$routeur->get('/admin/utilisateurs', [$admin, 'utilisateurs']);
$routeur->post('/admin/utilisateurs', [$admin, 'utilisateurEnregistrer']);
$routeur->get('/admin/parametres', [$admin, 'parametres']);
$routeur->post('/admin/parametres', [$admin, 'parametresEnregistrer']);

// --- Sécurité du compte (second facteur, ch. 8)
$routeur->get('/admin/securite', [$admin, 'securite']);
$routeur->post('/admin/securite/activer', [$admin, 'securiteActiver']);
$routeur->post('/admin/securite/desactiver', [$admin, 'securiteDesactiver']);
$routeur->post('/admin/securite/codes', [$admin, 'securiteRegenererCodes']);

// ===========================================================================
// 6. Page publique d’un annonceur — motif le plus générique, en dernier
// ===========================================================================
$routeur->get('/{adresse}', [$public, 'page']);

// ===========================================================================
// 7. Repli
// ===========================================================================
$routeur->nonTrouvee([$public, 'nonTrouvee']);
