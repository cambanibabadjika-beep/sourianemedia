<?php
/**
 * Vue — Page publique de l’annonceur (ch. 5.2 du cahier des charges).
 *
 * L’ordre d’affichage est imposé :
 *   bandeau d’offre → identité → boutons de contact → informations complémentaires
 *   → galerie → adresse et itinéraire → horaires → réseaux sociaux
 *   → « Rester informé » → mentions légales.
 *
 * Variables :
 *   $page      array — page + données annonceur (jointure)
 *   $sections  array — sections actives, déjà triées selon l’ordre imposé
 *   $code      ?array — code court d’origine, si l’accès vient d’un QR
 */

declare(strict_types=1);

use Souriane\Core\Icone;

$code = $code ?? null;

/** Récupère une section par son type. */
$parType = [];
foreach ($sections as $section) {
    $parType[$section['type']] = $section;
}

$contenu = static fn (?array $section): array => $section === null
    ? []
    : json_lire($section['contenu'] ?? null);

$offre      = $contenu($parType['offre'] ?? null);
$present    = $contenu($parType['presentation'] ?? null);
$infos      = $contenu($parType['infos_complementaires'] ?? null);
$galerie    = $contenu($parType['galerie'] ?? null);
$personnal  = $contenu($parType['personnalise'] ?? null);
$aVenir     = $contenu($parType['offres_a_venir'] ?? null);

$reseaux  = json_lire($page['reseaux'] ?? null);
$horaires = json_lire($page['horaires'] ?? null);

$telephone = tel_lien($page['telephone'] ?? null);
$whatsapp  = $page['whatsapp'] ?? $page['telephone'] ?? null;

$messageWhatsapp = sprintf(
    'Bonjour %s, je vous contacte après avoir vu votre panneau Souriane.',
    (string) $page['nom']
);

$adresseComplete = trim(implode(', ', array_filter([
    (string) ($page['adresse'] ?? ''),
    (string) ($page['ville'] ?? ''),
])));
$itineraire = null;
if (!empty($page['latitude']) && !empty($page['longitude'])) {
    $itineraire = 'https://www.google.com/maps/dir/?api=1&destination='
        . rawurlencode($page['latitude'] . ',' . $page['longitude']);
} elseif ($adresseComplete !== '') {
    $itineraire = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($adresseComplete);
}

$jours = [
    1 => ['cle' => 'lun', 'nom' => 'Lundi'],
    2 => ['cle' => 'mar', 'nom' => 'Mardi'],
    3 => ['cle' => 'mer', 'nom' => 'Mercredi'],
    4 => ['cle' => 'jeu', 'nom' => 'Jeudi'],
    5 => ['cle' => 'ven', 'nom' => 'Vendredi'],
    6 => ['cle' => 'sam', 'nom' => 'Samedi'],
    7 => ['cle' => 'dim', 'nom' => 'Dimanche'],
];
$jourCourant = (int) date('N');

$reseauxActifs = [];
foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'site'] as $reseau) {
    if (!empty($reseaux[$reseau])) {
        $reseauxActifs[$reseau] = $reseaux[$reseau];
    }
}

$libellesReseaux = [
    'facebook' => 'Facebook', 'instagram' => 'Instagram',
    'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'site' => 'Site web',
];
?>

