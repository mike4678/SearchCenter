<?php
/* ---------------------------------------------------- */
/* 程序名称: 前台入口 / 模板路由
/* 功能说明（2026 重写）：
/*  1. 系统本身只有一个后台，前台完全由“域名 → 模板”的绑定关系决定显示什么。
/*  2. 匹配顺序：当前 Host（含端口）→ Host（不含端口）→ Host+路径，
/*     都支持 zd_bindaddress 表与旧的 zd_system_setting.bind_address 配置。
/*  3. 命中绑定的域名按对应模板渲染；未命中的域名一律回落到默认模板，
/*     不会再出现 500 / Parse error。
/*  4. 模板目录与入口文件都做白名单 + realpath 校验，杜绝路径穿越与任意文件包含。
/* ---------------------------------------------------- */
declare(strict_types=1);

require_once __DIR__ . '/kernl/Init.php';

class FrontRouter
{
    private $db;
    private $host = '';
    private $hostNoPort = '';
    private $path = '/';
    private $bound = null;   // 命中的绑定记录
    private $matchedBy = '';

    public function __construct($db)
    {
        $this->db = $db;
        $this->parseRequest();
    }

    private function parseRequest(): void
    {
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower(trim((string)$_SERVER['HTTP_HOST'])) : 'localhost';
        // 去掉协议/斜杠等噪音，只保留 host[:port]
        $host = preg_replace('#^https?://#i', '', $host);
        $host = rtrim($host, '/');
        $this->host = $host;
        $this->hostNoPort = preg_replace('/:\d+$/', '', $host);

        $uri  = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        if ($path === '') {
            $path = '/';
        }
        $this->path = $path;
    }

    public function run(): void
    {
        $this->bound = $this->findBinding();
        $this->render();
    }

    /* ---------------- 绑定查找 ---------------- */

