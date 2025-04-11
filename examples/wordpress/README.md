# Swoole WordPress 集成

本示例展示了如何使用 Swoole 作为服务器来运行 WordPress，提高性能和并发处理能力。

## 特性

- 高性能 HTTP 服务器
- 内存常驻运行 WordPress
- 支持 WebSocket 实时功能扩展
- 数据库连接池
- 静态资源缓存
- WordPress 插件兼容性

## 运行要求

- PHP 7.2+
- Swoole 4.4.0+
- WordPress 5.0+
- MySQL/MariaDB

## 使用方法

1. 修改配置文件中的 WordPress 路径：

```php
define('WORDPRESS_ROOT', '/path/to/wordpress');
```

2. 运行服务器：

```bash
php server.php
# 或使用高级服务器
php advanced-server.php
```

3. 访问 WordPress:
   
   打开浏览器，访问 http://localhost:9501

## 性能优化

本实现包含多项性能优化：

1. **静态资源处理**：Swoole 直接处理静态文件请求，无需经过 PHP
2. **内存常驻**：WordPress 核心文件只需加载一次
3. **数据库连接重用**：使用数据库连接池
4. **协程并发**：利用 Swoole 协程处理并发请求
5. **缓存优化**：与外部缓存系统集成

## 已知问题与解决方案

1. **部分插件兼容性问题**：某些假设传统 SAPI 环境的插件可能不兼容
   - 解决方案：使用兼容层或替代插件

2. **内存泄漏**：长时间运行可能导致内存累积
   - 解决方案：定时重启 Worker 进程或使用高级服务器的自动 GC 功能

3. **会话管理**：WordPress 默认会话机制可能不适合常驻内存环境
   - 解决方案：使用外部会话存储如 Redis

## 调试技巧

添加以下代码到 advanced-server.php 可启用调试输出：

```php
$http->set([
    // 其他配置
    'log_level' => SWOOLE_LOG_DEBUG,
]);
```

## 高级功能

1. **WebSocket 支持**：见 `websocket-extension.php` 示例
2. **任务队列**：使用 Swoole Task 实现 WordPress 后台任务
3. **多站点支持**：见 `multisite.php` 示例

## 许可证

MIT
