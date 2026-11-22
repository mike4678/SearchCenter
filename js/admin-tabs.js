/* 后台选项夹（tab）独立实现：
 * 站点同时加载了多个 jQuery 副本，Bootstrap 的 tab 插件未能注册到最终 jQuery 对象上，
 * 导致所有页面的选项夹点击无反应。这里用不依赖 Bootstrap JS 的方式实现同样的交互：
 *   - 导航：.nav-tabs 内的 a[data-toggle="tab"]
 *   - 面板：.tab-content 内的 .tab-panel / .tab-pane
 */
(function () {
    if (window.__adminTabsBound) { return; }
    window.__adminTabsBound = true;

    function activate(link) {
        var $ = window.jQuery;
        if (!$) { return; }
        var target = link.getAttribute('href') || link.getAttribute('data-target') || '';
        if (!target || target.charAt(0) !== '#') { return; }
        var nav = link.parentNode;
        while (nav && !(nav.className && (' ' + nav.className + ' ').indexOf(' nav-tabs ') > -1)) {
            nav = nav.parentNode;
        }
        if (!nav) { return; }

        // 导航高亮
        var items = nav.getElementsByTagName('a'), i;
        for (i = 0; i < items.length; i++) {
            if (items[i] === link) { items[i].className = items[i].className.replace(/\bactive\b/g, '').replace(/\s+/g, ' ').replace(/^\s|\s$/g, '') + ' active'; }
            else { items[i].className = items[i].className.replace(/\bactive\b/g, '').replace(/\s+/g, ' ').replace(/^\s|\s$/g, ''); }
        }

        // 面板显示
        var panel = document.getElementById(target.slice(1));
        if (!panel) { return; }
        var box = panel.parentNode;
        while (box && !(box.className && (' ' + box.className + ' ').indexOf(' tab-content ') > -1)) {
            box = box.parentNode;
        }
        if (!box) { return; }

        var kids = box.children, k;
        for (k = 0; k < kids.length; k++) {
            var el = kids[k];
            if (!el.className) { continue; }
            if (/\btab-panel\b|\btab-pane\b/.test(el.className)) {
                if (el === panel) {
                    el.className = el.className.replace(/^\s|\s$/g, '') + ' active in';
                    el.style.display = 'block';
                } else {
                    el.className = el.className.replace(/\bactive\b/g, '').replace(/\bin\b/g, '').replace(/\s+/g, ' ').replace(/^\s|\s$/g, '');
                    el.style.display = 'none';
                }
            }
        }
    }

    document.addEventListener('click', function (e) {
        var node = e.target;
        while (node && node !== document) {
            if (node.tagName && node.tagName.toLowerCase() === 'a' &&
                node.getAttribute('data-toggle') === 'tab' && node.getAttribute('href')) {
                e.preventDefault();
                activate(node);
                return;
            }
            node = node.parentNode;
        }
    }, true);

    // 初始状态：只显示带 active 的面板
    document.addEventListener('DOMContentLoaded', function () {
        var boxes = document.getElementsByClassName('tab-content'), b, kids, k;
        for (b = 0; b < boxes.length; b++) {
            kids = boxes[b].children;
            for (k = 0; k < kids.length; k++) {
                var el = kids[k];
                if (!el.className || !/\btab-panel\b|\btab-pane\b/.test(el.className)) { continue; }
                if (/\bactive\b/.test(el.className)) { el.style.display = 'block'; }
                else { el.style.display = 'none'; }
            }
        }
    });
})();
