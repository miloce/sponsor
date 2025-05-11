<?php
    // 从环境变量中获取参数
    // 以下环境变量必须被设置
    $pagetitlevar = getenv('PAGETITLE') ?: '赞助页面'; // 网页标题
    $usernamevar = getenv('USERNAME'); // 你的用户名，即你的主页地址 @ 后面的那部分，如 https://afdian.net/@MisaLiu，那么 MisaLiu 就是你的用户名
    $useridvar = getenv('USERID'); // 你的用户 ID，请前往 https://afdian.net/dashboard/dev 获取
    $tokenvar = getenv('TOKEN'); // 你的 API Token，请前往 https://afdian.net/dashboard/dev 获取

    // 记录环境变量状态（敏感信息部分隐藏）
    error_log('环境变量状态 - PAGETITLE: ' . ($pagetitlevar ? '已设置' : '未设置') . 
              ', USERNAME: ' . ($usernamevar ? '已设置('.$usernamevar.')' : '未设置') . 
              ', USERID: ' . ($useridvar ? '已设置(前4位:'.substr($useridvar, 0, 4).'...)' : '未设置') . 
              ', TOKEN: ' . ($tokenvar ? '已设置(长度:'.strlen($tokenvar).')' : '未设置'));

    // 检查必要的环境变量是否已设置
    if (empty($usernamevar) || empty($useridvar) || empty($tokenvar)) {
        $errorMsg = '请检查环境变量设置，必须设置USERNAME、USERID和TOKEN';
        
        if (empty($_POST)) {
            die($errorMsg);
        } else {
            $return = array();
            $return['code'] = -1;
            $return['msg'] = $errorMsg;
            $return['html'] = '<div class="mdui-col-xs-12 mdui-text-center">' . $errorMsg . '</div>';
            echo json_encode($return);
            exit;
        }
    }
    
    $_AFDIAN = array(
        'pageTitle' => $pagetitlevar,
        'userName'  => $usernamevar,
        'userId'    => $useridvar,
        'token'     => $tokenvar
    );

    $currentPage = !empty($_POST['page']) ? intval($_POST['page']) : 1;
    if ($currentPage < 1) $currentPage = 1;

    $data = array();
    $data['user_id'] = $_AFDIAN['userId'];
    $data['params']  = json_encode(array('page' => $currentPage));
    $data['ts']      = time();
    $data['sign']    = SignAfdian($_AFDIAN['token'], $data['params'], $_AFDIAN['userId']);

    // 请求爱发电API
    $apiUrl = 'https://afdian.net/api/open/query-sponsor?' . http_build_query($data);
    error_log('请求爱发电API: ' . $apiUrl);
    
    $result = HttpGet($apiUrl);
    
    // 记录原始API返回结果供调试
    error_log('Afdian API 响应: ' . substr($result, 0, 1000) . (strlen($result) > 1000 ? '...(已截断)' : ''));
    
    $decoded = json_decode($result, true);
    error_log('解码结果: ' . ($decoded ? 'JSON解析成功' : 'JSON解析失败') . 
             ', EC: ' . (isset($decoded['ec']) ? $decoded['ec'] : '未设置') . 
             ', EM: ' . (isset($decoded['em']) ? $decoded['em'] : '未设置'));
    
    // 检查API返回结果是否有效
    if (isset($decoded['data']) && $decoded['data'] !== null) {
        $result = $decoded;
        $donator['total']     = $result['data']['total_count'];
        $donator['totalPage'] = $result['data']['total_page'];
        $donator['list']      = $result['data']['list'];

        $donatorsHTML = '';
        for ($i = 0; $i < count($donator['list']); $i++) {
            $_donator = $donator['list'][$i];
            
            // 安全地获取赞助计划
            $sponsor_plans = isset($_donator['sponsor_plans']) ? $_donator['sponsor_plans'] : array();
            $current_plan = isset($_donator['current_plan']) ? $_donator['current_plan'] : array('name' => '');
            
            // 安全地获取last_sponsor
            $last_sponsor_plan = !empty($sponsor_plans) ? end($sponsor_plans) : array('name' => '');
            $last_sponsor_name = isset($last_sponsor_plan['name']) ? $last_sponsor_plan['name'] : '';
            
            $_donator['last_sponsor'] = empty($last_sponsor_name) ? 
                (empty($current_plan['name']) ? array('name' => '') : $current_plan) : 
                $last_sponsor_plan;
            
            // 获取用户头像和名称
            $user_avatar = isset($_donator['user']['avatar']) ? $_donator['user']['avatar'] : 'https://static.luozhinet.com/xcx/assets/icons/default-avatar.png';
            $user_name = isset($_donator['user']['name']) ? $_donator['user']['name'] : '未知用户';
            $all_sum_amount = isset($_donator['all_sum_amount']) ? $_donator['all_sum_amount'] : '0';
            
            $donatorsHTML .= '<div class="mdui-col-xs-12 mdui-col-md-6 mdui-m-b-2">
                <div class="mdui-card">
                    <div class="mdui-card-header">
                        <img class="mdui-card-header-avatar" src="' . $user_avatar . '" />
                        <div class="mdui-card-header-title">' . $user_name .
                        '&nbsp;&nbsp;&nbsp;&nbsp;共' . $all_sum_amount . '元' . '</div>
                        <div class="mdui-card-header-subtitle">最后发电：' .
                        (empty($_donator['last_sponsor']['name']) ?
                            '暂无' :
                            $_donator['last_sponsor']['name'] . '&nbsp;&nbsp;' . 
                            (isset($_donator['last_sponsor']['show_price']) ? $_donator['last_sponsor']['show_price'] : '?') . 
                            '元，于 ' . (isset($_donator['last_pay_time']) ? date('Y-m-d H:i:s', $_donator['last_pay_time']) : '未知时间')) .
                        '</div>
                    </div>' .
                    (isset($_donator['last_sponsor']['pic']) && !empty($_donator['last_sponsor']['pic']) ? '
                        <div class="mdui-card-media">
                            <img src="' . $_donator['last_sponsor']['pic'] . '"/>
                        </div>' :
                        '') .
                '</div></div>';
        }

        $pageControlHTML = '<div class="mdui-row">
            <button onclick="switchPage(' . ($currentPage - 1) . ')" class="mdui-btn mdui-btm-raised mdui-ripple mdui-color-theme-accent mdui-float-left"' . ($currentPage == 1 ? ' disabled' : '') . '>
                <i class="mdui-icon material-icons">keyboard_arrow_left</i>
                上一页
            </button>
            <div class="mdui-btn-group -center">';
        
        // 保护：确保totalPage至少为1
        $totalPage = max(1, isset($donator['totalPage']) ? $donator['totalPage'] : 1);
        
        for ($i = 0; $i < $totalPage; $i++) {
            $pageControlHTML .= '<button onclick="switchPage(' . ($i + 1) . ')" class="mdui-btn ' .
            ($i + 1 == $currentPage ? 'mdui-btn-active mdui-color-theme-accent' : 'mdui-text-color-theme-text') .
            '">' . ($i + 1) . '</button>';
        }
        $pageControlHTML .= '</div>
            <button onclick="switchPage(' . ($currentPage + 1) . ')" class="mdui-btn mdui-btm-raised mdui-ripple mdui-color-theme-accent mdui-float-right"' . ($totalPage <= $currentPage ? ' disabled' : '') . '>
                下一页
                <i class="mdui-icon material-icons">keyboard_arrow_right</i>
            </button>
        </div>';

        if (empty($_POST)) {
$html = <<< HTML
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf8" />
        <meta name="viewport" content="width=device-width" />
        <link rel="stylesheet" href="./css/mdui.min.css" />
        <link rel="stylesheet" href="./css/main.css" />
        <script src="./js/mdui.min.js"></script>
        <title>${_AFDIAN['pageTitle']}</title>
    </head>
    <body class="mdui-appbar-with-toolbar mdui-theme-primary-blue-grey mdui-theme-accent-red mdui-theme-layout-auto">
        <header class="mdui-appbar mdui-appbar-fixed">
            <div class="mdui-progress mdui-hidden" style="position:absolute;top:0;width:100%" id="mdui_progress">
                <div class="mdui-progress-indeterminate" style="background-color:white"></div>
            </div>
            <div class="mdui-toolbar mdui-color-theme">
                <button class="mdui-btn mdui-btn-icon mdui-ripple" mdui-drawer="{target:'#drawer',swipe:true}"><i class="mdui-icon material-icons">menu</i></button>
                <a href="javascript:;" class="mdui-typo-headline">${_AFDIAN['pageTitle']}</a>
            </div>
        </header>

        <drawer class="mdui-drawer mdui-drawer-close" id="drawer">
            <div class="mdui-list">
                <a class="mdui-list-item mdui-ripple">
                    <i class="mdui-list-item-icon mdui-icon material-icons">home</i>
                    <div class="mdui-list-item-content">首页</div>
                </a>
            </div>
        </drawer>

        <main class="mdui-container mdui-typo">
            <h1 class="mdui-text-center">支持我，为我发电</h1>
            <iframe id="afdian_leaflet" class="mdui-center" src="https://afdian.net/leaflet?slug=${_AFDIAN['userName']}" scrolling="no" frameborder="0"></iframe>
            <div class="mdui-divider mdui-m-t-5"></div>
            <h2 class="mdui-text-center">感谢以下小伙伴的发电支持！</h2>
            
            <div class="mdui-m-b-2" id="afdian_sponsors">
                <div class="mdui-row">
                    ${donatorsHTML}
                </div>
                ${pageControlHTML}
            </div>
        </main>

        <script src="./js/main.js"></script>
    </body>
</html>
HTML;

            echo $html;
        } else {
            $return = array();
            $return['code'] = $result['ec'];
            $return['msg']  = $result['em'];
            $return['html'] = (!empty($donatorsHTML) ? '<div class="mdui-row">' . $donatorsHTML . "</div>" . $pageControlHTML : '');

            echo json_encode($return);
        }
    } else {
        // API返回结果无效
        $donator = array(
            'total' => 0,
            'totalPage' => 1,
            'list' => array()
        );
        $donatorsHTML = '<div class="mdui-col-xs-12 mdui-text-center">获取发电列表失败，请稍后再试</div>';
        $pageControlHTML = '';

        if (empty($_POST)) {
$html = <<< HTML
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf8" />
        <meta name="viewport" content="width=device-width" />
        <link rel="stylesheet" href="./css/mdui.min.css" />
        <link rel="stylesheet" href="./css/main.css" />
        <script src="./js/mdui.min.js"></script>
        <title>${_AFDIAN['pageTitle']}</title>
    </head>
    <body class="mdui-appbar-with-toolbar mdui-theme-primary-blue-grey mdui-theme-accent-red mdui-theme-layout-auto">
        <header class="mdui-appbar mdui-appbar-fixed">
            <div class="mdui-progress mdui-hidden" style="position:absolute;top:0;width:100%" id="mdui_progress">
                <div class="mdui-progress-indeterminate" style="background-color:white"></div>
            </div>
            <div class="mdui-toolbar mdui-color-theme">
                <button class="mdui-btn mdui-btn-icon mdui-ripple" mdui-drawer="{target:'#drawer',swipe:true}"><i class="mdui-icon material-icons">menu</i></button>
                <a href="javascript:;" class="mdui-typo-headline">${_AFDIAN['pageTitle']}</a>
            </div>
        </header>

        <drawer class="mdui-drawer mdui-drawer-close" id="drawer">
            <div class="mdui-list">
                <a class="mdui-list-item mdui-ripple">
                    <i class="mdui-list-item-icon mdui-icon material-icons">home</i>
                    <div class="mdui-list-item-content">首页</div>
                </a>
            </div>
        </drawer>

        <main class="mdui-container mdui-typo">
            <h1 class="mdui-text-center">支持我，为我发电</h1>
            <iframe id="afdian_leaflet" class="mdui-center" src="https://afdian.net/leaflet?slug=${_AFDIAN['userName']}" scrolling="no" frameborder="0"></iframe>
            <div class="mdui-divider mdui-m-t-5"></div>
            <h2 class="mdui-text-center">感谢以下小伙伴的发电支持！</h2>
            
            <div class="mdui-m-b-2" id="afdian_sponsors">
                <div class="mdui-row">
                    ${donatorsHTML}
                </div>
                ${pageControlHTML}
            </div>
        </main>

        <script src="./js/main.js"></script>
    </body>
</html>
HTML;

            echo $html;
        } else {
            $return = array();
            $return['code'] = $result['ec'];
            $return['msg']  = $result['em'];
            $return['html'] = (!empty($donatorsHTML) ? '<div class="mdui-row">' . $donatorsHTML . "</div>" . $pageControlHTML : '');

            echo json_encode($return);
        }
    }

    function SignAfdian ($token, $params, $userId) {
        $sign = $token;
        $sign .= 'params' . $params;
        $sign .= 'ts' . time();
        $sign .= 'user_id' . $userId;
        
        error_log('签名计算 - token长度: ' . strlen($token) . 
                 ', params长度: ' . strlen($params) . 
                 ', userId长度: ' . strlen($userId));
        
        return md5($sign, false);
    }

    function HttpGet ($url, $method = 'GET', $data = '', $contentType = '', $timeout = 10) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        if (!empty($contentType)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $contentType);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        // 设置用户代理
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36');
        
        $output = curl_exec($ch);
        
        // 记录CURL错误
        if($output === false) {
            error_log('CURL错误: ' . curl_error($ch) . ' (错误码: ' . curl_errno($ch) . ')');
        }
        
        curl_close($ch);
        return $output;
    }
