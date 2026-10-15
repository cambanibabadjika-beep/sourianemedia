<?php
/**
 * Vue — Parc de panneaux (vitrine commerciale).
 *
 * Variables :
 *   $panneaux array — liste des supports
 *   $villes   array — répartition par ville
 */

declare(strict_types=1);

use Souriane\Core\Icone;

$etats = [
    'disponible'  => 'Disponible',
    'reserve'     => 'Réservé',
    'occupe'      => 'Occupé',
    'maintenance' => 'Maintenance',
];
?>

<section class="section section--serree section--teinte">
    <div class="conteneur">
        <span class="entete-section__surtitre">Le parc</span>
        <h1>Nos supports d’affichage</h1>
        <p style="color:var(--gris-600);max-width:64ch">
            Chaque panneau est référencé, photographié et géolocalisé. Le statut indiqué est mis à jour
            en temps réel depuis l’exploitation.
        </p>
    </div>
</section>

<section class="section">
    <div class="conteneur">
        <?php if ($villes !== []): ?>
            <div class="grille grille--4 grille--espacee">
                <?php foreach ($villes as $rang => $ville): ?>
                    <?php
                    $totalVille = (int) $ville['total'];
                    $dispoVille = (int) $ville['disponibles'];
                    $partDispo  = $totalVille > 0 ? round($dispoVille / $totalVille * 100) : 0;
                    ?>
                    <div class="carte-ville reveler" style="--retard:<?= $rang * 90 ?>ms">
                        <span class="carte-ville__total"><?= e((string) $totalVille) ?></span>
                        <span class="carte-ville__nom"><?= e((string) $ville['ville']) ?></span>
                        <span class="jauge" role="img" aria-label="<?= e($dispoVille . ' panneau(x) disponible(s) sur ' . $totalVille) ?>">
                            <span class="jauge__remplissage" style="--jauge:<?= $partDispo ?>%"></span>
                        </span>
                        <span class="texte-petit"><?= e((string) $dispoVille) ?> disponible(s)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($panneaux === []): ?>
            <div class="etat-vide">
                <?= Icone::svg('panneau') ?>
                <h3>Aucun panneau référencé</h3>
                <p>Le parc sera affiché ici dès l’enregistrement des premiers supports.</p>
            </div>
        <?php else: ?>
            <?php
            // Villes réellement présentes dans les données : le filtre doit
            // refléter le parc, jamais une liste écrite en dur dans la vue.
            $villesParc = array_values(array_unique(array_filter(array_map(
                static fn ($p) => trim((string) ($p['ville'] ?? '')),
                $panneaux
            ))));
            sort($villesParc);

            $nbPanneaux = count($panneaux);

            // Libellés lus par le mode carte (attribut data-label). La référence
            // n'en a pas besoin : elle sert déjà de titre à la carte.
            $libelles = [
                'emplacement' => 'Emplacement',
                'ville'       => 'Ville',
                'format'      => 'Format',
                'trafic'      => 'Trafic / jour',
                'tarif'       => 'Tarif',
                'statut'      => 'Statut',
            ];
            ?>

            <!-- Filtre côté client : il masque des lignes, n'en supprime aucune.
                 Chaque bouton porte son décompte, calculé ici en PHP. -->
            <div class="filtres" role="group" aria-label="Filtrer le parc par ville">
                <span class="filtres__titre" id="titre-filtres">Filtrer</span>
                <button type="button" class="filtre" data-filtre="toutes" aria-pressed="true">
                    Toutes
                    <span class="filtre__compte"><?= $nbPanneaux ?></span>
                </button>
                <?php foreach ($villesParc as $villeFiltre): ?>
                    <?php $nbVille = count(array_filter($panneaux, static fn ($p) => (string) ($p['ville'] ?? '') === $villeFiltre)); ?>
                    <button type="button" class="filtre" data-filtre="<?= e($villeFiltre) ?>" aria-pressed="false">
                        <?= e($villeFiltre) ?>
                        <span class="filtre__compte"><?= $nbVille ?></span>
                    </button>
                <?php endforeach; ?>
                <p class="filtres__resultat" role="status" aria-live="polite">
                    <span id="parc-resultat"><?= $nbPanneaux ?></span> support(s) affiché(s)
                </p>
            </div>

            <div class="panneau reveler">
                <div class="tableau-enveloppe" id="parc-tableau">
                    <table class="tableau tableau--parc">
                        <thead>
                            <tr>
                                <th>Référence</th>
                                <th>Emplacement</th>
                                <th>Ville</th>
                                <th>Format</th>
                                <th class="tableau__numerique">Trafic / jour</th>
                                <th class="tableau__numerique">Tarif</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($panneaux as $rang => $p): ?>
                                <tr data-ville="<?= e((string) ($p['ville'] ?? '')) ?>" style="--retard:<?= $rang * 55 ?>ms">
                                    <td class="tableau__principal"><?= e((string) $p['reference']) ?></td>
                                    <td class="tableau__cellule" data-label="<?= e($libelles['emplacement']) ?>">
                                        <span class="cellule__valeur">
                                            <?= e((string) $p['libelle']) ?>
                                            <?php if (!empty($p['adresse'])): ?>
                                                <span class="tableau__secondaire"><?= e((string) $p['adresse']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="tableau__cellule" data-label="<?= e($libelles['ville']) ?>">
                                        <span class="cellule__valeur"><?= e((string) ($p['ville'] ?? '—')) ?></span>
                                    </td>
                                    <td class="tableau__cellule" data-label="<?= e($libelles['format']) ?>">
                                        <span class="cellule__valeur"><?= e((string) ($p['format'] ?? '—')) ?></span>
                                    </td>
                                    <td class="tableau__numerique tableau__cellule" data-label="<?= e($libelles['trafic']) ?>">
                                        <?= e(number_format((float) ($p['trafic_estime'] ?? 0), 0, ',', ' ')) ?>
                                    </td>
                                    <td class="tableau__numerique tableau__cellule" data-label="<?= e($libelles['tarif']) ?>">
                                        <?= e(fcfa($p['tarif'] ?? 0)) ?>
                                    </td>
                                    <td class="tableau__cellule" data-label="<?= e($libelles['statut']) ?>">
                                        <span class="etat etat--<?= e((string) $p['statut']) ?>"><?= e($etats[$p['statut']] ?? $p['statut']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="note">
                    <?= Icone::svg('info') ?>
                    <p>Les trafics indiqués sont des estimations déclarées par la régie à partir des comptages terrain. Ils servent au calcul de la portée estimée, distincte des scans réellement mesurés.</p>
                </div>

                <!-- État vide affiché quand un filtre ne ramène aucune ligne.
                     Il complète la vue ; il n'est jamais le seul contenu. -->
                <div class="etat-vide" id="parc-vide" hidden>
                    <?= Icone::svg('recherche') ?>
                    <h3>Aucun support dans cette ville</h3>
                    <p>Choisissez une autre ville, ou revenez à l’ensemble du parc.</p>
                    <button type="button" class="btn btn--primaire" data-filtre="toutes">
                        <?= Icone::svg('filtre') ?>
                        Voir tout le parc
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
