<?php
/**
 * Vue — Parc de panneaux (vitrine commerciale).
 *
 * Variables :
 *   $panneaux     array — supports correspondant aux filtres actifs
 *   $villes       array — répartition par ville, pour les cartes
 *   $villesParc   array — villes présentes dans le parc (valeurs de filtre)
 *   $formatsParc  array — formats présents dans le parc
 *   $statutsParc  array — statuts possibles
 *   $filtres      array — filtres actifs : ville, format, statut, disponible
 *
 * Le filtrage est fait par le serveur (EF-A05) : le formulaire ci-dessous
 * renvoie vers cette même page avec ses paramètres d’adresse. Il n’y a donc
 * pas de second filtrage en JavaScript, qui finirait par diverger de celui-ci.
 */

declare(strict_types=1);

use Souriane\Core\Icone;

/*
 * Les variables de filtre portent un préfixe : plus bas, la boucle qui rend
 * les cartes de ville utilise `$ville` comme variable de boucle. Sans
 * préfixe, elle écraserait la valeur du filtre, et la sélection ne serait
 * plus reportée dans le formulaire.
 */
$filtreVille      = (string) ($filtres['ville'] ?? '');
$filtreFormat     = (string) ($filtres['format'] ?? '');
$filtreStatut     = (string) ($filtres['statut'] ?? '');
$filtreDisponible = (bool) ($filtres['disponible'] ?? false);

