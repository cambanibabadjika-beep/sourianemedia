<?php
/**
 * Vue — Affiche imprimable d’un code court (EF-G06, ch. 5.1).
 *
 * Document autonome : la vue produit son propre <html>, sans gabarit. Une
 * affiche est une feuille de papier, pas une page d’administration — le menu
 * latéral, l’en-tête et le pied de site n’ont rien à y faire, et les masquer
 * en CSS laisserait des éléments dans le document envoyé à l’imprimante.
 *
 * Variables reçues du contrôleur :
 *   $code            array   — contexte du code (campagne, panneau, annonceur)
 *   $destination     string  — cible actuelle de la redirection
 *   $modele          string  — « panneau » ou « vitrine »
 *   $distance        float   — distance de lecture visée, en mètres
 *   $largeurSymbole  float   — largeur exigée du symbole, en centimètres
 *   $largeurImage    float   — largeur du dessin, marge de silence comprise
 *   $largeurImprimee float   — largeur effectivement imprimable
 *   $qr              string  — le SVG du QR code
 *   $format          string  — format ISO retenu (A4 … A0)
 *   $papierLargeur   float   — largeur du papier, en centimètres
 *   $papierHauteur   float   — hauteur du papier, en centimètres
 *   $largeurUtile    float   — largeur utile du papier, marges déduites
 *   $assezGrand      bool    — le papier retenu suffit-il à la distance ?
 *   $urlCourte       string  — URL encodée dans le QR code
 *
 * Trois points du cahier des charges gouvernent la mise en page :
 *   — la zone de silence fait au moins quatre modules et ne contient aucun
 *     texte ni aucune image ; elle est dessinée dans le SVG lui-même, et le
 *     bloc qui l’entoure n’est qu’un rembourrage blanc ;
 *   — le code est sombre sur fond clair, jamais l’inverse ;
 *   — le code court lisible est imprimé sous le QR code, en secours.
 *
 * Le nom de la marque est écrit en toutes lettres plutôt qu’en image : une
 * affiche grand format ne pardonne pas un logo matriciel agrandi au-delà de
 * sa résolution, et un fichier absent laisserait un carré vide sur le papier.
 */

declare(strict_types=1);

$distance        = (float) $distance;
$largeurSymbole  = (float) $largeurSymbole;
$largeurImage    = (float) $largeurImage;
$largeurImprimee = (float) $largeurImprimee;
$papierLargeur   = (float) $papierLargeur;
$papierHauteur   = (float) $papierHauteur;
$largeurUtile    = (float) $largeurUtile;
$assezGrand      = (bool) $assezGrand;
$modele          = $modele === 'vitrine' ? 'vitrine' : 'panneau';

// Le texte est exprimé en « em » et la racine de la feuille en centimètres :
// une même affiche reste proportionnée du A4 au A0, sans jeu de tailles par
// format. La base vaut un quarantième de la largeur du papier — 0,53 cm sur
// un A4, 2,1 cm sur un A0.
$baseCm = round($papierLargeur / 40, 4);

// Largeur du symbole telle qu’elle sera réellement imprimée, déduite du
// rapport entre le dessin et le symbole qu’il contient.
$rapport         = $largeurImage > 0 ? $largeurSymbole / $largeurImage : 1.0;
$symboleImprime  = round($largeurImprimee * $rapport, 1);

$codeTexte     = strtoupper((string) $code['code']);
$codeSecours   = (string) ($code['code_secours'] ?? '');
$annonceurNom  = (string) ($code['annonceur_nom'] ?? '');
$campagneNom   = (string) ($code['campagne_nom'] ?? '');
$panneauRef    = (string) ($code['panneau_reference'] ?? '');
$panneauLib    = (string) ($code['panneau_libelle'] ?? '');
$ville         = (string) ($code['ville'] ?? '');
$debut         = $code['date_debut'] ?? null;
$fin           = $code['date_fin'] ?? null;
$actif         = (bool) ($code['actif'] ?? true);

$mentions = array_filter([
    $campagneNom !== '' ? 'Campagne ' . $campagneNom : '',
    $panneauRef !== '' ? 'Panneau ' . $panneauRef . ($panneauLib !== '' ? ' — ' . $panneauLib : '') : '',
    $ville,
]);

$validite = '';
if ($fin !== null && $fin !== '') {
    $validite = 'Offre valable jusqu’au ' . date_fr((string) $fin);
}

