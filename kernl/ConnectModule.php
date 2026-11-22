<?php
/* ---------------------------------------------------- */
/* 程序名称: 数据库连接（PDO 风格封装）
/* 说明（2026）：原文件使用 PHP 7.4/8 的类型属性与命名参数，
/* 在本机 PHP 7.3 下 include 即 Parse error。现改写为 7.3 兼容，
/* 保留 prepare/execute/fetch 等原生 mysqli 用法，供新代码使用。
/* 旧模块仍在用 Db 类（见 kernl/Db.Class.php）。
/* ---------------------------------------------------- */
declare(strict_types=1);

class DatabaseException extends Exception
{
    public function __construct(string $message, int $code = 0, Throwable $previous = null)
    {
        parent::__construct('Database Error: ' . $message, $code, $previous);
    }
}

class ConnectModule
{
    private $connection = null;
    private $charset = 'utf8mb4';
    private $database = '';

    public function __construct(string $host, string $username, string $password, string $database = '', string $charset = 'utf8mb4')
    {
        $this->charset  = strtolower(str_replace('-', '', $charset));
        $this->database = $database;
        if (function_exists('mysqli_report')) {
            @mysqli_report(MYSQLI_REPORT_OFF);
        }
        $this->connection = @mysqli_connect($host, $username, $password, $database);
        if (!$this->connection) {
            throw new DatabaseException('Connection failed: ' . mysqli_connect_error());
        }
        if (!mysqli_set_charset($this->connection, $this->charset)) {
            throw new DatabaseException('Setting charset failed: ' . $this->getError());
        }
    }

    public function query(string $sql)
    {
        $result = @mysqli_query($this->connection, $sql);
        if ($result === false) {
            throw new DatabaseException('Query failed: ' . $this->getError());
        }
        return $result;
    }

    public function prepare(string $sql)
    {
        $stmt = @mysqli_prepare($this->connection, $sql);
        if ($stmt === false) {
            throw new DatabaseException('Prepare failed: ' . $this->getError());
        }
        return $stmt;
    }

    public function execute($stmt, array $params = array(), string $types = '')
    {
        if ($types !== '' && !empty($params)) {
            $vars = array();
            foreach ($params as $k => $v) {
                $vars[$k] = $v;
            }
            $args = array_merge(array($stmt, $types), $vars);
            $refs = array();
            foreach ($args as $k => $v) {
                $refs[$k] = &$args[$k];
            }
            call_user_func_array('mysqli_stmt_bind_param', $refs);
        }
        if (!mysqli_stmt_execute($stmt)) {
            throw new DatabaseException('Execute failed: ' . $this->getError());
        }
        return $stmt;
    }

    public function fetchAll($result): array
    {
        $rows = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function fetchRow($result)
    {
        $row = mysqli_fetch_assoc($result);
        return $row === null ? null : $row;
    }

    public function fetchColumn($result, int $columnIndex = 0)
    {
        $row = mysqli_fetch_row($result);
        return $row[$columnIndex] ?? null;
    }

    public function insertId()
    {
        return mysqli_insert_id($this->connection);
    }

    public function affectedRows()
    {
        return mysqli_affected_rows($this->connection);
    }

    public function escapeString(string $string): string
    {
        return mysqli_real_escape_string($this->connection, $string);
    }

    public function getVersion(): string
    {
        return mysqli_get_server_info($this->connection);
    }

    public function close(): void
    {
        if ($this->connection instanceof mysqli) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    private function getError(): string
    {
        return $this->connection ? mysqli_error($this->connection) : 'no connection';
    }
}
