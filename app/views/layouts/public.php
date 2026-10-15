<?php
/**
 * Gabarit — Site public.
 *
 * Variables facultatives :
 *   $titre         string  — titre de l’onglet
 *   $description   string  — méta description
 *   $canonique     string  — URL canonique
 *   $classeCorps   string  — classes additionnelles du <body>
 *   $entete        bool    — afficher l’en-tête du site (défaut : true)
 *   $pied          bool    — afficher le pied de page (défaut : true)
 *   $donneesCorps  array   — attributs data-* posés sur le <body>
 *   $configuration array   — configuration exposée au JavaScript
 *   $entetesExtra  string  — balises supplémentaires dans <head>
 *   $scripts       string  — scripts propres à la page
 *   $contenu       string  — corps de la page (injecté par le moteur de vues)
 */

declare(strict_types=1);

$titre         = $titre ?? APP_NAME;
$description   = $description ?? (APP_NAME . ' — ' . APP_SLOGAN . '. Chaque panneau porte un QR code qui ouvre la page de l’annonceur, puis permet d’installer l’application et de recevoir ses offres.');
$canonique     = $canonique ?? null;
$classeCorps   = $classeCorps ?? '';
$entete        = $entete ?? true;
$pied          = $pied ?? true;
$donneesCorps  = $donneesCorps ?? [];
$configuration = $configuration ?? [];
$entetesExtra  = $entetesExtra ?? '';
$scripts       = $scripts ?? '';

$attributsCorps = '';
foreach ($donneesCorps as $cle => $valeur) {
    if ($valeur === null || $valeur === '') {
        continue;
    }
    $attributsCorps .= ' data-' . e((string) $cle) . '="' . e((string) $valeur) . '"';
}

$configJs = json_encode($configuration, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?: '{}';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titre) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if ($canonique !== null): ?>
<link rel="canonical" href="<?= e($canonique) ?>">
<?php endif; ?>

<meta name="theme-color" content="#4C1D95">
<meta name="color-scheme" content="light">
<meta name="format-detection" content="telephone=no">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(APP_NAME) ?>">
<meta property="og:title" content="<?= e($titre) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<?php if ($canonique !== null): ?>
<meta property="og:url" content="<?= e($canonique) ?>">
<?php endif; ?>

<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.png')) ?>" type="image/png" sizes="any">
<link rel="apple-touch-icon" href="<?= e(asset('img/icons/apple-touch-icon.png')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(APP_NAME) ?>">
<meta name="mobile-web-app-capable" content="yes">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap"></noscript>

<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?= $entetesExtra ?>
<script>
/* Les éléments à révéler au défilement ne sont masqués que si le JavaScript
   peut les révéler. Le drapeau est posé ici, avant le premier rendu, afin
   d’éviter un clignotement (contenu visible, puis masqué, puis révélé).
   Un garde-fou le retire si app.js ne s’initialise pas — réseau coupé,
   script bloqué par une extension, erreur de syntaxe. */
(function () {
    var racine = document.documentElement;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    racine.setAttribute('data-anim', 'actif');
    window.setTimeout(function () {
        if (racine.getAttribute('data-anim-pret') !== '1') {
            racine.removeAttribute('data-anim');
        }
    }, 3000);
})();
</script>
<script>window.SOURIANE = <?= $configJs ?>;</script>
</head>
<body class="<?= e(trim($classeCorps)) ?>"<?= $attributsCorps ?>>

<div class="bandeau-hors-ligne" id="bandeau-hors-ligne" role="status" aria-live="polite">
    <?= \Souriane\Core\Icone::svg('hors-ligne') ?>
    <span>Vous êtes hors connexion — le contenu affiché provient du cache.</span>
</div>

<a class="lien-evitement" href="#contenu-principal">Aller au contenu principal</a>

<?php if ($entete): ?>
    <?php require __DIR__ . '/../partials/entete-site.php'; ?>
<?php endif; ?>

<main id="contenu-principal">
    <?= $contenu ?>
</main>

<?php if ($pied): ?>
    <?php require __DIR__ . '/../partials/pied-site.php'; ?>
<?php endif; ?>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?= $scripts ?>
</body>
</html>
