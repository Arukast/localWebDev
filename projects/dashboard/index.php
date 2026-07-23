<?php
// =========================================================================
// localDev Control Center & Service Dashboard
// =========================================================================

// Disable long execution times
set_time_limit(3);

/**
 * Socket check helper for TCP service health verification
 */
function check_tcp_service($host, $port, $timeout = 0.3) {
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

// 1. Database & Cache Engine Checks
$mariadb_status = "Pending";
$mariadb_error = "";
try {
    $mysql_conn = new PDO(
        "mysql:host=mariadb;port=3306;dbname=local_db;charset=utf8",
        "db_user",
        "db_password",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
    );
    $mariadb_status = "Connected";
} catch (PDOException $e) {
    $mariadb_status = "Failed";
    $mariadb_error = $e->getMessage();
}

$postgres_status = "Pending";
$postgres_error = "";
try {
    $pg_conn = new PDO(
        "pgsql:host=postgres;port=5432;dbname=local_db;user=db_user;password=db_password",
        null,
        null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
    );
    $postgres_status = "Connected";
} catch (PDOException $e) {
    $postgres_status = "Failed";
    $postgres_error = $e->getMessage();
}

$redis_online = check_tcp_service('redis', 6379);
$mailpit_online = check_tcp_service('mailpit', 8025);
$meilisearch_online = check_tcp_service('meilisearch', 7700);
$minio_online = check_tcp_service('minio', 9000);
$phpmyadmin_online = check_tcp_service('phpmyadmin', 80);
$pgadmin_online = check_tcp_service('pgadmin', 80);
$redis_cmd_online = check_tcp_service('redis-commander', 8081);

// 2. PHP Runtimes Status
$default_php_container = getenv('DEFAULT_PHP_VERSION') ?: 'php85';

$runtimes = [
    'php85' => ['name' => 'PHP 8.5', 'host' => 'php85', 'port' => 9000, 'http_port' => 8085, 'domain_suffix' => ($default_php_container === 'php85') ? '.test' : '.php85.test'],
    'php84' => ['name' => 'PHP 8.4', 'host' => 'php84', 'port' => 9000, 'http_port' => 8084, 'domain_suffix' => ($default_php_container === 'php84') ? '.test' : '.php84.test'],
    'php83' => ['name' => 'PHP 8.3', 'host' => 'php83', 'port' => 9000, 'http_port' => 8083, 'domain_suffix' => ($default_php_container === 'php83') ? '.test' : '.php83.test'],
    'php82' => ['name' => 'PHP 8.2', 'host' => 'php82', 'port' => 9000, 'http_port' => 8082, 'domain_suffix' => ($default_php_container === 'php82') ? '.test' : '.php82.test'],
];

$runtime_statuses = [];
foreach ($runtimes as $key => $rt) {
    $online = check_tcp_service($rt['host'], $rt['port']);
    $runtime_statuses[$key] = [
        'name' => $rt['name'],
        'status' => $online ? 'online' : 'offline',
        'http_port' => $rt['http_port'],
        'domain_suffix' => $rt['domain_suffix']
    ];
}

// 3. PHP Extensions & Xdebug check
$extensions = ["mysqli", "pdo_mysql", "pdo_pgsql", "pgsql", "gd", "zip", "intl", "Zend OPcache", "redis", "pcntl", "xdebug"];
$ext_status = [];
foreach ($extensions as $ext) {
    $ext_status[$ext] = extension_loaded($ext);
}
$xdebug_mode = getenv('XDEBUG_MODE') ?: 'off';

// 4. Projects Directory Scanner & Framework Auto-Detection
$projects = [];
$dir = dirname(__DIR__);
if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === 'dashboard' || strpos($file, '.') === 0) continue;
        $project_path = $dir . '/' . $file;
        if (is_dir($project_path)) {
            $framework = 'PHP / HTML';
            if (file_exists($project_path . '/artisan')) {
                $framework = 'Laravel';
            } elseif (file_exists($project_path . '/wp-config.php') || is_dir($project_path . '/wp-content')) {
                $framework = 'WordPress';
            } elseif (file_exists($project_path . '/bin/console')) {
                $framework = 'Symfony';
            } elseif (file_exists($project_path . '/vite.config.js') || file_exists($project_path . '/vite.config.ts')) {
                $framework = 'Vite / JS';
            }

            $has_public = is_dir($project_path . '/public');
            $has_root_index = is_file($project_path . '/index.php');
            $uses_public = $has_public && !$has_root_index;

            $projects[] = [
                'name' => $file,
                'framework' => $framework,
                'uses_public' => $uses_public,
                'suffix' => $uses_public ? 'public/' : '',
                'mtime' => filemtime($project_path)
            ];
        }
    }
}

