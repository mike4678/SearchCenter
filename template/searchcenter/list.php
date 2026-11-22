<?php
/* ----------------------------------------------------
 * 二次查询 / 列表页（原 template/list.php 原名保留）
 * 由搜索结果页以 iframe 弹出：/template/searchcenter/list.php?type=xxx
 * 关键词来自 Cookie SearchData（旧约定）
 * ---------------------------------------------------- */
require __DIR__ . '/../../kernl/Init.php';
global $db, $dou;

require_once ROOT_PATH . 'kernl/searchcenter/SearchCenter.class.php';
$sc = new SearchCenter($db, $dou);

$kw = isset($_GET['kw']) ? trim((string)$_GET['kw']) : '';
if ($kw === '') {
    $kw = isset($_COOKIE['SearchData']) ? trim((string)$_COOKIE['SearchData']) : '';
}
if ($kw === '') {
    die('搜索关键词为空');
}
$kw = function_exists('mb_substr') ? mb_substr($kw, 0, 200, 'UTF-8') : substr($kw, 0, 200);

$type = (isset($_GET['type']) && is_scalar($_GET['type'])) ? strtolower(trim((string)$_GET['type'])) : '';
$like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $kw) . '%';

$rows = array();
$echoType = $type;
switch ($type) {
    case 'process':
        $rows     = $sc->allPrepare("SELECT * FROM zd_process_list WHERE p_name LIKE ? LIMIT 200", array($like));
        $echoType = 'process';
        break;

    case 'hz':
        $rows     = $sc->allPrepare("SELECT * FROM zd_filetypetable WHERE filetype LIKE ? LIMIT 200", array($like));
        $echoType = 'hz_binary';
        break;

    case 'binary':
    case 'hz_binary':
        // 文件头：库里可能写成 “4d 5a”，先去掉分隔符再比
        $norm = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $kw));
        if ($norm === '') {
            die('文件头内容无效');
        }
        $rows     = $sc->allPrepare(
            "SELECT * FROM zd_filetypetable WHERE `binarydata` IS NOT NULL AND `binarydata` <> ''"
            . " AND REPLACE(REPLACE(REPLACE(UPPER(`binarydata`),' ',''),'0X',''),'0x','') LIKE ? LIMIT 200",
            array('%' . $norm . '%')
        );
        $echoType = 'hz_binary';
        break;

    case 'software':
        $rows     = $sc->allPrepare("SELECT * FROM zd_softwarelibrary WHERE softname LIKE ? LIMIT 200", array($like));
        $echoType = 'softhistory';
        break;

    case 'driver':
        // 模板自带驱动库 zd_device_library（软件名 / 软件分类）；原版 zd_device 结构缺表时降级
        if (@$db->table_exist('zd_device_library')) {
            $rows     = $sc->allPrepare("SELECT * FROM zd_device_library WHERE softname LIKE ? LIMIT 200", array($like));
            $echoType = 'driverquery';
            break;
        }
        $hasDriver = method_exists($db, 'table_exist') && @$db->table_exist('zd_device') && @$db->table_exist('zd_device_name');
        if (!$hasDriver) {
            die('驱动查询库（zd_device_library）未安装，该查询类型暂不可用。');
        }
        $rows     = $sc->allPrepare(
            "SELECT d.`char`, dn.devicename, dn.device_ver
             FROM zd_device d
             INNER JOIN zd_device_name dn
                 ON FIND_IN_SET(d.vid, dn.device_pid) > 0
                 AND FIND_IN_SET(d.comp, dn.device_comp) > 0
                 AND FIND_IN_SET(d.type, dn.device_type) > 0
             WHERE d.pid = ? LIMIT 200",
            array($kw)
        );
        $echoType = 'driverquery';
        break;

    default:
        die('无效的查询类型');
}

