<?php
/**
 * 通用验证码类
 * - 优先使用 TTF 字体（与本类同目录的 font.ttf）
 * - 字体文件缺失时自动降级为 GD 内置字体，保证验证码始终能显示
 */
class Utils_Caption
{
    var $Width      = 60;           // 图片宽
    var $Height     = 60;           // 图片高
    var $Length     = 4;            // 验证码位数
    var $BgColor    = "#FFFFFF";    // 背景色

    var $TFonts     = array("font.ttf");
    var $TFontSize  = array(17, 20); // 字体大小范围
    var $TFontAngle = array(-20, 20); // 旋转角度

    var $Chars      = "0123456789";  // 验证码范围（字母或数字）

    var $Code       = array();       // 验证码
    var $Image      = "";            // 图形对象
    var $FontColors = array('#f36161', '#6bc146', '#5368bd'); // 字体颜色
    var $TPadden    = 0.75;          // 字间补丁
    var $Txbase     = 5;             // x 轴左边距
    var $Tybase     = 5;             // y 轴左边距
    var $TLine      = true;          // 干扰线

    /** 取可用字体文件路径，找不到返回空串 */
    private function _fontFile()
    {
        foreach ((array)$this->TFonts as $name) {
            $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . $name;
            if ($name !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }
        return '';
    }

    public function RandRSI() // 生成验证码
    {
        $this->TFontAngle = range($this->TFontAngle[0], $this->TFontAngle[1]);
        $this->TFontSize  = range($this->TFontSize[0], $this->TFontSize[1]);

        $arr        = array();
        $Chars      = $this->Chars;
        $FontColors = $this->FontColors;
        $code       = '';
        $font       = $this->_fontFile();

        $charlen      = strlen($Chars) - 1;
        $anglelen     = count($this->TFontAngle) - 1;
        $fontsizelen  = count($this->TFontSize) - 1;
        $fontcolorlen = count($FontColors) - 1;

        for ($i = 0; $i < $this->Length; $i++) {
            $char      = $Chars[rand(0, $charlen)];
            $angle     = $this->TFontAngle[rand(0, $anglelen)];
            $fontsize  = $this->TFontSize[rand(0, $fontsizelen)];
            $fontcolor = $FontColors[rand(0, $fontcolorlen)];

            if ($font === '') {
                // 无字体文件：用 GD 内置字体估算边界
                $bound = array(
                    'left'   => 0,
                    'top'    => 0,
                    'width'  => imagefontwidth(5),
                    'height' => imagefontheight(5),
                    'box'    => array(),
                    'builtin' => true,
                );
            } else {
                $bound = $this->_calculateTextBox($fontsize, $angle, $font, $char);
                $bound['builtin'] = false;
            }

            $arr[] = array($fontsize, $angle, $fontcolor, $char, $font, $bound);
            $code .= $char;
        }
        $this->Code = $arr;
        return $code;
    }

    public function Draw() // 画图
    {
        if (empty($this->Code)) {
            $this->RandRSI();
        }
        $codes = $this->Code;

        $wh     = $this->_getImageWH($codes);
        $width  = $wh[0];
        $height = $wh[1];

        $this->Width  = $width;
        $this->Height = $height;

        $this->Image = imageCreate($width, $height);
        $image       = $this->Image;

        $back = $this->_getColor2($this->_getColor($this->BgColor));
        imageFilledRectangle($image, 0, 0, $width, $height, $back);

        $TPadden = $this->TPadden;
        $basex   = $this->Txbase;
        $color   = null;

        foreach ($codes as $v) {
            $bound = $v[5];
            $color = $this->_getColor2($this->_getColor($v[2]));
            if (!empty($bound['builtin']) || $v[4] === '') {
                // 内置字体绘制（无 TTF 时兜底）
                $fy = $this->Tybase + 2;
                $fh = imagefontheight(5);
                if ($fy + $fh > $height - 1) {
                    $fy = max(1, $height - 1 - $fh);
                }
                imagestring($image, 5, $basex, $fy, $v[3], $color);
                $basex = $basex + $bound['width'] + 3;
                continue;
            }
            imagettftext($image, $v[0], $v[1], $basex, $bound['height'], $color, $v[4], $v[3]);
            $basex = $basex + $bound['width'] * $TPadden - $bound['left'];
        }

        if ($this->TLine) {
            $this->_wirteSinLine($color, $basex);
        }

        if (!headers_sent()) {
            header("Content-Type: image/png");
            header("Cache-Control: no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
        }
        imagepng($image);
        imagedestroy($image);
    }

    /**
     * 通过字体角度得到最小外接矩形
     */
    private function _calculateTextBox($font_size, $font_angle, $font_file, $text)
    {
        $box = @imagettfbbox($font_size, $font_angle, $font_file, $text);
        if (!is_array($box) || count($box) < 8) {
            return array(
                'left'   => 0,
                'top'    => 0,
                'width'  => imagefontwidth(5) * max(1, strlen($text)),
                'height' => imagefontheight(5),
                'box'    => array(),
                'builtin' => true,
            );
        }

        $min_x = min(array($box[0], $box[2], $box[4], $box[6]));
        $max_x = max(array($box[0], $box[2], $box[4], $box[6]));
        $min_y = min(array($box[1], $box[3], $box[5], $box[7]));
        $max_y = max(array($box[1], $box[3], $box[5], $box[7]));

        return array(
            'left'   => ($min_x >= -1) ? -abs($min_x + 1) : abs($min_x + 2),
            'top'    => abs($min_y),
            'width'  => $max_x - $min_x,
            'height' => $max_y - $min_y,
            'box'    => $box,
            'builtin' => false,
        );
    }

    private function _getColor($color) // #ffffff
    {
        return array(hexdec($color[1] . $color[2]), hexdec($color[3] . $color[4]), hexdec($color[5] . $color[6]));
    }

    private function _getColor2($color) // #ffffff
    {
        return imagecolorallocate($this->Image, $color[0], $color[1], $color[2]);
    }

    private function _getImageWH($data)
    {
        $TPadden = $this->TPadden;
        $w = $this->Txbase;
        $h = 0;
        foreach ($data as $v) {
            $w = $w + $v[5]['width'] * $TPadden - $v[5]['left'];
            $h = $h > $v[5]['height'] ? $h : $v[5]['height'];
        }
        return array(max($w, $this->Width), max($h, $this->Height));
    }

    // 画正弦干扰线
    private function _wirteSinLine($color, $w)
    {
        $img = $this->Image;

        $h  = $this->Height;
        $h1 = rand(-5, 5);
        $h2 = rand(-1, 1);
        $w2 = rand(10, 15);
        $h3 = rand(4, 6);

        for ($i = -$w / 2; $i < $w / 2; $i = $i + 0.1) {
            $y = $h / $h3 * sin($i / $w2) + $h / 2 + $h1;
            imagesetpixel($img, (int)($i + $w / 2), (int)$y, $color);
            if ($h2 != 0) {
                imagesetpixel($img, (int)($i + $w / 2), (int)($y + $h2), $color);
            }
        }
    }
}
