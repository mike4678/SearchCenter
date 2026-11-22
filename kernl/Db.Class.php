<?php
/* ---------------------------------------------------- */
/* 程序名称: 数据库连接函数库（兼容旧 Db API）
/* 程序说明（2026 重构）：
/*  1. 原系统使用 PDO 风格的 ConnectModule，但 modules 目录下的核心函数
/*     仍按旧 Db 类（query/select/fetch_array）编写，且类 Db 根本不存在，
/*     导致后台一进来就 Fatal error。此文件补回 Db 基类并对齐两套 API。
/*  2. 全部使用 mysqli + utf8mb4；提供 quote()/safeIdent() 作为唯一的
/*     SQL 拼接入口，供上层逐步改造为参数化查询。
/*  3. 出错只写日志、不回显 SQL 细节，避免泄露表结构。
/* ---------------------------------------------------- */

class Db
{
    private $DBSERVER;
    private $USER;
    private $PASSWORD;
    private $DB;
    private $charset;
    public  $link;
    public  $sql;
    public  $result;
    private $error_msg;
    public  $server_version;
    private $sharedLink = false;

    function __construct($DBSERVER, $USER = null, $PASSWORD = null, $DB = '', $charset = 'utf8mb4')
    {
        // 允许传入已建好的连接（内核里各模块共用同一个连接）
        if ($DBSERVER instanceof Db) {
            $this->link        = $DBSERVER->link;
            $this->DBSERVER    = $DBSERVER->DBSERVER;
            $this->USER        = $DBSERVER->USER;
            $this->PASSWORD    = $DBSERVER->PASSWORD;
            $this->DB          = $DBSERVER->DB;
            $this->charset     = $DBSERVER->charset;
            $this->sharedLink  = true;
            return;
        }
        $this->DBSERVER = $DBSERVER;
        $this->USER     = $USER;
        $this->PASSWORD = $PASSWORD;
        $this->DB       = $DB;
        $this->charset  = strtolower(str_replace('-', '', (string)$charset));
        if ($this->charset === 'utf8') {
            $this->charset = 'utf8mb4';
        }
        $this->connect();
    }

    function connect()
    {
        if (function_exists('mysqli_report')) {
            @mysqli_report(MYSQLI_REPORT_OFF);
        }
        $link = @mysqli_connect($this->DBSERVER, $this->USER, $this->PASSWORD, $this->DB);
        if (!$link) {
            $this->error_msg = 'Can not connect to mysql server: ' . mysqli_connect_error();
            $this->error();
            return false;
        }
        $this->link = $link;
        @mysqli_set_charset($this->link, $this->charset);
        return true;
    }

    /* ---------------- 基础执行 ---------------- */

    function query($sql)
    {
        $this->sql = $sql;
        $query = @mysqli_query($this->link, $sql);
        if ($query === false) {
            $this->error_msg = mysqli_error($this->link);
        }
        return $query;
    }

    /** 预处理语句，兼容旧代码里的 $this->prepare() */
    function prepare($sql)
    {
        $stmt = @mysqli_prepare($this->link, $sql);
        if ($stmt === false) {
            $this->error_msg = mysqli_error($this->link);
        }
        return $stmt;
    }

    /* ---------------- 转义入口 ---------------- */

    function escape($string)
    {
        return mysqli_real_escape_string($this->link, (string)$string);
    }

