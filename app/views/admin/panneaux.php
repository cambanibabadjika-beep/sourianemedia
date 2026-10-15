<?php
/**
 * Vue — Parc de panneaux (administration).
 *
 * Variables : $panneaux, $villes, $formats, $recherche, $statut, $ville
 */

declare(strict_types=1);

use Souriane\Core\Auth;
use Souriane\Core\Icone;

$estRegie = Auth::aRole('regie');
?>

<div class="panneau">
    <form class="filtres" method="get" action="<?= e(url('admin/panneaux')) ?>">
        <div class="champ filtres__recherche">
            <label class="champ__etiquette" for="q">Recherche</label>
            <input class="saisie" type="search" id="q" name="q" value="<?= e($recherche) ?>" placeholder="Référence, libellé ou adresse">
        </div>
        <div class="champ">
            <label class="champ__etiquette" for="ville">Ville</label>
            <select class="liste" id="ville" name="ville">
                <option value="">Toutes</option>
                <?php foreach ($villes as $v): ?>
                    <option value="<?= e((string) $v) ?>"<?= $ville === (string) $v ? ' selected' : '' ?>><?= e((string) $v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ">
            <label class="champ__etiquette" for="statut">Statut</label>
            <select class="liste" id="statut" name="statut">
                <option value="">Tous</option>
                <?php foreach (['disponible', 'reserve', 'occupe', 'maintenance', 'archive'] as $valeur): ?>
                    <option value="<?= e($valeur) ?>"<?= $statut === $valeur ? ' selected' : '' ?>>
                        <?= e(ETATS_LIBELLES[$valeur]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn--primaire btn--petit"><?= Icone::svg('recherche') ?> Filtrer</button>
        <?php if ($recherche !== '' || $statut !== '' || $ville !== ''): ?>
            <a class="btn btn--contour btn--petit" href="<?= e(url('admin/panneaux')) ?>">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <?php if ($panneaux === []): ?>
        <div class="panneau__corps">
            <div class="etat-vide">
                <?= Icone::svg('panneau') ?>
                <h3>Aucun panneau</h3>
                <p>Ajoutez les supports du parc pour pouvoir les réserver.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Emplacement</th>
                        <th>Format</th>
                        <th class="tableau__numerique">Faces</th>
                        <th class="tableau__numerique">Trafic / j</th>
                        <th class="tableau__numerique">Tarif</th>
                        <th>Photo</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($panneaux as $panneau): ?>
                        <tr>
                            <td class="tableau__principal">
                                <a href="<?= e(url('admin/panneaux/' . $panneau['id'])) ?>"><?= e((string) $panneau['reference']) ?></a>
                            </td>
                            <td>
                                <?= e((string) $panneau['libelle']) ?>
                                <div class="tableau__secondaire">
                                    <?= e((string) ($panneau['adresse'] ?? '')) ?><?= !empty($panneau['ville']) ? ' · ' . e((string) $panneau['ville']) : '' ?>
                                </div>
                            </td>
                            <td><?= e((string) ($panneau['format'] ?? '—')) ?></td>
                            <td class="tableau__numerique"><?= e((string) $panneau['faces']) ?></td>
                            <td class="tableau__numerique"><?= e(number_format((float) ($panneau['trafic_estime'] ?? 0), 0, ',', ' ')) ?></td>
                            <td class="tableau__numerique"><?= e(fcfa($panneau['tarif'] ?? 0)) ?></td>
                            <td class="texte-petit">
                                <?= !empty($panneau['photo_date']) ? e(date_fr((string) $panneau['photo_date'])) : '<span style="color:var(--alerte)">à faire</span>' ?>
                            </td>
                            <td><?= etiquette_etat((string) $panneau['statut']) ?></td>
                            <td>
                                <div class="tableau__actions">
                                    <?php if (!empty($panneau['latitude']) && !empty($panneau['longitude'])): ?>
                                        <a class="btn btn--contour btn--petit" target="_blank" rel="noopener"
                                           href="https://www.google.com/maps?q=<?= e((string) $panneau['latitude'] . ',' . $panneau['longitude']) ?>"
                                           title="Voir sur la carte">
                                            <?= Icone::svg('carte') ?>
                                            <span class="sr-only">Carte</span>
                                        </a>
                                    <?php endif; ?>
                                    <a class="btn btn--contour btn--petit" href="<?= e(url('admin/panneaux/' . $panneau['id'])) ?>">
                                        <?= Icone::svg($estRegie ? 'modifier' : 'oeil') ?>
                                        <span class="sr-only"><?= $estRegie ? 'Modifier' : 'Consulter' ?></span>
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
    <strong>Trafic estimé.</strong> Les valeurs de trafic sont déclarées par la régie à partir des comptages
    terrain. Elles alimentent la portée <em>estimée</em> des campagnes, toujours distinguée des scans réellement
    <em>mesurés</em>. La date de la dernière photo est suivie pour chaque support (contrôle de conformité).
</div>
