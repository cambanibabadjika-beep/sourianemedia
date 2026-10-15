<?php
/**
 * SOURIANE — Requête entrante
 */

declare(strict_types=1);

namespace Souriane\Core;

final class Request
{
    private array $corps = [];
    private ?array $json = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public readonly array $server
    ) {
        $this->corps = $post;
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $path   = parse_url($uri, PHP_URL_PATH) ?? '/';
        $path   = rawurldecode($path);

        // Retire le chemin de base (ex. /sourianemedia) pour ne garder que la route
        $base = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
        $base = rtrim($base, '/');
        if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = '/' . trim($path, '/');
        if ($path === '/') {
            $path = '/';
        }

        return new self(
            $method,
            $path,
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER
        );
    }

    /** Corps de la requête décodé (JSON ou formulaire). */
    public function corps(): array
    {
        if ($this->json === null) {
            $brut = file_get_contents('php://input') ?: '';
            $decode = json_decode($brut, true);
            $this->json = is_array($decode) ? $decode : [];
        }

        return array_merge($this->corps, $this->json);
    }

    /** Lit une valeur du corps ou de la requête. */
    public function input(string $cle, mixed $defaut = null): mixed
    {
        $donnees = $this->corps();

        return $donnees[$cle] ?? $this->query[$cle] ?? $defaut;
    }

    /** Lit une chaîne nettoyée. */
    public function string(string $cle, string $defaut = ''): string
    {
        $valeur = $this->input($cle, $defaut);

        return is_scalar($valeur) ? trim((string) $valeur) : $defaut;
    }

    /** Lit un entier. */
    public function int(string $cle, int $defaut = 0): int
    {
        $valeur = $this->input($cle, $defaut);

        return is_numeric($valeur) ? (int) $valeur : $defaut;
    }

    /** Lit un booléen (case à cocher, « 1 », « on », « true »). */
    public function bool(string $cle): bool
    {
        $valeur = $this->input($cle, false);

        return in_array($valeur, [true, 1, '1', 'on', 'true', 'oui'], true);
    }

    public function estJson(): bool
    {
        $type = $this->server['CONTENT_TYPE'] ?? '';

        return str_contains(strtolower($type), 'json');
    }

    public function estPost(): bool
    {
        return $this->method === 'POST';
    }

    public function estAjax(): bool
    {
        return strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function agent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function referent(): ?string
    {
        $r = $this->server['HTTP_REFERER'] ?? null;

        return $r ? mb_substr((string) $r, 0, 255) : null;
    }

    public function baseUrl(): string
    {
        return BASE_URL;
    }
}
