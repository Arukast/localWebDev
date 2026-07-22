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
│   └── templates/
│       └── default.conf.template  <-- Nginx virtual host template with SSL, Gzip, and ${NGINX_WEB_ROOT} support
├── php/
│   ├── Dockerfile        <-- Minimal Alpine PHP 8.3 FPM image with Composer 2 & non-root user
│   └── php.ini           <-- Secure production PHP settings & OPcache tuning
├── .env                  <-- Port, web root, domain, and DB passwords (ignored by Git)
├── backup.sh             <-- Backup automation script with SHA256 integrity & optional S3 upload
├── restore.sh            <-- Interactive database restore script with SHA256 checksum verification
├── init-ssl.sh           <-- Automated Let's Encrypt SSL/TLS certificate bootstrap script
└── docker-compose.yml    <-- Service orchestrator with healthchecks, log rotation, & memory limits
```

---

## 🚀 Installation & First Boot

1. **Copy the folder** to your target computer (e.g., to `/home/user/production-system/`).
2. **Move your PHP code** into the `app/` subdirectory (so `app/index.php` or `app/public/index.php` is in place).
3. **Configure Settings**:
   Copy `.env.example` to `.env` and edit your domain, passwords, and configurations:
   ```ini
   APP_NAME=my-app
   DOMAIN_NAME=app.yourdomain.com
   CERTBOT_EMAIL=admin@yourdomain.com
   # Document Root: set /var/www/html for standard flat PHP, or /var/www/html/public for Laravel/Symfony
   NGINX_WEB_ROOT=/var/www/html
   DB_ROOT_PASSWORD=strong_production_root_password
   DB_NAME=app_production_db
   DB_USER=app_production_user
   DB_PASSWORD=strong_production_user_password
   REDIS_PASSWORD=strong_redis_production_password
   ```
4. **Boot up the containers**:
   Run the following command in the terminal:
   ```bash
   sudo docker compose up -d
   ```
5. **Access the Application**:
   Open the browser on the computer and navigate to:
   * `http://localhost` (or `https://app.yourdomain.com`).

---

## 🔒 Automated SSL / HTTPS Configuration (Let's Encrypt)

The production template includes automated Let's Encrypt SSL/TLS certificate provisioning and renewal:

1. **Configure Domain & Email in `.env`**:
   Ensure `DOMAIN_NAME` (e.g. `app.yourdomain.com`) and `CERTBOT_EMAIL` (e.g. `admin@yourdomain.com`) are configured in `.env`.

2. **Initialize Certificates**:
   Run the automated SSL bootstrapper:
   ```bash
   ./init-ssl.sh
   ```
   *To test certificate acquisition without hitting Let's Encrypt rate limits, pass `--staging`:*
   ```bash
   ./init-ssl.sh --staging
   ```

3. **Automatic Renewal**:
   The `certbot` container service automatically runs in the background and attempts to renew certificates every 12 hours.

---

## 🔒 Security & Performance Hardening

* **Automated HTTPS & HSTS**: Enforces Strict-Transport-Security (`HSTS`) headers and redirects all HTTP traffic (port 80) to secure HTTPS (port 443).
* **Nginx Gzip Compression**: Gzip compression is enabled for text, CSS, JavaScript, JSON, XML, and SVG assets to accelerate asset loading.
* **Non-Root Runtime Execution**: The PHP-FPM container executes processes under the `www-data` non-root user account to mitigate container breakout risks.
* **Redis Password Protection**: Redis authentication is enforced using `${REDIS_PASSWORD}` when the Redis service is enabled.
* **Dynamic Web Root Support**: Set `NGINX_WEB_ROOT=/var/www/html/public` in `.env` when deploying frameworks like Laravel or Symfony to prevent exposing root application files.
* **Log Rotation Safeguards**: All containers use Docker's `json-file` logging driver with `max-size: 10m` and `max-file: 3` to prevent log files from exhausting host storage.
* **Resource Limits**: Every container is bounded with strict memory constraints (e.g. PHP 512MB, MariaDB 1024MB, Nginx 256MB) to ensure system stability.
* **Composer 2 Integration**: Composer 2 is built into the PHP FPM container, allowing you to manage packages or run `docker compose exec php composer install`.

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
2. **In `.env`**:
   * Ensure `REDIS_PASSWORD` is configured.
3. **Restart the Stack**:
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

## 💾 Database Operations & Offsite S3 Storage

### 1. Manual Backup & S3 Upload
To run a database backup at any time:
```bash
./backup.sh
```
This will generate:
- A compressed backup: `${APP_NAME}_db_backup_YYYYMMDD_HHMMSS.sql.gz`
- A SHA256 checksum file: `${APP_NAME}_db_backup_YYYYMMDD_HHMMSS.sql.gz.sha256`
- Secure file permissions (`chmod 600`)
- Auto-prune backups and checksum files older than 30 days.
- **Optional S3 Offsite Storage**: If `S3_BUCKET` is configured in `.env` and `aws` CLI is installed, `backup.sh` will automatically upload the backup and checksum file to your remote S3 object storage bucket.

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

