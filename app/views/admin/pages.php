<?php
/**
 * Vue — Liste des pages publiques.
 *
 * Variables : $pages
 */

declare(strict_types=1);

use Souriane\Core\Icone;

?>

<div class="panneau">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('site') ?> Pages publiées</h2>
        <span class="texte-petit"><?= e((string) count($pages)) ?> page(s)</span>
    </div>

    <?php if ($pages === []): ?>
        <div class="panneau__corps">
            <div class="etat-vide">
                <?= Icone::svg('site') ?>
                <h3>Aucune page publique</h3>
                <p>Les pages sont créées automatiquement lors de l’enregistrement d’un annonceur.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Annonceur</th>
                        <th>Titre de la page</th>
                        <th>Adresse</th>
                        <th class="tableau__numerique">Sections actives</th>
                        <th>Statut</th>
                        <th>Mise à jour</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td class="tableau__principal"><?= e((string) $page['annonceur_nom']) ?></td>
                            <td><?= e((string) ($page['titre'] ?? '—')) ?></td>
                            <td class="monospace texte-petit">/<?= e((string) $page['adresse_courte']) ?></td>
                            <td class="tableau__numerique"><?= e((string) $page['nb_sections']) ?></td>
                            <td><?= etiquette_etat((string) $page['statut']) ?></td>
                            <td class="texte-petit"><?= e(date_fr((string) $page['maj_le'])) ?></td>
                            <td>
                                <div class="tableau__actions">
                                    <?php if (($page['statut'] ?? '') === 'publiee'): ?>
                                        <a class="btn btn--contour btn--petit" target="_blank" rel="noopener"
                                           href="<?= e(url_page((string) $page['adresse_courte'])) ?>" title="Voir la page">
                                            <?= Icone::svg('lien-externe') ?>
                                            <span class="sr-only">Voir</span>
                                        </a>
                                    <?php endif; ?>
                                    <a class="btn btn--contour btn--petit" href="<?= e(url('admin/pages/' . $page['id'])) ?>">
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

<div class="encart">
    <strong>Ordre d’affichage imposé.</strong> Les sections d’une page publique sont toujours présentées dans
    le même ordre, quelle que soit la façon dont elles ont été saisies : offre, présentation, informations
    complémentaires, galerie, adresse, horaires, réseaux sociaux, puis bloc « Rester informé » et mentions légales.
    Le passant doit comprendre l’offre en moins de trois secondes.
</div>
