<?php
/**
 * Vue — Second facteur : saisie du code à usage unique (ch. 8).
 *
 * Variables :
 *   $courriel    string — compte concerné par la connexion en cours
 *   $secretDemo  string — clé de démonstration, vide hors mode démonstration
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Flash;
use Souriane\Core\Icone;

$courriel   = $courriel ?? '';
$secretDemo = $secretDemo ?? '';
$messages   = Flash::recuperer();

require __DIR__ . '/../partials/entete-connexion.php';
?>

<h1>Vérification en deux étapes</h1>
<p class="connexion-carte__intro">
    Saisissez le code affiché par votre application d’authentification
    pour le compte <strong><?= e($courriel) ?></strong>.
</p>

<?php if ($messages !== []): ?>
    <?php require __DIR__ . '/../partials/messages.php'; ?>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/connexion/code')) ?>" autocomplete="off">
    <?= Csrf::champ() ?>

    <div class="champ">
        <label class="champ__etiquette" for="code">Code à six chiffres <span class="requis">*</span></label>
        <input class="saisie saisie--code" type="text" id="code" name="code" value=""
               required autofocus inputmode="numeric" autocomplete="one-time-code"
               maxlength="9" aria-describedby="aide-code">
        <p class="champ__aide" id="aide-code">
            Les espaces sont tolérés. Le code change toutes les trente secondes.
        </p>
    </div>

    <button type="submit" class="btn btn--primaire btn--large">
        <?= Icone::svg('verrou') ?>
        Vérifier
    </button>
</form>

<details class="recours">
    <summary>Vous n’avez plus votre téléphone ?</summary>
    <p class="texte-petit">
        Chaque code de secours ne sert qu’une seule fois. Il a la forme
        <span class="monospace">XXXX-XXXX</span>.
    </p>
    <form method="post" action="<?= e(url('admin/connexion/code')) ?>" autocomplete="off">
        <?= Csrf::champ() ?>
        <div class="champ">
            <label class="champ__etiquette" for="secours">Code de secours</label>
            <input class="saisie saisie--code" type="text" id="secours" name="secours" value=""
                   inputmode="text" maxlength="12" autocapitalize="characters">
        </div>
        <button type="submit" class="btn btn--contour">
            <?= Icone::svg('suivant') ?>
            Utiliser ce code
        </button>
    </form>
</details>

<p class="texte-petit texte-centre" style="margin-top:16px">
    <a href="<?= e(url('admin/connexion')) ?>">Revenir à la connexion</a>
</p>

<?php if (APP_DEBUG && $secretDemo !== ''): ?>
    <div class="encart" style="margin-top:24px">
        <strong>Démonstration</strong><br>
        Les comptes de démonstration partagent la clé
        <span class="monospace"><?= e($secretDemo) ?></span>.<br>
        Ajoutez-la à une application d’authentification, ou laissez
        <span class="monospace">cron/preparer_2fa_demo.php</span> imprimer le code courant.<br>
        <span class="texte-petit">Cette indication n’apparaît qu’en mode démonstration (APP_DEBUG).</span>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/pied-connexion.php'; ?>
