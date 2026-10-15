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

<!-- 4. QR codes ------------------------------------------------------------- -->
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

<!-- 5. Statistiques --------------------------------------------------------- -->
<?php if ($stats !== null): ?>
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
