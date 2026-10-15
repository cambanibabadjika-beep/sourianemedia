<?php
/**
 * SOURIANE — Modèle Utilisateur (comptes et rôles, EF-G01)
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database as Db;

final class Utilisateur
{
    public const ROLES = [
        'regie'     => 'Régie — accès complet',
        'agent'     => 'Agent terrain',
        'annonceur' => 'Annonceur',
    ];

    public static function tous(): array
    {
        return Db::instance()->select(
            'SELECT u.*, a.nom AS annonceur_nom FROM utilisateurs u
             LEFT JOIN annonceurs a ON a.id = u.annonceur_id
             ORDER BY u.role ASC, u.nom ASC'
        );
    }

    public static function trouver(int $id): ?array
    {
        return Db::instance()->selectOne('SELECT * FROM utilisateurs WHERE id = :id', ['id' => $id]);
    }

    public static function creer(array $donnees): int
    {
        $donnees['email'] = mb_strtolower(trim((string) $donnees['email']));

        return Db::instance()->insert('utilisateurs', $donnees);
    }

    public static function modifier(int $id, array $donnees): void
    {
        if (isset($donnees['email'])) {
            $donnees['email'] = mb_strtolower(trim((string) $donnees['email']));
        }
        Db::instance()->update('utilisateurs', $donnees, 'id = :id', ['id' => $id]);
    }

    public static function emailExiste(string $email, ?int $ignorer = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM utilisateurs WHERE email = :e';
        $params = ['e' => mb_strtolower(trim($email))];
        if ($ignorer !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignorer;
        }

        return (int) Db::instance()->selectValue($sql, $params, 0) > 0;
    }

    public static function compter(): int
    {
        return (int) Db::instance()->selectValue('SELECT COUNT(*) FROM utilisateurs WHERE actif = 1', [], 0);
    }
}
