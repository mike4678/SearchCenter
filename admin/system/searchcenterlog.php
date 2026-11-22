<?php
/* ----------------------------------------------------
 * 查询中心 · 关键词管理（searchcenter 模板后台页）
 * 记录前台每次查询的关键词与搜索类型，可按类型/关键词/结果筛选，支持删除与清空。
 * 数据表：zd_search_log（模板安装时自动创建，卸载时随清单删除）。
 * ---------------------------------------------------- */
if (!defined('source')) {
    header("Location: ../login.php");
    exit;
}

require_once ROOT_PATH . 'kernl/searchcenter/SearchCenter.class.php';
$sc = new SearchCenter($db, $dou);

$notice = '';
$noticeType = 'success';

/* ---------- 删除 / 清空 ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['act'])) {
    if (!sm_csrf_ok()) {
        $notice = '请求已过期（CSRF 校验失败），请刷新页面后重试。';
        $noticeType = 'danger';
    } else {
        $act = (string)$_POST['act'];
        if ($act === 'del') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $ok = $sc->logDelete($id);
            $dou->OpLog('删除查询关键词记录', '记录编号 ' . $id, $ok ? '成功' : '失败');
            $notice = $ok ? '已删除该记录。' : '删除失败。';
            $noticeType = $ok ? 'success' : 'danger';
        } elseif ($act === 'clear') {
            $ok = $sc->logDelete(0);
            $dou->OpLog('清空查询关键词记录', '全部记录', $ok ? '成功' : '失败');
            $notice = $ok ? '已清空全部关键词记录。' : '清空失败。';
            $noticeType = $ok ? 'success' : 'danger';
        }
    }
}

/* ---------- 筛选条件 ---------- */
$fType   = isset($_GET['stype']) ? trim((string)$_GET['stype']) : '';
$fKw     = isset($_GET['kw']) ? trim((string)$_GET['kw']) : '';
$fResult = isset($_GET['result']) ? (string)$_GET['result'] : '';
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$size    = 20;

$filters = array('type' => $fType, 'keyword' => $fKw, 'result' => $fResult);
list($rows, $total) = $sc->logList($filters, $page, $size);
$pages = max(1, (int)ceil($total / $size));

$typeOptions = $sc->searchTypes();
$queryBase = 'index.php?/search/searchcenterlog&stype=' . urlencode($fType)
    . '&kw=' . urlencode($fKw) . '&result=' . urlencode($fResult) . '&page=';
?>
<div class="col-xs-12">
<div class="box-content">

<?php if ($notice !== ''): ?>
<div class="alert alert-<?php echo h($noticeType); ?>"><?php echo h($notice); ?></div>
<?php endif; ?>

<div class="page-header">
    <h4 style="margin:0 0 10px">关键词管理
        <small style="color:#888">共 <?php echo (int)$total; ?> 条记录</small>
    </h4>
    <p style="color:#888;margin:0 0 12px">
        前台每次查询都会记录「关键词 + 搜索类型 + 是否命中 + 来源 IP + 时间」，用于分析用户实际查询需求。
    </p>
</div>

<form method="get" action="index.php" class="form-inline" style="margin-bottom:14px">
    <input type="hidden" name="/search/searchcenterlog" value="">
    <div class="form-group" style="margin-right:8px">
        <label class="sr-only" for="kw">关键词</label>
        <input type="text" class="form-control" id="kw" name="kw" placeholder="关键词包含…"
               value="<?php echo h($fKw); ?>">
    </div>
    <div class="form-group" style="margin-right:8px">
        <label class="sr-only" for="stype">类型</label>
        <select class="form-control" id="stype" name="stype">
            <option value="">全部类型</option>
            <?php foreach ($typeOptions as $opt): ?>
            <option value="<?php echo h($opt['value']); ?>"<?php echo $fType === $opt['value'] ? ' selected' : ''; ?>>
                <?php echo h($opt['text']); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group" style="margin-right:8px">
        <label class="sr-only" for="result">结果</label>
        <select class="form-control" id="result" name="result">
            <option value="">全部结果</option>
            <option value="1"<?php echo $fResult === '1' ? ' selected' : ''; ?>>有结果</option>
            <option value="0"<?php echo $fResult === '0' ? ' selected' : ''; ?>>无结果 / 失败</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">筛选</button>
    <a class="btn btn-default btn-sm" href="index.php?/search/searchcenterlog">重置</a>
