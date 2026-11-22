<?php
/* ----------------------------------------------------
 * 关于页（原 template/about.php 原名保留）
 * 统计各库可查询数量 + IP 库版本，全部走系统函数
 * 用于查询中心右上角「关于」弹窗
 * ---------------------------------------------------- */
require __DIR__ . '/../../kernl/Init.php';
global $db, $dou;

require_once ROOT_PATH . 'kernl/searchcenter/SearchCenter.class.php';
$sc = new SearchCenter($db, $dou);
$counts = $sc->counts();

$ipVersion = '';
if (method_exists($dou, 'Get_IPVersion')) {
    $ipVersion = (string)$dou->Get_IPVersion();
}
$corp   = $sc->setting('searchcenter_title', $sc->setting('corp', 'Source'));
$update = $sc->setting('searchcenter_data_update', '');
$version = trim((string)$dou->Info('version'));
if ($version === '') { $version = '1.0.0'; }
?><!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>关于查询中心</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;padding:22px 26px 18px;background:#fff;color:#4b5565;
            font:14px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif}
        .hd{display:flex;align-items:baseline;gap:10px;padding-bottom:12px;border-bottom:1px solid #eef1f6}
        .hd h1{margin:0;font-size:18px;font-weight:600;color:#152238;letter-spacing:.3px}
        .hd span{font-size:12px;color:#8b95a7}
        .hd a{color:#0b57d0;text-decoration:none;border-bottom:1px dashed rgba(11,87,208,.35)}
        .hd a:hover{text-decoration:none;border-bottom-style:solid}
        .rows{margin-top:6px}
        .row{display:flex;gap:14px;padding:9px 0;border-bottom:1px solid #f2f4f8}
        .row:last-child{border-bottom:0}
        .k{flex:none;width:92px;color:#8b95a7;text-align:right}
        .v{flex:1;color:#374151;min-width:0}
        .v small{color:#8b95a7}
        .num{display:flex;flex-wrap:wrap;gap:8px 18px}
        .num i{font-style:normal;color:#8b95a7;margin-right:4px}
        a{color:#0b57d0;text-decoration:none}
        a:hover{text-decoration:underline}
    </style>
</head>
<body>
    <div class="hd">
        <h1>关于查询中心</h1>
        <span>V<?php echo h($version); ?> · 模板化版</span>
    </div>
    <div class="rows">
        <div class="row"><div class="k">开发</div><div class="v"><?php echo h($corp); ?></div></div>
        <div class="row"><div class="k">可查询数</div><div class="v num">
            <span><i>MAC</i><?php echo (int)$counts['mac']; ?></span>
            <span><i>Process</i><?php echo (int)$counts['process']; ?></span>
            <span><i>FileType</i><?php echo (int)$counts['filetype']; ?></span>
            <span><i>Software</i><?php echo (int)$counts['software']; ?></span>
        </div></div>
        <div class="row"><div class="k">IP 库版本</div><div class="v"><?php echo h($ipVersion !== '' ? $ipVersion : '未检测到 IP 库'); ?></div></div>
        <div class="row"><div class="k">数据更新时间</div><div class="v"><?php echo h($update !== '' ? $update : '以库内实际数据为准'); ?></div></div>
        <div class="row"><div class="k">错误提交</div><div class="v"><a href="mailto:<?php echo h($sc->setting('searchcenter_contact', '')); ?>">点击提交</a></div></div>
    </div>
</body>
</html>
