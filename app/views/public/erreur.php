<?php
/**
 * Vue — Page d’erreur (404, 419, 500…).
 *
 * Variables :
 *   $codeErreur    int
 *   $titreErreur   string
 *   $messageErreur string
 */

declare(strict_types=1);

use Souriane\Core\Icone;

$codeErreur    = $codeErreur ?? 404;
$titreErreur   = $titreErreur ?? 'Page introuvable';
$messageErreur = $messageErreur ?? 'L’adresse demandée n’existe pas ou n’est plus disponible.';
?>

<section class="section">
    <div class="conteneur conteneur-etroit texte-centre" style="text-align:center;padding:70px 0">
        <div style="font-family:var(--police-titre);font-weight:700;font-size:clamp(3.4rem,14vw,6rem);line-height:1;color:var(--violet-300)">
            <?= e((string) $codeErreur) ?>
        </div>
        <h1 style="margin-top:10px"><?= e($titreErreur) ?></h1>
        <p style="color:var(--gris-600);max-width:52ch;margin:0 auto 28px"><?= e($messageErreur) ?></p>

        <div class="pile" style="justify-content:center">
            <a class="btn btn--primaire" href="<?= e(url('/')) ?>">
                <?= Icone::svg('fleche') ?>
                Retour à l’accueil
            </a>
            <a class="btn btn--contour" href="<?= e(url('le-parc')) ?>">
                <?= Icone::svg('panneau') ?>
                Voir le parc
            </a>
        </div>
    </div>
</section>
