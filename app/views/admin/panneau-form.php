<?php
/**
 * Vue — Fiche panneau : identité, disponibilité, preuves de pose.
 *
 * Variables :
 *   $panneau     ?array
 *   $calendrier  array — réservations à venir
 *   $poses       array — preuves de pose
 *   $lectureSeule bool
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Icone;

$creation = $panneau === null;
$action   = $creation ? url('admin/panneaux/nouveau') : url('admin/panneaux/' . $panneau['id']);
$lectureSeule = $lectureSeule ?? false;
?>

<form method="post" action="<?= e($action) ?>">
    <?= Csrf::champ() ?>

    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('panneau') ?> Identité du support</h2>
            <?php if (!$creation): ?>
                <?= etiquette_etat((string) $panneau['statut']) ?>
            <?php endif; ?>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ">
                    <label class="champ__etiquette" for="reference">Référence <span class="requis">*</span></label>
                    <input class="saisie" type="text" id="reference" name="reference" required maxlength="60"
                           value="<?= e((string) ($panneau['reference'] ?? '')) ?>" placeholder="PNB-001"<?= $lectureSeule ? ' readonly' : '' ?>>
                    <div class="champ__aide">Identifiant unique du support, en majuscules.</div>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="libelle">Libellé <span class="requis">*</span></label>
                    <input class="saisie" type="text" id="libelle" name="libelle" required maxlength="180"
                           value="<?= e((string) ($panneau['libelle'] ?? '')) ?>" placeholder="Entrée nord de Bignona"<?= $lectureSeule ? ' readonly' : '' ?>>
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="adresse">Adresse ou repère</label>
                    <input class="saisie" type="text" id="adresse" name="adresse" maxlength="255"
                           value="<?= e((string) ($panneau['adresse'] ?? '')) ?>"<?= $lectureSeule ? ' readonly' : '' ?>>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="ville">Ville</label>
                    <input class="saisie" type="text" id="ville" name="ville" maxlength="120"
                           value="<?= e((string) ($panneau['ville'] ?? '')) ?>"<?= $lectureSeule ? ' readonly' : '' ?>>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="format">Format</label>
                    <input class="saisie" type="text" id="format" name="format" maxlength="50" list="formats-connus"
                           value="<?= e((string) ($panneau['format'] ?? '')) ?>" placeholder="4x3, 8x3, abribus…"<?= $lectureSeule ? ' readonly' : '' ?>>
                    <datalist id="formats-connus">
                        <?php foreach (['4x3', '6x3', '8x3', '12x4', 'abribus', 'mobilier urbain'] as $format): ?>
                            <option value="<?= e($format) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="faces">Nombre de faces</label>
                    <input class="saisie" type="number" id="faces" name="faces" min="1" max="4"
                           value="<?= e((string) ($panneau['faces'] ?? 1)) ?>"<?= $lectureSeule ? ' readonly' : '' ?>>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="trafic_estime">Trafic estimé par jour</label>
                    <input class="saisie" type="number" id="trafic_estime" name="trafic_estime" min="0" step="100"
                           value="<?= e((string) ($panneau['trafic_estime'] ?? '')) ?>"<?= $lectureSeule ? ' readonly' : '' ?>>
                    <div class="champ__aide">Donnée déclarée, utilisée pour la portée estimée.</div>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="tarif">Tarif de référence (FCFA)</label>
                    <input class="saisie" type="number" id="tarif" name="tarif" min="0" step="1000"
                           value="<?= e((string) ($panneau['tarif'] ?? '')) ?>"<?= $lectureSeule ? ' readonly' : '' ?>>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="latitude">Latitude</label>
                    <input class="saisie" type="text" id="latitude" name="latitude" inputmode="decimal"
                           value="<?= e((string) ($panneau['latitude'] ?? '')) ?>"<?= $lectureSeule ? ' readonly' : '' ?>>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="longitude">Longitude</label>
                    <input class="saisie" type="text" id="longitude" name="longitude" inputmode="decimal"
                           value="<?= e((string) ($panneau['longitude'] ?? '')) ?>"<?= $lectureSeule ? ' readonly' : '' ?>>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="statut">Statut d’exploitation</label>
                    <select class="liste" id="statut" name="statut"<?= $lectureSeule ? ' disabled' : '' ?>>
                        <?php foreach (['disponible', 'reserve', 'occupe', 'maintenance', 'archive'] as $valeur): ?>
                            <option value="<?= e($valeur) ?>"<?= ($panneau['statut'] ?? 'disponible') === $valeur ? ' selected' : '' ?>>
                                <?= e(ETATS_LIBELLES[$valeur]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$lectureSeule): ?>
        <div class="pile">
            <button type="submit" class="btn btn--primaire">
                <?= Icone::svg('valider') ?>
                <?= $creation ? 'Créer le panneau' : 'Enregistrer' ?>
            </button>
            <a class="btn btn--contour" href="<?= e(url('admin/panneaux')) ?>">Annuler</a>
        </div>
    <?php endif; ?>
</form>

<?php if ($creation) { return; } ?>

<!-- Disponibilité ----------------------------------------------------------- -->
<div class="panneau ecart-haut--grand">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('calendrier') ?> Réservations à venir</h2>
        <span class="texte-petit">8 mois</span>
    </div>

    <?php if ($calendrier === []): ?>
        <div class="panneau__corps">
            <div class="etat-vide" style="padding:34px 20px">
                <?= Icone::svg('coche-cercle') ?>
                <h3>Support entièrement libre</h3>
                <p>Aucune réservation sur les huit prochains mois.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Campagne</th>
                        <th>Annonceur</th>
                        <th>Du</th>
                        <th>Au</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calendrier as $ligne): ?>
                        <tr>
                            <td class="tableau__principal"><?= e((string) $ligne['campagne']) ?></td>
                            <td><?= e((string) $ligne['annonceur']) ?></td>
                            <td><?= e(date_fr((string) $ligne['date_debut'])) ?></td>
                            <td><?= e(date_fr((string) $ligne['date_fin'])) ?></td>
                            <td><?= etiquette_etat((string) $ligne['statut']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Preuves de pose --------------------------------------------------------- -->
<div class="panneau">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('photo') ?> Preuves de pose et contrôles</h2>
        <span class="texte-petit"><?= e((string) count($poses)) ?> pose(s)</span>
    </div>

    <?php if ($poses === []): ?>
        <div class="panneau__corps">
            <p class="texte-petit">Aucune preuve de pose enregistrée pour ce support.</p>
        </div>
    <?php else: ?>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Campagne</th>
                        <th>Horodatage</th>
                        <th>Agent</th>
                        <th>Contrôle 15 j</th>
                        <th>Contrôle 30 j</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($poses as $pose): ?>
                        <tr>
                            <td class="tableau__principal"><?= e((string) $pose['campagne_nom']) ?></td>
                            <td class="texte-petit"><?= e(date_fr((string) $pose['horodatage'], true)) ?></td>
                            <td><?= e((string) ($pose['agent_nom'] ?? '—')) ?></td>
                            <td><?= etiquette_etat((string) $pose['controle_15j']) ?></td>
                            <td><?= etiquette_etat((string) $pose['controle_30j']) ?></td>
                            <td class="texte-petit"><?= e((string) ($pose['note'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