usort($projects, function($a, $b) {
    return $b['mtime'] - $a['mtime'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>localDev Control Center</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <div class="logo-section">
            <h1>🚀 localDev Control Center</h1>
            <p>Isolated Docker environment &bull; Wildcard DNS &bull; Multi-version PHP</p>
        </div>
        <div class="header-meta">
            <div class="xdebug-badge <?= ($xdebug_mode !== 'off') ? 'active' : 'inactive' ?>">
                Xdebug: <?= strtoupper(htmlspecialchars($xdebug_mode)) ?>
            </div>
            <div class="php-badge">PHP <?= phpversion() ?></div>
        </div>
    </header>

    <main>
        <div class="grid-left">
            <!-- Infrastructure Services Status -->
            <div class="card">
                <h2 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-2.239 10-5s-4.477-5-10-5-10 2.239-10 5 4.477 5 10 5z"/><path d="M22 12c0 2.761-4.477 5-10 5S2 14.761 2 12V7c0 2.761 4.477 5 10 5s10-2.239 10-5v5z"/><path d="M22 7c0 2.761-4.477 5-10 5S2 9.761 2 7V3c0 2.761 4.477 5 10 5s10-2.239 10-5v4z"/></svg>
                    Database & Infrastructure Engines
                </h2>
                <div class="services-status-grid">
                    <!-- MariaDB -->
                    <div class="service-card">
                        <div class="service-name">MariaDB (MySQL)</div>
                        <div class="status-badge <?= ($mariadb_status === 'Connected') ? 'online' : 'offline' ?>">
                            <span class="dot"></span> <?= $mariadb_status ?>
                        </div>
                        <div class="service-meta">Port 3306 &bull; local_db</div>
                    </div>

                    <!-- PostgreSQL -->
                    <div class="service-card">
                        <div class="service-name">PostgreSQL</div>
                        <div class="status-badge <?= ($postgres_status === 'Connected') ? 'online' : 'offline' ?>">
                            <span class="dot"></span> <?= $postgres_status ?>
                        </div>
                        <div class="service-meta">Port 5432 &bull; local_db</div>
                    </div>

                    <!-- Redis -->
                    <div class="service-card">
                        <div class="service-name">Redis Cache</div>
                        <div class="status-badge <?= $redis_online ? 'online' : 'offline' ?>">
                            <span class="dot"></span> <?= $redis_online ? 'Online' : 'Offline' ?>
                        </div>
                        <div class="service-meta">Port 6379 &bull; RAM Store</div>
                    </div>

                    <!-- Mailpit -->
                    <div class="service-card">
                        <div class="service-name">Mailpit SMTP</div>
                        <div class="status-badge <?= $mailpit_online ? 'online' : 'offline' ?>">
                            <span class="dot"></span> <?= $mailpit_online ? 'Online' : 'Offline' ?>
                        </div>
                        <div class="service-meta">Port 1025 &bull; UI 8025</div>
                    </div>

                    <!-- Meilisearch -->
                    <div class="service-card">
                        <div class="service-name">Meilisearch</div>
                        <div class="status-badge <?= $meilisearch_online ? 'online' : 'offline' ?>">
                            <span class="dot"></span> <?= $meilisearch_online ? 'Online' : 'Offline' ?>
                        </div>
                        <div class="service-meta">Port 7700 &bull; Search</div>
                    </div>

                    <!-- MinIO -->
                    <div class="service-card">
                        <div class="service-name">MinIO S3 Storage</div>
                        <div class="status-badge <?= $minio_online ? 'online' : 'offline' ?>">
                            <span class="dot"></span> <?= $minio_online ? 'Online' : 'Offline' ?>
                        </div>
                        <div class="service-meta">Port 9000 &bull; Console 9001</div>
                    </div>
                </div>
            </div>

            <!-- Custom Projects List -->
            <div class="card">
                <h2 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    Your Projects (<?= count($projects) ?>)
                </h2>

                <div class="search-bar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="project-search" placeholder="Filter projects by name or framework..." onkeyup="filterProjects()">
                </div>

                <div class="projects-list" id="projects-container">
                    <?php if (empty($projects)): ?>
                        <div class="empty-projects">
                            No projects detected in <code>./projects/</code> yet.<br>
                            Create one with <code>./dev new my-app --type=laravel</code>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $proj): ?>
                            <div class="project-item" data-name="<?= strtolower(htmlspecialchars($proj['name'])) ?>" data-framework="<?= strtolower($proj['framework']) ?>">
                                <div class="project-header">
                                    <span class="project-name"><?= htmlspecialchars($proj['name']) ?></span>
                                    <span class="framework-tag tag-<?= strtok($proj['framework'], ' /') ?>"><?= htmlspecialchars($proj['framework']) ?></span>
                                </div>
                                <div class="project-links">
                                    <div class="link-group">
                                        <span class="link-group-title">Wildcard Domain</span>
                                        <?php foreach ($runtimes as $rt_key => $rt_val): ?>
                                            <a href="http://<?= htmlspecialchars($proj['name']) ?><?= $rt_val['domain_suffix'] ?>/" class="project-link link-<?= $rt_key ?>" target="_blank"><?= htmlspecialchars($rt_val['name']) ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="link-group">
                                        <span class="link-group-title">Port Fallback</span>
                                        <a href="http://localhost:8085/<?= htmlspecialchars($proj['name']) ?>/" class="project-link link-php85" target="_blank">:8085</a>
                                        <a href="http://localhost:8084/<?= htmlspecialchars($proj['name']) ?>/" class="project-link link-php84" target="_blank">:8084</a>
                                        <a href="http://localhost:8083/<?= htmlspecialchars($proj['name']) ?>/" class="project-link link-php83" target="_blank">:8083</a>
                                        <a href="http://localhost:8082/<?= htmlspecialchars($proj['name']) ?>/" class="project-link link-php82" target="_blank">:8082</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PHP Loaded Extensions -->
            <div class="card">
                <h2 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    PHP Core & Extension Capabilities
                </h2>
                <div class="extensions-grid">
                    <?php foreach ($ext_status as $ext => $loaded): ?>
                        <div class="ext-badge <?= $loaded ? 'active' : 'inactive' ?>">
                            <span class="dot" style="width:6px; height:6px; background-color: <?= $loaded ? 'var(--success)' : 'var(--danger)' ?>;"></span>
                            <?= htmlspecialchars($ext) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid-right">
            <!-- Web GUIs & Tooling Interfaces -->
            <div class="card">
                <h2 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.5 1z"/></svg>
                    Web Tools & Consoles
                </h2>
                <div class="tools-list">
                    <a href="http://localhost:8025" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>Mailpit Email UI</h4>
                            <p>Inspect outbound application emails</p>
                        </div>
                        <span class="tool-link-btn">Open: 8025</span>
                    </a>
                    <a href="http://localhost:8080" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>phpMyAdmin</h4>
                            <p>Web UI for MariaDB database</p>
                        </div>
                        <span class="tool-link-btn">Open: 8080</span>
                    </a>
                    <a href="http://localhost:8081" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>pgAdmin 4</h4>
                            <p>Web UI for PostgreSQL database</p>
                        </div>
                        <span class="tool-link-btn">Open: 8081</span>
                    </a>
                    <a href="http://localhost:8086" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>Redis Commander</h4>
                            <p>Manage Redis keys & caching</p>
                        </div>
                        <span class="tool-link-btn">Open: 8086</span>
                    </a>
                    <a href="http://localhost:9001" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>MinIO Console</h4>
                            <p>Local S3 object storage dashboard</p>
                        </div>
                        <span class="tool-link-btn">Open: 9001</span>
                    </a>
                </div>
            </div>

            <!-- Quick CLI Reference Panel -->
            <div class="card guide-box">
                <h2 class="card-title" style="color: #c7d2fe;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17l6-6-6-6"/><path d="M12 19h8"/></svg>
                    Quick CLI Helpers
                </h2>
                <div class="guide-step">
                    <strong>Scaffold Project:</strong>
                    <div class="code-snippet" onclick="copyText(this)">./dev new my-app --type=laravel</div>
                </div>
                <div class="guide-step">
                    <strong>Run Artisan Command:</strong>
                    <div class="code-snippet" onclick="copyText(this)">./dev artisan my-app migrate</div>
                </div>
                <div class="guide-step">
                    <strong>Run Composer:</strong>
                    <div class="code-snippet" onclick="copyText(this)">./dev composer my-app install</div>
                </div>
                <div class="guide-step">
                    <strong>Database Dump:</strong>
                    <div class="code-snippet" onclick="copyText(this)">./dev db backup mariadb</div>
                </div>
                <div class="guide-step">
                    <strong>Enable Local DNS:</strong>
                    <div class="code-snippet" onclick="copyText(this)">./dev dns setup</div>
                </div>
                <div class="guide-step">
                    <strong>Cloudflare Tunnel Share:</strong>
                    <div class="code-snippet" onclick="copyText(this)">./dev share my-app</div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p>localDev Infrastructure &bull; Powered by Docker & Nginx Reverse Proxy</p>
    </footer>

    <div id="toast" class="toast">Copied to clipboard!</div>

    <script>
        function filterProjects() {
            const query = document.getElementById('project-search').value.toLowerCase();
            const items = document.querySelectorAll('.project-item');
            items.forEach(item => {
                const name = item.getAttribute('data-name');
                const framework = item.getAttribute('data-framework');
                if (name.includes(query) || framework.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function copyText(elem) {
            const text = elem.innerText;
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.getElementById('toast');
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 2000);
            });
        }
    </script>
</body>
</html>
