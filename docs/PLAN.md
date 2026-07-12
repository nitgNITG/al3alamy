# Project Status & Implementation Plan
**Last updated:** 2026-07-13

---

## What the Friend's Commit Actually Added (d90596f4)

| What | Status |
|------|--------|
| User-story docs (`docs/user stories/`) | ✅ Good — keep |
| `docker-compose.yml` | ✅ Good — local dev only |
| `kashier/callback.php` — `sub-` order handler | ⚠️ Partial — references missing plugin |
| `kashier/webhook.php` — `sub-` order handler | ⚠️ Partial — references missing plugin |
| `local/subscriptions/` plugin | ❌ NOT CREATED |
| Device login-limit plugin | ❌ NOT CREATED |

**Why nothing appeared on server after `git pull`:**
The commit added only documentation and partial payment hooks.
No plugin code, no DB tables, no UI pages were added.

---

## Critical Bug Fixed (commit 1f7e4390)

`\local_subscriptions\manager::activate_for_user()` was called unconditionally
in callback.php and webhook.php. Since the plugin doesn't exist yet, this would
cause a PHP fatal error on **any** subscription payment.

**Fix applied:** Both calls now wrapped in `class_exists('\local_subscriptions\manager')`.
Transactions are still safely recorded; activation is deferred until the plugin exists.

---

## What Is Currently Working on Server

| Feature | Status | Notes |
|---------|--------|-------|
| Registration-code signup gate | ✅ Working | Requires `local_regcodes` table in DB |
| Student profile fields (phone, gov, track) | ✅ Working | Created by upgrade.php |
| Admin free code generation | ✅ Working | `/local/registrationcodes/admin.php` |
| Manager paid code purchase | ⚠️ Needs Kashier .env | `local/registrationcodes/buy.php` |
| Per-module video pricing (UI) | ✅ Working | Saves to `local_videopay_prices` |
| Kashier payment (sessions API) | ⚠️ Needs .env credentials | `kashier/config.php` |
| OWL carousel RTL arrows | ✅ Fixed | CSS mirroring |
| Navbar login/register buttons | ✅ Working | |
| Mobile fixed-bottom CTA | ✅ Working | |

---

## What Needs to Be Built

### Task 2 — `local/subscriptions` Plugin  🔴 HIGH PRIORITY

Full subscription system matching user stories US-AD-1-1 through US-SB-1-3.

**DB Tables needed:**
```
local_subscriptions_plans      — plan definitions (name, price, expiry config)
local_subscriptions_plan_items — what's in each plan (courses + lessons)
local_subscriptions_users      — user-plan links (status, expiry, snapshot)
local_subscriptions_history    — plan change audit log
```

**Pages needed:**
```
Admin:
  /local/subscriptions/admin/plans.php        — list all plans
  /local/subscriptions/admin/plan_edit.php    — create/edit plan
  /local/subscriptions/admin/assign.php       — manually assign to user
  /local/subscriptions/admin/unsubscribe.php  — manually unsubscribe
  /local/subscriptions/admin/report.php       — full report

Student:
  /local/subscriptions/index.php              — browse plans (US-SB-1-1)
  /local/subscriptions/buy.php                — purchase flow → Kashier
  /local/subscriptions/mysubscriptions.php    — my subscriptions (US-SB-1-3)
```

**manager.php methods needed (for callback.php/webhook.php):**
```php
activate_for_user(planid, userid, amount, source, order_id, transaction_id)
has_active_subscription(userid): bool
get_active_subscription(userid): ?stdClass
```

**Access control hook:**
Check if user's active subscription includes the course/module being accessed.

---

### Task 3 — `local/devicecontrol` Plugin  🟡 MEDIUM PRIORITY

Per US-AD-11-1: admin sets max devices per user; login is blocked once limit is reached.

**DB Table:**
```
local_device_registrations
  — userid, device_hash, user_agent, ip_address, timecreated, last_seen
```

**Settings:** Enable/disable toggle + max devices count (admin panel)

**Hook:** `user_loggedin` event → count existing devices → block if over limit

---

### Task 4 — Video Direct Access After Payment  🟡 MEDIUM PRIORITY

**Current flow:**
```
Pay vid-{uid}-{courseid}-{groupid}-{cmid}-{ts}
→ enroll in course + add to group
→ redirect to /course/view.php?id={courseid}   ← too generic
```

**Fix:** Redirect to the specific module after successful payment:
```php
redirect(new moodle_url('/mod/hvp/view.php', ['id' => $cmid]));
// or for resource/page:
redirect(new moodle_url('/mod/resource/view.php', ['id' => $cmid]));
// or generic:
redirect(new moodle_url('/mod/view.php', ['id' => $cmid]));
```

---

### Task 5 — Video Upload Corruption  🟡 MEDIUM PRIORITY

**Suspected causes:**
1. `upload_max_filesize` / `post_max_size` too low in php.ini
2. `max_execution_time` too short for large files
3. Moodle `maxbytes` setting too low
4. Shared hosting upload temp dir permissions

**Check on server:**
```bash
php -i | grep -E "upload_max|post_max|max_execution|memory_limit"
```
Also check: Site Admin → Security → Site Policies → Maximum uploaded file size

---

### Task 6 — Kashier .env Setup  🔴 REQUIRED FOR PAYMENTS

Copy `.env.example` to `.env` on server and fill in:
```
KASHIER_MANAGER_MERCHANT_ID=MID_...
KASHIER_MANAGER_API_KEY=...
KASHIER_MANAGER_SECRET_KEY=...
KASHIER_STUDENT_MERCHANT_ID=MID_...
KASHIER_STUDENT_API_KEY=...
KASHIER_STUDENT_SECRET_KEY=...
```
Find keys at: merchant.kashier.io → Developers → API Keys

---

## Server Checks to Run (In Order)

Run these on the server to confirm current state:

### 1. DB Tables
```bash
# SSH to server, then:
php admin/cli/mysql_command.php "SHOW TABLES LIKE 'mdl_local_%';"
php admin/cli/mysql_command.php "SHOW TABLES LIKE 'mdl_kashier_%';"
```
Expected: `mdl_local_regcodes`, `mdl_local_videopay_prices`, `mdl_kashier_transactions`

### 2. Plugin Installation Status
Visit: `https://al3alamy.com/admin/index.php`
Moodle will prompt to run DB upgrade if new tables are needed.

### 3. .env File
```bash
cat /path/to/public_html/.env
```
Confirm KASHIER_* keys are set.

### 4. PHP Upload Limits
```bash
php -r "echo ini_get('upload_max_filesize') . '\n' . ini_get('post_max_size') . '\n';"
```

### 5. Video Module Type
Check what module type the videos use (hvp, url, resource, etc.):
```bash
php admin/cli/mysql_command.php "SELECT DISTINCT m.name FROM mdl_modules m JOIN mdl_course_modules cm ON cm.module=m.id LIMIT 20;"
```

---

## Implementation Order

```
[✅ Done]  Task 1 — Fix crash risk (commit 1f7e4390)
[🔴 Next]  Task 6 — Set up .env on server (manual, 5 min)
[🔴 Next]  Task 2 — Build local/subscriptions plugin
[🟡 Then]  Task 4 — Video direct redirect after payment
[🟡 Then]  Task 3 — Device login limits
[🟡 Then]  Task 5 — Video upload investigation (needs server info)
```