$filtreActif = $filtreVille !== '' || $filtreFormat !== '' || $filtreStatut !== '' || $filtreDisponible;
$nbPanneaux  = count($panneaux);
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

        <?php
        // Les listes de valeurs de filtre proviennent du parc entier, et non du
        // résultat filtré : elles restent donc justes même quand le filtre ne
        // ramène aucune ligne, ce qui permet toujours de le défaire.
        $parcVide = $villesParc === [] && $formatsParc === [];
        ?>

        <?php if ($parcVide): ?>
            <div class="etat-vide">
                <?= Icone::svg('panneau') ?>
                <h3>Aucun panneau référencé</h3>
                <p>Le parc sera affiché ici dès l’enregistrement des premiers supports.</p>
            </div>
        <?php else: ?>
            <?php
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

            <!-- Filtres du parc (EF-A05) : ville, format, statut, disponibilité.
                 Formulaire GET vers cette même page : la sélection obtenue est
                 une adresse partageable, et le filtre agit sans JavaScript.
                 Aucun second filtrage n’est fait côté navigateur, qui finirait
                 par diverger de celui-ci. -->
            <form class="filtres" method="get" action="<?= e(url('le-parc')) ?>">
                <span class="filtres__titre" id="titre-filtres">Filtrer</span>

                <div class="champ">
                    <label class="champ__etiquette" for="filtre-ville">Ville</label>
                    <select class="liste" id="filtre-ville" name="ville">
                        <option value="">Toutes les villes</option>
                        <?php foreach ($villesParc as $valeur): ?>
                            <option value="<?= e((string) $valeur) ?>"<?= (string) $valeur === $filtreVille ? ' selected' : '' ?>>
                                <?= e((string) $valeur) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="champ">
                    <label class="champ__etiquette" for="filtre-format">Format</label>
                    <select class="liste" id="filtre-format" name="format">
                        <option value="">Tous les formats</option>
                        <?php foreach ($formatsParc as $valeur): ?>
                            <option value="<?= e((string) $valeur) ?>"<?= (string) $valeur === $filtreFormat ? ' selected' : '' ?>>
                                <?= e((string) $valeur) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="champ">
                    <label class="champ__etiquette" for="filtre-statut">Statut</label>
                    <select class="liste" id="filtre-statut" name="statut">
                        <option value="">Tous les statuts</option>
                        <?php foreach ($statutsParc as $valeur): ?>
                            <option value="<?= e((string) $valeur) ?>"<?= (string) $valeur === $filtreStatut ? ' selected' : '' ?>>
                                <?= e(ETATS_LIBELLES[$valeur] ?? ucfirst((string) $valeur)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="champ">
                    <span class="champ__etiquette">Disponibilité</span>
                    <div class="choix">
                        <label class="choix__element">
                            <input type="checkbox" id="filtre-disponible" name="disponible" value="1"<?= $filtreDisponible ? ' checked' : '' ?>>
                            <span>Uniquement les supports disponibles</span>
                        </label>
                    </div>
                </div>

                <div class="pile">
                    <button type="submit" class="btn btn--primaire btn--petit">
                        <?= Icone::svg('filtre') ?> Appliquer
                    </button>
                    <?php if ($filtreActif): ?>
                        <a class="btn btn--contour btn--petit" href="<?= e(url('le-parc')) ?>">Réinitialiser</a>
                    <?php endif; ?>
                </div>

                <p class="filtres__resultat" role="status" aria-live="polite">
                    <span id="parc-resultat"><?= e((string) $nbPanneaux) ?></span> support(s) affiché(s)
                </p>
            </form>

            <?php if ($panneaux === []): ?>
                <div class="etat-vide">
                    <?= Icone::svg('recherche') ?>
                    <h3>Aucun support ne correspond à ces filtres</h3>
                    <p>Élargissez la sélection, ou revenez à l’ensemble du parc.</p>
                    <a class="btn btn--primaire" href="<?= e(url('le-parc')) ?>">
                        <?= Icone::svg('filtre') ?>
                        Voir tout le parc
                    </a>
                </div>
            <?php else: ?>

            <!-- Bascule liste / carte (EF-A06). Le bouton n'est révélé que
                 par parc-carte.js : sans JavaScript, aucune commande inerte
                 n'est proposée et le tableau reste la seule vue. -->
            <div class="parc-bascule" id="parc-bascule" hidden role="group" aria-label="Mode d’affichage du parc">
                <button type="button" class="parc-bascule__bouton" data-vue="carte" aria-pressed="true">
                    <?= Icone::svg('carte') ?> Carte
                </button>
                <button type="button" class="parc-bascule__bouton" data-vue="tableau" aria-pressed="false">
                    <?= Icone::svg('menu') ?> Liste
                </button>
            </div>

            <p class="parc-carte__avertissement" id="parc-carte-avertissement" hidden>
                <?= Icone::svg('info') ?>
                <span>Aucun support du parc ne porte encore de coordonnées : la carte ne peut pas être
                dessinée. Le tableau ci-dessous reste la référence.</span>
            </p>

            <!-- Fond de carte : OpenStreetMap. Les tuiles sont chargées
                 depuis le réseau ; sans connexion, le fond reste vide mais
                 les épingles et leurs bulles restent affichées. -->
            <div class="parc-carte" id="parc-carte" role="region"
                 aria-label="Carte des supports du parc"></div>

            <div class="panneau reveler" id="parc-liste">
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
                                <?php
                                // Les valeurs affichées ci-dessous sont reprises
                                // par la carte (EF-A06) sous forme d'attributs :
                                // la carte lit ces lignes, elle n'interroge pas
                                // la base une seconde fois. Liste et carte
                                // montrent donc toujours le même ensemble.
                                $latSupport = $p['latitude'] ?? null;
                                $lngSupport = $p['longitude'] ?? null;
                                $aCoordonnees = $latSupport !== null && $lngSupport !== null
                                    && (string) $latSupport !== '' && (string) $lngSupport !== '';
                                $libelleEtat = ETATS_LIBELLES[(string) $p['statut']] ?? ucfirst((string) $p['statut']);
                                ?>
                                <tr data-ville="<?= e((string) ($p['ville'] ?? '')) ?>"
                                    data-reference="<?= e((string) $p['reference']) ?>"
                                    data-libelle="<?= e((string) $p['libelle']) ?>"
                                    data-adresse="<?= e((string) ($p['adresse'] ?? '')) ?>"
                                    data-format="<?= e((string) ($p['format'] ?? '')) ?>"
                                    data-trafic="<?= e(number_format((float) ($p['trafic_estime'] ?? 0), 0, ',', ' ')) ?>"
                                    data-tarif="<?= e(fcfa($p['tarif'] ?? 0)) ?>"
                                    data-statut="<?= e((string) $p['statut']) ?>"
                                    data-etat="<?= e($libelleEtat) ?>"<?php if ($aCoordonnees): ?>
                                    data-lat="<?= e((string) $latSupport) ?>"
                                    data-lng="<?= e((string) $lngSupport) ?>"<?php endif; ?>
                                    style="--retard:<?= $rang * 55 ?>ms">
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
                                        <?= etiquette_etat((string) $p['statut']) ?>
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
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
