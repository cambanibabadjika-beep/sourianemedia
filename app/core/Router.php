<?php
/**
 * SOURIANE — Routeur à URL propres
 *
 * Les motifs utilisent des segments nommés : /c/{code}, /api/campagnes/{id}/statistiques
 * L’ordre de déclaration détermine la priorité de correspondance.
 */

declare(strict_types=1);

namespace Souriane\Core;

use Throwable;

final class Router
{
    /** @var array<int, array{method:string, motif:string, regex:string, params:array<int,string>, action:callable}> */
    private array $routes = [];

    private $pageNonTrouvee = null;

    public function get(string $motif, callable $action): void
    {
        $this->ajouter('GET', $motif, $action);
    }

    public function post(string $motif, callable $action): void
    {
        $this->ajouter('POST', $motif, $action);
    }

    public function put(string $motif, callable $action): void
    {
        $this->ajouter('PUT', $motif, $action);
    }

    public function delete(string $motif, callable $action): void
    {
        $this->ajouter('DELETE', $motif, $action);
    }

    public function nonTrouvee(callable $action): void
    {
        $this->pageNonTrouvee = $action;
    }

    private function ajouter(string $methode, string $motif, callable $action): void
    {
        $params = [];
        $regex  = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];

                return '([^/]+)';
            },
            $motif
        ) ?? $motif;

        $this->routes[] = [
            'method' => $methode,
            'motif'  => $motif,
            'regex'  => '#^' . $regex . '$#',
            'params' => $params,
            'action' => $action,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }

            array_shift($m);
            $arguments = array_combine($route['params'], $m) ?: [];

            try {
                $resultat = ($route['action'])($request, $arguments);
            } catch (Throwable $e) {
                return $this->erreur($e);
            }

            return $resultat instanceof Response ? $resultat : Response::html((string) $resultat);
        }

        // Une route existe sur un autre verbe : 405 plutôt que 404
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path)) {
                return Response::json(['erreur' => 'Méthode non autorisée'], 405);
            }
        }

        if ($this->pageNonTrouvee !== null) {
            return ($this->pageNonTrouvee)($request);
        }

        return Response::html('<h1>404 — Page introuvable</h1>', 404);
    }

    private function erreur(Throwable $e): Response
    {
        journal_technique('erreur', $e->getMessage(), [
            'fichier' => $e->getFile() . ':' . $e->getLine(),
            'trace'   => mb_substr($e->getTraceAsString(), 0, 1200),
        ]);

        if (APP_DEBUG) {
            $html = '<div style="font-family:monospace;padding:24px;background:#1A1033;color:#f8fafc">'
                . '<h1 style="color:#FBBF24">Erreur ' . e((string) $e->getCode()) . '</h1>'
                . '<p style="font-size:1.1rem">' . e($e->getMessage()) . '</p>'
                . '<p style="color:#94a3b8">' . e($e->getFile() . ':' . $e->getLine()) . '</p>'
                . '<pre style="white-space:pre-wrap;color:#cbd5e1">' . e($e->getTraceAsString()) . '</pre>'
                . '</div>';

            return Response::html($html, 500);
        }

        return Response::html('<h1>Une erreur est survenue</h1><p>Le service est momentanément indisponible.</p>', 500);
    }
}
