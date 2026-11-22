<?php
/* ----------------------------------------------------
 * 进程安全等级接口（原 api.php 原名保留）
 *   GET  ?name=xxx[&infocenter=yes]  → 文本「危险/谨慎/安全/未知」 或 JSON
 *   POST  name=xxx                    → JSON {p_name,safelevel,message}
 * ---------------------------------------------------- */
require __DIR__ . '/../../kernl/Init.php';
global $db, $dou;

@ob_clean();
header('Content-Type: application/json; charset=utf-8');

require_once ROOT_PATH . 'kernl/searchcenter/SearchCenter.class.php';
$sc = new SearchCenter($db, $dou);

function api_clean($str, $maxLen = 100)
{
    $str = trim((string)$str);
    if (function_exists('mb_substr')) {
        return mb_substr($str, 0, $maxLen, 'UTF-8');
    }
    return substr($str, 0, $maxLen);
}

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';

if ($method === 'GET') {
    $name = api_clean(isset($_GET['name']) ? $_GET['name'] : '');
    $infocenter = isset($_GET['infocenter']) ? (string)$_GET['infocenter'] : '';
    $row = $sc->onePrepare("SELECT * FROM zd_process_list WHERE p_name LIKE ? LIMIT 1", array('%' . $name . '%'));
    $safe = isset($row['safevalue']) ? (int)$row['safevalue'] : -1;

    if ($infocenter === 'yes') {
        header('Content-Type: text/plain; charset=utf-8');
        echo $sc->processLevel($safe);
    } else {
        echo json_encode(array(
            'p_name' => $name,
            'safelevel' => $row ? $safe : null,
            'message' => 200,
        ), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($method === 'POST') {
    $name = api_clean(isset($_POST['name']) ? $_POST['name'] : '');
    $rows = $sc->allPrepare("SELECT * FROM zd_process_list WHERE p_name LIKE ? LIMIT 50", array('%' . $name . '%'));
    $count = count($rows);

    if ($count === 0) {
        echo json_encode(array('p_name' => $name, 'safelevel' => null, 'message' => 404), JSON_UNESCAPED_UNICODE);
    } elseif ($count === 1) {
        echo json_encode(array(
            'p_name' => $name,
            'safelevel' => isset($rows[0]['safevalue']) ? (int)$rows[0]['safevalue'] : null,
            'message' => 200,
        ), JSON_UNESCAPED_UNICODE);
    } else {
        echo '5';
    }
    exit;
}

http_response_code(405);
echo json_encode(array('message' => 405, 'error' => 'Method Not Allowed'), JSON_UNESCAPED_UNICODE);
