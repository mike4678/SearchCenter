<?php
/* 内核模块注册表
/* 说明：
/*  1. System 是后台全部功能依赖的核心模块（原被注释掉，导致 $dou 取不到而 Fatal）。
/*  2. Mobile 目录里只有 check_wap() 等普通函数，没有类，不能当模块加载；
/*     它的函数在需要时会由 admin 页面自行 require。
*/
return [
    'core_modules' => [
        'FileUtil',
        'System',
    ],
    'optional_modules' => []
];
