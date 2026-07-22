# Docker-based XAMPP Replacement

This is a modern, modular, and lightweight Docker Compose environment to replace XAMPP. It enables running **PHP 8.2** and **PHP 8.3** side-by-side, contains both **MariaDB** and **PostgreSQL** databases, **Redis** caching, **Mailpit** email testing tool, **SSL/HTTPS** support, and includes **phpMyAdmin**, **pgAdmin 4**, and **Redis Commander** web administration interfaces.

---

## 🚀 Quick Start

1. **Start all services with the `./dev` helper**:
   ```bash
   ./dev up
   ```
   *(Or using Docker directly: `docker compose up -d`)*

2. **Check the Dashboard / Port Fallbacks**:
   - PHP 8.2: [http://localhost:8082](http://localhost:8082)
   - PHP 8.3: [http://localhost:8083](http://localhost:8083)
   - Mailpit Email UI: [http://localhost:8025](http://localhost:8025)

---

## ⚡ `./dev` CLI Helper Reference

The environment includes a unified `./dev` executable script to simplify daily development workflows:

| Command | Description |
| :--- | :--- |
| `./dev up` | Generate/verify SSL certs and start all Docker containers in background |
| `./dev down` | Stop all Docker containers |
| `./dev restart [service]` | Restart all or specific service containers |
| `./dev status` | Display status of running containers |
| `./dev logs [-f] [service]` | View log output |
| `./dev ssl` | Generate or renew local wildcard SSL certificates (`*.test`) |
| `./dev dns [setup\|status\|teardown]` | Configure automatic cross-platform local DNS resolution for `*.test` domains |
| `./dev new <name> [options]` | Scaffold new project (`--type=laravel\|wordpress\|blank`, `--php=8.3\|8.2`, `--db=mariadb\|postgres`) |
| `./dev list` | List all local projects with their HTTPS domain links and port fallbacks |
| `./dev composer [8.2\|8.3] <args>` | Execute Composer inside PHP container (auto-detects project folder) |
| `./dev artisan [app] <command>` | Run Laravel Artisan command inside target project |
| `./dev php [8.2\|8.3] <args>` | Run PHP CLI inside target container |
| `./dev npm [8.2\|8.3] <args>` | Run NPM command inside PHP container |
| `./dev npx [8.2\|8.3] <args>` | Run NPX command inside PHP container |
| `./dev node [8.2\|8.3] <args>` | Run Node script inside PHP container |
| `./dev db backup <mariadb\|postgres> [file.sql]` | Dump database backup file |
| `./dev db restore <mariadb\|postgres> <file.sql>` | Restore database from SQL dump |
| `./dev completion [bash\|zsh\|fish]` | Output shell autocompletion script |

---

## 🛠️ Port & Service Mapping

| Service | Host URL / Port | Container Port | Managed By | Description |
| :--- | :--- | :--- | :--- | :--- |
| **Nginx (HTTP)** | `http://localhost` (Port 80) | 80 | `nginx` | Reverse proxy for `*.test` HTTP domains |
| **Nginx (HTTPS)** | `https://*.test` (Port 443) | 443 | `nginx` | Reverse proxy for `*.test` HTTPS domains |
| **PHP 8.2 Port** | `http://localhost:8082` | 8082 | `php82` | Runs projects on PHP 8.2 |
| **PHP 8.3 Port** | `http://localhost:8083` | 8083 | `php83` | Runs projects on PHP 8.3 |
| **Mailpit Web UI** | `http://localhost:8025` | 8025 | `mailpit` | Web dashboard to inspect captured emails |
| **Mailpit SMTP** | `127.0.0.1:1025` | 1025 | `mailpit` | Local SMTP server for app emails |
| **phpMyAdmin** | `http://localhost:8080` | 80 | `phpmyadmin` | Web UI for MariaDB |
| **pgAdmin 4** | `http://localhost:8081` | 80 | `pgadmin` | Web UI for PostgreSQL |
| **Redis Commander** | `http://localhost:8084` | 8081 | `redis-commander` | Web UI for Redis cache |
| **MariaDB Database**| `localhost:3306` | 3306 | `mariadb` | MariaDB relational database |
| **PostgreSQL Database**| `localhost:5432` | 5432 | `postgres` | PostgreSQL relational database |
| **Redis Cache** | `localhost:6379` | 6379 | `redis` | In-memory key-value data store |

---

## 🔒 Local SSL / HTTPS Setup (`*.test`)

The environment supports zero-configuration HTTPS for your local custom domains (e.g. `https://my-app.php83.test`).

1. **Automatic Certificate Generation**:
   Running `./dev up` or `./dev ssl` checks if [`mkcert`](https://github.com/FiloSottile/mkcert) is installed on your host system:
   - **With `mkcert`**: Generates browser-trusted certificates in `./nginx/certs/`.
   - **Without `mkcert`**: Automatically falls back to generating a self-signed certificate so HTTPS works out of the box.

2. **Enable trusted certificates** (Optional):
   Install `mkcert` on your host machine (e.g., `sudo apt install mkcert` or `brew install mkcert`) and run:
   ```bash
   mkcert -install
   ./dev ssl
   ```

---

## 🌐 Automatic Wildcard Local DNS (`*.test`)

`localDev` includes an integrated `dnsmasq` container service that routes all `*.test` wildcard domains to `127.0.0.1` without needing to modify `/etc/hosts` for every new project.

1. **One-Time Host Configuration**:
   ```bash
   ./dev dns setup
   ```
   *Automatically configures your host OS resolver (`systemd-resolved` / NetworkManager on Linux, `/etc/resolver/test` on macOS).*

2. **Verify DNS Status**:
   ```bash
   ./dev dns status
   ```

---

## 📧 Mailpit (Local Email Testing)

Configure your PHP applications (e.g. `.env` in Laravel) to route emails through Mailpit:

```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

View sent emails in real time at **[http://localhost:8025](http://localhost:8025)**.

---

## 🔴 Redis Caching

To connect your project to Redis:
```env
REDIS_HOST=redis
REDIS_PORT=6379
```

Manage and inspect Redis keys via **[http://localhost:8084](http://localhost:8084)**.

---

## 🗄️ Database Credentials

### MariaDB (MySQL)
*   **Host**: `mariadb` (inside Docker network) or `127.0.0.1` (from host machine)
*   **Port**: `3306`
*   **Root Password**: `rootpassword`
*   **Database**: `local_db`
*   **User**: `db_user`
*   **Password**: `db_password`

### PostgreSQL
*   **Host**: `postgres` (inside Docker network) or `127.0.0.1` (from host machine)
*   **Port**: `5432`
*   **Database**: `local_db`
*   **User**: `db_user`
*   **Password**: `db_password`

---

## 💡 Database Tips & Backup Shortcuts

Use the `./dev` helper to backup and restore databases easily:

```bash
# Export backup
./dev db backup mariadb mariadb_backup.sql
./dev db backup postgres postgres_backup.sql

# Restore backup
./dev db restore mariadb mariadb_backup.sql
./dev db restore postgres postgres_backup.sql
```

---

## 📁 How to Manage Projects

Your project files live inside the `projects/` directory.

### ⚡ Smart Framework & Laravel Detection
Nginx automatically detects **Laravel** and other modern frameworks by checking for `artisan` or `public/index.php`:
* **Automatic Document Root**: For Laravel projects (`projects/my-laravel-app/`), Nginx automatically routes requests to `projects/my-laravel-app/public/`.
* **Static Assets & Storage Symlinks**: Build assets (`/build/assets/...`), CSS, JS, images, and `storage` symlinks (`storage/app/public`) are served directly as static files in both domain and subfolder modes.
* **Plain PHP Fallback**: Non-Laravel projects with a root `index.php` run seamlessly from the project root.

### 1. Default Directory-based Routing (Port Fallback)
If you create a folder named `my-app` inside `projects/` (`projects/my-app/`):
*   Run on **PHP 8.2**: [http://localhost:8082/my-app/](http://localhost:8082/my-app/)
*   Run on **PHP 8.3**: [http://localhost:8083/my-app/](http://localhost:8083/my-app/)

### 2. Custom Domain-based Routing (HTTPS / HTTP)
Add custom domain entries to your host machine's `/etc/hosts` file:
```text
127.0.0.1   my-app.php82.test my-app.php83.test
```
Now access them via HTTP or trusted HTTPS:
*   [http://my-app.php82.test](http://my-app.php82.test) / [https://my-app.php82.test](https://my-app.php82.test) (PHP 8.2)
*   [http://my-app.php83.test](http://my-app.php83.test) / [https://my-app.php83.test](https://my-app.php83.test) (PHP 8.3)

---

## 🔌 Stopping and Managing Services

*   **Stop all services**:
    ```bash
    ./dev down
    ```

*   **View container logs**:
    ```bash
    ./dev logs -f [service_name]
    ```

*   **Rebuild PHP images**:
    ```bash
    docker compose build --no-cache
    ```
