<?php 
// 搜索核心驱动模块	
//error_reporting(0);
require("kernl/Init.php"); //初始化基础参数
ob_clean();
$dou -> CheckServerState();//网站状态检查
$searchdata = empty($_POST['searchdata']) ? '' : $_POST['searchdata'];  // 搜索内容

//处理传入值
$sql = 'select * from zd_filetypetable where binarydata LIKE "%'.$searchdata.'%"';
//print_r($sql);
$result = $dou->query($sql); 
$Data = array();
while ($row =$dou->fetch_array($result))
{
    $count=count($row);//不能在循环语句中，由于每次删除 row数组长度都减小  
    for($i=0;$i<$count;$i++){  
        unset($row[$i]);//删除冗余数据  
    }
    array_push($Data,$row);
}
echo json_encode(array('status'=>'1','message'=>'Success','count'=>$dou->num_rows($result),'Data'=>$Data));

?>

