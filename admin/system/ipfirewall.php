<?php
/* ----------------------------------------------------
 * 系统 · 安全防御 · IP 防火墙
 * - 是否启用（0/1）、模式（黑名单命中即拦 / 白名单不在名单即拦）
 * - 规则：支持 1.2.3.4、1.2.3.0/24、1.2.3.1-1.2.3.9，掩码长度自动换算起止地址
 * - 拦截记录写入 zd_system_log（method = IP防火墙），本页可查看/清理
 * - 前台由 kernl/Init.php 全局匹配，命中跳转 /ip_blocked.php
 * ---------------------------------------------------- */
if (!defined('source')) {
    header("Location: ../login.php");
    exit;
}

require_once ROOT_PATH . 'kernl/IpFirewall.class.php';

$notice = '';
$noticeType = 'success';

/* ---------- 首次访问自动登记菜单（老站升级后无需重装数据库） ---------- */
$hasMenu = $dou->fetch_array($dou->query(
    "SELECT `m_e` FROM `zd_adminlist` WHERE `t_e`='system' AND `m_e`='ipfirewall' LIMIT 1"
));
if (!$hasMenu) {
    $dou->query("INSERT INTO `zd_adminlist` (`t_e`,`m_e`,`table`,`menu`,`url`,`page`,`count`) VALUES "
        . "('system','ipfirewall','安全防御','IP 防火墙','index.php?/system/ipfirewall','system/ipfirewall.php',5)");
}

/* ---------- 动作处理 ---------- */
$act = isset($_POST['act']) ? (string)$_POST['act'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $act !== '') {
    if (!sm_csrf_ok()) {
        $notice = '安全校验失败，请刷新页面后重试。';
        $noticeType = 'danger';
    } else {
        if ($act === 'save') {
            $enable = (isset($_POST['ipfw_enable']) && $_POST['ipfw_enable'] === '1') ? '1' : '0';
            $mode   = (isset($_POST['ipfw_mode']) && $_POST['ipfw_mode'] === 'white') ? 'white' : 'black';
            ipfw_put_setting($dou, 'ipfw_enable', $enable);
            ipfw_put_setting($dou, 'ipfw_mode', $mode);
            IpFirewall::logAction($db, '修改开关', 'enable=' . $enable . ', mode=' . $mode);
            $notice = '已保存：' . ($enable === '1' ? '已启用' : '已关闭') . '，模式为'
                . ($mode === 'white' ? '白名单' : '黑名单') . '。';
        } elseif ($act === 'add') {
            $input  = isset($_POST['rule_input']) ? trim((string)$_POST['rule_input']) : '';
            $remark = isset($_POST['remark']) ? trim((string)$_POST['remark']) : '';
            list($range, $err) = IpFirewall::parseInput($input);
            if ($err !== '') {
                $notice = $err;
                $noticeType = 'danger';
            } else {
                $dou->query("INSERT INTO `zd_ipfirewall` (`start_ip`,`end_ip`,`remark`,`ControlMode`) VALUES ("
                    . $dou->quote($range[0]) . ',' . $dou->quote($range[1]) . ',' . $dou->quote($remark) . ",'')");
                IpFirewall::logAction($db, '新增规则', $range[0] . ' - ' . $range[1] . ' ' . $remark);
                $notice = '规则已添加：' . $range[0] . ' - ' . $range[1] . '（掩码已自动换算）';
            }
        } elseif ($act === 'del') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id > 0) {
                $dou->query("DELETE FROM `zd_ipfirewall` WHERE `id`=" . $id);
                IpFirewall::logAction($db, '删除规则', 'id=' . $id);
                $notice = '规则已删除。';
            }
        } elseif ($act === 'clearlog') {
            $dou->query("DELETE FROM `zd_system_log` WHERE `method`=" . $dou->quote(IpFirewall::LOG_METHOD)
                . " AND `addr` <> 'admin'");
            $notice = '拦截记录已清空（设置变更日志保留）。';
        }
    }
}

