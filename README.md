# localDev

Modern, Docker-based local development environment designed as an isolated, high-performance replacement for traditional stacks such as XAMPP, MAMP, or Herd.

---

## Overview

`localDev` provides a modular infrastructure tailored for web developers and teams who need multi-version runtime support, enterprise-grade database tools, local SSL, and zero-configuration wildcard DNS routing.

Instead of polluting your host operating system with conflicting PHP, Node.js, or database binaries, `localDev` runs all services inside lightweight container environments managed by Docker Compose and an intuitive helper CLI (`./dev`).

---

## Core Capabilities

- **Multi-Version PHP Runtime**: Run legacy and modern PHP applications side-by-side using isolated PHP 8.2, PHP 8.3, PHP 8.4, and PHP 8.5 containers.
- **Polyglot Database Engine**: Built-in support for MariaDB, PostgreSQL, Redis, Meilisearch, and MinIO object storage.
- **Zero-Configuration HTTPS & DNS**: Automatic local wildcard SSL certificate generation (`*.test`) and OS-level DNS routing via integrated `dnsmasq`.
- **Integrated Mail & Debugging**: Intercept local outgoing emails using Mailpit and debug applications with native Xdebug support on demand.
- **Unified Developer CLI**: Manage services, scaffold projects, perform database operations, create state snapshots, and manage Cloudflare Tunnels using `./dev`.

---

## Case Study: Why Choose `localDev` over Traditional Stacks?

### Scenario: The Multi-Client Freelancer / Agency Environment

#### Context
A developer handles three simultaneous active client projects:
1. **Legacy E-Commerce System**: Requires PHP 8.2 and MariaDB.
2. **Modern SaaS Application**: Built on Laravel using PHP 8.4, PostgreSQL, Redis, Meilisearch, and S3 file uploads.
3. **Internal R&D Tool**: Experiments with PHP 8.5 features.

#### Problem with Traditional Stacks (XAMPP / Native Homebrew / MacPorts)
- **Version Lock-in**: XAMPP limits the machine to a single PHP version at a time. Switching versions requires reinstalling or manually toggling paths.
- **System Pollution**: Installing multiple database engines and search tools locally leads to port conflicts, persistent background daemons, and OS degradation.
- **Inaccurate Mail & SSL Testing**: Local development often lacks HTTPS or sends real emails accidentally due to unmanaged local SMTP setups.
- **Team Inconsistency**: "Works on my machine" issues arise when team members use slightly different host binary versions.

#### Solution with `localDev`
- **Side-by-Side Execution**: All PHP versions run simultaneously in separate containers. Route requests seamlessly via domain aliases (`my-app.test`, `my-app.php83.test`) or direct port mappings (`:8084`, `:8082`).
- **Clean Isolation**: Host system stays completely clean. Tear down containers or create compressed state snapshots using `./dev snapshot save` without altering host state.
- **Production Parity**: Environment mimics containerized production deployments with exact Nginx reverse proxy rules, isolated databases, and S3-compatible local MinIO storage.
- **Safe Development**: Mailpit captures all outbound application mail safely in a local web interface (`http://localhost:8025`).

---

## Documentation Links

For detailed operational guides, setup instructions, and CLI command references, refer to the following documentation files:

- [Setup & Usage Guide](USAGE.md): Quick start steps, system requirements, CLI command reference, and port mappings.
