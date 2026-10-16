<?php
/**
 * SOURIANE — Contrôleur d’authentification du back-office (EF-G01, ch. 8)
 *
 * Le parcours de connexion se déroule en deux temps pour les comptes
 * protégés : le mot de passe, puis un code à usage unique. Un compte
 * d’administration qui n’a pas encore de second facteur est conduit à en
 * mettre un en place avant d’entrer.
 *
 * Le troisième écran, l’enrôlement, sert deux situations avec le même code :
 * l’enrôlement imposé à la connexion, et l’activation volontaire depuis les
 * réglages du compte. Entretenir deux parcours séparés aurait fini par les
 * faire diverger.
 */

declare(strict_types=1);

namespace Souriane\Controllers;

use Souriane\Core\Auth;
use Souriane\Core\Coffre;
use Souriane\Core\Csrf;
use Souriane\Core\Flash;
use Souriane\Core\RateLimiter;
use Souriane\Core\Request;
use Souriane\Core\Response;
use Souriane\Core\Session;
use Souriane\Core\Totp;
use Souriane\Core\View;
use Souriane\Services\QrService;
use Souriane\Services\SecondFacteur;

final class AuthController
{
    /** Chemin demandé, conservé le temps du second facteur. */
    private const RETOUR = '_2fa_retour';

    /** GET /admin/connexion */
    public function formulaire(Request $request): Response
    {
        if (Auth::verifie()) {
            return Response::redirect(url('admin'));
        }

        // Une connexion déjà engagée reprend là où elle s’est arrêtée, plutôt
        // que de repartir du mot de passe.
        if (Auth::enrolementRequis()) {
            return Response::redirect(url('admin/connexion/enrolement'));
        }
        if (Auth::codeAttendu()) {
            return Response::redirect(url('admin/connexion/code'));
        }

        $html = View::rendre('admin/connexion', [
            'titre'     => 'Connexion — ' . APP_NAME,
            'courriel'  => '',
            'retour'    => (string) ($request->query['retour'] ?? ''),
            'configuration' => ['csrf' => Csrf::jeton()],
        ]);

        return Response::html($html)->avecEntete('Cache-Control', 'no-store, private');
    }

    /** POST /admin/connexion */
    public function connecter(Request $request): Response
    {
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $courriel   = $request->string('email');
        $motDePasse = (string) $request->input('mot_de_passe', '');
        $retour     = (string) $request->input('retour', '');

        // Protection contre la force brute : seules les tentatives ÉCHOUÉES
        // comptent, et une réussite remet le compteur à zéro. Une faute de
        // frappe ne doit pas verrouiller un utilisateur légitime.
        $limite = RateLimiter::bloque(
            'connexion',
            $request->ip(),
            SECURITE_CONNEXION_MAX_ECHECS,
            SECURITE_CONNEXION_FENETRE
        );
        if (!$limite['autorise']) {
            $reste = (int) $limite['reessayer_dans'];
            $delai = $reste >= 60
                ? (string) (int) ceil($reste / 60) . ' minute(s)'
                : $reste . ' seconde(s)';

            Flash::erreur('Trop de tentatives. Réessayez dans ' . $delai . '.');

            return Response::redirect(url('admin/connexion'));
        }

        if ($courriel === '' || $motDePasse === '') {
            Flash::erreur('Renseignez votre adresse e-mail et votre mot de passe.');

            return Response::redirect(url('admin/connexion'));
        }

        $utilisateur = Auth::connecter($courriel, $motDePasse, $request);

        if ($utilisateur === null) {
            RateLimiter::echec('connexion', $request->ip(), SECURITE_CONNEXION_FENETRE);
            Flash::erreur('Identifiants incorrects, ou compte temporairement verrouillé.');

            return Response::redirect(url('admin/connexion'));
        }

        // Connexion réussie : le compteur repart de zéro.
        RateLimiter::oublier('connexion', $request->ip());

        if ($this->retourValide($retour)) {
            Session::ecrire(self::RETOUR, $retour);
        }

        if (Auth::enrolementRequis()) {
            Flash::info('Ce compte doit être protégé par une vérification en deux étapes. Mettons-la en place.');

            return Response::redirect(url('admin/connexion/enrolement'));
        }

        if (Auth::codeAttendu()) {
            return Response::redirect(url('admin/connexion/code'));
        }

        Flash::succes('Bienvenue, ' . $utilisateur['nom'] . '.');

        return Response::redirect($this->destination());
    }