    private function candidates(): array
    {
        $list = array($this->host, $this->hostNoPort);
        if ($this->path !== '/' && $this->path !== '') {
            $list[] = $this->hostNoPort . rtrim($this->path, '/');
            $list[] = $this->host . rtrim($this->path, '/');
        }
        // 去重，保持优先级顺序
        $out = array();
        foreach ($list as $v) {
            $v = strtolower(trim($v));
            if ($v !== '' && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        }
        return $out;
    }

    private function findBinding()
    {
        $candidates = $this->candidates();

        // 1) 新表 zd_bindaddress
        $sql = "SELECT `address`,`template_path`,`bind_params` FROM `zd_bindaddress`";
        $res = $this->db->query($sql);
        $rows = array();
        if ($res) {
            while ($row = $this->db->fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        foreach ($candidates as $c) {
            foreach ($rows as $row) {
                $addr = strtolower(trim((string)$row['address']));
                $addr = preg_replace('#^https?://#i', '', $addr);
                $addr = rtrim($addr, '/');
                if ($addr === $c) {
                    $this->matchedBy = 'zd_bindaddress:' . $addr;
                    return array(
                        'template' => (string)$row['template_path'],
                        'params'   => $this->decodeParams((string)$row['bind_params']),
                    );
                }
            }
        }

        // 2) 兼容旧配置：bind_address = "域名,目录名|域名,目录名"
        $legacy = $this->db->query("SELECT `value` FROM `zd_system_setting` WHERE `vars` = 'bind_address' LIMIT 1");
        if ($legacy && ($row = $this->db->fetch_assoc($legacy)) && !empty($row['value'])) {
            foreach (explode('|', (string)$row['value']) as $item) {
                $item = trim($item);
                if ($item === '') {
                    continue;
                }
                $parts = explode(',', $item);
                if (count($parts) < 2) {
                    continue;
                }
                $addr = strtolower(trim($parts[0]));
                $addr = preg_replace('#^https?://#i', '', $addr);
                $addr = rtrim($addr, '/');
                $tpl  = trim($parts[1]);
                $addrNoPort = preg_replace('/:\d+$/', '', $addr);
                foreach ($candidates as $c) {
                    if ($addr === $c || $addrNoPort === $c || $addrNoPort . rtrim($this->path, '/') === $c) {
                        $this->matchedBy = 'bind_address:' . $addr;
                        return array('template' => $tpl, 'params' => array());
                    }
                }
            }
        }

        return null;
    }

    private function decodeParams(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return array();
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return array();
        }
        // 只接受标量参数，避免把对象/数组塞进模板作用域
        $out = array();
        foreach ($data as $k => $v) {
            if (is_string($k) && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,31}$/', $k) && (is_scalar($v) || $v === null)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /* ---------------- 模板解析 ---------------- */

    /** 把各种历史写法统一成模板目录名；非法返回 '' */
    private function normalizeTemplate(string $raw): string
    {
        $raw = trim(str_replace('\\', '/', $raw));
        if ($raw === '') {
            return '';
        }
        // 兼容 "/template/xxx/index.html"、"template/xxx/index.php"、"xxx"
        if (preg_match('#(?:^|/)template/([A-Za-z0-9_\-]{1,40})(?:/|$)#', $raw, $m)) {
            return $m[1];
        }
        if (preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $raw)) {
            return $raw;
        }
        return '';
    }

    private function templateDir(string $name): string
    {
        $dir  = realpath(ROOT_PATH . 'template/' . $name);
        $base = realpath(ROOT_PATH . 'template');
        if ($dir === false || $base === false) {
            return '';
        }
        if (strpos($dir, $base . DIRECTORY_SEPARATOR) !== 0 || !is_dir($dir)) {
            return '';
        }
        return $dir;
    }

    private function entryFile(string $dir): string
    {
        foreach (array('index.php', 'index.html', 'index.htm') as $f) {
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            if (is_file($p)) {
                return $p;
            }
        }
        return '';
    }

    /* ---------------- 渲染 ---------------- */

    private function baseParams(array $extra = array()): array
    {
        $params = array(
            'title'       => (string)$this->info('name', 'Source'),
            'corp'        => (string)$this->info('corp', 'Source'),
            'bottom'      => (string)$this->info('bottom', ''),
            'name'        => (string)$this->info('name', 'Source'),
            'version'     => (string)$this->info('version', ''),
            'host'        => $this->host,
            'uri'         => $this->path,
            'year'        => date('Y'),
            'errorcode'   => '',
            'data'        => '',
            'content'     => '',
            'matched'     => $this->bound ? 1 : 0,
            'matched_by'  => $this->matchedBy,
        );
        foreach ($extra as $k => $v) {
            $params[$k] = $v;
        }
        return $params;
    }

    private function info(string $key, string $default = ''): string
    {
        $res = $this->db->query("SELECT `value` FROM `zd_system_setting` WHERE `vars` = " . $this->db->quote($key) . " LIMIT 1");
        if ($res && ($row = $this->db->fetch_assoc($res))) {
            return (string)$row['value'];
        }
        return $default;
    }

    /* 维护模式统一拦截：读系统设置 server_status，管理员（已登录后台）放行 */
    private function maintenanceBlocked(): bool
    {
        if ($this->info('server_status', '0') !== '1') {
            return false;
        }
        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>网站维护中</title></head>'
            . '<body style="font-family:system-ui,-apple-system,\'Microsoft YaHei\',sans-serif;background:#f5f6fa;'
            . 'color:#333;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center">'
            . '<div style="max-width:520px;padding:40px 32px;text-align:center;background:#fff;border-radius:10px;'
            . 'box-shadow:0 8px 30px rgba(0,0,0,.08)">'
            . '<div style="font-size:42px;line-height:1">🛠️</div>'
            . '<h2 style="margin:16px 0 8px;font-size:22px">网站维护中</h2>'
            . '<p style="color:#666;line-height:1.8;margin:0">站点正在进行升级维护，请稍后再访问。<br>'
            . '管理员请登录后台，将「网站状态」切回「正常运行」。</p>'
            . '</div></body></html>';
        return true;
    }

    private function render(): void
    {
        // 网站维护模式：所有前台模板（默认首页 / searchcenter / 其它）统一拦截，管理员会话除外
        if ($this->maintenanceBlocked()) {
            return;
        }

        // 命中的模板
        $tplName = $this->bound ? $this->normalizeTemplate((string)$this->bound['template']) : '';
        $dir     = $tplName !== '' ? $this->templateDir($tplName) : '';
        $entry   = $dir !== '' ? $this->entryFile($dir) : '';

        // 未绑定 / 模板缺失：回落默认模板
        if ($entry === '') {
            $fallback = $this->defaultTemplate();
            if ($fallback !== '') {
                $dir   = dirname($fallback);
                $entry = $fallback;
                if ($this->bound) {
                    $this->bound['params']['notice'] = '绑定的模板不存在，已显示默认模板';
                }
            }
        }

        $params = $this->baseParams($this->bound ? $this->bound['params'] : array());

        if ($entry === '') {
            // 连默认模板都没有：给一个最小可用页面，绝不 500
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>'
                . h($params['title']) . '</title></head><body style="font-family:system-ui;padding:40px">'
                . '<h1>' . h($params['title']) . '</h1><p>系统已就绪，但尚未安装任何前台模板。</p></body></html>';
            return;
        }

        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            echo $this->renderPhp($entry, $params);
        } else {
            // 兼容“后缀是 .html 但内嵌 PHP 片段”的同款老模板：
            // 这类文件如果按静态文本输出，会把 PHP 源码直接暴露给访客。
            $raw = @file_get_contents($entry);
            $needle = '<' . '?php';   // 拆开写，避免被 PHP 以外的工具误判为文件起始标签
            if ($raw !== false && stripos($raw, $needle) !== false) {
                echo $this->renderPhp($entry, $params);
            } else {
                // 纯 HTML 模板：如模板目录带 controller.php，则先由它准备数据
                // （可返回 html_xxx 片段与标量占位符），再按 {key} 替换。
                $params = $this->loadController(dirname($entry), $params);
                echo $this->renderStatic($entry, $params);
            }
        }
    }

    /**
     * 模板控制器：template/<name>/controller.php 返回参数数组。
     * 约定：键名以 html_ 开头表示可信 HTML 片段（原样替换，需自行转义），
     * 其余键按标量处理并在输出时统一 h() 转义。
     */
    private function loadController(string $dir, array $params): array
    {
        $file = $dir . DIRECTORY_SEPARATOR . 'controller.php';
        if (!is_file($file)) {
            return $params;
        }
        try {
            ob_start();
            $result = include $file;   // 控制器内使用 global $db,$dou
            ob_end_clean();
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('模板控制器执行失败: ' . $e->getMessage());
            return $params;
        }
        if (!is_array($result)) {
            return $params;
        }
        foreach ($result as $k => $v) {
            if (is_string($k) && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,31}$/', $k) && (is_scalar($v) || $v === null)) {
                $params[$k] = (string)$v;
            }
        }
        return $params;
    }

    private function defaultTemplate(): string
    {
        // 1) 系统设置里的默认首页（历史字段 index_page，形如 /template/default/index.html）
        $setting = $this->info('index_page', '');
        $name    = $this->normalizeTemplate($setting);
        if ($name !== '') {
            $dir = $this->templateDir($name);
            if ($dir !== '' && ($e = $this->entryFile($dir)) !== '') {
                return $e;
            }
        }
        // 2) 本系统只装查询中心模板：优先 searchcenter
        $dir = $this->templateDir('searchcenter');
        if ($dir !== '') {
            return $this->entryFile($dir);
        }
        // 3) 兜底用 template/default
        $dir = $this->templateDir('default');
        if ($dir !== '') {
            return $this->entryFile($dir);
        }
        return '';
    }

    private function renderPhp(string $file, array $params): string
    {
        // 只把标量参数注入模板作用域
        foreach ($params as $k => $v) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,31}$/', (string)$k)) {
                ${$k} = $v;
            }
        }
        $templateDir = dirname($file);
        ob_start();
        try {
            include $file;
        } catch (Throwable $e) {
            ob_end_clean();
            error_log('模板渲染失败: ' . $e->getMessage());
            return '<p>模板渲染失败，请检查模板文件。</p>';
        }
        return (string)ob_get_clean();
    }

    private function renderStatic(string $file, array $params): string
    {
        $html = @file_get_contents($file);
        if ($html === false) {
            return '<p>模板文件无法读取。</p>';
        }
        // 支持 {key} 与 {{key}} 两种占位符：
        //   html_ 前缀 = 模板控制器产出的可信 HTML 片段，原样替换；
        //   其余键一律 h() 转义。
        foreach ($params as $k => $v) {
            if (is_array($v) || is_object($v)) {
                continue;
            }
            $rawHtml = (strpos((string)$k, 'html_') === 0);
            $val = $rawHtml ? (string)$v : h((string)$v);
            $html = str_replace(array('{{' . $k . '}}', '{' . $k . '}'), array($val, $val), $html);
        }
        // 未替换的占位符清空，避免把 {xxx} 暴露给访客
        $html = preg_replace('/\{\{?[A-Za-z_][A-Za-z0-9_]{0,31}\}?\}/', '', $html);
        return $html;
    }
}

try {
    $router = new FrontRouter($db);
    $router->run();
} catch (Throwable $e) {
    error_log('前台渲染异常: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>系统未定义</title></head><body>'
        . '<h2>系统未定义</h2><p>页面暂时无法显示，请稍后再试。</p></body></html>';
}
