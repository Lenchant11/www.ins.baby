<?php

/***********************************************************************
 * 圣盾防御核心文件
 * fangyu_core.php
 *
 * @website https://www.fangyu.io
 * @author  圣盾防御 <sdfangyu@gmail.com>
 * @update  2025-04-16 22:51
 ***********************************************************************/


/**
 * 安全加载页面文件并插入脚本
 */
function loadHtmlWithScript(bool $isIndex, ?string $filePath, array $result)
{
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo ($isIndex ? '落地页' : '安全页') . "文件不存在: " . htmlspecialchars(basename($filePath));
        exit;
    }
    // 启动输出缓冲
    ob_start();
    require $filePath;
    $htmlContent = ob_get_clean();

    // 生成要插入的脚本
    $script = getCoreScript($result);

    // 优化插入逻辑，兼容XHTML
    $htmlContent = preg_replace('/(<\/body\s*>)/i', $script . '$1', $htmlContent, 1, $count);
    if ($count === 0) {
        $htmlContent .= $script;
    }
    echo $htmlContent;
}

/**
 * 获取核心代码和参数
 */
function getCoreScript($result): string
{
    $repeatKey = htmlspecialchars($result['repeatKey'] ?? '');
    $repeatValue = htmlspecialchars($result['repeatValue'] ?? '');
    return "<script src='core/core.min.js'></script><script>if(typeof SdCore !== 'undefined') {SdCore.set('$repeatKey', '$repeatValue');}</script>";
}

/**
 * 获取key
 */
const DEFAULT_REPEAT_KEY = "_sd_0000";
function getRepeatKey(): string
{
    if (preg_match('#/check/(\d+)/#', FANGYU_API_URL, $matches)) {
        return "_sd_" . $matches[1];
    }
    return DEFAULT_REPEAT_KEY;
}

/**
 * 获取value
 */
function getRepeatValue(): string
{
    $repeatKey = getRepeatKey();
    $cookieValue = $_COOKIE[$repeatKey] ?? "";
    if ($cookieValue !== null) {
        return $cookieValue;
    } else {
        return "";
    }
}

/**
 * API请求执行器
 */