</form>

<form method="post" action="index.php?/search/searchcenterlog" style="margin-bottom:10px"
      onsubmit="return confirm('确定清空全部关键词记录吗？该操作不可恢复。');">
    <?php echo sm_csrf_field(); ?>
    <input type="hidden" name="act" value="clear">
    <button type="submit" class="btn btn-danger btn-sm">清空全部记录</button>
</form>

<table class="table table-bordered table-hover" style="background:#fff">
    <tr>
        <th style="width:60px">编号</th>
        <th>关键词</th>
        <th style="width:150px">搜索类型</th>
        <th style="width:100px">结果</th>
        <th>返回信息</th>
        <th style="width:130px">来源 IP</th>
        <th style="width:150px">时间</th>
        <th style="width:70px">操作</th>
    </tr>
    <?php if (empty($rows)): ?>
    <tr><td colspan="8" style="text-align:center;color:#999;padding:24px">暂无记录</td></tr>
    <?php else: ?>
    <?php foreach ($rows as $row): ?>
    <tr>
        <td><?php echo (int)$row['id']; ?></td>
        <td><?php echo h($row['keyword']); ?></td>
        <td><?php echo h($sc->typeName($row['stype'])); ?>
            <span style="color:#aaa;font-size:12px">(<?php echo h($row['stype']); ?>)</span></td>
        <td>
            <?php if ((int)$row['result'] === 1): ?>
                <span class="label label-success">有结果</span>
            <?php else: ?>
                <span class="label label-default">无结果</span>
            <?php endif; ?>
        </td>
        <td style="color:#888"><?php echo h($row['message']); ?></td>
        <td><?php echo h($row['ip']); ?></td>
        <td><?php echo h($row['addtime']); ?></td>
        <td>
            <form method="post" action="index.php?/search/searchcenterlog" style="display:inline"
                  onsubmit="return confirm('确定删除这条记录吗？');">
                <?php echo sm_csrf_field(); ?>
                <input type="hidden" name="act" value="del">
                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                <button type="submit" class="btn btn-default btn-xs">删除</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
</table>

<?php if ($pages > 1): ?>
<ul class="pagination pagination-sm">
    <?php
    $start = max(1, $page - 3);
    $end   = min($pages, $page + 3);
    ?>
    <?php if ($page > 1): ?>
    <li><a href="<?php echo h($queryBase . ($page - 1)); ?>">上一页</a></li>
    <?php endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
    <li<?php echo $i === $page ? ' class="active"' : ''; ?>>
        <a href="<?php echo h($queryBase . $i); ?>"><?php echo $i; ?></a>
    </li>
    <?php endfor; ?>
    <?php if ($page < $pages): ?>
    <li><a href="<?php echo h($queryBase . ($page + 1)); ?>">下一页</a></li>
    <?php endif; ?>
    <li><span style="color:#888">第 <?php echo (int)$page; ?> / <?php echo (int)$pages; ?> 页</span></li>
</ul>
<?php endif; ?>

<div class="alert alert-info" style="margin-top:14px">
    关键词日志表：<code>zd_search_log</code>；
    <?php
    $exists = $dou->query("SHOW TABLES LIKE 'zd_search_log'");
    $hasTable = $exists && (int)$dou->num_rows($exists) > 0;
    ?>
    <?php echo $hasTable ? '已创建，前台查询会自动写入。' : '当前不存在，请重新安装 searchcenter 模板（安装时自动建表）。'; ?>
</div>

</div>
</div>
