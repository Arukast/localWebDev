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
│   └── conf.d/
│       └── default.conf  <-- Web server virtual host settings
├── php/
│   ├── Dockerfile        <-- Minimal Alpine PHP FPM image build
│   └── php.ini           <-- Secure production PHP settings
├── .env                  <-- Port and DB passwords (ignored by Git)
├── backup.sh             <-- Backup automation script
└── docker-compose.yml    <-- Service orchestrator
```

---

## 🚀 Installation & First Boot

1. **Copy the folder** to your target computer (e.g., to `/home/user/production-system/`).
2. **Move your PHP code** into the `app/` subdirectory (so `app/index.php` is in the right place).
3. **Configure Settings**:
   Open `.env` on the machine and edit your passwords and configurations:
   ```ini
   APP_NAME=my-app
   DB_ROOT_PASSWORD=root_password
   DB_NAME=app_db
   DB_USER=app_user
   DB_PASSWORD=app_password
   ```
4. **Boot up the containers**:
   Run the following commands in the directory terminal:
   ```bash
   sudo docker compose up -d
   ```
5. **Access the Application**:
   Open the browser on the computer and navigate to:
   *   [http://localhost](http://localhost) (or the computer's local IP address from other devices on the same local network).

---

## 🔄 Switching Database Engines (MariaDB vs. PostgreSQL)

By default, the template is configured to use **MariaDB**. If you want to use **PostgreSQL** instead:

1. **In `docker-compose.yml`**:
   * Comment out the `mariadb` service block and the `db_data` volume at the bottom.
   * Uncomment the `postgres` service block and the `pg_data` volume at the bottom.
2. **In `backup.sh`**:
   * Comment out the MariaDB backup command (Option A).
   * Uncomment the PostgreSQL backup command (Option B).
3. **Restart the Stack**:
    *   Run "sudo docker compose up -d" to re-create the database containers.

---

## 🛠️ Web Administration Tools (Optional)

By default, database administration web interfaces are disabled for security and memory optimization. If you need to access them:

1. **In `docker-compose.yml`**:
   * **phpMyAdmin (for MariaDB)**: Uncomment the `phpmyadmin` service block.
   * **pgAdmin (for PostgreSQL)**: Uncomment the `pgadmin` service block and the `pgadmin_data` volume at the bottom.
2. **Accessing the tools**:
   * Ports and credentials can be configured in `.env` (default is `http://localhost:8080` for phpMyAdmin, and `http://localhost:8081` for pgAdmin).
3. **Restart the Stack**:
   * Run `sudo docker compose up -d` to create and launch the web interface container.

---

## 💾 Database Operations

### 1. Manual Backup
To run a manual database backup at any time, run:
```bash
./backup.sh
```
This will generate a compressed `.gz` backup file inside the `backups/` folder.

### 2. Restore a Database SQL file
To import or restore a `.sql` backup file into the database:
```bash
# If the sql file is compressed, unzip it first:
gunzip backups/pos_db_backup_xxxx.sql.gz

# Pipe the database SQL file back into the container:
sudo docker exec -i my-app-database mariadb -u root -pYOUR_ROOT_PASSWORD app_db < backups/pos_db_backup_xxxx.sql
```
*(Replace `my-app-database` and `app_db` with your actual `APP_NAME` and `DB_NAME` values from `.env`)*

---

## ⏰ Scheduling Automated Daily Backups

To automate backups so they run every evening at **10:00 PM**, we can set up a Linux `cron` job:

1. Open the crontab editor on the computer:
   ```bash
   crontab -e
   ```
2. Add the following line at the very bottom of the file (replace `/home/user/production-system` with the actual path of your setup):
   ```text
   0 22 * * * cd /home/user/production-system && ./backup.sh >> backups/backup.log 2>&1
   ```
3. Save and close. The system will now back up the database every night and log the activity in `backups/backup.log`.
