<?php
/**
 * Vue — Rapport de fin de campagne (EF-F06, EF-F07).
 *
 * Document autonome : la vue produit son propre <html>, sans gabarit. Le
 * rapport est destiné à être imprimé ou transmis à l’annonceur ; le menu du
 * back-office n’y a rien à faire et se retrouverait sur le papier.
 *
 * Variables reçues du contrôleur (voir AdminController::donneesRapport) :
 *   $campagne    array  — la campagne et son annonceur
 *   $duree       int    — durée de la campagne, en jours
 *   $indicateurs array  — définition des indicateurs mesurés (ch. 5.7)
 *   $mesure      array  — interactions réellement enregistrées
 *   $estime      array  — portée déduite par extrapolation
 *   $declare     array  — origine des données déclarées
 *   $serie       array  — scans jour par jour sur la période
 *   $panneaux    array  — détail par panneau
 *   $totaux      array  — totaux de la campagne
 *   $edite       string — date d’édition
 *
 * Le chapitre 6 est explicite : « Présenter un chiffre estimé comme s’il était
 * mesuré détruit la crédibilité du dispositif. » Chaque bloc porte donc sa
 * nature, et le tableau par panneau range ses colonnes sous trois en-têtes
 * distincts — mesuré, estimé, déclaré — pour qu’aucune colonne ne puisse être
 * lue dans la mauvaise catégorie.
 */

declare(strict_types=1);

$scans        = (int) ($mesure['scans_bruts'] ?? 0);
$scansRef     = max(1, $scans);
$nbPanneaux   = (int) $totaux['panneaux'];
$traficJour   = (int) ($estime['trafic_journalier'] ?? 0);
$passages     = (int) ($estime['passages_cumules'] ?? 0);
$scansAttendus = (int) ($estime['scans_estimes'] ?? 0);

// Écart entre ce qui était attendu et ce qui a été mesuré. Il est présenté
// comme une lecture, jamais comme un résultat : les deux nombres ne sont pas
// de même nature, et l’écart n’a de sens qu’accompagné de la méthode.
$ecart = $scansAttendus > 0
    ? round(($scans - $scansAttendus) / $scansAttendus * 100, 1)
    : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Rapport — <?= e((string) ($campagne['nom'] ?? 'Campagne')) ?> — <?= e(APP_NAME) ?></title>
<style>
/* ==========================================================================
   Rapport de fin de campagne — EF-F06, EF-F07
   ========================================================================== */

*, *::before, *::after { box-sizing: border-box; }

@page {
    size: A4 portrait;
    margin: 14mm;
}

html, body { margin: 0; padding: 0; }

body {
    background: #17171c;
    color: #e9e9f0;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    font-size: 15px;
    line-height: 1.5;
}

