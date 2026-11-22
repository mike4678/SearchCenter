<?php
/* IP 防火墙拦截提示页（被拦截的请求跳转到这里；本页不加载内核，避免二次拦截） */
if (!headers_sent()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
}
$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>访问被拒绝</title>
<style>
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
         font-family:system-ui,-apple-system,"Microsoft YaHei",sans-serif;background:#f5f6fa;color:#333}
    .box{max-width:560px;padding:42px 34px;text-align:center;background:#fff;border-radius:10px;
         box-shadow:0 8px 30px rgba(0,0,0,.08)}
    .icon{font-size:44px;line-height:1}
    h2{margin:16px 0 10px;font-size:22px}
    p{color:#666;line-height:1.9;margin:0}
    .ip{margin-top:14px;font-size:13px;color:#999}
</style>
</head>
<body>
<div class="box">
    <div class="icon">🚫</div>
    <h2>访问被拒绝</h2>
    <p>您的访问请求已被站点的 IP 防火墙拦截。<br>如果这是误判，请联系站点管理员将您的 IP 加入放行名单。</p>
    <?php if ($ip !== ''): ?>
    <div class="ip">您的 IP：<?php echo htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
</div>
</body>
</html>
