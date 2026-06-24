# Docker-based XAMPP Replacement

This is a modern, modular, and lightweight Docker Compose environment to replace XAMPP. It enables running **PHP 8.2** and **PHP 8.3** side-by-side, contains both **MariaDB** and **PostgreSQL** databases, and includes **phpMyAdmin** and **pgAdmin** web administration interfaces.

---

## 🚀 Quick Start

1. **Navigate to the directory**:
   ```bash
   cd xampp-docker
   ```

2. **Start all services**:
   ```bash
   docker compose up -d
   ```

3. **Check the Dashboard**:
   Open [http://localhost:8082](http://localhost:8082) or [http://localhost:8083](http://localhost:8083) in your web browser.

---

## 🛠️ Port & Service Mapping

| Service | Host URL / Port | Container Port | Managed By | Description |
| :--- | :--- | :--- | :--- | :--- |
| **Nginx (HTTP)** | `http://localhost` (Port 80) | 80 | - | Reverse proxy for `*.test` domains |
| **PHP 8.2 Port** | `http://localhost:8082` | 8082 | `php82` | Runs projects on PHP 8.2 |
| **PHP 8.3 Port** | `http://localhost:8083` | 8083 | `php83` | Runs projects on PHP 8.3 |
| **phpMyAdmin** | `http://localhost:8080` | 80 | - | Web UI for MariaDB |
| **pgAdmin 4** | `http://localhost:8081` | 80 | - | Web UI for PostgreSQL |
| **MariaDB Database**| `localhost:3306` | 3306 | `mariadb` | MariaDB (MySQL compatibility) |
| **PostgreSQL Database**| `localhost:5432` | 5432 | `postgres` | PostgreSQL relational database |

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

## 💡 Database Tips & Best Practices

### 1. Character Encoding & Collation (MariaDB)
To prevent issues displaying emojis or special characters, always use the following configurations:
* **Character Set:** `utf8mb4`
* **Collation:** `utf8mb4_unicode_ci`
*(Note: These defaults have been auto-configured in `docker-compose.yml` for MariaDB).*

### 2. Backup & Restore Databases (CLI)
You can run these commands directly from your host machine's terminal without needing to install CLI database tools:

#### MariaDB
* **Backup/Export database:**
  ```bash
  sudo docker exec -i local-mariadb mariadb-dump -u db_user -pdb_password local_db > mariadb_backup.sql
  ```
* **Restore/Import database:**
  ```bash
  sudo docker exec -i local-mariadb mariadb -u db_user -pdb_password local_db < mariadb_backup.sql
  ```

#### PostgreSQL
* **Backup/Export database:**
  ```bash
  sudo docker exec -t local-postgres pg_dump -U db_user local_db > postgres_backup.sql
  ```
* **Restore/Import database:**
  ```bash
  sudo docker exec -i local-postgres psql -U db_user -d local_db < postgres_backup.sql
  ```

### 3. Connect via Host Machine Terminal
If you have local database clients installed (like `mysql` or `psql`), you can connect directly:
* **Connect to MariaDB:** `mysql -h 127.0.0.1 -P 3306 -u db_user -p`
* **Connect to PostgreSQL:** `psql -h 127.0.0.1 -p 5432 -U db_user -d local_db`

---

## 📁 How to Manage Projects

Your project files live inside the `projects/` directory.

### 1. Default Directory-based Routing (Port Fallback)
If you create a folder named `my-app` inside `projects/` (`projects/my-app/`):
*   Run on **PHP 8.2**: [http://localhost:8082/my-app/](http://localhost:8082/my-app/)
*   Run on **PHP 8.3**: [http://localhost:8083/my-app/](http://localhost:8083/my-app/)

### 2. Custom Domain-based Routing (Optional)
If you want to use custom domains (e.g. `http://my-app.php82.test`), add them to your host machine's `/etc/hosts` file:
```text
127.0.0.1   my-app.php82.test my-app.php83.test
```
Now, they will resolve automatically:
*   [http://my-app.php82.test](http://my-app.php82.test) (Executes via PHP 8.2)
*   [http://my-app.php83.test](http://my-app.php83.test) (Executes via PHP 8.3)

---

## 🔌 Stopping and Managing Services

*   **Stop all services**:
    ```bash
    docker compose down
    ```

*   **Run only selected services** (e.g., to save RAM, running only PHP 8.3 and MariaDB without PostgreSQL or pgAdmin):
    ```bash
    docker compose up -d nginx php83 mariadb phpmyadmin
    ```

*   **View container logs**:
    ```bash
    docker compose logs -f [service_name]
    ```

*   **Rebuild PHP images** (after modifying Dockerfiles or installing new extensions):
    ```bash
    docker compose build --no-cache
    ```
