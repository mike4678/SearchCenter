<?php
/* ----------------------------------------------------
 * SearchCenter 模板扩展类（配套 template/searchcenter）
 * 约定：模板新增函数一律放在 kernl/<目录>/xxxx.class.php，
 *       安装模板时由系统释放到 kernl/；模板本身不连库，
 *       全部复用系统统一连接 $db 与系统模块 $dou。
 * 覆盖：MAC 前缀查询、IP 归属地、进程安全等级、文件后缀/文件头、软件库检索
 * ---------------------------------------------------- */

class SearchCenter
{
    /** @var object 系统统一数据库连接（Db 类，旧 API 兼容层） */
    private $db;

    /** @var object 系统模块（System） */
    private $dou;

    public function __construct($db = null, $dou = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            global $db;
            $this->db = $db;
        }
        if ($dou !== null) {
            $this->dou = $dou;
        } else {
            global $dou;
            $this->dou = $dou;
        }
    }

    /* ---------- 基础 ---------- */

    /** 站点根地址（协议 + Host），用于生成模板内绝对链接 */
    public function base()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'localhost';
        $proto = function_exists('PROTOCOL') ? PROTOCOL : 'http://';
        return rtrim($proto . $host, '/');
    }

    /** 模板内端点地址前缀 */
    public function endpoint($file)
    {
        return $this->base() . '/template/searchcenter/' . ltrim((string)$file, '/');
    }

    /** 读取系统设置（zd_system_setting） */
    public function setting($vars, $default = '')
    {
        if ($this->dou && method_exists($this->dou, 'Info')) {
            $v = $this->dou->Info($vars);
            if ($v === null || $v === false || $v === '') {
                return $default;
            }
            return (string)$v;
        }
        return $default;
    }

    /**
     * 软件 / 驱动下载链接（后台“查询中心设置”分两项配置，每行一条固定地址）
     * 行格式：显示名称|URL（只写 URL 时显示名称默认为“下载”）
     * 仅接受 http/https 地址；未配置返回空数组（前台不显示下载入口）。
     */
    public function downloads($type)
    {
        $key = ($type === 'driver') ? 'searchcenter_driver_downloads' : 'searchcenter_soft_downloads';
        $raw = $this->setting($key, '');
        if (trim($raw) === '') {
            return array();
        }
        $out = array();
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (strpos($line, '|') !== false) {
                list($label, $url) = explode('|', $line, 2);
                $label = trim($label);
                $url = trim($url);
            } else {
                $label = '';
                $url = $line;
            }
            if ($url === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            if ($label === '') {
                $label = '下载' . (count($out) > 0 ? (count($out) + 1) : '');
            }
            $out[] = array('label' => $label, 'url' => $url);
        }
        return $out;
    }

    /** 整数取值 */
    public function toInt($value, $default = 0)
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            return (int)trim($value);
        }
        return $default;
    }

    /** 表名/字段名白名单校验（反引号安全） */
    public function ident($name)
    {
        if (is_string($name) && preg_match('/^[A-Za-z0-9_]{1,40}$/', $name)) {
            return '`' . $name . '`';
        }
        return false;
    }

    /* ---------- 预处理语句兼容层 ---------- */

    /** 执行预处理查询，返回关联数组（兼容无 mysqlnd 的环境） */
    public function allPrepare($sql, array $params = array())
    {
        $rows = array();
        if (!$this->db) {
            return $rows;
        }
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return $rows;
        }
        if (!empty($params)) {
            $types = '';
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }
            $bind = array($types);
            foreach ($params as $k => $v) {
                $bind[] = $v;
            }
            $refs = array();
            foreach ($bind as $k => $v) {
                $refs[$k] = &$bind[$k];
            }
            if (!@call_user_func_array(array($stmt, 'bind_param'), $refs)) {
                $stmt->close();
                return $rows;
            }
        }
        if (!@$stmt->execute()) {
            $stmt->close();
            return $rows;
        }
        // mysqlnd：直接用 get_result
        $result = @call_user_func(array($stmt, 'get_result'));
        if ($result) {
            while ($r = $result->fetch_assoc()) {
                $rows[] = $r;
            }
            $result->free();
            $stmt->close();
            return $rows;
        }
        // 无 mysqlnd：动态 bind_result
        $meta = @$stmt->result_metadata();
        if ($meta) {
            $fields = array();
            $bindRefs = array();
            while ($f = $meta->fetch_field()) {
                $fields[$f->name] = null;
                $bindRefs[] = &$fields[$f->name];
            }
            if (!empty($bindRefs)) {
                @call_user_func_array(array($stmt, 'bind_result'), $bindRefs);
                while (@$stmt->fetch()) {
                    $row = array();
                    foreach ($fields as $k => $v) {
                        $row[$k] = $v;
                    }
                    $rows[] = $row;
                }
            }
            $meta->free();
        }
        $stmt->close();
        return $rows;
    }

    /** 单行查询 */
    public function onePrepare($sql, array $params = array())
    {
        $rows = $this->allPrepare($sql, $params);
        return empty($rows) ? null : $rows[0];
    }

    /** 执行写入/更新类预处理语句，成功返回受影响行数，失败返回 false */
    public function execPrepare($sql, array $params = array())
    {
        if (!$this->db) {
            return false;
        }
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return false;
        }
        if (!empty($params)) {
            $types = '';
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }
            $bind = array($types);
            foreach ($params as $v) {
                $bind[] = $v;
            }
            $refs = array();
            foreach ($bind as $k => $v) {
                $refs[$k] = &$bind[$k];
            }
            @call_user_func_array(array($stmt, 'bind_param'), $refs);
        }
        $ok = @$stmt->execute();
        $affected = $ok ? (int)$stmt->affected_rows : false;
        $stmt->close();
        return $affected;
    }

    /** 某表单字段的模糊检索，返回 array(rows, count) */
    public function searchLikeRows($table, $field, $value, $limit = 200)
    {
        $t = $this->ident($table);
        $f = $this->ident($field);
        if ($t === false || $f === false) {
            return array(array(), 0);
        }
        $value = (string)$value;
        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, 100, 'UTF-8');
        }
        $like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $value) . '%';
        $rows = $this->allPrepare(
            "SELECT * FROM " . $t . " WHERE " . $f . " LIKE ? LIMIT " . max(1, (int)$limit),
            array($like)
        );
        return array($rows, count($rows));
    }

    /* ---------- MAC 查询 ---------- */

    /**
     * 按 OUI（MAC 前 6 位）查询注册厂商
     * @return array|null
     */
    public function macLookup($mac)
    {
        $mac = trim((string)$mac);
        if (!preg_match('/^([A-Fa-f0-9]{2}[-,:]){5}[A-Fa-f0-9]{2}$/', $mac)) {
            return array('ok' => false, 'message' => '不是一个有效的MAC地址！有效的格式为：xx-xx-xx-xx-xx-xx');
        }
        $oui = substr(str_replace(array('-', ':'), '', $mac), 0, 6);
        $rows = $this->allPrepare(
            "SELECT * FROM zd_mac_addr WHERE OUI_HEX LIKE ? LIMIT 1",
            array('%' . $oui . '%')
        );
        if (count($rows) !== 1) {
            return array('ok' => false, 'message' => '查询无果');
        }
        return array(
            'ok'     => true,
            'mac'    => $mac,
            'oui'    => $oui,
            'vendor' => isset($rows[0]['Organization']) ? trim((string)$rows[0]['Organization']) : '',
        );
    }

    /* ---------- IP 归属地 ---------- */

    /**
     * IP 归属地查询（v4 / v6 自动选择；库缺失时给出明确提示）
     * @return array ok/region/beginIP/endIP/message
     */
    public function ipLookup($ip)
    {
        $ip = trim((string)$ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->ipv4($ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6($ip);
        }
        return array('ok' => false, 'message' => '不是一个有效的ip地址！');
    }

    /** IPv4 归属地：优先内核 qqwry 库 kernl/ipdata.dat（czdb 授权过期时仍可用） */
    private function ipv4($ip)
    {
        $dat = (defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(__DIR__)) . '/') . 'kernl/ipdata.dat';
        if (!is_file($dat)) {
            return array('ok' => false, 'message' => 'IPv4 归属地库缺失（kernl/ipdata.dat）');
        }
        if (!class_exists('IPQueryEngine')) {
            $cls = dirname(dirname(__DIR__)) . '/kernl/ip.Class.php';
            if (is_file($cls)) {
                require_once $cls;
            }
        }
        if (!class_exists('IPQueryEngine')) {
            return array('ok' => false, 'message' => 'IPv4 归属地库组件加载失败');
        }
        try {
            $engine = new IPQueryEngine($dat);
            $detail = $engine->getDetail($ip);
        } catch (Throwable $e) {
            error_log('IPv4 归属地查询失败: ' . $e->getMessage());
            return array('ok' => false, 'message' => '归属地查询失败');
        }
        if (!is_array($detail)) {
            return array('ok' => false, 'message' => '查询无果');
        }
        $region = trim((string)$detail['dataA'] . ' ' . (string)$detail['dataB']);
        return array(
            'ok' => true,
            'ip' => $ip,
            'family' => 'IPv4',
            'region' => $region,
            'beginIP' => (string)$detail['beginIP'],
            'endIP' => (string)$detail['endIP'],
            'dataA' => (string)$detail['dataA'],
            'dataB' => (string)$detail['dataB'],
            'localIP' => $this->localIp(),
        );
    }

    /** IPv6 归属地：走内核 czdb v6 库 */
    private function ipv6($ip)
    {
        $searcher = isset($GLOBALS['dbSearcherv6']) ? $GLOBALS['dbSearcherv6'] : null;
        if (!$searcher) {
            $dat = (defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(__DIR__)) . '/') . 'kernl/cz88_public_v6.czdb';
            if (!is_file($dat) || !class_exists('Czdb\DbSearcher')) {
                return array('ok' => false, 'message' => 'IPv6 归属地库不可用（kernl/cz88_public_v6.czdb）');
            }
            try {
                $searcher = new Czdb\DbSearcher($dat, 'BTREE', defined('ip_key') ? ip_key : '');
            } catch (Throwable $e) {
                return array('ok' => false, 'message' => 'IPv6 归属地库初始化失败：' . $e->getMessage());
            }
        }
        try {
            $region = $searcher->search($ip);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'expired') !== false) {
                return array('ok' => false, 'message' => 'IPv6 归属地库授权已过期，请更新 czdb 库后再查询');
            }
            return array('ok' => false, 'message' => 'IPv6 归属地查询失败');
        }
        if (is_array($region)) {
            $region = implode(' ', array_filter(array_map('strval', $region)));
        }
        if ($region === null || $region === '') {
            return array('ok' => false, 'message' => '查询无果');
        }
        return array(
            'ok' => true,
            'ip' => $ip,
            'family' => 'IPv6',
            'region' => (string)$region,
            'localIP' => $this->localIp(),
        );
    }

    /** 访客/本机 IP */
    public function localIp()
    {
        if ($this->dou && method_exists($this->dou, 'Get_LocalIP')) {
            $ip = (string)$this->dou->Get_LocalIP();
            if ($ip !== '') {
                return $ip;
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
    }

    /* ---------- 进程 / 后缀 ---------- */

    /** 进程安全信息（0-1 危险 / 2-3 谨慎 / 4-5 安全） */
    public function processLevel($safe)
    {
        $safe = $this->toInt($safe, -1);
        if ($safe === 0 || $safe === 1) {
            return '危险';
        }
        if ($safe === 2 || $safe === 3) {
            return '谨慎';
        }
        if ($safe === 4 || $safe === 5) {
            return '安全';
        }
        return '未知';
    }

    /** 进程查询 */
    public function processLookup($name)
    {
        list($rows, $count) = $this->searchLikeRows('zd_process_list', 'p_name', $name, 200);
        return array('rows' => $rows, 'count' => $count);
    }

    /** 后缀名 / 文件头查询 */
    public function filetypeLookup($keyword, $field = 'filetype')
    {
        if ($field === 'binarydata') {
            // 文件头库里可能写成 “4d 5a” / “4D5A” / “0x4D5A”，统一去掉分隔符后再比
            $norm = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string)$keyword));
            if ($norm === '') {
                return array('rows' => array(), 'count' => 0);
            }
            $like = '%' . $norm . '%';
            $rows = $this->allPrepare(
                "SELECT * FROM `zd_filetypetable` WHERE `binarydata` IS NOT NULL AND `binarydata` <> ''"
                . " AND REPLACE(REPLACE(REPLACE(UPPER(`binarydata`),' ',''),'0X',''),'0x','') LIKE ? LIMIT 200",
                array($like)
            );
            return array('rows' => $rows, 'count' => count($rows));
        }
        list($rows, $count) = $this->searchLikeRows('zd_filetypetable', 'filetype', $keyword, 200);
        return array('rows' => $rows, 'count' => $count);
    }

    /** 软件库检索（历史软件名） */
    public function softwareLookup($keyword)
    {
        list($rows, $count) = $this->searchLikeRows('zd_softwarelibrary', 'softname', $keyword, 200);
        return array('rows' => $rows, 'count' => $count);
    }

    /** 驱动库检索（zd_device_library.softname） */
    public function deviceLookup($keyword)
    {
        if (!$this->db || !$this->db->table_exist('zd_device_library')) {
            return array('rows' => array(), 'count' => 0, 'message' => '驱动查询库（zd_device_library）未安装');
        }
        list($rows, $count) = $this->searchLikeRows('zd_device_library', 'softname', $keyword, 200);
        return array('rows' => $rows, 'count' => $count);
    }

    /** 各库可查询数量（关于页统计） */
    public function counts()
    {
        $out = array('mac' => 0, 'process' => 0, 'filetype' => 0, 'software' => 0);
        foreach (array('mac' => 'zd_mac_addr', 'process' => 'zd_process_list',
                     'filetype' => 'zd_filetypetable', 'software' => 'zd_softwarelibrary') as $k => $t) {
            $tb = $this->ident($t);
            if ($tb === false) {
                continue;
            }
            $n = $this->db ? $this->db->get_one("SELECT COUNT(*) FROM " . $tb) : 0;
            $out[$k] = (int)$n;
        }
        return $out;
    }

    /* ---------- 智能类型识别 ---------- */

    /**
     * 根据输入内容自动判断该查哪一类（前端不再要求用户选择）
     * 返回：mac / ip / process / hz / hz_binary / softhistory / driverquery
     * 说明：先只作“主类型”判断；同一关键词在多个库都有记录时由 detectCandidates() 给出全部候选。
     */
    public function detectType($keyword)
    {
        $types = $this->detectCandidates($keyword);
        return empty($types) ? 'process' : $types[0];
    }

    /**
     * 列出所有可能命中的库（按优先级排序）
     * 格式类（MAC / IP / 文件头）只返回单一候选；文本类会同时返回多个，
     * 例如 “360” 既在进程库又在软件历史库，返回 array('process','softhistory')。
     */
    public function detectCandidates($keyword)
    {
        $kw = trim((string)$keyword);
        if ($kw === '') {
            return array('process');
        }

        // 1) MAC：xx-xx-xx-xx-xx-xx / xx:xx:... / 纯 12 位十六进制
        if (preg_match('/^([A-Fa-f0-9]{2}[-,:]){5}[A-Fa-f0-9]{2}$/', $kw)) {
            return array('mac');
        }
        if (preg_match('/^[A-Fa-f0-9]{12}$/', $kw)) {
            return array('mac');
        }

        // 2) IP（v4 / v6）
        if (filter_var($kw, FILTER_VALIDATE_IP)) {
            return array('ip');
        }

        // 3) 文件头：十六进制串且能在文件头库里命中
        if (preg_match('/^([0-9A-Fa-f]{2}[ ]*){2,}$/', $kw) && $this->existsBinaryData($kw)) {
            return array('hz_binary');
        }

        $low = strtolower($kw);
        $ext = ltrim($low, '.');

        // 4) 以点开头：明确是查后缀
        if (strpos($low, '.') === 0 && $ext !== '') {
            return array('hz');
        }

        // 5) 完整文件名（带可执行扩展名）或进程库精确同名 → 只按进程查
        if (preg_match('/\.(exe|dll|sys|com|bat|cmd|scr|ocx|drv)$/i', $low)) {
            return array('process');
        }
        if ($this->existsProcess($kw)) {
            return array('process');
        }

        // 6) 文本类关键词：把每个能命中的库都收集起来
        $cands = array();
        if (preg_match('/^[a-z0-9]{1,8}$/', $ext) && $this->existsFiletype($ext)) {
            $cands[] = 'hz';
        }
        if ($this->likeCount('zd_process_list', 'p_name', $kw) > 0) {
            $cands[] = 'process';
        }
        if (!in_array('hz', $cands, true) && $this->likeCount('zd_filetypetable', 'title', $kw) > 0) {
            $cands[] = 'hz';
        }
        if ($this->likeCount('zd_softwarelibrary', 'softname', $kw) > 0) {
            $cands[] = 'softhistory';
        }
        if ($this->db && $this->db->table_exist('zd_device_library')
            && $this->likeCount('zd_device_library', 'softname', $kw) > 0) {
            $cands[] = 'driverquery';
        }

        if (empty($cands)) {
            return array('process');
        }

        // 排序：进程 → 软件历史 → 后缀 → 驱动
        $order = array('process' => 1, 'softhistory' => 2, 'hz' => 3, 'driverquery' => 4);
        usort($cands, function ($a, $b) use ($order) {
            return $order[$a] - $order[$b];
        });
        return array_values(array_unique($cands));
    }

    /**
     * 多库分组查询：每个命中的库给出若干条可点条目
     * @return array 形如 [{type,label,count,items:[{label,value}],more}]
     */
    public function groupLookup($keyword, array $types, $limit = 20)
    {
        $labels = array(
            'process'     => '进程安全',
            'softhistory' => '软件历史',
            'hz'          => '文件后缀',
            'driverquery' => '驱动信息',
        );
        $limit = max(1, (int)$limit);
        $groups = array();
        foreach ($types as $type) {
            if (!isset($labels[$type])) {
                continue;
            }
            $items = array();
            $count = 0;
            if ($type === 'process') {
                $r = $this->processLookup($keyword);
                $count = (int)$r['count'];
                foreach ($r['rows'] as $row) {
                    if (count($items) >= $limit) {
                        break;
                    }
                    $name = isset($row['p_name']) ? (string)$row['p_name'] : '';
                    $level = $this->processLevel(isset($row['safevalue']) ? $row['safevalue'] : -1);
                    $label = $name . ($level !== '未知' ? '（' . $level . '）' : '');
                    $items[] = array('label' => $label, 'value' => $name);
                }
            } elseif ($type === 'softhistory') {
                $r = $this->softwareLookup($keyword);
                $count = (int)$r['count'];
                foreach ($r['rows'] as $row) {
                    if (count($items) >= $limit) {
                        break;
                    }
                    $name = isset($row['softname']) ? (string)$row['softname'] : '';
                    $items[] = array(
                        'label' => $name,
                        'value' => $name,
                        'urls'  => $this->downloads('software'),
                    );
                }
            } elseif ($type === 'hz') {
                $r = $this->filetypeLookup($keyword, 'filetype');
                $count = (int)$r['count'];
                foreach ($r['rows'] as $row) {
                    if (count($items) >= $limit) {
                        break;
                    }
                    $ft = isset($row['filetype']) ? (string)$row['filetype'] : '';
                    $title = isset($row['title']) ? (string)$row['title'] : '';
                    $items[] = array(
                        'label' => $ft . ($title !== '' ? '（' . $title . '）' : ''),
                        'value' => $ft,
                    );
                }
            } elseif ($type === 'driverquery') {
                $r = $this->deviceLookup($keyword);
                $count = (int)$r['count'];
                foreach ($r['rows'] as $row) {
                    if (count($items) >= $limit) {
                        break;
                    }
                    $name = isset($row['softname']) ? (string)$row['softname'] : '';
                    $items[] = array(
                        'label' => $name,
                        'value' => $name,
                        'urls'  => $this->downloads('driver'),
                    );
                }
            }
            if ($count < 1 || empty($items)) {
                continue;
            }
            $groups[] = array(
                'type'  => $type,
                'label' => $labels[$type],
                'count' => $count,
                'items' => $items,
                'more'  => $count > count($items),
            );
        }
        return $groups;
    }

    /** 进程库是否有精确同名记录 */
    public function existsProcess($name)
    {
        return $this->existsExact('zd_process_list', 'p_name', $name);
    }

    /** 后缀名库是否有精确记录（后缀不带点） */
    public function existsFiletype($ext)
    {
        return $this->existsExact('zd_filetypetable', 'filetype', $ext);
    }

    /** 文件头库是否命中（忽略空格 / 0x 前缀差异） */
    public function existsBinaryData($hex)
    {
        if (!$this->db || !$this->db->table_exist('zd_filetypetable')) {
            return false;
        }
        $norm = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string)$hex));
        if ($norm === '' || strlen($norm) < 4) {
            return false;
        }
        $row = $this->onePrepare(
            "SELECT `binarydata` FROM `zd_filetypetable` WHERE `binarydata` IS NOT NULL AND `binarydata` <> ''"
            . " AND REPLACE(REPLACE(REPLACE(UPPER(`binarydata`),' ',''),'0X',''),'0x','') LIKE ? LIMIT 1",
            array('%' . $norm . '%')
        );
        return !empty($row);
    }

    /** 某表某字段是否精确存在（表/字段走白名单） */
    protected function existsExact($table, $field, $value)
    {
        $t = $this->ident($table);
        $f = $this->ident($field);
        if ($t === false || $f === false || !$this->db || !$this->db->table_exist($table)) {
            return false;
        }
        $value = $this->cleanText($value, 100);
        if ($value === '') {
            return false;
        }
        $row = $this->onePrepare("SELECT 1 AS `hit` FROM " . $t . " WHERE " . $f . " = ? LIMIT 1", array($value));
        return !empty($row);
    }

    /** 某表某字段模糊命中条数（最多算到 1 条就够判断） */
    protected function likeCount($table, $field, $keyword)
    {
        $t = $this->ident($table);
        $f = $this->ident($field);
        if ($t === false || $f === false || !$this->db || !$this->db->table_exist($table)) {
            return 0;
        }
        $kw = $this->cleanText($keyword, 100);
        if ($kw === '') {
            return 0;
        }
        $row = $this->onePrepare(
            "SELECT 1 AS `hit` FROM " . $t . " WHERE " . $f . " LIKE ? LIMIT 1",
            array('%' . $this->likeEscape($kw) . '%')
        );
        return empty($row) ? 0 : 1;
    }

    /** 可选搜索类型（后台“查询中心设置”里配置的 openSearch 优先） */
    public function searchTypes()
    {
        $default = array(
            array('value' => 'mac', 'text' => 'MAC 地址'),
            array('value' => 'ipv4', 'text' => 'IPv4 归属地'),
            array('value' => 'ipv6', 'text' => 'IPv6 归属地'),
            array('value' => 'process', 'text' => '进程安全'),
            array('value' => 'hz', 'text' => '文件后缀'),
        );
        $setting = $this->setting('searchcenter_types', '');
        if ($setting === '') {
            $setting = $this->setting('openSearch', '');
        }
        if (trim($setting) === '') {
            return $default;
        }
        $out = array();
        foreach (explode(';', $setting) as $pair) {
            if (preg_match('/^([^\|]+)\|(.+)$/', trim($pair), $m)) {
                $out[] = array('value' => strtolower(trim($m[1])), 'text' => trim($m[2]));
            }
        }
        return empty($out) ? $default : $out;
    }

    /* ============================================================
     * 后台：关键词日志
     * ============================================================ */

    /** 记录一次前台查询（表不存在时静默跳过，不影响查询本身） */
    public function logKeyword($type, $keyword, $ok, $message = '')
    {
        if (!$this->db || !$this->db->table_exist('zd_search_log')) {
            return false;
        }
        $type    = $this->cleanText($type, 30);
        $keyword = $this->cleanText($keyword, 120);
        $message = $this->cleanText($message, 120);
        $ip      = $this->cleanText($this->localIp(), 64);
        $sql = "INSERT INTO `zd_search_log` (`keyword`,`stype`,`result`,`message`,`ip`,`addtime`) VALUES ("
            . $this->db->quote($keyword) . ","
            . $this->db->quote($type) . ","
            . ($ok ? '1' : '0') . ","
            . $this->db->quote($message) . ","
            . $this->db->quote($ip) . ","
            . $this->db->quote(date('Y-m-d H:i:s')) . ")";
        return (bool)$this->db->query($sql);
    }

    /** 关键词日志列表：array(rows, total) */
    public function logList($filters = array(), $page = 1, $size = 20)
    {
        $empty = array(array(), 0);
        if (!$this->db || !$this->db->table_exist('zd_search_log')) {
            return $empty;
        }
        $where = array();
        $type = isset($filters['type']) ? trim((string)$filters['type']) : '';
        if ($type !== '') {
            $where[] = "`stype` = " . $this->db->quote($this->cleanText($type, 30));
        }
        $kw = isset($filters['keyword']) ? trim((string)$filters['keyword']) : '';
        if ($kw !== '') {
            $where[] = "`keyword` LIKE " . $this->db->quote('%' . $this->likeEscape($this->cleanText($kw, 120)) . '%');
        }
        $result = isset($filters['result']) ? (string)$filters['result'] : '';
        if ($result === '1' || $result === '0') {
            $where[] = "`result` = " . $result;
        }
        $sqlWhere = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
        $total = (int)$this->db->get_one("SELECT COUNT(*) FROM `zd_search_log`" . $sqlWhere);
        $size  = max(1, min(200, (int)$size));
        $page  = max(1, (int)$page);
        $offset = ($page - 1) * $size;
        $query = $this->db->query("SELECT `id`,`keyword`,`stype`,`result`,`message`,`ip`,`addtime` FROM `zd_search_log`"
            . $sqlWhere . " ORDER BY `id` DESC LIMIT " . $offset . "," . $size);
        $rows = array();
        if ($query) {
            while ($row = $this->db->fetch_array($query)) {
                $rows[] = $row;
            }
        }
        return array($rows, $total);
    }

    /** 删除关键词日志（id 为 0 时清空） */
    public function logDelete($id = 0)
    {
        if (!$this->db || !$this->db->table_exist('zd_search_log')) {
            return false;
        }
        $id = (int)$id;
        if ($id > 0) {
            return (bool)$this->db->query("DELETE FROM `zd_search_log` WHERE `id` = " . $id);
        }
        return (bool)$this->db->query("DELETE FROM `zd_search_log`");
    }

    /** 搜索类型的中文名（后台列表用） */
    public function typeName($type)
    {
        $map = array();
        foreach ($this->searchTypes() as $item) {
            $map[$item['value']] = $item['text'];
        }
        $type = strtolower(trim((string)$type));
        if ($type === 'multi') {
            return '多库匹配';
        }
        if (isset($map[$type])) {
            return $map[$type];
        }
        $alias = array(
            'ip' => 'ipv4', 'ip_data' => 'ipv4', 'ipv4' => 'ipv4', 'ipv6' => 'ipv6',
            'process_data' => 'process', 'hz_binary' => 'hz', 'filetype' => 'hz',
            'software' => 'softhistory', 'softhistory' => 'softhistory',
            'driver' => 'driverquery', 'driverquery' => 'driverquery',
        );
        if (isset($alias[$type]) && isset($map[$alias[$type]])) {
            return $map[$alias[$type]];
        }
        return $type === '' ? '未知' : $type;
    }

    /* ============================================================
     * 后台：数据管理（MAC / 进程 / 后缀 / 软件库 / 驱动库）
     * ============================================================ */

    /** 各数据集的表结构定义（字段白名单，供后台管理与导入使用） */
    public function datasets()
    {
        return array(
            'mac' => array(
                'table'  => 'zd_mac_addr',
                'title'  => 'MAC 地址库（OUI / MA-L）',
                'fields' => array('OUI_HEX', 'Organization'),
                'labels' => array('OUI_HEX' => 'OUI（6 位十六进制）', 'Organization' => 'Organization（厂商）'),
                'search' => array('OUI_HEX', 'Organization'),
                'pk'     => '',
            ),
            'process' => array(
                'table'  => 'zd_process_list',
                'title'  => '进程信息库',
                'fields' => array('p_name', 'path', 'compy', 'safevalue', 'other'),
                'labels' => array('p_name' => '进程名', 'path' => '进程路径', 'compy' => '进程公司',
                                  'safevalue' => '安全等级（1-5）', 'other' => '描述'),
                'search' => array('p_name', 'path', 'compy', 'other'),
                'pk'     => '',
            ),
            'filetype' => array(
                'table'  => 'zd_filetypetable',
                'title'  => '后缀名 / 文件头库',
                'fields' => array('filetype', 'binarydata', 'Open', 'title', 'searchkeyword'),
                'labels' => array('filetype' => '后缀名', 'binarydata' => '二进制值（文件头）',
                                  'Open' => '执行方式', 'title' => '标题', 'searchkeyword' => '搜索关键词'),
                'search' => array('filetype', 'title', 'searchkeyword', 'binarydata', 'Open'),
                'pk'     => '',
            ),
            'software' => array(
                'table'  => 'zd_softwarelibrary',
                'title'  => '软件历史库',
                'fields' => array('softname', 'softtype'),
                'labels' => array('softname' => '软件名', 'softtype' => '软件分类'),
                'search' => array('softname', 'softtype'),
                'pk'     => 'id',
            ),
            'device' => array(
                'table'  => 'zd_device_library',
                'title'  => '驱动数据库',
                'fields' => array('softname', 'softtype'),
                'labels' => array('softname' => '软件名', 'softtype' => '软件分类'),
                'search' => array('softname', 'softtype'),
                'pk'     => 'id',
            ),
        );
    }

    /** 取某个数据集的定义（非法 key 返回 null） */
    public function dataset($key)
    {
        $all = $this->datasets();
        return isset($all[$key]) ? $all[$key] : null;
    }

    /** 数据集分页查询：array(rows, total, error) */
    public function dataList($key, $keyword = '', $page = 1, $size = 20)
    {
        $def = $this->dataset($key);
        if (!$def || !$this->db || !$this->db->table_exist($def['table'])) {
            return array(array(), 0, '表不存在：' . ($def ? $def['table'] : $key));
        }
        $table = $this->ident($def['table']);
        if ($table === false) {
            return array(array(), 0, '表名非法');
        }
        $keyword = $this->cleanText($keyword, 120);
        $where = '';
        if ($keyword !== '') {
            $parts = array();
            foreach ($def['search'] as $field) {
                $f = $this->ident($field);
                if ($f === false) {
                    continue;
                }
                $parts[] = $f . " LIKE " . $this->db->quote('%' . $this->likeEscape($keyword) . '%');
            }
            if (!empty($parts)) {
                $where = ' WHERE (' . implode(' OR ', $parts) . ')';
            }
        }
        $total = (int)$this->db->get_one("SELECT COUNT(*) FROM " . $table . $where);
        $size  = max(1, min(200, (int)$size));
        $page  = max(1, (int)$page);
        $offset = ($page - 1) * $size;

        $select = array();
        if ($def['pk'] !== '') {
            $select[] = $this->ident($def['pk']);
        }
        foreach ($def['fields'] as $field) {
            $f = $this->ident($field);
            if ($f !== false) {
                $select[] = $f;
            }
        }
        $order = $def['pk'] !== '' ? ' ORDER BY ' . $this->ident($def['pk']) . ' DESC' : '';
        $query = $this->db->query("SELECT " . implode(',', $select) . " FROM " . $table
            . $where . $order . " LIMIT " . $offset . "," . $size);
        $rows = array();
        if ($query) {
            while ($row = $this->db->fetch_array($query)) {
                $rows[] = $row;
            }
        }
        return array($rows, $total, '');
    }

    /** 数据集新增一条（字段白名单 + 必填校验） */
    public function dataAdd($key, array $input)
    {
        $def = $this->dataset($key);
        if (!$def || !$this->db || !$this->db->table_exist($def['table'])) {
            return array(false, '表不存在：' . ($def ? $def['table'] : $key));
        }
        $table = $this->ident($def['table']);
        if ($table === false) {
            return array(false, '表名非法');
        }
        $cols = array();
        $vals = array();
        foreach ($def['fields'] as $field) {
            $f = $this->ident($field);
            if ($f === false) {
                continue;
            }
            $value = isset($input[$field]) ? trim((string)$input[$field]) : '';
            if ($field === 'safevalue') {
                $value = (string)$this->toInt($value, 3);
                if ((int)$value < 1 || (int)$value > 5) {
                    $value = '3';
                }
            }
            $value = $this->cleanText($value, ($field === 'binarydata' || $field === 'path') ? 500 : 255);
            $cols[] = $f;
            $vals[] = $this->db->quote($value);
        }
        // 必填项：MAC 的 OUI、进程名、后缀名、软件名的第一列
        $first = $def['fields'][0];
        $firstValue = isset($input[$first]) ? trim((string)$input[$first]) : '';
        if ($firstValue === '') {
            return array(false, $def['labels'][$first] . '不能为空');
        }
        $sql = "INSERT INTO " . $table . " (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
        $ok = $this->db->query($sql);
        return array((bool)$ok, $ok ? '已新增' : '新增失败');
    }

    /** 数据集删除一条（无主键的表按第一个字段精确匹配） */
    public function dataDelete($key, $id, $value = '')
    {
        $def = $this->dataset($key);
        if (!$def || !$this->db || !$this->db->table_exist($def['table'])) {
            return array(false, '表不存在');
        }
        $table = $this->ident($def['table']);
        if ($table === false) {
            return array(false, '表名非法');
        }
        if ($def['pk'] !== '') {
            $id = (int)$id;
            if ($id <= 0) {
                return array(false, '编号非法');
            }
            $ok = $this->db->query("DELETE FROM " . $table
                . " WHERE " . $this->ident($def['pk']) . " = " . $id . " LIMIT 1");
            return array((bool)$ok, $ok ? '已删除' : '删除失败');
        }
        $first = $def['fields'][0];
        $value = $this->cleanText($value, 255);
        if ($value === '') {
            return array(false, '缺少要删除的记录值');
        }
        $ok = $this->db->query("DELETE FROM " . $table
            . " WHERE " . $this->ident($first) . " = " . $this->db->quote($value) . " LIMIT 1");
        return array((bool)$ok, $ok ? '已删除' : '删除失败');
    }

    /**
     * 解析 OUI / MA-L 文本（IEEE 导出格式）
     * 形如 “28-6F-B9   (hex)  Organization” 与 “286FB9  (base 16)  Organization”，
     * base16 行优先覆盖同 OUI 的 hex 行。
     * 返回 array(OUI大写6位 => Organization)
     */
    public function parseOuiFile($content)
    {
        $out = array();
        $content = str_replace(array("\r\n", "\r"), "\n", (string)$content);
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^([0-9A-Fa-f]{6})\s+\(base 16\)\s+(.+)$/', $line, $m)) {
                $out[strtoupper($m[1])] = trim($m[2]);
                continue;
            }
            if (preg_match('/^([0-9A-Fa-f]{2}-[0-9A-Fa-f]{2}-[0-9A-Fa-f]{2})\s+\(hex\)\s+(.+)$/', $line, $m)) {
                $oui = strtoupper(str_replace('-', '', $m[1]));
                if (!isset($out[$oui])) {
                    $out[$oui] = trim($m[2]);
                }
            }
        }
        return $out;
    }

    /** 批量导入 OUI：返回 array(新增数, 跳过数, 错误信息) */
    public function importOui($content)
    {
        if (!$this->db || !$this->db->table_exist('zd_mac_addr')) {
            return array(0, 0, '表不存在：zd_mac_addr');
        }
        $pairs = $this->parseOuiFile($content);
        if (empty($pairs)) {
            return array(0, 0, '未解析到有效记录，请确认文件为 IEEE OUI/MA-L 导出格式');
        }
        $existing = array();
        $query = $this->db->query("SELECT `OUI_HEX` FROM `zd_mac_addr`");
        if ($query) {
            while ($row = $this->db->fetch_array($query)) {
                $existing[strtoupper(trim((string)$row['OUI_HEX']))] = 1;
            }
        }
        $added = 0;
        $skipped = 0;
        $batch = array();
        foreach ($pairs as $oui => $org) {
            if (isset($existing[$oui])) {
                $skipped++;
                continue;
            }
            $batch[] = "(" . $this->db->quote($oui) . "," . $this->db->quote($this->cleanText($org, 255)) . ")";
            $existing[$oui] = 1;
            $added++;
            if (count($batch) >= 200) {
                if (!$this->db->query("INSERT INTO `zd_mac_addr` (`OUI_HEX`,`Organization`) VALUES " . implode(',', $batch))) {
                    return array($added - count($batch), $skipped, '写入失败（第 ' . $added . ' 条附近）');
                }
                $batch = array();
            }
        }
        if (!empty($batch)) {
            $ok = $this->db->query("INSERT INTO `zd_mac_addr` (`OUI_HEX`,`Organization`) VALUES " . implode(',', $batch));
            if (!$ok) {
                return array($added - count($batch), $skipped, '写入失败（最后一批）');
            }
        }
        return array($added, $skipped, '');
    }

    /* ---------- 内部工具 ---------- */

    /** 截断并按 UTF-8 处理 */
    protected function cleanText($value, $len = 255)
    {
        $value = trim((string)$value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $len, 'UTF-8');
        }
        return substr($value, 0, $len);
    }

    /** LIKE 通配符转义（配合参数化/quote 使用） */
    protected function likeEscape($value)
    {
        return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), (string)$value);
    }

    /** 转成 latin1 可存储的字符串（MAC 表为 latin1，中文会被 MySQL 拒绝） */
    protected function latin1Safe($value)
    {
        $value = (string)$value;
        if ($value === '' || !preg_match('/[^\x20-\x7E]/', $value)) {
            return $value;
        }
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value);
            if ($converted !== false && $converted !== '') {
                return $converted;
            }
            $converted = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $value);
            if ($converted !== false) {
                return $converted;
            }
        }
        return preg_replace('/[^\x20-\x7E]/', '', $value);
    }}
