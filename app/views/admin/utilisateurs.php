<?php
/**
 * Vue — Comptes d’administration et rôles (EF-G01).
 *
 * Variables : $utilisateurs, $roles, $annonceurs
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Icone;
use Souriane\Models\Utilisateur;

/** Libellé court d’un rôle, pour les pastilles du tableau. */
$libellesCourts = [
    'regie'     => 'Régie',
    'agent'     => 'Agent terrain',
    'annonceur' => 'Annonceur',
];

$nbActifs   = 0;
$nbInactifs = 0;
$parRole    = ['regie' => 0, 'agent' => 0, 'annonceur' => 0];
foreach ($utilisateurs as $compte) {
    if ((int) $compte['actif'] === 1) {
        $nbActifs++;
    } else {
        $nbInactifs++;
    }
    if (isset($parRole[$compte['role']])) {
        $parRole[$compte['role']]++;
    }
}
?>

<div class="indicateurs">
    <div class="indicateur">
        <div class="indicateur__icone"><?= Icone::svg('utilisateurs') ?></div>
        <div class="indicateur__etiquette">Comptes actifs</div>
        <div class="indicateur__valeur"><?= e((string) $nbActifs) ?></div>
        <div class="indicateur__detail"><?= e((string) $nbInactifs) ?> désactivé<?= $nbInactifs > 1 ? 's' : '' ?></div>
    </div>

    <div class="indicateur indicateur--lilas">
        <div class="indicateur__icone"><?= Icone::svg('verrou') ?></div>
        <div class="indicateur__etiquette">Régie</div>
        <div class="indicateur__valeur"><?= e((string) $parRole['regie']) ?></div>
        <div class="indicateur__detail">Accès complet</div>
    </div>

    <div class="indicateur indicateur--or">
        <div class="indicateur__icone"><?= Icone::svg('panneau') ?></div>
        <div class="indicateur__etiquette">Agents terrain</div>
        <div class="indicateur__valeur"><?= e((string) $parRole['agent']) ?></div>
        <div class="indicateur__detail">Poses et contrôles</div>
    </div>

    <div class="indicateur indicateur--gris">
        <div class="indicateur__icone"><?= Icone::svg('site') ?></div>
        <div class="indicateur__etiquette">Annonceurs</div>
        <div class="indicateur__valeur"><?= e((string) $parRole['annonceur']) ?></div>
        <div class="indicateur__detail">Leurs propres campagnes</div>
    </div>
</div>

<div class="panneau">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('verrou') ?> Comptes</h2>
        <?php if (count($utilisateurs) > 4): ?>
            <div class="champ filtres__recherche" style="margin:0">
                <label class="sr-only" for="filtre-comptes">Filtrer les comptes</label>
                <input class="saisie" type="search" id="filtre-comptes" placeholder="Filtrer par nom, e-mail ou rôle…"
                       autocomplete="off" data-filtre-tableau="tableau-comptes">
            </div>
        <?php endif; ?>
    </div>

    <?php if ($utilisateurs === []): ?>
        <div class="panneau__corps">
            <div class="etat-vide">
                <?= Icone::svg('utilisateurs') ?>
                <h3>Aucun compte</h3>
                <p>Créez un premier compte pour donner accès au back-office.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="tableau-enveloppe">
            <table class="tableau" id="tableau-comptes">
                <thead>
                    <tr>
                        <th>Compte</th>
                        <th>Rôle</th>
                        <th>Rattaché à</th>
                        <th>Téléphone</th>
                        <th>État</th>
                        <th>Dernière connexion</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($utilisateurs as $compte): ?>
                        <?php
                        $estMoi     = (int) $compte['id'] === (int) \Souriane\Core\Auth::id();
                        $estVerrou  = !empty($compte['verrouille_jusqua'])
                            && strtotime((string) $compte['verrouille_jusqua']) > time();
                        ?>
                        <tr data-ligne-recherche="<?= e(mb_strtolower((string) $compte['nom'] . ' ' . (string) $compte['email'] . ' ' . ($libellesCourts[$compte['role']] ?? ''))) ?>">
                            <td>
                                <span class="tableau__principal"><?= e((string) $compte['nom']) ?></span>
                                <?php if ($estMoi): ?>
                                    <span class="etat etat--disponible" style="margin-left:6px">Vous</span>
                                <?php endif; ?>
                                <div class="tableau__secondaire"><?= e((string) $compte['email']) ?></div>
                            </td>
                            <td><?= e($libellesCourts[$compte['role']] ?? (string) $compte['role']) ?></td>
                            <td>
                                <?php if ($compte['role'] === 'annonceur' && !empty($compte['annonceur_nom'])): ?>
                                    <?= e((string) $compte['annonceur_nom']) ?>
                                <?php else: ?>
                                    <span class="texte-petit">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="texte-petit"><?= e((string) ($compte['telephone'] ?? '—')) ?></td>
                            <td>
                                <?php if ($estVerrou): ?>
                                    <span class="etat etat--annulee">Verrouillé</span>
                                <?php elseif ((int) $compte['actif'] === 1): ?>
                                    <span class="etat etat--terminee">Actif</span>
                                <?php else: ?>
                                    <span class="etat etat--brouillon">Désactivé</span>
                                <?php endif; ?>
                            </td>
                            <td class="texte-petit">
                                <?= $compte['derniere_connexion'] !== null
                                    ? e(date_fr((string) $compte['derniere_connexion'], true))
                                    : 'Jamais' ?>
                            </td>
                            <td>
                                <div class="tableau__actions">
                                    <button type="button" class="btn btn--contour btn--petit"
                                            data-ouvrir-dialogue="dialogue-utilisateur"
                                            data-compte="<?= e(json_encode([
                                                'id'          => (int) $compte['id'],
                                                'nom'         => (string) $compte['nom'],
                                                'email'       => (string) $compte['email'],
                                                'role'        => (string) $compte['role'],
                                                'annonceur_id' => $compte['annonceur_id'] !== null ? (int) $compte['annonceur_id'] : '',
                                                'telephone'   => (string) ($compte['telephone'] ?? ''),
                                                'actif'       => (int) $compte['actif'],
                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>"
                                            title="Modifier le compte">
                                        <?= Icone::svg('modifier') ?>
                                        <span class="sr-only">Modifier</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="panneau__corps" data-aucun-resultat="tableau-comptes" hidden>
            <p class="texte-petit">Aucun compte ne correspond à ce filtre.</p>
        </div>
    <?php endif; ?>
