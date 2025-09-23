## Test Plan Outline – Water Quality Monitoring & Automation Control

### 1. BACKGROUND
- PHP-based admin portal and REST-like APIs to manage users, ingest water readings, schedule relay operations, and control relays.
- Backend: Apache/PHP on XAMPP, MySQL DB; scheduled jobs via cron-like trigger endpoints.
- Hardware: Arduino-based relay controller (sketches in `relay_control/`), integrated through API commands.

### 2. INTRODUCTION
- Purpose: Define strategy, scope, responsibilities, and criteria for validating functional and non-functional aspects of the system.
- Goals: Ensure accurate data handling, reliable scheduling/automation, secure user operations, and traceable actions.

### 3. ASSUMPTIONS
- Separate test database is available and seeded with sample data.
- APIs are authenticated for sensitive actions; basic session handling is in place.
- Relay hardware can be simulated (mock) or connected in a safe test rig.
- Time-based tests may use fixed-time endpoints (e.g., `api/execute_schedules_fixed.php`).

### 4. TEST ITEMS
- Web UI modules: `admin/dashboard`, `admin/analytics`, `admin/monitor`, `admin/schedule`, `admin/alerts`, `admin/controls`, `admin/user`, `admin/actlogs`.
- Authentication pages: `login/`, `login/logout.php`.
- APIs: `api/create_user.php`, `api/update_user.php`, `api/update_user_status.php`, `api/update_latest_reading.php`, `api/get_readings.php`, `api/check_data.php`, `api/relay_control.php`, `api/automation_control.php`, `api/automation_control_simple.php`, `api/execute_schedules.php`, `api/execute_schedules_fixed.php`, `api/cron_trigger.php`, `api/clear_schedule_logs.php`, `api/upload.php`.
- Migrations/scripts: `run_migration.php`, SQL under `database/`.
- Logs: `logs/schedule_execution.log`, `api/logs/*`.

### 5. FEATURES TO BE TESTED
- Authentication/session control.
- User management CRUD and status.
- Readings ingestion, validation, querying.
- Scheduling: create/update/disable; execution; retention of logs.
- Relay control: manual and automated; idempotency; offline handling.
- Alerts/monitoring/analytics UI rendering and data accuracy.
- Cron/trigger reliability and idempotence.
- File uploads validation and storage security.
- Database migrations/constraints.

### 6. FEATURES NOT TO BE TESTED
- In-depth firmware logic of Arduino sketches beyond API contract (tested via integration only) — out of scope due to different lifecycle.
- Full performance/load testing (only basic responsiveness checks) — constrained by local environment.
- Penetration testing — only basic security validation included.

### 7. APPROACH
- Data flows: Device → `update_latest_reading` → DB → UI/analytics; Schedules → Execute → `relay_control` → hardware → state/logs.
- Test philosophy: API-first functional validation with Postman/cURL; UI verification follows. Use DB assertions for side effects.
- Execution mode: Prefer simulation/mocks for hardware; use `execute_schedules_fixed` for deterministic time tests; live cron via `cron_trigger` for smoke checks.
- Traceability: Map tests to endpoints and log entries; capture evidence (responses, screenshots, log snippets).

### 8. ITEM PASS/FAIL CRITERIA
- Blanket: A test passes when HTTP status and payload match expectations, and DB/log side effects are correct with no PHP warnings/errors.
- Itemized tolerances:
  - API latency: < 500 ms locally for typical requests.
  - Schedule execution: No duplicate runs within same window; logs contain unique run key.
  - Uploads: Only allowed MIME types; no executable files stored; sanitized paths.
  - Readings: Timestamps in UTC; sorted responses; rejects invalid payloads.

### 9. SUSPENSION/RESUMPTION CRITERIA
- Suspend if: DB unavailable; authentication system down; repeated 5xx on core APIs; hardware thrashing observed.
- Resume when: Root cause mitigated and environment restored; DB integrity verified.
- Check-points: After migrations; after seed; after auth tests; after schedule creation; after first relay command.

### 10. TEST DELIVERABLES
- Test Plan (this document).
- Detailed Test Cases CSV (`tests/TEST_CASES.csv`).
- Execution Report (per run): status of each case, evidence links (responses, screenshots), defects list.
- Test data sets and environment configuration notes.

### 11. TESTING TASKS
- Functional: Set up DB and run migrations; seed sample data; configure `.env`/DB; execute API/UI test cases; verify DB/logs; simulate cron; mock hardware.
- Administrative: Manage accounts/tokens; capture and archive evidence; log defects; summarize results; maintain test artifacts in `tests/`.

### 12. ENVIRONMENTAL NEEDS
- Security: Access to local dev machine; no production secrets.
- Hardware/software: Windows 10, XAMPP (Apache/PHP/MySQL), PHP extensions as required, curl/Postman.
- Optional hardware: Arduino relay board on a safe test rig; network access for controller if used.
- Office/equipment: Stable network; ability to schedule background tasks (Windows Task Scheduler) if testing cron.

### 13. RESPONSIBILITIES
- QA Engineer: Plan, execute tests, prepare reports, raise defects.
- Developer: Fix defects, assist with environment setup, provide fixtures/mocks.
- Product/Owner: Approve scope and acceptance criteria; review results.
- Ops (if any): Support logs/backup/restore for test DB.

### 14. STAFFING & TRAINING
- 1 QA familiar with PHP and API testing; 1 Developer for support.
- Training: Short walkthrough of app modules, endpoints, and DB schema; Postman collection overview.

### 15. SCHEDULE
- Day 1: Environment setup, migrations, smoke tests.
- Day 2: Core APIs (auth, users, readings).
- Day 3: Scheduling, cron triggers, retention.
- Day 4: Relay control (mock/live), alerts/monitoring.
- Day 5: UI polish, analytics validation, regression and report.

### 16. RESOURCES
- Tools: Postman, curl, MySQL client (phpMyAdmin/MySQL Workbench), log viewer.
- Artifacts: `tests/TEST_CASES.csv`, sample payloads, seed SQL (`database/09_sample_data.sql`).
- Repositories/paths: Project workspace under `c:\xampp\htdocs\projtest`.

### 17. RISKS & CONTINGENCIES
- Risk: Hardware unavailability → Contingency: Use mock responses/dry-run mode.
- Risk: Time-based flakiness → Contingency: Use fixed-time execution endpoint.
- Risk: Data corruption in shared DB → Contingency: Isolated test DB and reset scripts.
- Risk: Incomplete logging → Contingency: Temporary debug logging for failing flows.

### 18. APPROVALS
- Prepared by: QA Engineer
- Reviewed by: Developer
- Approved by: Product/Owner
- Approval date: [to be filled]
