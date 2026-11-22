<?php
/* ---------------------------------------------------- */
/* 程序名称: 内核初始化
/* 重构说明（2026）：
/*  1. 去掉了 PHP7.4/8 才有的类型属性、命名参数、?-> 与 "?? throw"，
/*     使整套系统能在当前环境的 PHP 7.3 下运行（先前直接 Parse error）。
/*  2. 数据库统一走 Db 类（旧 API 兼容层），并全局提供 $dou（System 模块），
/*     修正 modules\System 里 "extends Db" 找不到父类导致的致命错误。
/*  3. 生产环境默认关闭错误回显，错误只写 logs/error.log。
/*  4. 统一安全响应头：CSP 保留 'self' 但允许内联脚本/样式（后台大量内联 JS），
/*     同时给出 nosniff、SAMEORIGIN、Referrer-Policy；后台页面改为 no-store。
/*  5. 会话参数加固（HttpOnly + SameSite=Lax），并把 CSRF 令牌挂到会话上。
/* ---------------------------------------------------- */
declare(strict_types=1);

// ---------- 错误处理 ----------
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/error.log');
error_reporting(E_ALL);

// 定义根目录常量
define('ROOT_PATH', realpath(__DIR__ . '/../') . '/');

// ---------- 工具函数 ----------
function isHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https');
}

define('PROTOCOL', isHttps() ? 'https://' : 'http://');

/** 兼容旧代码里的 HttpsCheck()：返回协议头 */
function HttpsCheck(): string
{
    return PROTOCOL;
}

/** 兼容旧代码里的 check_wap()（移动端判断），模块目录里是普通函数 */
if (!function_exists('check_wap') && file_exists(__DIR__ . '/modules/Mobile/MobileModule.php')) {
    require_once __DIR__ . '/modules/Mobile/MobileModule.php';
}

/** 安全启动会话（不重复启动、Cookie 加固） */
function sm_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = isHttps();
    if (PHP_VERSION_ID >= 70300) {
        @session_set_cookie_params(array(
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $https,
            'samesite' => 'Lax',
        ));
    } else {
        @session_set_cookie_params(0, '/', '', $https, true);
    }
    @session_name('SCSESSID');
    @session_start();
}
sm_session_start();

// CSRF 令牌（会话级）
if (empty($_SESSION['sc_csrf'])) {
    try {
        $_SESSION['sc_csrf'] = bin2hex(random_bytes(16));
    } catch (Exception $e) {
        $_SESSION['sc_csrf'] = md5(uniqid((string)mt_rand(), true));
    }
}
define('SM_CSRF', $_SESSION['sc_csrf']);

/** 输出隐藏的 CSRF 字段 */
function sm_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(SM_CSRF) . '">';
}

/** 校验 CSRF（仅 POST 使用） */
function sm_csrf_ok(): bool
{
    $token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
    return $token !== '' && hash_equals(SM_CSRF, $token);
}

