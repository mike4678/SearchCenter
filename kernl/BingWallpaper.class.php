<?php
/* ----------------------------------------------------
 * BingWallpaper —— Bing 每日一图公共接口（内核级）
 *
 * 其他模板 / 页面直接调用即可，不需要各自再写一遍抓取逻辑：
 *   $bg = BingWallpaper::get();                 // 数组：url/title/copyright/date/local
 *   $bg = BingWallpaper::get(array('fallback' => '/template/xxx/assets/img/hero.jpg'));
 *   BingWallpaper::url();                       // 只取图片地址
 *   BingWallpaper::css();                        // "background:url(...) center/cover no-repeat fixed;"
 *   BingWallpaper::style();                      // "<style>body{...}</style>"
 *
 * 特性：
 *   - 按本地日期取图，跨天强制刷新；图片保存到 images/bing/YYYYMM/DD.jpg
 *   - 元数据缓存 6 小时（kernl/cache/bing_wallpaper.json），跨天后不再直接使用旧缓存
 *   - 接口按 HTTP/HTTPS 多线路尝试，避免单一域名或 TLS 故障导致长期不更新
 *   - 支持 fallback：接口与缓存都拿不到时返回调用方给的本地图
 *   - 只接受 http/https 地址，输出前做转义
 * ---------------------------------------------------- */
class BingWallpaper
{
    const CACHE_TTL = 21600;   // 6 小时
    const DAILY_PROXY = 'https://bing.xinac.net/api/today?format=jpg'; // 服务器出网失败时由浏览器直连的每日图代理

    protected static $memo = null;

    /**
     * 读取当日 Bing 壁纸
     * @param array $options mkt / fallback / force
     * @return array
     */
    public static function get($options = array())
    {
        if (self::$memo !== null) {
            return self::$memo;
        }
        $mkt      = isset($options['mkt']) ? (string)$options['mkt'] : 'zh-CN';
        $fallback = isset($options['fallback']) ? (string)$options['fallback'] : '';
        $force    = !empty($options['force']);
        $today    = date('Ymd');
        $archBase = 'HPImageArchive.aspx?format=js&idx=0&n=1&mkt=' . rawurlencode($mkt);

        $empty = array(
            'url'       => $fallback,
            'title'     => '',
            'copyright' => '',
            'date'      => '',
            'local'     => false,
            'cached'    => false,
            'proxy'     => false,
        );

        $cacheFile = self::cacheFile();
        $cached    = self::readCache($cacheFile);
        if ($cached && !empty($cached['local']) && !empty($cached['local_rel'])
            && !is_file(ROOT_PATH . $cached['local_rel'])) {
            $cached = null;   // 缓存指向的本地图已丢失，重新获取
        }
        if (!$force && $cached && !empty($cached['url']) && !empty($cached['time'])) {
            $cachedDay = !empty($cached['day']) ? (string)$cached['day'] : date('Ymd', (int)$cached['time']);
            // 跨天后不再直接使用旧缓存，必须重新请求当天图片
            if ($cachedDay === $today && (time() - (int)$cached['time']) < self::CACHE_TTL) {
                self::$memo = $cached;
                return $cached;
            }
        }

        $raw = '';
        foreach (array(
            'http://www.bing.com/',
            'http://cn.bing.com/',
            'https://www.bing.com/',
            'https://cn.bing.com/',
        ) as $base) {
            $raw = self::http($base . $archBase);
            if ($raw !== '') {
                break;
            }
        }
        $data = $raw !== '' ? json_decode($raw, true) : null;

        if (empty($data['images'][0]['url'])) {
            $cachedDay = ($cached && !empty($cached['time'])
                ? (!empty($cached['day']) ? (string)$cached['day'] : date('Ymd', (int)$cached['time'])) : '');
            if ($cached && !empty($cached['url']) && $cachedDay === $today) {
                $cached['cached'] = true;
                self::$memo = $cached;
                return $cached;
            }

            // 服务器完全出网失败时，不再把旧图当“今天”的图；改由浏览器直连每日图代理
            $proxy = self::DAILY_PROXY;
            $localRel = self::localRel($today);
            $localOk  = self::download($proxy, ROOT_PATH . $localRel);
            $result = array(
                'url'       => $localOk ? self::localUrl($localRel) . '?d=' . $today : $proxy,
                'title'     => '',
                'copyright' => '',
                'date'      => $today,
                'bing_date' => '',
                'day'       => $today,
                'local'     => $localOk,
                'cached'    => false,
                'proxy'     => !$localOk,
                'time'      => time(),
                'remote'    => $proxy,
                'local_rel' => $localOk ? $localRel : '',
            );
            self::writeCache($cacheFile, $result);
            self::$memo = $result;
            return $result;
        }

        $img     = $data['images'][0];
        $remote  = (string)$img['url'];
        if (strpos($remote, 'http') !== 0) {
            $remote = 'https://www.bing.com' . $remote;
        }
        if (!preg_match('#^https?://#i', $remote)) {
            self::$memo = $empty;
            return $empty;
        }

        $startdate = isset($img['startdate']) ? (string)$img['startdate'] : $today;
        // 文件名按本地日期生成，保证每天都会出现新的图片地址；Bing 的 startdate 按 UTC，可能比本地日期晚一天
        $localRel  = self::localRel($today);
        $localOk   = self::download($remote, ROOT_PATH . $localRel);

        $result = array(
            'url'       => $localOk ? self::localUrl($localRel) . '?d=' . $today : self::DAILY_PROXY,
            'title'     => isset($img['title']) ? (string)$img['title'] : '',
            'copyright' => isset($img['copyright']) ? (string)$img['copyright'] : '',
            'date'      => $today,
            'bing_date' => $startdate,
            'day'       => $today,
            'local'     => $localOk,
            'cached'    => false,
            'proxy'     => !$localOk,
            'time'      => time(),
            'remote'    => $remote,
            'local_rel' => $localOk ? $localRel : '',
        );

        self::writeCache($cacheFile, $result);
        self::$memo = $result;
        return $result;
    }

