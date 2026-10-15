<?php
/**
 * Partial — Marque Souriane (marque vectorielle + mot-symbole).
 *
 * Variables facultatives :
 *   $variante    '' | 'blanc'  — couleur du texte
 *   $taille      '' | 'grand'
 *   $lien        bool          — enveloppe la marque dans un lien (défaut : true)
 *   $destination string        — cible du lien
 */

declare(strict_types=1);

$variante    = $variante ?? '';
$taille      = $taille ?? '';
$lien        = $lien ?? true;
$destination = $destination ?? url('/');

$classes = 'logo';
if ($variante === 'blanc') {
    $classes .= ' logo--blanc';
}
if ($taille === 'grand') {
    $classes .= ' logo--grand';
}

$balise     = $lien ? 'a' : 'span';
$attributs  = $lien ? ' href="' . e($destination) . '"' : '';
$libelle    = ' aria-label="' . e(APP_NAME) . ' — accueil"';
?>
<<?= $balise ?><?= $attributs ?> class="<?= $classes ?>"<?= $libelle ?>>
    <svg class="logo__marque" viewBox="0 0 122 192" role="img" aria-hidden="true" focusable="false"><g fill="currentColor"><path d="M2.00,150.00A58.67,58.67 0 0 0 97.12,79.12Z"/><path d="M25.75,112.75A58.58,58.58 0 0 1 119.88,41.88Z"/><circle cx="97.90" cy="176.06" r="13.85"/><circle cx="23.45" cy="15.70" r="13.84"/></g></svg>
    <span class="logo__mot"><?= e(APP_NAME) ?></span>
</<?= $balise ?>>
