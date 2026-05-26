# local_asyncwatch — AsyncWatch for Moodle (Async Progress Monitor)

A Moodle local plugin that monitors asynchronous learner progress by:

1. Grouping course activities into logical **Parts**
2. Defining **Rules** (e.g. "3 of 9 parts complete by Date X")
3. **Reporting** per-learner progress with OK / ⚠ At-risk / ✗ Behind status
4. Automatically **emailing** learners who are at risk or have missed a deadline

---

## Requirements

| Item | Version |
|------|---------|
| Moodle | 4.1+ (build 2022112800+) |
| PHP | 8.0+ |
| Activity completion | Must be enabled site-wide (`$CFG->enablecompletion`) |

---

## Installation

```bash
# Place the plugin folder in your Moodle codebase
cp -r local_asyncwatch /path/to/moodle/local/asyncwatch

# Then visit Site Administration → Notifications to run the DB installer,
# or run via CLI:
php admin/cli/upgrade.php
```

---

## File structure

```
local/asyncwatch/
├── version.php                   Plugin metadata
├── lib.php                       Course navigation hook
├── manage.php                    Admin UI: Parts & Rules tabs
├── report.php                    Progress report + CSV export
│
├── db/
│   ├── install.xml               Database schema (4 tables)
│   ├── access.php                Capabilities
│   └── tasks.php                 Scheduled task registration
│
├── classes/
│   ├── helper.php                Core data access & progress calc
│   ├── form/
│   │   ├── part_form.php         Moodle form: create/edit Part
│   │   └── rule_form.php         Moodle form: create/edit Rule
│   └── task/
│       └── check_progress.php    Hourly cron task (emails)
│
├── amd/src/
│   └── activity_filter.js        Live filter for activity selector
│
└── lang/en/
    └── local_asyncwatch.php      English strings
```

---

## Database tables

### `asyncwatch_parts`
One row per logical "Part" within a course.

| Column | Type | Notes |
|--------|------|-------|
| id | INT | PK |
| courseid | INT | FK → course |
| name | VARCHAR(255) | Display label |
| sortorder | INT | Display order |

### `asyncwatch_part_activities`
Maps course modules to Parts (many activities per part, one part per cm per course).

| Column | Type | Notes |
|--------|------|-------|
| partid | INT | FK → asyncwatch_parts |
| cmid | INT | FK → course_modules |

### `asyncwatch_rules`
Completion threshold rules.

| Column | Type | Notes |
|--------|------|-------|
| courseid | INT | FK → course |
| name | VARCHAR(255) | e.g. "Checkpoint 1" |
| parts_required | INT | How many parts must be done |
| deadline | INT | Unix timestamp |
| warn_hours | INT | Hours before deadline for yellow warning (0 = off) |
| enabled | TINYINT | 1 = active |

### `asyncwatch_notifications`
Idempotent log — prevents duplicate emails.

| Column | Type | Notes |
|--------|------|-------|
| ruleid | INT | FK → asyncwatch_rules |
| userid | INT | FK → user |
| type | VARCHAR(20) | `'warning'` or `'breach'` |
| timesent | INT | Unix timestamp |

---

## How progress is calculated

A **Part** is considered **complete** when every activity assigned to it has a
Moodle completion state of `COMPLETION_COMPLETE` (1) or `COMPLETION_COMPLETE_PASS` (2).

> ⚠ **Important:** Activities with completion tracking disabled
> (`completion = COMPLETION_TRACKING_NONE`) will **never** register as complete.
> The Part form warns admins and provides a filter to hide untracked activities.

---

## Scheduled task

The task `\local_asyncwatch\task\check_progress` runs **hourly** and:

1. Iterates every enabled rule across all courses.
2. For each enrolled learner, calculates their part-completion count.
3. Sends a **warning** email if: inside the `warn_hours` window + below threshold + not yet sent.
4. Sends a **breach** email if: past the deadline + below threshold + not yet sent.

Each email type is recorded in `asyncwatch_notifications` so it is only sent once.

Run manually:
```bash
php admin/cli/scheduled_task.php --execute='\local_asyncwatch\task\check_progress'
```

---

## Capabilities

| Capability | Default roles |
|------------|---------------|
| `local/asyncwatch:manage` | editingteacher, manager |
| `local/asyncwatch:viewreport` | editingteacher, teacher, manager |

---

## Roadmap / next steps

- [ ] Build AMD JS (run `grunt amd` to compile `amd/src/` → `amd/build/`)
- [ ] Add drag-and-drop sortorder for Parts
- [ ] Moodle messaging API integration (in-app notifications alongside email)
- [ ] Per-rule email template customisation
- [ ] Dashboard block showing learner's own progress
- [ ] Behat / PHPUnit test suite
