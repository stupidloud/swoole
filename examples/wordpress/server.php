<?php
/**
 * Swoole WordPress 集成示例
 * 
 * 这个脚本创建一个 Swoole HTTP 服务器来运行 WordPress
 */

// 定义 WordPress 根目录路径，需要根据实际情况修改
define('WORDPRESS_ROOT', '/path/to/wordpress');

// 创建 HTTP 服务器
$http = new Swoole\HTTP\Server('0.0.0.0', 9501);

$http->set([
    'document_root' => WORDPRESS_ROOT,
    'enable_static_handler' => true,
    'worker_num' => swoole_cpu_num() * 2,
    'max_request' => 1000,
    'package_max_length' => 10 * 1024 * 1024, // 10MB
]);

// 在 Worker 进程启动时加载 WordPress 环境
$http->on('WorkerStart', function($server, $worker_id) {
    // 预加载一些常用的 WordPress 文件
    require_once WORDPRESS_ROOT . '/wp-load.php';
});

// 处理 HTTP 请求
$http->on('request', function(Swoole\Http\Request $request, Swoole\Http\Response $response) {
    // 启用输出缓冲
    ob_start();
    
    try {
        // 设置必要的全局变量
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
        
        // 初始化 WordPress 环境并处理请求
        if (!defined('ABSPATH')) {
            define('WP_USE_THEMES', true);
            require_once WORDPRESS_ROOT . '/wp-blog-header.php';
        } else {
            // 如果 ABSPATH 已定义，则直接处理请求
            require_once WORDPRESS_ROOT . '/index.php';
        }
        
        // 获取缓冲内容并发送响应
        $content = ob_get_clean();
        
        // 发送 WordPress 生成的头信息
        foreach (headers_list() as $header) {
            $header_parts = explode(':', $header, 2);
            if (count($header_parts) == 2) {
                $name = trim($header_parts[0]);
                $value = trim($header_parts[1]);
                $response->header($name, $value);
            }
        }
        
        // 发送内容和状态码
        $status = http_response_code();
        $response->status($status ?: 200);
        $response->end($content);
        
    } catch (\Throwable $e) {
        // 错误处理
        ob_end_clean();
        $response->status(500);
        $response->end("Server Error: " . $e->getMessage());
    }
});

echo "Swoole WordPress Server started at http://0.0.0.0:9501\n";
$http->start();