<div class="page-annonceur">
    <div class="page-annonceur__interieur">

        <!-- 1. Bandeau d’offre — la première chose visible -------------------- -->
        <?php if (!empty($page['bandeau_offre']) || !empty($offre['texte'])): ?>
            <div class="bandeau-offre">
                <span class="bandeau-offre__etiquette">
                    <?= Icone::svg('etincelle') ?>
                    Offre du moment
                </span>
                <div class="bandeau-offre__texte">
                    <?= e((string) ($page['bandeau_offre'] ?: ($offre['texte'] ?? ''))) ?>
                </div>
                <?php if (!empty($offre['debut']) || !empty($offre['fin'])): ?>
                    <div class="bandeau-offre__validite">
                        <?= Icone::svg('calendrier') ?>
                        <?php if (!empty($offre['debut']) && !empty($offre['fin'])): ?>
                            Du <?= e(date_fr((string) $offre['debut'])) ?> au <?= e(date_fr((string) $offre['fin'])) ?>
                        <?php elseif (!empty($offre['fin'])): ?>
                            Jusqu’au <?= e(date_fr((string) $offre['fin'])) ?>
                        <?php else: ?>
                            À partir du <?= e(date_fr((string) $offre['debut'])) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 2. Identité de l’annonceur --------------------------------------- -->
        <div class="identite">
            <div class="identite__logo">
                <?php if (!empty($page['logo'])): ?>
                    <img src="<?= e(url((string) $page['logo'])) ?>" alt="Logo <?= e((string) $page['nom']) ?>" width="62" height="62" loading="lazy">
                <?php else: ?>
                    <?= Icone::svg('panneau') ?>
                <?php endif; ?>
            </div>
            <div style="min-width:0">
                <h1 class="identite__nom"><?= e((string) $page['nom']) ?></h1>
                <div class="identite__meta">
                    <?php if (!empty($page['ville'])): ?>
                        <span><?= Icone::svg('carte') ?> <?= e((string) $page['ville']) ?></span>
                    <?php endif; ?>
                    <?php if ($code !== null): ?>
                        <span><?= Icone::svg('qr') ?> Code <?= e((string) $code['code']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 3. Boutons de contact — visibles sans défilement ------------------ -->
        <div class="actions-contact">
            <?php if ($telephone !== ''): ?>
                <a class="btn btn--primaire btn--action" href="tel:<?= e($telephone) ?>" data-action="clic_appel">
                    <?= Icone::svg('telephone') ?>
                    Appeler
                </a>
            <?php endif; ?>
            <?php if (whatsapp_lien($whatsapp) !== ''): ?>
                <a class="btn btn--whatsapp btn--action" href="<?= e(whatsapp_lien($whatsapp, $messageWhatsapp)) ?>" rel="noopener" data-action="clic_whatsapp">
                    <?= Icone::svg('whatsapp') ?>
                    WhatsApp
                </a>
            <?php endif; ?>
        </div>

        <!-- 4. Présentation --------------------------------------------------- -->
        <?php if (!empty($present['texte']) || !empty($parType['presentation']['titre'])): ?>
            <div class="bloc">
                <h2 class="bloc__titre"><?= Icone::svg('info') ?> <?= e((string) ($parType['presentation']['titre'] ?? 'Qui sommes-nous')) ?></h2>
                <div class="bloc__texte"><?= nl2br(e((string) ($present['texte'] ?? ''))) ?></div>
            </div>
        <?php elseif (!empty($page['description'])): ?>
            <div class="bloc">
                <h2 class="bloc__titre"><?= Icone::svg('info') ?> Qui sommes-nous</h2>
                <div class="bloc__texte"><?= nl2br(e((string) $page['description'])) ?></div>
            </div>
        <?php endif; ?>

        <!-- 5. Informations complémentaires et conditions --------------------- -->
        <?php if (!empty($infos['texte']) || !empty($offre['restrictions'])): ?>
            <div class="bloc">
                <h2 class="bloc__titre">
                    <?= Icone::svg('coche-cercle') ?>
                    <?= e((string) ($parType['infos_complementaires']['titre'] ?? 'Informations complémentaires')) ?>
                </h2>
                <?php if (!empty($infos['texte'])): ?>
                    <div class="bloc__texte"><?= nl2br(e((string) $infos['texte'])) ?></div>
                <?php endif; ?>

                <?php if (!empty($offre['restrictions']) || !empty($infos['conditions']) || !empty($infos['validite'])): ?>
                    <div class="conditions">
                        <div class="conditions__titre"><?= Icone::svg('alerte') ?> Conditions de l’offre</div>
                        <dl>
                            <?php if (!empty($offre['restrictions'])): ?>
                                <dt>Restrictions</dt><dd><?= e((string) $offre['restrictions']) ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($infos['conditions'])): ?>
                                <dt>Conditions</dt><dd><?= e((string) $infos['conditions']) ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($infos['validite'])): ?>
                                <dt>Validité</dt><dd><?= e((string) $infos['validite']) ?></dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 6. Galerie -------------------------------------------------------- -->
        <?php if (!empty($galerie['images']) && is_array($galerie['images'])): ?>
            <div class="bloc">
                <h2 class="bloc__titre"><?= Icone::svg('photo') ?> <?= e((string) ($parType['galerie']['titre'] ?? 'En images')) ?></h2>
                <div class="galerie">
                    <?php foreach (array_slice($galerie['images'], 0, 9) as $image): ?>
                        <?php $source = is_array($image) ? ($image['url'] ?? $image['src'] ?? '') : $image; ?>
                        <?php if ($source === '') { continue; } ?>
                        <a href="<?= e(url((string) $source)) ?>" target="_blank" rel="noopener">
                            <img src="<?= e(url((string) $source)) ?>" alt="<?= e((string) $page['nom']) ?>" loading="lazy" width="300" height="300">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 7. Contenu personnalisé ------------------------------------------ -->
        <?php if (!empty($personnal['texte'])): ?>
            <div class="bloc">
                <h2 class="bloc__titre"><?= Icone::svg('etincelle') ?> <?= e((string) ($parType['personnalise']['titre'] ?? 'Bon à savoir')) ?></h2>
                <div class="bloc__texte"><?= nl2br(e((string) $personnal['texte'])) ?></div>
            </div>
        <?php endif; ?>

        <!-- 8. Adresse et itinéraire ----------------------------------------- -->
        <?php if ($adresseComplete !== ''): ?>
            <div class="bloc">
                <h2 class="bloc__titre"><?= Icone::svg('carte') ?> Nous trouver</h2>
                <div class="adresse">
                    <div class="adresse__ligne">
                        <?= Icone::svg('carte') ?>
                        <span><?= e($adresseComplete) ?></span>
                    </div>
                    <?php if ($telephone !== ''): ?>
                        <div class="adresse__ligne">
                            <?= Icone::svg('telephone') ?>
                            <span><a href="tel:<?= e($telephone) ?>"><?= e((string) $page['telephone']) ?></a></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($page['email'])): ?>
                        <div class="adresse__ligne">
                            <?= Icone::svg('courriel') ?>
                            <span><a href="mailto:<?= e((string) $page['email']) ?>"><?= e((string) $page['email']) ?></a></span>
                        </div>
                    <?php endif; ?>

                    <div class="carte-statique" role="img" aria-label="Localisation approximative de <?= e((string) $page['nom']) ?>">
                        <?= Icone::svg('itineraire') ?>
                    </div>

                    <?php if ($itineraire !== null): ?>
                        <a class="btn btn--contour btn--large" href="<?= e($itineraire) ?>" target="_blank" rel="noopener" data-action="clic_itineraire">
                            <?= Icone::svg('itineraire') ?>
                            Ouvrir l’itinéraire
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 9. Horaires ------------------------------------------------------ -->
        <?php if ($horaires !== []): ?>
            <div class="bloc">
                <h2 class="bloc__titre"><?= Icone::svg('horloge') ?> Horaires d’ouverture</h2>
                <div class="horaires">
                    <?php foreach ($jours as $numero => $jour): ?>
                        <?php
                        $valeur   = (string) ($horaires[$jour['cle']] ?? '—');
                        $ferme    = in_array(mb_strtolower($valeur), ['fermé', 'ferme', '—', ''], true);
                        $classes  = 'horaires__ligne';
                        if ($numero === $jourCourant) {
                            $classes .= ' horaires__ligne--aujourdhui';
                        }
                        ?>
                        <div class="<?= $classes ?>">
                            <span class="horaires__jour"><?= e($jour['nom']) ?><?= $numero === $jourCourant ? ' (aujourd’hui)' : '' ?></span>
                            <span class="<?= $ferme ? 'horaires__valeur--ferme' : '' ?>"><?= e($ferme ? 'Fermé' : $valeur) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 10. Réseaux sociaux ---------------------------------------------- -->
        <?php if ($reseauxActifs !== []): ?>
            <div class="bloc">
                <h2 class="bloc__titre"><?= Icone::svg('partage') ?> Nous suivre</h2>
                <div class="reseaux">
                    <?php foreach ($reseauxActifs as $reseau => $adresse): ?>
                        <a href="<?= e((string) $adresse) ?>" target="_blank" rel="noopener" data-action="clic_reseau">
                            <?= Icone::svg($reseau) ?>
                            <?= e($libellesReseaux[$reseau] ?? $reseau) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 11. Offres à venir ------------------------------------------------ -->
        <?php if (!empty($aVenir['texte']) || !empty($aVenir['elements'])): ?>
            <div class="bloc">
                <h2 class="bloc__titre"><?= Icone::svg('calendrier') ?> <?= e((string) ($parType['offres_a_venir']['titre'] ?? 'Prochainement')) ?></h2>
                <?php if (!empty($aVenir['texte'])): ?>
                    <div class="bloc__texte"><?= nl2br(e((string) $aVenir['texte'])) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 12. Rester informé (ch. 5.3) -------------------------------------- -->
        <div class="rester-informe">
            <h3>Rester informé</h3>
            <p>
                Recevez les prochaines offres de <?= e((string) $page['nom']) ?> directement sur votre
                téléphone. Sans compte, et vous pouvez vous désinscrire à tout moment.
            </p>
            <button type="button" class="bouton-discret" id="ouvrir-rester-informe" data-action="clic_rester_informe">
                <?= Icone::svg('cloche') ?>
                Choisir comment rester informé
            </button>
        </div>

        <!-- 13. Mentions légales ---------------------------------------------- -->
        <p class="mentions">
            <?= e((string) $page['nom']) ?> — <?= e($adresseComplete !== '' ? $adresseComplete : (string) ($page['ville'] ?? '')) ?><br>
            Page publiée par la régie <?= e(APP_NAME) ?>. Données de visite mesurées de façon anonyme.
            <a href="<?= e(url('confidentialite')) ?>">En savoir plus</a>
        </p>
    </div>
