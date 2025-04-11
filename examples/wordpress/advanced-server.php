<?php
/**
 * Swoole WordPress 高级服务器
 * 
 * 包含内存管理、连接池和热重载功能
 */

require_once __DIR__ . '/helpers.php';

// 定义 WordPress 根目录路径
define('WORDPRESS_ROOT', '/path/to/wordpress');

// 创建 HTTP 服务器
$http = new Swoole\HTTP\Server('0.0.0.0', 9501);

// 服务器配置
$http->set([
    'document_root' => WORDPRESS_ROOT,
    'enable_static_handler' => true,
    'static_handler_locations' => ['/wp-content', '/wp-includes'],
    'worker_num' => swoole_cpu_num(),
    'task_worker_num' => swoole_cpu_num(),
    'max_request' => 1000,
    'buffer_output_size' => 32 * 1024 * 1024, // 32MB
    'package_max_length' => 10 * 1024 * 1024, // 10MB
    'log_level' => SWOOLE_LOG_INFO,
    // 以下是服务器性能调优选项
    'reactor_num' => swoole_cpu_num(),
    'open_tcp_nodelay' => true,
    'tcp_fastopen' => true,
    'max_coroutine' => 3000, // 每个 Worker 进程的最大协程数
]);

// 创建 DB 连接池
$db_pool = new Swoole\Table(1024);
$db_pool->column('connection', Swoole\Table::TYPE_STRING, 128);
$db_pool->column('last_used', Swoole\Table::TYPE_INT, 8);
$db_pool->create();

// 服务器启动事件
$http->on('start', function($server) {
    echo "Swoole WordPress Server 已启动，监听 http://0.0.0.0:9501\n";
});

// Worker 进程启动事件
$http->on('WorkerStart', function($server, $worker_id) {
    // 当前进程是 Worker 进程
    if ($worker_id < $server->setting['worker_num']) {
        echo "Worker #{$worker_id} 已启动\n";
        // 预加载 WordPress 环境
        try {
            require_once WORDPRESS_ROOT . '/wp-load.php';
            
            // 禁用 WordPress 的一些不兼容功能
            if (function_exists('wp_using_ext_object_cache')) {
                wp_using_ext_object_cache(true); // 使用外部对象缓存
            }
            
            // 初始化数据库连接池
            global $wpdb;
            if (isset($wpdb)) {
                echo "WordPress 数据库连接已初始化\n";
            }
        } catch (\Throwable $e) {
            echo "Worker 启动时出错: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Task Worker #{$worker_id} 已启动\n";
    }
});

// 请求处理
$http->on('request', function(Swoole\Http\Request $request, Swoole\Http\Response $response) use ($db_pool) {
    // 启用输出缓冲
    ob_start();
    
    try {
        // 设置全局变量
        convert_swoole_request_to_globals($request);
        
        // 启用协程 Hook
        \Swoole\Runtime::enableCoroutine();
        
        // 初始化 WordPress
        init_wordpress_environment();
        
        // 实际加载 WordPress
        if (file_exists(WORDPRESS_ROOT . '/wp-blog-header.php')) {
            require_once WORDPRESS_ROOT . '/wp-blog-header.php';
        } else {
            throw new Exception('WordPress 核心文件找不到');
        }
        
        // 处理输出并发送响应
        handle_wordpress_output($response);
        
    } catch (\Throwable $e) {
        // 错误处理
        ob_end_clean();
        $response->status(500);
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->end("<h1>服务器错误</h1><p>{$e->getMessage()}</p>");
        
        // 记录错误
        echo "请求处理错误: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    }
    
    // 清理资源
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // 手动触发垃圾回收
    if (mt_rand(1, 100) <= 5) {
        gc_collect_cycles();
    }
});

// 任务处理
$http->on('task', function($server, $task_id, $worker_id, $data) {
    // 处理异步任务
    if (isset($data['type']) && $data['type'] === 'wordpress_cron') {
        try {
            require_once WORDPRESS_ROOT . '/wp-cron.php';
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    return null;
});

// 任务完成回调
$http->on('finish', function($server, $task_id, $data) {
    if (isset($data['success']) && !$data['success']) {
        echo "任务 #{$task_id} 失败: " . ($data['error'] ?? 'Unknown error') . "\n";
    }
});

// 启动服务器
$http->start();
