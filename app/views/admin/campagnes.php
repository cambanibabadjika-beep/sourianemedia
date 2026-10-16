<?php
/**
 * Vue — Liste des campagnes.
 *
 * Variables : $campagnes, $statuts, $recherche, $statut, $lectureSeule
 */

declare(strict_types=1);

use Souriane\Core\Icone;

?>

<div class="panneau">
    <form class="filtres" method="get" action="<?= e(url('admin/campagnes')) ?>">
        <div class="champ filtres__recherche">
            <label class="champ__etiquette" for="q">Recherche</label>
            <input class="saisie" type="search" id="q" name="q" value="<?= e($recherche) ?>" placeholder="Nom de campagne ou d’annonceur">
        </div>
        <div class="champ">
            <label class="champ__etiquette" for="statut">Statut</label>
            <select class="liste" id="statut" name="statut">
                <option value="">Tous</option>
                <?php foreach (\Souriane\Models\Campagne::STATUTS as $valeur): ?>
                    <option value="<?= e($valeur) ?>"<?= $statut === $valeur ? ' selected' : '' ?>>
                        <?= e(ETATS_LIBELLES[$valeur] ?? $valeur) ?> (<?= e((string) ($statuts[$valeur] ?? 0)) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn--primaire btn--petit"><?= Icone::svg('recherche') ?> Filtrer</button>
        <?php if ($recherche !== '' || $statut !== ''): ?>
            <a class="btn btn--contour btn--petit" href="<?= e(url('admin/campagnes')) ?>">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <?php if ($campagnes === []): ?>
        <div class="panneau__corps">
            <div class="etat-vide">
                <?= Icone::svg('etincelle') ?>
                <h3>Aucune campagne</h3>
                <p><?= $lectureSeule ? 'Aucune campagne n’est encore associée à votre compte.' : 'Créez une campagne pour réserver des panneaux.' ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Campagne</th>
                        <th>Période</th>
                        <th class="tableau__numerique">Panneaux</th>
                        <th class="tableau__numerique">Codes</th>
                        <th class="tableau__numerique">Prix</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campagnes as $campagne): ?>
                        <tr>
                            <td>
                                <a class="tableau__principal" href="<?= e(url('admin/campagnes/' . $campagne['id'])) ?>">
                                    <?= e((string) $campagne['nom']) ?>
                                </a>
                                <div class="tableau__secondaire"><?= e((string) $campagne['annonceur_nom']) ?></div>
                            </td>
                            <td class="texte-petit">
                                <?= e(date_fr((string) $campagne['date_debut'])) ?><br>
                                <?= e(date_fr((string) $campagne['date_fin'])) ?>
                            </td>
                            <td class="tableau__numerique"><?= e((string) $campagne['nb_panneaux']) ?></td>
                            <td class="tableau__numerique"><?= e((string) $campagne['nb_codes']) ?></td>
                            <td class="tableau__numerique"><?= e(fcfa($campagne['prix'])) ?></td>
                            <td><?= etiquette_etat((string) $campagne['statut']) ?></td>
                            <td>
                                <div class="tableau__actions">
                                    <a class="btn btn--contour btn--petit" href="<?= e(url('admin/campagnes/' . $campagne['id'])) ?>">
                                        <?= Icone::svg('suivant') ?>
                                        <span class="sr-only">Ouvrir</span>
                                    </a>
                                    <!-- Rapport de fin de campagne (EF-F06) -->
                                    <a class="btn btn--contour btn--petit" target="_blank" rel="noopener"
                                       href="<?= e(url('admin/campagnes/' . $campagne['id'] . '/rapport')) ?>">
                                        <?= Icone::svg('graphique') ?>
                                        <span class="sr-only">Rapport de fin de campagne</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!$lectureSeule): ?>
    <div class="encart">
        <strong>Réservation et anti-double réservation.</strong> Un panneau ne peut être réservé que sur une
        seule campagne à la fois pour une période donnée. La vérification est faite au moment de la validation,
        avec verrouillage de la ligne en base : deux réservations simultanées ne peuvent pas aboutir sur le même support.
    </div>
<?php endif; ?>
