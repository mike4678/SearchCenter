<?php
/* 查询结果面板（片段，由 controller.php 引入；禁止直访） */
if (!defined('IN_SEARCHCENTER')) {
    http_response_code(404);
    exit;
}
?>
<div class="sc-hint" id="sc-hint">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#0b6cff" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>
    无需选择查询类型，输入内容后系统会自动识别并给出结果
</div>

<div class="sc-panel" id="sc-panel-mac">
    <h4>MAC 地址信息</h4>
    <div class="sc-row"><b>查询 MAC：</b><span id="sc-mac-value"></span></div>
    <div class="sc-row"><b>MAC 前缀：</b><span id="sc-mac-oui"></span></div>
    <div class="sc-row"><b>注册厂商：</b><span id="sc-mac-vendor"></span></div>
</div>

<div class="sc-panel" id="sc-panel-ip">
    <h4>IP 归属地</h4>
    <div class="sc-row"><b>查询 IP：</b><span id="sc-ip-value"></span></div>
    <div class="sc-row"><b>归属地：</b><span id="sc-ip-region"></span></div>
    <div class="sc-row"><b>你的 IP：</b><span id="sc-ip-local"></span></div>
</div>

<div class="sc-panel" id="sc-panel-process">
    <h4>进程安全信息</h4>
    <div class="sc-row"><b>进程名：</b><span id="sc-p-name"></span></div>
    <div class="sc-row"><b>文件路径：</b><span id="sc-p-path"></span></div>
    <div class="sc-row"><b>开发公司：</b><span id="sc-p-compy"></span></div>
    <div class="sc-row"><b>安全等级：</b><span id="sc-p-level"></span></div>
    <div class="sc-row"><b>相关说明：</b><span id="sc-p-other"></span></div>
</div>

<div class="sc-panel" id="sc-panel-hz">
    <h4>文件后缀 / 文件头</h4>
    <div class="sc-row"><b>后缀名：</b><span id="sc-hz-type"></span></div>
    <div class="sc-row"><b>文件头：</b><span id="sc-hz-binary"></span></div>
    <div class="sc-row"><b>执行方式：</b><span id="sc-hz-open"></span></div>
    <div class="sc-row"><b>描述：</b><span id="sc-hz-title"></span></div>
</div>

<div class="sc-panel" id="sc-panel-software">
    <h4>软件历史记录</h4>
    <div class="sc-row"><b>软件名：</b><span id="sc-sw-name"></span></div>
    <div class="sc-row"><b>软件类型：</b><span id="sc-sw-type"></span></div>
    <div class="sc-row" id="sc-sw-dl-row" style="display:none"><b>下载链接：</b><span id="sc-sw-dl" class="sc-row-actions"></span></div>
</div>

<div class="sc-panel" id="sc-panel-driver">
    <h4>驱动信息</h4>
    <div class="sc-row"><b>软件名：</b><span id="sc-drv-name"></span></div>
    <div class="sc-row"><b>软件分类：</b><span id="sc-drv-type"></span></div>
    <div class="sc-row"><b>匹配数量：</b><span id="sc-drv-count"></span></div>
    <div class="sc-row" id="sc-drv-dl-row" style="display:none"><b>下载链接：</b><span id="sc-drv-dl" class="sc-row-actions"></span></div>
</div>

<div class="sc-panel" id="sc-panel-multi">
    <h4>多个库都有匹配</h4>
    <div class="sc-row" id="sc-multi-summary"></div>
    <div id="sc-multi-groups"></div>
</div>

<div class="sc-panel" id="sc-panel-list">
    <h4>匹配结果列表</h4>
    <div class="sc-row"><b>查询类型：</b><span id="sc-list-type"></span></div>
    <div class="sc-row"><b>匹配条数：</b><span><em id="sc-list-count" style="font-style:normal;color:#0b57d0;font-weight:600"></em> 条，点击下面的条目查看详情</span></div>
    <div class="sc-list" id="sc-list-items"></div>
</div>

<div class="sc-panel" id="sc-panel-msg">
    <h4>提示</h4>
    <div class="sc-row" id="sc-msg"></div>
</div>
