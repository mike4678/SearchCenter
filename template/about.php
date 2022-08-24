<?php 
require("../kernl/Init.php"); //初始化基础参数
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="renderer" content="webkit">
	<link rel="stylesheet" href="../css/style.min.css">
	<link rel="stylesheet" href="../fonts/material-design/css/materialdesignicons.css">
	<link rel="stylesheet" href="../plugin/mCustomScrollbar/jquery.mCustomScrollbar.min.css">
	<link rel="stylesheet" href="../plugin/waves/waves.min.css">
	<link rel="stylesheet" href="../plugin/sweet-alert/sweetalert.css">
	<link rel="stylesheet" href="../css/style-dark.min.css">
</head>
<body>
<div class="row">
	<div class="col-lg-4 col-md-6 col-xs-12">
    	<div class="box-content">
              <table class="table">
                    <tr><td width="110" align="right">开发：</td><td>Source</td><td align="right">官网：</td><td><a href="http://bbs.csource.com.cn" target="_blank">点击访问</a></td></tr>
                    <tr><td align="right">可查询数：</td><td>Mac Address：<?php echo $dou -> GetSearchCount('zd_mac_addr');  ?><br>Process：<?php echo $dou -> GetSearchCount('zd_process_list');  ?><br>FileType：<?php echo $dou -> GetSearchCount('zd_filetypetable');  ?></td><td align="right">更新时间：</td><td>2022-08-24</td></tr>
                    <tr><td align="right">IP库版本：</td><td><?php echo $dou -> Get_IPVersion(); ?></td><td align="right">更新时间：</td><td><?php echo $dou -> Get_IPVersion(); ?></td></tr>					
					<tr><td align="right">系统版本：</td><td>V1.0</td><td align="right">错误提交：</td><td><a href="http://bbs.csource.com.cn" target="_blank">点击提交</a></td></tr>
					<tr><td align="right">源码下载：</td><td><a href="http://tool.csource.com.cn/QueryMAC.zip" target="_blank">点击下载</a>(<a href="https://github.com/mike4678/SearchCenter" target="_blank">GitHub</a>)(<a href="https://gitee.com/marcocat/SearchCenter" target="_blank">Gitee</a>)</td><td align="right">客户端下载：</td><td><a href="http://tool.csource.com.cn/QueryClient.zip" target="_blank">点击下载</a></td></tr>
                </table>
            </div>
        </div>
		            </div>
    </div>    
</div>
</body></html>