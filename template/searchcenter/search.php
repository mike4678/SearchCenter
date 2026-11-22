<?php
/* ----------------------------------------------------
 * 查询中心统一查询接口（原 search.php 原名保留）
 * 依赖系统统一内核与扩展类，模板不自建连接。
 * 请求：POST type + keyword（兼容旧参数 searchdata/searchfs）
 * 返回：JSON {status, message, ...}
 * ---------------------------------------------------- */
require __DIR__ . '/../../kernl/Init.php';
global $db, $dou;

@ob_clean();
header('Content-Type: application/json; charset=utf-8');

require_once ROOT_PATH . 'kernl/searchcenter/SearchCenter.class.php';
$sc = new SearchCenter($db, $dou);

function sc_out(array $data, $code = 200)
{
    // 记录关键词日志（每个请求只写一次）
    if (empty($GLOBALS['sc_logged']) && isset($GLOBALS['sc']) && $GLOBALS['sc'] instanceof SearchCenter
        && !empty($GLOBALS['scLogCtx'])) {
        $GLOBALS['sc_logged'] = true;
        $GLOBALS['sc']->logKeyword(
            $GLOBALS['scLogCtx']['type'],
            $GLOBALS['scLogCtx']['keyword'],
            !empty($data['status']),
            isset($data['message']) ? $data['message'] : ''
        );
    }
    // 把实际使用的查询类型一并返回，前端据此选择面板
    if (!empty($GLOBALS['scType'])) {
        $data['type'] = $GLOBALS['scType'];
    }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sc_clean($value, $len = 100)
{
    $value = trim((string)$value);
    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $len, 'UTF-8');
    } else {
        $value = substr($value, 0, $len);
    }
    return $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sc_out(array('status' => false, 'message' => '只接受 POST 请求'), 405);
}

$GLOBALS['sc'] = $sc;
$type = isset($_POST['type']) ? strtolower(trim((string)$_POST['type'])) : '';
if ($type === '' && isset($_POST['searchfs'])) {
    $type = strtolower(trim((string)$_POST['searchfs']));   // 旧参数兼容
}
$keyword = isset($_POST['keyword']) ? trim((string)$_POST['keyword']) : '';
if ($keyword === '' && isset($_POST['searchdata'])) {
    $keyword = trim((string)$_POST['searchdata']);       // 旧参数兼容
}
$keyword = sc_clean($keyword);

if ($keyword === '') {
    sc_out(array('status' => false, 'message' => '请输入查询内容'));
}

/* 前端不再让用户选类型：type 为空或 auto 时由后端按内容自动识别 */
if ($type === '' || $type === 'auto' || $type === 'smart') {
    $cands = $sc->detectCandidates($keyword);
    if (count($cands) > 1) {
        // 同一个词在多个库都有记录（如 “360” 同时命中进程与软件历史）→ 分组返回
        $groups = $sc->groupLookup($keyword, $cands, 20);
        if (count($groups) > 1) {
            $total = 0;
            foreach ($groups as $g) {
                $total += (int)$g['count'];
            }
            $GLOBALS['scType'] = 'multi';
            $GLOBALS['scLogCtx'] = array('type' => 'multi', 'keyword' => $keyword);
            sc_out(array(
                'status'  => true,
                'message' => '在 ' . count($groups) . ' 个库中都有匹配',
                'type'    => 'multi',
                'groups'  => $groups,
                'count'   => $total,
            ));
        }
    }
    $type = !empty($cands) ? $cands[0] : 'process';
}
$GLOBALS['scType'] = $type;
$GLOBALS['scLogCtx'] = array('type' => $type, 'keyword' => $keyword);   // 关键词管理用

