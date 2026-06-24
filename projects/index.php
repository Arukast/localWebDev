<?php
// Database connection checks using default settings
$mariadb_status = 'Pending';
$mariadb_error = '';
try {
    $mysql_conn = new PDO("mysql:host=mariadb;port=3306;dbname=local_db;charset=utf8", "db_user", "db_password", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2
    ]);
    $mariadb_status = 'Connected';
} catch (PDOException $e) {
    $mariadb_status = 'Failed';
    $mariadb_error = $e->getMessage();
}

$postgres_status = 'Pending';
$postgres_error = '';
try {
    $pg_conn = new PDO("pgsql:host=postgres;port=5432;dbname=local_db;user=db_user;password=db_password", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2
    ]);
    $postgres_status = 'Connected';
} catch (PDOException $e) {
    $postgres_status = 'Failed';
    $postgres_error = $e->getMessage();
}

// PHP Extensions check
$extensions = ['mysqli', 'pdo_mysql', 'pdo_pgsql', 'pgsql', 'gd', 'zip', 'intl', 'Zend OPcache', 'redis'];
$ext_status = [];
foreach ($extensions as $ext) {
    $ext_status[$ext] = extension_loaded($ext);
}

// Get running directory content for projects list
$projects = [];
$dir = __DIR__;
if (is_dir($dir)) {
    if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
            if ($file != '.' && $file != '..' && is_dir($dir . '/' . $file)) {
                $projects[] = $file;
            }
        }
        closedir($dh);
    }
}
sort($projects);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docker LAMP/LEMP Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(20, 26, 42, 0.6);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.15);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.15);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.15);
            --accent: #a855f7;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

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
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(168, 85, 247, 0.05) 0%, transparent 40%);
        }

        header {
            padding: 2.5rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff 0%, #a855f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo-section p {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .php-badge {
            background: var(--primary-glow);
            border: 1px solid var(--primary);
            color: #c7d2fe;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            box-shadow: 0 0 15px var(--primary-glow);
        }

        main {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            padding: 0 2rem 3rem 2rem;
            flex-grow: 1;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 900px) {
            main {
                grid-template-columns: 1fr;
            }
        }

        .grid-left {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.75rem;
            backdrop-filter: blur(12px);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #fff;
        }

        /* Database Status Grid */
        .db-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 600px) {
            .db-grid {
                grid-template-columns: 1fr;
            }
        }

        .db-status-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            position: relative;
        }

        .db-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .db-badge-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-text {
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .db-details {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.5;
            background: rgba(0, 0, 0, 0.2);
            padding: 0.5rem;
            border-radius: 6px;
        }

        .status-Connected {
            color: var(--success);
        }
        .status-Connected .status-dot {
            background-color: var(--success);
            box-shadow: 0 0 10px var(--success);
        }
        .status-Failed {
            color: var(--danger);
        }
        .status-Failed .status-dot {
            background-color: var(--danger);
            box-shadow: 0 0 10px var(--danger);
        }

        .db-error {
            margin-top: 0.5rem;
            color: var(--danger);
            font-size: 0.7rem;
            word-break: break-all;
        }

        /* Quick Links / Admin Tools */
        .tools-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .tool-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            padding: 1rem;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-main);
            transition: background 0.2s, border-color 0.2s;
        }

        .tool-item:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: var(--primary);
        }

        .tool-info h4 {
            font-size: 0.95rem;
            font-weight: 600;
        }

        .tool-info p {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

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
            gap: 0.75rem;
        }

        .ext-badge {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
        }

        .ext-badge.active {
            border-color: rgba(16, 185, 129, 0.3);
            background: rgba(16, 185, 129, 0.03);
            color: #a7f3d0;
        }

        .ext-badge.inactive {
            border-color: rgba(239, 68, 68, 0.3);
            background: rgba(239, 68, 68, 0.03);
            color: #fca5a5;
        }

        /* Projects Section */
        .projects-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .project-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            padding: 1rem;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .project-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .project-links {
            display: flex;
            gap: 1rem;
        }

        .link-group {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 0.35rem 0.6rem;
            border-radius: 8px;
        }

        .link-group-title {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-right: 0.25rem;
            font-weight: 700;
        }

        .project-link {
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .link-php82 {
            color: var(--primary);
            background: var(--primary-glow);
        }

        .link-php82:hover {
            background: rgba(99, 102, 241, 0.25);
        }

        .link-php83 {
            color: var(--accent);
            background: rgba(168, 85, 247, 0.15);
        }

        .link-php83:hover {
            background: rgba(168, 85, 247, 0.25);
        }

        .empty-projects {
            color: var(--text-muted);
            font-size: 0.85rem;
            text-align: center;
            padding: 1.5rem 0;
            border: 1px dashed var(--border-color);
            border-radius: 12px;
        }

        /* Guide / Instruction Panel */
        .guide-box {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(168, 85, 247, 0.05) 100%);
            border: 1px solid rgba(99, 102, 241, 0.15);
        }

        .guide-step {
            margin-bottom: 1rem;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .guide-step:last-child {
            margin-bottom: 0;
        }

        .guide-step strong {
            color: #fff;
        }

        .code-snippet {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            padding: 0.5rem;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: #fff;
            margin-top: 0.35rem;
            word-break: break-all;
        }

        footer {
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.8rem;
            border-top: 1px solid var(--border-color);
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
    </style>
</head>
<body>

    <header>
        <div class="logo-section">
            <h1>Docker DEV Sandbox</h1>
            <p>XAMPP replacement with Nginx proxy & dual database engines</p>
        </div>
        <div class="php-badge">PHP <?= phpversion() ?></div>
    </header>

    <main>
        <div class="grid-left">
            <!-- Database Connections Status -->
            <div class="card">
                <h2 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-2.239 10-5s-4.477-5-10-5-10 2.239-10 5 4.477 5 10 5z"/><path d="M22 12c0 2.761-4.477 5-10 5S2 14.761 2 12V7c0 2.761 4.477 5 10 5s10-2.239 10-5v5z"/><path d="M22 7c0 2.761-4.477 5-10 5S2 9.761 2 7V3c0 2.761 4.477 5 10 5s10-2.239 10-5v4z"/></svg>
                    Database Engines Status
                </h2>
                <div class="db-grid">
                    <!-- MariaDB -->
                    <div class="db-status-card status-<?= $mariadb_status ?>">
                        <h3 class="db-name">MariaDB (MySQL)</h3>
                        <div class="db-badge-container">
                            <span class="status-dot"></span>
                            <span class="status-text"><?= $mariadb_status ?></span>
                        </div>
                        <div class="db-details">
                            Host: mariadb:3306<br>
                            DB: local_db<br>
                            User: db_user
                        </div>
                        <?php if ($mariadb_status === 'Failed'): ?>
                            <div class="db-error"><?= htmlspecialchars($mariadb_error) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- PostgreSQL -->
                    <div class="db-status-card status-<?= $postgres_status ?>">
                        <h3 class="db-name">PostgreSQL</h3>
                        <div class="db-badge-container">
                            <span class="status-dot"></span>
                            <span class="status-text"><?= $postgres_status ?></span>
                        </div>
                        <div class="db-details">
                            Host: postgres:5432<br>
                            DB: local_db<br>
                            User: db_user
                        </div>
                        <?php if ($postgres_status === 'Failed'): ?>
                            <div class="db-error"><?= htmlspecialchars($postgres_error) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Custom Projects List -->
            <div class="card">
                <h2 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    Your Projects
                </h2>
                <div class="projects-list">
                    <?php if (empty($projects)): ?>
                        <div class="empty-projects">
                            No custom projects detected in <code>xampp-docker/projects/</code> yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                            <div class="project-item">
                                <span class="project-name"><?= htmlspecialchars($project) ?></span>
                                <div class="project-links">
                                    <div class="link-group">
                                        <span class="link-group-title">Domain (.test)</span>
                                        <a href="http://<?= htmlspecialchars($project) ?>.php82.test" class="project-link link-php82" target="_blank">PHP 8.2</a>
                                        <a href="http://<?= htmlspecialchars($project) ?>.php83.test" class="project-link link-php83" target="_blank">PHP 8.3</a>
                                    </div>
                                    <div class="link-group">
                                        <span class="link-group-title">Port Fallback</span>
                                        <a href="http://localhost:8082/<?= htmlspecialchars($project) ?>/" class="project-link link-php82" target="_blank">PHP 8.2</a>
                                        <a href="http://localhost:8083/<?= htmlspecialchars($project) ?>/" class="project-link link-php83" target="_blank">PHP 8.3</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PHP Extensions Panel -->
            <div class="card">
                <h2 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    Loaded PHP Extensions
                </h2>
                <div class="extensions-grid">
                    <?php foreach ($ext_status as $ext => $loaded): ?>
                        <div class="ext-badge <?= $loaded ? 'active' : 'inactive' ?>">
                            <span class="status-dot" style="width:6px; height:6px; background-color: <?= $loaded ? 'var(--success)' : 'var(--danger)' ?>; border-radius:50%"></span>
                            <?= $ext ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid-right" style="display: flex; flex-direction: column; gap: 2rem;">
            <!-- Admin Tools / Web GUIs -->
            <div class="card">
                <h2 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.5 1z"/></svg>
                    Database GUIs
                </h2>
                <div class="tools-list">
                    <a href="http://localhost:8080" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>phpMyAdmin</h4>
                            <p>Manage MariaDB / MySQL</p>
                        </div>
                        <span class="tool-link-btn">Open</span>
                    </a>
                    <a href="http://localhost:8081" target="_blank" class="tool-item">
                        <div class="tool-info">
                            <h4>pgAdmin 4</h4>
                            <p>Manage PostgreSQL</p>
                        </div>
                        <span class="tool-link-btn">Open</span>
                    </a>
                </div>
            </div>

            <!-- Development Guide -->
            <div class="card guide-box">
                <h2 class="card-title" style="color: #c7d2fe;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    Workflow Guide
                </h2>
                <div class="guide-step">
                    <strong>1. Adding a new project:</strong><br>
                    Create a subfolder in <code>projects/</code> (e.g. <code>my-cool-app/</code>).
                </div>
                <div class="guide-step">
                    <strong>2. Accessing your app:</strong><br>
                    - Via ports (no hosts config):<br>
                    PHP 8.2: <a href="http://localhost:8082/my-cool-app/" target="_blank" style="color: var(--primary);">localhost:8082/my-cool-app/</a><br>
                    PHP 8.3: <a href="http://localhost:8083/my-cool-app/" target="_blank" style="color: var(--accent);">localhost:8083/my-cool-app/</a><br>
                    - Via domains (needs <code>/etc/hosts</code>):<br>
                    PHP 8.2: <code>http://my-cool-app.php82.test</code><br>
                    PHP 8.3: <code>http://my-cool-app.php83.test</code>
                </div>
                <div class="guide-step">
                    <strong>3. Mapping Domains (/etc/hosts):</strong>
                    <div class="code-snippet">127.0.0.1 my-cool-app.php82.test my-cool-app.php83.test</div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p>Powered by Docker Compose • Local Dev Environment Sandbox</p>
    </footer>

    <script>
        // Dynamically adjust URLs if accessing via host IP instead of localhost
        const host = window.location.hostname;
        if (host !== 'localhost') {
            document.querySelectorAll('.tool-item').forEach(item => {
                const url = new URL(item.href);
                url.hostname = host;
                item.href = url.toString();
            });
        }
    </script>
</body>
</html>
