<?php
/**
 * Vue — Écran de connexion au back-office (premier facteur).
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

require __DIR__ . '/../partials/entete-connexion.php';
?>

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
        Mot de passe : <span class="monospace">Souriane2026!</span><br>
        <span class="texte-petit">
            La régie et l’agent terrain passent par la vérification en deux étapes.
        </span>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pied-connexion.php'; ?>
