<?php
/* ---------------------------------------------------- */
/* 程序名称: 核心函数
/* 程序功能: 所有核心函数的调用均在此页面中
/* 程序开发: Source
/* 联系方式: 542112943@qq.com
/* Date: 1970-01-01 / 2019-01-01
/* ---------------------------------------------------- */
/* 使用条款:
/* 1.该软件免费使用.
/* 2.禁止任何衍生版本.
/* ---------------------------------------------------- */

class System extends Db 
{
	//系统设置表信息
	/* ---------------------------------------------------- */
	function Info($data) {
		// 使用参数化查询
		$key = $data; // 确保 $key 已定义
		$stmt = $this->prepare("SELECT `value` FROM `zd_system_setting` WHERE `vars` = ?");
		if ($stmt === false) {
        // 输出具体错误信息（开发环境）
        throw new RuntimeException("SQL 准备失败: " . $this->SystemError());
		}
		$stmt->bind_param("s", $key);
		$stmt->execute();
		$result = $stmt->get_result();
    
		if ($row = $result->fetch_assoc()) {
			return $row['value'];
		}
		// 配置项不存在时返回空串而不是抛异常：
		// 旧库升级到新库时常缺字段（如 Is_FirstRun），抛异常会让整个后台白屏。
		return '';
	}	
	/* ---------------------------------------------------- */
	
		//软件设置表信息
	/* ---------------------------------------------------- */
	function SInfo($data) {                  
		$query = $this->select_all('zd_config');
		$row = $this->fetch_array($query);
		return( $row[$data]);
		//break;
		}
	/* ---------------------------------------------------- */
		
		//软件信息
	/* ---------------------------------------------------- */
	function SoftwareDataInfo($various,$id) {                  
		$query = $this->select('zd_software', $various, 'id = ' . $id);
		$row = $this->fetch_array($query);
		//print_r($row );
		return( $row[$various]);
		//break;
		}
	/* ---------------------------------------------------- */
	
	//删除一条或多条数据
	/* ---------------------------------------------------- */
	function DelData($table,$hterm,$term,$method)    //参数：表$table,条件头$hterm,条件$term,操作方式$method
	{ 
		// 修复（2026）：原实现把 $table/$hterm/$term 直接拼进 SQL，可被构造出
		// 任意删除语句（SQL 注入）。这里表名/字段名走白名单，值统一走 quote()。
		$tbl = $this->safeIdent($table);
		$col = $this->safeIdent($hterm);
		if ($tbl === false || $col === false || (string)$term === '' || $method === '') {
			echo "<script language='JavaScript'>window.alert('操作请求所需要的值错误，请检查！')</script>";
			return;
		}

		switch ($method) {
			default:
				echo '<script language="JavaScript">window.alert("无效的操作请求")</script>';
			break;

			case 'POST':    //处理多个删除任务（值以逗号分隔）
				$ids = array();
				foreach (explode(',', (string)$term) as $one) {
					$one = trim($one);
					if ($one !== '') {
						$ids[] = $this->quote($one);
					}
				}
				if (empty($ids)) {
					echo '<script language="JavaScript">window.alert("删除失败！")</script>';
					break;
				}
				$sql = "DELETE FROM `" . $tbl . "` WHERE `" . $col . "` IN (" . implode(',', $ids) . ")";
				if (!$this->query($sql)) {
					echo '<script language="JavaScript">window.alert("删除失败！")</script>';
					break;
				}
				echo '<script language="JavaScript">window.alert("删除成功！")</script>';
				echo '<script language="JavaScript">self.location=document.referrer;</script>';
			break;

			case 'GET':    //处理单个删除任务
				$url = urldecode((string)$term);
				if ($url === '') {
					break;
				}
				$sql = "DELETE FROM `" . $tbl . "` WHERE `" . $col . "` = " . $this->quote($url);
				if (!$this->query($sql)) {
					echo '<script language="JavaScript">window.alert("删除失败！")</script>';
					break;
				}
				echo '<script language="JavaScript">window.alert("删除成功！")</script>';
				echo '<script language="JavaScript">self.location=document.referrer;</script>';
			break;
		} 
	}
	/* ---------------------------------------------------- */
	
	//页面功能生成
	/* ---------------------------------------------------- */	
	function table_list_New($table,$table_list) //自动生成左边列表
	{  
		if( empty($table) && empty($table_list) )   //检查，如果这两个参数都为空，则返回错误信息，
		{ 
			
			echo '<script language="JavaScript">window.alert("初始化页面失败！");history.back(-1);</script>';
			
 	 	} else { 
			$tab = ""; //初始化变量
			$TableData = explode("|", $this -> Info("system_table"));
				foreach($TableData as $val)
				{
					$row = explode(";", $val);
					$groupName = isset($row[0]) ? trim($row[0]) : '';
					if ($groupName === '') { continue; }
					// 先取出该分组下的菜单项；没有菜单项的分组不渲染（避免空分组留在左侧）
					$items = '';
					$stmt = $this->prepare("SELECT `url`,`menu` FROM `zd_adminlist` WHERE `table` = ? ORDER BY `count` ASC, `m_e` ASC");
					if ($stmt) {
						$stmt->bind_param('s', $groupName);
						$stmt->execute();
						$result = $stmt->get_result();
						if ($result) {
							while ($row1 = $result->fetch_assoc()) {
								$items .= "<li><a href='" . h($row1['url']) . "'>" . h($row1['menu']) . "</a></li>";
							}
						}
						$stmt->close();
					}
					if ($items === '') { continue; }
					$tab.="<li><a class='waves-effect parent-item js__control' href='#'><i class='menu-icon mdi mdi-desktop-mac'></i><span>" . h($groupName) . "</span><span class='menu-arrow fa fa-angle-down'></span></a><ul class='sub-menu js__content'>";
					$tab.=$items;
					$tab.="</ul></li>";
				}
		}
			return $tab;
		}
	function front_table_list() //自动生成左边列表
	{  
		$tab = "<li><a class='waves-effect parent-item js__control' href='index.php'><i class='menu-icon mdi mdi-desktop-mac'></i><span>查询类型</span><span class='menu-arrow fa fa-angle-down'></span></a><ul class='sub-menu js__content'>"; //初始化变量
		$sql = "select * from zd_type;"; 
		$result = $this -> query($sql); 
		while ($row = $this -> fetch_array($result)) 
		{
			$tab.="<li><a href='index.php?uid=".$row['uid']."'> |- ".$row['name']."</a></li>";
			
		}
		$tab.="</ul></li>";
		return $tab;
	}
	
