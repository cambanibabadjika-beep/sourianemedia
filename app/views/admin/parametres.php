<?php
/**
 * Vue — Paramètres de la plateforme (EF-G06).
 *
 * Variables : $lignes, $vapid, $systeme
 */

declare(strict_types=1);

use Souriane\Core\Icone;

/**
 * Réglages éditables regroupés par thème.
 * Les clés VAPID n’y figurent pas : elles sont lues depuis .env et affichées
 * en lecture seule, pour éviter qu’un enregistrement accidentel ne remplace
 * la clé privée par sa version masquée.
 */
$groupes = [
    'Identité de la plateforme' => [
        'icone' => 'site',
        'aide'  => 'Ces valeurs alimentent le titre du site, le pied de page et les métadonnées.',
        'cles'  => ['site_nom', 'site_slogan'],
    ],
    'Contact de la régie' => [
        'icone' => 'telephone',
        'aide'  => 'Utilisés pour les demandes commerciales et le repli WhatsApp.',
        'cles'  => ['contact_email', 'whatsapp_regie'],
    ],
    'Diffusion des notifications' => [
        'icone' => 'cloche',
        'aide'  => 'Le plafond mensuel est une exigence du cahier des charges : il protège l’audience du démarchage excessif.',
        'cles'  => ['plafond_notifications'],
    ],
];

/** Indexe les lignes par clé pour un accès direct. */
$parCle = [];
foreach ($lignes as $ligne) {
    $parCle[(string) $ligne['cle']] = $ligne;
}

/** Toutes les clés présentes en base, pour repérer les réglages non listés. */
$clesListees = [];
foreach ($groupes as $groupe) {
    $clesListees = array_merge($clesListees, $groupe['cles']);
}
$clesListees[] = 'cle_publique_vapid';
$clesListees[] = 'cle_privee_vapid';
?>

<div class="indicateurs">
    <div class="indicateur">
        <div class="indicateur__icone"><?= Icone::svg('reglages') ?></div>
        <div class="indicateur__etiquette">Environnement</div>
        <div class="indicateur__valeur">PHP <?= e($systeme['php']) ?></div>
        <div class="indicateur__detail">MySQL <?= e((string) $systeme['mysql']) ?></div>
    </div>

    <div class="indicateur <?= $systeme['https'] ? 'indicateur--lilas' : 'indicateur--or' ?>">
        <div class="indicateur__icone"><?= Icone::svg($systeme['https'] ? 'verrou' : 'alerte') ?></div>
        <div class="indicateur__etiquette">Connexion sécurisée</div>
        <div class="indicateur__valeur"><?= $systeme['https'] ? 'HTTPS' : 'HTTP' ?></div>
        <div class="indicateur__detail">
            <?= $systeme['https']
                ? 'Service worker et notifications disponibles'
                : 'Notifications push inopérantes hors HTTPS' ?>
        </div>
    </div>

    <div class="indicateur indicateur--gris">
        <div class="indicateur__icone"><?= Icone::svg('cloche') ?></div>
        <div class="indicateur__etiquette">Abonnés actifs</div>
        <div class="indicateur__valeur"><?= e((string) $systeme['abonnes']) ?></div>
        <div class="indicateur__detail">Appareils ayant accepté les notifications</div>
    </div>

    <div class="indicateur indicateur--gris">
        <div class="indicateur__icone"><?= Icone::svg('archive') ?></div>
        <div class="indicateur__etiquette">Réglages en base</div>
        <div class="indicateur__valeur"><?= e((string) count($lignes)) ?></div>
        <div class="indicateur__detail">Clés de configuration persistées</div>
    </div>
</div>

<?php if (!$systeme['https']): ?>
    <div class="encart encart--avertissement">
        <strong>Environnement non sécurisé.</strong> Le service worker, l’installation de la PWA et les
        notifications push exigent une connexion HTTPS (ou <span class="monospace">localhost</span>).
        En production, activez un certificat avant la mise en service.
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/parametres')) ?>">
    <?= \Souriane\Core\Csrf::champ() ?>

    <?php foreach ($groupes as $titreGroupe => $groupe): ?>
        <div class="panneau">
            <div class="panneau__entete">
                <h2 class="panneau__titre"><?= Icone::svg($groupe['icone']) ?> <?= e($titreGroupe) ?></h2>
            </div>
            <div class="panneau__corps">
                <p class="texte-petit" style="margin-bottom:18px"><?= e($groupe['aide']) ?></p>

                <div class="grille-champs">
                    <?php foreach ($groupe['cles'] as $cle): ?>
                        <?php
                        $ligne   = $parCle[$cle] ?? null;
                        $libelle = $ligne !== null && $ligne['libelle'] !== null ? (string) $ligne['libelle'] : $cle;
                        $valeur  = $ligne !== null ? (string) ($ligne['valeur'] ?? '') : '';
                        $id      = 'param-' . str_replace('_', '-', $cle);
                        ?>
                        <div class="champ">
                            <label class="champ__etiquette" for="<?= e($id) ?>"><?= e($libelle) ?></label>
                            <?php if ($cle === 'plafond_notifications'): ?>
                                <input class="saisie" type="number" id="<?= e($id) ?>"
                                       name="parametres[<?= e($cle) ?>]" value="<?= e($valeur) ?>"
                                       min="0" max="12" step="1">
                                <div class="champ__aide">
                                    Nombre maximal de notifications par mois et par annonceur.
                                    Valeur recommandée : 4. Zéro suspend l’envoi.
                                </div>
                            <?php else: ?>
                                <input class="saisie" type="text" id="<?= e($id) ?>"
                                       name="parametres[<?= e($cle) ?>]" value="<?= e($valeur) ?>"
                                       maxlength="255" autocomplete="off">
                                <div class="champ__aide monospace">clé : <?= e($cle) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="pile pile--droite" style="margin-bottom:26px">
        <button type="submit" class="btn btn--primaire"><?= Icone::svg('valider') ?> Enregistrer les paramètres</button>
    </div>
