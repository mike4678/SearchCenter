<?php
/* ----------------------------------------------------
 * 查询中心接口 · 兼容层（原 search_api.php 原名保留）
 * 输出结构与旧版一致：{"status":"1","Data":[...],"count":n}
 * 前端（旧客户端 / 旧页面）可直接沿用，无需改调用代码。
 * ---------------------------------------------------- */
require __DIR__ . '/../../kernl/Init.php';
global $db, $dou;

@ob_clean();
header('Content-Type: application/json; charset=utf-8');

require_once ROOT_PATH . 'kernl/searchcenter/SearchCenter.class.php';
$sc = new SearchCenter($db, $dou);

$type = isset($_REQUEST['type']) ? strtolower(trim((string)$_REQUEST['type'])) : '';
if ($type === '' && isset($_REQUEST['searchfs'])) {
    $type = strtolower(trim((string)$_REQUEST['searchfs']));
}
$keyword = isset($_REQUEST['keyword']) ? $_REQUEST['keyword'] : (isset($_REQUEST['searchdata']) ? $_REQUEST['searchdata'] : '');
$keyword = trim((string)$keyword);
if (function_exists('mb_substr')) {
    $keyword = mb_substr($keyword, 0, 100, 'UTF-8');
}

if ($keyword === '') {
    echo json_encode(array('status' => '0', 'message' => '关键词为空', 'count' => 0, 'Data' => array()), JSON_UNESCAPED_UNICODE);
    exit;
}

/* type 为空或 auto 时智能识别 */
if ($type === '' || $type === 'auto' || $type === 'smart') {
    $type = $sc->detectType($keyword);
}

$data = array();
switch ($type) {
    case 'mac':
        $r = $sc->macLookup($keyword);
        if ($r['ok']) {
            $data[] = array('mac' => $r['mac'], 'oui' => $r['oui'], 'vendor' => $r['vendor']);
        }
        break;

    case 'ip':
    case 'ipv4':
    case 'ipv6':
        $r = $sc->ipLookup($keyword);
        if ($r['ok']) {
            $data[] = array('ip' => $r['ip'], 'region' => $r['region'], 'family' => $r['family']);
        }
        break;

    case 'process':
        $r = $sc->processLookup($keyword);
        $data = $r['rows'];
        break;

    case 'hz':
        $r = $sc->filetypeLookup($keyword, 'filetype');
        $data = $r['rows'];
        break;

    case 'hz_binary':

    case 'binary':
        $r = $sc->filetypeLookup($keyword, 'binarydata');
        $data = $r['rows'];
        break;

    case 'software':
    case 'softhistory':
        $r = $sc->softwareLookup($keyword);
        $data = $r['rows'];
        break;

    case 'driverquery':
    case 'driver':
    case 'device':
        $r = $sc->deviceLookup($keyword);
        $data = $r['rows'];
        break;

    default:
        echo json_encode(array('status' => '0', 'message' => '不支持的查询类型', 'count' => 0, 'Data' => array()), JSON_UNESCAPED_UNICODE);
        exit;
}

echo json_encode(array(
    'status' => '1',
    'message' => 'Success',
    'count' => count($data),
    'Data' => $data,
), JSON_UNESCAPED_UNICODE);

// 关键词日志（兼容层同样记录）
$sc->logKeyword($type, $keyword, count($data) > 0, count($data) > 0 ? '匹配 ' . count($data) . ' 条' : '查询无果');
