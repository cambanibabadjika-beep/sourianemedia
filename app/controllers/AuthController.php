<?php
/**
 * SOURIANE — Contrôleur d’authentification du back-office (EF-G01)
 */

declare(strict_types=1);

namespace Souriane\Controllers;

use Souriane\Core\Auth;
use Souriane\Core\Csrf;
use Souriane\Core\Flash;
use Souriane\Core\RateLimiter;
use Souriane\Core\Request;
use Souriane\Core\Response;
use Souriane\Core\View;

final class AuthController
{
    /** GET /admin/connexion */
    public function formulaire(Request $request): Response
    {
        if (Auth::verifie()) {
            return Response::redirect(url('admin'));
        }

        $html = View::rendre('admin/connexion', [
            'titre'     => 'Connexion — ' . APP_NAME,
            'courriel'  => '',
            'retour'    => (string) ($request->query['retour'] ?? ''),
            'configuration' => ['csrf' => Csrf::jeton()],
        ]);

        return Response::html($html);
    }

    /** POST /admin/connexion */
    public function connecter(Request $request): Response
    {
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        $courriel = $request->string('email');
        $motDePasse = (string) $request->input('mot_de_passe', '');
        $retour   = (string) $request->input('retour', '');

        // Cinq tentatives par quart d’heure et par adresse
        $limite = RateLimiter::verifier('connexion', $request->ip(), 5, 900);
        if (!$limite['autorise']) {
            Flash::erreur('Trop de tentatives. Réessayez dans ' . ceil($limite['reessayer_dans'] / 60) . ' minute(s).');

            return Response::redirect(url('admin/connexion'));
        }

        if ($courriel === '' || $motDePasse === '') {
            Flash::erreur('Renseignez votre adresse e-mail et votre mot de passe.');

            return Response::redirect(url('admin/connexion'));
        }

        $utilisateur = Auth::connecter($courriel, $motDePasse, $request);

        if ($utilisateur === null) {
            Flash::erreur('Identifiants incorrects, ou compte temporairement verrouillé.');

            return Response::redirect(url('admin/connexion'));
        }

        Flash::succes('Bienvenue, ' . $utilisateur['nom'] . '.');

        // On n’accepte qu’un chemin interne comme destination de retour
        if ($retour !== '' && str_starts_with($retour, '/') && !str_starts_with($retour, '//')) {
            return Response::redirect($retour);
        }

        return Response::redirect(url('admin'));
    }

    /** POST /admin/deconnexion */
    public function deconnecter(Request $request): Response
    {
        if ($refus = Csrf::verifier($request)) {
            return $refus;
        }

        Auth::deconnecter($request);
        Flash::info('Vous êtes déconnecté.');

        return Response::redirect(url('admin/connexion'));
    }
}
