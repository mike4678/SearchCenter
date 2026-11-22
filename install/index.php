<?php
/**
 * SearchCenter 安装向导（独立运行，不依赖内核）
 * 步骤：1 环境检查 → 2 填写配置 → 3 执行安装 → 4 完成
 * 安装成功后会在站点根目录生成 install.lock，再次访问本页将被拦截。
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('SC_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('SC_LOCK', SC_ROOT . 'install.lock');
define('SC_VERSION', '1.0.0');

$SQL_SCHEMA = __DIR__ . '/sql/schema.sql';
$SQL_DATA   = __DIR__ . '/sql/data.sql';

/* 安装令牌：不依赖会话（避免 cookie 丢失导致“令牌失效”），由站点路径 + 固定盐派生 */
$TOKEN = hash_hmac('sha256', 'searchcenter-install|' . SC_ROOT, 'sc-install-token-v1');

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function sc_env_checks()
{
    $phpOk = version_compare(PHP_VERSION, '7.2.0', '>=');
    $items = array();
    $items[] = array('PHP 版本 ≥ 7.2', PHP_VERSION . ($phpOk ? '' : '（过低）'), $phpOk, true);
    foreach (array('mysqli' => '数据库扩展 mysqli', 'mbstring' => '字符串扩展 mbstring', 'json' => 'JSON 扩展', 'curl' => 'CURL 扩展（Bing 壁纸 / 远程取数）') as $ext => $label) {
        $on = extension_loaded($ext);
        $items[] = array($label, $on ? '已开启' : '未开启', $on, true);
    }
    $opt = extension_loaded('openssl');
    $items[] = array('OpenSSL 扩展（可选）', $opt ? '已开启' : '未开启', $opt, false);

    $rootW = is_writable(SC_ROOT);
    $items[] = array('站点根目录可写（写入 install.lock）', SC_ROOT, $rootW, true);

    $kernlW = is_dir(SC_ROOT . 'kernl') && is_writable(SC_ROOT . 'kernl');
    $items[] = array('kernl 目录可写（写入数据库配置）', SC_ROOT . 'kernl', $kernlW, true);

    $logsW = is_dir(SC_ROOT . 'logs') ? is_writable(SC_ROOT . 'logs') : is_writable(SC_ROOT);
    $items[] = array('logs 运行日志目录', is_dir(SC_ROOT . 'logs') ? '已存在' : '将在安装时创建', $logsW, true);

    $cacheW = is_dir(SC_ROOT . 'cache') ? is_writable(SC_ROOT . 'cache') : is_writable(SC_ROOT);
    $items[] = array('cache 缓存目录', is_dir(SC_ROOT . 'cache') ? '已存在' : '将在安装时创建', $cacheW, true);

    $sqlOk = is_file($GLOBALS['SQL_SCHEMA']) && is_file($GLOBALS['SQL_DATA']);
    $items[] = array('安装 SQL 文件', $sqlOk ? '完整' : '缺失 schema.sql / data.sql', $sqlOk, true);

    return $items;
}

/** 读取 SQL 文件并切成单条语句（按 ";\n" 边界，跳过注释行） */
function sc_sql_statements($file)
{
    $raw = file_get_contents($file);
    if ($raw === false) {
        throw new RuntimeException('无法读取 SQL 文件：' . $file);
    }
    $raw = str_replace(array("\r\n", "\r"), "\n", $raw);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $chunks = explode(";\n", $raw);
    $out = array();
    foreach ($chunks as $chunk) {
        $lines = explode("\n", $chunk);
        $keep = array();
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '' || strpos($t, '--') === 0 || strpos($t, '#') === 0) {
                continue;
            }
            if (strpos($t, '/*!') === 0 || strpos($t, '/*') === 0) {
                continue;
            }
            $keep[] = $line;
        }
        $sql = trim(implode("\n", $keep));
        if ($sql !== '') {
            $out[] = rtrim($sql, ';') . ';';
        }
    }
    return $out;
}