    /** 只取图片地址 */
    public static function url($options = array())
    {
        $bg = self::get($options);
        return isset($bg['url']) ? (string)$bg['url'] : '';
    }

    /** 返回可直接塞进 style 属性的背景声明 */
    public static function css($options = array())
    {
        $url = self::url($options);
        if ($url === '') {
            return '';
        }
        return 'background-image:url("' . self::esc($url) . '");background-position:center center;'
            . 'background-size:cover;background-repeat:no-repeat;background-attachment:fixed;'
            . '/*bing-daily-20261121*/';
    }

    /** 返回 <style> 片段（兼容旧页面直接用 echo 的方式） */
    public static function style($options = array())
    {
        $css = self::css($options);
        return $css === '' ? '' : '<style>body{' . $css . '}</style>';
    }

    /* ---------------- 内部实现 ---------------- */

    protected static function cacheFile()
    {
        $dir = ROOT_PATH . 'kernl/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            $dir = rtrim(sys_get_temp_dir(), '/\\');
        }
        return $dir . '/bing_wallpaper.json';
    }

    protected static function readCache($file)
    {
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    protected static function writeCache($file, array $data)
    {
        @file_put_contents($file, json_encode($data));
    }

    /** 图片相对路径：images/bing/YYYYMM/DD.jpg */
    protected static function localRel($startdate)
    {
        $ymd = preg_match('/^\d{8}$/', (string)$startdate) ? (string)$startdate : date('Ymd');
        $ym  = substr($ymd, 0, 6);
        $d   = substr($ymd, 6, 2);
        return 'images/bing/' . $ym . '/' . $d . '.jpg';
    }

    /** 把相对路径转成站内 URL（自动兼容站点部署在子目录的情况） */
    protected static function localUrl($rel)
    {
        $prefix = '';
        if (defined('ROOT_PATH') && !empty($_SERVER['DOCUMENT_ROOT'])) {
            $docRoot = realpath((string)$_SERVER['DOCUMENT_ROOT']);
            $rootDir = realpath(ROOT_PATH);
            if ($docRoot && $rootDir && strpos($rootDir, $docRoot) === 0) {
                $prefix = str_replace('\\', '/', substr($rootDir, strlen($docRoot)));
            }
        }
        return rtrim($prefix, '/') . '/' . ltrim($rel, '/');
    }

    protected static function download($remote, $target)
    {
        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }
        if (is_file($target) && filesize($target) > 10240) {
            return true;   // 当天已下载过，直接复用
        }
        if (function_exists('curl_init')) {
            $fp = @fopen($target, 'wb');
            if (!$fp) {
                return false;
            }
            $ch = curl_init($remote);
            curl_setopt_array($ch, array(
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 12,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
            ));
            $ok = curl_exec($ch);
            curl_close($ch);
            fclose($fp);
            if ($ok && is_file($target) && filesize($target) > 10240) {
                return true;
            }
            @unlink($target);
            return false;
        }
        $ctx = stream_context_create(array('http' => array(
            'timeout' => 8,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
        ), 'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
        )));
        $raw = @file_get_contents($remote, false, $ctx);
        if ($raw === false || strlen($raw) < 10240) {
            return false;
        }
        return (bool)@file_put_contents($target, $raw);
    }

    protected static function http($url)
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 4,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT      => $ua,
            ));
            $raw = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($raw !== false && $code >= 200 && $code < 400) {
                return (string)$raw;
            }
        }
        $ctx = stream_context_create(array(
            'http' => array('timeout' => 4, 'user_agent' => $ua),
            'ssl'  => array('verify_peer' => false, 'verify_peer_name' => false),
        ));
        $raw = @file_get_contents($url, false, $ctx);
        return $raw === false ? '' : (string)$raw;
    }

    protected static function esc($str)
    {
        return str_replace(array('"', '<', '>'), array('%22', '', ''), (string)$str);
    }
}
