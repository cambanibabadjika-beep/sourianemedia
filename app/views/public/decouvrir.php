<?php
/**
 * Vue — Découvrir les offres (EF-E11).
 *
 * Rassemble les offres en cours de tous les annonceurs actifs : c’est la
 * « place publique » du réseau, accessible depuis l’en-tête, le pied de page
 * et la page de chaque annonceur.
 *
 * Variables :
 *   $surtitre  string
 *   $titrePage string
 *   $offres    array<{adresse, nom, ville, logo, image, bandeau, resume, debut, fin}>
 */

declare(strict_types=1);

use Souriane\Core\Icone;

$surtitre  = $surtitre ?? '';
$titrePage = $titrePage ?? 'Découvrir les offres';
$offres    = $offres ?? [];
?>

<!-- ======================================================================
     En-tête de page
     ====================================================================== -->
<section class="section section--serree section--teinte">
    <div class="conteneur">
        <?php if ($surtitre !== ''): ?>
            <span class="entete-section__surtitre"><?= e($surtitre) ?></span>
        <?php endif; ?>
        <h1><?= e($titrePage) ?></h1>
        <p style="color:var(--gris-600);font-size:1.05rem;max-width:64ch">
            Toutes les offres en cours des annonceurs du réseau, au même endroit.
            Un QR code scanné sur un panneau ouvre une enseigne : cette page vous
            montre les autres.
        </p>
    </div>
</section>

<!-- ======================================================================
     Les offres
     ====================================================================== -->
<section class="section">
    <div class="conteneur">
        <?php if ($offres === []): ?>
            <div class="carte carte--appel">
                <h2>Aucune offre en ligne pour le moment</h2>
                <p class="carte--appel__texte">
                    Les annonceurs du réseau publient leurs offres au fil des campagnes.
                    Revenez bientôt, ou restez informé pour être prévenu dès la prochaine.
                </p>
            </div>
        <?php else: ?>
            <div class="grille grille--3">
                <?php foreach ($offres as $rang => $offre): ?>
                    <?php
                    $illustration = $offre['image'] !== '' ? $offre['image'] : $offre['logo'];
                    $validite     = '';
                    if ($offre['fin'] !== '') {
                        $validite = 'Jusqu’au ' . date_fr($offre['fin']);
                    } elseif ($offre['debut'] !== '') {
                        $validite = 'À partir du ' . date_fr($offre['debut']);
                    }
                    ?>
                    <article class="offre-carte reveler" style="--retard:<?= min($rang, 8) * 90 ?>ms">
                        <a class="offre-carte__lien" href="<?= e(url_page($offre['adresse'])) ?>" data-action="clic_offre_decouverte">
                            <div class="offre-carte__visuel">
                                <?php if ($illustration !== ''): ?>
                                    <img src="<?= e(url($illustration)) ?>" alt="" loading="lazy" width="360" height="200">
                                <?php else: ?>
                                    <span class="offre-carte__visuel-vide" aria-hidden="true"><?= Icone::svg('etincelle') ?></span>
                                <?php endif; ?>
                                <?php if ($offre['bandeau'] !== ''): ?>
                                    <span class="offre-carte__etiquette"><?= e(extrait($offre['bandeau'], 46)) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="offre-carte__corps">
                                <h3 class="offre-carte__nom"><?= e($offre['nom']) ?></h3>
                                <?php if ($offre['ville'] !== ''): ?>
                                    <div class="offre-carte__ville"><?= Icone::svg('carte') ?> <?= e($offre['ville']) ?></div>
                                <?php endif; ?>
                                <?php if ($offre['resume'] !== ''): ?>
                                    <p class="offre-carte__resume"><?= e($offre['resume']) ?></p>
                                <?php endif; ?>
                                <div class="offre-carte__pied">
                                    <span class="offre-carte__validite"><?= e($validite !== '' ? $validite : 'Offre en cours') ?></span>
                                    <span class="offre-carte__action">
                                        Voir l’offre <?= Icone::svg('suivant') ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ======================================================================
     Rester informé de tout le réseau
     ====================================================================== -->
<section class="section section--serree">
    <div class="conteneur">
        <div class="carte carte--appel reveler">
            <h2>Ne rien manquer du réseau</h2>
            <p class="carte--appel__texte">
                Installez l’application et recevez les offres de tous les annonceurs
                Souriane, pas seulement de celui dont vous avez scanné le panneau.
            </p>
            <div class="pile carte--appel__actions">
                <button type="button" class="btn btn--primaire" id="ouvrir-rester-informe" data-action="clic_rester_informe">
                    <?= Icone::svg('cloche') ?>
                    Rester informé
                </button>
                <a class="btn btn--contour" href="<?= e(url('le-parc')) ?>">
                    <?= Icone::svg('panneau') ?>
                    Voir le parc
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ======================================================================
     Feuille « Rester informé » — portée : tout le réseau
     ====================================================================== -->
<?php
$feuilleTitre           = 'Rester informé';
$feuilleIntro           = 'Installez l’application et recevez les offres de tout le réseau Souriane. Choisissez ce qui vous convient.';
$feuilleWhatsapp        = null;
$feuilleMessageWhatsapp = '';
$feuillePortee          = 'plateforme';
$feuilleSansAnnonceur   = true;
require __DIR__ . '/../partials/feuille-informe.php';
?>
