<?php
    // 从环境变量中获取参数
    // 以下环境变量必须被设置
    $pagetitlevar = getenv('PAGETITLE'); // 网页标题
    $usernamevar = getenv('USERNAME'); // 你的用户名，即你的主页地址 @ 后面的那部分，如 https://afdian.com/@Miloce，那么 Miloce 就是你的用户名
    $useridvar = getenv('USERID'); // 你的用户 ID，请前往 https://afdian.com/dashboard/dev 获取
    $tokenvar = getenv('TOKEN'); // 你的 API Token，请前往 https://afdian.com/dashboard/dev 获取
    $_AFDIAN = array(
        'pageTitle' => $pagetitlevar,
        'userName'  => $usernamevar,
        'userId'    => $useridvar,
        'token'     => $tokenvar
    );

    $currentPage = !empty($_POST['page']) ? $_POST['page'] : 1;
    $per_page = 20; // 默认每页显示20条记录

    $data = array();
    $data['user_id'] = $_AFDIAN['userId'];
    $data['params']  = json_encode(array('page' => $currentPage, 'per_page' => $per_page));
    $data['ts']      = time();
    $data['sign']    = SignAfdian($_AFDIAN['token'], $data['params'], $_AFDIAN['userId'], $data['ts']);

    // 请求爱发电API获取赞助者数据
    $api_url = 'https://afdian.com/api/open/query-sponsor';
    
    // 记录发送请求的参数
    error_log("爱发电API请求参数: " . json_encode($data));
    
    $result = HttpGet($api_url . '?' . http_build_query($data));
    
    // 记录API响应用于调试
    error_log("爱发电API响应: " . $result);
    
    $result = json_decode($result, true);
    
    // 检查响应是否包含错误
    if (isset($result['ec']) && $result['ec'] != 200) {
        error_log("爱发电API错误: 错误码=" . $result['ec'] . ", 消息=" . $result['em'] . 
                 (isset($result['data']['explain']) ? ", 解释=" . $result['data']['explain'] : "") .
                 (isset($result['data']['debug']) ? ", 调试信息=" . json_encode($result['data']['debug']) : ""));
    }

    // 检查API返回结果是否有效
    if (isset($result['data']) && $result['data'] !== null) {
        $donator['total']     = $result['data']['total_count'];
        $donator['totalPage'] = $result['data']['total_page'];
        $donator['list']      = $result['data']['list'];

        $donatorsHTML = '';
        for ($i = 0; $i < count($donator['list']); $i++) {
            $_donator = $donator['list'][$i];
            $_donator['last_sponsor'] = (empty(end($_donator['sponsor_plans'])['name']) ?
                (empty($_donator['current_plan']['name']) ? array('name' => '') : $_donator['current_plan']) :
                end($_donator['sponsor_plans']));
            
            $donatorsHTML .= '<div class="mdui-col-xs-12 mdui-col-md-6 mdui-m-b-2">
                <div class="mdui-card">
                    <div class="mdui-card-header">
                        <img class="mdui-card-header-avatar" src="' . $_donator['user']['avatar'] . '" />
                        <div class="mdui-card-header-title">' . $_donator['user']['name'] .
                        '&nbsp;&nbsp;&nbsp;&nbsp;共' . $_donator['all_sum_amount'] . '元' . '</div>
                        <div class="mdui-card-header-subtitle">最后发电：' .
                        (empty($_donator['last_sponsor']['name']) ?
                            '暂无' :
                            $_donator['last_sponsor']['name'] . '&nbsp;&nbsp;' . $_donator['last_sponsor']['show_price'] . '元，于 ' . date('Y-m-d H:i:s', $_donator['last_pay_time'])) .
                        '</div>
                    </div>' .
                        (!empty($_donator['last_sponsor']['pic']) ? '
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
        for ($i = 0; $i < $donator['totalPage']; $i++) {
            $pageControlHTML .= '<button onclick="switchPage(' . ($i + 1) . ')" class="mdui-btn ' .
            ($i + 1 == $currentPage ? 'mdui-btn-active mdui-color-theme-accent' : 'mdui-text-color-theme-text') .
            '">' . ($i + 1) . '</button>';
        }
        $pageControlHTML .= '</div>
            <button onclick="switchPage(' . ($currentPage + 1) . ')" class="mdui-btn mdui-btm-raised mdui-ripple mdui-color-theme-accent mdui-float-right"' . ($donator['totalPage'] == 1 ? ' disabled' : '') . '>
                下一页
                <i class="mdui-icon material-icons">keyboard_arrow_right</i>
            </button>
        </div>';

        if (empty($_POST)) {
            // 处理空数据情况
            if (empty($donatorsHTML)) {
                $donatorsHTML = '<div class="mdui-col-xs-12 empty-state"><i class="mdui-icon material-icons">emoji_people</i><p>暂无赞助者</p></div>';
            }
            
$html = <<< HTML
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="stylesheet" href="./css/mdui.min.css" />
        <link rel="stylesheet" href="./css/main.css" />
        <script src="./js/mdui.min.js"></script>
        <title>{$_AFDIAN['pageTitle']}</title>
        <style>
            body {
                background-color: #f5f5f5;
                transition: all 0.3s ease;
            }
            .mdui-card {
                margin-bottom: 16px;
                border-radius: 8px;
                box-shadow: 0 3px 5px -1px rgba(0,0,0,.2), 0 6px 10px 0 rgba(0,0,0,.14), 0 1px 18px 0 rgba(0,0,0,.12);
                transition: all 0.3s cubic-bezier(.25,.8,.25,1);
                overflow: hidden;
            }
            .mdui-card:hover {
                box-shadow: 0 6px 10px -1px rgba(0,0,0,.2), 0 12px 20px 0 rgba(0,0,0,.14), 0 2px 36px 0 rgba(0,0,0,.12);
                transform: translateY(-3px);
            }
            .mdui-card-header {
                padding: 16px;
            }
            .mdui-card-header-avatar {
                border-radius: 50%;
                width: 60px;
                height: 60px;
                object-fit: cover;
                border: 2px solid #fff;
                box-shadow: 0 2px 4px rgba(0,0,0,.1);
            }
            .mdui-card-header-title {
                font-size: 18px;
                font-weight: 500;
                margin-bottom: 4px;
            }
            .mdui-card-header-subtitle {
                font-size: 14px;
                opacity: 0.75;
            }
            .mdui-card-media {
                position: relative;
                overflow: hidden;
            }
            .mdui-card-media img {
                width: 100%;
                transition: transform 0.3s ease;
            }
            .mdui-card:hover .mdui-card-media img {
                transform: scale(1.05);
            }
            #afdian_leaflet {
                width: 100%;
                height: 240px;
                max-width: 800px;
                border-radius: 8px;
                box-shadow: 0 3px 6px rgba(0,0,0,.16);
                margin: 24px auto;
                display: block;
            }
            .page-title {
                position: relative;
                padding-bottom: 8px;
                margin-bottom: 32px;
            }
            .page-title:after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 50%;
                transform: translateX(-50%);
                width: 80px;
                height: 4px;
                background-color: var(--mdui-color-theme);
                border-radius: 2px;
            }
            .mdui-btn-group.-center {
                display: flex;
                justify-content: center;
                margin: 0 16px;
            }
            .mdui-btn {
                min-width: 88px;
                height: 36px;
                border-radius: 4px;
                text-transform: uppercase;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            .mdui-btn-raised {
                box-shadow: 0 3px 1px -2px rgba(0,0,0,.2), 0 2px 2px 0 rgba(0,0,0,.14), 0 1px 5px 0 rgba(0,0,0,.12);
            }
            .mdui-btn-raised:hover {
                box-shadow: 0 2px 4px -1px rgba(0,0,0,.2), 0 4px 5px 0 rgba(0,0,0,.14), 0 1px 10px 0 rgba(0,0,0,.12);
            }
            .mdui-divider {
                margin: 32px 0;
                height: 1px;
                background-color: rgba(0,0,0,.08);
            }
            .mdui-container {
                padding: 16px;
                max-width: 1200px;
                margin: 0 auto;
            }
            .mdui-row {
                margin: -8px;
            }
            [class*=mdui-col] {
                padding: 8px;
            }
            .mdui-appbar {
                box-shadow: 0 2px 4px rgba(0,0,0,.1);
            }
            @media (max-width: 600px) {
                .mdui-container {
                    padding: 8px;
                }
                #afdian_leaflet {
                    height: 200px;
                }
                .page-title {
                    font-size: 24px;
                }
            }
            .empty-state {
                text-align: center;
                padding: 48px 0;
                color: rgba(0,0,0,.6);
            }
            .empty-state i {
                font-size: 64px;
                margin-bottom: 16px;
                opacity: 0.5;
            }
            .mdui-drawer {
                background-color: #fff;
                width: 240px;
                box-shadow: 0 8px 10px -5px rgba(0,0,0,.2), 0 16px 24px 2px rgba(0,0,0,.14), 0 6px 30px 5px rgba(0,0,0,.12);
            }
            .drawer-header {
                height: 180px;
                background-color: var(--mdui-color-theme);
                color: #fff;
                padding: 16px;
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
            }
            .drawer-title {
                font-size: 24px;
                font-weight: 500;
                margin-bottom: 4px;
            }
            .drawer-subtitle {
                font-size: 14px;
                opacity: 0.85;
            }
        </style>
    </head>
    <body class="mdui-appbar-with-toolbar mdui-theme-primary-blue-grey mdui-theme-accent-pink mdui-theme-layout-auto">
        <header class="mdui-appbar mdui-appbar-fixed">
            <div class="mdui-progress mdui-hidden" style="position:absolute;top:0;width:100%" id="mdui_progress">
                <div class="mdui-progress-indeterminate" style="background-color:white"></div>
            </div>
            <div class="mdui-toolbar mdui-color-theme">
                <button class="mdui-btn mdui-btn-icon mdui-ripple" mdui-drawer="{target:'#drawer',swipe:true}">
                    <i class="mdui-icon material-icons">menu</i>
                </button>
                <a href="javascript:;" class="mdui-typo-headline">{$_AFDIAN['pageTitle']}</a>
                <div class="mdui-toolbar-spacer"></div>
                <button class="mdui-btn mdui-btn-icon mdui-ripple" mdui-tooltip="{content: '刷新'}" onclick="location.reload()">
                    <i class="mdui-icon material-icons">refresh</i>
                </button>
            </div>
        </header>

        <drawer class="mdui-drawer mdui-drawer-close" id="drawer">
            <div class="drawer-header">
                <div class="drawer-title">{$_AFDIAN['pageTitle']}</div>
                <div class="drawer-subtitle">感谢您的支持</div>
            </div>
            <div class="mdui-list">
                <a class="mdui-list-item mdui-ripple mdui-list-item-active">
                    <i class="mdui-list-item-icon mdui-icon material-icons">home</i>
                    <div class="mdui-list-item-content">首页</div>
                </a>
                <a href="https://afdian.com/@{$_AFDIAN['userName']}" target="_blank" class="mdui-list-item mdui-ripple">
                    <i class="mdui-list-item-icon mdui-icon material-icons">favorite</i>
                    <div class="mdui-list-item-content">爱发电主页</div>
                </a>
                <div class="mdui-divider"></div>
                <a class="mdui-list-item mdui-ripple" mdui-dialog="{target: '#about-dialog'}">
                    <i class="mdui-list-item-icon mdui-icon material-icons">info</i>
                    <div class="mdui-list-item-content">关于</div>
                </a>
            </div>
        </drawer>

        <main class="mdui-container mdui-typo">
            <h1 class="mdui-text-center page-title">支持我，为我发电</h1>
            <iframe id="afdian_leaflet" class="mdui-center mdui-shadow-5" src="https://afdian.com/leaflet?slug={$_AFDIAN['userName']}" scrolling="no" frameborder="0"></iframe>
            <div class="mdui-divider"></div>
            <h2 class="mdui-text-center page-title">感谢以下小伙伴的发电支持！</h2>
            
            <div class="mdui-m-b-2" id="afdian_sponsors">
                <div class="mdui-row">
                    {$donatorsHTML}
                </div>
                {$pageControlHTML}
            </div>
        </main>

        <div class="mdui-dialog" id="about-dialog">
            <div class="mdui-dialog-title">关于</div>
            <div class="mdui-dialog-content">
                <p>本页面展示了对我的赞助信息，感谢每一位支持我的朋友！</p>
                <p>基于 <a href="https://github.com/miloce/sponsor" target="_blank">afdian-sponsor-page-vercel</a> 开发</p>
            </div>
            <div class="mdui-dialog-actions">
                <button class="mdui-btn mdui-ripple" mdui-dialog-close>关闭</button>
            </div>
        </div>

        <script src="./js/main.js"></script>
        <script>
            // 添加页面加载动画
            document.addEventListener('DOMContentLoaded', function() {
                const progress = document.getElementById('mdui_progress');
                progress.classList.remove('mdui-hidden');
                
                window.setTimeout(function() {
                    progress.classList.add('mdui-hidden');
                }, 1000);
            });
            
            // 初始化所有工具提示
            mdui.mutation();
            
            // 添加卡片动画效果
            const cards = document.querySelectorAll('.mdui-card');
            cards.forEach(function(card, index) {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('mdui-animation-fade-in');
            });
        </script>
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
        $donatorsHTML = '<div class="mdui-col-xs-12 empty-state"><i class="mdui-icon material-icons">emoji_people</i><p>获取发电列表失败，请稍后再试</p></div>';
        $pageControlHTML = '';

        if (empty($_POST)) {
            // 处理空数据情况
$html = <<< HTML
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="stylesheet" href="./css/mdui.min.css" />
        <link rel="stylesheet" href="./css/main.css" />
        <script src="./js/mdui.min.js"></script>
        <title>{$_AFDIAN['pageTitle']}</title>
        <style>
            /* 与上面相同的样式 */
        </style>
    </head>
    <body class="mdui-appbar-with-toolbar mdui-theme-primary-blue-grey mdui-theme-accent-pink mdui-theme-layout-auto">
        <header class="mdui-appbar mdui-appbar-fixed">
            <div class="mdui-progress mdui-hidden" style="position:absolute;top:0;width:100%" id="mdui_progress">
                <div class="mdui-progress-indeterminate" style="background-color:white"></div>
            </div>
            <div class="mdui-toolbar mdui-color-theme">
                <button class="mdui-btn mdui-btn-icon mdui-ripple" mdui-drawer="{target:'#drawer',swipe:true}">
                    <i class="mdui-icon material-icons">menu</i>
                </button>
                <a href="javascript:;" class="mdui-typo-headline">{$_AFDIAN['pageTitle']}</a>
                <div class="mdui-toolbar-spacer"></div>
                <button class="mdui-btn mdui-btn-icon mdui-ripple" mdui-tooltip="{content: '刷新'}" onclick="location.reload()">
                    <i class="mdui-icon material-icons">refresh</i>
                </button>
            </div>
        </header>

        <drawer class="mdui-drawer mdui-drawer-close" id="drawer">
            <div class="drawer-header">
                <div class="drawer-title">{$_AFDIAN['pageTitle']}</div>
                <div class="drawer-subtitle">感谢您的支持</div>
            </div>
            <div class="mdui-list">
                <a class="mdui-list-item mdui-ripple mdui-list-item-active">
                    <i class="mdui-list-item-icon mdui-icon material-icons">home</i>
                    <div class="mdui-list-item-content">首页</div>
                </a>
                <a href="https://afdian.com/@{$_AFDIAN['userName']}" target="_blank" class="mdui-list-item mdui-ripple">
                    <i class="mdui-list-item-icon mdui-icon material-icons">favorite</i>
                    <div class="mdui-list-item-content">爱发电主页</div>
                </a>
                <div class="mdui-divider"></div>
                <a class="mdui-list-item mdui-ripple" mdui-dialog="{target: '#about-dialog'}">
                    <i class="mdui-list-item-icon mdui-icon material-icons">info</i>
                    <div class="mdui-list-item-content">关于</div>
                </a>
            </div>
        </drawer>

        <main class="mdui-container mdui-typo">
            <h1 class="mdui-text-center page-title">支持我，为我发电</h1>
            <iframe id="afdian_leaflet" class="mdui-center mdui-shadow-5" src="https://afdian.com/leaflet?slug={$_AFDIAN['userName']}" scrolling="no" frameborder="0"></iframe>
            <div class="mdui-divider"></div>
            <h2 class="mdui-text-center page-title">感谢以下小伙伴的发电支持！</h2>
            
            <div class="mdui-m-b-2" id="afdian_sponsors">
                <div class="mdui-row">
                    {$donatorsHTML}
                </div>
                {$pageControlHTML}
            </div>
        </main>
HTML;
            echo $html;
        } else {
            $return = array();
            $return['code'] = 400;
            $return['msg']  = "获取发电列表失败，请稍后再试";
            $return['html'] = '<div class="mdui-row">' . $donatorsHTML . "</div>";

            echo json_encode($return);
        }
    }

    function SignAfdian ($token, $params, $userId, $ts) {
        // 按照API文档： sign = md5({token}params{params}ts{ts}user_id{user_id})
        $sign = $token;
        $sign .= 'params' . $params;
        $sign .= 'ts' . $ts;
        $sign .= 'user_id' . $userId;
        return md5($sign);
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
        
        // 设置一个默认的User-Agent
        curl_setopt($ch, CURLOPT_USERAGENT, 'Afdian-PHP-SDK/1.0');
        
        $output = curl_exec($ch);
        
        // 检查是否有错误
        if ($output === false) {
            error_log("CURL错误: " . curl_error($ch) . " (错误码: " . curl_errno($ch) . ")");
        }
        
        // 获取HTTP状态码
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            error_log("HTTP错误: 状态码=" . $http_code . ", URL=" . $url);
        }
        
        curl_close($ch);
        return $output;
    }
?>
