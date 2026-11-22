<?php
/* ----------------------------------------------------
 * 二进制文件头查询接口（原 hzsearch.php 原名保留）
 * POST searchdata=xxx → {status:'1',count,Data:[...]}
 * ---------------------------------------------------- */
require __DIR__ . '/../../kernl/Init.php';
global $db, $dou;

@ob_clean();
header('Content-Type: application/json; charset=utf-8');

require_once ROOT_PATH . 'kernl/searchcenter/SearchCenter.class.php';
$sc = new SearchCenter($db, $dou);

$searchdata = isset($_POST['searchdata']) ? trim((string)$_POST['searchdata']) : '';
if ($searchdata === '') {
    echo json_encode(array('status' => '0', 'message' => '关键词为空', 'count' => 0, 'Data' => array()), JSON_UNESCAPED_UNICODE);
    exit;
}

list($data, $count) = $sc->searchLikeRows('zd_filetypetable', 'binarydata', $searchdata, 200);

echo json_encode(array(
    'status' => '1',
    'message' => 'Success',
    'count' => $count,
    'Data' => $data,
), JSON_UNESCAPED_UNICODE);
