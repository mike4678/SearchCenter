<?php
/* 查询中心前端脚本（片段，由 controller.php 引入；禁止直访） */
if (!defined('IN_SEARCHCENTER')) {
    http_response_code(404);
    exit;
}
?>
<script>
(function () {
    var API = <?php echo json_encode($api, JSON_UNESCAPED_UNICODE); ?>;
    var LIST_URL = <?php echo json_encode($listUrl, JSON_UNESCAPED_UNICODE); ?>;
    var ABOUT_URL = <?php echo json_encode($aboutUrl, JSON_UNESCAPED_UNICODE); ?>;
    /* 后台配置的固定下载链接：软件、驱动分开，可多条 */
    var SOFT_DLS = <?php echo json_encode(isset($softDownloads) ? $softDownloads : array(), JSON_UNESCAPED_UNICODE); ?>;
    var DRV_DLS = <?php echo json_encode(isset($driverDownloads) ? $driverDownloads : array(), JSON_UNESCAPED_UNICODE); ?>;

    /* 渲染多条下载链接（名称|地址），列表为空则隐藏整行 */
    function setDownloads(rowId, boxId, list) {
        var box = $(boxId).empty();
        if (!list || !list.length) { $(rowId).hide(); return; }
        $.each(list, function (i, it) {
            if (!it || !it.url) { return; }
            if (i > 0) { box.append(document.createTextNode('　')); }
            box.append($('<a target="_blank" rel="noopener" style="color:#18a058"></a>')
                .attr('href', it.url).text(it.label || '下载'));
        });
        $(rowId).show();
    }

    var TYPE_LABEL = {
        mac: 'MAC 地址',
        ip: 'IP 归属地',
        ipv4: 'IP 归属地',
        ipv6: 'IP 归属地',
        process: '进程安全',
        hz: '文件后缀',
        hz_binary: '文件头',
        softhistory: '软件历史',
        software: '软件历史',
        driverquery: '驱动信息',
        driver: '驱动信息'
    };

    var firstResult = false;
    function hideAll() {
        $('.sc-panel').hide();
    }
    function show(id) {
        hideAll();
        $('#sc-hint').hide();
        $(id).show();
        if (!firstResult) {
            firstResult = true;
            var box = document.getElementById('sc-results');
            if (box && box.getBoundingClientRect) {
                var top = box.getBoundingClientRect().top;
                if (top > window.innerHeight - 170) {
                    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }
    }
    function msg(text) {
        $('#sc-msg').text(text);
        show('#sc-panel-msg');
    }

    function jsonPost(data, done) {
        $.ajax({
            url: API,
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (res) { done(null, res); },
            error: function () { done('接口请求失败，请稍后重试'); }
        });
    }

    /* 列表页类型映射：process / hz / software / driver */
    function listType(type) {
        if (type === 'softhistory' || type === 'software') { return 'software'; }
        if (type === 'driverquery' || type === 'driver' || type === 'device') { return 'driver'; }
        if (type === 'hz' || type === 'filetype') { return 'hz'; }
        if (type === 'hz_binary' || type === 'binary') { return 'binary'; }
        if (type === 'process') { return 'process'; }
        return type;
    }

    function typeLabel(type) {
        return TYPE_LABEL[type] || type || '';
    }

    /* 打开某个条目的列表/详情页（原系统行为：多条结果 → 列表页里点条目看详情） */
    function openList(type, kw) {
        var lt = listType(type);
        // 文件头多条时，点条目是按后缀名看详情，不是再按十六进制查
        if (lt === 'binary') { lt = 'hz'; }
        var url = LIST_URL + '?type=' + encodeURIComponent(lt)
            + '&kw=' + encodeURIComponent(kw);
        if (window.art && art.dialog) {
            art.dialog.open(url, { title: '查询结果列表', width: 720, height: 480, lock: true });
        } else {
            window.open(url, '_blank');
        }
    }

    /* 多条结果：在主面板里列出所有条目，点哪条就进哪条的列表/详情页 */
    function renderList(type, kw, items, count) {
        var total = count || items.length;
        $('#sc-list-count').text(total);
        $('#sc-list-type').text(typeLabel(type));
        var box = $('#sc-list-items').empty();
        if (!items.length) {
            box.append($('<p class="sc-row"></p>').text('没有可显示的结果'));
        }
        $.each(items, function (i, it) {
            var label = it.label || it.value || '';
            if (!label) { return; }
            var a = $('<a href="#" class="sc-item"></a>').text(label);
            a.attr('data-value', it.value || label);
            a.on('click', function (e) {
                e.preventDefault();
                openList(type, $(this).attr('data-value'));
            });
            box.append(a);
        });
        show('#sc-panel-list');
    }

    /* 通过列表接口抓取条目（后缀名这类接口不返回明细数组时用） */
    function listItems(type, kw, done) {
        $.ajax({
            url: LIST_URL + '?type=' + encodeURIComponent(listType(type))
                + '&kw=' + encodeURIComponent(kw) + '&json=1',
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                done((res && res.items) ? res.items : []);
            },
            error: function () { done([]); }
        });
    }

    function renderMac(res, kw) {
        $('#sc-mac-value').text(res.mac || kw);
        $('#sc-mac-oui').text(res.oui || '');
        $('#sc-mac-vendor').text(res.vendor || '');
        show('#sc-panel-mac');
    }

    function renderIp(res, kw) {
        $('#sc-ip-value').text(res.ip || kw);
        $('#sc-ip-region').text(res.region || '');
        $('#sc-ip-local').text(res.localIP || '');
        show('#sc-panel-ip');
    }

    function renderProcess(res, kw) {
        if (res.status === 5 && res.items && res.items.length) {
            var items = [];
            $.each(res.items, function (i, r) {
                items.push({ label: (r.p_name || '') + (r.p_level ? '（' + r.p_level + '）' : ''), value: r.p_name || '' });
            });
            return renderList('process', kw, items, res.count);
        }
        $('#sc-p-name').text(res.p_name || kw);
        $('#sc-p-path').text(res.p_path || '');
        $('#sc-p-compy').text(res.regcompy || '');
        $('#sc-p-level').text(res.p_level || '');
        $('#sc-p-other').text(res.p_other || '');
        show('#sc-panel-process');
    }

    function renderHz(res, kw, type) {
        if (res.status === 5) {
            // 多条：按文件后缀 / 文件头分别重新拉取列表
            return listItems(type || 'hz', kw, function (items) {
                renderList(type || 'hz', kw, items, res.count);
            });
        }
        $('#sc-hz-type').text(res.filetype || kw);
        $('#sc-hz-binary').text(res.binarydata || '');
        $('#sc-hz-open').text(res.open || '');
        $('#sc-hz-title').text(res.title || '');
        show('#sc-panel-hz');
    }

    function renderSoftware(res, kw) {
        var data = res.Data || [];
        if (data.length > 1) {
            var items = [];
            $.each(data, function (i, r) {
                items.push({ label: r.softname || '', value: r.softname || '' });
            });
            return renderList('softhistory', kw, items, res.count || data.length);
        }
        var row = data.length ? data[0] : res;
        $('#sc-sw-name').text(row.softname || kw);
        $('#sc-sw-type').text(row.softtype || '');
        setDownloads('#sc-sw-dl-row', '#sc-sw-dl', (res.downloads && res.downloads.length) ? res.downloads : SOFT_DLS);
        show('#sc-panel-software');
    }

    /* 多库命中：按库分组列出，点条目进入对应库的完整列表 */
    function renderMulti(res, kw) {
        var groups = res.groups || [];
        $('#sc-multi-summary').text((res.message || '多个库都有匹配') + '，共 ' + (res.count || 0) + ' 条，点击条目查看详情');
        var box = $('#sc-multi-groups').empty();
        $.each(groups, function (i, g) {
            var sec = $('<div class="sc-group"></div>');
            var head = $('<div class="sc-group-head"></div>');
            head.append($('<span></span>').css({ 'font-size': '14px', 'font-weight': '600', 'color': '#152238' })
                .text(g.label || g.type));
            head.append($('<span class="sc-badge"></span>').text((g.count || 0) + ' 条'));
            sec.append(head);
            var list = $('<div class="sc-list"></div>').css({ 'max-height': '200px' });
            $.each(g.items || [], function (j, it) {
                var row = $('<div class="sc-item-row"></div>');
                var a = $('<a href="#" class="sc-item" style="padding:3px 6px"></a>')
                    .text(it.label || it.value || '');
                a.attr('data-value', it.value || it.label || '');
                a.on('click', function (e) {
                    e.preventDefault();
                    openList(g.type, $(this).attr('data-value'));
                });
                row.append(a);
                var dls = it.urls || (g.type === 'softhistory' ? SOFT_DLS
                    : (g.type === 'driverquery' ? DRV_DLS : []));
                $.each(dls || [], function (k, d) {
                    if (!d || !d.url) { return; }
                    row.append($('<a target="_blank" rel="noopener" style="margin-left:8px;font-size:13px;color:#18a058"></a>')
                        .attr('href', d.url).text(d.label || '下载'));
                });
                list.append(row);
            });
            if (g.more) {
                list.append($('<p class="sc-row"></p>')
                    .text('仅显示前 ' + (g.items || []).length + ' 条，点任一条目可进入该库的完整列表'));
            }
            sec.append(list);
            box.append(sec);
        });
        show('#sc-panel-multi');
    }

    function renderDriver(res, kw) {
        var data = res.Data || [];
        if (data.length > 1) {
            var items = [];
            $.each(data, function (i, r) {
                items.push({ label: r.softname || '', value: r.softname || '' });
            });
            return renderList('driverquery', kw, items, res.count || data.length);
        }
        var row = data.length ? data[0] : {};
        $('#sc-drv-name').text(row.softname || kw);
        $('#sc-drv-type').text(row.softtype || '');
        $('#sc-drv-count').text(res.count || 0);
        setDownloads('#sc-drv-dl-row', '#sc-drv-dl', (res.downloads && res.downloads.length) ? res.downloads : DRV_DLS);
        show('#sc-panel-driver');
    }

    /* 一个入口搞定：后端按内容自动判断类型并返回 type，前端只负责渲染 */
    function queryAuto(kw) {
        jsonPost({ type: 'auto', keyword: kw }, function (err, res) {
            if (err) { return msg(err); }
            if (!res.status && res.status !== 0) { return msg(res.message || '查询无果'); }
            var t = res.type || 'process';
            if (t === 'multi') { return renderMulti(res, kw); }
            if (t === 'mac') { return renderMac(res, kw); }
            if (t === 'ip' || t === 'ipv4' || t === 'ipv6' || t === 'ip_data') { return renderIp(res, kw); }
            if (t === 'process' || t === 'process_data') { return renderProcess(res, kw); }
            if (t === 'hz' || t === 'filetype' || t === 'hz_binary' || t === 'binary') { return renderHz(res, kw, t); }
            if (t === 'softhistory' || t === 'software') { return renderSoftware(res, kw); }
            if (t === 'driverquery' || t === 'driver' || t === 'device') { return renderDriver(res, kw); }
            msg(res.message || '查询无果');
        });
    }

    $(document).on('click', '#sc-search', function () {
        var kw = $.trim($('#sc-keyword').val());
        if (!kw) { return msg('请输入查询内容'); }
        queryAuto(kw);
    });

    $(document).on('keydown', '#sc-keyword', function (e) {
        if (e.which === 13) { $('#sc-search').click(); }
    });

    /* 首屏快捷示例：点一下就填进输入框并查询 */
    $(document).on('click', '.sc-chip', function () {
        var kw = $(this).attr('data-kw') || '';
        if (!kw) { return; }
        $('#sc-keyword').val(kw);
        queryAuto(kw);
    });

    window.ScAbout = function () {
        if (window.art && art.dialog) {
            art.dialog.open(ABOUT_URL, { title: '关于查询中心', width: 720, height: 420, lock: true });
        } else {
            window.open(ABOUT_URL, '_blank');
        }
    };
})();
</script>
