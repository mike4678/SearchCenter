<?php
require("kernl/Init.php"); //初始化基础参数
ob_clean();
if($_SERVER['REQUEST_METHOD']=="GET")
{
	$query = $dou -> select('zd_process_list', '*', "process_list.p_name LIKE '%".$_GET['name']."%'", $debug = '');
	$row = $dou->fetch_array($query);
	header("content-type:application/json");// 尽量不要用text/json 某些浏览器会不兼容
	//$json='{"p_name":null,"value":null,"message":500}';//注意外面的单引号
	//针对客户端进行处理
	if ($_GET['infocenter'] == "yes")
	{
		switch($row['safevalue'])
		{
			case 0:
			$json="危险";
			break;
			case 1:
			$json="危险";
			break;
			case 2:
			$json="谨慎";
			break;
			case 3:
			$json="谨慎";
			break;
			case 4:
			$json="安全";
			break;
			case 5:
			$json="安全";
			break;
			default:
			$json="未知";
			break;
		}
	}else {
		
		$json='{"p_name":'.$_GET['name'].',"safelevel":'.$row['safevalue'].',"message":200}';//注意外面的单引号
	}

	echo $json;
	
} 

if($_SERVER['REQUEST_METHOD']=="POST")
{
	
	$query = $dou -> select('zd_process_list', '*', "process_list.p_name LIKE '%".$_POST['name']."%'", $debug = '');
	$row = $dou->fetch_array($query);
	$count = $dou ->affected_rows();
	switch($count)
	{
		case 0:
			header("content-type:application/json");// 尽量不要用text/json 某些浏览器会不兼容
			$json='{["p_name":'.$_POST['name'].',"safelevel":null,"message":404}';//注意外面的单引号
			$dou -> InsertKeyword($_POST['name'],'Process');
			echo $json;
			//echo '0';
			break;
		
		case 1:
			header("content-type:application/json");// 尽量不要用text/json 某些浏览器会不兼容
			$json='{"p_name":'.$_POST['name'].',"safelevel":'.$row['safevalue'].',"message":200}';//注意外面的单引号
			$dou -> InsertKeyword($_POST['name'],'Process');
			echo $json;
			//echo '5';
			break;
		
		default:
			//header("content-type:application/json");// 尽量不要用text/json 某些浏览器会不兼容
			//$json='{"name":null,"singer":null,"message":404}';//注意外面的单引号
			$dou -> InsertKeyword($_POST['name'],'Process');
			//echo $json;
			echo '5';
			break;
	}	
	
}
?>




