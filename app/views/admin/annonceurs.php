<?php
/**
 * Vue — Liste des annonceurs.
 *
 * Variables : $annonceurs, $recherche, $statut
 */

declare(strict_types=1);

use Souriane\Core\Icone;

?>

<div class="panneau">
    <form class="filtres" method="get" action="<?= e(url('admin/annonceurs')) ?>">
        <div class="champ filtres__recherche">
            <label class="champ__etiquette" for="q">Recherche</label>
            <input class="saisie" type="search" id="q" name="q" value="<?= e($recherche) ?>" placeholder="Nom, ville ou adresse courte">
        </div>
        <div class="champ">
            <label class="champ__etiquette" for="statut">Statut</label>
            <select class="liste" id="statut" name="statut">
                <option value="">Tous</option>
                <?php foreach (['actif', 'inactif', 'archive'] as $valeur): ?>
                    <option value="<?= e($valeur) ?>"<?= $statut === $valeur ? ' selected' : '' ?>>
                        <?= e(ETATS_LIBELLES[$valeur]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn--primaire btn--petit"><?= Icone::svg('recherche') ?> Filtrer</button>
        <?php if ($recherche !== '' || $statut !== ''): ?>
            <a class="btn btn--contour btn--petit" href="<?= e(url('admin/annonceurs')) ?>">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <?php if ($annonceurs === []): ?>
        <div class="panneau__corps">
            <div class="etat-vide">
                <?= Icone::svg('utilisateurs') ?>
                <h3>Aucun annonceur</h3>
                <p>Créez un premier annonceur pour lui associer une page publique.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Annonceur</th>
                        <th>Ville</th>
                        <th>Contact</th>
                        <th class="tableau__numerique">Campagnes</th>
                        <th class="tableau__numerique">Abonnés</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($annonceurs as $annonceur): ?>
                        <tr>
                            <td>
                                <a class="tableau__principal" href="<?= e(url('admin/annonceurs/' . $annonceur['id'])) ?>">
                                    <?= e((string) $annonceur['nom']) ?>
                                </a>
                                <div class="tableau__secondaire monospace">/<?= e((string) $annonceur['adresse_courte']) ?></div>
                            </td>
                            <td><?= e((string) ($annonceur['ville'] ?? '—')) ?></td>
                            <td class="texte-petit">
                                <?php if (!empty($annonceur['telephone'])): ?>
                                    <?= e((string) $annonceur['telephone']) ?><br>
                                <?php endif; ?>
                                <?= e((string) ($annonceur['email'] ?? '—')) ?>
                            </td>
                            <td class="tableau__numerique"><?= e((string) $annonceur['nb_campagnes']) ?></td>
                            <td class="tableau__numerique"><?= e((string) $annonceur['nb_abonnes']) ?></td>
                            <td><?= etiquette_etat((string) $annonceur['statut']) ?></td>
                            <td>
                                <div class="tableau__actions">
                                    <?php if (($annonceur['statut'] ?? '') === 'actif'): ?>
                                        <a class="btn btn--contour btn--petit" target="_blank" rel="noopener"
                                           href="<?= e(url_page((string) $annonceur['adresse_courte'])) ?>"
                                           title="Voir la page publique">
                                            <?= Icone::svg('lien-externe') ?>
                                            <span class="sr-only">Page publique</span>
                                        </a>
                                    <?php endif; ?>
                                    <a class="btn btn--contour btn--petit" href="<?= e(url('admin/annonceurs/' . $annonceur['id'])) ?>">
                                        <?= Icone::svg('modifier') ?>
                                        <span class="sr-only">Modifier</span>
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