function fangyuApiRequestExecutor(): array
{
    //设置编码
    $encoding = 'UTF-8';
    //请求参数
    $params = [
        //访客IP(最多50个字符，必填)
        'clientIp' => mb_substr(getClientIp(), 0, 50, $encoding),
        //访客代理(最多500个字符，必填)
        'userAgent' => mb_substr(($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500, $encoding),
        //访问网址(最多500个字符，必填)
        'visitUrl' => mb_substr(getPageUrl(), 0, 500, $encoding),
        //客户端语言(最多100个字符，可为空，即'')
        'clientLanguage' => mb_substr(($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 100, $encoding),
        //访客来路(最多500个字符，可为空，即'')
        'referer' => mb_substr(getReferer(), 0, 500, $encoding),
        //时间戳（秒级10位数字，必填）
        'timestamp' => time(),
    ];
    //添加签名
    $params['sign'] = getSign($params);
    //添加用户标记
    $params['repeatKey'] = getRepeatKey();
    $params['repeatValue'] = getRepeatValue();
    //执行请求
    $response = postUrl(FANGYU_API_URL, json_encode($params));
    //如果响应为空，一般为接口请求失败，即Unknown error。
    if (empty($response)) {
        //响应为空，返回数组，用户自定义执行动作
        return resultData(-1, '接口网络连接失败', null);
    }

    //格式化
    $respArray = json_decode($response, true);
    //******************************* 接口异常 *******************************//
    if (!$respArray['success'] || $respArray['code'] != 200) {
        return resultData(-1, $respArray['message'] ?? '接口返回异常', null);
    }

    //*************************** 接口正常,业务数据为空 ***************************//
    $dataArray = $respArray['data'];
    if (empty($dataArray)) {
        return resultData(-1, '访客业务数据为空', $dataArray);
    }

    //***************************** 返回接口的业务消息 *****************************//
    if ($dataArray['status']) {
        #被允许
        return resultData(1, 'ok', $dataArray);
    } else {
        #被拦截
        return resultData(0, $dataArray['message'] ?? '被拦截', $dataArray);
    }
}

/**
 * 统一返回结果的数组格式
 * @param int $code 状态码 (-1:错误, 0:拦截, 1:成功)
 * @param string|null $message 提示信息
 * @param $respArray
 * @return array $respArray 接口返回程序
 */
function resultData(int $code, ?string $message, $respArray): array
{
    return [
        'code' => $code,
        'message' => $message,
        'jump' => $respArray['jump'] ?? null,
        'jumpMode' => $respArray['jumpMode'] ?? 0,
        'jumpBlockUrl' => $respArray['jumpBlockUrl'] ?? null,
        'jumpBlockMode' => $respArray['jumpBlockMode'] ?? 0,
        'repeatKey' => $respArray['repeatKey'] ?? DEFAULT_REPEAT_KEY,
        'repeatValue' => $respArray['repeatValue'] ?? null,
        'custom' => $respArray['custom'] ?? []
    ];
}

/**
 * 模拟POST提交
 * @param string $url 地址
 * @param string $params 提交的数据 $params = ["age"=>18, "name"=>"小明"];
 * @return string 返回结果
 */
function postUrl(string $url, string $params): string
{
    // 启动一个CURL会话
    $curl = curl_init();
    // 要访问的地址
    curl_setopt($curl, CURLOPT_URL, $url);
    // 对认证证书来源的检查
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    // 从证书中检查SSL加密算法是否存在
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    // 模拟用户使用的浏览器
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Windows NT 10.0)');
    // 请求header
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type:application/json;charset="utf-8"', 'Accept:application/json'));
    // 使用自动跳转
    //curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    // 自动设置Referer
    //curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
    // 发送一个常规的Post请求
    curl_setopt($curl, CURLOPT_POST, 1);
    // Post请求数据包
    curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
    // 设置超时限制，以秒为单位，设置为0则无限制。
    curl_setopt($curl, CURLOPT_TIMEOUT, 16);
    // 设置发起连接前等待的时间，如果设置为0，则无限等待。
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
    // 显示返回的Header区域内容
    curl_setopt($curl, CURLOPT_HEADER, 0);
    // 获取的信息以文件流的形式返回
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    // 执行操作
    $response = curl_exec($curl);
    //关闭CURL会话
    curl_close($curl);
    // 返回数据
    return $response;
}

/**
 * 检查IP是否在指定范围内
 *
 * @param string|null $ip IP地址
 * @param string|null $range IP范围（CIDR表示法）
 * @return bool 是否在范围内
 */
function ipInRange(?string $ip, ?string $range): bool
{
    if (strpos($range, '/') === false) {
        $range .= '/32';
    }
    list($range, $netmask) = explode('/', $range, 2);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // IPv4处理
        $netmask = ~((1 << (32 - $netmask)) - 1);
        return (ip2long($ip) & $netmask) == (ip2long($range) & $netmask);
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6处理
        $ip = inet_pton($ip);
        $range = inet_pton(explode('/', $range)[0]);
        $netmask = str_repeat('f', $netmask / 4) . str_repeat('0', (128 - $netmask) / 4);
        $netmask = pack('H*', $netmask);
        return ($ip & $netmask) == ($range & $netmask);
    }
    return false;
}

/**
 * 获取访客IP
 *
 * 通过增加CDN的受信任的代理IP，来正确获得客户IP，防止被伪造。
 * @return string 客户端的真实IP地址
 */
