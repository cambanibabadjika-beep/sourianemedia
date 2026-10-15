<?php
/**
 * Vue — Tableau de bord.
 *
 * Variables :
 *   $compteurs array — indicateurs globaux (Evenement::compteursGlobaux)
 *   $statuts   array — répartition des campagnes par statut
 *   $campagnes array — dernières campagnes
 *   $parc      array — {total, disponibles, villes}
 *   $diffusion array — {annonceurs, abonnes, visiteurs, codes}
 *   $journal   array — dernières opérations (régie uniquement)
 */

declare(strict_types=1);

use Souriane\Core\Auth;
use Souriane\Core\Icone;

$estRegie = Auth::aRole('regie');
?>

<?php if (Auth::role() === 'annonceur'): ?>
    <div class="encart">
        <strong>Vue annonceur.</strong> Vous consultez ici les campagnes et les statistiques de votre compte.
        La gestion du parc et de la diffusion est assurée par la régie.
    </div>
<?php endif; ?>

<!-- Indicateurs ------------------------------------------------------------ -->
<div class="indicateurs">
    <div class="indicateur">
        <div class="indicateur__icone"><?= Icone::svg('qr') ?></div>
        <div class="indicateur__etiquette">Scans · 7 jours</div>
        <div class="indicateur__valeur"><?= e(number_format((float) $compteurs['scans_7j'], 0, ',', ' ')) ?></div>
        <div class="indicateur__detail"><?= e(number_format((float) $compteurs['scans_30j'], 0, ',', ' ')) ?> sur 30 jours</div>
    </div>

    <div class="indicateur indicateur--lilas">
        <div class="indicateur__icone"><?= Icone::svg('utilisateurs') ?></div>
        <div class="indicateur__etiquette">Visiteurs uniques · 30 j</div>
        <div class="indicateur__valeur"><?= e(number_format((float) $compteurs['visiteurs_30j'], 0, ',', ' ')) ?></div>
        <div class="indicateur__detail"><?= e(number_format((float) $diffusion['visiteurs'], 0, ',', ' ')) ?> sessions de visite</div>
    </div>

    <div class="indicateur indicateur--or">
        <div class="indicateur__icone"><?= Icone::svg('telephone') ?></div>
        <div class="indicateur__etiquette">Contacts</div>
        <div class="indicateur__valeur"><?= e(number_format((float) $compteurs['clics_contact'], 0, ',', ' ')) ?></div>
        <div class="indicateur__detail">appels et messages WhatsApp</div>
    </div>

    <div class="indicateur indicateur--gris">
        <div class="indicateur__icone"><?= Icone::svg('cloche') ?></div>
        <div class="indicateur__etiquette">Abonnés actifs</div>
        <div class="indicateur__valeur"><?= e(number_format((float) $compteurs['abonnements'], 0, ',', ' ')) ?></div>
        <div class="indicateur__detail"><?= e(number_format((float) $compteurs['installations'], 0, ',', ' ')) ?> installation(s)</div>
    </div>
</div>

