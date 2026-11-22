<?php
/* ---------------------------------------------------- */
/* 查询中心设置（searchcenter 模板后台设置页）
/* 说明：原 SearchCenter 无后台设置页，本页为模板化管理后新增，
/*       配置项统一写入 zd_system_setting（searchcenter_* 前缀），
/*       页面文件由模板安装时释放，卸载时随清单删除。
/* 安全：POST 校验 CSRF；写入用 quote() 转义；回显统一 h() 转义。
/* ---------------------------------------------------- */
if (!defined('source')) {
    header("Location: ../login.php");
    exit;
}

$notice     = '';
$noticeType = 'success';

if (!function_exists('sc_set_setting')) {
    function sc_set_setting($dou, $vars, $value)
    {
        $exists = $dou->fetch_array($dou->query(
            "SELECT `vars` FROM `zd_system_setting` WHERE `vars` = " . $dou->quote($vars) . " LIMIT 1"
        ));
        if (!$exists) {
            return $dou->query("INSERT INTO `zd_system_setting` (`vars`, `value`, `备注`) VALUES ("
                . $dou->quote($vars) . "," . $dou->quote($value) . "," . $dou->quote('查询中心设置') . ")");
        }
        return $dou->query("UPDATE `zd_system_setting` SET `value` = " . $dou->quote($value)
            . " WHERE `vars` = " . $dou->quote($vars));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Tab'])) {
    if (!sm_csrf_ok()) {
        $notice     = '请求已过期（CSRF 校验失败），请刷新页面后重试。';
        $noticeType = 'danger';
    } else {
        $title    = isset($_POST['sc_title']) ? trim((string)$_POST['sc_title']) : '';
        $subtitle = isset($_POST['sc_subtitle']) ? trim((string)$_POST['sc_subtitle']) : '';
        $noticeTxt = isset($_POST['sc_notice']) ? trim((string)$_POST['sc_notice']) : '';
        $bottom   = isset($_POST['sc_bottom']) ? trim((string)$_POST['sc_bottom']) : '';
        $types    = isset($_POST['sc_types']) ? trim((string)$_POST['sc_types']) : '';
        $contact  = isset($_POST['sc_contact']) ? trim((string)$_POST['sc_contact']) : '';
        $update   = isset($_POST['sc_update']) ? trim((string)$_POST['sc_update']) : '';

        if (function_exists('mb_substr')) {
            $title    = mb_substr($title, 0, 120, 'UTF-8');
            $subtitle = mb_substr($subtitle, 0, 120, 'UTF-8');
            $noticeTxt = mb_substr($noticeTxt, 0, 300, 'UTF-8');
            $bottom   = mb_substr($bottom, 0, 200, 'UTF-8');
            $types    = mb_substr($types, 0, 500, 'UTF-8');
            $contact  = mb_substr($contact, 0, 120, 'UTF-8');
            $update   = mb_substr($update, 0, 60, 'UTF-8');
        }

        // 搜索类型格式校验：value|显示名;value|显示名
        $typesOk = true;
        if ($types !== '') {
            foreach (explode(';', $types) as $pair) {
                if (trim($pair) === '') {
                    continue;
                }
                if (!preg_match('/^[a-z0-9_]+\|.+$/i', trim($pair))) {
                    $typesOk = false;
                    break;
                }
            }
        }
        if (!$typesOk) {
            $notice     = '搜索类型格式不正确，应为 “mac|MAC 地址;ipv4|IPv4 归属地”，本项未保存，其它项已保存。';
            $noticeType = 'warning';
        }

        $ok = sc_set_setting($dou, 'searchcenter_title', $title);
        $ok = sc_set_setting($dou, 'searchcenter_subtitle', $subtitle) && $ok;
        $ok = sc_set_setting($dou, 'searchcenter_notice', $noticeTxt) && $ok;
        $ok = sc_set_setting($dou, 'searchcenter_bottom', $bottom) && $ok;
        $ok = sc_set_setting($dou, 'searchcenter_contact', $contact) && $ok;
        $ok = sc_set_setting($dou, 'searchcenter_data_update', $update) && $ok;
        if ($typesOk) {
            $ok = sc_set_setting($dou, 'searchcenter_types', $types) && $ok;
        }

        $dou->OpLog('修改查询中心设置', '标题/副标题/公告/底部/搜索类型/联系方式', $ok ? '成功' : '失败');
        if ($noticeType !== 'warning') {
            $notice     = $ok ? '查询中心设置已保存。' : '保存失败，请检查数据库权限。';
            $noticeType = $ok ? 'success' : 'danger';
        }
        $dou->cookie("set", 'active');
    }
}

/* ---------- 当前值 ---------- */
$curTitle    = (string)$dou->Info('searchcenter_title');
$curSubtitle = (string)$dou->Info('searchcenter_subtitle');
$curNotice   = (string)$dou->Info('searchcenter_notice');
$curBottom   = (string)$dou->Info('searchcenter_bottom');
$curTypes    = (string)$dou->Info('searchcenter_types');
$curContact  = (string)$dou->Info('searchcenter_contact');
$curUpdate   = (string)$dou->Info('searchcenter_data_update');

/* ---------- 数据表状态提示 ---------- */
$tables = array('zd_mac_addr', 'zd_process_list', 'zd_filetypetable', 'zd_softwarelibrary', 'zd_report');
$missing = array();
foreach ($tables as $t) {
    $res = $dou->query("SHOW TABLES LIKE " . $dou->quote($t));
    if (!$res || (int)$dou->num_rows($res) < 1) {
        $missing[] = $t;
    }
}

$scDataTab = (isset($_COOKIE['set']) && $_COOKIE['set'] === 'data');
$data = array(
    "TabName" => array("TabName" => "查询中心设置"),
    array("Name" => "前台显示", "tab" => 'tab-sc-front', "active" => $scDataTab ? '' : 'active'),
    array("Name" => "数据状态", "tab" => 'tab-sc-data', "active" => $scDataTab ? 'active' : ''),
);
$dou->FormCreate($data);
?>
<?php if ($notice !== ''): ?>
<div class="alert alert-<?php echo h($noticeType); ?>"><?php echo h($notice); ?></div>
<?php endif; ?>

<div class="tab-content" id="myTabContent">
    <br />

    <!-- ============ 前台显示 ============ -->
    <div class="tab-panel fade in <?php echo $scDataTab ? '' : 'active'; ?>" id="tab-sc-front">
        <form method="post" class="form-x" action="index.php?/search/searchcenterinfo">
            <?php echo sm_csrf_field(); ?>
            <input type="hidden" value="tab-sc-front" name="Tab">
            <div class="form-group">
                <label for="sc_title">站点标题</label>
                <input type="text" class="form-control" id="sc_title" name="sc_title"
                       placeholder="留空则使用系统设置里的 corp" value="<?php echo h($curTitle); ?>">
            </div>
            <div class="form-group">
                <label for="sc_subtitle">副标题</label>
                <input type="text" class="form-control" id="sc_subtitle" name="sc_subtitle"
                       placeholder="例如：MAC / IP / 进程 / 后缀 一站式查询" value="<?php echo h($curSubtitle); ?>">
            </div>
            <div class="form-group">
                <label for="sc_notice">顶部公告</label>
                <textarea name="sc_notice" cols="50" rows="4" class="form-control" id="sc_notice"
                          placeholder="留空则不显示公告"><?php echo h($curNotice); ?></textarea>
            </div>
            <div class="form-group">
                <label for="sc_types">搜索类型</label>
                <textarea name="sc_types" cols="50" rows="3" class="form-control" id="sc_types"
                          placeholder="留空使用默认：mac/ipv4/ipv6/process/hz"><?php echo h($curTypes); ?></textarea>
                <p class="help-block" style="color:#888;margin:4px 0 0">
                    格式：<code>mac|MAC 地址;ipv4|IPv4 归属地;ipv6|IPv6 归属地;process|进程安全;hz|文件后缀</code>，
                    支持的类型值固定为 mac / ipv4 / ipv6 / process / hz。
                </p>
            </div>
            <div class="form-group">
                <label for="sc_contact">错误反馈邮箱</label>
                <input type="text" class="form-control" id="sc_contact" name="sc_contact"
                       placeholder="例如 admin@example.com" value="<?php echo h($curContact); ?>">
            </div>
            <div class="form-group">
                <label for="sc_bottom">底部版权</label>
                <input type="text" class="form-control" id="sc_bottom" name="sc_bottom"
                       placeholder="留空则使用系统设置里的 bottom" value="<?php echo h($curBottom); ?>">
            </div>
            <div class="form-group">
                <label for="sc_update">数据更新时间</label>
                <input type="text" class="form-control" id="sc_update" name="sc_update"
                       placeholder="例如 2026-11-15" value="<?php echo h($curUpdate); ?>">
            </div>
            <div><button class="btn btn-primary btn-sm waves-effect waves-light" type="submit" style="margin-top:30px;">提交</button></div>
        </form>
    </div>

    <!-- ============ 数据状态 ============ -->
    <div class="tab-panel fade in <?php echo $scDataTab ? 'active' : ''; ?>" id="tab-sc-data">
        <br />
        <?php if (empty($missing)): ?>
        <div class="alert alert-success">查询数据表齐全：<?php echo h(implode('、', $tables)); ?></div>
        <?php else: ?>
        <div class="alert alert-warning">
            以下数据表不存在：<?php echo h(implode('、', $missing)); ?><br>
            请在「系统 → 模板管理」里重新安装 searchcenter 模板（安装时自动导入模板自带数据）。
        </div>
        <?php endif; ?>
        <table class="table table-bordered" style="max-width:720px">
            <tr><th>数据表</th><th>记录数</th></tr>
            <?php foreach ($tables as $t): ?>
            <?php
                $n = 0;
                if (!in_array($t, $missing, true)) {
                    $c = $dou->query("SELECT COUNT(*) AS n FROM `" . $t . "`");
                    $row = $c ? $dou->fetch_array($c) : null;
                    $n = $row ? (int)$row['n'] : 0;
                }
            ?>
            <tr><td><?php echo h($t); ?></td><td><?php echo in_array($t, $missing, true) ? '表不存在' : (int)$n; ?></td></tr>
            <?php endforeach; ?>
        </table>
        <p style="color:#888">IPv4 归属地使用内核 qqwry 库（kernl/ipdata.dat，版本见“关于”页）；IPv6 归属地使用 kernl/cz88_public_v6.czdb，该库授权过期时前台会明确提示，不影响 IPv4 查询。</p>
    </div>
</div>
</div>
