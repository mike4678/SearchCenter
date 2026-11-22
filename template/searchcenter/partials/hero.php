<?php
/* 首屏标题 + 搜索框 + 快捷示例（片段，由 controller.php 引入；禁止直访） */
if (!defined('IN_SEARCHCENTER')) {
    http_response_code(404);
    exit;
}
$chips = array(
    array('label' => 'MAC',  'kw' => '00-1A-2B-3C-4D-5E'),
    array('label' => 'IP',   'kw' => '8.8.8.8'),
    array('label' => '进程', 'kw' => 'svchost.exe'),
    array('label' => '后缀', 'kw' => 'jpg'),
    array('label' => '软件', 'kw' => '微信'),
    array('label' => '文件头', 'kw' => '4D5A'),
);
?>
<p class="sc-eyebrow">MAC · IP · PROCESS · FILETYPE · SOFTWARE</p>
<h1 class="sc-title"><?php echo h($sc->setting('searchcenter_title', $sc->setting('corp', 'Source'))); ?> 查询中心</h1>
<p class="sc-sub"><?php echo h($sc->setting('searchcenter_subtitle', 'MAC 地址 / IP 归属地 / 进程安全 / 文件后缀 一站式查询')); ?></p>

<div class="sc-search">
    <svg class="sc-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="11" cy="11" r="7"></circle><line x1="16.5" y1="16.5" x2="21" y2="21"></line>
    </svg>
    <input type="text" id="sc-keyword" autocomplete="off" spellcheck="false"
           placeholder="输入 MAC、IP、进程名、后缀名、文件头或软件名，回车即查">
    <button type="button" class="sc-search-btn" id="sc-search" aria-label="查询">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
             stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"></circle><line x1="15.5" y1="15.5" x2="20.5" y2="20.5"></line></svg>
    </button>
</div>

<div class="sc-chips">
    <?php foreach ($chips as $c): ?>
    <button type="button" class="sc-chip" data-kw="<?php echo h($c['kw']); ?>">
        <b><?php echo h($c['label']); ?></b><?php echo h($c['kw']); ?>
    </button>
    <?php endforeach; ?>
</div>

<?php if ($notice !== ''): ?>
<div class="sc-notice"><?php echo h($notice); ?></div>
<?php endif; ?>
