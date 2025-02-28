<?php 
// 搜索核心驱动模块	
//error_reporting(0);
require("kernl/Init.php"); //初始化基础参数
ob_clean();
$dou -> CheckServerState();//网站状态检查
$searchdata = empty($_POST['searchdata']) ? '' : $_POST['searchdata'];  // 搜索内容
$type = empty($_POST['searchfs']) ? '' : $_POST['searchfs'];  // 搜索方式
setcookie("SearchData", $searchdata, time() + 3600, "/", $_SERVER['SERVER_NAME'], isset($_SERVER["HTTP"]), true);
switch($type)
{
	case 'mac':
		//MAC地址分析
		$pattern = "/^([A-Fa-f0-9]{2}[-,:]){5}[A-Fa-f0-9]{2}$/"  ;
		if(!preg_match($pattern, $searchdata))
		{
			echo json_encode(array('status'=>'-1','message'=>'不是一个有效的MAC地址！<br> 有效的地址格式为：xx-xx-xx-xx-xx-xx'));
			exit;
		}
		if(strpos($searchdata,"-") != FALSE) 
		{
			$replace1 = str_replace("-","",$searchdata);
		} else {
			if(strpos($searchdata,":") != FALSE) 
			{
				$replace1 = str_replace(":","",$searchdata);
			}	
		}
		$querydata = substr($replace1,0,6);
		$sql = 'select * from zd_mac_addr where OUI_HEX LIKE "%'.$querydata.'%"';
		$row = $dou -> fetch_array($dou -> query($sql));
		$count = $dou -> affected_rows();
		switch($c