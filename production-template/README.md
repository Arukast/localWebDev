# Offline Production Environment Template (Docker)

This is a minimized, high-performance, and secure Docker environment template designed for deploying a single-version PHP application on local computers (such as cash registers, office intranet servers, or local display kiosks).

---

## 📦 Directory Structure
Copy this directory structure to your target deployment computer:
```text
production-system/
├── app/                  <-- Put your PHP code files here (index.php, etc.)
├── backups/              <-- Database backups will automatically save here
├── nginx/
│   ├── certs/            <-- Place SSL certificates here (server.crt, server.key)
│   └── conf.d/
│       └── default.conf  <-- Web server virtual host settings & security headers
├── php/
│   ├── Dockerfile        <-- Minimal Alpine PHP FPM image build
│   └── php.ini           <-- Secure production PHP settings & OPcache tuning
├── .env                  <-- Port and DB passwords (ignored by Git)
├── backup.sh             <-- Backup automation script
├── restore.sh            <-- Interactive database restore script
└── docker-compose.yml    <-- Service orchestrator with container healthchecks
```

---

## 🚀 Installation & First Boot

1. **Copy the folder** to your target computer (e.g., to `/home/user/production-system/`).
2. **Move your PHP code** into the `app/` subdirectory (so `app/index.php` is in the right place).
3. **Configure Settings**:
   Copy `.env.example` to `.env` and edit your passwords and configurations:
   ```ini
   APP_NAME=my-app
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
   *   `http://localhost:8181` (or your configured `APP_PORT`).

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

3. **Enable SSL Block in `nginx/conf.d/default.conf`**:
   Uncomment the `server { listen 443 ssl http2; ... }` configuration block at the bottom of `default.conf`.

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

## 💾 Database Operations

### 1. Manual Backup
To run a manual database backup at any time:
```bash
./backup.sh
```
This will generate a compressed `${APP_NAME}_db_backup_YYYYMMDD_HHMMSS.sql.gz` backup file inside the `backups/` folder and prune backups older than 30 days.

### 2. Interactive Database Restore
To restore a database from an existing backup:

```bash
# Interactive selection menu:
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