<div class="grille-admin-2" style="display:grid;grid-template-columns:1.35fr 1fr;gap:22px;align-items:start">

    <!-- Dernières campagnes ------------------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('etincelle') ?> Campagnes récentes</h2>
            <a class="btn btn--contour btn--petit" href="<?= e(url('admin/campagnes')) ?>">Tout voir</a>
        </div>

        <?php if ($campagnes === []): ?>
            <div class="panneau__corps">
                <p class="texte-petit">Aucune campagne enregistrée pour l’instant.</p>
            </div>
        <?php else: ?>
            <div class="tableau-enveloppe">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Campagne</th>
                            <th>Période</th>
                            <th class="tableau__numerique">Prix</th>
                            <th>Statut</th>
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
                                <td class="tableau__numerique"><?= e(fcfa($campagne['prix'])) ?></td>
                                <td><?= etiquette_etat((string) $campagne['statut']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <!-- Parc ----------------------------------------------------------- -->
        <div class="panneau">
            <div class="panneau__entete">
                <h2 class="panneau__titre"><?= Icone::svg('panneau') ?> Parc de panneaux</h2>
            </div>
            <div class="panneau__corps">
                <div class="liste-stats">
                    <div class="liste-stats__ligne">
                        <span class="liste-stats__etiquette">Supports référencés</span>
                        <span class="liste-stats__valeur"><?= e((string) $parc['total']) ?></span>
                        <span class="liste-stats__barre barre-progression">
                            <span class="barre-progression__remplissage" style="width:100%"></span>
                        </span>
                    </div>
                    <div class="liste-stats__ligne">
                        <span class="liste-stats__etiquette">Disponibles immédiatement</span>
                        <span class="liste-stats__valeur"><?= e((string) $parc['disponibles']) ?></span>
                        <span class="liste-stats__barre barre-progression">
                            <span class="barre-progression__remplissage" style="width:<?= e((string) ($parc['total'] > 0 ? round($parc['disponibles'] / $parc['total'] * 100) : 0)) ?>%"></span>
                        </span>
                    </div>
                    <div class="liste-stats__ligne">
                        <span class="liste-stats__etiquette">Villes couvertes</span>
                        <span class="liste-stats__valeur"><?= e((string) $parc['villes']) ?></span>
                    </div>
                </div>

                <a class="btn btn--contour btn--large ecart-haut" href="<?= e(url('admin/panneaux')) ?>">
                    <?= Icone::svg('panneau') ?>
                    Consulter le parc
                </a>
            </div>
        </div>

        <!-- Répartition des campagnes -------------------------------------- -->
        <div class="panneau">
            <div class="panneau__entete">
                <h2 class="panneau__titre"><?= Icone::svg('graphique') ?> État des campagnes</h2>
            </div>
            <div class="panneau__corps">
                <div class="liste-stats">
                    <?php $total = max(1, array_sum($statuts)); ?>
                    <?php foreach ($statuts as $statut => $nombre): ?>
                        <div class="liste-stats__ligne">
                            <span class="liste-stats__etiquette"><?= e(ETATS_LIBELLES[$statut] ?? $statut) ?></span>
                            <span class="liste-stats__valeur"><?= e((string) $nombre) ?></span>
                            <span class="liste-stats__barre barre-progression">
                                <span class="barre-progression__remplissage" style="width:<?= e((string) round($nombre / $total * 100)) ?>%"></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Diffusion ------------------------------------------------------ -->
        <div class="panneau">
            <div class="panneau__entete">
                <h2 class="panneau__titre"><?= Icone::svg('qr') ?> Diffusion</h2>
            </div>
            <div class="panneau__corps">
                <div class="liste-stats">
                    <div class="liste-stats__ligne">
                        <span class="liste-stats__etiquette">Annonceurs</span>
                        <span class="liste-stats__valeur"><?= e((string) $diffusion['annonceurs']) ?></span>
                    </div>
                    <div class="liste-stats__ligne">
                        <span class="liste-stats__etiquette">Codes courts actifs</span>
                        <span class="liste-stats__valeur"><?= e((string) $diffusion['codes']) ?></span>
                    </div>
                    <div class="liste-stats__ligne">
                        <span class="liste-stats__etiquette">Abonnés aux notifications</span>
                        <span class="liste-stats__valeur"><?= e((string) $diffusion['abonnes']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($estRegie && $journal !== []): ?>
    <div class="panneau ecart-haut">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('archive') ?> Dernières opérations</h2>
            <a class="btn btn--contour btn--petit" href="<?= e(url('admin/journal')) ?>">Journal complet</a>
        </div>
        <div class="panneau__corps panneau__corps--sans-marge">
            <div class="tableau-enveloppe">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Détail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($journal as $entree): ?>
                            <tr>
                                <td class="texte-petit"><?= e(date_fr((string) $entree['date'], true)) ?></td>
                                <td><?= e((string) ($entree['utilisateur_nom'] ?? '—')) ?></td>
                                <td><span class="monospace"><?= e((string) $entree['action']) ?></span></td>
                                <td class="texte-petit"><?= e((string) ($entree['detail'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
@media (max-width: 1100px) {
    .grille-admin-2 { grid-template-columns: 1fr !important; }
}
</style>