/*
 * Ligne technique du pied : elle indique où mène réellement le code. La
 * destination peut être un chemin interne — c’est le cas courant, la page de
 * l’annonceur — ou une adresse complète ; on l’affiche dans les deux cas sous
 * une forme absolue, pour qu’elle soit recopiable telle quelle.
 */
$cible = (string) $destination;
if (!str_starts_with($cible, 'http')) {
    $cible = url($cible);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Affiche <?= e($codeTexte) ?> — <?= e(APP_NAME) ?></title>
<style>
/* ==========================================================================
   Affiche imprimable — EF-G06, ch. 5.1
   --------------------------------------------------------------------------
   Deux contextes de rendu cohabitent :
     — l’écran, où l’on choisit la distance, le modèle, et d’où l’on lance
       l’impression ; la barre d’outils et l’échelle d’aperçu n’existent que
       là, et sont retirées à l’impression ;
     — le papier, où seule la feuille compte, à sa taille réelle.
   ========================================================================== */

*, *::before, *::after { box-sizing: border-box; }

@page {
    size: <?= e($format) ?> portrait;
    margin: 0;
}

html, body { margin: 0; padding: 0; }

body {
    background: #17171c;
    color: #e9e9f0;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

/* --------------------------------------------------------------------------
   Barre d’outils — écran uniquement
   -------------------------------------------------------------------------- */
.barre-outils {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
    padding: 12px 20px;
    background: #22222b;
    border-bottom: 1px solid #34343f;
}
.barre-outils__titre {
    font-size: 15px;
    font-weight: 600;
    letter-spacing: .02em;
}
.barre-outils__titre code {
    font-size: 15px;
    color: #c4b5fd;
}
.barre-outils form {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
    margin: 0;
}
.barre-outils label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #9b9bab;
    margin-bottom: 4px;
}
.barre-outils input,
.barre-outils select {
    padding: 7px 10px;
    border: 1px solid #3d3d4a;
    border-radius: 6px;
    background: #17171c;
    color: #e9e9f0;
    font-size: 14px;
    font-family: inherit;
}
.barre-outils input[type="number"] { width: 90px; }
.barre-outils button,
.barre-outils a.bouton {
    padding: 8px 14px;
    border: 1px solid #4c4c5c;
    border-radius: 6px;
    background: #2c2c37;
    color: #e9e9f0;
    font-size: 14px;
    font-family: inherit;
    text-decoration: none;
    cursor: pointer;
}
.barre-outils button.principal {
    background: #6d28d9;
    border-color: #7c3aed;
    color: #fff;
    font-weight: 600;
}
.barre-outils__mesures {
    margin-left: auto;
    font-size: 12.5px;
    line-height: 1.6;
    color: #a9a9bb;
    text-align: right;
}
.barre-outils__mesures strong { color: #e9e9f0; }

.avertissement {
    flex-basis: 100%;
    padding: 10px 14px;
    border: 1px solid #b45309;
    border-left-width: 4px;
    border-radius: 6px;
    background: #2a1c0c;
    color: #fcd9a8;
    font-size: 13.5px;
    line-height: 1.6;
}
.avertissement strong { color: #fdba74; }

/* --------------------------------------------------------------------------
   Aperçu — l’échelle ne s’applique qu’à l’écran
   -------------------------------------------------------------------------- */
.apercu {
    zoom: var(--echelle, 0.5);
    padding: 28px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

/* --------------------------------------------------------------------------
   La feuille
   -------------------------------------------------------------------------- */
.feuille {
    position: relative;
    width: <?= e((string) $papierLargeur) ?>cm;
    height: <?= e((string) $papierHauteur) ?>cm;
    padding: 2cm;
    background: #ffffff;
    color: #101014;
    font-size: <?= e((string) $baseCm) ?>cm;
    line-height: 1.25;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 1.2em;
}

/* Bandeau d’alerte, imprimé lui aussi : une affiche sous-dimensionnée ne doit
   pas pouvoir être posée sans que personne ne s’en aperçoive. */
.feuille__alerte {
    padding: .5em .8em;
    border: .12em solid #b91c1c;
    background: #fee2e2;
    color: #7f1d1d;
    font-size: .8em;
    font-weight: 600;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.feuille__entete {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 1em;
    border-bottom: .06em solid #101014;
    padding-bottom: .5em;
}
.feuille__marque {
    font-size: 1.5em;
    font-weight: 800;
    letter-spacing: -.01em;
}
.feuille__slogan {
    font-size: .8em;
    color: #4b5563;
}

.feuille__corps {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .9em;
    min-height: 0;
}

/* Le rembourrage blanc qui entoure le code fait partie de la zone de silence :
   aucun texte, aucun filet, aucune image ne doit s’y trouver. Le SVG porte
   lui-même les quatre modules réglementaires ; ce rembourrage les protège. */
.qr-bloc {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: .4em;
    background: #ffffff;
}
.qr-bloc svg {
    display: block;
    width: 100%;
    height: 100%;
}

.feuille__invite {
    margin: 0;
    font-size: 1.35em;
    font-weight: 700;
    text-align: center;
    text-wrap: balance;
}

/* Code de secours : ce que l’on tape à la main quand l’appareil photo refuse
   la mise au point ou que le code est abîmé (ch. 5.1). Il doit être lisible à
   la même distance que le reste du visuel. */
.feuille__secours {
    margin: 0;
    text-align: center;
    font-size: 1.15em;
}
.feuille__secours span {
    display: inline-block;
    margin-top: .3em;
    padding: .15em .6em;
    border: .1em solid #101014;
    font-family: ui-monospace, "Cascadia Mono", "Consolas", monospace;
    font-size: 1.25em;
    font-weight: 700;
    letter-spacing: .06em;
    word-break: break-all;
}
.feuille__code {
    margin: 0;
    text-align: center;
    font-family: ui-monospace, "Cascadia Mono", "Consolas", monospace;
    font-size: 1.6em;
    font-weight: 700;
    letter-spacing: .22em;
    text-indent: .22em;
}

.feuille__pied {
    border-top: .06em solid #101014;
    padding-top: .6em;
    display: flex;
    flex-direction: column;
    gap: .25em;
}
.feuille__annonceur {
    font-size: 1.3em;
    font-weight: 700;
}
.feuille__mentions {
    font-size: .78em;
    color: #374151;
}
.feuille__validite {
    font-size: .78em;
    font-weight: 600;
}
.feuille__technique {
    font-size: .62em;
    color: #6b7280;
    font-family: ui-monospace, "Cascadia Mono", "Consolas", monospace;
    word-break: break-all;
}

/* --------------------------------------------------------------------------
   Modèle « vitrine » — composition en deux colonnes, lue à bout portant
   -------------------------------------------------------------------------- */
.feuille--vitrine .feuille__corps {
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 1.4em;
}
.feuille--vitrine .qr-bloc { flex: 0 0 auto; }
.feuille--vitrine .vitrine__texte {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: .7em;
    align-items: flex-start;
}
.feuille--vitrine .feuille__invite,
.feuille--vitrine .feuille__secours,
.feuille--vitrine .feuille__code { text-align: left; }
.feuille--vitrine .feuille__code { font-size: 1.3em; }

/* --------------------------------------------------------------------------
   Impression
   -------------------------------------------------------------------------- */
@media print {
    html, body { background: #ffffff; }
    .barre-outils { display: none !important; }
    .apercu {
        zoom: 1;
        padding: 0;
        display: block;
    }
    .feuille {
        /* Le papier ne connaît pas les arrondis ni les ombres : on ne garde
           que l’aplat blanc et le dessin. */
        box-shadow: none !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>
</head>
<body>

<div class="barre-outils">
    <div class="barre-outils__titre">
        Affiche — code <code><?= e($codeTexte) ?></code>
    </div>

    <form method="get" action="<?= e(url('admin/codes/' . (int) $code['id'] . '/affiche')) ?>">
        <div>
            <label for="distance">Distance de lecture (m)</label>
            <input type="number" id="distance" name="distance" min="1" max="20" step="0.5"
                   value="<?= e((string) $distance) ?>">
        </div>
        <div>
            <label for="modele">Modèle</label>
            <select id="modele" name="modele">
                <option value="panneau"<?= $modele === 'panneau' ? ' selected' : '' ?>>Panneau (lu de loin)</option>
                <option value="vitrine"<?= $modele === 'vitrine' ? ' selected' : '' ?>>Affichette de vitrine</option>
            </select>
        </div>
        <div>
            <label for="echelle">Échelle d’aperçu</label>
            <select id="echelle">
                <option value="1">100 %</option>
                <option value="0.5" selected>50 %</option>
                <option value="0.35">35 %</option>
                <option value="0.25">25 %</option>
            </select>
        </div>
        <button type="submit">Recalculer</button>
        <button type="button" class="principal" onclick="window.print()">Imprimer</button>
        <a class="bouton" href="<?= e(url('admin/codes')) ?>">Retour aux codes</a>
    </form>

    <div class="barre-outils__mesures">
        Format <strong><?= e($format) ?></strong>
        (<?= e((string) $papierLargeur) ?> × <?= e((string) $papierHauteur) ?> cm) ·
        symbole <strong><?= e((string) $symboleImprime) ?> cm</strong><br>
        Exigé à <?= e((string) $distance) ?> m : <?= e((string) $largeurSymbole) ?> cm
        (distance ÷ 10) · largeur utile <?= e((string) $largeurUtile) ?> cm<br>
        Correction d’erreur H · marge de silence 4 modules ·
        <?= e($modele === 'vitrine' ? 'modèle vitrine' : 'modèle panneau') ?>
    </div>

    <?php if (!$assezGrand): ?>
        <div class="avertissement">
            <strong>Affiche sous-dimensionnée.</strong>
            À <?= e((string) $distance) ?> m de distance, le chapitre 5.1 exige un symbole de
            <?= e((string) $largeurSymbole) ?> cm de large, marge de silence comprise, soit
            <?= e((string) $largeurImage) ?> cm de largeur totale. Le plus grand format normalisé
            (A0) n’offre que <?= e((string) $largeurUtile) ?> cm utiles.
            L’aperçu ci-dessous est donc imprimé à <?= e((string) $symboleImprime) ?> cm seulement.
            Pour un panneau de bord de route, commandez un support de
            <?= e((string) ceil($largeurImage + 4)) ?> cm de large au minimum, ou réduisez la distance de lecture.
        </div>
    <?php endif; ?>

    <?php if (!$actif): ?>
        <div class="avertissement">
            <strong>Code désactivé.</strong>
            Ce code ne mène plus nulle part : la résolution d’un scan le refuse. L’affiche peut
            être préparée, mais elle ne doit pas être posée avant réactivation.
        </div>
    <?php endif; ?>
</div>

<div class="apercu" id="apercu" style="--echelle:.5">
    <div class="feuille feuille--<?= e($modele) ?>">

        <?php if (!$assezGrand): ?>
            <div class="feuille__alerte">
                Épreuve — ne pas poser en l’état : code sous-dimensionné pour <?= e((string) $distance) ?> m
            </div>
        <?php endif; ?>

        <div class="feuille__entete">
            <div class="feuille__marque"><?= e(APP_NAME) ?></div>
            <div class="feuille__slogan"><?= e(APP_SLOGAN) ?></div>
        </div>

        <div class="feuille__corps">
            <div class="qr-bloc"
                 style="width:<?= e((string) $largeurImprimee) ?>cm;height:<?= e((string) $largeurImprimee) ?>cm">
                <?= $qr ?>
            </div>

            <div class="vitrine__texte">
                <p class="feuille__invite">Scannez ce code avec l’appareil photo de votre téléphone</p>

                <?php if ($codeSecours !== ''): ?>
                    <p class="feuille__secours">
                        Ou tapez ce code sur <?= e(COURT_DOMAINE) ?> :
                        <span><?= e($codeSecours) ?></span>
                    </p>
                <?php endif; ?>

                <p class="feuille__code"><?= e($codeTexte) ?></p>
            </div>
        </div>

        <div class="feuille__pied">
            <?php if ($annonceurNom !== ''): ?>
                <div class="feuille__annonceur"><?= e($annonceurNom) ?></div>
            <?php endif; ?>

            <?php if ($mentions !== []): ?>
                <div class="feuille__mentions"><?= e(implode(' · ', $mentions)) ?></div>
            <?php endif; ?>

            <?php if ($validite !== ''): ?>
                <div class="feuille__validite"><?= e($validite) ?></div>
            <?php endif; ?>

            <div class="feuille__technique">
                <?= e($urlCourte) ?> → <?= e($cible) ?>
            </div>
        </div>

    </div>
</div>

<script>
/* L’échelle d’aperçu n’existe qu’à l’écran : elle permet de juger une affiche
   A0 sans écran de trois mètres. L’impression remet l’échelle à 1, quelle que
   soit la valeur choisie ici. */
(function () {
    var selecteur = document.getElementById('echelle');
    var apercu    = document.getElementById('apercu');
    if (!selecteur || !apercu) { return; }
    selecteur.addEventListener('change', function () {
        apercu.style.setProperty('--echelle', selecteur.value);
    });
})();
</script>

</body>
</html>
