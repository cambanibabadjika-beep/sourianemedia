<?php
/**
 * Vue — Vitrine publique.
 *
 * Variables :
 *   $qrDemo     string — SVG du QR code de démonstration
 *   $codeDemo   string — code court de démonstration
 *   $chiffres   array  — {panneaux, villes, annonceurs, campagnes}
 *   $villesListe array<string> — villes du parc, pour le bandeau défilant
 */

declare(strict_types=1);

use Souriane\Core\Icone;

$chiffres    = $chiffres ?? ['panneaux' => 0, 'villes' => 0, 'annonceurs' => 0, 'campagnes' => 0];
$villesListe = $villesListe ?? [];

$etapes = [
    [
        'icone' => 'qr',
        'titre' => 'Le passant scanne',
        'texte' => 'Un QR code lisible à plusieurs mètres, imprimé sur le panneau. Aucune application à installer, aucun compte à créer.',
    ],
    [
        'icone' => 'etincelle',
        'titre' => 'Il découvre l’offre',
        'texte' => 'En moins de trois secondes, il voit l’offre du moment, le nom de l’annonceur, puis les boutons d’appel et de WhatsApp.',
    ],
    [
        'icone' => 'cloche',
        'titre' => 'Il reste informé',
        'texte' => 'Il installe la page sur son téléphone et reçoit les prochaines offres. L’annonceur garde le contact après l’affichage.',
    ],
];

$avantages = [
    [
        'icone' => 'graphique',
        'variante' => '',
        'titre' => 'Des résultats mesurés',
        'texte' => 'Scans, visiteurs uniques, appels, messages WhatsApp et itinéraires : chaque interaction est comptée et restituée par panneau et par campagne.',
    ],
    [
        'icone' => 'panneau',
        'variante' => 'lilas',
        'titre' => 'Un parc vérifié',
        'texte' => 'Chaque support est photographié, géolocalisé et contrôlé à 15 et 30 jours. La disponibilité est consultable avant réservation.',
    ],
    [
        'icone' => 'etincelle',
        'variante' => 'or',
        'titre' => 'Une offre prolongée',
        'texte' => 'La campagne ne s’arrête plus au décrochage de l’affiche : les abonnés reçoivent les annonces suivantes, dans la limite de quatre par mois.',
    ],
];
?>

<!-- ======================================================================
     Héros
     ====================================================================== -->
<section class="heros">
    <div class="conteneur heros__grille">
        <div>
            <span class="heros__etat">
                <span class="pastille-vivante" aria-hidden="true"></span>
                Affichage extérieur connecté
            </span>
            <h1>Votre panneau vous <em>parle</em>.</h1>
            <p class="heros__texte">
                Souriane relie chaque support d’affichage à son audience. Le passant scanne un code,
                découvre votre offre, appelle, écrit sur WhatsApp — puis continue de vous suivre
                bien après que l’affiche a été décrochée.
            </p>

            <div class="heros__actions">
                <a class="btn btn--accent" href="<?= e(url('comment-ca-marche')) ?>">
                    <?= Icone::svg('etincelle') ?>
                    Comment ça marche
                </a>
                <a class="btn btn--blanc" href="<?= e(url('annonceurs')) ?>">
                    <?= Icone::svg('panneau') ?>
                    Réserver un panneau
                </a>
            </div>

            <div class="heros__puces">
                <span><?= Icone::svg('coche-cercle') ?> Sans application à installer</span>
                <span><?= Icone::svg('coche-cercle') ?> Codes uniques par panneau</span>
                <span><?= Icone::svg('coche-cercle') ?> Statistiques en temps réel</span>
            </div>
        </div>

        <div class="apercu-panneau">
            <div class="apercu-panneau__qr"><?= $qrDemo ?></div>
            <div class="apercu-panneau__lien"><?= e(url_courte($codeDemo)) ?></div>
            <p class="apercu-panneau__legende">
                Exemple de code imprimé sur un panneau.<br>Scannez-le avec votre téléphone.
            </p>
        </div>
    </div>
</section>

<!-- ======================================================================
     Comment ça marche
     ====================================================================== -->
