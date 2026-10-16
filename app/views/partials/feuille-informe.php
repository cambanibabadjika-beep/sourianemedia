<?php
/**
 * Partial — Panneau glissant « Rester informé » (ch. 5.3).
 *
 * Le même balisage sert la page d’un annonceur et la page « Découvrir les
 * offres » : les identifiants sont ceux attendus par assets/js/app.js, qui
 * pilote l’ouverture, l’installation et l’abonnement sans savoir sur quelle
 * page il se trouve.
 *
 * Variables :
 *   $feuilleTitre           string  — titre de la feuille
 *   $feuilleIntro           string  — phrase d’introduction (texte brut)
 *   $feuilleWhatsapp        ?string — numéro WhatsApp de repli (facultatif)
 *   $feuilleMessageWhatsapp string  — message pré-rempli du lien WhatsApp
 *   $feuillePortee          string  — portée proposée par défaut :
 *                                     'annonceur' ou 'plateforme'
 *   $feuilleSansAnnonceur   bool    — vrai lorsqu’aucun annonceur n’est en
 *                                     contexte (page de découverte) : seule
 *                                     la portée « plateforme » est proposée
 */

declare(strict_types=1);

use Souriane\Core\Icone;

$feuilleTitre           = $feuilleTitre ?? __('annonceur.rester_informe', 'Rester informé');
$feuilleIntro           = $feuilleIntro ?? '';
$feuilleWhatsapp        = $feuilleWhatsapp ?? null;
$feuilleMessageWhatsapp = $feuilleMessageWhatsapp ?? '';
$feuilleSansAnnonceur   = (bool) ($feuilleSansAnnonceur ?? false);
$feuillePortee          = ($feuillePortee ?? 'annonceur') === 'plateforme' ? 'plateforme' : 'annonceur';

// Options de portée. Sans annonceur en contexte, seul l’abonnement à toute la
// plateforme a un sens : proposer « cet annonceur » serait trompeur.
if ($feuilleSansAnnonceur) {
    $optionsPortee = [[
        'valeur'  => 'plateforme',
        'libelle' => 'Toutes les offres Souriane',
        'aide'    => 'Les bons plans de tous les annonceurs du réseau.',
        'coche'   => true,
    ]];
} else {
    $optionsPortee = [
        [
            'valeur'  => 'annonceur',
            'libelle' => 'Cet annonceur seulement',
            'aide'    => 'Vous ne recevez que les offres de cette enseigne.',
            'coche'   => $feuillePortee === 'annonceur',
        ],
        [
            'valeur'  => 'plateforme',
            'libelle' => 'Toutes les offres Souriane',
            'aide'    => 'Les bons plans de tous les annonceurs du réseau.',
            'coche'   => $feuillePortee === 'plateforme',
        ],
    ];
}
?>
<div class="voile" id="voile-informe" hidden></div>