	function navigation ($table,$table_list)   //导航栏内容
	{   
		if( empty($table) || empty($table_list) )   //检查
		{ 
			echo '<script language="JavaScript">window.alert("初始化导航栏失败！");history.back(-1);</script>';
			
		} else { 
			$infor = array(); 
			$query = $this->select('zd_adminlist', '*', "zd_adminlist.table='".$table."'  AND zd_adminlist.menu = '".$table_list."' UNION ALL SELECT * FROM `zd_adminlist` where adminlist.table = '".$table."' ORDER BY count ASC limit 2;", $debug = '');
			while (	$row = $this->fetch_array($query))   
			{
				$TaskBar = "<a href='admin.php' class='icon-home'> 首页</a> > ";
				$TaskBar.= "<ui>".$row[2]."管理</ui> > "; 
				$TaskBar.="".$table_list."";
				break;
			}
		}
		return $TaskBar;
	}

	function convert($table,$table_list)  //将英文名称转换为中文
	{  
		// 修复：原拼接方式可注入；改为参数化查询
		$stmt = $this->prepare("SELECT * FROM `zd_adminlist` WHERE `t_e` = ? AND `m_e` = ? LIMIT 1");
		if (!$stmt) {
			return array();
		}
		$dashTable = (string)$table;
		$dashMenu  = (string)$table_list;
		$stmt->bind_param('ss', $dashTable, $dashMenu);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		return is_array($row) ? $row : array();
	}
	
	function AddrConvery($data)  //处理请求
	{  
		$arr = array('', '', ''); // 修复：无参数请求时数组未定义，array_slice(null) 会告警
		if(count($data) > 1)
		{
			//获取第一个主Key值
			reset($data);
			$first_key = key($data);
			$arr = explode("/",$first_key);
			return $arr;
		}
		
		foreach ($data as $key=>$value) //把参数传入变量key中
		$arr = explode("/",$key);
		return $arr;
		
	}	
	
	function PageLoading($table,$table_list = '')    //载入框架页面
	{  
		// 修复：表名/菜单名参数化，且只允许返回 admin 目录下的 php 相对路径
		$stmt = $this->prepare("SELECT `page` FROM `zd_adminlist` WHERE `table` = ? AND `menu` = ? LIMIT 1");
		if (!$stmt) {
			return '';
		}
		$tab  = (string)$table;
		$menu = (string)$table_list;
		$stmt->bind_param('ss', $tab, $menu);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		return isset($row['page']) ? (string)$row['page'] : '';
	}
	
	function FormCheck($address = '')  //检查框架页面是否存在，即不允许直接访问
	{   
		echo '<script>if(window.top==window.self){ window.alert("请勿试图在非授权区域运行！")'.$this -> WriteLog('Get','试图从外部访问地址:'.$address.'已被拦截',$address).';var browserName=navigator.appName; if (browserName=="Netscape") {window.opener = null; window.open("","_self");window.close(); } else {open(location, "_self").close();window.location.href = "about:blank"; window.close();}  }</script>';
		
	}

	function FormCreate($data)  //多选项夹生成
	{ 
		$form = '<div class="col-xs-12"><div class="box-content"><div class="row"><div class="col-md-12"><ul class="nav nav-tabs" id="myTabs" role="tablist">';
		unset($data['TabName']); //删除标题数组
		foreach ($data as $row)   
		{
			// 修复：tab / Name 直接拼接会引入 XSS（标签名与链接可控时）。
			$tab  = isset($row['tab']) ? h((string)$row['tab']) : '';
			$name = isset($row['Name']) ? h((string)$row['Name']) : '';
			// 修复：bootstrap 选项夹必须用 #id 作为 href，否则点击会跳 URL，选项夹失效
			if ($tab !== '' && strpos($tab, '#') !== 0) {
				$tab = '#' . $tab;
			}
			$liClass = (isset($row['active']) && $row['active'] == 'active') ? ' class="active"' : '';
			// 关键：链接的 id 不能与面板 id 相同，否则 $('#panel') 会命中链接本身，选项夹永远切不到面板
			$form .= '<li role="presentation"' . $liClass . '><a href="' . $tab . '" role="tab" id="tab-link-' . str_replace('#', '', $tab)
				. '" data-toggle="tab" aria-controls="' . str_replace('#', '', $tab) . '">' . $name . '</a></li>';
		}
		$form.= "</ul>"; 
		echo $form;
	}
	
	function PageValue($table,$table_list = '')  //读入页面参数
	{  
		$stmt = $this->prepare("SELECT `value` FROM `zd_adminlist` WHERE `table` = ? AND `menu` = ? LIMIT 1");
		if (!$stmt) {
			return '';
		}
		$tab  = (string)$table;
		$menu = (string)$table_list;
		$stmt->bind_param('ss', $tab, $menu);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		return isset($row['value']) ? (string)$row['value'] : '';
	}
	
	function content($data,$value)
	{                     //内容页面相关参数返回
		$stmt = $this->prepare("SELECT * FROM `zd_content_data` WHERE `list` = ? LIMIT 1");
		if (!$stmt) {
			return 'error';
		}
		$listName = (string)$data;
		$stmt->bind_param('s', $listName);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		if (!is_array($row)) {
			return 'error';
		}
		return isset($row[$value]) ? $row[$value] : '';
	}
	/* ---------------------------------------------------- */
	
	//写入系统日志
	/* ---------------------------------------------------- */
	function WriteLog($method,$data,$addr)     //参数：请求方式、操作内容、操作页面
	{    
		$ip = $_SERVER["REMOTE_ADDR"]; //获取IP
		$time = date("y/m/d",time());  //获取现在时间
		// 修复（2026）：参数化写入，去掉 max(id)+1 的并发竞态与字符串拼接注入
		$ip   = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
		$time = date('Y-m-d H:i:s');
		$m    = (string)$method;
		$d    = (string)$data;
		$a    = (string)$addr;
		$stmt = $this->prepare("INSERT INTO `zd_system_log` (`method`,`ip`,`data`,`addr`,`time`) VALUES (?,?,?,?,?)");
		if (!$stmt) {
			return false;
		}
		$stmt->bind_param('sssss', $m, $ip, $d, $a, $time);
		if (!$stmt->execute()) {
			error_log('写入日志失败: ' . $this->SystemError());
			return false;
		}
		return true;
	} 
	/* ---------------------------------------------------- */
	
		//上传大小检查
	/* ---------------------------------------------------- */
	function CheckUploadSize() 
	{
		$File_size = $this->Info('upload_size');
		$PHP_Size = str_replace("M","",ini_get('post_max_size'));
		if($File_size == NULL || $File_size == 0 || $File_size > $PHP_Size) 
		{
			$size = $PHP_Size - 1;
			$sql = "update zd_system_setting set value = '". $size ."' where vars = 'upload_size';";
			if(!$this->query($sql))
			{
				echo '参数检测发现异常，但尝试修复失败！';
			}
		} 
	}

