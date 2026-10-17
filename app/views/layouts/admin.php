<?php
/**
 * Gabarit — Back-office (régie et agents terrain).
 *
 * Variables facultatives :
 *   $titre        string — titre de l’onglet
 *   $titrePage    string — titre affiché dans la barre supérieure
 *   $section      string — clé de la rubrique active (met en surbrillance le menu)
 *   $actionsEntete string — boutons affichés à droite de la barre supérieure
 *   $compteurs    array  — pastilles numériques du menu
 *   $scripts      string
 *   $contenu      string
 */

declare(strict_types=1);

use Souriane\Core\Auth;
use Souriane\Core\Icone;

$titre         = $titre ?? ('Administration — ' . APP_NAME);
$titrePage     = $titrePage ?? 'Tableau de bord';
$section       = $section ?? '';
$actionsEntete = $actionsEntete ?? '';
$compteurs     = $compteurs ?? [];
$scripts       = $scripts ?? '';
$contenu       = $contenu ?? '';

$utilisateur = Auth::utilisateur();
$initiales   = '';
if ($utilisateur !== null) {
    $mots = preg_split('/\s+/', trim((string) $utilisateur['nom'])) ?: [];
    foreach (array_slice($mots, 0, 2) as $mot) {
        $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
    }
}

$role        = (string) ($utilisateur['role'] ?? 'regie');
$roleLibelle = \Souriane\Models\Utilisateur::ROLES[$role] ?? $role;

// Informations annonceur rattaché (si profil annonceur)
$pageAnnonceurSlug = null;
$annonceurNom      = null;
if ($role === 'annonceur' && !empty($utilisateur['annonceur_id'])) {
    $annId = (int) $utilisateur['annonceur_id'];
    $pageAssociee = \Souriane\Models\Page::parAnnonceur($annId);
    if ($pageAssociee !== null && !empty($pageAssociee['adresse_courte'])) {
        $pageAnnonceurSlug = (string) $pageAssociee['adresse_courte'];
    }
    $ann = \Souriane\Models\Annonceur::trouver($annId);
    if ($ann !== null) {
        $annonceurNom = (string) ($ann['nom'] ?? '');
        if ($pageAnnonceurSlug === null && !empty($ann['adresse_courte'])) {
            $pageAnnonceurSlug = (string) $ann['adresse_courte'];
        }
    }
}

/**
 * Définition complète du menu de l’administration avec les rôles autorisés.
 *
 * Rôles :
 *   regie     → Régie publicitaire (accès complet)
 *   agent     → Agent terrain (parc, poses, QR codes, lecture des campagnes)
 *   annonceur → Annonceur (tableau de bord, statistiques, ses campagnes, sa page publique)
 */
$menuComplet = [
    [
        'titre' => 'Pilotage',
        'liens' => [
            ['cle' => 'tableau-de-bord', 'libelle' => 'Tableau de bord', 'url' => url('admin'), 'icone' => 'graphique', 'roles' => ['regie', 'agent', 'annonceur']],
            ['cle' => 'statistiques',    'libelle' => 'Statistiques',    'url' => url('admin/statistiques'), 'icone' => 'coche-cercle', 'roles' => ['regie', 'agent', 'annonceur']],
        ],
    ],
    [
        'titre' => $role === 'annonceur' ? 'Campagnes' : 'Commercial',
        'liens' => [
            ['cle' => 'annonceurs', 'libelle' => 'Annonceurs', 'url' => url('admin/annonceurs'), 'icone' => 'utilisateurs', 'roles' => ['regie']],
            ['cle' => 'demandes',   'libelle' => 'Demandes',   'url' => url('admin/demandes'),   'icone' => 'courriel', 'roles' => ['regie']],
            ['cle' => 'campagnes',  'libelle' => $role === 'annonceur' ? 'Mes campagnes' : 'Campagnes', 'url' => url('admin/campagnes'), 'icone' => 'etincelle', 'roles' => ['regie', 'agent', 'annonceur']],
            ['cle' => 'panneaux',   'libelle' => 'Panneaux',   'url' => url('admin/panneaux'),   'icone' => 'panneau', 'roles' => ['regie', 'agent']],
        ],
    ],
    [
        'titre' => 'Diffusion',
        'liens' => [
            ['cle' => 'pages',         'libelle' => 'Pages publiques', 'url' => url('admin/pages'), 'icone' => 'site', 'roles' => ['regie']],
            ['cle' => 'codes',         'libelle' => 'QR codes',        'url' => url('admin/codes'), 'icone' => 'qr', 'roles' => ['regie', 'agent']],
            ['cle' => 'notifications', 'libelle' => 'Notifications',   'url' => url('admin/notifications'), 'icone' => 'cloche', 'roles' => ['regie']],
        ],
    ],
    [
        'titre' => $role === 'annonceur' ? 'Terrain' : 'Exploitation',
        'liens' => [
            ['cle' => 'poses',   'libelle' => 'Preuves de pose', 'url' => url('admin/poses'),   'icone' => 'photo', 'roles' => ['regie', 'agent', 'annonceur']],
            ['cle' => 'journal', 'libelle' => 'Journal',         'url' => url('admin/journal'), 'icone' => 'archive', 'roles' => ['regie']],
        ],
    ],
    [
        'titre' => $role === 'annonceur' ? 'Compte' : 'Système',
        'liens' => [
            ['cle' => 'utilisateurs', 'libelle' => 'Utilisateurs', 'url' => url('admin/utilisateurs'), 'icone' => 'utilisateurs', 'roles' => ['regie']],
            ['cle' => 'parametres',   'libelle' => 'Paramètres',   'url' => url('admin/parametres'),   'icone' => 'reglages', 'roles' => ['regie']],
            ['cle' => 'securite',     'libelle' => 'Sécurité du compte', 'url' => url('admin/securite'), 'icone' => 'verrou', 'roles' => ['regie', 'agent', 'annonceur']],
        ],
    ],
];