/** 与内核 Account::PwdEnc 完全一致的可逆加密 */
function sc_pwd_enc($data, $key)
{
    $data = (string)$data;
    $key  = md5((string)$key);
    $x = 0;
    $len = strlen($data);
    $l = strlen($key);
    $char = '';
    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) {
            $x = 0;
        }
        $char .= $key[$x];
        $x++;
    }
    $str = '';
    for ($i = 0; $i < $len; $i++) {
        $str .= chr((ord($data[$i]) + ord($char[$i])) % 256);
    }
    return base64_encode($str);
}

function sc_write_config(array $c)
{
    $tpl = <<<'PHP'
<?php
/* ---------------------------------------------------- */
/* SearchCenter 数据库配置（安装向导生成于 %DATE%）
/* 如需修改连接参数，改动下面 4 个常量即可。
/* ---------------------------------------------------- */
date_default_timezone_set("Asia/Shanghai"); // 时区设定

define("DBSERVER", getenv('SC_DB_HOST') !== false && getenv('SC_DB_HOST') !== '' ? getenv('SC_DB_HOST') : %HOST%);
define("USER", getenv('SC_DB_USER') !== false && getenv('SC_DB_USER') !== '' ? getenv('SC_DB_USER') : %USER%);
define("PASSWORD", getenv('SC_DB_PASS') !== false && getenv('SC_DB_PASS') !== '' ? getenv('SC_DB_PASS') : %PASS%);
define("DB", getenv('SC_DB_NAME') !== false && getenv('SC_DB_NAME') !== '' ? getenv('SC_DB_NAME') : %DB%);

define("Time", date("Y/m/d H:i:s", time()));
define("Debug", getenv('SC_DEBUG') !== false && getenv('SC_DEBUG') !== '' ? getenv('SC_DEBUG') : "Off");
define("ip_key", "bh3E6Q02n8TOZVxR8sd1Zw==");
define("ip_license", "15944a3c-7889-32d0-a0e8-a3a9c3f28efc");
PHP;
    $rep = array(
        '%DATE%' => date('Y-m-d H:i:s'),
        '%HOST%' => var_export($c['host'], true),
        '%USER%' => var_export($c['user'], true),
        '%PASS%' => var_export($c['pass'], true),
        '%DB%'   => var_export($c['dbname'], true),
    );
    $code = str_replace(array_keys($rep), array_values($rep), $tpl);
    $file = SC_ROOT . 'kernl' . DIRECTORY_SEPARATOR . 'config.Class.php';
    if (file_put_contents($file, $code . "\n") === false) {
        throw new RuntimeException('写入配置失败：' . $file . '（请检查 kernl 目录权限）');
    }
    return $file;
}

$installed = is_file(SC_LOCK);
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = array();
$warnings = array();
$done = null;