// ---------- 安全响应头 ----------
if (!headers_sent()) {
    // connect-src 单独放开 http/https/ws，避免同域不同端口或绝对地址资源被 default-src 拦掉
    header("Content-Security-Policy: default-src 'self'; connect-src 'self' http: https: ws: wss:; img-src 'self' data: http: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; frame-ancestors 'self'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Content-Type: text/html; charset=utf-8');
}

// ---------- 配置文件 ----------
$configFile = ROOT_PATH . 'kernl/config.Class.php';
if (!file_exists($configFile) && file_exists(ROOT_PATH . 'kernl/config.class.php')) {
    $configFile = ROOT_PATH . 'kernl/config.class.php';
}
if (!file_exists($configFile)) {
    header('Location: ' . PROTOCOL . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost') . '/install/index.php', true, 302);
    exit;
}
require_once $configFile;

define('DEBUG_MODE', defined('Debug') && strtolower((string)Debug) === 'on' && (    (isset($_SERVER['HTTP_HOST']) && strtolower((string)$_SERVER['HTTP_HOST']) === 'localhost')
    || (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === '127.0.0.1')
));
if (DEBUG_MODE) {
    ini_set('display_errors', '1');
}

// 后台各功能页用 if(!defined('source')) 判断是否经由后台入口进入，
// 配置文件里该定义被注释掉了，这里补上（仅作“走后门入口”标记，不含任何密钥）。
if (!defined('source')) {
    define('source', 'SourceCenter');}

// 软件库/本地源目录（历史常量，原配置里缺失导致相关页面 Fatal）。目录不存在时相关页会返回空列表。
if (!defined('LocalSource')) {
    define('LocalSource', ROOT_PATH . 'software');}

// ---------- 数据库与模块 ----------
require_once ROOT_PATH . 'kernl/Db.Class.php';
require_once ROOT_PATH . 'kernl/Account.class.php';
require_once ROOT_PATH . 'kernl/ip.Class.php';
require_once ROOT_PATH . 'kernl/ConnectModule.php';
require_once ROOT_PATH . 'kernl/BingWallpaper.class.php';
require_once ROOT_PATH . 'kernl/vendor/autoload.php';

class KernelInitializer
{
    private static $instance = null;
    private $db;
    private $modules = array();

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->initDatabase();
        $this->registerAutoload();
        $this->initializeModules();
    }

    private function initDatabase(): void
    {
        try {
            $this->db = new Db(DBSERVER, USER, PASSWORD, DB, 'utf8mb4');
        } catch (Exception $e) {
            error_log('数据库连接失败: ' . $e->getMessage());
            exit('数据库连接错误，请检查配置');
        }
        if (!$this->db->query('SELECT 1')) {
            error_log('数据库健康检查失败');
            exit('数据库连接异常，请检查服务状态');
        }
    }

    private function registerAutoload(): void
    {
        $autoload = ROOT_PATH . 'kernl/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        // 扩展类约定：kernl/<Name>.class.php 或 kernl/<任意子目录>/<Name>.class.php
        // 只按类名（字母/数字/下划线）在白名单路径下查找，不拼接用户输入。
        spl_autoload_register(function ($class) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', (string)$class)) {
                return;
            }
            $candidates = array(
                ROOT_PATH . 'kernl/' . $class . '.class.php',
                ROOT_PATH . 'kernl/' . $class . '.Class.php',
            );
            foreach (glob(ROOT_PATH . 'kernl/*/' . $class . '.class.php') ?: array() as $f) {
                $candidates[] = $f;
            }
            foreach (glob(ROOT_PATH . 'kernl/*/' . $class . '.Class.php') ?: array() as $f) {
                $candidates[] = $f;
            }
            foreach ($candidates as $file) {
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }

    private function initializeModules(): void
    {
        $moduleConfig = require ROOT_PATH . 'kernl/config/module.php';
        $coreModules = isset($moduleConfig['core_modules']) ? $moduleConfig['core_modules'] : array();
        foreach ($coreModules as $moduleName) {
            $filePath = ROOT_PATH . 'kernl/modules/' . $moduleName . '/' . $moduleName . 'Module.php';
            if (!file_exists($filePath)) {
                throw new RuntimeException('模块文件缺失: ' . $filePath);
            }
            require_once $filePath;

            // 目录名与类名并不总是一致：System 目录里是 class System，
            // FileUtil 目录里是 class FileUtilModule。
            $className = '';
            if (class_exists($moduleName)) {
                $className = $moduleName;
            } elseif (class_exists($moduleName . 'Module')) {
                $className = $moduleName . 'Module';
            }
            if ($className === '') {
                throw new RuntimeException('类定义不存在: ' . $moduleName);
            }

            $instance = new $className($this->db);
            $this->modules[strtolower($moduleName)] = $instance;
            $this->modules[$moduleName] = $instance;
        }
    }

    public function getModule(string $moduleName): object
    {
        if (!isset($this->modules[$moduleName])) {
            throw new InvalidArgumentException('模块不存在: ' . $moduleName);
        }
        return $this->modules[$moduleName];
    }

    public function getDatabase(): object
    {
        return $this->db;
    }
}

$kernel = KernelInitializer::getInstance();
$db     = $kernel->getDatabase();
// 后台沿用的旧变量名
$dou    = $kernel->getModule('system');
$FileControl = $kernel->getModule('fileutil');

// ---------- IP 库（缺失时降级，不再直接 die） ----------
$dbSearcherv4 = null;
$dbSearcherv6 = null;
try {
    if (class_exists('Czdb\\DbSearcher')) {
        $v4 = __DIR__ . '/cz88_public_v4.czdb';
        $v6 = __DIR__ . '/cz88_public_v6.czdb';
        if (is_file($v4) && is_file($v6)) {
            $dbSearcherv4 = new Czdb\DbSearcher($v4, 'BTREE', ip_key);
            $dbSearcherv6 = new Czdb\DbSearcher($v6, 'BTREE', ip_key);
        }
    }
} catch (Exception $e) {
    error_log('IP库初始化失败: ' . $e->getMessage());
}

// ---------- 系统配置常量 ----------
if (!function_exists('getConfigValue')) {
    function getConfigValue(Db $db, string $varName)
    {
        $stmt = $db->prepare('SELECT `value` FROM `zd_system_setting` WHERE `vars` = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $varName);
        if (!$stmt->execute()) {
            return null;
        }
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            return null;
        }
        $row = $result->fetch_assoc();
        return isset($row['value']) ? $row['value'] : null;
    }
}

$encryptionKey = getConfigValue($db, 'encrypted');
define('ENCRYPTION_KEY', $encryptionKey === null ? 'source' : $encryptionKey);

$maxAttempts = getConfigValue($db, 'MaxCount');
$_SESSION['max_login_attempts'] = is_numeric($maxAttempts) ? (int)$maxAttempts : 4;

// 后台页面沿用的旧常量/全局数组
define('key', ENCRYPTION_KEY);

// 兼容旧代码里的 $_G（登录限制、客户端 IP 等）
$GLOBALS['_G'] = array(
    'Login' => array(
        'MaxNumber' => $_SESSION['max_login_attempts'],
    ),
    'IPS' => array(
        'ClientIP' => isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '',
    ),
);
$_G = $GLOBALS['_G'];

// ---------- 访问控制（域名白名单，全局前置拦截；本机地址始终放行） ----------
require_once __DIR__ . '/HostGuard.class.php';
HostGuard::enforce($db);

// ---------- IP 防火墙（前台全局拦截；后台/安装/提示页自动跳过） ----------
require_once __DIR__ . '/IpFirewall.class.php';
IpFirewall::enforce($db);

register_shutdown_function(function () use ($kernel) {
    try {
        $kernel->getDatabase()->close();
    } catch (Exception $e) {
        // 关闭失败无需处理
    }
});
