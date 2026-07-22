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
$runtimes = [
    'php84' => ['name' => 'PHP 8.4 (Default)', 'host' => 'php84', 'port' => 9000, 'http_port' => 8084, 'domain_suffix' => '.test'],
    'php83' => ['name' => 'PHP 8.3', 'host' => 'php83', 'port' => 9000, 'http_port' => 8083, 'domain_suffix' => '.php83.test'],
    'php82' => ['name' => 'PHP 8.2', 'host' => 'php82', 'port' => 9000, 'http_port' => 8082, 'domain_suffix' => '.php82.test'],
    'php85' => ['name' => 'PHP 8.5 (Experimental)', 'host' => 'php85', 'port' => 9000, 'http_port' => 8085, 'domain_suffix' => '.php85.test'],
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
$dir = __DIR__;
if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || strpos($file, '.') === 0) continue;
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
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(20, 26, 42, 0.65);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.18);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.18);
            --warning: #f59e0b;
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.18);
            --accent: #a855f7;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-x: hidden;
            background-image:
                radial-gradient(circle at 10% 15%, rgba(99, 102, 241, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 90% 85%, rgba(168, 85, 247, 0.08) 0%, transparent 40%);
        }

        header {
            padding: 2.25rem 2rem;
            max-width: 1280px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo-section h1 {
            font-size: 1.85rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff 0%, #c084fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .logo-section p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .header-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .php-badge {
            background: var(--primary-glow);
            border: 1px solid var(--primary);
            color: #c7d2fe;
            padding: 0.5rem 1.1rem;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            box-shadow: 0 0 15px var(--primary-glow);
        }

        .xdebug-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            border: 1px solid var(--border-color);
        }
        .xdebug-badge.active { background: rgba(16, 185, 129, 0.15); border-color: var(--success); color: #a7f3d0; }
        .xdebug-badge.inactive { background: rgba(255, 255, 255, 0.03); color: var(--text-muted); }

        main {
            max-width: 1280px;
            margin: 0 auto;
            width: 100%;
            padding: 0 2rem 3rem 2rem;
            flex-grow: 1;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 960px) { main { grid-template-columns: 1fr; } }

        .grid-left, .grid-right { display: flex; flex-direction: column; gap: 2rem; }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.75rem;
            backdrop-filter: blur(12px);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover { box-shadow: 0 8px 30px rgba(0, 0, 0, 0.35); }

        .card-title {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: #fff;
        }

        /* Search input */
        .search-bar {
            margin-bottom: 1.25rem;
            position: relative;
        }
        .search-bar input {
            width: 100%;
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            color: #fff;
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-bar input:focus { border-color: var(--primary); }
        .search-bar svg {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        /* Services Status Grid */
        .services-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .service-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.1rem;
            position: relative;
        }

        .service-name { font-weight: 600; font-size: 0.95rem; margin-bottom: 0.4rem; color: #fff; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 0.5rem;
        }

        .dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .online .dot { background-color: var(--success); box-shadow: 0 0 8px var(--success); }
        .online { color: var(--success); }
        .offline .dot { background-color: var(--danger); box-shadow: 0 0 8px var(--danger); }
        .offline { color: var(--danger); }

        .service-meta {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.4;
            background: rgba(0, 0, 0, 0.2);
            padding: 0.4rem 0.6rem;
            border-radius: 6px;
        }

        /* Projects Section */
        .projects-list { display: flex; flex-direction: column; gap: 0.85rem; }

        .project-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            padding: 1.1rem;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            transition: border-color 0.2s;
        }
        .project-item:hover { border-color: rgba(99, 102, 241, 0.4); }

        .project-header { display: flex; align-items: center; gap: 0.75rem; }
        .project-name { font-weight: 600; font-size: 1rem; color: #fff; }

        .framework-tag {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.55rem;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .tag-Laravel { background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); }
        .tag-WordPress { background: rgba(59, 130, 246, 0.15); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.3); }
        .tag-Symfony { background: rgba(245, 158, 11, 0.15); color: #fde047; border: 1px solid rgba(245, 158, 11, 0.3); }
        .tag-Vite { background: rgba(168, 85, 247, 0.15); color: #e9d5ff; border: 1px solid rgba(168, 85, 247, 0.3); }
        .tag-PHP { background: rgba(99, 102, 241, 0.15); color: #c7d2fe; border: 1px solid rgba(99, 102, 241, 0.3); }

        .project-links { display: flex; gap: 0.75rem; flex-wrap: wrap; }

        .link-group {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 0.3rem 0.5rem;
            border-radius: 8px;
        }
        .link-group-title { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 700; margin-right: 0.2rem; }

        .project-link {
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .link-php84 { color: var(--success); background: var(--success-glow); }
        .link-php84:hover { background: rgba(16, 185, 129, 0.3); }
        .link-php83 { color: var(--accent); background: rgba(168, 85, 247, 0.15); }
        .link-php83:hover { background: rgba(168, 85, 247, 0.3); }
        .link-php82 { color: var(--primary); background: var(--primary-glow); }
        .link-php82:hover { background: rgba(99, 102, 241, 0.3); }
        .link-php85 { color: var(--warning); background: rgba(245, 158, 11, 0.15); }
        .link-php85:hover { background: rgba(245, 158, 11, 0.3); }

        .empty-projects {
            color: var(--text-muted);
            font-size: 0.85rem;
            text-align: center;
            padding: 2rem 0;
            border: 1px dashed var(--border-color);
            border-radius: 12px;
        }

        /* Quick Tools List */
        .tools-list { display: flex; flex-direction: column; gap: 0.75rem; }

        .tool-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            padding: 0.9rem 1.1rem;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-main);
            transition: background 0.2s, border-color 0.2s;
        }
        .tool-item:hover { background: rgba(255, 255, 255, 0.04); border-color: var(--primary); }

        .tool-info h4 { font-size: 0.95rem; font-weight: 600; color: #fff; }
        .tool-info p { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; }

        .tool-link-btn {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-glow);
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
        }

        /* Extension List */
        .extensions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 0.6rem;
        }

        .ext-badge {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            padding: 0.45rem 0.65rem;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
        }
        .ext-badge.active { border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.03); color: #a7f3d0; }
        .ext-badge.inactive { border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.03); color: #fca5a5; }

        /* Guide Panel */
        .guide-box {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.06) 0%, rgba(168, 85, 247, 0.06) 100%);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .guide-step { margin-bottom: 1rem; font-size: 0.85rem; line-height: 1.5; }
        .guide-step:last-child { margin-bottom: 0; }
        .guide-step strong { color: #fff; }

        .code-snippet {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid var(--border-color);
            padding: 0.5rem;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: #c7d2fe;
            margin-top: 0.35rem;
            word-break: break-all;
            cursor: pointer;
        }
        .code-snippet:hover { border-color: var(--primary); }

        footer {
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.8rem;
            border-top: 1px solid var(--border-color);
            max-width: 1280px;
            margin: 0 auto;
            width: 100%;
        }

        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid var(--primary);
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-size: 0.85rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.2s, transform 0.2s;
            pointer-events: none;
            z-index: 100;
        }
        .toast.show { opacity: 1; transform: translateY(0); }
    </style>
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
                                        <a href="http://<?= htmlspecialchars($proj['name']) ?>.test/<?= $proj['suffix'] ?>" class="project-link link-php84" target="_blank">PHP 8.4</a>
                                        <a href="http://<?= htmlspecialchars($proj['name']) ?>.php83.test/<?= $proj['suffix'] ?>" class="project-link link-php83" target="_blank">8.3</a>
                                        <a href="http://<?= htmlspecialchars($proj['name']) ?>.php82.test/<?= $proj['suffix'] ?>" class="project-link link-php82" target="_blank">8.2</a>
                                        <a href="http://<?= htmlspecialchars($proj['name']) ?>.php85.test/<?= $proj['suffix'] ?>" class="project-link link-php85" target="_blank">8.5</a>
                                    </div>
                                    <div class="link-group">
                                        <span class="link-group-title">Port Fallback</span>
                                        <a href="http://localhost:8084/<?= htmlspecialchars($proj['name']) ?>/<?= $proj['suffix'] ?>" class="project-link link-php84" target="_blank">:8084</a>
                                        <a href="http://localhost:8083/<?= htmlspecialchars($proj['name']) ?>/<?= $proj['suffix'] ?>" class="project-link link-php83" target="_blank">:8083</a>
                                        <a href="http://localhost:8082/<?= htmlspecialchars($proj['name']) ?>/<?= $proj['suffix'] ?>" class="project-link link-php82" target="_blank">:8082</a>
                                        <a href="http://localhost:8085/<?= htmlspecialchars($proj['name']) ?>/<?= $proj['suffix'] ?>" class="project-link link-php85" target="_blank">:8085</a>
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
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.5 1z"/></svg>
                    Web Tools & Consoles
                </h2>
                <div class="tools-list">
                    <a href="http://localhost:8025" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>Mailpit Email UI</h4>
                            <p>Inspect outbound application emails</p>
                        </div>
                        <span class="tool-link-btn">Open :8025</span>
                    </a>
                    <a href="http://localhost:8080" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>phpMyAdmin</h4>
                            <p>Web UI for MariaDB database</p>
                        </div>
                        <span class="tool-link-btn">Open :8080</span>
                    </a>
                    <a href="http://localhost:8081" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>pgAdmin 4</h4>
                            <p>Web UI for PostgreSQL database</p>
                        </div>
                        <span class="tool-link-btn">Open :8081</span>
                    </a>
                    <a href="http://localhost:8086" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>Redis Commander</h4>
                            <p>Manage Redis keys & caching</p>
                        </div>
                        <span class="tool-link-btn">Open :8086</span>
                    </a>
                    <a href="http://localhost:9001" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>MinIO Console</h4>
                            <p>Local S3 object storage dashboard</p>
                        </div>
                        <span class="tool-link-btn">Open :9001</span>
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