    /** POST /admin/deconnexion */
    public function deconnecter(Request $request): Response
    {
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        Auth::deconnecter($request);
        Session::oublier(self::RETOUR);
        Flash::info('Vous êtes déconnecté.');

        return Response::redirect(url('admin/connexion'));
    }

    // -----------------------------------------------------------------------
    // Second facteur (ch. 8)
    // -----------------------------------------------------------------------

    /** GET /admin/connexion/code */
    public function formulaireCode(Request $request): Response
    {
        if (Auth::verifie()) {
            return Response::redirect(url('admin'));
        }

        $utilisateur = $this->compteEnAttenteDeCode();
        if ($utilisateur === null) {
            Flash::erreur('Reprenez la connexion depuis le début.');

            return Response::redirect(url('admin/connexion'));
        }

        $html = View::rendre('admin/connexion-code', [
            'titre'      => 'Vérification en deux étapes — ' . APP_NAME,
            'courriel'   => (string) $utilisateur['email'],
            'secretDemo' => APP_DEBUG ? SECURITE_2FA_SECRET_DEMO : '',
            'configuration' => ['csrf' => Csrf::jeton()],
        ]);

        return Response::html($html)->avecEntete('Cache-Control', 'no-store, private');
    }

    /** POST /admin/connexion/code */
    public function verifierCode(Request $request): Response
    {
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $utilisateur = $this->compteEnAttenteDeCode();
        if ($utilisateur === null) {
            Flash::erreur('Reprenez la connexion depuis le début.');

            return Response::redirect(url('admin/connexion'));
        }

        $limite = RateLimiter::verifier('second-facteur', $request->ip(), SECURITE_CODE_MAX_ESSAIS, SECURITE_CODE_FENETRE);
        if (!$limite['autorise']) {
            $reste = (int) $limite['reessayer_dans'];
            $delai = $reste >= 60
                ? (string) (int) ceil($reste / 60) . ' minute(s)'
                : $reste . ' seconde(s)';

            Flash::erreur('Trop de tentatives. Réessayez dans ' . $delai . '.');

            return Response::redirect(url('admin/connexion/code'));
        }

        $id = (int) $utilisateur['id'];

        // Deux voies pour un même besoin : le code de l’application, ou un
        // code de secours si le téléphone est perdu. Le second n’est examiné
        // que si le premier a échoué.
        $saisie = (string) $request->input('code', '');
        if (trim($saisie) === '') {
            $saisie = (string) $request->input('secours', '');
        }

        $accepte = SecondFacteur::verifierCode($id, $utilisateur, $saisie)
            || SecondFacteur::consommerSecours($id, $utilisateur, $saisie);

        if (!$accepte) {
            journal_technique('securite', 'Code à deux facteurs refusé', ['email' => $utilisateur['email']]);
            Flash::erreur('Code refusé. Vérifiez l’heure de votre téléphone, puis réessayez — un code déjà utilisé ne resert pas.');

            return Response::redirect(url('admin/connexion/code'));
        }

        Auth::achever($utilisateur, $request);
        journal_technique('securite', 'Second facteur validé', ['email' => $utilisateur['email']]);
        Flash::succes('Bienvenue, ' . $utilisateur['nom'] . '.');

        return Response::redirect($this->destination());
    }

    /** GET /admin/connexion/enrolement */
    public function formulaireEnrolement(Request $request): Response
    {
        $utilisateur = $this->compteEnEnrolement();
        if ($utilisateur === null) {
            if (Auth::verifie()) {
                Flash::info('Aucun enrôlement en cours.');

                return Response::redirect(url('admin/securite'));
            }

            Flash::erreur('Reprenez la connexion depuis le début.');

            return Response::redirect(url('admin/connexion'));
        }

        // La clé n’est tirée qu’une fois : rafraîchir la page ne doit pas
        // invalider le QR code que l’utilisateur vient de scanner. Elle est
        // rangée chiffrée, jamais en clair dans la session.
        $secret = Coffre::ouvrir(Auth::secretEnrolement());
        if ($secret === null) {
            $secret = Totp::secret();
            Auth::retenirSecret(Coffre::sceller($secret));
        }

        try {
            $qr = QrService::svg(SecondFacteur::uri($secret, (string) $utilisateur['email']), 'Q', 5, 4, '#000000');
        } catch (\Throwable $e) {
            // La clé reste recopiable à la main : l’enrôlement ne dépend pas
            // du dessin du QR code.
            journal_technique('securite', 'QR code d’enrôlement non dessiné', ['raison' => $e->getMessage()]);
            $qr = '';
        }

        $connecte = Auth::verifie();

        $html = View::rendre('admin/connexion-enrolement', [
            'titre'       => 'Vérification en deux étapes — ' . APP_NAME,
            'courriel'    => (string) $utilisateur['email'],
            'secret'      => $secret,
            'qr'          => $qr,
            'obligatoire' => SecondFacteur::obligatoire((string) $utilisateur['role']),
            'annulationUrl' => $connecte ? url('admin/securite') : url('admin/connexion'),
            'annulationLibelle' => $connecte
                ? 'Annuler et revenir à la sécurité du compte'
                : 'Annuler et revenir à la connexion',
            'configuration' => ['csrf' => Csrf::jeton()],
        ]);

        return Response::html($html)->avecEntete('Cache-Control', 'no-store, private');
    }

