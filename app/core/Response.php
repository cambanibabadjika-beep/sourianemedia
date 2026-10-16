<?php
/**
 * SOURIANE — Réponse sortante
 */

declare(strict_types=1);

namespace Souriane\Core;

final class Response
{
    /**
     * Codes non répertoriés par PHP : la ligne de statut est écrite explicitement
     * avec un motif, faute de quoi certains serveurs la rejettent.
     */
    private const RAISONS = [
        419 => 'Authentication Timeout',
        425 => 'Too Early',
        451 => 'Unavailable For Legal Reasons',
        499 => 'Client Closed Request',
    ];

    public function __construct(
        private string $contenu = '',
        private int $statut = 200,
        private array $entetes = []
    ) {
    }

    public static function html(string $contenu, int $statut = 200): self
    {
        return new self($contenu, $statut, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $donnees, int $statut = 200): self
    {
        return new self(
            json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $statut,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $destination, int $statut = 302): self
    {
        return new self('', $statut, ['Location' => $destination]);
    }

    public static function texte(string $contenu, int $statut = 200): self
    {
        return new self($contenu, $statut, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function fichier(string $chemin, string $type): self
    {
        return new self('', 200, [
            'Content-Type'  => $type,
            'X-Fichier'     => $chemin,
            '__fichier'     => $chemin,
        ]);
    }

    public function avecEntete(string $nom, string $valeur): self
    {
        $this->entetes[$nom] = $valeur;

        return $this;
    }

    public function avecCache(int $secondes): self
    {
        return $this->avecEntete('Cache-Control', 'public, max-age=' . $secondes);
    }

    public function contenu(): string
    {
        return $this->contenu;
    }

    public function statut(): int
    {
        return $this->statut;
    }

    public function entetes(): array
    {
        return $this->entetes;
    }

    /**
     * Copie sans corps, pour répondre à une requête HEAD.
     *
     * La RFC 9110 impose qu’une ressource servie en GET le soit aussi en HEAD,
     * mais sans corps. Conserver le statut et les en-têtes est tout l’intérêt :
     * un client peut lire le type et la longueur sans rien télécharger.
     */
    public function sansCorps(): self
    {
        $entetes = $this->entetes;
        unset($entetes['__fichier']);

        if ($this->contenu !== '' && !isset($entetes['Content-Length'])) {
            $entetes['Content-Length'] = (string) strlen($this->contenu);
        }

        return new self('', $this->statut, $entetes);
    }

    /** Envoie la réponse au client. */
    public function envoyer(): void
    {
        // Neutralise toute sortie parasite (avertissement PHP) pour que les
        // en-têtes — et notamment les redirections — restent valides.
        if (ob_get_level() > 0) {
            $parasites = (string) ob_get_clean();
            if (trim($parasites) !== '') {
                journal_technique('avertissement', 'Sortie parasite interceptée', [
                    'extrait' => mb_substr($parasites, 0, 600),
                ]);
            }
        }

        if (!headers_sent()) {
            if (isset(self::RAISONS[$this->statut])) {
                header(
                    sprintf('HTTP/1.1 %d %s', $this->statut, self::RAISONS[$this->statut]),
                    true,
                    $this->statut
                );
            } else {
                http_response_code($this->statut);
            }

            foreach ($this->entetes as $nom => $valeur) {
                if ($nom === '__fichier') {
                    continue;
                }
                header($nom . ': ' . $valeur);
            }
        }

        // Un fichier est envoyé en flux, il n’est jamais tenu en mémoire : son
        // contenu n’est donc pas dans $contenu. Sans cette lecture, une réponse
        // construite par Response::fichier() partait avec un corps vide.
        if (isset($this->entetes['__fichier'])) {
            readfile($this->entetes['__fichier']);

            return;
        }

        echo $this->contenu;
    }
}
