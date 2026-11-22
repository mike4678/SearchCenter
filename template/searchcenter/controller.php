<?php
/* ----------------------------------------------------
 * searchcenter 模板控制器（配合 index.html 使用）
 * 系统前台入口 index.php 渲染纯 HTML 模板前会 include 本文件，
 * 用返回的数组替换 index.html 里的 {key} 占位符：
 *   html_ 前缀 = 可信 HTML 片段，原样输出；其余按标量 h() 转义。
 * 数据全部走系统统一连接 $db / 系统模块 $dou，模板不单独连库。
 * ---------------------------------------------------- */
global $db, $dou;

define('IN_SEARCHCENTER', true);   // 片段文件直访保护

require_once ROOT_PATH . 'kernl/searchcenter/SearchCenter.class.php';

$sc   = new SearchCenter($db, $dou);
$base = $sc->base();

/* ---------- 片段渲染 ---------- */
$partial = function ($name, array $vars) {
    $file = __DIR__ . '/partials/' . $name . '.php';
    if (!is_file($file)) {
        return '';
    }
    extract($vars, EXTR_SKIP);
    ob_start();
    include $file;
    return (string)ob_get_clean();
};

/* ---------- 首屏每日一图：统一走内核 BingWallpaper 接口（未装内核补丁时回退本地图） ---------- */
$heroFallback = rtrim($base, '/') . '/template/searchcenter/assets/img/hero.jpg';
$hero = class_exists('BingWallpaper')
    ? BingWallpaper::get(array('fallback' => $heroFallback))
    : array('url' => $heroFallback, 'title' => '', 'copyright' => '', 'date' => '');
$heroCredit = '';
if (!empty($hero['title']) || !empty($hero['copyright'])) {
    $parts = array();
    if (!empty($hero['title']))     { $parts[] = $hero['title']; }
    if (!empty($hero['copyright'])) { $parts[] = $hero['copyright']; }
    $heroCredit = '每日一图 · ' . implode(' · ', $parts);
}

$types  = $sc->searchTypes();
$notice = $sc->setting('searchcenter_notice', '');

$htmlHero = $partial('hero', array(
    'sc'     => $sc,
    'types'  => $types,
    'notice' => $notice,
));

$htmlResults = $partial('results', array(
    'sc'       => $sc,
    'types'    => $types,
    'listUrl'  => $sc->endpoint('list.php'),
    'aboutUrl' => $sc->endpoint('about.php'),
));

$htmlNavLinks = '<a href="' . h($base . '/') . '">首页</a>'
    . '<a href="#" onclick="javascript:ScAbout();return false;">关于</a>';

$siteName = $sc->setting('searchcenter_title', $sc->setting('corp', $sc->setting('name', 'Source')));
$bottom   = $sc->setting('searchcenter_bottom', $sc->setting('bottom', ''));

return array(
    'page_title'       => $siteName . ' - 查询中心',
    'corp'             => $siteName,
    'bottom'           => $bottom,
    'base'             => $base,
    'api_url'          => $sc->endpoint('search.php'),
    'hero_url'         => $hero['url'],
    'hero_credit'      => $heroCredit,
    'html_hero'        => $htmlHero,
    'html_results'     => $htmlResults,
    'html_nav_links'   => $htmlNavLinks,
    'html_scripts'     => $partial('scripts', array(
        'api'      => $sc->endpoint('search.php'),
        'listUrl'  => $sc->endpoint('list.php'),
        'aboutUrl' => $sc->endpoint('about.php'),
        'softDownloads'   => $sc->downloads('software'),
        'driverDownloads' => $sc->downloads('driver'),
    )),
);
