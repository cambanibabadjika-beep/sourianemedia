<?php
/**
 * Partial — En-tête des écrans d’accès au back-office.
 *
 * Les trois écrans du parcours de connexion — mot de passe, code à deux
 * facteurs, enrôlement — partagent la même carte. L’en-tête est donc écrit
 * une fois ici plutôt que recopié trois fois, où il finirait par diverger.
 *
 * Variables :
 *   $titre string — titre de l’onglet
 *
 * La vue referme la carte avec partials/pied-connexion.php.
 */

declare(strict_types=1);

$titre = $titre ?? ('Connexion — ' . APP_NAME);
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
<body>

<div class="theme-flotant"><?php require __DIR__ . '/bascule-theme.php'; ?></div>

<div class="connexion-page">
    <div class="connexion-carte">
        <div class="connexion-carte__marque">
            <?php
            $variante    = '';
            $taille      = 'grand';
            $lien        = false;
            require __DIR__ . '/logo.php';
            ?>
        </div>