/* JSON 模式：给前台“多条结果”列表用，返回 {items:[{label,value}]} */
if (isset($_GET['json']) && (string)$_GET['json'] === '1') {
    $items = array();
    foreach ($rows as $row) {
        if ($echoType === 'process') {
            $label = isset($row['p_name']) ? (string)$row['p_name'] : '';
            $value = $label;
        } elseif ($echoType === 'hz_binary') {
            $label = (isset($row['filetype']) ? (string)$row['filetype'] : '')
                . '(' . (isset($row['title']) ? (string)$row['title'] : '') . ')';
            $value = isset($row['filetype']) ? (string)$row['filetype'] : $label;
        } elseif ($echoType === 'driverquery') {
            $label = (isset($row['softname']) ? (string)$row['softname'] : '');
            $value = $label;
        } else {
            $label = isset($row['softname']) ? (string)$row['softname'] : '';
            $value = $label;
        }
        if ($label === '') {
            continue;
        }
        $items[] = array('label' => $label, 'value' => $value);
    }
    @ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('status' => '1', 'count' => count($items), 'items' => $items), JSON_UNESCAPED_UNICODE);
    exit;
}
?><!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="/css/style.min.css">
    <link rel="stylesheet" href="/fonts/material-design/css/materialdesignicons.css">
    <link rel="stylesheet" href="/plugin/sweet-alert/sweetalert.css">
    <style>
        body{background:#fff;padding:14px;font-size:14px;color:#5b6478}
        .sc-list a{color:#4a8cff;text-decoration:none;display:block;padding:6px 0;border-bottom:1px dashed #eee}
        .sc-list a:hover{color:#2f6fe0}
        .sc-detail{display:none}
        .sc-detail tr td{padding:5px 8px;border-bottom:1px solid #f1f3f7}
        .sc-detail tr td:first-child{width:110px;text-align:right;color:#93a0b5}
    </style>
</head>
<body>
<div class="sc-list">
    <p>根据「<?php echo h($kw); ?>」为您查询到：</p>
    <?php
    $linkId = 0;
    foreach ($rows as $row) {
        switch ($echoType) {
            case 'process':
                $data = isset($row['p_name']) ? (string)$row['p_name'] : '';
                $label = $data;
                $detail = array(
                    'name'  => $data,
                    'path'  => isset($row['path']) ? (string)$row['path'] : '',
                    'compy' => isset($row['compy']) ? (string)$row['compy'] : '',
                    'level' => $sc->processLevel(isset($row['safevalue']) ? $row['safevalue'] : -1),
                    'other' => isset($row['other']) ? (string)$row['other'] : '',
                );
                break;
            case 'hz_binary':
                $data = isset($row['binarydata']) ? (string)$row['binarydata'] : '';
                $label = (isset($row['filetype']) ? (string)$row['filetype'] : '')
                    . '(' . (isset($row['title']) ? (string)$row['title'] : '') . ')';
                /* 列表里的每一条已经是完整记录，直接用本行数据展示。
                   不能再拿 binarydata 去反查，否则同文件头的多个后缀会再次命中多条。 */
                $detail = array(
                    'filetype'   => isset($row['filetype']) ? (string)$row['filetype'] : '',
                    'binarydata' => $data,
                    'open'       => isset($row['Open']) ? (string)$row['Open'] : (isset($row['open']) ? (string)$row['open'] : ''),
                    'title'      => isset($row['title']) ? (string)$row['title'] : '',
                );
                break;
            case 'softhistory':
                $data = isset($row['softname']) ? (string)$row['softname'] : '';
                $label = $data;
                $detail = array(
                    'name' => $data,
                    'type' => isset($row['softtype']) ? (string)$row['softtype'] : '',
                );
                break;
            case 'driverquery':
                $data = isset($row['softname']) ? (string)$row['softname'] : '';
                $label = $data !== '' ? $data : (isset($row['char']) ? (string)$row['char'] : '');
                $detail = array(
                    'name' => $data !== '' ? $data : $label,
                    'type' => isset($row['softtype']) ? (string)$row['softtype'] : '',
                );
                break;
            case 'DriverQuery':
            default:
                $data = $kw;
                $label = (isset($row['char']) ? (string)$row['char'] : '')
                    . '(' . (isset($row['devicename']) ? (string)$row['devicename'] : '')
                    . ' ' . (isset($row['device_ver']) ? (string)$row['device_ver'] : '') . ')';
                $detail = array(
                    'name' => $label,
                    'type' => isset($row['devicename']) ? (string)$row['devicename'] : '',
                );
                break;
        }
        if ($data === '' && $label === '') {
            continue;
        }
        $detailJs = htmlspecialchars(json_encode($detail, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $typeJs = htmlspecialchars(json_encode((string)$echoType, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $dls = array();
        if ($echoType === 'softhistory') {
            $dls = $sc->downloads('software');
        } elseif ($echoType === 'driverquery') {
            $dls = $sc->downloads('driver');
        }
        ?>
        <a href="#" onclick='scShowDetail(<?php echo $detailJs; ?>,<?php echo $typeJs; ?>);return false;'><?php echo h($label); ?></a>
        <?php foreach ($dls as $d): ?>
        <a href="<?php echo h($d['url']); ?>" target="_blank" rel="noopener"
           style="display:inline-block;margin-left:8px;padding:0;border:0;color:#18a058;font-size:13px"><?php echo h($d['label']); ?></a>
        <?php endforeach; ?>
        <?php if (!empty($dls)): ?><br><?php endif; ?>
        <?php
        $linkId++;
    }
    if (empty($rows)) {
        echo '<p style="color:#9aa4b6">没有匹配的记录</p>';
    }
    ?>
</div>

<table class="sc-detail" id="sc-detail-process">
    <tr><td>查询进程名：</td><td id="p_name"></td></tr>
    <tr><td>文件路径：</td><td id="p_path"></td></tr>
    <tr><td>开发商：</td><td id="regcompy"></td></tr>
    <tr><td>安全等级：</td><td id="p_level"></td></tr>
    <tr><td>描述：</td><td id="p_other"></td></tr>
    <tr><td><a href="javascript:scBack();">返回</a></td></tr>
</table>

<table class="sc-detail" id="sc-detail-hz">
    <tr><td>查询后缀名：</td><td id="hz_filetype"></td></tr>
    <tr><td>文件头：</td><td id="hz_binarydata"></td></tr>
    <tr><td>执行方式：</td><td id="hz_open"></td></tr>
    <tr><td>描述：</td><td id="hz_title"></td></tr>
    <tr><td><a href="javascript:scBack();">返回</a></td></tr>
</table>

<table class="sc-detail" id="sc-detail-software">
    <tr><td>软件名：</td><td id="sw_name"></td></tr>
    <tr><td>软件类型：</td><td id="sw_type"></td></tr>
    <tr id="sw_dl_row" style="display:none"><td>下载链接：</td><td id="sw_download"></td></tr>
    <tr><td><a href="javascript:scBack();">返回</a></td></tr>
</table>

<table class="sc-detail" id="sc-detail-driver">
    <tr><td>软件名：</td><td id="drv_name"></td></tr>
    <tr><td>软件分类：</td><td id="drv_type"></td></tr>
    <tr><td><a href="javascript:scBack();">返回</a></td></tr>
</table>

<script src="/js/jquery-1.8.3.min.js"></script>
<script>
function scBack() {
    $('.sc-detail').hide();
    $('.sc-list').show();
}

/* 列表里的每一条已经是完整记录，直接展示本行数据；
   不要再发起 keyword 查询，否则“文件头”会再次命中同文件头的多个后缀。 */
function scShowDetail(d, type) {
    d = d || {};
    if (type === 'process') {
        $('.sc-list').hide();
        $('#sc-detail-process').show();
        $('#p_name').text(d.name || '');
        $('#p_path').text(d.path || '');
        $('#regcompy').text(d.compy || '');
        $('#p_level').text(d.level || '');
        $('#p_other').text(d.other || '');
        return;
    }
    if (type === 'hz_binary' || type === 'hz') {
        $('.sc-list').hide();
        $('#sc-detail-hz').show();
        $('#hz_filetype').text(d.filetype || '');
        $('#hz_binarydata').text(d.binarydata || '');
        $('#hz_open').text(d.open || '');
        $('#hz_title').text(d.title || '');
        return;
    }
    if (type === 'softhistory' || type === 'software') {
        $('.sc-list').hide();
        $('#sc-detail-software').show();
        $('#sw_name').text(d.name || '');
        $('#sw_type').text(d.type || '');
        var box = $('#sw_download').empty();
        var dls = d.downloads || [];
        if (dls.length) {
            $.each(dls, function (i, item) {
                if (!item || !item.url) { return; }
                if (i > 0) { box.append(document.createTextNode('　')); }
                box.append($('<a target="_blank" rel="noopener"></a>')
                    .attr('href', item.url).text(item.label || '下载'));
            });
            $('#sw_dl_row').show();
        } else {
            $('#sw_dl_row').hide();
        }
        return;
    }
    if (type === 'driverquery' || type === 'driver') {
        $('.sc-list').hide();
        $('#sc-detail-driver').show();
        $('#drv_name').text(d.name || '');
        $('#drv_type').text(d.type || '');
        return;
    }
    alert('暂不支持该类型的详情展示');
}
</script>
</body>
</html>
