<?php
/**
 * Vue — Écran de connexion au back-office.
 *
 * Variables :
 *   $courriel string
 *   $retour   string
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Flash;
use Souriane\Core\Icone;

$courriel = $courriel ?? '';
$retour   = $retour ?? '';
$messages = Flash::recuperer();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre ?? ('Connexion — ' . APP_NAME)) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#2E1065">
<link rel="icon" href="<?= e(asset('img/favicon.png')) ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>

<div class="connexion-page">
    <div class="connexion-carte">
        <div class="connexion-carte__marque">
            <?php
            $variante    = '';
            $taille      = 'grand';
            $lien        = false;
            require __DIR__ . '/../partials/logo.php';
            ?>
        </div>

        <h1>Espace régie</h1>
        <p class="connexion-carte__intro">Connectez-vous pour piloter le parc, les campagnes et la diffusion.</p>

        <?php if ($messages !== []): ?>
            <?php require __DIR__ . '/../partials/messages.php'; ?>
        <?php endif; ?>

        <form method="post" action="<?= e(url('admin/connexion')) ?>" autocomplete="on">
            <?= Csrf::champ() ?>
            <input type="hidden" name="retour" value="<?= e($retour) ?>">

            <div class="champ">
                <label class="champ__etiquette" for="email">Adresse e-mail <span class="requis">*</span></label>
                <input class="saisie" type="email" id="email" name="email" value="<?= e($courriel) ?>"
                       required autofocus autocomplete="username" inputmode="email">
            </div>

            <div class="champ">
                <label class="champ__etiquette" for="mot_de_passe">Mot de passe <span class="requis">*</span></label>
                <input class="saisie" type="password" id="mot_de_passe" name="mot_de_passe"
                       required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn--primaire btn--large">
                <?= Icone::svg('verrou') ?>
                Se connecter
            </button>
        </form>

        <?php if (APP_DEBUG): ?>
            <div class="encart" style="margin-top:24px">
                <strong>Comptes de démonstration</strong><br>
                admin@souriane.sn · agent@souriane.sn · annonceur@souriane.sn<br>
                Mot de passe : <span class="monospace">Souriane2026!</span>
            </div>
        <?php endif; ?>

        <p class="texte-petit texte-centre" style="margin-top:20px">
            <a href="<?= e(url('/')) ?>">← Retour au site public</a>
        </p>
    </div>
</div>

</body>
</html>