    /** POST /admin/connexion/enrolement */
    public function activerEnrolement(Request $request): Response
    {
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $utilisateur = $this->compteEnEnrolement();
        if ($utilisateur === null) {
            Flash::erreur('Reprenez la connexion depuis le début.');

            return Response::redirect(url('admin/connexion'));
        }

        $limite = RateLimiter::verifier('second-facteur', $request->ip(), SECURITE_CODE_MAX_ESSAIS, SECURITE_CODE_FENETRE);
        if (!$limite['autorise']) {
            $reste = (int) $limite['reessayer_dans'];
            $delai = $reste >= 60
                ? (string) (int) ceil($reste / 60) . ' minute(s)'
                : $reste . ' seconde(s)';

            Flash::erreur('Trop de tentatives. Réessayez dans ' . $delai . '.');

            return Response::redirect(url('admin/connexion/enrolement'));
        }

        $secret = Coffre::ouvrir(Auth::secretEnrolement());
        if ($secret === null) {
            Flash::erreur('L’enrôlement a expiré. Reprenez la mise en place.');

            return Response::redirect(url('admin/connexion/enrolement'));
        }

        $id    = (int) $utilisateur['id'];
        $codes = SecondFacteur::confirmer($id, $secret, (string) $request->input('code', ''));

        if ($codes === null) {
            journal_technique('securite', 'Enrôlement refusé : code incorrect', ['email' => $utilisateur['email']]);
            Flash::erreur(
                'Ce code ne correspond pas. Vérifiez que l’application affiche bien un code pour « '
                . SECURITE_2FA_EMETTEUR . ' ».'
            );

            return Response::redirect(url('admin/connexion/enrolement'));
        }

        // Les codes de secours ne sont lisibles qu’une fois. Ils traversent la
        // redirection dans la session ; la page de sécurité les efface après
        // les avoir affichés.
        Auth::retenirCodesSecours($codes);
        Auth::fermerEnrolement();

        journal_operation($id, 'modification', 'utilisateurs', $id, 'Second facteur activé', $request->ip());

        if (Auth::verifie()) {
            Flash::succes('Vérification en deux étapes activée.');

            return Response::redirect(url('admin/securite'));
        }

        Auth::achever($utilisateur, $request);
        Flash::succes('Vérification en deux étapes activée. Bienvenue, ' . $utilisateur['nom'] . '.');

        return Response::redirect(url('admin/securite'));
    }

    // -----------------------------------------------------------------------
    // Interne
    // -----------------------------------------------------------------------

    /** Compte dont le mot de passe est accepté et dont le code est attendu. */
    private function compteEnAttenteDeCode(): ?array
    {
        return Auth::codeAttendu() ? Auth::enSuspens() : null;
    }

    /** Compte concerné par un enrôlement en cours, connecté ou non. */
    private function compteEnEnrolement(): ?array
    {
        if (!Auth::enrolementEnCours()) {
            return null;
        }

        return Auth::utilisateur() ?? Auth::enSuspens();
    }

    /** Destination après connexion : le chemin demandé, sinon le tableau de bord. */
    private function destination(): string
    {
        $retour = Session::lire(self::RETOUR);
        Session::oublier(self::RETOUR);

        return is_string($retour) && $this->retourValide($retour) ? $retour : url('admin');
    }

    /**
     * N’accepte qu’un chemin interne comme destination de retour.
     *
     * Une adresse absolue, ou une adresse relative au protocole (« //hôte »),
     * transformerait la page de connexion en tremplin vers un site tiers.
     */
    private function retourValide(string $retour): bool
    {
        return $retour !== '' && str_starts_with($retour, '/') && !str_starts_with($retour, '//');
    }
}
