<?php
/**
 * Vue — Statistiques d’une campagne.
 *
 * Variables :
 *   $campagnes  array
 *   $campagneId int
 *   $detail     ?array — {mesure, estime, declare}
 *   $serie      array  — série quotidienne
 *   $appareils  array  — répartition par appareil
 *   $globaux    array  — compteurs globaux
 */

declare(strict_types=1);

use Souriane\Core\Icone;
use Souriane\Models\Evenement;

$mesure = $detail['mesure'] ?? [];
$estime = $detail['estime'] ?? [];
$maxSerie = 1;
foreach ($serie as $jour) {
    $maxSerie = max($maxSerie, (int) $jour['scans']);
}

/* Indicateurs mesurés, dans l’ordre du chapitre 5.7. La définition vit dans le
   modèle : le tableau de bord, le rapport de fin de campagne et son export
   lus depuis la même source ne peuvent pas diverger. */
$lignes = Evenement::INDICATEURS_MESURES;
?>

<div class="panneau">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('graphique') ?> Choisir une campagne</h2>
    </div>
    <div class="panneau__corps">
        <?php if ($campagnes === []): ?>
            <p class="texte-petit">Aucune campagne disponible pour le moment.</p>
        <?php else: ?>
            <form method="get" action="<?= e(url('admin/statistiques')) ?>" class="pile">
                <select class="liste" name="campagne" style="min-width:320px" onchange="this.form.submit()">
                    <?php foreach ($campagnes as $campagne): ?>
                        <option value="<?= e((string) $campagne['id']) ?>"<?= (int) $campagne['id'] === (int) $campagneId ? ' selected' : '' ?>>
                            <?= e((string) $campagne['nom']) ?> — <?= e((string) $campagne['annonceur_nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="btn btn--primaire btn--petit">Afficher</button></noscript>
            </form>
        <?php endif; ?>

        <?php if ((int) $campagneId > 0): ?>
            <!-- Rapport de fin de campagne : page imprimable et export
                 tableur (EF-F06). Le rapport est destiné à sortir du
                 back-office, il s’ouvre donc dans un onglet à part. -->
            <div class="pile" style="margin-top:14px">
                <a class="btn btn--primaire btn--petit" target="_blank" rel="noopener"
                   href="<?= e(url('admin/campagnes/' . (int) $campagneId . '/rapport')) ?>">
                    <?= Icone::svg('graphique') ?> Rapport de fin de campagne
                </a>
                <a class="btn btn--contour btn--petit"
                   href="<?= e(url('admin/campagnes/' . (int) $campagneId . '/rapport.csv')) ?>">
                    <?= Icone::svg('telecharger') ?> Export CSV
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($detail === null): ?>
    <div class="etat-vide">
        <?= Icone::svg('graphique') ?>
        <h3>Aucune campagne sélectionnée</h3>
        <p>Choisissez une campagne pour afficher ses indicateurs.</p>
    </div>
<?php else: ?>

    <div class="encart">
        <strong>Lecture des chiffres.</strong> Les valeurs mesurées proviennent des interactions réellement
        enregistrées. La portée estimée est calculée à partir du trafic déclaré sur les fiches panneaux :
        elle ne doit jamais être présentée comme une mesure.
    </div>

    <!-- Indicateurs mesurés --------------------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('coche-cercle') ?> Interactions mesurées</h2>
            <?= etiquette_nature('mesure') ?>
        </div>
        <div class="panneau__corps panneau__corps--sans-marge">
            <div class="tableau-enveloppe">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Indicateur</th>
                            <th class="tableau__numerique">Valeur</th>
                            <th>Part des scans</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $scans = max(1, (int) ($mesure['scans_bruts'] ?? 0)); ?>
                        <?php foreach ($lignes as $ligne): ?>
                            <?php $valeur = (int) ($mesure[$ligne['cle']] ?? 0); ?>
                            <tr>
                                <td>
                                    <span style="display:inline-flex;align-items:center;gap:9px">
                                        <span style="color:var(--lilas-700)"><?= Icone::svg($ligne['icone'], '', 17) ?></span>
                                        <?= e($ligne['libelle']) ?>
                                    </span>
                                </td>
                                <td class="tableau__numerique tableau__principal"><?= e(number_format($valeur, 0, ',', ' ')) ?></td>
                                <td style="min-width:180px">
                                    <?php if ($ligne['cle'] !== 'scans_bruts'): ?>
                                        <div class="pile">
                                            <span class="barre-progression" style="flex:1">
                                                <span class="barre-progression__remplissage" style="width:<?= e((string) min(100, round($valeur / $scans * 100))) ?>%"></span>
                                            </span>
                                            <span class="texte-petit" style="min-width:52px;text-align:right"><?= e(pourcent($valeur / $scans * 100, 1)) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="texte-petit">référence</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Portée estimée -------------------------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('oeil') ?> Portée estimée</h2>
            <?= etiquette_nature('estime') ?>
        </div>
        <div class="panneau__corps">
            <div class="indicateurs" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
                <div class="indicateur">
                    <div class="indicateur__etiquette">Panneaux</div>
                    <div class="indicateur__valeur"><?= e((string) ($estime['nb_panneaux'] ?? 0)) ?></div>
                    <div class="indicateur__detail">sur <?= e((string) ($estime['duree_jours'] ?? 0)) ?> jour(s)</div>
                </div>
                <div class="indicateur indicateur--or">
                    <div class="indicateur__etiquette">Passages cumulés</div>
                    <div class="indicateur__valeur"><?= e(number_format((float) ($estime['passages_cumules'] ?? 0), 0, ',', ' ')) ?></div>
                    <div class="indicateur__detail">trafic déclaré × durée</div>
                </div>
                <div class="indicateur indicateur--gris">
                    <div class="indicateur__etiquette">Scans attendus</div>
                    <div class="indicateur__valeur"><?= e(number_format((float) ($estime['scans_estimes'] ?? 0), 0, ',', ' ')) ?></div>
                    <div class="indicateur__detail">hypothèse prudente</div>
                </div>
            </div>

            <div class="encart encart--avertissement" style="margin-bottom:0">
                <strong>Méthode d’estimation.</strong> <?= e((string) ($estime['methode'] ?? '—')) ?>.
                Le trafic journalier retenu est de <?= e(number_format((float) ($estime['trafic_journalier'] ?? 0), 0, ',', ' ')) ?> passages.
                Source : <?= e((string) ($detail['declare']['source'] ?? '—')) ?>.
            </div>
        </div>
    </div>

    <!-- Série quotidienne ----------------------------------------------------- -->
    <?php if ($serie !== []): ?>
        <div class="panneau">
            <div class="panneau__entete">
                <h2 class="panneau__titre"><?= Icone::svg('calendrier') ?> Scans des 30 derniers jours</h2>
                <?= etiquette_nature('mesure') ?>
            </div>
            <div class="panneau__corps">
                <div style="display:flex;align-items:flex-end;gap:4px;height:150px;overflow-x:auto;padding-bottom:6px">
                    <?php foreach ($serie as $jour): ?>
                        <?php
                        $scansJour = (int) $jour['scans'];
                        $hauteur   = max(2, (int) round($scansJour / $maxSerie * 130));
                        ?>
                        <div style="flex:1;min-width:16px;display:flex;flex-direction:column;align-items:center;gap:5px"
                             title="<?= e(date_fr((string) $jour['jour'])) ?> : <?= e((string) $scansJour) ?> scan(s), <?= e((string) $jour['appels']) ?> appel(s)">
                            <div style="width:100%;height:<?= e((string) $hauteur) ?>px;background:var(--lilas);border-radius:3px 3px 0 0"></div>
                            <span style="font-size:.62rem;color:var(--gris-500);white-space:nowrap">
                                <?= e(date('d/m', (int) strtotime((string) $jour['jour']))) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px" class="grille-stats-bas">
    <!-- Répartition par appareil --------------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('telephone-mobile') ?> Appareils · 30 jours</h2>
            <?= etiquette_nature('mesure') ?>
        </div>
        <div class="panneau__corps">
            <?php if ($appareils === []): ?>
                <p class="texte-petit">Aucune visite enregistrée sur la période.</p>
            <?php else: ?>
                <?php $totalAppareils = max(1, array_sum(array_column($appareils, 'total'))); ?>
                <div class="liste-stats">
                    <?php foreach ($appareils as $appareil): ?>
                        <div class="liste-stats__ligne">
                            <span class="liste-stats__etiquette"><?= e((string) ($appareil['appareil'] ?? 'Inconnu')) ?></span>
                            <span class="liste-stats__valeur"><?= e((string) $appareil['total']) ?></span>
                            <span class="liste-stats__barre barre-progression">
                                <span class="barre-progression__remplissage" style="width:<?= e((string) round((int) $appareil['total'] / $totalAppareils * 100)) ?>%"></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Repères globaux ------------------------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('coche-cercle') ?> Repères du réseau</h2>
            <?= etiquette_nature('mesure') ?>
        </div>
        <div class="panneau__corps">
            <div class="liste-stats">
                <div class="liste-stats__ligne">
                    <span class="liste-stats__etiquette">Scans sur 7 jours</span>
                    <span class="liste-stats__valeur"><?= e(number_format((float) $globaux['scans_7j'], 0, ',', ' ')) ?></span>
                </div>
                <div class="liste-stats__ligne">
                    <span class="liste-stats__etiquette">Scans sur 30 jours</span>
                    <span class="liste-stats__valeur"><?= e(number_format((float) $globaux['scans_30j'], 0, ',', ' ')) ?></span>
                </div>
                <div class="liste-stats__ligne">
                    <span class="liste-stats__etiquette">Visiteurs uniques · 30 j</span>
                    <span class="liste-stats__valeur"><?= e(number_format((float) $globaux['visiteurs_30j'], 0, ',', ' ')) ?></span>
                </div>
                <div class="liste-stats__ligne">
                    <span class="liste-stats__etiquette">Contacts générés</span>
                    <span class="liste-stats__valeur"><?= e(number_format((float) $globaux['clics_contact'], 0, ',', ' ')) ?></span>
                </div>
                <div class="liste-stats__ligne">
                    <span class="liste-stats__etiquette">Abonnés actifs</span>
                    <span class="liste-stats__valeur"><?= e(number_format((float) $globaux['abonnements'], 0, ',', ' ')) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 1000px) { .grille-stats-bas { grid-template-columns: 1fr !important; } }
</style>
