<?php

/***********************************************************************
 * 圣盾防御索引主文件
 * index.php
 *
 * @website https://www.fangyu.io
 * @author  圣盾防御 <sdfangyu@gmail.com>
 * @update  2025-04-16 22:51
 ***********************************************************************/

//唯一编号配置
require __DIR__ . '/api.php';
//引用配置文件
require __DIR__ . '/core/fangyu_core.php';

//***********************************************************************************//
// 请求网络接口
// @param 是否返回接口结果，空或0无返回；1有返回；
// @return json
//***********************************************************************************//
$result = fangyuApiRequestExecutor();
//***********************************************************************************//
// 接口返回码
// 请求异常：-1；被拦截：0；被允许：1；
//***********************************************************************************//
$resultCode = $result['code'] ?? -1;
//***********************************************************************************//
// 被允许时的参数
//设置位置：我的网站 -- 某网站 -- 编辑规则 -- 基础 -- 被允许时TAB标签
//***********************************************************************************//
//被允许时的模式（1-单一地址；2-轮询地址；3-安全页文件名）
if (defined('JUMP_MODE') && JUMP_MODE !== null) {
    $jumpMode = JUMP_MODE;
} else {
    $jumpMode = $result['jumpMode'] ?? 1;
}

//被允许时的地址
if (defined('JUMP_URL') && JUMP_URL !== '') {
    $jumpUrl = JUMP_URL;
} else {
    $jumpUrl = $result['jump'];
}

//***********************************************************************************//
//被拦截时的参数
//设置位置：我的网站 -- 某网站 -- 编辑规则 -- 基础 -- 被拦截时TAB标签
//***********************************************************************************//
//被拦截时的模式（1-单一地址；2-轮询地址；3-安全页文件名；4-404错误页面）
if (defined('JUMP_BLOCK_MODE') && JUMP_BLOCK_MODE !== null) {
    $jumpBlockMode = JUMP_BLOCK_MODE;
} else {
    $jumpBlockMode = $result['jumpBlockMode'] ?? 4;
}

//被拦截时的地址
if (defined('JUMP_BLOCK_URL') && JUMP_BLOCK_URL !== '') {
    $jumpBlockUrl = JUMP_BLOCK_URL;
} else {
    $jumpBlockUrl = $result['jumpBlockUrl'];
}

//============================== 公共函数 ==============================//

if ($resultCode == 1) {
//***********************************************//
// 访客被允许
//***********************************************//
    if ($jumpMode == 1 || $jumpMode == 2) {
        // JS 跳转模式
        echo getCoreScript($result);
        echo "<script>window.location.href = '$jumpUrl';</script>";
    } else {
        // 加载本文件目录下的落地页，如：落地页-{{随机字符串}}.html
        $file = __DIR__ . DIRECTORY_SEPARATOR . $jumpUrl;
        loadHtmlWithScript(true, $file, $result);
    }
    exit;
}

//**********************************************//
// 被拦截 和 请求异常 等
//**********************************************//
else {
    if ($jumpBlockMode == 1 || $jumpBlockMode == 2) {
        //单一地址和轮询地址跳转
        echo getCoreScript($result);
        echo "<script>window.location.href = '$jumpBlockUrl';</script>";
    } else if ($jumpBlockMode == 3) {
        // 加载本文件目录下的安全页，如：安全页-{{随机字符串}}.html
        $file = __DIR__ . DIRECTORY_SEPARATOR . $jumpBlockUrl;
        loadHtmlWithScript(false, $file, $result);
    } else {
        // 404 模式
        //默认404模式被拦截后，展示的是nginx的默认404页面
        //要以下代码生效（非必要），需要nginx的PHP中配置：fastcgi_intercept_errors off;
        http_response_code(404);
        echo '<html><head><title>404 Not Found</title></head><body><center><h1>404 Not Found</h1></center><hr><center>nginx</center>' . getCoreScript($result) . '</body></html>';
    }
    exit;
}