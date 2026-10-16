<?php
/**
 * Vue — Enrôlement du second facteur (ch. 8).
 *
 * Servie dans deux situations : un compte d’administration qui doit mettre en
 * place son second facteur avant d’entrer, et un compte connecté qui l’active
 * depuis ses réglages. Le parcours est le même, seul le texte d’introduction
 * change — d’où $obligatoire.
 *
 * Variables :
 *   $courriel    string — compte enrôlé
 *   $secret      string — clé partagée, en base32, à recopier si le QR code
 *                         ne peut pas être scanné
 *   $qr          string — SVG du QR code d’enrôlement, ou '' si le dessin a échoué
 *   $obligatoire bool   — le second facteur est-il imposé à ce compte
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Flash;
use Souriane\Core\Icone;

$courriel    = $courriel ?? '';
$secret      = $secret ?? '';
$qr          = $qr ?? '';
$obligatoire = (bool) ($obligatoire ?? false);
$messages    = Flash::recuperer();

require __DIR__ . '/../partials/entete-connexion.php';
?>

<h1>Vérification en deux étapes</h1>
<p class="connexion-carte__intro">
    <?php if ($obligatoire): ?>
        Votre compte relève de l’administration : la vérification en deux étapes
        est obligatoire avant l’entrée. Elle prend une minute à mettre en place.
    <?php else: ?>
        Renforcez l’accès au compte <strong><?= e($courriel) ?></strong> avec une
        application d’authentification.
    <?php endif; ?>
</p>

<?php if ($messages !== []): ?>
    <?php require __DIR__ . '/../partials/messages.php'; ?>
<?php endif; ?>

<ol class="etapes">
    <li>Ouvrez une application d’authentification — Google Authenticator, Authy, FreeOTP…</li>
    <li>Scannez le QR code ci-dessous, ou recopiez la clé à la main.</li>
    <li>Saisissez le code à six chiffres que l’application affiche, pour confirmer.</li>
</ol>

<?php if ($qr !== ''): ?>
    <div class="qr-enrolement">
        <?php /* SVG produit par QrService à partir de la clé, jamais d’une saisie */ ?>
        <?= $qr ?>
    </div>
<?php else: ?>
    <div class="message message--attention">
        <?= Icone::svg('alerte') ?>
        <div>Le QR code n’a pas pu être dessiné. Recopiez la clé ci-dessous : cela suffit.</div>
    </div>
<?php endif; ?>

<div class="champ">
    <span class="champ__etiquette">Clé à recopier</span>
    <code class="cle-secret"><?= e($secret) ?></code>
    <p class="champ__aide">
        À saisir en minuscules ou en majuscules, sans les espaces.
    </p>
</div>

<form method="post" action="<?= e(url('admin/connexion/enrolement')) ?>" autocomplete="off">
    <?= Csrf::champ() ?>

    <div class="champ">
        <label class="champ__etiquette" for="code">
            Code affiché par l’application <span class="requis">*</span>
        </label>
        <input class="saisie saisie--code" type="text" id="code" name="code" value=""
               required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="9">
    </div>

    <button type="submit" class="btn btn--primaire btn--large">
        <?= Icone::svg('verrou') ?>
        <?= $obligatoire ? 'Activer et entrer' : 'Activer la vérification' ?>
    </button>
</form>

<p class="texte-petit texte-centre" style="margin-top:16px">
    <a href="<?= e($annulationUrl ?? url('admin/securite')) ?>"><?= e($annulationLibelle ?? 'Annuler et revenir à la sécurité du compte') ?></a>
</p>

<?php require __DIR__ . '/../partials/pied-connexion.php'; ?>