/* --- Barre d’outils, écran uniquement ----------------------------------- */
.barre-outils {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    background: #22222b;
    border-bottom: 1px solid #34343f;
}
.barre-outils__titre { font-size: 15px; font-weight: 600; }
.barre-outils__titre span { color: #c4b5fd; }
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
.barre-outils button.principal { background: #6d28d9; border-color: #7c3aed; color: #fff; font-weight: 600; }

/* --- La feuille --------------------------------------------------------- */
.feuille {
    width: 210mm;
    min-height: 297mm;
    margin: 24px auto;
    padding: 14mm;
    background: #ffffff;
    color: #101014;
    font-size: 10.5pt;
}

.feuille__entete {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12mm;
    border-bottom: 2pt solid #101014;
    padding-bottom: 3mm;
    margin-bottom: 5mm;
}
.feuille__titre { font-size: 17pt; font-weight: 800; line-height: 1.15; margin: 0; }
.feuille__sous-titre { font-size: 11pt; font-weight: 600; color: #4b5563; margin: 1mm 0 0; }
.feuille__edition { font-size: 8pt; color: #6b7280; text-align: right; white-space: nowrap; }

.feuille__reperes {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 3mm;
    margin-bottom: 5mm;
}
.repere {
    border: .4pt solid #cbd5e1;
    border-radius: 2mm;
    padding: 2.5mm 3mm;
}
.repere__etiquette {
    font-size: 7.5pt;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #6b7280;
}
.repere__valeur { font-size: 13pt; font-weight: 700; margin-top: .5mm; }

/* --- Étiquettes de nature ------------------------------------------------ */
.nature {
    display: inline-block;
    padding: .3mm 2mm;
    border: .4pt solid;
    border-radius: 1mm;
    font-size: 7.5pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    vertical-align: middle;
}
.nature--mesure  { border-color: #15803d; color: #15803d; background: #f0fdf4; }
.nature--estime  { border-color: #b45309; color: #b45309; background: #fffbeb; }
.nature--declare { border-color: #6d28d9; color: #6d28d9; background: #f5f3ff; }

/* --- Sections ----------------------------------------------------------- */
.section { margin-bottom: 6mm; break-inside: avoid; }
.section__entete {
    display: flex;
    align-items: center;
    gap: 3mm;
    border-bottom: .6pt solid #cbd5e1;
    padding-bottom: 1.5mm;
    margin-bottom: 3mm;
}
.section__titre { font-size: 12pt; font-weight: 700; margin: 0; flex: 1; }
.section__note { font-size: 8.5pt; color: #4b5563; margin: 0 0 3mm; }

table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 1.6mm 2mm; border-bottom: .4pt solid #e2e8f0; }
thead th {
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #475569;
    border-bottom: .8pt solid #94a3b8;
}
td.nombre, th.nombre { text-align: right; font-variant-numeric: tabular-nums; }
tbody tr:last-child td { border-bottom: none; }
.ligne-total td { font-weight: 700; border-top: .8pt solid #94a3b8; }

/* Trois familles de colonnes, distinguées par un fond très clair : à
   l’impression noir et blanc les libellés restent lisibles, mais la
   séparation est visible à l’écran. */
.col-mesure  { background: #f0fdf4; }
.col-estime  { background: #fffbeb; }
.col-declare { background: #f5f3ff; }
thead th.col-mesure, thead th.col-estime, thead th.col-declare { color: #101014; }

.groupe-entete th {
    font-size: 7.5pt;
    text-align: center;
    border-bottom: .4pt solid #cbd5e1;
    letter-spacing: .08em;
}

.encart {
    border: .4pt solid #cbd5e1;
    border-left: 3pt solid #94a3b8;
    border-radius: 1.5mm;
    padding: 3mm;
    font-size: 9pt;
    background: #f8fafc;
}
.encart strong { font-weight: 700; }

.grille-portee { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3mm; margin-bottom: 3mm; }

.feuille__pied {
    margin-top: 6mm;
    border-top: .6pt solid #cbd5e1;
    padding-top: 2mm;
    font-size: 7.5pt;
    color: #6b7280;
}

@media print {
    html, body { background: #ffffff; }
    .barre-outils { display: none !important; }
    .feuille { width: auto; min-height: 0; margin: 0; padding: 0; }
    .section { page-break-inside: avoid; }
    thead { display: table-header-group; }
}
</style>
</head>
<body>

<div class="barre-outils">
    <div class="barre-outils__titre">
        Rapport de fin de campagne — <span><?= e((string) ($campagne['nom'] ?? '')) ?></span>
    </div>
    <button type="button" class="principal" onclick="window.print()">Imprimer</button>
    <a class="bouton" href="<?= e(url('admin/campagnes/' . (int) $campagne['id'] . '/rapport.csv')) ?>">
        Exporter en CSV
    </a>
    <a class="bouton" href="<?= e(url('admin/statistiques?campagne=' . (int) $campagne['id'])) ?>">
        Tableau de bord
    </a>
</div>

<div class="feuille">

    <div class="feuille__entete">
        <div>
            <h1 class="feuille__titre">Rapport de fin de campagne</h1>
            <p class="feuille__sous-titre">
                <?= e((string) ($campagne['nom'] ?? '')) ?>
                <?php if (!empty($campagne['annonceur_nom'])): ?>
                    · <?= e((string) $campagne['annonceur_nom']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="feuille__edition">
            <?= e(APP_NAME) ?><br>
            Édité le <?= e($edite) ?><br>
            <?= e(BASE_URL) ?>
        </div>
    </div>

    <div class="feuille__reperes">
        <div class="repere">
            <div class="repere__etiquette">Début</div>
            <div class="repere__valeur"><?= e(date_fr((string) ($campagne['date_debut'] ?? ''))) ?></div>
        </div>
        <div class="repere">
            <div class="repere__etiquette">Fin</div>
            <div class="repere__valeur"><?= e(date_fr((string) ($campagne['date_fin'] ?? ''))) ?></div>
        </div>
        <div class="repere">
            <div class="repere__etiquette">Durée</div>
            <div class="repere__valeur"><?= e((string) $duree) ?> jour(s)</div>
        </div>
        <div class="repere">
            <div class="repere__etiquette">Panneaux</div>
            <div class="repere__valeur"><?= e((string) $nbPanneaux) ?></div>
        </div>
    </div>

    <!-- ------------------------------------------------------------------
         Interactions mesurées
         ------------------------------------------------------------------ -->
    <div class="section">
        <div class="section__entete">
            <h2 class="section__titre">Interactions mesurées</h2>
            <?= etiquette_nature('mesure') ?>
        </div>
        <p class="section__note">
            Chaque ligne compte des interactions réellement enregistrées par la Plateforme.
            Aucune extrapolation n’entre dans ce tableau.
        </p>
        <table>
            <thead>
                <tr>
                    <th>Indicateur</th>
                    <th class="nombre">Valeur</th>
                    <th class="nombre">Part des scans</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($indicateurs as $indicateur): ?>
                    <?php
                    $cle    = (string) $indicateur['cle'];
                    $valeur = (int) ($mesure[$cle] ?? 0);
                    ?>
                    <tr>
                        <td><?= e((string) $indicateur['libelle']) ?></td>
                        <td class="nombre"><strong><?= e(number_format($valeur, 0, ',', ' ')) ?></strong></td>
                        <td class="nombre">
                            <?= $cle === 'scans_bruts'
                                ? 'référence'
                                : e(pourcent($valeur / $scansRef * 100, 1)) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ------------------------------------------------------------------
         Portée estimée
         ------------------------------------------------------------------ -->
    <div class="section">
        <div class="section__entete">
            <h2 class="section__titre">Portée estimée</h2>
            <?= etiquette_nature('estime') ?>
        </div>
        <p class="section__note">
            Ces valeurs sont déduites du trafic déclaré sur les fiches panneaux, par extrapolation.
            Elles ne mesurent rien : elles situent l’ordre de grandeur de l’exposition.
        </p>
        <div class="grille-portee">
            <div class="repere col-estime">
                <div class="repere__etiquette">Trafic journalier retenu</div>
                <div class="repere__valeur"><?= e(number_format($traficJour, 0, ',', ' ')) ?></div>
            </div>
            <div class="repere col-estime">
                <div class="repere__etiquette">Passages cumulés</div>
                <div class="repere__valeur"><?= e(number_format($passages, 0, ',', ' ')) ?></div>
            </div>
            <div class="repere col-estime">
                <div class="repere__etiquette">Scans attendus</div>
                <div class="repere__valeur"><?= e(number_format($scansAttendus, 0, ',', ' ')) ?></div>
            </div>
        </div>
        <div class="encart">
            <strong>Méthode.</strong> <?= e((string) ($estime['methode'] ?? '—')) ?>.
            Le trafic journalier retenu est la somme des trafics déclarés sur les fiches des
            <?= e((string) $nbPanneaux) ?> panneaux, multipliée par <?= e((string) $duree) ?> jour(s).
            <?php if ($ecart !== null): ?>
                Les scans mesurés représentent <?= e(pourcent((float) $ecart, 1)) ?>
                <?= $ecart >= 0 ? 'de plus' : 'de moins' ?> que cette attente.
                Cet écart se lit, il ne se mesure pas : les deux nombres ne sont pas de même nature.
            <?php endif; ?>
        </div>
    </div>

    <!-- ------------------------------------------------------------------
         Données déclarées
         ------------------------------------------------------------------ -->
    <div class="section">
        <div class="section__entete">
            <h2 class="section__titre">Données déclarées</h2>
            <?= etiquette_nature('declare') ?>
        </div>
        <p class="section__note">
            Ces valeurs ont été saisies à la main sur les fiches panneaux. Elles servent de base au
            calcul de la portée estimée et engagent la responsabilité de la régie, pas celle du dispositif.
        </p>
        <table>
            <thead>
                <tr>
                    <th>Donnée</th>
                    <th>Origine</th>
                    <th class="nombre">Valeur</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Trafic journalier cumulé</td>
                    <td><?= e((string) ($declare['source'] ?? '—')) ?></td>
                    <td class="nombre"><?= e(number_format($traficJour, 0, ',', ' ')) ?> passages / jour</td>
                </tr>
                <tr>
                    <td>Nature de la donnée</td>
                    <td>Déclaration de la régie</td>
                    <td class="nombre"><?= e((string) ($declare['nature'] ?? 'déclaré')) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ------------------------------------------------------------------
         Détail par panneau — attribution issue des codes courts (EF-C01)
         ------------------------------------------------------------------ -->
    <div class="section">
        <div class="section__entete">
            <h2 class="section__titre">Détail par panneau</h2>
        </div>
        <p class="section__note">
            Chaque couple campagne × panneau porte son propre QR code : les colonnes mesurées sont donc
            attribuables panneau par panneau. Le total des visiteurs uniques reste celui de la campagne —
            une même personne ayant pu scanner deux panneaux, une somme par panneau compterait des doublons.
        </p>
        <table>
            <thead>
                <tr class="groupe-entete">
                    <th colspan="2"></th>
                    <th colspan="2" class="col-mesure">Mesuré</th>
                    <th class="col-declare">Déclaré</th>
                    <th colspan="2" class="col-estime">Estimé</th>
                </tr>
                <tr>
                    <th>Panneau</th>
                    <th>Emplacement</th>
                    <th class="nombre col-mesure">Scans</th>
                    <th class="nombre col-mesure">Visiteurs</th>
                    <th class="nombre col-declare">Trafic / jour</th>
                    <th class="nombre col-estime">Passages cumulés</th>
                    <th class="nombre col-estime">Scans attendus</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($panneaux === []): ?>
                    <tr>
                        <td colspan="7">Aucun panneau n’est rattaché à cette campagne.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($panneaux as $p): ?>
                        <tr>
                            <td><strong><?= e((string) $p['reference']) ?></strong></td>
                            <td>
                                <?= e((string) $p['libelle']) ?>
                                <?php if ($p['ville'] !== ''): ?>
                                    <br><span style="color:#6b7280"><?= e((string) $p['ville']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="nombre col-mesure"><?= e(number_format((float) $p['scans'], 0, ',', ' ')) ?></td>
                            <td class="nombre col-mesure"><?= e(number_format((float) $p['visiteurs'], 0, ',', ' ')) ?></td>
                            <td class="nombre col-declare"><?= e(number_format((float) $p['trafic_declare'], 0, ',', ' ')) ?></td>
                            <td class="nombre col-estime"><?= e(number_format((float) $p['passages_estimes'], 0, ',', ' ')) ?></td>
                            <td class="nombre col-estime"><?= e(number_format((float) $p['scans_attendus'], 0, ',', ' ')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="ligne-total">
                        <td colspan="2">Total campagne</td>
                        <td class="nombre col-mesure"><?= e(number_format((float) $totaux['scans'], 0, ',', ' ')) ?></td>
                        <td class="nombre col-mesure"><?= e(number_format((float) $totaux['visiteurs'], 0, ',', ' ')) ?></td>
                        <td class="nombre col-declare"><?= e(number_format($traficJour, 0, ',', ' ')) ?></td>
                        <td class="nombre col-estime"><?= e(number_format((float) $totaux['passages_estimes'], 0, ',', ' ')) ?></td>
                        <td class="nombre col-estime"><?= e(number_format($scansAttendus, 0, ',', ' ')) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ------------------------------------------------------------------
         Série quotidienne
         ------------------------------------------------------------------ -->
    <?php if ($serie !== []): ?>
        <?php
        $maxJour = 1;
        foreach ($serie as $jour) {
            $maxJour = max($maxJour, (int) $jour['scans']);
        }
        ?>
        <div class="section">
            <div class="section__entete">
                <h2 class="section__titre">Scans jour par jour</h2>
                <?= etiquette_nature('mesure') ?>
            </div>
            <p class="section__note">
                Période couverte : du <?= e(date_fr((string) ($campagne['date_debut'] ?? ''))) ?>
                au <?= e(date_fr((string) ($campagne['date_fin'] ?? ''))) ?>.
            </p>
            <div style="display:flex;align-items:flex-end;gap:1.5mm;height:34mm;border-bottom:.4pt solid #94a3b8">
                <?php foreach ($serie as $jour): ?>
                    <?php $hauteur = max(1.0, round((int) $jour['scans'] / $maxJour * 32, 1)); ?>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%">
                        <span style="font-size:6.5pt;color:#475569"><?= e((string) (int) $jour['scans']) ?></span>
                        <div style="width:100%;height:<?= e((string) $hauteur) ?>mm;background:#6d28d9"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:1.5mm;margin-top:1mm">
                <?php foreach ($serie as $jour): ?>
                    <div style="flex:1;text-align:center;font-size:6pt;color:#6b7280">
                        <?= e(date('d/m', (int) strtotime((string) $jour['jour']))) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="feuille__pied">
        Ce rapport sépare trois natures de données : <strong>mesuré</strong> — enregistré par la
        Plateforme ; <strong>estimé</strong> — déduit par extrapolation du trafic déclaré ;
        <strong>déclaré</strong> — saisi sur les fiches panneaux. Un chiffre estimé ou déclaré ne doit
        jamais être présenté comme une mesure. Édité le <?= e($edite) ?>.
    </div>

</div>

</body>
</html>
