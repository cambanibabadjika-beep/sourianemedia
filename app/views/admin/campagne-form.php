<?php
/**
 * Vue — Campagne : paramètres, réservation, QR codes et statistiques.
 *
 * Variables :
 *   $campagne     ?array
 *   $annonceurs   array
 *   $formules     array
 *   $panneaux     array
 *   $associes     array
 *   $disponibilites array<int,bool> — disponibilité par panneau sur la période
 *   $codes        array
 *   $stats        ?array
 *   $lectureSeule bool
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Icone;
use Souriane\Services\QrService;

$creation   = $campagne === null;
$action     = $creation ? url('admin/campagnes/nouveau') : url('admin/campagnes/' . $campagne['id']);
$disponibilites = $disponibilites ?? [];

$associesParPanneau = [];
foreach ($associes as $ligne) {
    $associesParPanneau[(int) $ligne['panneau_id']] = $ligne;
}

$peutReserver = !$creation
    && !$lectureSeule
    && in_array((string) $campagne['statut'], ['brouillon', 'reservee'], true);

$mesure = $stats['mesure'] ?? [];
?>

<?php if ($creation): ?>
    <div class="encart">
        <strong>Étape 1 sur 2.</strong> Enregistrez d’abord les paramètres de la campagne.
        Vous pourrez ensuite sélectionner les panneaux et valider la réservation.
    </div>
<?php endif; ?>

<!-- 1. Paramètres ----------------------------------------------------------- -->
<form method="post" action="<?= e($action) ?>">
    <?= Csrf::champ() ?>

    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('etincelle') ?> Paramètres de la campagne</h2>
            <?php if (!$creation): ?>
                <?= etiquette_etat((string) $campagne['statut']) ?>
            <?php endif; ?>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ">
                    <label class="champ__etiquette" for="annonceur_id">Annonceur <span class="requis">*</span></label>
                    <select class="liste" id="annonceur_id" name="annonceur_id" required<?= $lectureSeule ? ' disabled' : '' ?>>
                        <option value="">— Choisir —</option>
                        <?php foreach ($annonceurs as $annonceur): ?>
                            <option value="<?= e((string) $annonceur['id']) ?>"
                                <?= (int) ($campagne['annonceur_id'] ?? 0) === (int) $annonceur['id'] ? ' selected' : '' ?>>
                                <?= e((string) $annonceur['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="nom">Nom de la campagne <span class="requis">*</span></label>
                    <input class="saisie" type="text" id="nom" name="nom" required maxlength="180"
                           value="<?= e((string) ($campagne['nom'] ?? '')) ?>"
                           placeholder="ex. Rentrée des chantiers — outillage"<?= $lectureSeule ? ' readonly' : '' ?>>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="date_debut">Début <span class="requis">*</span></label>
                    <input class="saisie" type="date" id="date_debut" name="date_debut" required
                           value="<?= e((string) ($campagne['date_debut'] ?? date('Y-m-d'))) ?>"<?= $lectureSeule ? ' readonly' : '' ?>>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="date_fin">Fin <span class="requis">*</span></label>
                    <input class="saisie" type="date" id="date_fin" name="date_fin" required
                           value="<?= e((string) ($campagne['date_fin'] ?? date('Y-m-d', strtotime('+30 days')))) ?>"<?= $lectureSeule ? ' readonly' : '' ?>>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="formule_id">Formule tarifaire</label>
                    <select class="liste" id="formule_id" name="formule_id"<?= $lectureSeule ? ' disabled' : '' ?>>
                        <option value="">— Aucune —</option>
                        <?php foreach ($formules as $formule): ?>
                            <option value="<?= e((string) $formule['id']) ?>"
                                <?= (int) ($campagne['formule_id'] ?? 0) === (int) $formule['id'] ? ' selected' : '' ?>>
                                <?= e((string) $formule['nom']) ?> — <?= e(fcfa($formule['prix_base'])) ?>
                                / <?= e((string) $formule['duree_reference_jours']) ?> j
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="champ__aide">Le prix final est calculé à la réservation, selon la durée et le nombre de panneaux.</div>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="moyen_paiement">Moyen de paiement</label>
                    <select class="liste" id="moyen_paiement" name="moyen_paiement"<?= $lectureSeule ? ' disabled' : '' ?>>
                        <?php foreach (['aucun' => 'Non renseigné', 'wave' => 'Wave', 'orange_money' => 'Orange Money', 'especes' => 'Espèces', 'virement' => 'Virement'] as $cle => $libelle): ?>
                            <option value="<?= e($cle) ?>"<?= ($campagne['moyen_paiement'] ?? 'aucun') === $cle ? ' selected' : '' ?>>
                                <?= e($libelle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="reference_paiement">Référence de paiement</label>
                    <input class="saisie" type="text" id="reference_paiement" name="reference_paiement" maxlength="120"
                           value="<?= e((string) ($campagne['reference_paiement'] ?? '')) ?>"<?= $lectureSeule ? ' readonly' : '' ?>>
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="note">Note interne</label>
                    <textarea class="zone-texte" id="note" name="note" rows="2"<?= $lectureSeule ? ' readonly' : '' ?>><?= e((string) ($campagne['note'] ?? '')) ?></textarea>
                </div>
            </div>

            <?php if (!$creation): ?>
                <hr class="separateur">
                <div class="pile pile--entre">
                    <span class="texte-petit">
                        Créée le <?= e(date_fr((string) $campagne['cree_le'])) ?> ·
                        Formule : <?= e((string) ($campagne['formule_nom'] ?? 'aucune')) ?>
                    </span>
                    <span class="indicateur__valeur" style="font-size:1.3rem"><?= e(fcfa($campagne['prix'])) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$lectureSeule): ?>
        <div class="pile">
            <button type="submit" class="btn btn--primaire">
                <?= Icone::svg('valider') ?>
                <?= $creation ? 'Créer la campagne' : 'Enregistrer les paramètres' ?>
            </button>
            <a class="btn btn--contour" href="<?= e(url('admin/campagnes')) ?>">Annuler</a>
        </div>
    <?php endif; ?>
</form>

<?php if ($creation): ?>
    <?php return; ?>
<?php endif; ?>

<!-- 2. Réservation ---------------------------------------------------------- -->
<?php if ($peutReserver): ?>
    <form method="post" action="<?= e(url('admin/campagnes/' . $campagne['id'] . '/reserver')) ?>" class="ecart-haut--grand">
        <?= Csrf::champ() ?>

        <div class="panneau">
            <div class="panneau__entete">
                <h2 class="panneau__titre"><?= Icone::svg('panneau') ?> Choisir les panneaux</h2>
                <span class="texte-petit">
                    Période : <?= e(date_fr((string) $campagne['date_debut'])) ?> → <?= e(date_fr((string) $campagne['date_fin'])) ?>
                </span>
            </div>

            <?php if ($panneaux === []): ?>
                <div class="panneau__corps">
                    <p class="texte-petit">Aucun panneau référencé dans le parc.</p>
                </div>
            <?php else: ?>
                <div class="tableau-enveloppe">
                    <table class="tableau">
                        <thead>
                            <tr>
                                <th style="width:44px"></th>
                                <th>Référence</th>
                                <th>Emplacement</th>
                                <th>Format</th>
                                <th class="tableau__numerique">Trafic / j</th>
                                <th>Disponibilité sur la période</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($panneaux as $panneau): ?>
                                <?php
                                $id        = (int) $panneau['id'];
                                $associe   = isset($associesParPanneau[$id]);
                                $libre     = $disponibilites[$id] ?? true;
                                $selectionnable = $libre || $associe;
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="panneaux[]" value="<?= e((string) $id) ?>"
                                               id="panneau_<?= e((string) $id) ?>"
                                               style="width:18px;height:18px;accent-color:var(--lilas-700)"
                                               <?= $associe ? 'checked' : '' ?>
                                               <?= $selectionnable ? '' : 'disabled' ?>>
                                    </td>
                                    <td class="tableau__principal">
                                        <label for="panneau_<?= e((string) $id) ?>"><?= e((string) $panneau['reference']) ?></label>
                                        <?php if ($associe): ?>
                                            <div class="tableau__secondaire">déjà dans la campagne</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= e((string) $panneau['libelle']) ?>
                                        <div class="tableau__secondaire"><?= e((string) ($panneau['ville'] ?? '—')) ?></div>
                                    </td>
                                    <td><?= e((string) ($panneau['format'] ?? '—')) ?></td>
                                    <td class="tableau__numerique"><?= e(number_format((float) ($panneau['trafic_estime'] ?? 0), 0, ',', ' ')) ?></td>
                                    <td>
                                        <?php if ($associe): ?>
                                            <span class="etat etat--conforme">Réservé sur cette campagne</span>
                                        <?php elseif ($libre): ?>
                                            <span class="etat etat--disponible">Disponible</span>
                                        <?php else: ?>
                                            <span class="etat etat--annulee">Déjà réservé sur la période</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="panneau__corps">
                    <div class="encart" style="margin-bottom:16px">
                        <strong>Contrôle de disponibilité.</strong> Les panneaux marqués « déjà réservé » ne peuvent pas
                        être sélectionnés. La vérification est refaite au moment de l’enregistrement, avec verrouillage
                        de la ligne en base : la double réservation est impossible, même en cas de validation simultanée.
                    </div>

                    <button type="submit" class="btn btn--primaire">
                        <?= Icone::svg('calendrier') ?>
                        Valider la réservation et générer les QR codes
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>

<!-- 3. Panneaux associés ---------------------------------------------------- -->
<?php if ($associes !== []): ?>
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('panneau') ?> Panneaux réservés</h2>
            <span class="texte-petit"><?= e((string) count($associes)) ?> support(s)</span>
        </div>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Emplacement</th>
                        <th>Période</th>
                        <th class="tableau__numerique">Prix de la ligne</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($associes as $ligne): ?>
                        <tr>
                            <td class="tableau__principal"><?= e((string) $ligne['reference']) ?></td>
                            <td>
                                <?= e((string) $ligne['libelle']) ?>
                                <div class="tableau__secondaire"><?= e((string) ($ligne['ville'] ?? '—')) ?></div>
                            </td>
                            <td class="texte-petit">
                                <?= e(date_fr((string) $ligne['date_debut'])) ?> → <?= e(date_fr((string) $ligne['date_fin'])) ?>
                            </td>
                            <td class="tableau__numerique"><?= e(fcfa($ligne['prix_ligne'])) ?></td>
                            <td>
                                <?php if (!$lectureSeule): ?>
                                    <div class="tableau__actions">
                                        <form method="post" action="<?= e(url('admin/campagnes/' . $campagne['id'] . '/retirer')) ?>" style="margin:0"
                                              onsubmit="return confirm('Retirer ce panneau de la campagne ?');">
                                            <?= Csrf::champ() ?>
                                            <input type="hidden" name="panneau_id" value="<?= e((string) $ligne['panneau_id']) ?>">
                                            <button type="submit" class="btn btn--contour btn--petit" title="Retirer">
                                                <?= Icone::svg('supprimer') ?>
                                                <span class="sr-only">Retirer</span>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- 4. Preuves de pose & photos d'installation ------------------------------ -->
<?php if ($campagne !== null): ?>
    <div class="panneau" id="preuves-poses">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('photo') ?> Preuves de pose & photos terrain</h2>
            <div style="display:flex; align-items:center; gap:10px;">
                <span class="texte-petit"><?= e((string) count($poses)) ?> photo(s) terrain</span>
                <?php if (in_array(\Souriane\Core\Auth::role(), ['regie', 'agent'], true)): ?>
                    <button type="button" class="btn btn--primaire btn--petit" data-ouvrir-dialogue="dialogue-pose-campagne">
                        <?= Icone::svg('ajouter') ?> Ajouter une photo de pose
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="panneau__corps">
            <?php if ($poses === []): ?>
                <div class="encart" style="margin:0">
                    Aucune photo d’installation enregistrée pour cette campagne pour le moment.
                    Dès que nos équipes effectuent la pose des affiches sur le terrain, leurs photos horodatées
                    avec géolocalisation apparaîtront ici.
                </div>
            <?php else: ?>
                <div class="grille-poses-campagne">
                    <?php foreach ($poses as $p): ?>
                        <?php
                        $photoUrl = !empty($p['photo']) ? url_photo((string) $p['photo']) : '';
                        $gpsDispo = !empty($p['latitude']) && !empty($p['longitude']);
                        $lienGps = $gpsDispo
                            ? 'https://www.google.com/maps?q=' . rawurlencode($p['latitude'] . ',' . $p['longitude'])
                            : null;
                        ?>
                        <article class="carte-pose">
                            <div class="carte-pose__media"
                                 data-ouvrir-zoom-campagne
                                 data-photo="<?= e($photoUrl) ?>"
                                 data-panneau="<?= e((string) $p['reference'] . ' — ' . $p['libelle']) ?>"
                                 data-ville="<?= e((string) ($p['ville'] ?? '')) ?>"
                                 data-format="<?= e((string) ($p['format'] ?? '')) ?>"
                                 data-date="<?= e(date_fr((string) $p['horodatage'], true)) ?>"
                                 data-agent="<?= e((string) ($p['agent_nom'] ?? 'Équipe terrain')) ?>"
                                 data-lat="<?= e((string) ($p['latitude'] ?? '')) ?>"
                                 data-lng="<?= e((string) ($p['longitude'] ?? '')) ?>"
                                 data-note="<?= e((string) ($p['note'] ?? '')) ?>"
                                 data-c15="<?= e((string) $p['controle_15j']) ?>"
                                 data-c30="<?= e((string) $p['controle_30j']) ?>"
                                 title="Cliquer pour voir la photo en grand">
                                <?php if ($photoUrl !== ''): ?>
                                    <img src="<?= e($photoUrl) ?>" alt="Pose <?= e((string) $p['reference']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div style="display:flex; align-items:center; justify-content:center; height:100%; color:var(--gris-400);">
                                        <?= Icone::svg('photo') ?>
                                    </div>
                                <?php endif; ?>
                                <div class="carte-pose__badge-date">
                                    <?= e(date_fr((string) $p['horodatage'], true)) ?>
                                </div>
                            </div>
                            <div class="carte-pose__corps">
                                <h3 class="carte-pose__titre">
                                    <?= e((string) $p['reference']) ?> · <?= e((string) $p['libelle']) ?>
                                </h3>
                                <div class="carte-pose__meta">
                                    <span><?= e((string) ($p['format'] ?? '')) ?><?= !empty($p['ville']) ? ' · ' . e($p['ville']) : '' ?></span>
                                    <span>Posé par : <?= e((string) ($p['agent_nom'] ?? 'Équipe terrain')) ?></span>
                                    <?php if ($gpsDispo): ?>
                                        <span>
                                            <a href="<?= e($lienGps) ?>" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;">
                                                GPS : <?= e(number_format((float) $p['latitude'], 4, '.', '')) ?>, <?= e(number_format((float) $p['longitude'], 4, '.', '')) ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($p['note'])): ?>
                                    <div class="carte-pose__note">
                                        « <?= e((string) $p['note']) ?> »
                                    </div>
                                <?php endif; ?>
                                <div class="carte-pose__statuts">
                                    <span class="etat <?= $p['controle_15j'] === 'conforme' ? 'etat--conforme' : ($p['controle_15j'] === 'non_conforme' ? 'etat--annulee' : 'etat--reservee') ?>">
                                        15 j : <?= e($p['controle_15j'] === 'conforme' ? 'Conforme' : ($p['controle_15j'] === 'non_conforme' ? 'Non conforme' : 'À faire')) ?>
                                    </span>
                                    <span class="etat <?= $p['controle_30j'] === 'conforme' ? 'etat--conforme' : ($p['controle_30j'] === 'non_conforme' ? 'etat--annulee' : 'etat--reservee') ?>">
                                        30 j : <?= e($p['controle_30j'] === 'conforme' ? 'Conforme' : ($p['controle_30j'] === 'non_conforme' ? 'Non conforme' : 'À faire')) ?>
                                    </span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- 5. QR codes ------------------------------------------------------------- -->
<div class="panneau">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('qr') ?> QR codes de la campagne</h2>
        <span class="texte-petit">un code par couple campagne × panneau</span>
    </div>

    <?php if ($codes === []): ?>
        <div class="panneau__corps">
            <div class="encart" style="margin:0">
                Aucun code généré. Les codes sont créés automatiquement à la validation de la réservation.
            </div>
        </div>
    <?php else: ?>
        <div class="panneau__corps">
            <div class="grille-qr">
                <?php foreach ($codes as $code): ?>
                    <div class="carte-qr">
                        <div class="carte-qr__code"><?= e(strtoupper((string) $code['code'])) ?></div>
                        <div class="carte-qr__image">
                            <?= QrService::svg(url_courte((string) $code['code']), 'H', 4, 4, '#4C1D95') ?>
                        </div>
                        <div class="carte-qr__pied">
                            Panneau <?= e((string) $code['panneau_reference']) ?> · <?= e((string) ($code['ville'] ?? '')) ?>
                        </div>
                        <div class="carte-qr__secours"><?= e((string) ($code['code_secours'] ?? '')) ?></div>
                        <div class="carte-qr__pied">
                            <?= e((string) $code['scans']) ?> scan(s) · <?= e((string) $code['scans_session']) ?> visiteur(s)
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="texte-petit ecart-haut">
                Niveau de correction d’erreur H (30 % de redondance), marge de silence de 4 modules.
                Le code de secours imprimé sous le QR permet une saisie manuelle si la lecture échoue.
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- 6. Statistiques --------------------------------------------------------- -->
<?php if ($stats !== null): ?>
    <?php $mesure = $stats; ?>
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('graphique') ?> Résultats de la campagne</h2>
            <?= etiquette_nature('mesure') ?>
        </div>
        <div class="panneau__corps">
            <div class="indicateurs" style="margin-bottom:0">
                <div class="indicateur">
                    <div class="indicateur__etiquette">Scans bruts</div>
                    <div class="indicateur__valeur"><?= e(number_format((float) ($mesure['scans_bruts'] ?? 0), 0, ',', ' ')) ?></div>
                </div>
                <div class="indicateur indicateur--lilas">
                    <div class="indicateur__etiquette">Visiteurs uniques</div>
                    <div class="indicateur__valeur"><?= e(number_format((float) ($mesure['visiteurs_uniques'] ?? 0), 0, ',', ' ')) ?></div>
                </div>
                <div class="indicateur indicateur--or">
                    <div class="indicateur__etiquette">Appels</div>
                    <div class="indicateur__valeur"><?= e(number_format((float) ($mesure['clics_appel'] ?? 0), 0, ',', ' ')) ?></div>
                </div>
                <div class="indicateur indicateur--gris">
                    <div class="indicateur__etiquette">WhatsApp</div>
                    <div class="indicateur__valeur"><?= e(number_format((float) ($mesure['clics_whatsapp'] ?? 0), 0, ',', ' ')) ?></div>
                </div>
            </div>

            <a class="btn btn--contour ecart-haut" href="<?= e(url('admin/statistiques?campagne=' . $campagne['id'])) ?>">
                <?= Icone::svg('graphique') ?>
                Analyse détaillée
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Dialogue d'agrandissement photo de la campagne --------------------------- -->
<dialog id="dialogue-zoom-campagne" class="dialogue-zoom">
    <div style="position:relative; background:#0b0716; text-align:center;">
        <img id="zoom-img-campagne" class="dialogue-zoom__image" src="" alt="Preuve de pose">
        <button type="button" class="btn btn--contour btn--petit" data-fermer-dialogue
                style="position:absolute; top:12px; right:12px; background:rgba(0,0,0,.65); color:#fff; border-color:rgba(255,255,255,.3); z-index:2">
            ✕ Fermer
        </button>
    </div>
    <div class="dialogue-zoom__corps">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
            <div>
                <h3 id="zoom-panneau-campagne" style="margin:0 0 4px; font-family:var(--police-titre); font-size:1.15rem; color:var(--charbon);"></h3>
                <div id="zoom-meta-campagne" class="texte-petit" style="color:var(--gris-600);"></div>
            </div>
            <div id="zoom-statuts-campagne" class="carte-pose__statuts"></div>
        </div>

        <div id="zoom-note-conteneur-campagne" style="display:none;" class="carte-pose__note">
            <strong>Rapport d’installation :</strong> <span id="zoom-note-campagne"></span>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; padding-top:10px; border-top:1px solid var(--bordure); flex-wrap:wrap; gap:12px;">
            <div id="zoom-gps-campagne" class="texte-petit monospace"></div>
            <a id="zoom-lien-carte-campagne" href="#" target="_blank" rel="noopener noreferrer" class="btn btn--contour btn--petit">
                <?= Icone::svg('site') ?> Voir la localisation satellite
            </a>
        </div>
    </div>
</dialog>

<!-- Dialogue d'enregistrement d'une pose depuis la fiche campagne ----------- -->
<?php if ($campagne !== null && in_array(\Souriane\Core\Auth::role(), ['regie', 'agent'], true)): ?>
<dialog id="dialogue-pose-campagne" style="border:0; border-radius:var(--rayon); padding:0; max-width:540px; width:calc(100% - 32px); box-shadow:var(--ombre-lg); background:var(--surface);">
    <form method="post" action="<?= e(url('admin/poses')) ?>" enctype="multipart/form-data" style="padding:24px">
        <?= Csrf::champ() ?>
        <input type="hidden" name="campagne_id" value="<?= e((string) $campagne['id']) ?>">
        <input type="hidden" name="retour" value="<?= e(url('admin/campagnes/' . $campagne['id'] . '#preuves-poses')) ?>">

        <h2 class="panneau__titre" style="margin-bottom:18px"><?= Icone::svg('photo') ?> Ajouter une preuve de pose</h2>

        <div class="champ">
            <label class="champ__etiquette" for="pose_campagne_panneau">Panneau de la campagne <span class="requis">*</span></label>
            <select class="liste" id="pose_campagne_panneau" name="panneau_id" required>
                <option value="">— Choisir le panneau —</option>
                <?php
                $panneauxOptions = $associes !== [] ? $associes : $panneaux;
                foreach ($panneauxOptions as $pOption):
                    $pId = (int) ($pOption['panneau_id'] ?? $pOption['id']);
                ?>
                    <option value="<?= e((string) $pId) ?>">
                        <?= e((string) $pOption['reference']) ?> — <?= e((string) $pOption['libelle']) ?> (<?= e((string) ($pOption['ville'] ?? '')) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="pose_campagne_photo_fichier">
                Photo prise sur le terrain <span class="requis">*</span>
            </label>
            <input class="saisie" type="file" id="pose_campagne_photo_fichier" name="photo_fichier"
                   accept="image/jpeg,image/png,image/webp" capture="environment">
            <div class="champ__aide">Photo nette de l'affiche posée sur le panneau avec le QR code visible (max 12 Mo).</div>
            <div id="zone-apercu-campagne" style="display:none; margin-top:10px;">
                <img id="apercu-img-campagne" src="" alt="Aperçu photo"
                     style="max-width:100%; max-height:200px; border-radius:var(--rayon-sm); object-fit:cover; border:1px solid var(--bordure); display:block;">
            </div>
        </div>

        <details style="margin-bottom:14px; font-size:.85rem;">
            <summary style="cursor:pointer; color:var(--lilas-700); padding:4px 0;">Ou renseigner un chemin existant</summary>
            <div class="champ" style="margin-top:8px;">
                <input class="saisie" type="text" id="pose_campagne_photo" name="photo" placeholder="storage/uploads/... ou https://...">
            </div>
        </details>

        <div class="champ">
            <label class="champ__etiquette" for="pose_campagne_horodatage">Date et heure d'installation</label>
            <input class="saisie" type="datetime-local" id="pose_campagne_horodatage" name="horodatage" value="<?= date('Y-m-d\TH:i') ?>">
        </div>

        <div class="grille-champs">
            <div class="champ">
                <label class="champ__etiquette" for="pose_campagne_lat">Latitude</label>
                <input class="saisie" type="text" id="pose_campagne_lat" name="latitude" inputmode="decimal" placeholder="ex: 12.8150">
            </div>
            <div class="champ">
                <label class="champ__etiquette" for="pose_campagne_lng">Longitude</label>
                <input class="saisie" type="text" id="pose_campagne_lng" name="longitude" inputmode="decimal" placeholder="ex: -16.2280">
            </div>
        </div>

        <div style="margin-top:-6px; margin-bottom:16px;">
            <button type="button" class="btn btn--contour btn--petit" id="btn-geoloc-campagne">
                <?= Icone::svg('gps') ?> Géolocaliser ma position actuelle
            </button>
            <div id="statut-geoloc-campagne" class="texte-petit" style="margin-top:6px; display:none;"></div>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="pose_campagne_note">Note de terrain</label>
            <textarea class="zone-texte" id="pose_campagne_note" name="note" rows="2"
                      placeholder="Pose conforme, QR testé sous ensoleillement direct."></textarea>
        </div>

        <div class="pile pile--droite">
            <button type="button" class="btn btn--contour" data-fermer-dialogue>Annuler</button>
            <button type="submit" class="btn btn--primaire"><?= Icone::svg('valider') ?> Enregistrer la preuve</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script>
(function () {
    // Gestion ouverture / fermeture des dialogues
    var ouvreurs = document.querySelectorAll('[data-ouvrir-dialogue]');
    for (var i = 0; i < ouvreurs.length; i++) {
        ouvreurs[i].addEventListener('click', function () {
            var cible = document.getElementById(this.getAttribute('data-ouvrir-dialogue'));
            if (cible && cible.showModal) { cible.showModal(); }
        });
    }
    var fermeurs = document.querySelectorAll('[data-fermer-dialogue]');
    for (var j = 0; j < fermeurs.length; j++) {
        fermeurs[j].addEventListener('click', function () {
            var d = this.closest('dialog');
            if (d) { d.close(); }
        });
    }

    // Zoom Photo dans la fiche campagne
    var zoomDialog = document.getElementById('dialogue-zoom-campagne');
    var zoomImg = document.getElementById('zoom-img-campagne');
    var zoomPanneau = document.getElementById('zoom-panneau-campagne');
    var zoomMeta = document.getElementById('zoom-meta-campagne');
    var zoomStatuts = document.getElementById('zoom-statuts-campagne');
    var zoomNoteCont = document.getElementById('zoom-note-conteneur-campagne');
    var zoomNote = document.getElementById('zoom-note-campagne');
    var zoomGps = document.getElementById('zoom-gps-campagne');
    var zoomLienCarte = document.getElementById('zoom-lien-carte-campagne');

    var cartesZoom = document.querySelectorAll('[data-ouvrir-zoom-campagne]');
    for (var k = 0; k < cartesZoom.length; k++) {
        cartesZoom[k].addEventListener('click', function () {
            var item = this;
            var photo = item.getAttribute('data-photo');
            var panneau = item.getAttribute('data-panneau');
            var format = item.getAttribute('data-format');
            var ville = item.getAttribute('data-ville');
            var date = item.getAttribute('data-date');
            var agent = item.getAttribute('data-agent');
            var lat = item.getAttribute('data-lat');
            var lng = item.getAttribute('data-lng');
            var note = item.getAttribute('data-note');
            var c15 = item.getAttribute('data-c15');
            var c30 = item.getAttribute('data-c30');

            zoomImg.src = photo;
            zoomPanneau.textContent = panneau;

            var metaText = 'Posé le ' + date + ' · Par : ' + agent;
            if (format || ville) {
                metaText += ' · ' + [format, ville].filter(Boolean).join(' - ');
            }
            zoomMeta.textContent = metaText;

            if (note) {
                zoomNote.textContent = note;
                zoomNoteCont.style.display = 'block';
            } else {
                zoomNoteCont.style.display = 'none';
            }

            if (lat && lng) {
                zoomGps.textContent = 'GPS : ' + parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5);
                zoomLienCarte.href = 'https://www.google.com/maps?q=' + encodeURIComponent(lat + ',' + lng);
                zoomLienCarte.style.display = 'inline-flex';
            } else {
                zoomGps.textContent = 'Coordonnées GPS non renseignées';
                zoomLienCarte.style.display = 'none';
            }

            var htmlStatuts = '';
            var libelles = { non_fait: 'À faire', conforme: 'Conforme', non_conforme: 'Non conforme' };
            var classes = { non_fait: 'etat--reservee', conforme: 'etat--conforme', non_conforme: 'etat--annulee' };
            if (c15) {
                htmlStatuts += '<span class="etat ' + (classes[c15] || '') + '">15 j : ' + (libelles[c15] || c15) + '</span>';
            }
            if (c30) {
                htmlStatuts += '<span class="etat ' + (classes[c30] || '') + '">30 j : ' + (libelles[c30] || c30) + '</span>';
            }
            zoomStatuts.innerHTML = htmlStatuts;

            if (zoomDialog && zoomDialog.showModal) {
                zoomDialog.showModal();
            }
        });
    }

    // Aperçu photo téléversée
    var inputFichier = document.getElementById('pose_campagne_photo_fichier');
    var zoneApercu = document.getElementById('zone-apercu-campagne');
    var apercuImg = document.getElementById('apercu-img-campagne');
    if (inputFichier && zoneApercu && apercuImg) {
        inputFichier.addEventListener('change', function () {
            var fichier = this.files && this.files[0];
            if (fichier) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    apercuImg.src = e.target.result;
                    zoneApercu.style.display = 'block';
                };
                reader.readAsDataURL(fichier);
            } else {
                zoneApercu.style.display = 'none';
            }
        });
    }

    // Géolocalisation
    var btnGeoloc = document.getElementById('btn-geoloc-campagne');
    var statutGeoloc = document.getElementById('statut-geoloc-campagne');
    var champLat = document.getElementById('pose_campagne_lat');
    var champLng = document.getElementById('pose_campagne_lng');
    if (btnGeoloc && statutGeoloc && champLat && champLng) {
        btnGeoloc.addEventListener('click', function () {
            if (!navigator.geolocation) {
                alert('La géolocalisation n’est pas supportée par votre navigateur.');
                return;
            }
            statutGeoloc.style.display = 'block';
            statutGeoloc.style.color = 'var(--texte-discret)';
            statutGeoloc.textContent = 'Acquisition de votre position satellite GPS...';

            navigator.geolocation.getCurrentPosition(function (pos) {
                champLat.value = pos.coords.latitude.toFixed(6);
                champLng.value = pos.coords.longitude.toFixed(6);
                statutGeoloc.textContent = '✓ Position capturée (précision ~' + Math.round(pos.coords.accuracy) + ' m)';
                statutGeoloc.style.color = 'var(--vert-600)';
            }, function (err) {
                statutGeoloc.textContent = 'Impossible de récupérer la position : ' + err.message;
                statutGeoloc.style.color = 'var(--rouge-600)';
            }, { enableHighAccuracy: true, timeout: 10000 });
        });
    }
})();
</script>
