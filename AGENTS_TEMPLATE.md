# AGENTS.md

## Section 1: Agent Operational Workflow

### 1.1 Startup Workflow
Before writing any code, the agent must complete these steps:
1. Confirm the working directory using `pwd`.
2. Read `claude-progress.md` for the latest verified state and the next required step.
3. Read `feature_list.json` and select the highest-priority unfinished feature.
4. Review recent repository history by checking the last 5 commits using `git log --oneline -5`.
5. Run the initialization script via `./init.sh` or `./dev status`.
6. Run the required smoke or end-to-end verification before starting new work. If baseline verification fails, fix that issue first before stacking new feature work on top of a broken starting state.

### 1.2 Working Rules
1. Focus entirely on one feature at a time.
2. Do not mark a feature complete just because code was added.
3. Keep all changes within the selected feature scope unless a critical blocker forces a narrow supporting fix.
4. Do not silently alter verification rules during implementation.
5. Prefer durable repository artifacts over transient chat summaries.

### 1.3 Required Artifacts
1. `feature_list.json`: The absolute source of truth for tracking feature states.
2. `claude-progress.md`: The continuous session log and current verified status.
3. `init.sh`: The standardized repository startup and verification path.
4. `session-handoff.md`: An optional, compact handoff document reserved for larger development sessions.

### 1.4 Definition Of Done
A feature achieves the status of completed only when all of the following conditions are met:
1. The target behavior is fully implemented.
2. The required verification scripts actually executed successfully.
3. Direct evidence of verification is explicitly recorded in `feature_list.json` or `claude-progress.md`.
4. The repository remains completely restartable from the standard startup path using `./dev up`.

### 1.5 End Of Session Workflow
Before ending a session, the agent must perform these tasks:
1. Update the contents of `claude-progress.md`.
2. Update the tracking states within `feature_list.json`.
3. Explicitly record any unresolved risk, technical debt, or workflow blocker.
4. Commit the changes with a descriptive message adhering to the Conventional Commits specification once the work is in a safe state.
5. Leave the repository clean enough for the next session to execute `./dev up` immediately.

## Section 2: Tech Stack & Architecture

### 2.1 Framework & Core Stack
* Docker Compose multi-container development environment.
* PHP (8.2, 8.3, 8.4, 8.5) + Nginx reverse proxy + Dnsmasq DNS resolver (`*.test`).
* MariaDB, PostgreSQL, Redis, Mailpit, Meilisearch, MinIO.

### 2.2 Directory Layout & Component Roles
* `dev`: Primary CLI control script.
* `docker-compose.yml`: Multi-container service definitions with profile management.
* `nginx/conf.d/default.conf.template`: Dynamic wildcard domain router (`*.test`, `*.php82.test`, `*.php83.test`, `*.php84.test`, `*.php85.test`).
* `php82/`, `php83/`, `php84/`, `php85/`: PHP FPM container build contexts and custom `php.ini` configurations.
* `projects/`: Root host mount directory where individual PHP & Node web applications reside.

---

## Section 3: Privacy, Security & Specifications

### 3.1 Privacy & Environment Safety
1. Do not hardcode personal user data in the source code, comments, or documentation. Always use generic test data.
2. Secrets management: Always use environment variables. Never hardcode or commit keys, passwords, or credentials. Keep `.env` in `.gitignore`.

### 3.2 Security Rules
1. SQL Injection: Use ORM bindings or parameterized queries. Avoid raw SQL concatenation.
2. XSS: Escape dynamic parameters in UI templates using framework-native escaping features.
3. CSRF: Protect state-changing operations with standard security tokens/middleware.

---

## Section 4: Development, Testing & Operations

### 4.1 Coding Standards & Formatting
1. Indentation: 4 spaces for Shell/Bash scripts, 2 spaces for YAML/JSON.
2. Formatting: ShellCheck for bash scripts.
3. Commit conventions: All commits must follow the Conventional Commits specification.

### 4.2 Testing & Portability
1. Verification: Test CLI script syntax using `bash -n ./dev`. Validate compose file using `docker compose config`.
2. Test Isolation: Run container commands through `./dev php`, `./dev composer`, `./dev artisan`.

### 4.3 Containerization & Commands
* `./dev up`: Start core containers.
* `./dev up --tools`: Start core containers + Web GUIs & extra tools.
* `./dev share <project>`: Expose project via ephemeral Cloudflare Tunnel.
* `./dev completion install`: Install shell completion for Bash/Zsh/Fish.
* `./dev db shell <mariadb|postgres>`: Interactive database shell inside container.
