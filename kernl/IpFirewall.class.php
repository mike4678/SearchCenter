<?php
/* ----------------------------------------------------
 * IP 防火墙（前台全局拦截）
 * - 开关与模式存在 zd_system_setting：ipfw_enable(0/1)、ipfw_mode(black|white)
 * - 规则存在 zd_ipfirewall：start_ip / end_ip / remark / ControlMode
 * - 后台请求（/admin/）、安装程序（/install/）、拦截提示页（ip_blocked.php）不参与拦截
 * - 命中拦截时写一条 zd_system_log（method = IP防火墙）并跳转到 /ip_blocked.php
 * ---------------------------------------------------- */
class IpFirewall
{
    const LOG_METHOD = 'IP防火墙';

    /** 后台请求 / 安装页 / 提示页 / CLI 一律跳过 */
    public static function skipCurrentRequest(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
        $uri    = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        foreach (array('/admin/', '/install/') as $needle) {
            if (stripos($script, $needle) !== false || stripos($uri, $needle) !== false) {
                return true;
            }
        }
        if (basename($script) === 'ip_blocked.php' || strpos($uri, 'ip_blocked.php') !== false) {
            return true;
        }
        return false;
    }

    /** 客户端 IP：优先 REMOTE_ADDR，其后 X-Forwarded-For 首个 */
    public static function clientIp(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '';
        if ($ip === '' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
        }
        return $ip;
    }

    /** 读单个系统设置（不依赖 $dou） */
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

    public static function enabled($db): bool
    {
        return self::setting($db, 'ipfw_enable', '0') === '1';
    }

    /** black=黑名单（命中即拦）；white=白名单（不命中即拦） */
    public static function mode($db): string
    {
        return self::setting($db, 'ipfw_mode', 'black') === 'white' ? 'white' : 'black';
    }

    /** 取全部规则；表不存在时返回空数组 */
    public static function rules($db): array
    {
        $rows = array();
        $res  = $db->query('SELECT `id`,`start_ip`,`end_ip`,`remark`,`ControlMode` FROM `zd_ipfirewall` ORDER BY `id` ASC');
        if (!$res) {
            return $rows;
        }
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    private static function packed($ip)
    {
        $ip = trim((string)$ip);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }
        $p = @inet_pton($ip);
        return ($p === false) ? null : $p;
    }

    /** 同族地址按二进制比较（IPv4 4 字节 / IPv6 16 字节） */
    public static function inRange($ipPacked, $startPacked, $endPacked): bool
    {
        if ($ipPacked === null || $startPacked === null || $endPacked === null) {
            return false;
        }
        if (strlen($ipPacked) !== strlen($startPacked) || strlen($startPacked) !== strlen($endPacked)) {
            return false;
        }
        return (strcmp($ipPacked, $startPacked) >= 0 && strcmp($ipPacked, $endPacked) <= 0);
    }

    /** 返回命中的规则（数组）或 null */
    public static function matchRule($db, $ip)
    {
        $ipPacked = self::packed($ip);
        if ($ipPacked === null) {
            return null;
        }
        foreach (self::rules($db) as $rule) {
            $s = self::packed($rule['start_ip']);
            $e = self::packed($rule['end_ip']);
            if (self::inRange($ipPacked, $s, $e)) {
                return $rule;
            }
        }
        return null;
    }

    /** 当前 IP 在当前配置下是否会被拦截（后台预览用） */
    public static function wouldBlock($db, $ip): array
    {
        if (!self::enabled($db)) {
            return array(false, '未启用');
        }
        $hit  = self::matchRule($db, $ip);
        $mode = self::mode($db);
        if ($mode === 'black') {
            return $hit ? array(true, '命中黑名单规则：' . ipfw_rule_text($hit)) : array(false, '未命中黑名单');
        }
        return $hit ? array(false, '命中白名单规则：' . ipfw_rule_text($hit)) : array(true, '不在白名单内');
    }

    /** 写入/更新单个系统设置（依赖 zd_system_setting 的 vars 唯一键） */
    public static function putSetting($db, $key, $value)
    {
        $stmt = $db->prepare("INSERT INTO `zd_system_setting` (`vars`,`value`,`备注`) VALUES (?,?,?) "
            . "ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        if (!$stmt) {
            return false;
        }
        $remark = 'IP防火墙';
        $stmt->bind_param('sss', $key, $value, $remark);
        return @$stmt->execute();
    }

