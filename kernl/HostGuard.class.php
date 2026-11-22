<?php
/* ----------------------------------------------------
 * 访问控制（后台域名白名单，内核级）
 * - 设置存 zd_system_setting：
 *     access_host_mode  all | white
 *     access_hosts      每行一个域名，支持 *.example.com、host:port
 * - all：允许任意网址访问后台
 * - white：只允许名单内域名访问 /admin 后台，其余后台请求跳转 /host_blocked.php 并留痕
 * - 仅限制后台（admin 目录）；查询中心、软件管家等前台页面不受影响
 * - 本机（localhost / 127.0.0.1 / ::1）始终放行，避免配置错误把自己锁死
 * ---------------------------------------------------- */
class HostGuard
{
    const LOG_METHOD = '访问控制';

    public static function setting($db, $key, $default = '')
    {
        $stmt = $db->prepare('SELECT `value` FROM `zd_system_setting` WHERE `vars` = ? LIMIT 1');
        if (!$stmt) {
            return $default;
        }
        $stmt->bind_param('s', $key);
        if (!$stmt->execute()) {
            return $default;
        }
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) {
            return $default;
        }
        $row = $res->fetch_assoc();
        return isset($row['value']) ? (string)$row['value'] : $default;
    }

    public static function mode($db): string
    {
        return self::setting($db, 'access_host_mode', 'all') === 'white' ? 'white' : 'all';
    }

    /** 读取名单（兼容换行、逗号、分号、空格分隔） */
    public static function hosts($db): array
    {
        $raw = self::setting($db, 'access_hosts', '');
        $out = array();
        foreach (preg_split('/[\r\n,;]+/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $line = self::normalize($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return array_values(array_unique($out));
    }

    /** 当前请求域名（含端口） */
    public static function currentHost(): string
    {
        if (!empty($_SERVER['HTTP_HOST'])) {
            return self::normalize((string)$_SERVER['HTTP_HOST']);
        }
        if (!empty($_SERVER['SERVER_NAME'])) {
            $port = !empty($_SERVER['SERVER_PORT']) ? ':' . (int)$_SERVER['SERVER_PORT'] : '';
            return self::normalize((string)$_SERVER['SERVER_NAME'] . $port);
        }
        return '';
    }

    /** 归一化：去协议、去路径、转小写 */
    public static function normalize($host): string
    {
        $host = trim((string)$host);
        if ($host === '') {
            return '';
        }
        if (strpos($host, '://') !== false) {
            $p = @parse_url($host);
            $host = isset($p['host']) ? $p['host'] : '';
            if (!empty($p['port'])) {
                $host .= ':' . (int)$p['port'];
            }
        } else {
            $host = preg_replace('#/.*$#', '', $host);
        }
        return strtolower(trim($host));
    }

    /** 单条规则匹配域名（支持 * 通配、无端口规则忽略端口） */
    public static function matches($pattern, $host): bool
    {
        $pattern = self::normalize($pattern);
        $host    = self::normalize($host);
        if ($pattern === '' || $host === '') {
            return false;
        }
        if ($pattern === '*') {
            return true;
        }
        $hostNoPort = preg_replace('/:\d+$/', '', $host);
        $patNoPort  = preg_replace('/:\d+$/', '', $pattern);
        $candidates = array($host, $hostNoPort);
        $patterns   = array($pattern);
        if ($patNoPort !== $pattern) {
            // 规则带端口：只匹配完整 host；否则补充无端口规则
        } elseif ($host !== $hostNoPort) {
            $patterns[] = $pattern; // 规则无端口，host 带端口时用无端口 host 比对
        }

        foreach ($patterns as $p) {
            foreach ($candidates as $h) {
                if (strpos($p, '*') === false) {
                    if ($p === $h) {
                        return true;
                    }
                } else {
                    $regex = '#^' . str_replace('\*', '.*', preg_quote($p, '#')) . '$#i';
                    if (preg_match($regex, $h)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /** 本机地址永远放行 */
    public static function isLoopback($host): bool
    {
        $h = preg_replace('/:\d+$/', '', self::normalize($host));
        return in_array($h, array('localhost', '127.0.0.1', '::1', '[::1]'), true);
    }

    public static function isAllowed($db, $host = null)
    {
        if ($host === null) {
            $host = self::currentHost();
        }
        if (self::mode($db) === 'all') {
            return true;
        }
        if (self::isLoopback($host)) {
            return true;
        }
        $hosts = self::hosts($db);
        if (empty($hosts)) {
            return true;   // 白名单为空时放行，避免把站点整体锁死
        }
        foreach ($hosts as $rule) {
            if (self::matches($rule, $host)) {
                return true;
            }
        }
        return false;
    }

    /** 当前请求是否属于后台（/admin/ 目录；兼容站点部署在子目录） */
    public static function isAdminRequest(): bool
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
        $uri    = isset($_SERVER['REQUEST_URI']) ? str_replace('\\', '/', (string)$_SERVER['REQUEST_URI']) : '';
        $path   = $script !== '' ? $script : preg_replace('/[?#].*$/', '', $uri);
        return (bool)preg_match('#(^|/)admin(/|$)#i', $path);
    }

    /** 前置拦截（Init.php 调用，仅后台 admin 目录生效） */
    public static function enforce($db)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (!self::isAdminRequest()) {
            return;   // 前台查询中心 / 软件管家等不参与后台访问控制
        }
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
        $uri    = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        if (basename($script) === 'host_blocked.php' || strpos($uri, 'host_blocked.php') !== false) {
            return;
        }
        if (self::isAllowed($db)) {
            return;
        }

        self::logBlock($db, self::currentHost(), $uri);

        if (!headers_sent()) {
            header('Location: /host_blocked.php', true, 302);
        }
        exit;
    }

    /** 拦截留痕（同域名同 IP 5 分钟内只记一次，避免刷日志） */
    public static function logBlock($db, $host, $uri = '')
    {
        $ip  = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
        $dir = defined('ROOT_PATH') ? ROOT_PATH . 'kernl/cache' : sys_get_temp_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $lock = $dir . '/host_block_' . md5($host . '|' . $ip) . '.txt';
        if (is_file($lock) && (time() - (int)@filemtime($lock)) < 300) {
            return;
        }
        @file_put_contents($lock, (string)time());

        $data = json_encode(array(
            'host' => $host,
            'uri'  => $uri,
            'ip'   => $ip,
        ), JSON_UNESCAPED_UNICODE);
        $time = date('Y-m-d H:i:s');
        $stmt = $db->prepare('INSERT INTO `zd_system_log` (`method`,`ip`,`data`,`addr`,`time`) VALUES (?,?,?,?,?)');
        if ($stmt) {
            $method = self::LOG_METHOD;
            $addr   = 'blocked';
            $stmt->bind_param('sssss', $method, $ip, $data, $addr, $time);
            @$stmt->execute();
        }
    }

    /** 后台保存设置时写一条变更日志 */
    public static function logAction($db, $detail)
    {
        $ip   = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
        $time = date('Y-m-d H:i:s');
        $data = json_encode(array('action' => '修改访问控制', 'detail' => $detail), JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO `zd_system_log` (`method`,`ip`,`data`,`addr`,`time`) VALUES (?,?,?,?,?)');
        if ($stmt) {
            $method = self::LOG_METHOD;
            $addr   = 'admin';
            $stmt->bind_param('sssss', $method, $ip, $data, $addr, $time);
            @$stmt->execute();
        }
    }
}
