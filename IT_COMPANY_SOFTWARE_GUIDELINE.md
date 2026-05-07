# Internal IT Company Operations Software — Module & Architecture Guideline

This document proposes the functional modules, core entities, and design principles for an internal system that manages organizational structure, work assignment, time tracking, and talent / career intake. Use it as a blueprint when implementing features in this codebase (or any stack).

---

## 1. Goals

- Give leadership visibility into **who works on what**, across **sections** and **teams**.
- Support **reassignment** of work without losing history.
- Record **time spent** at task and sub-task granularity.
- Model **many roles per person** (e.g. developer + team lead).
- Provide a **Careers** area with CRUD for openings and capture of **resumes** and **candidate interests**.

---

## 2. Recommended Modules

### 2.1 Organization & access

| Area | Purpose |
|------|---------|
| **Users & authentication** | Login, profiles, optional SSO later. |
| **Roles & permissions** | System roles (admin, HR, manager, member) and fine-grained abilities (e.g. manage section, assign tasks, view all time). |
| **Sections** | Top-level groupings (e.g. Engineering, Operations). Each has a **section leader** (user reference). |
| **Teams** | Belong to one section; each has a **team leader** (user reference). |
| **Membership** | Users belong to one or more teams; optionally track primary team. Historical membership helps reporting if people move. |

**Multi-role rule:** Avoid a single “job title” field as the only authority. Store **assignments**: e.g. `user` is `team_leader` for `team_id`, `section_leader` for `section_id`, and `member` on other teams. Permissions derive from these assignments plus optional global roles.

### 2.2 Work management

| Area | Purpose |
|------|---------|
| **Tasks** | Title, description, status, priority, due dates, link to team (and/or section), **assignee** (current), **creator**, optional reporter. |
| **Task lifecycle** | States such as backlog → in progress → review → done → cancelled; configurable per team if needed. |
| **Task transfer / reassignment** | Each change of assignee should append an **assignment event** (from user, to user, when, optional reason). The task always has a **current** assignee; history lives in events. |
| **Sub-tasks** | Child tasks under a parent task (same table with `parent_task_id` or dedicated `sub_tasks` with same shape). Inherit team/context from parent unless overridden. |
| **Comments & attachments** | Optional but valuable for handoffs and audits. |

**Sub-task time:** If each sub-task is a first-class task row, **time entries** attach to that row; roll up totals to the parent for reporting.

### 2.3 Time tracking

| Area | Purpose |
|------|---------|
| **Time entries** | Who, which task (or sub-task), start/end or duration, optional note, billable flag if you ever need it. |
| **Timers (optional)** | Running timer per user with pause/resume; on stop, create a time entry. |
| **Policies** | Max duration per entry, rounding rules, who can edit/delete entries (e.g. only own entries; managers may adjust). |
| **Reporting** | By user, team, section, task, date range; export CSV for payroll or client billing later. |

### 2.4 Careers & talent intake

| Area | Purpose |
|------|---------|
| **Job postings (CRUD)** | Title, section/team, location, type (full-time, contract), description, requirements, status (draft / open / closed), publish dates. |
| **Applications** | Candidate name, email, phone, links, **uploaded resume** (stored file + metadata), cover letter, applied-at, status pipeline (received → screening → interview → offer → hired / rejected). |
| **Interests / tags** | Structured fields (e.g. skills, preferred stack, salary expectation) plus free-text **interests**; optional **tag** model for faceted search. |
| **Privacy** | Restrict careers data to HR and authorized roles; separate from day-to-day task permissions. |

### 2.5 Supporting modules (strongly recommended)

| Area | Purpose |
|------|---------|
| **Notifications** | In-app (and later email) for assignment, mention, due date, application received. |
| **Audit log** | Who changed leadership, reassigned tasks, edited sensitive fields. |
| **Dashboards** | Section/team workload, overdue tasks, time summaries, hiring funnel counts. |
| **Settings** | Company name, fiscal week, default task statuses, file upload limits. |

---

## 3. Core Data Model (conceptual)

- **User** — identity, profile.
- **Section** — name, `section_leader_id` (nullable User).
- **Team** — `section_id`, name, `team_leader_id` (User).
- **TeamMembership** — `user_id`, `team_id`, `joined_at`, `left_at` (optional).
- **Role / Permission** — either Spatie-style roles or custom `user_section_role`, `user_team_role` pivots.
- **Task** — hierarchy via `parent_id`; `assignee_id`; `team_id`; status; etc.
- **TaskAssignment** (or event table) — `task_id`, `from_user_id`, `to_user_id`, `changed_by_id`, `changed_at`, `note`.
- **TimeEntry** — `user_id`, `task_id`, `started_at`, `ended_at` or `minutes`, `note`.
- **JobPosting** — careers CRUD entity.
- **Application** — `job_posting_id`, candidate fields, `resume_path` or storage reference, `interests` JSON or related table, `status`.

---

## 4. User stories (acceptance-oriented)

1. As an admin, I can create sections and teams and designate section and team leaders.
2. As a team lead, I can create tasks, assign them to a member, and transfer them to another member with an optional note.
3. As a developer, I can log time against my tasks and sub-tasks and see my weekly totals.
4. As a manager, I can see time and task load by team and section.
5. As HR, I can CRUD job postings, collect applications with resume upload, and track candidate interest fields and pipeline status.

---

## 5. Implementation phases (suggested)

1. **Foundation** — Users, sections, teams, memberships, leaders, basic roles.
2. **Tasks** — Tasks, sub-tasks, assignment + transfer history, statuses.
3. **Time** — Time entries, reports, optional timer.
4. **Careers** — Job postings CRUD, applications, file storage, interests.
5. **Polish** — Notifications, audit, dashboards, exports.

---

## 6. Technical notes (for this Laravel project)

- Use **policies** and **gates** for section/team-scoped authorization (leads see their tree; members see own tasks unless broadened).
- **Eloquent** `parent_id` self-relation on `Task` simplifies sub-tasks and aggregation (e.g. sum child time).
- Store resume files on **private disk** with signed URLs or admin-only download routes.
- Prefer **explicit tables** for assignment history and time entries over overwriting fields, so reporting and audits stay trustworthy.

---

## 7. Out of scope (unless you add later)

- Full project management (Gantt, critical path).
- Client invoicing and contracts.
- Payroll integration.
- Device / asset inventory.

---

*Document version: 1.0 — created as an internal guideline for structuring modules and data. Update as product decisions change.*