function getClientIp(): ?string
{
    // Cloudflare 的 IP 范围
    //* https://www.cloudflare.com/ips-v6/
    //* https://www.cloudflare.com/ips-v4/
    $trustedProxies = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
        //亚马逊云 CloudFront
        //IP范围： https://d7uri8nf7uskq.cloudfront.net/tools/list-cloudfront-ips
        '120.52.22.96/27',
        '205.251.249.0/24',
        '180.163.57.128/26',
        '204.246.168.0/22',
        '111.13.171.128/26',
        '18.160.0.0/15',
        '205.251.252.0/23',
        '54.192.0.0/16',
        '204.246.173.0/24',
        '54.230.200.0/21',
        '120.253.240.192/26',
        '116.129.226.128/26',
        '130.176.0.0/17',
        '108.156.0.0/14',
        '99.86.0.0/16',
        '13.32.0.0/15',
        '120.253.245.128/26',
        '13.224.0.0/14',
        '70.132.0.0/18',
        '15.158.0.0/16',
        '111.13.171.192/26',
        '13.249.0.0/16',
        '18.238.0.0/15',
        '18.244.0.0/15',
        '205.251.208.0/20',
        '3.165.0.0/16',
        '3.168.0.0/14',
        '65.9.128.0/18',
        '130.176.128.0/18',
        '58.254.138.0/25',
        '205.251.201.0/24',
        '205.251.206.0/23',
        '54.230.208.0/20',
        '3.160.0.0/14',
        '116.129.226.0/25',
        '52.222.128.0/17',
        '18.164.0.0/15',
        '111.13.185.32/27',
        '64.252.128.0/18',
        '205.251.254.0/24',
        '3.166.0.0/15',
        '54.230.224.0/19',
        '71.152.0.0/17',
        '216.137.32.0/19',
        '204.246.172.0/24',
        '205.251.202.0/23',
        '18.172.0.0/15',
        '120.52.39.128/27',
        '118.193.97.64/26',
        '3.164.64.0/18',
        '18.154.0.0/15',
        '3.173.0.0/17',
        '54.240.128.0/18',
        '205.251.250.0/23',
        '180.163.57.0/25',
        '52.46.0.0/18',
        '3.174.0.0/15',
        '52.82.128.0/19',
        '54.230.0.0/17',
        '54.230.128.0/18',
        '54.239.128.0/18',
        '130.176.224.0/20',
        '36.103.232.128/26',
        '52.84.0.0/15',
        '143.204.0.0/16',
        '144.220.0.0/16',
        '120.52.153.192/26',
        '119.147.182.0/25',
        '120.232.236.0/25',
        '111.13.185.64/27',
        '3.164.0.0/18',
        '3.172.64.0/18',
        '54.182.0.0/16',
        '58.254.138.128/26',
        '120.253.245.192/27',
        '54.239.192.0/19',
        '18.68.0.0/16',
        '18.64.0.0/14',
        '120.52.12.64/26',
        '99.84.0.0/16',
        '205.251.204.0/23',
        '130.176.192.0/19',
        '52.124.128.0/17',
        '205.251.200.0/24',
        '204.246.164.0/22',
        '13.35.0.0/16',
        '204.246.174.0/23',
        '3.164.128.0/17',
        '3.172.0.0/18',
        '36.103.232.0/25',
        '119.147.182.128/26',
        '118.193.97.128/25',
        '120.232.236.128/26',
        '204.246.176.0/20',
        '65.8.0.0/16',
        '65.9.0.0/17',
        '108.138.0.0/15',
        '120.253.241.160/27',
        '3.173.128.0/18',
        '64.252.64.0/18',
        '13.113.196.64/26',
        '13.113.203.0/24',
        '52.199.127.192/26',
        '13.124.199.0/24',
        '3.35.130.128/25',
        '52.78.247.128/26',
        '13.203.133.0/26',
        '13.233.177.192/26',
        '15.207.13.128/25',
        '15.207.213.128/25',
        '52.66.194.128/26',
        '13.228.69.0/24',
        '47.129.82.0/24',
        '47.129.83.0/24',
        '47.129.84.0/24',
        '52.220.191.0/26',
        '13.210.67.128/26',
        '13.54.63.128/26',
        '3.107.43.128/25',
        '3.107.44.0/25',
        '3.107.44.128/25',
        '43.218.56.128/26',
        '43.218.56.192/26',
        '43.218.56.64/26',
        '43.218.71.0/26',
        '99.79.169.0/24',
        '18.192.142.0/23',
        '18.199.68.0/22',
        '18.199.72.0/22',
        '18.199.76.0/22',
        '35.158.136.0/24',
        '52.57.254.0/24',
        '18.200.212.0/23',
        '52.212.248.0/26',
        '18.175.65.0/24',
        '18.175.66.0/24',
        '18.175.67.0/24',
        '3.10.17.128/25',
        '3.11.53.0/24',
        '52.56.127.0/25',
        '15.188.184.0/24',
        '52.47.139.0/24',
        '3.29.40.128/26',
        '3.29.40.192/26',
        '3.29.40.64/26',
        '3.29.57.0/26',
        '18.229.220.192/26',
        '18.230.229.0/24',
        '18.230.230.0/25',
        '54.233.255.128/26',
        '3.231.2.0/25',
        '3.234.232.224/27',
        '3.236.169.192/26',
        '3.236.48.0/23',
        '34.195.252.0/24',
        '34.226.14.0/24',
        '44.220.194.0/23',
        '44.220.196.0/23',
        '44.220.198.0/23',
        '44.220.200.0/23',
        '44.220.202.0/23',
        '44.222.66.0/24',
        '13.59.250.0/26',
        '18.216.170.128/25',
        '3.128.93.0/24',
        '3.134.215.0/24',
        '3.146.232.0/22',
        '3.147.164.0/22',
        '3.147.244.0/22',
        '52.15.127.128/26',
        '3.101.158.0/23',
        '52.52.191.128/26',
        '34.216.51.0/25',
        '34.223.12.224/27',
        '34.223.80.192/26',
        '35.162.63.192/26',
        '35.167.191.128/26',
        '35.93.168.0/23',
        '35.93.170.0/23',
        '35.93.172.0/23',
        '44.227.178.0/24',
        '44.234.108.128/25',
        '44.234.90.252/30'
    ];

    //去掉 X-Forwarded-For 防止被伪造，如果获取不到真实IP，可以给 X-Forwarded-For 追加到 headersToInspect 数组最后
    $headersToInspect = ['CF-Connecting-IP', 'True-Client-IP', 'X-Real-IP'];
    $ipAddress = null;

    // 验证请求是否来自受信任的代理
    $remoteAddr = $_SERVER['REMOTE_ADDR'];
    $isTrustedProxy = false;
    foreach ($trustedProxies as $trustedProxy) {
        if (ipInRange($remoteAddr, $trustedProxy)) {
            $isTrustedProxy = true;
            break;
        }
    }

    if ($isTrustedProxy) {
        foreach ($headersToInspect as $header) {
            if (!empty($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $header))])) {
                $ipAddress = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $header))];
                break;
            }
        }
    }

    // 如果 IP 仍然为空，获取直接的远程地址
    if (empty($ipAddress)) {
        $ipAddress = $remoteAddr;
    }

    // 如果 IP 地址包含多个，取第一个
    if (strpos($ipAddress, ',') !== false) {
        $ipAddress = trim(explode(',', $ipAddress)[0]);
    }

    // 验证 IP 地址格式是否正确
    if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
        // 或者设置一个默认值
        $ipAddress = '0.0.0.0';
    }
    return $ipAddress;
}

