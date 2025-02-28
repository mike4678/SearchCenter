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
		switch($count)
		{
			case 0:
			echo json_encode(array('status'=>'-1','message'=>'查询无果','test'=>$querydata));
			exit;
			
			case 1:
			echo json_encode(array('status'=>'0','message'=>'Success','mac'=>$searchdata,'mac_q'=>$querydata,'regcompy'=>$row['Organization'])); 
			exit;
		
			default:
			echo json_encode(array('status'=>'-1','message'=>'查询无果','test'=>$querydata));
			exit;
		}
		break;
		
	case 'ipv4':
		if($_POST['software_alive'] == 'get')
		{
			if($searchdata="local")
			{
				$region = $dbSearcherv4->search($dou -> Get_LocalIP());
				echo json_encode(array('status'=>'1','message'=>'Success','UserIP'=>$dou -> Get_LocalIP(),'Data'=>$region ));// 输出查询结果
			
			}
		} else {
			//IP地址分析
			$pattern = '/((2(5[0-5]|[0-4]\d))|[0-1]?\d{1,2})(\.((2(5[0-5]|[0-4]\d))|[0-1]?\d{1,2})){3}/'  ;
			if(!preg_match($pattern, $searchdata))
			{
				echo json_encode(array('status'=>'-1','message'=>'不是一个有效的ip地址！'));
				exit;
			}
			$region = $dbSearcherv4->search($searchdata);
			echo json_encode(array('status'=>'1','message'=>'Success','UserIP'=>$dou -> Get_LocalIP(),'Data'=>$region ));// 输出查询结果
			
		}
		break;
		
		case 'ipv6':
		if($_POST['software_alive'] == 'get')
		{
			if($searchdata="local")
			{
				$region = $dbSearcherv4->search($dou -> Get_LocalIP_V6());
				echo json_encode(array('status'=>'1','message'=>'Success','UserIP'=>$dou -> Get_LocalIP_V6(),'Data'=>$region ));// 输出查询结果
			
			}
		} else {
			//IP地址分析
			$pattern = '/^(([0-9a-fA-F]{1,4}:){7}([0-9a-fA-F]{1,4})|(([0-9a-fA-F]{1,4}:){1,7}:)|(([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4})|(([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2})|(([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3})|(([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4})|(([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5})|([0-9a-fA-F]{1,4}:)((:[0-9a-fA-F]{1,4}){1,6})|:(:((:[0-9a-fA-F]{1,4}){1,7}|:))|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]|[1-9]|)[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]|[1-9]|)[0-9])|(([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]|[1-9]|)[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]|[1-9]|)[0-9])))$/';
			if(!preg_match($pattern, $searchdata))
			{
				echo json_encode(array('status'=>'-1','message'=>'不是一个有效的ip地址！'));
				exit;
			}
			$region = $dbSearcherv6->search($searchdata);
			echo json_encode(array('status'=>'1','message'=>'Success','UserIP'=>'','Data'=>$region ));// 输出查询结果
		}
		break;
		
	case 'process':
		$sql = 'select * from zd_process_list where p_name LIKE "%'.$searchdata.'%"';
		$row = $dou -> fetch_array($dou -> query($sql));
		$count = $dou -> affected_rows();
		switch($count)
		{
			case 0:
				echo json_encode(array('status'=>'-1','message'=>'查询无果'));
				exit;
				
			case 1:
				echo json_encode(array('status'=>'2','message'=>'Success','p_name'=>$searchdata,'p_path'=>$row['path'],'regcompy'=>$row['compy'],'p_level'=>$row['safevalue'],'p_other'=>$row['other'])); 
				exit;
		
			default:
				echo json_encode(array('status'=>'3','message'=>'Success','Data'=>$count));
				exit;
		}
		break;
		
	case 'hz':
		$sql = 'select * from zd_filetypetable where filetype LIKE "%'.$searchdata.'%"';
		//print_r($sql);
		$result = $dou->query($sql); 
		$Data = array();
		while ($row =$dou->fetch_array($result))
		{		
			$count=count($row);//不能在循环语句中，由于每次删除 row数组长度都减小  
			for($i=0;$i<$count;$i++)
			{  
				unset($row[$i]);//删除冗余数据  
			}
			array_push($Data,$row);
		}
		echo json_encode(array('status'=>'4','message'=>'Success','count'=>$dou->num_rows($result),'Data'=>$Data));
		break;
		
	case 'hz_binary':
		$sql = 'select * from zd_filetypetable where binarydata LIKE "%'.$searchdata.'%"';
		$result = $dou->query($sql); 
		$Data = array();
		while ($row =$dou->fetch_array($result))
		{		
			$count=count($row);//不能在循环语句中，由于每次删除 row数组长度都减小  
			for($i=0;$i<$count;$i++)
			{  
				unset($row[$i]);//删除冗余数据  
			}
			array_push($Data,$row);
		}
		echo json_encode(array('status'=>'4','message'=>'Success','count'=>$dou->num_rows($result),'Data'=>$Data));
		break;
		
		
	case 'softhistory':
		$sql = 'select * from zd_softwarelibrary where softname LIKE "%'.$searchdata.'%"';
		$result = $dou->query($sql); 
		$Data = array();
		while ($row =$dou->fetch_array($result))
		{		
			$count=count($row);//不能在循环语句中，由于每次删除 row数组长度都减小  
			for($i=0;$i<$count;$i++)
			{  
				unset($row[$i]);//删除冗余数据  
			}
			array_push($Data,$row);
		}
		echo json_encode(array('status'=>'5','message'=>'Success','count'=>$dou->num_rows($result),'Data'=>$Data));
		break;
}
?>