/* ---------------- 安装执行 ---------------- */
if (!$installed && $step === 3 && isset($_POST['do_install'])) {
    try {
        if (!isset($_POST['token']) || !hash_equals($TOKEN, (string)$_POST['token'])) {
            throw new RuntimeException('页面令牌已失效，请刷新后重新填写。');
        }
        $host   = trim((string)($_POST['dbhost'] ?? ''));
        $port   = (int)($_POST['dbport'] ?? 3306);
        $user   = trim((string)($_POST['dbuser'] ?? ''));
        $pass   = (string)($_POST['dbpass'] ?? '');
        $dbname = trim((string)($_POST['dbname'] ?? ''));
        $admin  = trim((string)($_POST['adminuser'] ?? 'admin'));
        $apass  = (string)($_POST['adminpass'] ?? '');
        $mkey   = trim((string)($_POST['mkey'] ?? ''));
        $site   = trim((string)($_POST['sitename'] ?? ''));
        $import = isset($_POST['import_data']);
        $rebuild = isset($_POST['rebuild']);

        if ($host === '') { throw new RuntimeException('数据库地址不能为空。'); }
        if ($user === '') { throw new RuntimeException('数据库账号不能为空。'); }
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbname)) { throw new RuntimeException('数据库名只能包含字母、数字与下划线。'); }
        if (!preg_match('/^[A-Za-z0-9_.@-]{2,32}$/', $admin)) { throw new RuntimeException('管理员账号需 2-32 位，仅允许字母、数字、下划线、@ 与点。'); }
        if (strlen($apass) < 4) { throw new RuntimeException('管理员密码至少 4 位。'); }
        if ($mkey === '') { $mkey = substr(bin2hex(random_bytes(8)), 0, 16); }
        $site = $site === '' ? '在线查询中心' : $site;

        $db = @new mysqli($host, $user, $pass, '', $port);
        if ($db->connect_error) {
            throw new RuntimeException('数据库连接失败：' . $db->connect_error);
        }
        $db->set_charset('utf8mb4');
        $db->query("SET SESSION sql_mode=''");

        $qdb = '`' . $dbname . '`';
        if (!$db->query("CREATE DATABASE IF NOT EXISTS $qdb DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
            throw new RuntimeException('创建数据库失败：' . $db->error);
        }
        if (!$db->select_db($dbname)) {
            throw new RuntimeException('选择数据库失败：' . $db->error);
        }

        // 已存在同名表时的保护
        $exists = array();
        $res = $db->query('SHOW TABLES');
        if ($res) {
            while ($row = $res->fetch_array()) { $exists[] = $row[0]; }
            $res->free();
        }
        if ($exists && !$rebuild) {
            throw new RuntimeException('目标数据库已有 ' . count($exists) . ' 张表。若确认要用本系统覆盖，请勾选“重建数据表”。');
        }
        if ($exists && $rebuild) {
            foreach ($exists as $t) {
                if (preg_match('/^zd_[A-Za-z0-9_]+$/', $t)) {
                    $db->query('DROP TABLE IF EXISTS `' . $t . '`');
                }
            }
        }

        $schema = sc_sql_statements($GLOBALS['SQL_SCHEMA']);
        foreach ($schema as $i => $sql) {
            if (!$db->query($sql)) {
                throw new RuntimeException('建表失败（第 ' . ($i + 1) . ' 条）：' . $db->error);
            }
        }

        if ($import) {
            $data = sc_sql_statements($GLOBALS['SQL_DATA']);
            foreach ($data as $i => $sql) {
                if (!$db->query($sql)) {
                    throw new RuntimeException('导入初始数据失败（第 ' . ($i + 1) . ' 条）：' . $db->error);
                }
            }
        }

        // 管理员账号（覆盖安装时以本次填写为准）
        $db->query('DELETE FROM `zd_admin_user`');
        $pwd = sc_pwd_enc($apass, $mkey);
        $stmt = $db->prepare('INSERT INTO `zd_admin_user` (`username`,`password`,`lastip`,`lasttime`) VALUES (?,?,?,?)');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $now = date('Y-m-d H:i:s');
        $stmt->bind_param('ssss', $admin, $pwd, $ip, $now);
        if (!$stmt->execute()) {
            throw new RuntimeException('写入管理员账号失败：' . $stmt->error);
        }
        $stmt->close();

        // 系统设置：加密密钥 + 站点名称（仅更新已存在的配置项）
        $setKey = $db->prepare('UPDATE `zd_system_setting` SET `value`=? WHERE `vars`=?');
        $updates = array(
            'encrypted'             => $mkey,
            'name'                  => $site,
            'corp'                  => $site,
            'searchcenter_title'    => $site,
            'searchcenter_subtitle' => 'MAC 地址 / IP 归属地 / 进程安全 / 文件后缀 一站式查询',
            'searchcenter_bottom'   => 'Copyright © ' . $site,
            'system_table'          => '系统|查询中心',
            'index_page'            => '/template/searchcenter/index.html',
        );
        foreach ($updates as $k => $v) {
            $setKey->bind_param('ss', $v, $k);
            $setKey->execute();
        }
        $setKey->close();

        // 目录准备
        foreach (array('logs', 'cache') as $dir) {
            $p = SC_ROOT . $dir;
            if (!is_dir($p)) {
                @mkdir($p, 0755, true);
            }
        }

        $cfg = sc_write_config(array('host' => $host, 'user' => $user, 'pass' => $pass, 'dbname' => $dbname));

        file_put_contents(SC_LOCK, "SearchCenter installed at " . date('Y-m-d H:i:s') . "\nadmin=" . $admin . "\ndb=" . $dbname . "\n");

        $done = array(
            'admin' => $admin,
            'site'  => $site,
            'db'    => $dbname,
            'host'  => $host,
            'config' => $cfg,
            'tables' => 17,
        );
        $step = 4;
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$checks = sc_env_checks();
$mustOk = true;
foreach ($checks as $c) {
    if ($c[3] && !$c[2]) {
        $mustOk = false;
    }
}
$posted = $_POST;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SearchCenter 安装向导</title>
<style>
    *{box-sizing:border-box}
    body{margin:0;padding:40px 16px;font-family:system-ui,-apple-system,"Segoe UI","Microsoft YaHei",sans-serif;
         background:linear-gradient(160deg,#f4f8fb 0%,#e9f1f5 55%,#eef4f7 100%);color:#233}
    .wrap{max-width:880px;margin:0 auto}
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:22px}
    .brand .dot{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#0f7ba8,#12a4a4);
         color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:17px}
    .brand h1{margin:0;font-size:19px;letter-spacing:.5px}
    .brand span{font-size:12px;color:#7b8994;display:block;margin-top:2px}
    .card{background:#fff;border-radius:16px;padding:30px 32px;box-shadow:0 10px 34px rgba(16,60,80,.09)}
    .steps{display:flex;gap:8px;margin-bottom:26px}
    .steps div{flex:1;text-align:center;font-size:12px;padding:9px 4px;border-radius:9px;
         background:#f2f6f8;color:#8a99a3;border:1px solid #e6edf1}
    .steps div.on{background:#0f7ba8;color:#fff;border-color:#0f7ba8;font-weight:600}
    .steps div.ok{background:#e8f6f1;color:#0f8a62;border-color:#ccebe0}
    h2{font-size:17px;margin:0 0 8px}
    p.tip{margin:0 0 18px;color:#657882;font-size:13px;line-height:1.8}
    table.ck{width:100%;border-collapse:collapse;font-size:13px}
    table.ck td{padding:10px 8px;border-bottom:1px solid #eef2f5;vertical-align:middle}
    table.ck td.n{width:58%}
    .pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600}
    .pill.ok{background:#e8f6f1;color:#0f8a62}
    .pill.no{background:#fdecec;color:#c0392b}
    .pill.warn{background:#fdf6e3;color:#a5761a}
    .field{margin:0 0 16px}
    .field label{display:block;font-size:13px;color:#41535e;margin-bottom:6px;font-weight:600}
    .field input[type=text],.field input[type=password],.field input[type=number]{
        width:100%;padding:11px 13px;border:1px solid #d7e2e9;border-radius:9px;font-size:14px;background:#fbfdfe;
        transition:.15s}
    .field input:focus{outline:none;border-color:#0f7ba8;background:#fff;box-shadow:0 0 0 3px rgba(15,123,168,.12)}
    .row{display:flex;gap:14px;flex-wrap:wrap}
    .row .field{flex:1;min-width:210px}
    .hint{font-size:12px;color:#8a99a3;margin-top:5px}
    .check{display:flex;align-items:flex-start;gap:9px;font-size:13px;color:#41535e;margin:10px 0}
    .check input{margin-top:2px}
    .btn{display:inline-block;padding:12px 26px;border-radius:10px;border:none;cursor:pointer;font-size:14px;font-weight:600;
         background:linear-gradient(135deg,#0f7ba8,#12a4a4);color:#fff;text-decoration:none}
    .btn.ghost{background:#f2f6f8;color:#41535e;border:1px solid #dde8ee}
    .btn:disabled{opacity:.55;cursor:not-allowed}
    .alert{border-radius:10px;padding:13px 15px;font-size:13px;line-height:1.75;margin:0 0 18px}
    .alert.err{background:#fdecec;color:#a02c2c;border:1px solid #f6cfcf}
    .alert.ok{background:#e8f6f1;color:#0f7a58;border:1px solid #c9eade}
    .alert.info{background:#eef6fb;color:#14607f;border:1px solid #d4e7f2}
    .actions{display:flex;gap:12px;align-items:center;margin-top:22px;flex-wrap:wrap}
    code{background:#f2f6f8;padding:2px 7px;border-radius:6px;font-size:12px;color:#14607f}
    .foot{margin-top:16px;text-align:center;font-size:12px;color:#93a4ae}
    ul.done{margin:8px 0 0 18px;font-size:13px;line-height:2;color:#41535e}
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <div class="dot">SC</div>
        <div>
            <h1>SearchCenter 安装向导</h1>
            <span>在线查询中心 · 版本 <?php echo SC_VERSION; ?></span>
        </div>
    </div>

    <div class="card">
        <?php if ($installed && $step !== 4): ?>
            <div class="alert ok">本机已完成安装（检测到 <code>install.lock</code>），安装入口已锁定。</div>
            <ul class="done">
                <li>前台首页：<code>/</code></li>
                <li>后台入口：<code>/admin</code></li>
                <li>如需重新安装，请先删除站点根目录下的 <code>install.lock</code>，再访问本页。</li>
                <li>出于安全考虑，建议安装完成后直接删除整个 <code>install</code> 目录。</li>
            </ul>
            <div class="actions">
                <a class="btn" href="../admin/" target="_blank">进入后台</a>
                <a class="btn ghost" href="../" target="_blank">打开前台</a>
            </div>
        <?php else: ?>
            <div class="steps">
                <div class="<?php echo $step === 1 ? 'on' : ($step > 1 ? 'ok' : ''); ?>">1 环境检查</div>
                <div class="<?php echo $step === 2 ? 'on' : ($step > 2 ? 'ok' : ''); ?>">2 安装配置</div>
                <div class="<?php echo $step === 3 ? 'on' : ($step > 3 ? 'ok' : ''); ?>">3 执行安装</div>
                <div class="<?php echo $step === 4 ? 'on' : ''; ?>">4 完成</div>
            </div>

            <?php foreach ($errors as $e): ?>
                <div class="alert err"><?php echo h($e); ?></div>
            <?php endforeach; ?>

            <?php if ($step === 1): ?>
                <h2>运行环境检查</h2>
                <p class="tip">以下为系统运行所需条件，全部显示“正常”后即可继续。</p>
                <table class="ck">
                    <?php foreach ($checks as $c): ?>
                        <tr>
                            <td class="n"><?php echo h($c[0]); ?></td>
                            <td><?php echo h($c[1]); ?></td>
                            <td style="text-align:right">
                                <?php if ($c[2]): ?>
                                    <span class="pill ok">正常</span>
                                <?php else: ?>
                                    <span class="pill <?php echo $c[3] ? 'no' : 'warn'; ?>"><?php echo $c[3] ? '不满足' : '建议开启'; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <div class="actions">
                    <a class="btn" href="?step=2">下一步</a>
                    <span class="hint"><?php echo $mustOk ? '环境满足安装要求' : '存在必须项未满足，可先联系运维处理'; ?></span>
                </div>

            <?php elseif ($step === 2): ?>
                <h2>数据库与管理员</h2>
                <p class="tip">填写 MySQL 连接信息。数据库不存在时会自动创建（utf8mb4），已有同名表默认不覆盖。</p>
                <form method="post" action="?step=3" autocomplete="off">
                    <input type="hidden" name="token" value="<?php echo h($TOKEN); ?>">
                    <div class="row">
                        <div class="field">
                            <label>数据库地址</label>
                            <input type="text" name="dbhost" value="<?php echo h($posted['dbhost'] ?? 'localhost'); ?>" required>
                        </div>
                        <div class="field" style="max-width:150px">
                            <label>端口</label>
                            <input type="number" name="dbport" value="<?php echo h($posted['dbport'] ?? '3306'); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="field">
                            <label>数据库账号</label>
                            <input type="text" name="dbuser" value="<?php echo h($posted['dbuser'] ?? 'root'); ?>" required>
                        </div>
                        <div class="field">
                            <label>数据库密码</label>
                            <input type="password" name="dbpass" value="<?php echo h($posted['dbpass'] ?? ''); ?>">
                        </div>
                        <div class="field">
                            <label>数据库名</label>
                            <input type="text" name="dbname" value="<?php echo h($posted['dbname'] ?? 'searchcenter'); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="field">
                            <label>站点名称</label>
                            <input type="text" name="sitename" value="<?php echo h($posted['sitename'] ?? '在线查询中心'); ?>">
                            <div class="hint">用于前台标题、后台标题与页脚版权</div>
                        </div>
                        <div class="field">
                            <label>加密密钥（用于后台密码）</label>
                            <input type="text" name="mkey" value="<?php echo h($posted['mkey'] ?? ''); ?>" placeholder="留空自动生成 16 位随机值">
                            <div class="hint">保存在 <code>zd_system_setting.encrypted</code>，请勿随意更改</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="field">
                            <label>管理员账号</label>
                            <input type="text" name="adminuser" value="<?php echo h($posted['adminuser'] ?? 'admin'); ?>" required>
                        </div>
                        <div class="field">
                            <label>管理员密码</label>
                            <input type="password" name="adminpass" value="<?php echo h($posted['adminpass'] ?? ''); ?>" placeholder="至少 4 位" required>
                        </div>
                    </div>
                    <div class="check">
                        <input type="checkbox" id="imp" name="import_data" value="1" <?php echo (isset($posted['import_data']) || !$posted) ? 'checked' : ''; ?>>
                        <label for="imp" style="font-weight:400;margin:0;cursor:pointer">导入基础数据（菜单、系统设置、MAC 地址库、软件库、驱动库、进程/后缀库，约 2 MB）</label>
                    </div>
                    <div class="check">
                        <input type="checkbox" id="reb" name="rebuild" value="1" <?php echo isset($posted['rebuild']) ? 'checked' : ''; ?>>
                        <label for="reb" style="font-weight:400;margin:0;cursor:pointer">重建数据表（<span style="color:#c0392b">会删除数据库中已有的 zd_ 开头表</span>）</label>
                    </div>
                    <div class="actions">
                        <button class="btn" type="submit" name="do_install" value="1">开始安装</button>
                        <a class="btn ghost" href="?step=1">返回检查</a>
                    </div>
                </form>

            <?php elseif ($step === 4 && $done): ?>
                <div class="alert ok">安装成功，系统已可用。已在站点根目录生成 <code>install.lock</code>。</div>
                <ul class="done">
                    <li>数据库：<code><?php echo h($done['host']); ?> / <?php echo h($done['db']); ?></code>（17 张表）</li>
                    <li>管理员账号：<code><?php echo h($done['admin']); ?></code>（密码为刚才填写的内容）</li>
                    <li>配置文件：<code>kernl/config.Class.php</code></li>
                </ul>
                <div class="alert info" style="margin-top:18px">
                    安全提示：安装已锁定，但仍建议删除整个 <code>install</code> 目录；同时请确认站点根目录的 <code>install.sql</code>（如果有）不被公网直接下载。
                </div>
                <div class="actions">
                    <a class="btn" href="../admin/" target="_blank">进入后台登录</a>
                    <a class="btn ghost" href="../" target="_blank">打开前台</a>
                </div>
            <?php else: ?>
                <div class="alert err">页面状态异常，请从第 1 步重新开始。</div>
                <a class="btn" href="?step=1">重新开始</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="foot">SearchCenter · 安装向导 <?php echo SC_VERSION; ?></div>
</div>
</body>
</html>