<section class="section">
    <div class="conteneur">
        <div class="entete-section">
            <span class="entete-section__surtitre">Trois temps</span>
            <h2>Du panneau au téléphone, en trois gestes</h2>
            <p>Le parcours est pensé pour un passant pressé : pas d’installation, pas d’inscription, pas d’attente.</p>
        </div>

        <div class="etapes">
            <?php foreach ($etapes as $rang => $etape): ?>
                <div class="etape reveler" style="--retard:<?= $rang * 130 ?>ms">
                    <h3><?= e($etape['titre']) ?></h3>
                    <p><?= e($etape['texte']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ======================================================================
     Aperçu de la page annonceur
     ====================================================================== -->
<section class="section section--gris">
    <div class="conteneur">
        <div class="entete-section">
            <span class="entete-section__surtitre">Ce que voit le passant</span>
            <h2>Une page claire, lisible en une seconde</h2>
            <p>
                L’ordre d’affichage est imposé : l’offre d’abord, puis l’identité de l’annonceur,
                les boutons de contact, les informations pratiques et enfin les mentions légales.
            </p>
        </div>

        <div class="grille grille--3">
            <div class="carte carte--liee reveler inclinable" style="--retard:0ms">
                <div class="carte__icone carte__icone--or"><?= Icone::svg('etincelle') ?></div>
                <h3>1 · L’offre</h3>
                <p>Un bandeau reprend la mention du panneau : remise, menu du jour, campagne en cours, avec sa date de validité.</p>
            </div>
            <div class="carte carte--liee reveler inclinable" style="--retard:120ms">
                <div class="carte__icone"><?= Icone::svg('telephone') ?></div>
                <h3>2 · Le contact</h3>
                <p>Appeler ou écrire sur WhatsApp, sans copier de numéro. L’itinéraire s’ouvre dans l’application de cartes du téléphone.</p>
            </div>
            <div class="carte carte--liee reveler inclinable" style="--retard:240ms">
                <div class="carte__icone carte__icone--lilas"><?= Icone::svg('horloge') ?></div>
                <h3>3 · Les informations</h3>
                <p>Horaires, adresse, réseaux sociaux, photos. Le jour courant est mis en évidence dans le tableau des horaires.</p>
            </div>
        </div>
    </div>
</section>

<!-- ======================================================================
     Avantages annonceurs
     ====================================================================== -->
<section class="section">
    <div class="conteneur">
        <div class="entete-section">
            <span class="entete-section__surtitre">Pour les annonceurs</span>
            <h2>Ce que l’affichage classique ne sait pas faire</h2>
        </div>

        <div class="grille grille--3">
            <?php foreach ($avantages as $rang => $avantage): ?>
                <div class="carte carte--liee reveler inclinable" style="--retard:<?= $rang * 120 ?>ms">
                    <div class="carte__icone<?= $avantage['variante'] !== '' ? ' carte__icone--' . e($avantage['variante']) : '' ?>">
                        <?= Icone::svg($avantage['icone']) ?>
                    </div>
                    <h3><?= e($avantage['titre']) ?></h3>
                    <p><?= e($avantage['texte']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ======================================================================
     Chiffres du parc
     ----------------------------------------------------------------------
     Bande violette profonde + cartes de verre clair. Le nombre est en violet
     sur verre (7,2:1) et l’étiquette en gris-700 (7,9:1) : les deux passent
     le seuil WCAG AA. Les valeurs sont doublées — un attribut data-compteur
     pour l’animation, et le texte déjà formaté en repli si le JS ne tourne pas.
     ====================================================================== -->
<section class="section section--serree section--profil" aria-labelledby="titre-chiffres">
    <div class="conteneur">
        <h2 id="titre-chiffres" class="sr-only">Le parc Souriane en chiffres</h2>

        <div class="chiffres">
            <?php
            $blocs = [
                ['valeur' => $chiffres['panneaux'],   'etiquette' => 'Panneaux en parc',    'icone' => 'panneau'],
                ['valeur' => $chiffres['villes'],     'etiquette' => 'Villes couvertes',    'icone' => 'carte'],
                ['valeur' => $chiffres['annonceurs'], 'etiquette' => 'Annonceurs actifs',   'icone' => 'utilisateurs'],
                ['valeur' => $chiffres['campagnes'],  'etiquette' => 'Campagnes diffusées', 'icone' => 'graphique'],
            ];
            ?>
            <?php foreach ($blocs as $rang => $bloc): ?>
                <div class="chiffre verre verre--fort reveler" style="--retard:<?= $rang * 110 ?>ms">
                    <span class="chiffre__icone" aria-hidden="true"><?= Icone::svg($bloc['icone']) ?></span>
                    <span class="chiffre__valeur" data-compteur="<?= e((string) (int) $bloc['valeur']) ?>"><?= e(number_format((float) $bloc['valeur'], 0, ',', ' ')) ?></span>
                    <span class="chiffre__etiquette"><?= e($bloc['etiquette']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($villesListe !== []): ?>
            <div class="defileur defileur--villes" aria-label="Villes couvertes par le parc">
                <div class="defileur__piste">
                    <?php
                    // La piste est dupliquée : la seconde moitié prend le relais
                    // quand la première sort de l’écran, ce qui rend la boucle
                    // invisible. La copie est masquée aux lecteurs d’écran.
                    $villesBoucle = array_merge($villesListe, $villesListe);
                    ?>
                    <?php foreach ($villesBoucle as $i => $ville): ?>
                        <span class="defileur__element<?= $i >= count($villesListe) ? ' defileur__element--copie' : '' ?>">
                            <?= Icone::svg('carte') ?>
                            <?= e((string) $ville) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ======================================================================
     Appel à l’action final
     ====================================================================== -->
<section class="section">
    <div class="conteneur">
        <div class="carte carte--appel reveler">
            <h2>Prêt à faire parler vos panneaux ?</h2>
            <p class="carte--appel__texte">
                Dites-nous votre zone, votre période et votre objectif. Nous vous proposons les supports
                disponibles et le prix calculé automatiquement.
            </p>
            <div class="pile carte--appel__actions">
                <a class="btn btn--primaire" href="<?= e(url('annonceurs/demande')) ?>">
                    <?= Icone::svg('calendrier') ?>
                    Demander une proposition
                </a>
                <?php if (WHATSAPP_REGIE !== ''): ?>
                    <a class="btn btn--whatsapp" href="<?= e(whatsapp_lien(WHATSAPP_REGIE, 'Bonjour Souriane, je souhaite un devis d’affichage.')) ?>" rel="noopener">
                        <?= Icone::svg('whatsapp') ?>
                        Écrire sur WhatsApp
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
