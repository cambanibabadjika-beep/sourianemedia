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

/** Menu de l’administration. */
$menu = [
    [
        'titre' => 'Pilotage',
        'liens' => [
            ['cle' => 'tableau-de-bord', 'libelle' => 'Tableau de bord', 'url' => url('admin'), 'icone' => 'graphique'],
            ['cle' => 'statistiques',    'libelle' => 'Statistiques',    'url' => url('admin/statistiques'), 'icone' => 'coche-cercle'],
        ],
    ],
    [
        'titre' => 'Commercial',
        'liens' => [
            ['cle' => 'annonceurs', 'libelle' => 'Annonceurs', 'url' => url('admin/annonceurs'), 'icone' => 'utilisateurs'],
            ['cle' => 'demandes',   'libelle' => 'Demandes',   'url' => url('admin/demandes'),   'icone' => 'courriel'],
            ['cle' => 'campagnes',  'libelle' => 'Campagnes',  'url' => url('admin/campagnes'),  'icone' => 'etincelle'],
            ['cle' => 'panneaux',   'libelle' => 'Panneaux',   'url' => url('admin/panneaux'),   'icone' => 'panneau'],
        ],
    ],
    [
        'titre' => 'Diffusion',
        'liens' => [
            ['cle' => 'pages',         'libelle' => 'Pages publiques', 'url' => url('admin/pages'), 'icone' => 'site'],
            ['cle' => 'codes',         'libelle' => 'QR codes',        'url' => url('admin/codes'), 'icone' => 'qr'],
            ['cle' => 'notifications', 'libelle' => 'Notifications',   'url' => url('admin/notifications'), 'icone' => 'cloche'],
        ],
    ],
    [
        'titre' => 'Exploitation',
        'liens' => [
            ['cle' => 'poses',   'libelle' => 'Preuves de pose', 'url' => url('admin/poses'),   'icone' => 'photo'],
            ['cle' => 'journal', 'libelle' => 'Journal',         'url' => url('admin/journal'), 'icone' => 'archive'],
        ],
    ],
    [
        'titre' => 'Système',
        'liens' => [
            ['cle' => 'utilisateurs', 'libelle' => 'Utilisateurs', 'url' => url('admin/utilisateurs'), 'icone' => 'utilisateurs'],
            ['cle' => 'parametres',   'libelle' => 'Paramètres',   'url' => url('admin/parametres'),   'icone' => 'reglages'],
            ['cle' => 'securite',     'libelle' => 'Sécurité du compte', 'url' => url('admin/securite'), 'icone' => 'verrou'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#2E1065">
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

        <nav class="admin-nav" aria-label="Navigation de l’administration">
            <?php foreach ($menu as $groupe): ?>
                <div class="admin-nav__groupe">
                    <div class="admin-nav__titre"><?= e($groupe['titre']) ?></div>
                    <?php foreach ($groupe['liens'] as $lien): ?>
                        <?php $actif = $lien['cle'] === $section; ?>
                        <a href="<?= e($lien['url']) ?>"<?= $actif ? ' class="actif" aria-current="page"' : '' ?>>
                            <?= Icone::svg($lien['icone']) ?>
                            <span><?= e($lien['libelle']) ?></span>
                            <?php if (!empty($compteurs[$lien['cle']])): ?>
                                <span class="admin-nav__badge"><?= e((string) $compteurs[$lien['cle']]) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="admin-laterale__pied">
            <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?><br>
            <a href="<?= e(url('/')) ?>" style="color:inherit">Voir le site public</a>
        </div>
    </aside>

    <div class="admin-principal">
        <header class="admin-entete">
            <div class="pile">
                <button type="button" class="btn btn--contour btn--petit bouton-menu" id="bouton-menu" aria-label="Ouvrir le menu" aria-controls="admin-laterale">
                    <?= Icone::svg('menu') ?>
                </button>
                <h1 class="admin-entete__titre"><?= e($titrePage) ?></h1>
            </div>

            <div class="admin-entete__actions">
                <?= $actionsEntete ?>
                <?php if ($utilisateur !== null): ?>
                    <div class="admin-entete__utilisateur">
                        <span class="avatar" aria-hidden="true"><?= e($initiales !== '' ? $initiales : 'SA') ?></span>
                        <span>
                            <strong style="display:block;line-height:1.2"><?= e((string) $utilisateur['nom']) ?></strong>
                            <span class="texte-petit"><?= e(\Souriane\Models\Utilisateur::ROLES[$utilisateur['role']] ?? $utilisateur['role']) ?></span>
                        </span>
                        <form method="post" action="<?= e(url('admin/deconnexion')) ?>" style="margin:0">
                            <?= \Souriane\Core\Csrf::champ() ?>
                            <button type="submit" class="btn btn--contour btn--petit" title="Se déconnecter">
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
})();
</script>
<?= $scripts ?>
</body>
</html>