</div>

<div class="encart">
    <strong>Rôles et portée.</strong> Un compte <em>régie</em> dispose de l’accès complet au back-office.
    Un <em>agent terrain</em> accède aux poses, aux contrôles et au journal. Un compte <em>annonceur</em>
    est rattaché à un annonceur unique et ne consulte que ses propres campagnes et statistiques.
    Un compte est verrouillé automatiquement après cinq tentatives de connexion infructueuses en quinze minutes.
</div>

<!-- Dialogue de création / modification d’un compte ------------------------- -->
<dialog id="dialogue-utilisateur" style="border:0;border-radius:var(--rayon);padding:0;max-width:560px;width:calc(100% - 32px)">
    <form method="post" action="<?= e(url('admin/utilisateurs')) ?>" style="padding:24px" id="formulaire-utilisateur">
        <?= Csrf::champ() ?>
        <input type="hidden" name="id" id="utilisateur_id" value="0">

        <h2 class="panneau__titre" style="margin-bottom:18px">
            <?= Icone::svg('utilisateurs') ?> <span id="utilisateur_titre">Nouveau compte</span>
        </h2>

        <div class="grille-champs">
            <div class="champ">
                <label class="champ__etiquette" for="utilisateur_nom">Nom complet <span class="requis">*</span></label>
                <input class="saisie" type="text" id="utilisateur_nom" name="nom" required maxlength="120"
                       autocomplete="off" placeholder="Awa Diop">
            </div>
            <div class="champ">
                <label class="champ__etiquette" for="utilisateur_telephone">Téléphone</label>
                <input class="saisie" type="tel" id="utilisateur_telephone" name="telephone" maxlength="32"
                       autocomplete="off" placeholder="+221 77 000 00 00">
            </div>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="utilisateur_email">Adresse e-mail <span class="requis">*</span></label>
            <input class="saisie" type="email" id="utilisateur_email" name="email" required maxlength="190"
                   autocomplete="off" placeholder="prenom.nom@souriane.sn">
        </div>

        <div class="grille-champs">
            <div class="champ">
                <label class="champ__etiquette" for="utilisateur_role">Rôle <span class="requis">*</span></label>
                <select class="liste" id="utilisateur_role" name="role" required>
                    <?php foreach ($roles as $cle => $libelle): ?>
                        <option value="<?= e((string) $cle) ?>"><?= e((string) $libelle) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="champ" id="bloc-annonceur" hidden>
                <label class="champ__etiquette" for="utilisateur_annonceur">Annonceur rattaché <span class="requis">*</span></label>
                <select class="liste" id="utilisateur_annonceur" name="annonceur_id">
                    <option value="">— Choisir —</option>
                    <?php foreach ($annonceurs as $annonceur): ?>
                        <option value="<?= e((string) $annonceur['id']) ?>"><?= e((string) $annonceur['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="champ__aide">Obligatoire pour un compte annonceur : il borne la portée des données visibles.</div>
            </div>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="utilisateur_mot_de_passe">
                Mot de passe <span class="requis" id="utilisateur_mdp_requis">*</span>
            </label>
            <input class="saisie" type="password" id="utilisateur_mot_de_passe" name="mot_de_passe"
                   minlength="10" autocomplete="new-password" placeholder="Au moins 10 caractères">
            <div class="champ__aide" id="utilisateur_mdp_aide">
                Au moins 10 caractères. Le mot de passe est haché en Argon2id et n’est jamais conservé en clair.
            </div>
        </div>

        <div class="champ">
            <label class="case" for="utilisateur_actif" style="display:flex;gap:10px;align-items:center">
                <input type="checkbox" id="utilisateur_actif" name="actif" value="1" checked>
                <span>Compte actif — autorisé à se connecter</span>
            </label>
        </div>

        <div class="pile pile--droite">
            <button type="button" class="btn btn--contour" data-fermer-dialogue>Annuler</button>
            <button type="submit" class="btn btn--primaire"><?= Icone::svg('valider') ?> Enregistrer</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    'use strict';

    var dialogue   = document.getElementById('dialogue-utilisateur');
    var formulaire = document.getElementById('formulaire-utilisateur');
    if (!dialogue || !formulaire) { return; }

    var champId     = document.getElementById('utilisateur_id');
    var champNom    = document.getElementById('utilisateur_nom');
    var champEmail  = document.getElementById('utilisateur_email');
    var champTel    = document.getElementById('utilisateur_telephone');
    var champRole   = document.getElementById('utilisateur_role');
    var champAnnon  = document.getElementById('utilisateur_annonceur');
    var champMdp    = document.getElementById('utilisateur_mot_de_passe');
    var champActif  = document.getElementById('utilisateur_actif');
    var blocAnnon   = document.getElementById('bloc-annonceur');
    var titre       = document.getElementById('utilisateur_titre');
    var mdpRequis   = document.getElementById('utilisateur_mdp_requis');
    var mdpAide     = document.getElementById('utilisateur_mdp_aide');

    /* Le rattachement à un annonceur ne concerne que les comptes annonceurs. */
    function majRole() {
        var estAnnonceur = champRole.value === 'annonceur';
        blocAnnon.hidden = !estAnnonceur;
        champAnnon.required = estAnnonceur;
        if (!estAnnonceur) { champAnnon.value = ''; }
    }
    champRole.addEventListener('change', majRole);

    /* Ouverture : création (vierge) ou modification (pré-remplie). */
    var ouvreurs = document.querySelectorAll('[data-ouvrir-dialogue="dialogue-utilisateur"]');
    for (var i = 0; i < ouvreurs.length; i++) {
        ouvreurs[i].addEventListener('click', function () {
            var brut = this.getAttribute('data-compte');
            if (brut) {
                var c;
                try { c = JSON.parse(brut); } catch (err) { c = null; }
                if (c) {
                    champId.value      = c.id;
                    champNom.value     = c.nom;
                    champEmail.value   = c.email;
                    champTel.value     = c.telephone || '';
                    champRole.value    = c.role;
                    champAnnon.value   = c.annonceur_id === '' ? '' : String(c.annonceur_id);
                    champActif.checked = c.actif === 1;
                    champMdp.value     = '';
                    champMdp.required  = false;
                    mdpRequis.hidden   = true;
                    mdpAide.textContent = 'Laisser vide pour conserver le mot de passe actuel.';
                    titre.textContent  = 'Modifier le compte';
                }
            } else {
                formulaire.reset();
                champId.value     = '0';
                champActif.checked = true;
                champMdp.required = true;
                mdpRequis.hidden  = false;
                mdpAide.textContent = 'Au moins 10 caractères. Le mot de passe est haché en Argon2id et n’est jamais conservé en clair.';
                titre.textContent = 'Nouveau compte';
            }
            majRole();
            if (dialogue.showModal) { dialogue.showModal(); }
        });
    }

    var fermeurs = dialogue.querySelectorAll('[data-fermer-dialogue]');
    for (var j = 0; j < fermeurs.length; j++) {
        fermeurs[j].addEventListener('click', function () { dialogue.close(); });
    }

    /* Filtre instantané du tableau. */
    var filtre = document.querySelector('[data-filtre-tableau="tableau-comptes"]');
    if (filtre) {
        var tableau = document.getElementById('tableau-comptes');
        var vide    = document.querySelector('[data-aucun-resultat="tableau-comptes"]');
        filtre.addEventListener('input', function () {
            var terme = this.value.trim().toLowerCase();
            var lignes = tableau.querySelectorAll('tbody tr');
            var visibles = 0;
            for (var k = 0; k < lignes.length; k++) {
                var trouve = lignes[k].getAttribute('data-ligne-recherche').indexOf(terme) !== -1;
                lignes[k].hidden = !trouve;
                if (trouve) { visibles++; }
            }
            if (vide) { vide.hidden = visibles !== 0; }
        });
    }
})();
</script>
