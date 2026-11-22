<?php
/* ----------------------------------------------------
 * 文件信息上报接口（原 hzreport.php 原名保留）
 * POST filename/filecode/localtype/localopen → 写入 zd_report
 * ---------------------------------------------------- */
require __DIR__ . '/../../kernl/Init.php';
global $db, $dou;

@ob_clean();
header('Content-Type: application/json; charset=utf-8');

require_once ROOT_PATH . 'kernl/searchcenter/SearchCenter.class.php';
$sc = new SearchCenter($db, $dou);

if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'POST') {
    echo json_encode(array('status' => '405', 'message' => '请求方式错误'), JSON_UNESCAPED_UNICODE);
    exit;
}

$field = function ($key, $max = 255) {
    $v = isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
    return function_exists('mb_substr') ? mb_substr($v, 0, $max, 'UTF-8') : substr($v, 0, $max);
};

$fileName  = $field('filename');
$fileCode  = $field('filecode');
$localType = $field('localtype');
$localOpen = $field('localopen');

if ($fileName === '' && $fileCode === '') {
    echo json_encode(array('status' => '400', 'message' => '文件名与文件头不能同时为空'), JSON_UNESCAPED_UNICODE);
    exit;
}

$affected = $sc->execPrepare(
    "INSERT INTO zd_report (filetype,filecode,localtype,localopen) VALUES (?,?,?,?)",
    array($fileName, $fileCode, $localType, $localOpen)
);

if ($affected === false) {
    echo json_encode(array('status' => '500', 'message' => 'Failed!'), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(array('status' => '200', 'message' => 'Success!'), JSON_UNESCAPED_UNICODE);
