<?php 
require("../kernl/Init.php"); //初始化基础参数
switch ($_GET['type'])
{
	case 'process':
		$sql = 'select * from zd_process_list where p_name LIKE "%'.$_COOKIE["SearchData"].'%"';
		$type = 'process';
		break;
	case 'hz':
		$sql = 'select * from zd_filetypetable where filetype LIKE "%'.$_COOKIE["SearchData"].'%"';
		$type = 'hz_binary';
		break;
	default:
		break;
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="renderer" content="webkit">
    <link rel="stylesheet" href="../css/pintuer.css">
	<script src="../js/jquery-1.8.3.min.js"></script>
</head>
<body>
    <div class="mainer"> 
	<div class="line-big">
        <div class="xm9">
            <div class="panel">
              <table class="table" name="list" id="list" style="display:block">
                    <tr><td align="left">根据<?php echo $_COOKIE["SearchData"]; ?>为您查询到：</td></tr>
					<?php 
					$result = $dou->query($sql); 
					while ($row = $dou->fetch_array($result)) { 
						if($row['p_name'] != "")
						{
							$data = $row['p_name'];
							$data_button = $row['p_name'];
							
						} else {
							
							$data_button = $row['filetype'] . "(" . $row['title'] . ")";
							$data = $row['binarydata'];
							
						}
					?>
					<tr><td><a href='#' id='select' onclick='Query("<?php echo $data; ?>","<?php echo $type ; ?>")'><?php echo $data_button; }?></a></td></tr>  
                </table>
			  <table class="table" name="result" id="result" style="display:none">
			        <tr><td width="110" align="right">查询进程名：</td><td id="FileName" > </td></tr>
                    <tr><td align="right">文件路径：</td><td id="FilePath" > </td></tr>
					<tr><td align="right">开发商：</td><td id="Compy" > </td></tr>
					<tr><td align="right">安全等级：</td><td id="Level" > </td></tr>
					<tr><td align="right">描述：</td><td id="Other" > </td></tr>
			</table>
			  <table class="table" name="result_hz" id="result_hz" style="display:none">
			        <tr><td width="110" align="right">查询后缀名：</td><td id="FileName_hz" > </td></tr>
                    <tr><td align="right">文件头：</td><td id="FilePath_hz" > </td></tr>
					<tr><td align="right">执行方式：</td><td id="Compy_hz" > </td></tr>
					<tr><td align="right">描    述：</td><td id="Level_hz" > </td></tr>
			</table>			
            </div>
        </div>
		            </div>
        </div>
    </div>    
</div>
</body>
<script>
function Query(value,type) 
{
	$.ajax({
		url: '../search.php',
        type: 'POST',
        dataType: 'json',
        data: {
                searchdata: value,
				searchfs: type
        },
        success: function(res) {
                var errcode = res.status;
                var errmessage = res.message;
				if(res.status == 2)
				{
					$('#result').attr("style", 'display:block')
					$('#list').attr("style", 'display:none')
					$("#FileName").text(res.p_name)
					$("#FilePath").text(res.p_path)
					$("#Compy").text(res.regcompy)
					$("#Level").text(res.p_level)
					$("#Other").text(res.p_other)
				}
				if(res.status == 4)
				{
					$('#result_hz').attr("style", 'display:block')
					$('#list').attr("style", 'display:none')
					$("#FileName_hz").text(res.Data[0].filetype)
					$("#FilePath_hz").text(res.Data[0].binarydata)
					$("#Compy_hz").text(res.Data[0].Open)
					$("#Level_hz").text(res.Data[0].title)
				}
            }
    })
};	
</script>
</html>