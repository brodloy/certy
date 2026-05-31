<?php
/**
 * DATABASE — a thin, safe wrapper around PDO.
 *
 * You write the SQL yourself (so there's no hidden query-builder to learn), but
 * you ALWAYS pass values as the second argument with `?` placeholders. That
 * keeps queries safe from SQL injection automatically:
 *
 *   db()->all('SELECT * FROM `Example` WHERE `FK_UserID` = ?', [$userId]);
 *   db()->first('SELECT * FROM `User` WHERE `Email` = ?', [$email]);
 *   db()->run('UPDATE `Example` SET `Title` = ? WHERE `PK_ExampleID` = ?', [$title, $id]);
 *   $id = db()->insert('Example', ['FK_UserID' => $uid, 'Title' => $t]);
 *
 * NEVER build SQL by pasting variables into the string. Use `?` + params.
 */
class Database
{
    private ?PDO $pdo = null;

    /** Connect lazily — the first query opens the connection, configured strictly. */
    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                config('db_host'),
                config('db_port'),
                config('db_name'),
            );

            $this->pdo = new PDO($dsn, config('db_user'), config('db_pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw on errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // rows as arrays
                PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared statements
            ]);

            // Keep the DB in UTC too, so stored timestamps match the app.
            $this->pdo->exec("SET time_zone = '+00:00'");
        }

        return $this->pdo;
    }

    /** Run any SQL with bound params; returns the statement (use for INSERT/UPDATE/DELETE). */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Run raw SQL that may contain SEVERAL statements (e.g. a whole .sql file).
     * Uses PDO::exec, which — unlike a prepared statement — allows multiple
     * statements separated by ';'. Only use this for trusted files you wrote
     * (migrations, seed), never for user input.
     */
    public function execRaw(string $sql): void
    {
        $this->pdo()->exec($sql);
    }

    /** Return ALL matching rows as an array of associative arrays. */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** Return the FIRST matching row as an array, or null if none. */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Insert a row from an associative array and return the new id.
     * Column names come from your code (never user input), values are bound.
     *   db()->insert('Example', ['FK_UserID' => 3, 'Title' => $title]);
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $cols    = '`' . implode('`, `', $columns) . '`';
        $holders = implode(', ', array_fill(0, count($columns), '?'));

        $this->run(
            "INSERT INTO `{$table}` ({$cols}) VALUES ({$holders})",
            array_values($data),
        );

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Paginate a table. $where is the WHERE/ORDER fragment (with `?` for values),
     * $params are those values. Returns rows plus page metadata:
     *   db()->paginate('Example', 'WHERE `FK_UserID` = ?', [$uid], $page, 15, 'ORDER BY `CreatedAt` DESC')
     *   → ['rows' => [...], 'page' => 1, 'perPage' => 15, 'total' => 42, 'totalPages' => 3]
     * The table name comes from your code (never user input); values are bound.
     */
    public function paginate(string $table, string $where, array $params, int $page, int $perPage = 15, string $orderBy = ''): array
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        $countRow = $this->first("SELECT COUNT(*) AS c FROM `{$table}` {$where}", $params);
        $total    = (int) ($countRow['c'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows   = $this->all(
            "SELECT * FROM `{$table}` {$where} {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );

        return [
            'rows'       => $rows,
            'page'       => $page,
            'perPage'    => $perPage,
            'total'      => $total,
            'totalPages' => (int) ceil($total / $perPage),
        ];
    }
}