switch ($type) {
    case 'mac':
        $r = $sc->macLookup($keyword);
        if (!$r['ok']) {
            sc_out(array('status' => false, 'message' => $r['message']));
        }
        sc_out(array(
            'status' => true,
            'message' => 'Success',
            'mac' => $r['mac'],
            'oui' => $r['oui'],
            'vendor' => $r['vendor'],
        ));
        break;

    case 'ip':
    case 'ipv4':
    case 'ipv6':
    case 'ip_data':
        $r = $sc->ipLookup($keyword);
        if (!$r['ok']) {
            sc_out(array('status' => false, 'message' => $r['message']));
        }
        sc_out(array(
            'status' => true,
            'message' => 'Success',
            'ip' => $r['ip'],
            'family' => $r['family'],
            'region' => $r['region'],
            'localIP' => $r['localIP'],
        ));
        break;

    case 'process':
    case 'process_data':
        $r = $sc->processLookup($keyword);
        if ($r['count'] < 1) {
            sc_out(array('status' => false, 'message' => '查询无果'));
        }
        if ($r['count'] > 1) {
            sc_out(array(
                'status' => 5,
                'message' => '匹配到 ' . $r['count'] . ' 条，点击列表查看',
                'count' => $r['count'],
                'items' => array_map(function ($row) {
                    return array(
                        'p_name' => isset($row['p_name']) ? $row['p_name'] : '',
                        'p_level' => isset($row['safevalue']) ? (int)$row['safevalue'] : null,
                    );
                }, $r['rows']),
            ));
        }
        $row = $r['rows'][0];
        sc_out(array(
            'status' => 2,
            'message' => 'Success',
            'p_name' => isset($row['p_name']) ? (string)$row['p_name'] : '',
            'p_path' => isset($row['path']) ? (string)$row['path'] : '',
            'regcompy' => isset($row['compy']) ? (string)$row['compy'] : '',
            'p_level' => $sc->processLevel(isset($row['safevalue']) ? $row['safevalue'] : -1),
            'p_other' => isset($row['other']) ? (string)$row['other'] : '',
        ));
        break;

    case 'hz_binary':
    case 'binary':
        // 文件头（十六进制）查询
        $r = $sc->filetypeLookup($keyword, 'binarydata');
        if ($r['count'] < 1) {
            sc_out(array('status' => false, 'message' => '查询无果'));
        }
        if ($r['count'] > 1) {
            sc_out(array(
                'status' => 5,
                'message' => '匹配到 ' . $r['count'] . ' 条，点击列表查看',
                'count' => $r['count'],
            ));
        }
        $row = $r['rows'][0];
        sc_out(array(
            'status' => 4,
            'message' => 'Success',
            'filetype' => isset($row['filetype']) ? (string)$row['filetype'] : '',
            'binarydata' => isset($row['binarydata']) ? (string)$row['binarydata'] : '',
            'open' => isset($row['Open']) ? (string)$row['Open'] : (isset($row['open']) ? (string)$row['open'] : ''),
            'title' => isset($row['title']) ? (string)$row['title'] : '',
        ));
        break;

    case 'hz':
    case 'filetype':
        $r = $sc->filetypeLookup($keyword, 'filetype');
        if ($r['count'] < 1) {
            sc_out(array('status' => false, 'message' => '查询无果'));
        }
        if ($r['count'] > 1) {
            sc_out(array(
                'status' => 5,
                'message' => '匹配到 ' . $r['count'] . ' 条，点击列表查看',
                'count' => $r['count'],
            ));
        }
        $row = $r['rows'][0];
        sc_out(array(
            'status' => 4,
            'message' => 'Success',
            'filetype' => isset($row['filetype']) ? (string)$row['filetype'] : '',
            'binarydata' => isset($row['binarydata']) ? (string)$row['binarydata'] : '',
            'open' => isset($row['Open']) ? (string)$row['Open'] : (isset($row['open']) ? (string)$row['open'] : ''),
            'title' => isset($row['title']) ? (string)$row['title'] : '',
        ));
        break;

    case 'softhistory':
    case 'software':
        $r = $sc->softwareLookup($keyword);
        if ($r['count'] < 1) {
            sc_out(array('status' => false, 'message' => '查询无果'));
        }
        $first = $r['rows'][0];
        $firstSoft = isset($first['softname']) ? (string)$first['softname'] : '';
        sc_out(array(
            'status' => 5,
            'message' => 'Success',
            'count' => $r['count'],
            'softname' => $firstSoft,
            'softtype' => isset($first['softtype']) ? (string)$first['softtype'] : '',
            'downloads' => $sc->downloads('software'),
            'Data' => $r['rows'],
        ));
        break;

    case 'driverquery':
    case 'driver':
        $r = $sc->deviceLookup($keyword);
        if ($r['count'] < 1) {
            sc_out(array('status' => false, 'message' => isset($r['message']) ? $r['message'] : '查询无果'));
        }
        $firstDrv = isset($r['rows'][0]['softname']) ? (string)$r['rows'][0]['softname'] : '';
        sc_out(array(
            'status' => 5,
            'message' => 'Success',
            'count' => $r['count'],
            'downloads' => $sc->downloads('driver'),
            'Data' => $r['rows'],
        ));
        break;

    default:
        sc_out(array('status' => false, 'message' => '不支持的查询类型：' . $type));
}

sc_out(array('status' => false, 'message' => '查询失败'));