/* ---------- 读取当前配置 ---------- */
$enable = IpFirewall::setting($db, 'ipfw_enable', '0') === '1';
$mode   = IpFirewall::mode($db);
$myIp   = IpFirewall::clientIp();
list($myBlocked, $myReason) = IpFirewall::wouldBlock($db, $myIp);
$rules  = IpFirewall::rules($db);

/* 拦截记录 */
$logs = array();
$res  = $dou->query("SELECT `id`,`ip`,`data`,`addr`,`time` FROM `zd_system_log` WHERE `method`="
    . $dou->quote(IpFirewall::LOG_METHOD) . " AND `addr` <> 'admin' ORDER BY `id` DESC LIMIT 50");
while ($res && ($row = $dou->fetch_array($res))) {
    $logs[] = $row;
}
/* 设置变更记录 */
$changeLogs = array();
$res2 = $dou->query("SELECT `id`,`ip`,`data`,`time` FROM `zd_system_log` WHERE `method`="
    . $dou->quote(IpFirewall::LOG_METHOD) . " AND `addr` = 'admin' ORDER BY `id` DESC LIMIT 30");
while ($res2 && ($row = $dou->fetch_array($res2))) {
    $changeLogs[] = $row;
}
?>

<div class="row">
<div class="col-xs-12">
<div class="box-content">

<?php if ($notice !== ''): ?>
<div class="alert alert-<?php echo h($noticeType); ?>" style="margin-bottom:14px"><?php echo h($notice); ?></div>
<?php endif; ?>

<h4 style="margin:0 0 10px">IP 防火墙</h4>
<p class="help-block" style="color:#888;margin:0 0 16px">
    前台所有模板页面、直连 PHP 入口在进入业务逻辑前统一匹配规则；命中后跳转
    <code>/ip_blocked.php</code>，并在「拦截记录」中留痕。后台（/admin/）与安装程序不参与拦截，避免把自己锁在外面。
</p>

<!-- ============ 开关与模式 ============ -->
<div class="panel panel-default">
    <div class="panel-heading">启停与匹配模式</div>
    <div class="panel-body">
        <form method="post" class="form-horizontal">
            <?php echo sm_csrf_field(); ?>
            <input type="hidden" name="act" value="save">
            <div class="form-group">
                <label class="col-sm-2 control-label">是否启用</label>
                <div class="col-sm-10">
                    <label class="radio-inline">
                        <input type="radio" name="ipfw_enable" value="1" <?php echo $enable ? 'checked' : ''; ?>> 启用
                    </label>
                    <label class="radio-inline">
                        <input type="radio" name="ipfw_enable" value="0" <?php echo !$enable ? 'checked' : ''; ?>> 关闭
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">匹配模式</label>
                <div class="col-sm-10">
                    <label class="radio-inline">
                        <input type="radio" name="ipfw_mode" value="black" <?php echo $mode === 'black' ? 'checked' : ''; ?>> 黑名单（命中规则 → 拦截）
                    </label>
                    <label class="radio-inline">
                        <input type="radio" name="ipfw_mode" value="white" <?php echo $mode === 'white' ? 'checked' : ''; ?>> 白名单（不在规则内 → 拦截）
                    </label>
                    <p class="help-block" style="color:#c0392b">
                        白名单模式等于只放行名单内的 IP，其它所有访客都会被拦；名单为空时前台全部不可访问（后台不受影响）。
                    </p>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">当前访客 IP</label>
                <div class="col-sm-10" style="padding-top:7px">
                    <code><?php echo h($myIp); ?></code>
                    &nbsp;
                    <?php if ($myBlocked): ?>
                        <span class="label label-danger">按当前配置会被拦截：<?php echo h($myReason); ?></span>
                    <?php else: ?>
                        <span class="label label-success">按当前配置可正常访问：<?php echo h($myReason); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button class="btn btn-primary" type="submit">保存设置</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ============ 规则 ============ -->
