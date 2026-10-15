<?php
/**
 * Vue — Page de contenu statique (mentions légales, confidentialité, etc.).
 *
 * Variables :
 *   $surtitre  string
 *   $titrePage string
 *   $intro     string
 *   $blocs     array<{titre:string, paragraphes:array<int,string>, liste:array<int,string>}>
 */

declare(strict_types=1);

$surtitre  = $surtitre ?? '';
$titrePage = $titrePage ?? '';
$intro     = $intro ?? '';
$blocs     = $blocs ?? [];
?>

<section class="section section--serree section--teinte">
    <div class="conteneur conteneur-etroit">
        <?php if ($surtitre !== ''): ?>
            <span class="entete-section__surtitre"><?= e($surtitre) ?></span>
        <?php endif; ?>
        <h1><?= e($titrePage) ?></h1>
        <?php if ($intro !== ''): ?>
            <p style="color:var(--gris-600);font-size:1.05rem;max-width:64ch"><?= e($intro) ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="conteneur conteneur-etroit">
        <?php foreach ($blocs as $bloc): ?>
            <div class="bloc">
                <h2 class="bloc__titre"><?= e((string) $bloc['titre']) ?></h2>
                <div class="bloc__texte">
                    <?php foreach (($bloc['paragraphes'] ?? []) as $paragraphe): ?>
                        <p><?= e($paragraphe) ?></p>
                    <?php endforeach; ?>

                    <?php if (!empty($bloc['liste'])): ?>
                        <ul style="margin:0;padding-left:1.2em;display:grid;gap:7px">
                            <?php foreach ($bloc['liste'] as $element): ?>
                                <li><?= e($element) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