	/* ---------------------------------------------------- */
	
		
		//系统状态检查
	/* ---------------------------------------------------- */
	function CheckServerState() 
	{
		$staus = $this->Info('server_status');
		if($staus == "1") 
		{
			echo $this->Sys_ErrorPage(503);
			exit;
		}
	}

	/* ---------------------------------------------------- */
	
		
		
		//登陆状态（2026 重写：改为服务端会话 + HMAC 令牌，彻底移除 Cookie 里的账号密码）
	/* ---------------------------------------------------- */
	function AccountState() 
	{
		$user = isset($_SESSION['sm_admin_user']) ? (string)$_SESSION['sm_admin_user'] : '';
		$sign = isset($_SESSION['sm_admin_sign']) ? (string)$_SESSION['sm_admin_sign'] : '';
		$time = isset($_SESSION['sm_admin_time']) ? (int)$_SESSION['sm_admin_time'] : 0;

		if ($user === '' || $sign === '' || $time === 0) {
			return 'Access denied';
		}
		// 会话有效期：2 小时无操作即失效
		if ($time < time() - 7200) {
			$this->Logout();
			return 'Access denied';
		}
		if (!hash_equals($this->signToken($user, $time), $sign)) {
			$this->Logout();
			return 'Access denied';
		}
		// 账号仍在库中存在才算有效（被删除的账号立即失效）
		$stmt = $this->prepare("SELECT `username` FROM `zd_admin_user` WHERE `username` = ? LIMIT 1");
		if (!$stmt) {
			return 'Access denied';
		}
		$stmt->bind_param('s', $user);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		if (!is_array($row)) {
			$this->Logout();
			return 'Access denied';
		}
		// 校验通过后刷新活跃时间，并同步重签令牌（否则下次请求会因时间变化而判定失效）
		$now = time();
		$_SESSION['sm_admin_time'] = $now;
		$_SESSION['sm_admin_sign'] = $this->signToken($user, $now);
		return 'Access granted';
	}