    /** 解析输入为 [start,end]：支持 1.2.3.4 / 1.2.3.0/24 / 1.2.3.0-1.2.3.255 */
    public static function parseInput($input)
    {
        $input = trim((string)$input);
        if ($input === '') {
            return array(null, '地址不能为空');
        }
        // 起止段
        if (preg_match('/^([0-9a-fA-F:\.]+)\s*-\s*([0-9a-fA-F:\.]+)$/', $input, $m)) {
            if (!filter_var($m[1], FILTER_VALIDATE_IP) || !filter_var($m[2], FILTER_VALIDATE_IP)) {
                return array(null, '起止地址格式不正确');
            }
            $a = @inet_pton($m[1]);
            $b = @inet_pton($m[2]);
            if ($a === false || $b === false || strlen($a) !== strlen($b)) {
                return array(null, '起止地址必须是同一版本（IPv4/IPv6 不可混用）');
            }
            if (strcmp($a, $b) > 0) {
                return array(null, '起始地址必须小于结束地址');
            }
            return array(array($m[1], $m[2]), '');
        }
        // CIDR
        if (strpos($input, '/') !== false) {
            list($ip, $mask) = array_pad(explode('/', $input, 2), 2, '');
            $ip   = trim($ip);
            $mask = trim($mask);
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return array(null, 'IP 地址格式不正确');
            }
            $packed = @inet_pton($ip);
            $bits   = strlen($packed) * 8;
            if (!preg_match('/^\d{1,3}$/', $mask) || (int)$mask < 1 || (int)$mask > $bits) {
                return array(null, '掩码长度必须在 1 – ' . $bits . ' 之间');
            }
            return self::maskRange($ip, (int)$mask);
        }
        // 单 IP = /32 或 /128
        if (filter_var($input, FILTER_VALIDATE_IP)) {
            $packed = @inet_pton($input);
            $bits   = strlen($packed) * 8;
            return self::maskRange($input, $bits);
        }
        return array(null, '无法识别：请输入 1.2.3.4、1.2.3.0/24 或 1.2.3.1-1.2.3.9');
    }

    /** 由 IP + 掩码长度计算起止地址 */
    public static function maskRange($ip, $prefix)
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return array(null, 'IP 地址格式不正确');
        }
        $bits = strlen($packed) * 8;
        if ($prefix < 0 || $prefix > $bits) {
            return array(null, '掩码长度越界');
        }
        // 拆字节，按位清零主机位再填满，得到网络地址与广播地址
        $bytes    = str_split($packed);
        $start    = '';
        $end      = '';
        $fullByte = (int)floor($prefix / 8);
        $rest     = $prefix % 8;
        for ($i = 0; $i < count($bytes); $i++) {
            $byte = ord($bytes[$i]);
            if ($i < $fullByte) {
                $start .= chr($byte);
                $end   .= chr($byte);
                continue;
            }
            if ($i === $fullByte && $rest > 0) {
                $hostBits = 8 - $rest;
                $maskByte = (0xFF << $hostBits) & 0xFF;
                $start .= chr($byte & $maskByte);
                $end   .= chr(($byte & $maskByte) | (0xFF >> $rest));
                continue;
            }
            $start .= chr(0x00);
            $end   .= chr(0xFF);
        }
        $sIp = inet_ntop($start);
        $eIp = inet_ntop($end);
        return array(array($sIp, $eIp), '');
    }

    /** 拦截并记录日志（由 Init.php 调用） */
    public static function enforce($db)
    {
        if (self::skipCurrentRequest()) {
            return;
        }
        // 表/开关缺失时静默放过，避免把站点打挂
        if (!self::enabled($db)) {
            return;
        }
        $mode = self::mode($db);
        $ip   = self::clientIp();
        $hit  = self::matchRule($db, $ip);
        $blocked = ($mode === 'black') ? ($hit !== null) : ($hit === null);
        if (!$blocked) {
            return;
        }

        // 记录拦截日志
        $data = json_encode(array(
            'mode'  => $mode,
            'ip'    => $ip,
            'rule'  => $hit ? ($hit['start_ip'] . ' - ' . $hit['end_ip']) : '不在白名单',
            'remark'=> $hit ? (string)$hit['remark'] : '',
        ), JSON_UNESCAPED_UNICODE);
        $time = date('Y-m-d H:i:s');
        $addr = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $stmt = $db->prepare('INSERT INTO `zd_system_log` (`method`,`ip`,`data`,`addr`,`time`) VALUES (?,?,?,?,?)');
        if ($stmt) {
            $method = self::LOG_METHOD;
            $stmt->bind_param('sssss', $method, $ip, $data, $addr, $time);
            @$stmt->execute();
        }

        if (!headers_sent()) {
            header('Location: /ip_blocked.php', true, 302);
        }
        exit;
    }

    /** 写一条防火墙操作日志（后台设置变更、规则增删） */
    public static function logAction($db, $action, $detail = '')
    {
        $ip   = self::clientIp();
        $time = date('Y-m-d H:i:s');
        $data = json_encode(array('action' => $action, 'detail' => $detail), JSON_UNESCAPED_UNICODE);
        $addr = 'admin';
        $stmt = $db->prepare('INSERT INTO `zd_system_log` (`method`,`ip`,`data`,`addr`,`time`) VALUES (?,?,?,?,?)');
        if (!$stmt) {
            return false;
        }
        $method = self::LOG_METHOD;
        $stmt->bind_param('sssss', $method, $ip, $data, $addr, $time);
        return @$stmt->execute();
    }
}

/** 规则文本（日志/预览用） */
function ipfw_rule_text($rule)
{
    if (!$rule || !is_array($rule)) {
        return '';
    }
    $s = isset($rule['start_ip']) ? $rule['start_ip'] : '';
    $e = isset($rule['end_ip']) ? $rule['end_ip'] : '';
    $r = isset($rule['remark']) ? (string)$rule['remark'] : '';
    return $s . ($e !== $s ? ' - ' . $e : '') . ($r !== '' ? '（' . $r . '）' : '');
}

/** 兼容旧调用名 */
function ipfw_put_setting($db, $key, $value)
{
    return IpFirewall::putSetting($db, $key, $value);
}
