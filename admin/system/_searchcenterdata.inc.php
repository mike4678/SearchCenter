<?php
/* ----------------------------------------------------
 * 查询中心 · 数据管理公共逻辑（由各独立子页面 include）
 * 依赖：$scMod（mac|process|filetype|software|device）、$scRoute（路由名）
 * 仅操作本模板自有数据表，不触碰系统表。
 * ---------------------------------------------------- */
if (!defined('source')) {
    header("Location: ../login.php");
    exit;
}
if (!isset($scMod) || !isset($scRoute)) {
    exit('缺少模块参数');
}

require_once ROOT_PATH . 'kernl/searchcenter/SearchCenter.class.php';
$sc = new SearchCenter($db, $dou);

$modules = $sc->datasets();
if (!isset($modules[$scMod])) {
    echo $dou->Admin_ErrorPage(isset($addr[1]) ? $addr[1] : '', isset($addr[2]) ? $addr[2] : '', __FILE__);
    exit;
}
$def = $modules[$scMod];

/* 模块与独立页面/路由的对应关系 */
$modRoutes = array(
    'mac'      => 'searchcentermac',
    'process'  => 'searchcenterprocess',
    'filetype' => 'searchcenterfiletype',
    'software' => 'searchcentersoftware',
    'device'   => 'searchcenterdevice',
);

$notice = '';
$noticeType = 'success';

