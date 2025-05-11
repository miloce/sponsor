<?php
// 简单的API测试脚本

// 从环境变量中获取参数
$userId = getenv('USERID'); 
$token = getenv('TOKEN'); 

error_log('测试脚本 - USERID: ' . (empty($userId) ? '未设置' : '已设置，前4位: ' . substr($userId, 0, 4)) . 
          ', TOKEN: ' . (empty($token) ? '未设置' : '已设置，长度: ' . strlen($token)));

if(empty($userId) || empty($token)) {
    die("缺少必要的环境变量USERID或TOKEN");
}

// 准备参数
$params = json_encode(array('page' => 1));
$ts = time();

// 计算签名
$sign = $token;
$sign .= 'params' . $params;
$sign .= 'ts' . $ts;
$sign .= 'user_id' . $userId;
$sign = md5($sign);

// 构建请求参数
$data = array(
    'user_id' => $userId,
    'params' => $params,
    'ts' => $ts,
    'sign' => $sign
);

// 发送请求
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://47.242.58.243/api/open/query-sponsor?' . http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/96.0.4664.110');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$response = curl_exec($ch);

if($response === false) {
    echo "CURL错误: " . curl_error($ch);
    error_log("CURL错误: " . curl_error($ch) . " (错误码: " . curl_errno($ch) . ")");
} else {
    echo "<pre>";
    $result = json_decode($response, true);
    if(json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON解析错误: " . json_last_error_msg();
        error_log("JSON解析错误: " . json_last_error_msg() . ", 原始响应: " . substr($response, 0, 500));
    } else {
        print_r($result);
        error_log("API测试响应: " . (isset($result['ec']) ? "EC=" . $result['ec'] : "无EC") . 
                 ", " . (isset($result['em']) ? "EM=" . $result['em'] : "无EM"));
    }
    echo "</pre>";
}

curl_close($ch);
?> 