<div class="feuille" id="feuille-informe" role="dialog" aria-modal="true" aria-labelledby="titre-feuille-informe" hidden>
    <div class="feuille__poignee"></div>

    <h3 id="titre-feuille-informe"><?= e($feuilleTitre) ?></h3>
    <p class="feuille__intro"><?= e($feuilleIntro) ?></p>

    <!-- Action 1 : installer la page sur le téléphone -->
    <button type="button" class="action-informe" id="action-installer">
        <span class="action-informe__icone"><?= Icone::svg('telecharger') ?></span>
        <span style="flex:1;min-width:0">
            <span class="action-informe__titre">
                Installer sur mon téléphone
                <span class="action-informe__etat" id="etat-installe" hidden>Installé</span>
            </span>
            <span class="action-informe__benefice">
                La page s’ouvre comme une application, sans passer par le navigateur.
            </span>
        </span>
    </button>

    <!-- Action 2 : recevoir les notifications -->
    <button type="button" class="action-informe action-informe--lilas" id="action-notifier">
        <span class="action-informe__icone"><?= Icone::svg('cloche') ?></span>
        <span style="flex:1;min-width:0">
            <span class="action-informe__titre">
                Recevoir les offres
                <span class="action-informe__etat" id="etat-abonne" hidden>Abonné</span>
            </span>
            <span class="action-informe__benefice">
                Une notification à chaque nouvelle offre. Quatre par mois au maximum.
            </span>
        </span>
    </button>

    <!-- Portée de l’abonnement : un annonceur, ou tout le réseau -->
    <div class="frequence" id="bloc-portee" hidden>
        <div class="frequence__titre">Quelles offres souhaitez-vous recevoir ?</div>
        <div class="frequence__options">
            <?php foreach ($optionsPortee as $option): ?>
                <label class="frequence__option">
                    <input type="radio" name="portee" value="<?= e($option['valeur']) ?>"<?= $option['coche'] ? ' checked' : '' ?>>
                    <span><?= e($option['libelle']) ?><small><?= e($option['aide']) ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Choix de la fréquence -->
    <div class="frequence" id="bloc-frequence" hidden>
        <div class="frequence__titre">À quelle fréquence ?</div>
        <div class="frequence__options">
            <label class="frequence__option">
                <input type="radio" name="frequence" value="chaque_annonce" checked>
                <span>À chaque nouvelle offre<small>Vous ne manquez rien.</small></span>
            </label>
            <label class="frequence__option">
                <input type="radio" name="frequence" value="hebdomadaire">
                <span>Un résumé par semaine<small>Une seule notification, le lundi.</small></span>
            </label>
        </div>
    </div>

    <!-- Tutoriel iOS (EF-E05) -->
    <div class="tutoriel" id="tutoriel-ios" hidden>
        <div class="tutoriel__etape">
            <span class="tutoriel__numero">1</span>
            <p>Appuyez sur le bouton <strong>Partager</strong>
                <span class="icone-partage"><?= Icone::svg('partage') ?></span>
                en bas de l’écran.</p>
        </div>
        <div class="tutoriel__etape">
            <span class="tutoriel__numero">2</span>
            <p>Faites défiler puis choisissez <strong>« Sur l’écran d’accueil »</strong>.</p>
        </div>
        <div class="tutoriel__etape">
            <span class="tutoriel__numero">3</span>
            <p>Appuyez sur <strong>Ajouter</strong>. Rouvrez ensuite la page depuis l’icône,
               puis activez les notifications.</p>
        </div>
    </div>

    <!-- Explication de la restriction iOS (ch. 5.5) -->
    <div class="conditions" id="note-ios" hidden style="margin-top:16px">
        <div class="conditions__titre"><?= Icone::svg('info') ?> Sur iPhone</div>
        Les notifications ne sont possibles qu’après avoir ajouté la page à l’écran d’accueil
        (iOS 16.4 ou plus récent).
    </div>

    <!-- Repli WhatsApp -->
    <?php if (whatsapp_lien($feuilleWhatsapp) !== ''): ?>
        <a class="action-informe action-informe--or" href="<?= e(whatsapp_lien($feuilleWhatsapp, $feuilleMessageWhatsapp)) ?>" rel="noopener" style="text-decoration:none" data-action="clic_whatsapp">
            <span class="action-informe__icone"><?= Icone::svg('whatsapp') ?></span>
            <span style="flex:1;min-width:0">
                <span class="action-informe__titre">Recevoir les offres par WhatsApp</span>
                <span class="action-informe__benefice">
                    Solution de repli si les notifications ne sont pas disponibles sur votre appareil.
                </span>
            </span>
        </a>
    <?php endif; ?>

    <p class="texte-petit" style="margin-top:14px">
        En activant les notifications, vous acceptez de recevoir les offres de cet annonceur.
        Vous pouvez vous désinscrire depuis cette même fenêtre, à tout moment.
    </p>

    <button type="button" class="btn btn--contour btn--large" id="fermer-feuille" style="margin-top:14px">
        Fermer
    </button>
</div>