// Si l'annonceur possède une page publique, on insère un accès direct
if ($role === 'annonceur' && $pageAnnonceurSlug !== null) {
    $menuComplet[2]['liens'][] = [
        'cle'     => 'ma-page',
        'libelle' => 'Ma page publique',
        'url'     => url_page($pageAnnonceurSlug),
        'icone'   => 'site',
        'externe' => true,
        'roles'   => ['annonceur'],
    ];
}

/** Filtrage du menu selon le rôle de l'utilisateur connecté */
$menu = [];
foreach ($menuComplet as $groupe) {
    $liensFiltres = [];
    foreach ($groupe['liens'] as $lien) {
        $rolesAutorises = $lien['roles'] ?? ['regie'];
        if (in_array($role, $rolesAutorises, true)) {
            $liensFiltres[] = $lien;
        }
    }
    if ($liensFiltres !== []) {
        $groupe['liens'] = $liensFiltres;
        $menu[] = $groupe;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#2E1065">
<?php require __DIR__ . '/../partials/theme-init.php'; ?>
<link rel="icon" href="<?= e(asset('img/favicon.png')) ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">

<div class="admin-conteneur">

    <aside class="admin-laterale" id="admin-laterale">
        <div class="admin-laterale__marque">
            <?php
            $variante    = '';
            $taille      = '';
            $lien        = true;
            $destination = url('admin');
            require __DIR__ . '/../partials/logo.php';
            ?>
        </div>

        <?php if ($utilisateur !== null): ?>
            <div class="admin-laterale__profil">
                <span class="admin-laterale__badge-role admin-laterale__badge-role--<?= e($role) ?>">
                    <?= e($roleLibelle) ?>
                </span>
                <?php if ($role === 'annonceur' && !empty($annonceurNom)): ?>
                    <div class="admin-laterale__entite" title="<?= e($annonceurNom) ?>">
                        <?= e($annonceurNom) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <nav class="admin-nav" aria-label="Navigation de l’administration">
            <?php foreach ($menu as $groupe): ?>
                <div class="admin-nav__groupe">
                    <div class="admin-nav__titre"><?= e($groupe['titre']) ?></div>
                    <?php foreach ($groupe['liens'] as $lien): ?>
                        <?php
                        $actif   = $lien['cle'] === $section;
                        $externe = !empty($lien['externe']);
                        ?>
                        <a href="<?= e($lien['url']) ?>"<?= $actif ? ' class="actif" aria-current="page"' : '' ?><?= $externe ? ' target="_blank" rel="noopener"' : '' ?>>
                            <?= Icone::svg($lien['icone']) ?>
                            <span><?= e($lien['libelle']) ?></span>
                            <?php if ($externe): ?>
                                <span class="admin-nav__externe" title="Ouvrir dans un nouvel onglet">
                                    <?= Icone::svg('lien-externe') ?>
                                </span>
                            <?php elseif (!empty($compteurs[$lien['cle']])): ?>
                                <span class="admin-nav__badge"><?= e((string) $compteurs[$lien['cle']]) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>


        <div class="admin-laterale__pied">
            <div style="margin-bottom:8px">
                <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?><br>
                <a href="<?= e(url('/')) ?>" style="color:inherit">Voir le site public</a>
            </div>
            <?php if ($utilisateur !== null): ?>
                <button type="button" class="btn-deconnexion-laterale" id="bouton-deconnexion-laterale" aria-haspopup="dialog" aria-controls="dialogue-deconnexion">
                    <?= Icone::svg('deconnexion') ?>
                    <span>Se déconnecter</span>
                </button>
            <?php endif; ?>
        </div>
    </aside>

    <div class="admin-principal">
        <header class="admin-entete">
            <div class="admin-entete__gauche">
                <button type="button" class="btn btn--contour btn--petit bouton-menu" id="bouton-menu" aria-label="Ouvrir le menu" aria-controls="admin-laterale">
                    <?= Icone::svg('menu') ?>
                </button>
                <h1 class="admin-entete__titre" title="<?= e($titrePage) ?>"><?= e($titrePage) ?></h1>
            </div>

            <div class="admin-entete__actions">
                <?php if ($actionsEntete !== ''): ?>
                    <div class="admin-entete__action-specifique">
                        <?= $actionsEntete ?>
                    </div>
                <?php endif; ?>
                <?php require __DIR__ . '/../partials/bascule-theme.php'; ?>
                <?php if ($utilisateur !== null): ?>
                    <div class="admin-entete__utilisateur">
                        <span class="avatar" aria-hidden="true" title="<?= e((string) $utilisateur['nom']) ?> (<?= e(\Souriane\Models\Utilisateur::ROLES[$utilisateur['role']] ?? $utilisateur['role']) ?>)"><?= e($initiales !== '' ? $initiales : 'SA') ?></span>
                        <span class="admin-entete__utilisateur-texte">
                            <strong style="display:block;line-height:1.2"><?= e((string) $utilisateur['nom']) ?></strong>
                            <span class="texte-petit"><?= e(\Souriane\Models\Utilisateur::ROLES[$utilisateur['role']] ?? $utilisateur['role']) ?></span>
                        </span>
                        <form method="post" action="<?= e(url('admin/deconnexion')) ?>" style="margin:0" id="formulaire-deconnexion-entete">
                            <?= \Souriane\Core\Csrf::champ() ?>
                            <button type="submit" class="btn btn--contour btn--petit" title="Se déconnecter" id="bouton-deconnexion-entete" aria-haspopup="dialog" aria-controls="dialogue-deconnexion">
                                <?= Icone::svg('deconnexion') ?>
                                <span class="sr-only">Se déconnecter</span>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <div class="admin-contenu">
            <?php $messages = \Souriane\Core\Flash::recuperer(); ?>
            <?php if ($messages !== []): ?>
                <?php require __DIR__ . '/../partials/messages.php'; ?>
            <?php endif; ?>

            <?= $contenu ?>
        </div>
    </div>
</div>

<?php if ($utilisateur !== null): ?>
<!-- Dialogue de confirmation de déconnexion --------------------------------- -->
<dialog id="dialogue-deconnexion" class="dialogue-deconnexion" aria-labelledby="titre-dialogue-deconnexion" aria-describedby="desc-dialogue-deconnexion">
    <div class="dialogue-deconnexion__carte">
        <div class="dialogue-deconnexion__entete">
            <div class="dialogue-deconnexion__badge-icone" aria-hidden="true">
                <?= Icone::svg('deconnexion') ?>
            </div>
            <button type="button" class="dialogue-deconnexion__fermer" id="bouton-fermer-dialogue-deconnexion" title="Fermer" aria-label="Fermer la boîte de dialogue">
                <?= Icone::svg('fermer') ?>
            </button>
        </div>

        <div class="dialogue-deconnexion__corps">
            <h2 id="titre-dialogue-deconnexion" class="dialogue-deconnexion__titre">Confirmer la déconnexion</h2>
            <p id="desc-dialogue-deconnexion" class="dialogue-deconnexion__message">
                Êtes-vous sûr de vouloir vous déconnecter de votre session d’administration ?
            </p>

            <div class="dialogue-deconnexion__utilisateur">
                <span class="dialogue-deconnexion__avatar" aria-hidden="true"><?= e($initiales !== '' ? $initiales : 'SA') ?></span>
                <div class="dialogue-deconnexion__utilisateur-texte">
                    <strong class="dialogue-deconnexion__utilisateur-nom"><?= e((string) $utilisateur['nom']) ?></strong>
                    <span class="dialogue-deconnexion__utilisateur-role">
                        <?= e(\Souriane\Models\Utilisateur::ROLES[$utilisateur['role']] ?? $utilisateur['role']) ?>
                        <?php if (!empty($utilisateur['email'])): ?>
                            <span class="dialogue-deconnexion__point-separateur">·</span> <span class="dialogue-deconnexion__utilisateur-email"><?= e((string) $utilisateur['email']) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <div class="dialogue-deconnexion__rappel">
                <?= Icone::svg('info') ?>
                <span>Pour des raisons de sécurité, fermez votre navigateur si vous utilisez un appareil partagé.</span>
            </div>
        </div>

        <div class="dialogue-deconnexion__actions">
            <button type="button" class="btn btn--contour" id="bouton-annuler-deconnexion">
                Annuler
            </button>
            <form method="post" action="<?= e(url('admin/deconnexion')) ?>" style="margin:0" id="formulaire-deconnexion-confirme">
                <?= \Souriane\Core\Csrf::champ() ?>
                <button type="submit" class="btn btn--danger" id="bouton-confirmer-deconnexion">
                    <?= Icone::svg('deconnexion') ?>
                    <span>Se déconnecter</span>
                </button>
            </form>
        </div>
    </div>
</dialog>
<?php endif; ?>

<script>
(function () {
    var bouton  = document.getElementById('bouton-menu');
    var lat     = document.getElementById('admin-laterale');
    if (bouton && lat) {
        bouton.addEventListener('click', function () {
            lat.classList.toggle('admin-laterale--ouverte');
        });
        document.addEventListener('click', function (e) {
            if (window.innerWidth > 1000) { return; }
            if (!lat.contains(e.target) && !bouton.contains(e.target)) {
                lat.classList.remove('admin-laterale--ouverte');
            }
        });
    }

    /* Fenêtre de confirmation de déconnexion */
    var dialogue = document.getElementById('dialogue-deconnexion');
    if (dialogue) {
        var formDeclencheur = document.getElementById('formulaire-deconnexion-entete');
        var btnLaterale     = document.getElementById('bouton-deconnexion-laterale');
        var btnAnnuler      = document.getElementById('bouton-annuler-deconnexion');
        var btnFermerCroix  = document.getElementById('bouton-fermer-dialogue-deconnexion');
        var formConfirme    = document.getElementById('formulaire-deconnexion-confirme');
        var btnConfirmer    = document.getElementById('bouton-confirmer-deconnexion');

        function ouvrirDialogue(e) {
            if (e) { e.preventDefault(); }
            if (typeof dialogue.showModal === 'function') {
                dialogue.showModal();
            } else {
                dialogue.setAttribute('open', '');
            }
            if (btnAnnuler) { btnAnnuler.focus(); }
        }

        function fermerDialogue() {
            if (typeof dialogue.close === 'function') {
                dialogue.close();
            } else {
                dialogue.removeAttribute('open');
            }
        }

        if (formDeclencheur) {
            formDeclencheur.addEventListener('submit', ouvrirDialogue);
        }
        if (btnLaterale) {
            btnLaterale.addEventListener('click', ouvrirDialogue);
        }
        if (btnAnnuler) {
            btnAnnuler.addEventListener('click', fermerDialogue);
        }
        if (btnFermerCroix) {
            btnFermerCroix.addEventListener('click', fermerDialogue);
        }

        /* Fermeture en cliquant sur l'arrière-plan */
        dialogue.addEventListener('click', function (e) {
            var rect = dialogue.getBoundingClientRect();
            var enDehors = (
                e.clientX < rect.left ||
                e.clientX > rect.right ||
                e.clientY < rect.top ||
                e.clientY > rect.bottom
            );
            if (enDehors) {
                fermerDialogue();
            }
        });

        /* Évite les doubles soumissions lors de la validation */
        if (formConfirme && btnConfirmer) {
            formConfirme.addEventListener('submit', function () {
                btnConfirmer.disabled = true;
                btnConfirmer.style.opacity = '0.7';
                btnConfirmer.style.cursor = 'wait';
            });
        }
    }
})();
</script>
<?= $scripts ?>
</body>
</html>
