<?php
/**
 * Vue — Sécurité du compte : vérification en deux étapes (ch. 8).
 *
 *   « Les comptes d’administration sont protégés par une authentification à
 *     deux facteurs. »
 *
 * Variables :
 *   $courriel      string
 *   $role          string — clé du rôle
 *   $actif         bool   — le second facteur est-il opérationnel
 *   $obligatoire   bool   — le rôle impose-t-il le second facteur
 *   $confirmeLe    string|null — date d’activation
 *   $codesRestants int
 *   $codesNeufs    list<string> — remis une seule fois, juste après activation
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Icone;
use Souriane\Models\Utilisateur;

$courriel      = (string) ($courriel ?? '');
$role          = (string) ($role ?? '');
$actif         = (bool) ($actif ?? false);
$obligatoire   = (bool) ($obligatoire ?? false);
$confirmeLe    = $confirmeLe ?? null;
$codesRestants = (int) ($codesRestants ?? 0);
$codesNeufs    = is_array($codesNeufs ?? null) ? $codesNeufs : [];

$roleLibelle = Utilisateur::ROLES[$role] ?? $role;
?>

<!-- Codes de secours neufs : affichés une seule fois ---------------------- -->
<?php if ($codesNeufs !== []): ?>
    <div class="panneau panneau--accent">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('archive') ?> Vos codes de secours</h2>
        </div>
        <div class="panneau__corps">
            <p style="margin-bottom:14px">
                Chaque code remplace le code de votre application, une seule fois. Ils sont
                nécessaires si vous perdez ou changez de téléphone.
            </p>

            <ul class="codes-secours">
                <?php foreach ($codesNeufs as $code): ?>
                    <li class="monospace"><?= e($code) ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="encart encart--avertissement">
                <strong>Notez-les maintenant.</strong> Cette liste ne sera plus jamais affichée :
                les codes sont rangés hachés, et l’application ne peut pas les reconstituer.
                Si vous les perdez, il faudra en régénérer un jeu — ce qui invalidera celui-ci.
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- État du second facteur ------------------------------------------------- -->
<div class="indicateurs">
    <div class="indicateur <?= $actif ? 'indicateur--lilas' : 'indicateur--or' ?>">
        <div class="indicateur__icone"><?= Icone::svg($actif ? 'coche-cercle' : 'alerte') ?></div>
        <div class="indicateur__etiquette">Vérification en deux étapes</div>
        <div class="indicateur__valeur"><?= $actif ? 'Active' : 'Inactive' ?></div>
        <div class="indicateur__detail">
            <?php if ($actif && $confirmeLe !== null): ?>
                Activée le <?= e(date_fr((string) $confirmeLe)) ?>
            <?php elseif ($obligatoire): ?>
                Obligatoire pour le rôle « <?= e($roleLibelle) ?> »
            <?php else: ?>
                Facultative pour ce compte
            <?php endif; ?>
        </div>
    </div>

    <div class="indicateur indicateur--gris">
        <div class="indicateur__icone"><?= Icone::svg('utilisateurs') ?></div>
        <div class="indicateur__etiquette">Compte</div>
        <div class="indicateur__valeur"><?= e($roleLibelle) ?></div>
        <div class="indicateur__detail"><?= e($courriel) ?></div>
    </div>

    <?php if ($actif): ?>
        <div class="indicateur <?= $codesRestants > 0 ? 'indicateur--gris' : 'indicateur--or' ?>">
            <div class="indicateur__icone"><?= Icone::svg('archive') ?></div>
            <div class="indicateur__etiquette">Codes de secours</div>
            <div class="indicateur__valeur"><?= e((string) $codesRestants) ?></div>
            <div class="indicateur__detail">
                <?= $codesRestants > 0 ? 'Encore utilisables' : 'Aucun : régénérez-en un jeu' ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($obligatoire && !$actif): ?>
    <div class="encart encart--avertissement">
        <strong>Mise en place requise.</strong> Le chapitre 8 du cahier des charges impose une
        authentification à deux facteurs sur les comptes d’administration. Sans elle, la connexion
        s’arrête après le mot de passe.
    </div>
<?php endif; ?>

<!-- Mise en place ---------------------------------------------------------- -->
<?php if (!$actif): ?>
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('verrou') ?> Mettre en place la vérification</h2>
        </div>
        <div class="panneau__corps">
            <p class="texte-petit" style="margin-bottom:18px">
                Vous aurez besoin d’une application d’authentification — Google Authenticator, Authy,
                FreeOTP… Le mot de passe est redemandé : mettre en place un second facteur engage
                l’accès au compte.
            </p>

            <form method="post" action="<?= e(url('admin/securite/activer')) ?>" autocomplete="off">
                <?= Csrf::champ() ?>

                <div class="champ" style="max-width:360px">
                    <label class="champ__etiquette" for="mdp-activer">
                        Mot de passe du compte <span class="requis">*</span>
                    </label>
                    <input class="saisie" type="password" id="mdp-activer" name="mot_de_passe"
                           required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn--primaire">
                    <?= Icone::svg('ajouter') ?>
                    Continuer vers l’enrôlement
                </button>
            </form>
        </div>
    </div>
<?php else: ?>

    <!-- Codes de secours -------------------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('archive') ?> Remplacer les codes de secours</h2>
        </div>
        <div class="panneau__corps">
            <p class="texte-petit" style="margin-bottom:18px">
                Un nouveau jeu de <?= e((string) SECURITE_2FA_CODES_SECOURS) ?> codes est remis, et
                l’ancien cesse aussitôt de fonctionner. À faire si vous pensez que votre liste a été vue.
            </p>

            <form method="post" action="<?= e(url('admin/securite/codes')) ?>" autocomplete="off">
                <?= Csrf::champ() ?>

                <div class="grille-champs">
                    <div class="champ">
                        <label class="champ__etiquette" for="mdp-codes">
                            Mot de passe du compte <span class="requis">*</span>
                        </label>
                        <input class="saisie" type="password" id="mdp-codes" name="mot_de_passe"
                               required autocomplete="current-password">
                    </div>

                    <div class="champ">
                        <label class="champ__etiquette" for="code-codes">
                            Code de l’application <span class="requis">*</span>
                        </label>
                        <input class="saisie saisie--code" type="text" id="code-codes" name="code"
                               required inputmode="numeric" autocomplete="one-time-code" maxlength="9">
                        <div class="champ__aide">Un code de secours est également accepté ici.</div>
                    </div>
                </div>

                <button type="submit" class="btn btn--primaire">
                    <?= Icone::svg('modifier') ?>
                    Remplacer les codes de secours
                </button>
            </form>
        </div>
    </div>

    <!-- Retrait ------------------------------------------------------------ -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('fermer') ?> Retirer la vérification</h2>
        </div>
        <div class="panneau__corps">
            <?php if ($obligatoire): ?>
                <p class="texte-petit">
                    Le rôle « <?= e($roleLibelle) ?> » relève de l’administration : le chapitre 8 impose
                    la vérification en deux étapes, elle ne peut donc pas être retirée. Pour la déplacer
                    sur un autre appareil, remplacez les codes de secours puis enrôlez le nouvel appareil
                    depuis un autre compte d’administration.
                </p>
            <?php else: ?>
                <p class="texte-petit" style="margin-bottom:18px">
                    Le compte repassera en connexion simple. Le mot de passe et un code en cours de
                    validité sont exigés : retirer une protection ne doit pas être plus facile que
                    de la mettre en place.
                </p>

                <form method="post" action="<?= e(url('admin/securite/desactiver')) ?>" autocomplete="off">
                    <?= Csrf::champ() ?>

                    <div class="grille-champs">
                        <div class="champ">
                            <label class="champ__etiquette" for="mdp-desactiver">
                                Mot de passe du compte <span class="requis">*</span>
                            </label>
                            <input class="saisie" type="password" id="mdp-desactiver" name="mot_de_passe"
                                   required autocomplete="current-password">
                        </div>

                        <div class="champ">
                            <label class="champ__etiquette" for="code-desactiver">
                                Code de l’application <span class="requis">*</span>
                            </label>
                            <input class="saisie saisie--code" type="text" id="code-desactiver" name="code"
                                   required inputmode="numeric" autocomplete="one-time-code" maxlength="9">
                        </div>
                    </div>

                    <button type="submit" class="btn btn--contour">
                        <?= Icone::svg('fermer') ?>
                        Retirer la vérification en deux étapes
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="encart">
    <strong>Comment cela fonctionne.</strong> Le secret partagé est chiffré en base (AES-256-GCM,
    clé dérivée de <span class="monospace">HASH_SECRET</span>) : contrairement à un mot de passe,
    il doit être relu à chaque connexion pour recalculer le code attendu. Les codes font six
    chiffres et changent toutes les trente secondes (RFC 6238). Un code déjà utilisé est refusé :
    le laisser resservir pendant sa demi-minute de validité ouvrirait une fenêtre de rejeu.
</div>
