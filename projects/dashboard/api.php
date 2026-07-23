<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Disable long execution times
set_time_limit(5);

/**
 * Perform a quick TCP socket check to verify service health
 */
function check_service($host, $port, $timeout = 0.4) {
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

// 1. Check Container Services Health
$services = [
    'mariadb' => [
        'name' => 'MariaDB Database',
        'host' => 'mariadb',
        'port' => 3306,
        'profile' => 'default',
        'fallback_port' => 3306,
        'description' => 'MySQL/MariaDB relational database engine'
    ],
    'postgres' => [
        'name' => 'PostgreSQL Database',
        'host' => 'postgres',
        'port' => 5432,
        'profile' => 'default',
        'fallback_port' => 5432,
        'description' => 'Advanced relational object database'
    ],
    'redis' => [
        'name' => 'Redis Key-Value Cache',
        'host' => 'redis',
        'port' => 6379,
        'profile' => 'default',
        'fallback_port' => 6379,
        'description' => 'In-memory data structure store & cache'
    ],
    'mailpit' => [
        'name' => 'Mailpit SMTP & UI',
        'host' => 'mailpit',
        'port' => 8025,
        'profile' => 'default',
        'url' => 'http://localhost:8025',
        'description' => 'Local email capture and inbox inspector'
    ],
    'phpmyadmin' => [
        'name' => 'phpMyAdmin',
        'host' => 'phpmyadmin',
        'port' => 80,
        'profile' => 'tools',
        'url' => 'http://localhost:8080',
        'description' => 'Web administration interface for MariaDB'
    ],
    'pgadmin' => [
        'name' => 'pgAdmin 4',
        'host' => 'pgadmin',
        'port' => 80,
        'profile' => 'tools',
        'url' => 'http://localhost:8081',
        'description' => 'Web administration client for PostgreSQL'
    ],
    'redis-commander' => [
        'name' => 'Redis Commander',
        'host' => 'redis-commander',
        'port' => 8081,
        'profile' => 'tools',
        'url' => 'http://localhost:8086',
        'description' => 'Web management dashboard for Redis'
    ],
    'meilisearch' => [
        'name' => 'Meilisearch',
        'host' => 'meilisearch',
        'port' => 7700,
        'profile' => 'tools',
        'url' => 'http://localhost:7700',
        'description' => 'Lightning fast search engine service'
    ],
    'minio' => [
        'name' => 'MinIO Object Storage',
        'host' => 'minio',
        'port' => 9000,
        'profile' => 'tools',
        'url' => 'http://localhost:9001',
        'description' => 'High-performance local S3-compatible storage'
    ]
];

$service_statuses = [];
foreach ($services as $key => $info) {
    $is_healthy = check_service($info['host'], $info['port']);
    $info['status'] = $is_healthy ? 'online' : 'offline';
    $service_statuses[$key] = $info;
}

// 2. PHP Runtimes Status & Xdebug Checks
$runtimes = [
    'php85' => ['name' => 'PHP 8.5 (Experimental)', 'host' => 'php85', 'port' => 9000, 'http_port' => 8085],
    'php84' => ['name' => 'PHP 8.4 (Default)', 'host' => 'php84', 'port' => 9000, 'http_port' => 8084],
    'php83' => ['name' => 'PHP 8.3', 'host' => 'php83', 'port' => 9000, 'http_port' => 8083],
    'php82' => ['name' => 'PHP 8.2', 'host' => 'php82', 'port' => 9000, 'http_port' => 8082],
];

$runtime_statuses = [];
foreach ($runtimes as $key => $rt) {
    $online = check_service($rt['host'], $rt['port']);
    $xdebug_env = getenv('XDEBUG_MODE') ?: 'off';
    if ($key === 'php84') {
        $xdebug_active = extension_loaded('xdebug') && strpos(ini_get('xdebug.mode'), 'debug') !== false;
    } else {
        $xdebug_active = ($xdebug_env === 'debug');
    }
    
    $runtime_statuses[$key] = [
        'name' => $rt['name'],
        'status' => $online ? 'online' : 'offline',
        'http_port' => $rt['http_port'],
        'xdebug' => $xdebug_active ? 'enabled' : 'disabled',
        'xdebug_mode' => $xdebug_env
    ];
}

// 3. Project Directory Scanner
$projects_dir = '/var/www/html';
$projects = [];

if (is_dir($projects_dir)) {
    $items = scandir($projects_dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'dashboard' || strpos($item, '.') === 0) {
            continue;
        }

        $full_path = $projects_dir . '/' . $item;
        if (is_dir($full_path)) {
            // Framework & Stack Detection
            $framework = 'PHP / HTML';
            $has_public = is_dir($full_path . '/public');
            
            if (file_exists($full_path . '/artisan') || file_exists($full_path . '/bootstrap/app.php')) {
                $framework = 'Laravel';
            } elseif (file_exists($full_path . '/wp-config.php') || is_dir($full_path . '/wp-content')) {
                $framework = 'WordPress';
            } elseif (file_exists($full_path . '/bin/console') || file_exists($full_path . '/symfony.lock')) {
                $framework = 'Symfony';
            } elseif (file_exists($full_path . '/vite.config.js') || file_exists($full_path . '/vite.config.ts')) {
                $framework = 'Vite / JS';
            }

            $projects[] = [
                'name' => $item,
                'framework' => $framework,
                'has_public' => $has_public,
                'domains' => [
                    'php85' => "http://{$item}.php85.test",
                    'default' => "http://{$item}.test",
                    'default_ssl' => "https://{$item}.test",
                    'php83' => "http://{$item}.php83.test",
                    'php82' => "http://{$item}.php82.test",
                ],
                'fallback_urls' => [
                    'php85' => "http://localhost:8085/{$item}",
                    'php84' => "http://localhost:8084/{$item}",
                    'php83' => "http://localhost:8083/{$item}",
                    'php82' => "http://localhost:8082/{$item}",
                ],
                'updated_at' => filemtime($full_path)
            ];
        }
    }
}

// Sort projects by recent update
usort($projects, function($a, $b) {
    return $b['updated_at'] - $a['updated_at'];
});

// Response JSON payload
$response = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'environment' => [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Nginx',
        'timezone' => date_default_timezone_get(),
        'projects_count' => count($projects),
    ],
    'services' => $service_statuses,
    'runtimes' => $runtime_statuses,
    'projects' => $projects
];

echo json_encode($response, JSON_PRETTY_PRINT);
