<?php
/**
 * SOURIANE — Couche d’accès aux données (PDO)
 *
 * Toutes les requêtes passent par des requêtes préparées (ch. 8).
 * Aucune concaténation de valeurs dans le SQL.
 */

declare(strict_types=1);

namespace Souriane\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private int $transactions = 0;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            journal_technique('erreur', 'Connexion base de données impossible', [
                'message' => $e->getMessage(),
            ]);
            throw new RuntimeException(
                APP_DEBUG
                    ? 'Connexion à la base impossible : ' . $e->getMessage()
                    : 'Le service est momentanément indisponible.',
                0,
                $e
            );
        }
    }

    public static function instance(): Database
    {
        return self::$instance ??= new self();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** Exécute une requête préparée et retourne la déclaration. */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $debut = microtime(true);
        $stmt  = $this->pdo->prepare($sql);

        foreach ($params as $cle => $valeur) {
            // Les colonnes de type JSON reçoivent directement tableaux et objets
            if (is_array($valeur) || is_object($valeur)) {
                $encode = json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encode === false) {
                    throw new RuntimeException(
                        'Valeur non sérialisable en JSON pour le paramètre « ' . $cle . ' ».'
                    );
                }
                $valeur = $encode;
            }

            $nom = is_int($cle) ? $cle + 1 : ':' . ltrim((string) $cle, ':');
            $type = match (true) {
                is_int($valeur)  => PDO::PARAM_INT,
                is_bool($valeur) => PDO::PARAM_BOOL,
                $valeur === null => PDO::PARAM_NULL,
                default          => PDO::PARAM_STR,
            };
            $stmt->bindValue($nom, $valeur, $type);
        }

        $stmt->execute();

        $duree = (microtime(true) - $debut) * 1000;
        if ($duree > 400) {
            journal_technique('performance', 'Requête lente', [
                'duree_ms' => round($duree, 1),
                'sql'      => mb_substr($sql, 0, 160),
            ]);
        }

        return $stmt;
    }

    /** Retourne toutes les lignes d’une requête. */
    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Retourne la première ligne ou null. */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $ligne = $this->query($sql, $params)->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** Retourne la première colonne de la première ligne. */
    public function selectValue(string $sql, array $params = [], mixed $defaut = null): mixed
    {
        $valeur = $this->query($sql, $params)->fetchColumn();

        return $valeur === false ? $defaut : $valeur;
    }

    /** Insère une ligne et retourne l’identifiant créé. */
    public function insert(string $table, array $donnees): int
    {
        $colonnes = array_keys($donnees);
        $marqueurs = array_map(static fn (string $c): string => ':' . $c, $colonnes);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            '`' . implode('`, `', $colonnes) . '`',
            implode(', ', $marqueurs)
        );

        $this->query($sql, $donnees);

        return (int) $this->pdo->lastInsertId();
    }

    /** Met à jour des lignes selon une condition. */
    public function update(string $table, array $donnees, string $condition, array $params = []): int
    {
        $affectations = [];
        foreach (array_keys($donnees) as $colonne) {
            $affectations[] = '`' . $colonne . '` = :' . $colonne;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $affectations),
            $condition
        );

        return $this->query($sql, array_merge($donnees, $params))->rowCount();
    }

    /** Supprime des lignes selon une condition. */
    public function delete(string $table, string $condition, array $params = []): int
    {
        return $this->query(sprintf('DELETE FROM `%s` WHERE %s', $table, $condition), $params)
            ->rowCount();
    }

    /** Démarre une transaction (réentrante). */
    public function begin(): void
    {
        if ($this->transactions === 0) {
            $this->pdo->beginTransaction();
        }
        $this->transactions++;
    }

    /** Valide la transaction en cours. */
    public function commit(): void
    {
        if ($this->transactions > 0) {
            $this->transactions--;
            if ($this->transactions === 0) {
                $this->pdo->commit();
            }
        }
    }

    /** Annule la transaction en cours. */
    public function rollback(): void
    {
        if ($this->transactions > 0) {
            $this->transactions = 0;
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    /** Exécute une opération dans une transaction. */
    public function transaction(callable $operation): mixed
    {
        $this->begin();
        try {
            $resultat = $operation();
            $this->commit();

            return $resultat;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}