</form>

<!-- Clés de signature des notifications ------------------------------------- -->
<div class="panneau">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('cloche') ?> Clés de signature VAPID</h2>
    </div>
    <div class="panneau__corps">
        <p class="texte-petit" style="margin-bottom:18px">
            Les clés VAPID signent les notifications push (RFC 8292). Elles sont conservées hors du dépôt
            de code, dans le fichier <span class="monospace">.env</span>, et affichées ici en lecture seule.
            Pour les régénérer : <span class="monospace">php cron/generer_cles.php</span>. Une rotation
            invalide les abonnements existants.
        </p>

        <div class="champ">
            <label class="champ__etiquette" for="vapid-publique">Clé publique</label>
            <div class="pile" style="gap:8px;align-items:stretch">
                <input class="saisie monospace" type="text" id="vapid-publique" readonly
                       value="<?= e((string) $vapid['publique']) ?>">
                <button type="button" class="btn btn--contour btn--petit" data-copier="vapid-publique">
                    <?= Icone::svg('partage') ?> Copier
                </button>
            </div>
            <div class="champ__aide">
                Transmise au navigateur lors de l’abonnement. Elle peut être publiée sans risque.
            </div>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="vapid-privee">Clé privée</label>
            <input class="saisie monospace" type="text" id="vapid-privee" readonly
                   value="<?= e((string) $vapid['privee']) ?>">
            <div class="champ__aide">
                Masquée volontairement : elle ne quitte jamais le serveur et n’est jamais affichée en clair
                dans le back-office.
            </div>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="vapid-sujet">Contact d’administration (sujet)</label>
            <input class="saisie monospace" type="text" id="vapid-sujet" readonly
                   value="<?= e((string) $vapid['sujet']) ?>">
            <div class="champ__aide">
                Adresse de contact exigée par le protocole, sous la forme
                <span class="monospace">mailto:</span> ou <span class="monospace">https:</span>.
            </div>
        </div>

        <?php if ((string) $vapid['publique'] === '' || (string) $vapid['privee'] === ''): ?>
            <div class="encart encart--avertissement" style="margin-bottom:0">
                <strong>Clés incomplètes.</strong> Aucune notification ne peut être envoyée tant que la paire
                de clés n’est pas renseignée dans <span class="monospace">.env</span>.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Réglages présents en base mais non pilotés par cet écran --------------- -->
<?php $orphelines = array_diff(array_keys($parCle), $clesListees); ?>
<?php if ($orphelines !== []): ?>
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('archive') ?> Autres réglages en base</h2>
        </div>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Clé</th>
                        <th>Libellé</th>
                        <th>Valeur</th>
                        <th>Mise à jour</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orphelines as $cle): ?>
                        <?php $ligne = $parCle[$cle]; ?>
                        <tr>
                            <td class="monospace"><?= e($cle) ?></td>
                            <td class="texte-petit"><?= e((string) ($ligne['libelle'] ?? '—')) ?></td>
                            <td class="monospace texte-petit"><?= e(mb_strimwidth((string) ($ligne['valeur'] ?? ''), 0, 48, '…')) ?></td>
                            <td class="texte-petit">
                                <?= $ligne['maj_le'] !== null ? e(date_fr((string) $ligne['maj_le'], true)) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="encart">
    <strong>Configuration sensible.</strong> Les identifiants de base de données, la clé privée VAPID et le
    secret de hachage des empreintes réseau résident exclusivement dans le fichier
    <span class="monospace">.env</span>, qui n’est pas servi par le serveur web. Aucune valeur secrète
    n’est stockée ni affichée en clair dans cette interface.
</div>

<script>
(function () {
    'use strict';

    /* Copie d’une valeur en lecture seule, avec repli si l’API presse-papiers
       n’est pas disponible (contexte non sécurisé). */
    var boutons = document.querySelectorAll('[data-copier]');
    for (var i = 0; i < boutons.length; i++) {
        boutons[i].addEventListener('click', function () {
            var source = document.getElementById(this.getAttribute('data-copier'));
            if (!source) { return; }
            var bouton = this;
            var libelle = bouton.innerHTML;

            function confirmer() {
                bouton.innerHTML = 'Copié';
                window.setTimeout(function () { bouton.innerHTML = libelle; }, 1600);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(source.value).then(confirmer, function () {
                    source.select();
                    document.execCommand('copy');
                    confirmer();
                });
            } else {
                source.select();
                document.execCommand('copy');
                confirmer();
            }
        });
    }
})();
</script>
