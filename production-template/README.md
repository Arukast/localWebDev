# Offline Production Environment Template (Docker)

This is a minimized, high-performance, hardened, and secure Docker environment template designed for deploying single-version PHP applications (standard flat PHP or frameworks like Laravel and Symfony) on production systems, intranet servers, edge nodes, or display kiosks.

---

## 📦 Directory Structure
Copy this directory structure to your target deployment computer:
```text
production-system/
├── .github/
│   └── workflows/
│       └── ci.yml        <-- GitHub Actions CI workflow (compose config, shellcheck, Docker build)
├── app/                  <-- Put your PHP code files here (index.php, public/, etc.)
├── backups/              <-- Database backups (.sql.gz & .sha256) will automatically save here
├── nginx/
│   ├── certs/            <-- Place SSL certificates here (server.crt, server.key)
│   └── templates/
│       └── default.conf.template  <-- Nginx virtual host template with ${NGINX_WEB_ROOT} support
├── php/
│   ├── Dockerfile        <-- Minimal Alpine PHP 8.3 FPM image with Composer 2 & non-root user
│   └── php.ini           <-- Secure production PHP settings & OPcache tuning
├── .env                  <-- Port, web root, and DB passwords (ignored by Git)
├── backup.sh             <-- Backup automation script with SHA256 integrity generation
├── restore.sh            <-- Interactive database restore script with SHA256 checksum verification
└── docker-compose.yml    <-- Service orchestrator with healthchecks, log rotation, & memory limits
```

---

## 🚀 Installation & First Boot

1. **Copy the folder** to your target computer (e.g., to `/home/user/production-system/`).
2. **Move your PHP code** into the `app/` subdirectory (so `app/index.php` or `app/public/index.php` is in place).
3. **Configure Settings**:
   Copy `.env.example` to `.env` and edit your passwords and configurations:
   ```ini
   APP_NAME=my-app
   # Document Root: set /var/www/html for standard flat PHP, or /var/www/html/public for Laravel/Symfony
   NGINX_WEB_ROOT=/var/www/html
   DB_ROOT_PASSWORD=strong_production_root_password
   DB_NAME=app_production_db
   DB_USER=app_production_user
   DB_PASSWORD=strong_production_user_password
   ```
4. **Boot up the containers**:
   Run the following command in the terminal:
   ```bash
   sudo docker compose up -d
   ```
5. **Access the Application**:
   Open the browser on the computer and navigate to:
   * `http://localhost:8181` (or your configured `APP_PORT`).

---

## 🔒 Security & Performance Hardening

* **Non-Root Runtime Execution**: The PHP-FPM container executes processes under the `www-data` non-root user account to mitigate container breakout risks.
* **Dynamic Web Root Support**: Set `NGINX_WEB_ROOT=/var/www/html/public` in `.env` when deploying frameworks like Laravel or Symfony to prevent exposing root application files.
* **Log Rotation Safeguards**: All containers use Docker's `json-file` logging driver with `max-size: 10m` and `max-file: 3` to prevent log files from exhausting host storage.
* **Resource Limits**: Every container is bounded with strict memory constraints (e.g. PHP 512MB, MariaDB 1024MB, Nginx 256MB) to ensure system stability.
* **Composer 2 Integration**: Composer 2 is built into the PHP FPM container, allowing you to manage packages or run `docker compose exec php composer install`.

---

## 🔒 HTTPS / SSL Configuration (Optional)

To enable SSL / HTTPS:

1. **Place SSL Certificate Files**:
   Copy your SSL certificate and private key into `nginx/certs/`:
   - `nginx/certs/server.crt`
   - `nginx/certs/server.key`

2. **Enable Port 443 & Volume in `docker-compose.yml`**:
   Uncomment the SSL port and volume mapping under `nginx`:
   ```yaml
   ports:
     - "${APP_PORT}:80"
     - "${APP_SSL_PORT:-443}:443"
   volumes:
     - ./nginx/certs:/etc/nginx/certs:ro
   ```

3. **Enable SSL Block in `nginx/templates/default.conf.template`**:
   Uncomment the `server { listen 443 ssl http2; ... }` configuration block at the bottom of `default.conf.template`.

4. **Restart Stack**:
   ```bash
   sudo docker compose restart nginx
   ```

---

## 🔄 Switching Database Engines (MariaDB vs. PostgreSQL)

By default, the template is configured to use **MariaDB**. If you want to use **PostgreSQL** instead:

1. **In `docker-compose.yml`**:
   * Comment out the `mariadb` service block and the `db_data` volume at the bottom.
   * Uncomment the `postgres` service block and the `pg_data` volume at the bottom.
2. **In `backup.sh` & `restore.sh`**:
   * Comment out Option A (MariaDB) and uncomment Option B (PostgreSQL).
3. **Restart the Stack**:
   * Run `sudo docker compose up -d` to create the PostgreSQL containers.

---

## ⚡ Redis In-Memory Cache (Optional)

If your application requires Redis caching or session storage:

1. **In `docker-compose.yml`**:
   * Uncomment the `redis` service block.
2. **Restart the Stack**:
   * Run `sudo docker compose up -d`.

---

## 🛠️ Web Administration Tools (Optional)

By default, database administration web interfaces are disabled for security and memory optimization. If you need to access them:

1. **In `docker-compose.yml`**:
   * **phpMyAdmin (for MariaDB)**: Uncomment the `phpmyadmin` service block.
   * **pgAdmin (for PostgreSQL)**: Uncomment the `pgadmin` service block and the `pgadmin_data` volume at the bottom.
2. **Accessing the tools**:
   * Ports and credentials can be configured in `.env` (default is `http://localhost:8880` for phpMyAdmin, and `http://localhost:8881` for pgAdmin).
3. **Restart the Stack**:
   * Run `sudo docker compose up -d` to launch the web interface container.

---

## 💾 Database Operations & Checksum Integrity

### 1. Manual Backup
To run a manual database backup at any time:
```bash
./backup.sh
```
This will generate:
- A compressed backup: `${APP_NAME}_db_backup_YYYYMMDD_HHMMSS.sql.gz`
- A SHA256 checksum file: `${APP_NAME}_db_backup_YYYYMMDD_HHMMSS.sql.gz.sha256`
- Secure file permissions (`chmod 600`)
- Auto-prune backups and checksum files older than 30 days.

### 2. Interactive Database Restore
To restore a database from an existing backup:

```bash
# Interactive selection menu (automatically verifies SHA256 checksum prior to restore):
./restore.sh

# Or specify a backup file directly:
./restore.sh backups/my-app_db_backup_20260722_120000.sql.gz

# Skip confirmation prompt:
./restore.sh -y backups/my-app_db_backup_20260722_120000.sql.gz
```

---

## ⏰ Scheduling Automated Daily Backups

To automate backups so they run every evening at **10:00 PM**, set up a Linux `cron` job:

1. Open the crontab editor on the computer:
   ```bash
   crontab -e
   ```
2. Add the following line at the very bottom (replace `/home/user/production-system` with your actual setup path):
   ```text
   0 22 * * * cd /home/user/production-system && ./backup.sh >> backups/backup.log 2>&1
   ```
3. Save and close. The system will now back up the database every night and log activity to `backups/backup.log`.