    function quote($value)
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return "'" . mysqli_real_escape_string($this->link, (string)$value) . "'";
    }

    function safeIdent($name)
    {
        $name = trim((string)$name);
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
            return $name;
        }
        return false;
    }

    function ident($name, $default = '')
    {
        $name = trim((string)$name);
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
            $name = $default;
        }
        return $name === '' ? '``' : '`' . $name . '`';
    }

    /* ---------------- 结果集 ---------------- */

    function affected_rows()
    {
        return mysqli_affected_rows($this->link);
    }

    function insert_id()
    {
        return mysqli_insert_id($this->link);
    }

    function num_rows($query)
    {
        if (!($query instanceof mysqli_result)) {
            return 0;
        }
        return @mysqli_num_rows($query);
    }

    function num_fields($query)
    {
        if (!($query instanceof mysqli_result)) {
            return 0;
        }
        return @mysqli_num_fields($query);
    }

    function fetch_row($query)
    {
        if (!($query instanceof mysqli_result)) {
            return false;
        }
        return @mysqli_fetch_row($query);
    }

    function fetch_assoc($query)
    {
        if (!($query instanceof mysqli_result)) {
            return false;
        }
        return @mysqli_fetch_assoc($query);
    }

    function fetch_array($query)
    {
        if (!($query instanceof mysqli_result)) {
            return false;
        }
        return @mysqli_fetch_array($query);
    }

    function free_result($query = null)
    {
        if ($query instanceof mysqli_result) {
            return @mysqli_free_result($query);
        }
        if ($this->result instanceof mysqli_result) {
            return @mysqli_free_result($this->result);
        }
        return false;
    }

    function result($row = 0)
    {
        if (!($this->result instanceof mysqli_result)) {
            return false;
        }
        if (!@mysqli_data_seek($this->result, (int)$row)) {
            return false;
        }
        $r = @mysqli_fetch_row($this->result);
        return ($r === false || $r === null) ? false : $r[0];
    }

    function version()
    {
        if (empty($this->server_version)) {
            $this->server_version = @mysqli_get_server_info($this->link);
        }
        return $this->server_version;
    }

    function close()
    {
        if ($this->sharedLink) {
            // 共享内核连接时由内核统一关闭
            return true;
        }
        if ($this->link) {
            return @mysqli_close($this->link);
        }
        return true;
    }

    /* ---------------- 常用封装 ---------------- */

    function select_all($table)
    {
        $tbl = $this->safeIdent($table);
        if ($tbl === false) {
            return false;
        }
        return $this->query("SELECT * FROM `" . $tbl . "`");
    }

    function select($table, $columnName = '*', $condition = '', $debug = '')
    {
        $tbl = $this->safeIdent($table);
        if ($tbl === false) {
            return false;
        }
        $columns = $columnName === '*' ? '*' : $columnName;
        $cond    = $condition ? ' WHERE ' . $condition : '';
        $sql     = "SELECT $columns FROM `$tbl`" . $cond;
        if ($debug) {
            return null;
        }
        return $this->query($sql);
    }

    function delete($table, $condition)
    {
        $tbl = $this->safeIdent($table);
        if ($tbl === false) {
            return false;
        }
        return $this->query("DELETE FROM `$tbl` WHERE " . $condition);
    }

    function get_one($sql, $limited = false)
    {
        if ($limited === true) {
            $sql = trim($sql . ' LIMIT 1');
        }
        $res = $this->query($sql);
        if ($res !== false) {
            $row = $this->fetch_row($res);
            return ($row !== false && $row !== null) ? $row[0] : '';
        }
        return false;
    }

    function escape_string($string)
    {
        return $this->escape($string);
    }

    function fetch_array_all($table, $order_by = '')
    {
        $tbl = $this->safeIdent($table);
        if ($tbl === false) {
            return array();
        }
        $order = $order_by ? ' ORDER BY ' . $order_by : '';
        $query = $this->query("SELECT * FROM `" . $tbl . "`" . $order);
        $data  = array();
        while ($row = $this->fetch_assoc($query)) {
            $data[] = $row;
        }
        return $data;
    }

    function table_exist($table)
    {
        $tbl = $this->safeIdent($table);
        if ($tbl === false) {
            return false;
        }
        return $this->num_rows($this->query("SHOW TABLES LIKE " . $this->quote($tbl))) === 1;
    }

    function field_exist($table, $field)
    {
        $tbl = $this->safeIdent($table);
        if ($tbl === false) {
            return false;
        }
        $query = $this->query("SHOW COLUMNS FROM `" . $tbl . "`");
        while ($row = $this->fetch_array($query)) {
            if (isset($row['Field']) && $row['Field'] === $field) {
                return true;
            }
        }
        return false;
    }

    function value_exist($table, $field, $value)
    {
        $tbl = $this->safeIdent($table);
        $col = $this->safeIdent($field);
        if ($tbl === false || $col === false) {
            return false;
        }
        $sql = "SELECT `" . $col . "` FROM `" . $tbl . "` WHERE `" . $col . "` = " . $this->quote($value) . " LIMIT 1";
        return $this->num_rows($this->query($sql)) > 0;
    }

    /* ---------------- 错误处理 ---------------- */

    function error($msg = '')
    {
        $text = $msg !== '' ? $msg : $this->error_msg;
        $this->WriteFatalLog($text);
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo '<pre style="color:#c00">Database error: ' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</pre>';
        }
        exit('数据库服务暂时不可用，请稍后重试');
    }

    function SystemError()
    {
        return $this->error_msg !== '' && $this->error_msg !== null ? $this->error_msg : @mysqli_error($this->link);
    }

    private function WriteFatalLog($msg)
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . ' URI=' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-') . PHP_EOL;
        $logDir = defined('ROOT_PATH') ? ROOT_PATH . 'logs' : dirname(__FILE__) . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
        @file_put_contents($logDir . '/db_error.log', $line, FILE_APPEND);
    }

    /* ---------------- 导入 ---------------- */

    function fn_execute($sql)
    {
        foreach ($this->fn_split($sql) as $s) {
            if (trim($s) !== '') {
                $this->query($s);
            }
        }
        return true;
    }

    function fn_split($sql)
    {
        $sql = str_replace("\r", "\n", $sql);
        $ret = array();
        $num = 0;
        foreach (explode(";\n", trim($sql)) as $query) {
            $lines = array_filter(explode("\n", trim($query)));
            $ret[$num] = '';
            foreach ($lines as $q) {
                $first = substr($q, 0, 1);
                if ($first !== '#' && $first !== '-') {
                    $ret[$num] .= $q;
                }
            }
            $num++;
        }
        return $ret;
    }
}

/* ---------------------------------------------------- */
/* 输出转义 / 输入过滤（全局）                            */
/* ---------------------------------------------------- */

function h($value, $flags = ENT_QUOTES)
{
    if (is_array($value) || is_object($value)) {
        return '';
    }
    return htmlspecialchars((string)$value, $flags | ENT_SUBSTITUTE, 'UTF-8');
}

function js_str($value)
{
    $json = json_encode((string)$value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return $json === false ? '""' : $json;
}

function js_text($value)
{
    $s = (string)$value;
    $s = preg_replace('/<br\s*\/?>/i', "\n", $s);
    $s = strip_tags($s);
    $s = str_replace(array('"', "'", '\\'), array('＂', '＇', '＼'), $s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    return $s;
}

function to_int($value, $default = 0)
{
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && preg_match('/^-?[0-9]{1,20}$/', trim($value))) {
        return (int)trim($value);
    }
    return $default;
}
