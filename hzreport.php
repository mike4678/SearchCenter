<?php 
// 搜索核心驱动模块	
//error_reporting(0);
require("kernl/Init.php"); //初始化基础参数
ob_clean();
$dou -> CheckServerState();//网站状态检查
$FileName = empty($_POST['filename']) ? '' : $_POST['filename'];  // 搜索内容
$FileCode = empty($_POST['filecode']) ? '' : $_POST['filecode'];  // 搜索内容

//处理传入值
$sql = 'Insert into zd_report (filetype,filecode) values ("'.$FileName.'","'.$FileCode.'")';
//print_r($sql);
if (!$dou->query($sql))
{
	echo json_encode(array('status'=>'500','message'=>'Failed!'));
	
} else {
	
	echo json_encode(array('status'=>'200','message'=>'Success!'));
}
?>