/* ---------- 新增 / 删除 / 导入 ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['act'])) {
    if (!sm_csrf_ok()) {
        $notice = '请求已过期（CSRF 校验失败），请刷新页面后重试。';
        $noticeType = 'danger';
    } else {
        $act = (string)$_POST['act'];
        if ($act === 'add') {
            $input = array();
            foreach ($def['fields'] as $field) {
                $input[$field] = isset($_POST['f_' . $field]) ? (string)$_POST['f_' . $field] : '';
            }
            list($ok, $msg) = $sc->dataAdd($scMod, $input);
            $dou->OpLog('新增查询中心数据', $def['title'] . '：' . $input[$def['fields'][0]], $ok ? '成功' : '失败');
            $notice = $msg;
            $noticeType = $ok ? 'success' : 'danger';
        } elseif ($act === 'del') {
            $id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $value = isset($_POST['value']) ? (string)$_POST['value'] : '';
            list($ok, $msg) = $sc->dataDelete($scMod, $id, $value);
            $dou->OpLog('删除查询中心数据', $def['title'] . '：' . ($value !== '' ? $value : '编号 ' . $id), $ok ? '成功' : '失败');
            $notice = $msg;
            $noticeType = $ok ? 'success' : 'danger';
        } elseif ($act === 'import' && $scMod === 'mac') {
            $file = isset($_FILES['ouifile']) ? $_FILES['ouifile'] : null;
            if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
                $notice = '请选择要导入的 OUI 文本文件（.txt）。';
                $noticeType = 'danger';
            } else {
                $size = isset($file['size']) ? (int)$file['size'] : 0;
                if ($size <= 0 || $size > 20 * 1024 * 1024) {
                    $notice = '文件大小不合法（限 20MB 以内）。';
                    $noticeType = 'danger';
                } else {
                    $content = @file_get_contents($file['tmp_name']);
                    if ($content === false || $content === '') {
                        $notice = '读取上传文件失败。';
                        $noticeType = 'danger';
                    } else {
                        list($added, $skipped, $err) = $sc->importOui($content);
                        if ($err !== '') {
                            $notice = '导入中断：' . $err . '（已写入 ' . $added . ' 条，跳过 ' . $skipped . ' 条）';
                            $noticeType = 'danger';
                        } else {
                            $notice = '导入完成：新增 ' . $added . ' 条，已存在跳过 ' . $skipped . ' 条。';
                            $noticeType = $added > 0 ? 'success' : 'warning';
                        }
                        $dou->OpLog('导入 OUI 地址库', '新增 ' . $added . ' 条，跳过 ' . $skipped . ' 条', $err === '' ? '成功' : '失败');
                    }
                }
            }
        }
    }
}

/* ---------- 列表 ---------- */
$kw   = isset($_GET['kw']) ? trim((string)$_GET['kw']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$size = 20;
list($rows, $total, $listError) = $sc->dataList($scMod, $kw, $page, $size);
$pages = max(1, (int)ceil($total / $size));
$routeUrl = 'index.php?/search/' . $scRoute;
$baseUrl  = $routeUrl . '&kw=' . urlencode($kw) . '&page=';
?>
<div class="col-xs-12">
<div class="box-content">

<?php if ($notice !== ''): ?>
<div class="alert alert-<?php echo h($noticeType); ?>"><?php echo h($notice); ?></div>
<?php endif; ?>

<div class="page-header" style="margin-bottom:10px">
    <h4 style="margin:0 0 8px"><?php echo h($def['title']); ?>
        <small style="color:#888">共 <?php echo (int)$total; ?> 条</small>
    </h4>
</div>

<ul class="nav nav-pills" style="margin-bottom:14px">
    <?php foreach ($modRoutes as $mk => $mroute): ?>
    <?php if (!isset($modules[$mk])) { continue; } ?>
    <li<?php echo $mk === $scMod ? ' class="active"' : ''; ?> style="margin-right:6px">
        <a href="index.php?/search/<?php echo h($mroute); ?>"><?php echo h($modules[$mk]['title']); ?></a>
    </li>
    <?php endforeach; ?>
    <li style="margin-right:6px"><a href="index.php?/search/searchcenterlog">关键词记录</a></li>
</ul>

<?php if ($listError !== ''): ?>
<div class="alert alert-warning"><?php echo h($listError); ?></div>
<?php endif; ?>

<!-- 筛选 -->
<form method="get" action="index.php" class="form-inline" style="margin-bottom:12px">
    <input type="hidden" name="<?php echo h('/search/' . $scRoute); ?>" value="">
    <div class="form-group" style="margin-right:8px">
        <input type="text" class="form-control" name="kw" placeholder="按<?php echo h(implode(' / ', $def['search'])); ?>筛选"
               value="<?php echo h($kw); ?>">
    </div>
    <button type="submit" class="btn btn-primary btn-sm">搜索</button>
    <a class="btn btn-default btn-sm" href="<?php echo h($routeUrl); ?>">重置</a>
</form>

<!-- 新增 -->
<form method="post" class="form-inline" style="margin-bottom:10px;padding:10px;background:#fafbfd;border:1px solid #eef1f6;border-radius:6px">
    <?php echo sm_csrf_field(); ?>
    <input type="hidden" name="act" value="add">
    <?php foreach ($def['fields'] as $field): ?>
    <div class="form-group" style="margin:0 8px 6px 0">
        <label style="display:block;font-size:12px;color:#888;margin-bottom:4px"><?php echo h($def['labels'][$field]); ?></label>
        <?php if ($field === 'binarydata' || $field === 'other' || $field === 'path'): ?>
        <input type="text" class="form-control input-sm" name="f_<?php echo h($field); ?>" style="width:260px"
               placeholder="<?php echo h($def['labels'][$field]); ?>">
        <?php elseif ($field === 'safevalue'): ?>
        <select class="form-control input-sm" name="f_<?php echo h($field); ?>">
            <option value="5">5 · 安全</option>
            <option value="4">4 · 较安全</option>
            <option value="3" selected>3 · 未知</option>
            <option value="2">2 · 可疑</option>
            <option value="1">1 · 危险</option>
        </select>
        <?php else: ?>
        <input type="text" class="form-control input-sm" name="f_<?php echo h($field); ?>" style="width:200px"
               placeholder="<?php echo h($def['labels'][$field]); ?>">
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-success btn-sm">新增</button>
</form>

<?php if ($scMod === 'mac'): ?>
<form method="post" enctype="multipart/form-data"
      style="margin-bottom:14px;padding:10px;background:#fafbfd;border:1px dashed #cfe0ff;border-radius:6px">
    <?php echo sm_csrf_field(); ?>
    <input type="hidden" name="act" value="import">
    <label style="font-size:12px;color:#888;margin-right:8px">导入 IEEE OUI / MA-L 文本</label>
    <input type="file" name="ouifile" accept=".txt,text/plain" style="display:inline-block;margin-right:8px">
    <button type="submit" class="btn btn-info btn-sm"
            onclick="return confirm('确定导入该 OUI 文件吗？已存在的 OUI 会自动跳过。');">开始导入</button>
    <p style="color:#888;font-size:12px;margin:8px 0 0 0">
        支持 IEEE 导出格式：<code>28-6F-B9 (hex) Organization</code> 与 <code>286FB9 (base 16) Organization</code>，
        以 <code>(base 16)</code> 行的 6 位十六进制作为 OUI 写入，同一 OUI 只保留一条。
    </p>
</form>
<?php endif; ?>

<!-- 列表 -->
<table class="table table-bordered table-hover" style="background:#fff">
    <tr>
        <?php if ($def['pk'] !== ''): ?><th style="width:64px">编号</th><?php endif; ?>
        <?php foreach ($def['fields'] as $field): ?>
        <th><?php echo h($def['labels'][$field]); ?></th>
        <?php endforeach; ?>
        <th style="width:70px">操作</th>
    </tr>
    <?php if (empty($rows)): ?>
    <tr><td colspan="<?php echo count($def['fields']) + ($def['pk'] !== '' ? 2 : 1); ?>"
            style="text-align:center;color:#999;padding:24px">暂无数据</td></tr>
    <?php else: ?>
    <?php foreach ($rows as $row): ?>
    <tr>
        <?php if ($def['pk'] !== ''): ?><td><?php echo (int)$row[$def['pk']]; ?></td><?php endif; ?>
        <?php foreach ($def['fields'] as $field): ?>
        <td style="<?php echo in_array($field, array('binarydata', 'path', 'other'), true) ? 'word-break:break-all;max-width:420px' : ''; ?>">
            <?php echo h(isset($row[$field]) ? $row[$field] : ''); ?>
        </td>
        <?php endforeach; ?>
        <td>
            <form method="post" action="<?php echo h($routeUrl); ?>"
                  style="display:inline" onsubmit="return confirm('确定删除这条记录吗？');">
                <?php echo sm_csrf_field(); ?>
                <input type="hidden" name="act" value="del">
                <?php if ($def['pk'] !== ''): ?>
                <input type="hidden" name="id" value="<?php echo (int)$row[$def['pk']]; ?>">
                <?php else: ?>
                <input type="hidden" name="value" value="<?php echo h($row[$def['fields'][0]]); ?>">
                <?php endif; ?>
                <button type="submit" class="btn btn-default btn-xs">删除</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
</table>

<?php if ($pages > 1): ?>
<ul class="pagination pagination-sm">
    <?php $start = max(1, $page - 3); $end = min($pages, $page + 3); ?>
    <?php if ($page > 1): ?><li><a href="<?php echo h($baseUrl . ($page - 1)); ?>">上一页</a></li><?php endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
    <li<?php echo $i === $page ? ' class="active"' : ''; ?>><a href="<?php echo h($baseUrl . $i); ?>"><?php echo $i; ?></a></li>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><li><a href="<?php echo h($baseUrl . ($page + 1)); ?>">下一页</a></li><?php endif; ?>
    <li><span style="color:#888">第 <?php echo (int)$page; ?> / <?php echo (int)$pages; ?> 页</span></li>
</ul>
<?php endif; ?>

<div class="alert alert-info" style="margin-top:12px">
    当前操作的数据表：<code><?php echo h($def['table']); ?></code>。
    删除只影响该表本模板自有数据，不会改动系统表；卸载模板时该表随卸载清单一起删除。
    <?php if ($scMod === 'mac'): ?>
    <br>MAC 库为 IEEE OUI/MA-L 数据：OUI 为 6 位十六进制（不含分隔符），Organization 为厂商名。
    <?php elseif ($scMod === 'device'): ?>
    <br>驱动库用于前台「驱动查询」，字段为软件名 + 软件分类；前台按软件名模糊匹配。
    <?php endif; ?>
</div>

</div>
</div>
