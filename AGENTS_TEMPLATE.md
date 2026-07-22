# AGENTS.md

## Section 1: Agent Operational Workflow

### 1.1 Startup Workflow
Before writing any code, the agent must complete these steps:
1. Confirm the working directory using `pwd`.
2. Read `claude-progress.md` for the latest verified state and the next required step.
3. Read `feature_list.json` and select the highest-priority unfinished feature.
4. Review recent repository history by checking the last 5 commits using `git log --oneline -5`.
5. Run the initialization script via `./init.sh`.
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
4. The repository remains completely restartable from the standard startup path using `./init.sh`.

### 1.5 End Of Session Workflow
Before ending a session, the agent must perform these tasks:
1. Update the contents of `claude-progress.md`.
2. Update the tracking states within `feature_list.json`.
3. Explicitly record any unresolved risk, technical debt, or workflow blocker.
4. Commit the changes with a descriptive message adhering to the Conventional Commits specification once the work is in a safe state.
5. Leave the repository clean enough for the next session to execute `./init.sh` immediately.

## Section 2: Tech Stack & Architecture

### 2.1 Framework & Core Stack
* [Insert framework, languages, and core versions here, e.g., "Built with React/Next.js, TypeScript, and PostgreSQL"]

### 2.2 Directory Layout & Component Roles
* [Describe layout structure, e.g., where logic, components, routes, database assets reside]

### 2.3 Role-Based Access Control & Scoping (If Applicable)
* [Describe user roles, multi-tenancy rules, and permissions protocols]

### 2.4 Service & Business Logic Layer
* [Define architectural boundaries, e.g., separating database/API views from domain logic]

## Section 3: Privacy, Security & Specifications

### 3.1 Privacy & Environment Safety
1. Do not hardcode personal user data in the source code, comments, or documentation. Always use generic test data.
2. Secrets management: Always use environment variables. Never hardcode or commit keys, passwords, or credentials. Keep `.env` (or equivalent) in `.gitignore`.

### 3.2 Security Rules
1. SQL Injection: Use ORM bindings or parameterized queries. Avoid raw SQL concatenation.
2. XSS: Escape dynamic parameters in UI templates using framework-native escaping features.
3. CSRF: Protect state-changing operations with standard security tokens/middleware.

### 3.3 Database Status & State Codes (If Applicable)
* [Document status keys, integer state codes, or state machine flows here]

## Section 4: Development, Testing & Operations

### 4.1 Coding Standards & Formatting
1. Indentation: [Specify rules, e.g., "2 spaces for JSON/JS, 4 spaces for Python"]
2. Formatting: [Specify linter/formatter tools, e.g., Prettier, ESLint, Black, Ruff]
3. Commit conventions: All commits must follow the Conventional Commits specification.

### 4.2 Testing & Portability
1. Verification: [Define commands to run tests and linters, e.g., "npm run test" or "pytest"]
2. Test Isolation: Ensure tests run against a mock, in-memory, or isolated test database to prevent mutation of development or production data.
3. Regression Prevention: Every new feature or bug fix must include corresponding tests.

### 4.3 Containerization & Ports (If Applicable)
* [Specify container commands like docker exec prefix, port mappings, and build/deploy steps]