<div class="panel panel-default">
    <div class="panel-heading">IP 规则（共 <?php echo count($rules); ?> 条）</div>
    <div class="panel-body">
        <form method="post" class="form-inline" style="margin-bottom:16px">
            <?php echo sm_csrf_field(); ?>
            <input type="hidden" name="act" value="add">
            <input type="text" class="form-control" name="rule_input" style="width:320px"
                   placeholder="1.2.3.4 或 1.2.3.0/24 或 1.2.3.1-1.2.3.9" required>
            <input type="text" class="form-control" name="remark" style="width:220px" placeholder="备注（可空）">
            <button class="btn btn-success" type="submit">添加规则</button>
            <p class="help-block" style="margin:8px 0 0;color:#888">
                填 CIDR 时按掩码长度自动换算出起止地址；只填单个 IP 按 /32（IPv6 为 /128）处理。
            </p>
        </form>

        <table class="table table-bordered table-hover">
            <thead>
            <tr>
                <th style="width:60px">ID</th>
                <th>起始 IP</th>
                <th>结束 IP</th>
                <th>备注</th>
                <th style="width:90px">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($rules) === 0): ?>
                <tr><td colspan="5" class="text-center" style="color:#999">暂无规则</td></tr>
            <?php else: ?>
                <?php foreach ($rules as $r): ?>
                <tr>
                    <td><?php echo (int)$r['id']; ?></td>
                    <td><code><?php echo h($r['start_ip']); ?></code></td>
                    <td><code><?php echo h($r['end_ip']); ?></code></td>
                    <td><?php echo h((string)$r['remark']); ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('删除该规则？');" style="margin:0">
                            <?php echo sm_csrf_field(); ?>
                            <input type="hidden" name="act" value="del">
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                            <button class="btn btn-danger btn-xs" type="submit">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ 拦截记录 ============ -->
<div class="panel panel-default">
    <div class="panel-heading">
        最近拦截记录（最多 50 条）
        <form method="post" style="float:right;margin:-4px 0 0" onsubmit="return confirm('清空全部拦截记录？');">
            <?php echo sm_csrf_field(); ?>
            <input type="hidden" name="act" value="clearlog">
            <button class="btn btn-default btn-xs" type="submit">清空拦截记录</button>
        </form>
    </div>
    <div class="panel-body">
        <table class="table table-bordered table-condensed">
            <thead>
            <tr>
                <th style="width:60px">ID</th>
                <th style="width:150px">时间</th>
                <th style="width:150px">来源 IP</th>
                <th>请求地址</th>
                <th>命中说明</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($logs) === 0): ?>
                <tr><td colspan="5" class="text-center" style="color:#999">暂无拦截记录</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <?php $d = json_decode((string)$log['data'], true); ?>
                <tr>
                    <td><?php echo (int)$log['id']; ?></td>
                    <td><?php echo h((string)$log['time']); ?></td>
                    <td><code><?php echo h((string)$log['ip']); ?></code></td>
                    <td style="word-break:break-all"><?php echo h((string)$log['addr']); ?></td>
                    <td>
                        <?php
                        if (is_array($d)) {
                            echo h(($d['mode'] === 'white' ? '白名单' : '黑名单') . ' · ' . (isset($d['rule']) ? $d['rule'] : ''));
                            if (!empty($d['remark'])) {
                                echo ' <span style="color:#888">（' . h($d['remark']) . '）</span>';
                            }
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ 设置变更记录 ============ -->
<div class="panel panel-default">
    <div class="panel-heading">防火墙操作日志（开关变更 / 规则增删，最多 30 条）</div>
    <div class="panel-body">
        <table class="table table-bordered table-condensed">
            <thead>
            <tr>
                <th style="width:60px">ID</th>
                <th style="width:150px">时间</th>
                <th style="width:150px">操作者 IP</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($changeLogs) === 0): ?>
                <tr><td colspan="4" class="text-center" style="color:#999">暂无操作日志</td></tr>
            <?php else: ?>
                <?php foreach ($changeLogs as $log): ?>
                <?php $d = json_decode((string)$log['data'], true); ?>
                <tr>
                    <td><?php echo (int)$log['id']; ?></td>
                    <td><?php echo h((string)$log['time']); ?></td>
                    <td><code><?php echo h((string)$log['ip']); ?></code></td>
                    <td>
                        <?php
                        if (is_array($d)) {
                            echo h((isset($d['action']) ? $d['action'] : '') . ' ' . (isset($d['detail']) ? $d['detail'] : ''));
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>
</div>