</div>

<!-- ======================================================================
     Bouton WhatsApp permanent
     ====================================================================== -->
<?php if (whatsapp_lien($whatsapp) !== ''): ?>
    <a class="whatsapp-flottant" href="<?= e(whatsapp_lien($whatsapp, $messageWhatsapp)) ?>" rel="noopener" data-action="clic_whatsapp">
        <?= Icone::svg('whatsapp') ?>
        <span>WhatsApp</span>
    </a>
<?php endif; ?>

<!-- ======================================================================
     Panneau glissant « Rester informé » (ch. 5.3)
     ====================================================================== -->
<div class="voile" id="voile-informe" hidden></div>

<div class="feuille" id="feuille-informe" role="dialog" aria-modal="true" aria-labelledby="titre-feuille-informe" hidden>
    <div class="feuille__poignee"></div>

    <h3 id="titre-feuille-informe">Rester informé</h3>
    <p class="feuille__intro">
        Deux façons de garder le contact avec <?= e((string) $page['nom']) ?>. Choisissez celle qui vous convient.
    </p>

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
    <?php if (whatsapp_lien($whatsapp) !== ''): ?>
        <a class="action-informe action-informe--or" href="<?= e(whatsapp_lien($whatsapp, $messageWhatsapp)) ?>" rel="noopener" style="text-decoration:none" data-action="clic_whatsapp">
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
