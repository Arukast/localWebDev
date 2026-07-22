# Setup and Usage Guide

Detailed instructions for installing, configuring, running, and using localDev.

---

## Operating System Compatibility

| Platform | Support | Requirements & Details |
| :--- | :--- | :--- |
| **Linux** | Native | Native Bash & Docker Engine. `./dev dns setup` automatically configures `systemd-resolved` or `NetworkManager`. |
| **macOS** | Native | Native Bash & Docker Desktop / OrbStack. `./dev dns setup` automatically configures `/etc/resolver/test`. Trusted SSL via `mkcert`. |
| **Windows** | WSL2 / Git Bash | Requires **WSL2** (Windows Subsystem for Linux) or **Git Bash** to run `./dev` commands and Docker Desktop (WSL2 backend). Automatic host DNS wildcard resolution (`*.test`) requires manual setup or WSL resolver configuration; port fallbacks (e.g. `http://localhost:8084`) work out of the box. |

---

## Quick Start

1. **Start core services with `./dev`**:
   ```bash
   ./dev up
   ```
   *(To start optional Web GUIs, Meilisearch, and MinIO: `./dev up --tools`)*

2. **Check the Dashboard / Port Fallbacks**:
   - Default PHP (8.4): [https://my-app.test](https://my-app.test) or [http://localhost:8084](http://localhost:8084)
   - PHP 8.3: [https://my-app.php83.test](https://my-app.php83.test) or [http://localhost:8083](http://localhost:8083)
   - PHP 8.2: [https://my-app.php82.test](https://my-app.php82.test) or [http://localhost:8082](http://localhost:8082)
   - PHP 8.5: [https://my-app.php85.test](https://my-app.php85.test) or [http://localhost:8085](http://localhost:8085)
   - Mailpit Email UI: [http://localhost:8025](http://localhost:8025)

---

## CLI Helper (`./dev`) Reference

The environment includes a unified `./dev` executable script to simplify daily development workflows:

| Command | Description |
| :--- | :--- |
| `./dev up [--tools\|--all]` | Generate SSL certs and start core containers (or all tools with `--tools`) |
| `./dev down` | Stop all Docker containers |
| `./dev restart [service]` | Restart all or specific service containers |
| `./dev status` | Display status of running containers |
| `./dev logs [-f] [service]` | View log output |
| `./dev ssl` | Generate or renew local wildcard SSL certificates (`*.test`) |
| `./dev dns [setup\|status\|teardown]` | Configure automatic cross-platform local DNS resolution for `*.test` domains |
| `./dev new <name> [options]` | Scaffold new project (`--type=laravel\|wordpress\|symfony\|vite\|blank`, `--php=8.4\|8.3\|8.2\|8.5`, `--db=mariadb\|postgres`) |
| `./dev list` | List all local projects with their HTTPS domain links and port fallbacks |
| `./dev share <project>` | Share local project publicly over HTTPS using ephemeral Cloudflare Tunnel |
| `./dev composer [8.4\|8.3\|8.2\|8.5] <args>` | Execute Composer inside PHP container (auto-detects project folder) |
| `./dev artisan [app] <command>` | Run Laravel Artisan command inside target project |
| `./dev php [8.4\|8.3\|8.2\|8.5] <args>` | Run PHP CLI inside target container |
| `./dev npm [8.4\|8.3\|8.2\|8.5] <args>` | Run NPM command inside PHP container |
| `./dev npx [8.4\|8.3\|8.2\|8.5] <args>` | Run NPX command inside PHP container |
| `./dev node [8.4\|8.3\|8.2\|8.5] <args>` | Run Node script inside PHP container |
| `./dev db shell <mariadb\|postgres>` | Open interactive database shell inside container |
| `./dev db backup <mariadb\|postgres> [file.sql]` | Dump database backup file |
| `./dev db restore <mariadb\|postgres> <file.sql>` | Restore database from SQL dump |
| `./dev snapshot [save\|restore\|list\|delete]` | Create, list, restore, or delete compressed state snapshots |
| `./dev completion install` | Install shell completions into `~/.zshrc`, `~/.bashrc`, or fish config |

---

## Service & Port Mappings

| Service | Host URL / Port | Container Port | Profile | Description |
| :--- | :--- | :--- | :--- | :--- |
| **Nginx (HTTP)** | `http://localhost` (Port 80) | 80 | `default` | Reverse proxy for `*.test` HTTP domains |
| **Nginx (HTTPS)** | `https://*.test` (Port 443) | 443 | `default` | Reverse proxy for `*.test` HTTPS domains |
| **PHP 8.4 Port** | `http://localhost:8084` | 8084 | `default` | Runs projects on PHP 8.4 (Default) |
| **PHP 8.3 Port** | `http://localhost:8083` | 8083 | `default` | Runs projects on PHP 8.3 |
| **PHP 8.2 Port** | `http://localhost:8082` | 8082 | `default` | Runs projects on PHP 8.2 |
| **PHP 8.5 Port** | `http://localhost:8085` | 8085 | `default` | Runs projects on PHP 8.5 (Experimental) |
| **Mailpit Web UI** | `http://localhost:8025` | 8025 | `default` | Web dashboard to inspect captured emails |
| **Mailpit SMTP** | `127.0.0.1:1025` | 1025 | `default` | Local SMTP server for app emails |
| **MariaDB Database**| `localhost:3306` | 3306 | `default` | MariaDB relational database |
| **PostgreSQL Database**| `localhost:5432` | 5432 | `default` | PostgreSQL relational database |
| **Redis Cache** | `localhost:6379` | 6379 | `default` | In-memory key-value data store |
| **phpMyAdmin** | `http://localhost:8080` | 80 | `tools` | Web UI for MariaDB |
| **pgAdmin 4** | `http://localhost:8081` | 80 | `tools` | Web UI for PostgreSQL |
| **Redis Commander** | `http://localhost:8086` | 8081 | `tools` | Web UI for Redis cache |
| **Meilisearch** | `http://localhost:7700` | 7700 | `tools` | Fast search engine service |
| **MinIO API / Console** | `http://localhost:9000` / `9001` | 9000/9001 | `tools` | Local S3-compatible object storage |

---

## Configuration Details

### Xdebug Step-Debugging
All PHP containers are pre-configured with Xdebug. By default, `XDEBUG_MODE=off` to keep performance native.
1. Set `XDEBUG_MODE=debug` in your `.env` file.
2. Restart PHP container: `./dev restart php84`.
3. Configure your IDE (VSCode or PhpStorm) to listen on port `9003`.

### Local SSL / HTTPS Setup (`*.test`)
1. **Automatic Certificate Generation**:
   Running `./dev up` or `./dev ssl` automatically detects if `mkcert` is installed:
   - **With `mkcert`**: Generates browser-trusted certificates in `./nginx/certs/`.
   - **Without `mkcert`**: Generates self-signed certificates.
2. **Enable Trusted Certificates**:
   Install `mkcert` on your host machine and run:
   ```bash
   mkcert -install
   ./dev ssl
   ```

### Automatic Wildcard Local DNS (`*.test`)
1. **Host Setup**:
   ```bash
   ./dev dns setup
   ```
2. **Check DNS Status**:
   ```bash
   ./dev dns status
   ```

### Testing Harness
```bash
./dev test
./dev test unit
./dev test integration
```