/**
 * 获取当前页面地址
 */
function getPageUrl(): string
{
    $serviceName = $_SERVER['SERVER_NAME'] ?? '';
    if (empty($serviceName)) {
        return '';
    }
    $pageURL = 'http';
    if (($_SERVER['HTTPS'] ?? '') == 'on') {
        $pageURL .= 's';
    }
    $pageURL .= '://';
    if ($_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443') {
        // 如果端口号既不是 80 也不是 443，拼接端口号到 URL
        $pageURL .= $serviceName . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
    } else {
        // 如果端口号是 80 或者 443，不拼接端口号到 URL
        $pageURL .= $serviceName . $_SERVER['REQUEST_URI'];
    }
    return $pageURL;
}

/**
 * 获取来路
 */
function getReferer(): string
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (empty($referer)) {
        return '';
    }
    //判断 referer 是否包含 SERVER_NAME
    //包含说明是当前网站，返回null，否则返回来路。
    return contains($referer, $_SERVER['SERVER_NAME']) ? '' : $referer;
}

/**
 * 签名生成算法
 * @param array $params API调用的请求参数集合的关联数组，不包含sign参数
 * @return string 返回参数签名值
 */
function getSign(array $params): string
{
    // 对数组的值按key排序
    ksort($params);
    // 生成sign
    return hash('sha256', http_build_query($params) . FANGYU_API_KEY);
}

/**
 * 判断sourceStr中是否包含findStr
 */
function contains($sourceStr, $findStr): bool
{
    return count(explode($findStr, $sourceStr)) > 1;
}

/**
 * 比较字符串是否相等
 */
function equals($str1, $str2): bool
{
    return strcasecmp($str1, $str2) == 0;
}

/**
 * 判断sourceStr是否以findStr字符串开始
 */
function startsWith($sourceStr, $findStr): bool
{
    return substr($sourceStr, 0, strlen($findStr)) == $findStr;
}

/**
 * 判断sourceStr是否以findStr字符串结束
 */
function endsWith($sourceStr, $findStr): bool
{
    return substr($sourceStr, strpos($sourceStr, $findStr)) == $findStr;
}

