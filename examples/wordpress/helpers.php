<?php
/**
 * Swoole WordPress 辅助函数
 */

/**
 * 初始化 WordPress 环境
 */
function init_wordpress_environment() {
    // 定义 WordPress 必要的常量
    if (!defined('WORDPRESS_ROOT')) {
        throw new Exception('WORDPRESS_ROOT 未定义');
    }
    
    if (!defined('ABSPATH')) {
        define('ABSPATH', WORDPRESS_ROOT . '/');
    }
    
    // 兼容 WordPress 的一些必要设置
    if (!defined('WP_USE_THEMES')) {
        define('WP_USE_THEMES', true);
    }
}

/**
 * 将 Swoole 请求转换为 WordPress 所需的全局变量
 */
function convert_swoole_request_to_globals(Swoole\Http\Request $request) {
    $_SERVER = $request->server ?? [];
    $_GET = $request->get ?? [];
    $_POST = $request->post ?? [];
    $_COOKIE = $request->cookie ?? [];
    $_FILES = $request->files ?? [];
    
    // 特殊处理 SERVER 变量
    $_SERVER['REQUEST_METHOD'] = $request->server['request_method'] ?? 'GET';
    $_SERVER['REQUEST_URI'] = $request->server['request_uri'] ?? '/';
    $_SERVER['QUERY_STRING'] = $request->server['query_string'] ?? '';
    $_SERVER['HTTP_HOST'] = $request->header['host'] ?? '';
    $_SERVER['HTTP_USER_AGENT'] = $request->header['user-agent'] ?? '';
    $_SERVER['REMOTE_ADDR'] = $request->server['remote_addr'] ?? '127.0.0.1';
    
    // 处理多级数组的 POST 数据
    if (isset($request->post) && is_array($request->post)) {
        parse_str(http_build_query($request->post), $_POST);
    }
    
    // 设置 REQUEST 变量
    $_REQUEST = array_merge($_GET, $_POST);
}

/**
 * 处理 WordPress 输出并发送到 Swoole 响应
 */
function handle_wordpress_output(Swoole\Http\Response $response) {
    $content = ob_get_clean();
    
    // 处理 WordPress 设置的 Cookie
    foreach (headers_list() as $header) {
        if (strpos($header, 'Set-Cookie:') === 0) {
            $cookie = substr($header, 12);
            $cookie_parts = explode(';', $cookie);
            $main_part = trim(array_shift($cookie_parts));
            $cookie_kv = explode('=', $main_part, 2);
            
            if (count($cookie_kv) === 2) {
                $name = $cookie_kv[0];
                $value = $cookie_kv[1];
                
                // 提取其他 cookie 参数
                $cookie_params = [];
                foreach ($cookie_parts as $part) {
                    $part = trim($part);
                    if (strtolower($part) === 'httponly') {
                        $cookie_params['httponly'] = true;
                    } elseif (strtolower($part) === 'secure') {
                        $cookie_params['secure'] = true;
                    } elseif (strpos(strtolower($part), 'path=') === 0) {
                        $cookie_params['path'] = substr($part, 5);
                    } elseif (strpos(strtolower($part), 'expires=') === 0) {
                        // 处理过期时间
                        $time = strtotime(substr($part, 8));
                        if ($time !== false) {
                            $cookie_params['expires'] = $time;
                        }
                    }
                }
                
                $response->cookie($name, $value, 
                    $cookie_params['expires'] ?? 0, 
                    $cookie_params['path'] ?? '/', 
                    '', 
                    $cookie_params['secure'] ?? false, 
                    $cookie_params['httponly'] ?? false
                );
            }
        }
    }
    
    // 处理其他头信息
    foreach (headers_list() as $header) {
        if (strpos($header, 'Set-Cookie:') !== 0) {
            $header_parts = explode(':', $header, 2);
            if (count($header_parts) == 2) {
                $name = trim($header_parts[0]);
                $value = trim($header_parts[1]);
                $response->header($name, $value);
            }
        }
    }
    
    // 获取 HTTP 状态码
    $status = http_response_code();
    
    // 设置状态码和响应内容
    $response->status($status ?: 200);
    $response->end($content);
}