	/* 会话令牌签名 */
	private function signToken($user, $time)
	{
		return hash_hmac('sha256', $user . '|' . $time . '|' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''), ENCRYPTION_KEY);
	}

	/* 登录成功：写入会话（$save 仅用于延长有效期） */
	function LoginUser($username, $save = false)
	{
		$username = (string)$username;
		session_regenerate_id(true);
		$_SESSION['sm_admin_user'] = $username;
		$_SESSION['sm_admin_time'] = time();
		$_SESSION['sm_admin_sign'] = $this->signToken($username, time());
		if ($save) {
			// “记住登录”只延长会话 Cookie 生命周期，不再在客户端存任何凭据
			@setcookie(session_name(), session_id(), time() + 86400 * 7, '/', '', isHttps(), true);
		}
		return true;
	}

	/* 退出登录 */
	function Logout()
	{
		unset($_SESSION['sm_admin_user'], $_SESSION['sm_admin_sign'], $_SESSION['sm_admin_time']);
		$_SESSION = array();
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_destroy();
		}
		return true;
	}

	/* 当前登录用户名 */
	function CurrentUser()
	{
		return isset($_SESSION['sm_admin_user']) ? (string)$_SESSION['sm_admin_user'] : '';
	}

	/* 登录审计：登录、退出、失败都入库（登录日志） */
	function LoginAudit($action, $username = '', $result = '', $detail = '')
	{
		$msg = '[' . $result . '] ' . $action . ' 账号=' . $username . ($detail !== '' ? ' ' . $detail : '');
		return $this->WriteLog('LOGIN', $msg, isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '');
	}

	/* 后台操作审计（增删改统一入口） */
	function OpLog($action, $detail = '')
	{
		$user = $this->CurrentUser();
		$msg  = '[' . $user . '] ' . $action . ($detail !== '' ? '：' . $detail : '');
		return $this->WriteLog('ADMIN', $msg, isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '');
	}

	/* 后台请求全量审计：每次进入后台页面都记一条（含请求方式、模块、页面文件、附带参数）
	   页面内部的增删改仍可通过 OpLog() 追加更具体的记录。 */
	function AuditRequest($table, $menu, $file, $extra = '')
	{
		$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET';
		$user   = $this->CurrentUser();
		$uri    = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';

		$parts = array();
		if ($table !== '' || $menu !== '') {
			$parts[] = '模块 ' . $table . '/' . $menu;
		}
		if ($file !== '') {
			$parts[] = '页面 ' . $file;
		}
		if ($extra !== '') {
			$parts[] = $extra;
		}
		$parts[] = '页面地址 ' . $uri;

		$msg = '[' . $user . '] 后台请求(' . $method . ') ' . implode('，', $parts);
		if (function_exists('mb_substr')) {
			$msg = mb_substr($msg, 0, 900, 'UTF-8');
		} else {
			$msg = substr($msg, 0, 900);
		}
		return $this->WriteLog('ADMIN', $msg, $uri);
	}

	/* 防御开关：csrf / xss 等，取系统设置，缺省为开启 */
	function Defense($key)
	{
		$map = array(
			'csrf' => 'defense_csrf',
			'xss'  => 'defense_xss',
			'wscs' => 'defense_wscs',
			'sql'  => 'defense_sql',
		);
		$var = isset($map[$key]) ? $map[$key] : $key;
		$val = $this->Info($var);
		return $val === '' ? true : ($val === '1');
	}
	
	/* ---------------------------------------------------- */
	
		
		//PHP COOKIE设置函数立即生效，支持数组
	/* ---------------------------------------------------- */
	function cookie($var, $value = '', $time = 0, $path = '', $domain = '', $s = false)
	{
    	$_COOKIE[$var] = $value;
    	if (is_array($value)) 
		{
        	foreach ($value as $k => $v)
			{
            	setcookie($var . '[' . $k . ']', $v, $time, $path, $domain, $s);
        	}
			
    	} else {
			
        	setcookie($var, $value, $time, $path, $domain, $s);
    	}
	}
	
	/* ---------------------------------------------------- */
		
	
		
		//在线检查更新
	/* ---------------------------------------------------- */
	function CheckUpdate()
	{
		
		if(strtolower($this -> Info("update_status")) == 'custom')  //定制版
		{
			echo '定制版请联系开发者获取更新';
			
		} else {
			
			$opts = array(
							'http'=>array(
							'method'=>"GET",
							'timeout'=>600,
							)
						);
		
			$context = stream_context_create($opts);
			$url = 'http://service.csource.com.cn/update/check.php?product=' . $this -> Info("update_status"); 
			$html = file_get_contents($url,false, $context); 
			$str = strtr($html, "\t", ' ');
			$encode = mb_detect_encoding($str, array("ASCII","UTF-8","GB2312","GBK","BIG5"));
				if($encode != "UTF-8")
				{
  					$str = iconv("GBK","UTF-8",$str);
				}
			$return_data = json_decode($str,true);
			echo '<a href="javascript:SystemBox(2,\'http://service.csource.com.cn/update/update.php?product=' . $this -> Info("update_status") . '\',\'\',\'系统更新\',400,420)" >' . $return_data['version'] . '</a>'; 
			
			}
	}
	
	
	/* ---------------------------------------------------- */
	
			
	
		
		//数据表检查(参数：需要检查的表名)
	/* ---------------------------------------------------- */
	function TableCheck($table)   
	{
		
		$result = $dou -> query("SELECT * FROM ".$table );
  		while($row = $dou -> fetch_array($result))
  		{     
			if(!$row)
			{         
				die('系统初始化出现异常错误，请删除kernl目录下config文件后，尝试重新安装，如果仍看到此提示，请与管理员联系！');
				
			}    
  		}
		
	}
	
	/* ---------------------------------------------------- */

	
		
		//IP管理核心部分
		//type 定义
		// 0   白名单
		// 1   黑名单
		// 2   双向
	/* ---------------------------------------------------- */
	function Get_LocalIP()          //获取客户端IP
	{
		
		if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown")) 
			$ip = getenv("HTTP_CLIENT_IP"); 
		else if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown")) 
			$ip = getenv("HTTP_X_FORWARDED_FOR"); 
		else if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown")) 
			$ip = getenv("REMOTE_ADDR"); 
		else if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown")) 
			$ip = $_SERVER['REMOTE_ADDR']; 
		else 
			$ip = "unknown"; 
		return($ip); 
		
	}
	
	function Check_IPStatus()     //检查当前IP是否被限制
	{
		
		if($this -> Info('ipfirewall_status') == 0)
		{
			
			$Data = $this -> Get_IpList(); //服务端IP
			$Check_IP_Arrary = explode('.',$this -> Get_LocalIP()); //当前客户端IP
			$List_IP = ''; //黑白名单列表
			$Allow = false;
			foreach ($Data as $value)
			{
				if($value['type'] == '2' || $value['type'] == $this -> Info('ipfirewall_mode'))
				{
					$List_IP[] = explode('.',$value['iptable']);
				} 	
			}

			foreach ($List_IP as $value)
			{
				$Allow_Number = 0; //匹配次数
				$TDF_Number = 0;  //替代符出现次数				
				for ($i=0;$i<4;$i++)
				{
					if($value[$i] != '*')
					{

          				if($Check_IP_Arrary[$i] == $value[$i])
						{
									
							$Allow_Number++;
						}
						
        			} else {  //发现*号，记录该符号出现次数
								
						$TDF_Number++;
					}
      			}
				if($TDF_Number != 0) 
				{
					if($Allow_Number + $TDF_Number == 4)
					{
						$Allow = 'TRUE';

					} 
						
				} elseif($Allow_Number == 4) 
				{
					$Allow = 'TRUE';
					
				} 

			}
			
			switch ($this -> Info('ipfirewall_mode'))
			{
				case '0':
					if($Allow != 'TRUE') 
					{
						$return = array(
       									'status' => 2,
       									'msg' => '该IP无权限访问',
       									'data' => $Check_IP_Arrary
       									);
    					return json_encode($return);
						exit();
					}
					break;
					
				case '1':
					if($Allow == 'TRUE') 
					{
						$return = array(
       									'status' => 2,
       									'msg' => '该IP无权限访问',
       									'data' => $Check_IP_Arrary
       									);
    					return json_encode($return);
						exit();
					}
					break;
			}
		}
	}
	
	function IPFirewallControl($method, $ip , $mode)    //IP新增删除修改
	{
		// 修复：$ip/$mode 原样拼 SQL。这里先做格式校验再用 quote() 转义。
		$ip = trim((string)$ip);
		if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
			return 'failed';
		}
		$mode = (string)$mode;
		if (!in_array($mode, array('0', '1'), true)) {
			$mode = '0';
		}
		if ($method != "" && $ip != "")
		{
			$sql = '';
			switch($method)
			{
				case 'add':
					$sql = "INSERT INTO `system_ips` (`iptable`, `type`) VALUES (" . $this->quote($ip) . ", " . $this->quote($mode) . ")";
					break;
				
				case 'edit':
					$sql = "UPDATE `system_ips` SET `type`=" . $this->quote($mode) . " WHERE `iptable`=" . $this->quote($ip);
					break;
					
				case 'del':
					$sql = "DELETE FROM `system_ips` WHERE `iptable`=" . $this->quote($ip);
					break;
					
			}
			if ($sql === '') {
				return 'failed';
			}
			
			if(!$this->query($sql))
			{
				return 'failed';
				
			} else {
				
				echo '<script language="JavaScript">window.alert("操作成功！")</script>';
			}
			
		} else {
			
			die("参数数据错误！<a href='javascript:history.go(-1)'>点击返回</a>");
			
		}
	
	}
	
	function Get_IpList()   //获取当前模式下的ip信息
	{
		
		$IPListRow = $this->fetch_array_all('system_ips');
		return $IPListRow;
		
	}	
	/* ---------------------------------------------------- */

		
		//随机字符生成
	/* ---------------------------------------------------- */	
	
	function generate_password( $length = 8 ) 
	{
    // 密码字符集，可任意添加你需要的字符
    	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#￥%&*()<>?{}[]|';

    	$password = '';
    	for ( $i = 0; $i < $length; $i++ ) 
    	{
        // 这里提供两种字符获取方式
        // 第一种是使用 substr 截取$chars中的任意一位字符；
        // 第二种是取字符数组 $chars 的任意元素
        // $password .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        	$password .= $chars[ mt_rand(0, strlen($chars) - 1) ];
    	}

    return $password;
	}
	/* ---------------------------------------------------- */
			//首页错误页生成
	/* ---------------------------------------------------- */	
	function getNeedBetween($kw,$mark1,$mark2)
	{
		$st = stripos($kw,$mark1);
		$ed = stripos($kw,$mark2);
		if(($st == false || $ed == false) || $st >= $ed)
		{
			return 0;
		}
		$kw = substr($kw,($st+1),($ed-$st-1));
		return $kw;
	}
	/* ---------------------------------------------------- */
		//首页错误页生成
	/* ---------------------------------------------------- */	
	
	function Index_ErrorPage ( $code = 3 ) 
	{
    	// 根据错误码生成对应的错误文本
    	switch($code) //判断错误码
		{
			
			case '307':    
				$errorcode = "<span>3</span><span>0</span><span>7</span>";
				$data = "如果您看到此页面，是由于启用了首页模块，但所设置的页面系统未搜索到，如果您是网站管理员请检查所设置页面是否可以访问";
				$title = "";
				break;
			case '301':    
				$errorcode = "<span>3</span><span>0</span><span>1</span>";
				$data = "如果您看到此页面，是由于启用了首页模块，但并未设置相应页面，如果您是网站管理员可到系统设置中进行设置";
				$title = "";
				break;
			case '404':    
				$errorcode = "<span>4</span><span>0</span><span>4</span>";
				$data = "这是系统默认首页，如果您看到此页面说明当前网站服务是正常的，但未设置前端主页！";
				$title = "Page No Found!";
				break;	
		}
		
		$Page = '<!DOCTYPE html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><link href="../css/error.css" rel="stylesheet" type="text/css" media="all" /><body><div class="w3layouts-bg"><h1 class="header-w3ls">Error Page</h1><div class="agileits-content"><h2>' . $errorcode . '</h2></div><div class="w3layouts-right"><div class="w3ls-text"><h3>sorry !</h3><h4 class="w3-agileits2">' . $title . '</h4><p>' . $data . '</p><p class="copyright">'. $this->Info(bottom) . '</p></div></div><div class="clearfix"></div></div></body></html>';
		
		return $Page;
		
	}
		/* ---------------------------------------------------- */
	
			//后台404错误页生成
	/* ---------------------------------------------------- */	
	function Admin_ErrorPage($MainParameter, $SubParameter, $FilePath) {
    // 输出前进行HTML实体转义
    $safeMain = htmlspecialchars($MainParameter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeSub = htmlspecialchars($SubParameter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $this -> WriteLog('GET', '加载模块：' . $data[2] . '/' . $data[3] .'<br />实例对象：'. $FilePath . '失败','404.php'); //记录错误日志
    $Page = '<div id="main">
                <header id="header">
                    <h1><span class="sub">模块加载失败！</span></h1>
                </header>
                <div id="content">
                    <p>[ 主参数 ] ' . $safeMain . '<br/>
                       [ 子参数 ] ' . $safeSub . '<br/>
                       [ 错误时间 ] ' . date('Y-m-d H:i:s') . '
                    </p>
                </div>
            </div>';
    return $Page;
}

		/* ---------------------------------------------------- */
		
		//系统错误页生成
	/* ---------------------------------------------------- */	
	
	function Sys_ErrorPage ( $code = 3 ) 
	{
    	// 根据错误码生成对应的错误文本
    	switch($code) //判断错误码
		{
			
			case '503':
				$title = '系统正在维护中！';
				$content = $this->Info('server_infomaction');
				$this -> WriteLog('GET', '访问请求由于 系统维护中 被拦截<br />','error.php');
				break;
			
			case '342':
				$title = '系统数据检查失败';
				$content = '检测到一个或多个核心数据字段丢失，请删除kernl目录下的conf文件，重新安装系统！';
				break;	
		
			case '336:1':
				$title = '操作非法';
				$content = '当前操作不在系统许可范围内，请检查后重试！';
				break;
	
			case '336:2':
				$title = '操作非法';
				$content = 'Session值校验异常，请检查后重试！';
				break;	
		
			case '336:3':
				$title = '操作非法';
				$content = '用户检查失败,系统安装未完成！';
				break;		
		
			case '336:4':
				$title = '路径检查失败';
				$content = '路径检查失败，系统仅支持在一级目录下安装！';
				break;	
		
			case '195':
				$title = '浏览器检查失败！';
				$content = '当前浏览器版本不受支持，请更新浏览器或更换为更安全的Google浏览器';
				break;		
		
			case '330':
				$title = '管理员已禁用留言板';
				$content = '管理员已禁用留言板，如需帮助请与网站管理员联系！&nbsp; &nbsp; <a href="../index.php">返回首页</a>';
				break;	
				
			case '500':
				$title = 'Access Denied';
				$content = '您无权使用所提供的凭据查看此目录或页面。';
				break;
				
			case '701':
				$title = 'Access Denied';
				$content = '当前请求包含违禁字符，已被系统拦截！';
				break;	
				
			case '999':
				$title = 'Access Denied';
				$content = '当前IP已被限制访问！';
				break;			
		
		}
		$time = date("y/m/d H:i:s",time());
		// 修复：Info(bottom) 少写引号（未定义常量告警），并且 $title/$content 是内部常量赋值但同样做转义
		$Page = '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>' . h($title) . '</title></head><body><div id="main"><header id="header"><h1><span class="sub"> ' . h($title) . '</span></h1></header><div id="content"><p>[ 错误原因 ] ' . h($content) . ' <br /> [ 出错时间 ] ' . h($time)  . ' [ 访问IP ] ' . h(isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '') . '</p>----------------------------------------------------------------<p class="copyright">'. h($this->Info('bottom')) . '</p></div></div></html>' ;
		
		return $Page;
		
	}
		/* ---------------------------------------------------- */
		
		//上传大小检查
		/* ---------------------------------------------------- */
		function UploadFrameFix() 
		{
			$UploadFrame = $this->Info('upload_frame');
			if($UploadFrame == NULL ) 
			{
				$sql = "update zd_system_setting set value = 'system;.png、.jpg、.gif;../../images/|module;.zip;../../module/|' where vars = 'upload_frame';";
				if(!$this->query($sql))
				{
					echo 'null';
				}
			} 
		}

		/* ---------------------------------------------------- */
			
		// 上传框架解析，返回数组
		/* ---------------------------------------------------- */
		function UploadFrame() 
		{
			if($this -> Info("upload_frame") == NULL)  //如果为空自动修复							 
			{
				if($this -> UploadFrameFix() == 'null') 
				{
					die("参数读取发生错误，请与管理员联系");
				}
						
			} else {
				
				$pieces = explode("|", $this ->Info("upload_frame"));
				foreach($pieces as $val)
				{
					if($val == NULL)
					{
						break;
					}
						$IndexData = explode(";", $val);
						$uploadData[] = array ($IndexData[0],$IndexData[1],$IndexData[2]);
				}
			
			}
			return $uploadData;
		}

		/* ---------------------------------------------------- */
				
		// 上传框架处理
		/* ---------------------------------------------------- */
		function UploadFrameServicing($method,$data) 
		{
			$ReturnData = $this -> UploadFrame();
			switch($method)
			{
				case 'add':
					$SystemUploadFrame = $this ->Info("upload_frame");
					$sql = "update zd_system_setting set value = '".$SystemUploadFrame.$data."' where vars = 'upload_frame';";
					break;
				
				case 'edit':
					$pieces = explode(";", $data);
					$EditData = str_replace($pieces[0],$ReturnData[$pieces[0]][0],$data); //第一次替换，将数值替换成框架名
					$pieces1 = explode(";", $EditData); //分割替换后的数据
					$ReturnData[$pieces[0]] = array($pieces1[0],$pieces1[1],$pieces1[2]); //写入替换数组
					$SystemUploadFrame = "";
					foreach($ReturnData as $val)  //输出成命令行形式
					{
	
						if(strpos($val[2], "|") === false) //确保单个框架结尾都有分隔符号
						{
							$val[2] = $val[2]."|";
						}
						
						$SystemUploadFrame.= $val[0].";".$val[1].";".$val[2];
					}
					$sql = "update zd_system_setting set value = '".$SystemUploadFrame."' where vars = 'upload_frame';";	
					break;
					
				case 'del':
					$SystemUploadFrame = "";
					unset($ReturnData[$data]);
					foreach ($ReturnData as $value)
					{
	
						$SystemUploadFrame.= $value[0].";".$value[1].";".$value[2]."|";

					}
					$sql = "update zd_system_setting set value = '".$SystemUploadFrame."' where vars = 'upload_frame';";
					break;
					
			}
			
			if(!$this->query($sql))
			{
				return 'failed';
				
			} else {
				
				echo '<script language="JavaScript">window.alert("操作成功！")</script>';
			}
			
		}

		/* ---------------------------------------------------- */
			
		// 模块安装与卸载
		/* ---------------------------------------------------- */
		function ModuleInstall($path) 
		{
			if(!file_exists('../../' . $path . '/install.xml')) 						 
			{

				return "Install Failed！The File Was Not Module.";
						
			} else { //解析install.xhr
				//初始化安装文件中的所有值
				$database = iconv("gb2312","utf-8", file_get_contents('../../' . $path . '/data.sql'));
    			$xml_array = simplexml_load_string(file_get_contents('../../' . $path . '/install.xml')); //将XML中的数据,读取到数组对象中
				//初始化结束，开始处理
				if($database != NULL)  //判断是否读入数据库
				{
					if($xml_array -> Type == 1) //
					{
						$InstallTable = $this -> Info("system_table");
						$InstallSqlData = str_replace("\$InstallSystemTable$",$InstallTable,$database);
						if($xml_array -> Index == 1) //是否存在前台模块
						{
							$InstallIndex = $this -> Info("index_page_all");
							if($InstallIndex != NULL)
							{
								$InstallIndexCount = sizeof(explode("|",$InstallIndex));
							}
							$InstallIndexCount = isset($InstallIndexCount)?$InstallIndexCount:1;
							$InstallSqlData = str_replace("\$InstallSystemIndex$",$InstallIndex,$InstallSqlData);
							$InstallSqlData = str_replace("\$InstallSystemIndexCount$",$InstallIndexCount,$InstallSqlData);
							
						}
						$InstallData = explode(";\r\n",$InstallSqlData);
						foreach ($InstallData as $value) 
						{
							$this -> query($value);
						}
					}
				}
				//数据库安装结束
				//处理文件操作
				include_once('FileUtil.Class.php');
				$FileControl = new FileUtil();
				$FileRootPath = str_replace('\\','/',realpath(dirname(__FILE__).'/../'));
				if($xml_array -> IndexFile != NULL && $xml_array -> Index == 1) //是否存在前台模块文件
				{
					$InstallFile = explode(";",$xml_array -> IndexFile);
					foreach ($InstallFile as $value) 
					{
						$FileControl -> copyDir($FileRootPath . '/' . $path . '/' . $value , $FileRootPath . '/' . $value );
					}
				}
				if($xml_array -> KernlFile != NULL) //是否存在Kernl核心文件
				{
					$InstallFile = explode(";",$xml_array -> KernlFile);
					foreach ($InstallFile as $value) 
					{
						$FileControl -> copyDir($FileRootPath . '/' . $path . '/' . $value , $FileRootPath . '/kernl/' . $value );
					}
				}
				if($xml_array -> ManagerFile != NULL) //是否存在后台管理文件
				{
					$InstallFile = explode(";",$xml_array -> ManagerFile);
					foreach ($InstallFile as $value) 
					{
						$FileControl -> copyDir($FileRootPath . '/' . $path . '/' . $value , $FileRootPath . '/admin/' . $value );
					}
				}
				rename($FileRootPath . '/' . $path . '/' .  'install.xml', $FileRootPath . '/' . $path . '/' . "uninstall.xml"); //执行重命名，将文件修改为卸载
				$FileControl -> createFile($FileRootPath . '/' . $path . '/' . $value . 'Init.Lock');   //创建安装锁
			return "Install Success!";
			}
		}	
		
		function ModuleUninstall($name)
		{
			
		}	
		/* ---------------------------------------------------- */
	
			
		//WebSite Safe Check System(wscs 网站安全检查系统)
		/* ---------------------------------------------------- */	
	
		function WSCS_Check( ) 
		{
			// 修复：①原实现只查 $_GET，POST 完全放行；②不读 defense_wscs 开关；
			//       ③按单字符黑名单误伤正常输入（如括号），改用特征串匹配。
			if (!$this->Defense('wscs')) {
				return;
			}
			$CurrentAddr = HttpsCheck()
				. (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '')
				. (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '')
				. (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '');

			$patterns = array(
				'#<script#i', '#javascript:#i', '#on(error|load|click|mouseover)\s*=#i',
				'#union\s+select#i', '#\bselect\b[\s\S]{0,40}\bfrom\b#i',
				'#\.\./#', '#/etc/passwd#', '#\$\{jndi:#i',
			);

			$bags = array('GET' => $_GET, 'POST' => $_POST);
			foreach ($bags as $bagName => $bag) {
				foreach ($bag as $key => $value) {
					if (!is_string($value) || $value === '') {
						continue;
					}
					foreach ($patterns as $p) {
						if (preg_match($p, $value)) {
							$this->WriteLog('Get', '请求存在违禁内容 [' . $bagName . '.' . $key . ']', $CurrentAddr);
							echo $this->Sys_ErrorPage(701);
							exit;
						}
					}
				}
			}
		}
		/* ---------------------------------------------------- */
		
			
		//取特定部分内容
		/* ---------------------------------------------------- */	
		function getSubstr($str, $leftStr, $rightStr)
		{
    		$left = strpos($str, $leftStr);
    		//echo '左边:'.$left;
    		$right = strpos($str, $rightStr,$left);
    		//echo '<br>右边:'.$right;
    		if($left < 0 or $right < $left) 
			{
				return '';
			}
    		return substr($str, $left + strlen($leftStr), $right-$left-strlen($leftStr));
		}
		/* ---------------------------------------------------- */	
				
			
		//更新软件Logo
		/* ---------------------------------------------------- */	
		function UpdateSoftwareLogo($data)
		{
			if($data != NULL ) 
			{
				$sql = "update zd_config set `softwarelogo`='" . $data . "'";
				if(!$this->query($sql))
				{
					echo $sql;
					echo 'error,' . $this->SystemError() ;
				}
			} 
		}
		/* ---------------------------------------------------- */	
						
			
		//列出目录中的目录和文件
		/* ---------------------------------------------------- */	
		function getFileList3($directory, $throwOnError = false) 
		{        
			$files = array();        
			try {        
				$dir = new DirectoryIterator($directory);        
				} catch (Exception $e) {        
					// 目录不存在/不可读时默认返回空列表，避免整页 Fatal；需要严格报错时传 true
					if ($throwOnError) {
						throw new Exception($directory . ' is not readable');
					}
					return $files;
				}        
			foreach($dir as $file) 
			{        
				if($file->isDot()) continue;
				if($file->isDir()) continue;			
				$files[] = $file->getFileName();        
			}        
			return $files;        
		}       
		/* ---------------------------------------------------- */							
			
		//生成随机字符串
		/* ---------------------------------------------------- */	
		function random_strings( $length_of_string ) 
		{        
			$str_result = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz' ;
			return substr ( str_shuffle ( $str_result ), 0, $length_of_string );	    
		}       
		/* ---------------------------------------------------- */	
					
		//处理软件LOGO图片数据
		/* ---------------------------------------------------- */	
		function UpdateSoftwareLogoData($datatype , $data , $sid ) 
		{        
			if($sid != NULL && $datatype != NULL) 
			{
				if($datatype == 'local')
				{
						$random = $this -> random_strings(5);
						if($data != NULL)
						{
							$sql = "insert into `zd_imgdata` (`type`,`data`,`querycode`,`softwareid`) values ('0','".$data ."','".$random."','".floatval($sid)."')";
							if(!$this->query($sql))
							{
								echo $sql;
								echo 'error,' . $this->SystemError() ;
							}
							$sql = "UPDATE `zd_software` SET `logotype`='0' , `logodata`='".$random."' WHERE id = '".$sid."'";
							if(!$this->query($sql))
							{
								echo $sql;
								echo 'error,' . $this->SystemError() ;
							}
							
						} else {
							$sql = "UPDATE `zd_software` SET `logotype`='0' , `logodata`='' WHERE id = '".$sid."'";
							if(!$this->query($sql))
							{
								echo $sql;
								echo 'error,' . $this->SystemError() ;
							}
							echo '<script>alert("已切换至本地源");</script>';
						}	
				} else {
						$sql = "UPDATE `zd_software` SET `logotype`='1' , `logodata`='".$data."' WHERE id = '".$sid."'";
						if(!$this->query($sql))
						{
							echo $sql;
							echo 'error,' . $this->SystemError() ;
						} 
						$this -> DelSoftwareLogoData(1,'' , $sid );
						echo '<script>alert("操作成功！");</script>';
				}
				
			}     
		} 
		
		function DelSoftwareLogoData($type , $code , $sid ) 
		{        

			if($type != NULL && $sid != NULL) 
			{
				if($type == 0)  //特定
				{
					$sql = "DELETE FROM `zd_imgdata` WHERE (`softwareid`='".floatval($sid)."' AND `querycode`='".$code."')";
					echo $sql;
					if(!$this->query($sql))
					{
						echo $sql;
						echo 'error,' . $this->SystemError() ;
					}
				
					$sql = "UPDATE `zd_software` SET `logotype`=NULL , `logodata`=NULL WHERE id = '".$sid."'";
					if(!$this->query($sql))
					{
						echo $sql;
						echo 'error,' . $this->SystemError() ;
					}
					
				} else {
				
					$sql = "DELETE FROM `zd_imgdata` WHERE (`softwareid`='".floatval($sid)."' AND `type`='0')";
					if(!$this->query($sql))
					{
						echo $sql;
						echo 'error,' . $this->SystemError() ;
					}
				
				}
			}     
		} 
		
		/* ---------------------------------------------------- */	
							
		//处理软件图片数据
		/* ---------------------------------------------------- */	
		function UpdateSoftwareImgData($datatype , $data , $sid ) 
		{        
			if($sid != NULL && $datatype != NULL) 
			{
				if($datatype == 'local')
				{
						$random = $this -> random_strings(5);
						if($data != NULL)
						{
							$sql = "insert into `zd_imgdata` (`type`,`data`,`querycode`,`softwareid`) values ('1','".$data ."','".$random."','".floatval($sid)."')";
							if(!$this->query($sql))
							{
								echo $sql;
								echo 'error,' . $this->SystemError() ;
							}
							$query = $this -> select('zd_software', 'runpic' ,'id = '.floatval($sid) );
							$row = $this -> fetch_array($query);
							$outside =  explode(',', $row['runpic']);
							if($outside != NULL)
							{
								foreach ($outside as $x) 
								{
									if($imglistdata != NULL)
									{
										$imglistdata = $imglistdata . ','.$x;
									} else {
										$imglistdata = $x;
									}
								} 
							} 
							$imglistdata = $imglistdata .','.$random;
							$sql = "UPDATE `zd_software` SET `runpictype`='0' , `runpic`='".$imglistdata."' WHERE id = '".$sid."'";
							if(!$this->query($sql))
							{
								echo $sql;
								echo 'error,' . $this->SystemError() ;
							}
							
						} else {
							$sql = "UPDATE `zd_software` SET `runpictype`='0' , `runpic`='' WHERE id = '".$sid."'";
							if(!$this->query($sql))
							{
								echo $sql;
								echo 'error,' . $this->SystemError() ;
							}
							echo '<script>alert("已切换至本地源");</script>';
						}	
				} else {
						$query = $this -> select('zd_software', 'runpic,runpictype' ,'id = '.floatval($sid) );
						$row = $this -> fetch_array($query);
						if($row['runpic'] != NULL)
						{
							$data =  $row['runpic'] . ',' . $data;
						} 
						$sql = "UPDATE `zd_software` SET `runpictype`='1' , `runpic`='".$data."' WHERE id = '".$sid."'";
						if(!$this->query($sql))
						{
							echo $sql;
							echo 'error,' . $this->SystemError() ;
						} 
						$this -> DelSoftwareImgData(1,'' , $sid );
						echo '<script>alert("操作成功！");</script>';
				}
				
			}     
		} 
		
		function DelSoftwareImgData($type , $code , $sid ) 
		{        

			if($type != NULL && $sid != NULL) 
			{
				switch($type)
				{
					case 1:
						$sql = "DELETE FROM `zd_imgdata` WHERE (`softwareid`='".floatval($sid)."' AND `querycode`='".$code."')";
						if(!$this->query($sql))
						{
							echo $sql;
							echo 'error,' . $this->SystemError() ;
						}
						$query = $this -> select('zd_software', 'runpic' ,'id = '.floatval($sid) );
						$row = $this -> fetch_array($query);
						$outside =  explode(',', $row['runpic']);
						if($outside != NULL)
						{
							foreach ($outside as $x) 
							{
								if($x != $code)
								{
									if($imglistdata != NULL)
									{
										$imglistdata = $imglistdata . ','.$x;
									} else {
										$imglistdata = $x;
									}
								} 
							} 
						} 				
						$sql = "UPDATE `zd_software` SET `runpictype`='0' , `runpic`='".$imglistdata."' WHERE id = '".$sid."'";
						if(!$this->query($sql))
						{
							echo $sql;
							echo 'error,' . $this->SystemError() ;
						}
						break;
					
					case 2:
						$query = $this -> select('zd_software', 'runpic' ,'id = '.floatval($sid) );
						$row = $this -> fetch_array($query);
						$outside =  explode(',', $row['runpic']);
						unset($outside [$code - 1]);
						if($outside != NULL)
						{
							foreach ($outside as $x) 
							{
								if($imglistdata != NULL)
								{
									$imglistdata = $imglistdata . ','.$x;
								} else {
									$imglistdata = $x;
								}
							} 
						} 
						$sql = "UPDATE `zd_software` SET `runpictype`='1' , `runpic`='".$imglistdata."' WHERE id = '".$sid."'";
						if(!$this->query($sql))
						{
							echo $sql;
							echo 'error,' . $this->SystemError() ;
						}
						break;
				}
			}     
		} 
		
		/* ---------------------------------------------------- */	
									
		//Get IP
		/* ---------------------------------------------------- */	
		function Get_IPData($ipdata)
		{
			if($ipdata != NULL)
			{
				$pattern = '/((2(5[0-5]|[0-4]\d))|[0-1]?\d{1,2})(\.((2(5[0-5]|[0-4]\d))|[0-1]?\d{1,2})){3}/'  ;
				if(!preg_match($pattern, $ipdata))
				{
					echo '不是一个有效的ip地址！';
					exit;
				}
				$IPQuery = new IPQueryEngine(ROOT_PATH . "kernl/ipdata.dat");
				$detail = $IPQuery -> getDetail($ipdata); // 调用查询函数
				//print_r($detail);
				return (isset($detail["dataA"]) ? $detail["dataA"] : "") . (isset($detail["dataB"]) ? $detail["dataB"] : "");// 输出查询结果
			}
		}

		function Get_UserIP()
		{
				$IPQuery = new IPQueryEngine(ROOT_PATH . "kernl/ipdata.dat");
				$detail = $IPQuery -> ip(); // 调用查询函数
				//print_r($detail);
				return $detail;// 输出查询结果
		}

		function Get_IPVersion()
		{
				$IPQuery = new IPQueryEngine(ROOT_PATH . "kernl/ipdata.dat");
				$detail = $IPQuery -> getVersion(); // 调用查询函数
				//print_r($detail);
				return $detail;// 输出查询结果
		}
		
		/* ---------------------------------------------------- */	
									
		//Loading Bing Background（改用内核公共接口 BingWallpaper，模板/页面可直接调用）
		/* ---------------------------------------------------- */	
		function LoadingBingBackground()
		{
			if (class_exists('BingWallpaper')) {
				return BingWallpaper::style();
			}
			return '';
		}
		
		/* ---------------------------------------------------- */	
									
		//处理下载数更新
		/* ---------------------------------------------------- */	
		function UpdateSoftwareDownloadCount($sid) 
		{  
			$query = $this -> select('zd_software', 'downloadcount' ,'id = '.floatval($sid) );
			$row = $this -> fetch_array($query); 
			if(floatval($row['downloadcount']) == NULL || floatval($row['downloadcount']) == 0)
			{
				$count = 0 + 1;
				
			} else {
			
				$count = floatval($row['downloadcount']) + 1;
			}
			$sql = "UPDATE `zd_software` SET `downloadcount`='" . $count . "'  WHERE id = '".$sid."'";
			if(!$this->query($sql))
			{
				echo $sql;
				echo 'error,' . $this->SystemError() ;
				
			} else {
			
				echo '<script>alert("操作成功！");</script>';
			} 
			
		}   
		/* ---------------------------------------------------- */	
									
		//获取可查询数量
		/* ---------------------------------------------------- */	
		function GetSearchCount($table) 
		{
			$allowedTables = ['zd_type', 'zd_softwaretype', 'zd_softwarelibrary', 'zd_process_list', 'zd_mac_addr', 'zd_filetypetable', 'zd_device', 'zd_device_name'];
			if (!in_array($table, $allowedTables)) 
			{
				error_log("Attempted access to invalid table: $table");
				return 0;
			}
    
			$table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
			$sql = "SELECT COUNT(*) FROM `$table`";
    
			try 
			{
				$result = $this->query($sql);
					if (!$result) 
					{
						throw new Exception("Query failed: " . $this->SystemError());
					}
        
				$row = $this->fetch_array($result);
				return (int)($row[0] ?? 0);
			} catch (Exception $e) 
			{
				error_log("Database error: " . $e->getMessage());
				return 0;
		}
		}
		/* ---------------------------------------------------- */	
		
	//系统设置表信息
	/* ---------------------------------------------------- */
	function SearcType() {
		$input = Info('openSearch');
		$options = [];
		foreach (explode(';', $input) as $pair) 
		{
			if (preg_match('/^([^\|]+)\|(.+)$/', trim($pair), $matches)) 
			{
				$value = strtolower(trim($matches[1]));
				$text = htmlspecialchars(trim($matches[2]));
				$options[] = "<option value=\"$value\">$text</option>";
			}
		}
 
		echo implode("\n", $options);
	}	
	/* ---------------------------------------------------- */		

}		
?>