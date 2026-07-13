<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_subscriptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Subscription manager class for local_subscriptions plugin.
 *
 * @package    local_subscriptions
 */
class manager {

    // Status constants.
    const STATUS_ACTIVE    = 'active';
    const STATUS_INACTIVE  = 'inactive';
    const STATUS_EXPIRED   = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    // Source constants.
    const SOURCE_ONLINE = 'online';
    const SOURCE_MANUAL = 'manual';

    // Course access constants.
    const COURSE_ACCESS_ALL      = 'all';
    const COURSE_ACCESS_SPECIFIC = 'specific';

    // Lesson access constants.
    const LESSON_ACCESS_ALL      = 'all';
    const LESSON_ACCESS_SPECIFIC = 'specific';

    // Expiry type constants.
    const EXPIRY_DAYS = 'days';
    const EXPIRY_DATE = 'date';

    /**
     * Check if a user has an active subscription.
     *
     * @param int $userid
     * @return bool
     */
    public static function has_active_subscription(int $userid): bool {
        global $DB;
        $now = time();
        return $DB->record_exists_select(
            'local_subscriptions_users',
            'userid = :userid AND status = :status AND expiry_time > :now',
            ['userid' => $userid, 'status' => self::STATUS_ACTIVE, 'now' => $now]
        );
    }

    /**
     * Get the active subscription record for a user.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_active_subscription(int $userid): ?\stdClass {
        global $DB;
        $now = time();
        $record = $DB->get_record_select(
            'local_subscriptions_users',
            'userid = :userid AND status = :status AND expiry_time > :now',
            ['userid' => $userid, 'status' => self::STATUS_ACTIVE, 'now' => $now]
        );
        return $record ?: null;
    }

    /**
     * Activate a subscription for a user (typically after online payment).
     *
     * @param int    $planid
     * @param int    $userid
     * @param float  $amount
     * @param string $source
     * @param string $order_id
     * @param string $transaction_id
     * @return int  New subscription record id
     */
    public static function activate_for_user(
        int $planid,
        int $userid,
        float $amount,
        string $source,
        string $order_id = '',
        string $transaction_id = ''
    ): int {
        global $DB;

        $plan = $DB->get_record('local_subscriptions_plans', ['id' => $planid], '*', MUST_EXIST);

        $now         = time();
        $expiry_time = self::compute_expiry($plan);
        $snapshot    = self::build_snapshot($planid);

        $record = new \stdClass();
        $record->planid         = $planid;
        $record->userid         = $userid;
        $record->status         = self::STATUS_ACTIVE;
        $record->source         = $source;
        $record->start_time     = $now;
        $record->expiry_time    = $expiry_time;
        $record->amount_paid    = $amount;
        $record->order_id       = $order_id;
        $record->transaction_id = $transaction_id;
        $record->assigned_by    = null;
        $record->cancelled_by   = null;
        $record->cancelled_time = null;
        $record->cancel_reason  = null;
        $record->refund_amount  = null;
        $record->snapshot       = $snapshot;
        $record->timecreated    = $now;

        return $DB->insert_record('local_subscriptions_users', $record);
    }

    /**
     * Compute expiry timestamp for a plan.
     *
     * @param \stdClass $plan
     * @return int Unix timestamp
     */
    public static function compute_expiry(\stdClass $plan): int {
        if ($plan->expiry_type === self::EXPIRY_DAYS) {
            return time() + ((int)$plan->expiry_days * 86400);
        }
        // expiry_type === 'date'
        return (int)$plan->expiry_date;
    }

    /**
     * Build a JSON snapshot of a plan (for audit trail).
     *
     * @param int $planid
     * @return string JSON
     */
    public static function build_snapshot(int $planid): string {
        global $DB;
        $plan = $DB->get_record('local_subscriptions_plans', ['id' => $planid]);
        if (!$plan) {
            return json_encode([]);
        }
        $items = $DB->get_records('local_subscriptions_items', ['planid' => $planid]);
        $data = (array)$plan;
        $data['items'] = array_values(array_map(fn($i) => (array)$i, $items));
        return json_encode($data);
    }

    /**
     * Get all plans, optionally only active ones.
     *
     * @param bool $active_only
     * @return array
     */
    public static function get_plans(bool $active_only = false): array {
        global $DB;
        if (!$active_only) {
            return array_values($DB->get_records('local_subscriptions_plans', null, 'timecreated DESC'));
        }
        $now = time();
        $sql = "SELECT * FROM {local_subscriptions_plans}
                WHERE status = :status
                  AND (expiry_type = :expiry_days OR expiry_date > :now)
                ORDER BY timecreated DESC";
        return array_values($DB->get_records_sql($sql, [
            'status'      => self::STATUS_ACTIVE,
            'expiry_days' => self::EXPIRY_DAYS,
            'now'         => $now,
        ]));
    }

    /**
     * Get a single plan record.
     *
     * @param int $planid
     * @return \stdClass|null
     */
    public static function get_plan(int $planid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('local_subscriptions_plans', ['id' => $planid]);
        return $record ?: null;
    }

    /**
     * Get all items (courses/modules) belonging to a plan.
     *
     * @param int $planid
     * @return array
     */
    public static function get_plan_items(int $planid): array {
        global $DB;
        return array_values($DB->get_records('local_subscriptions_items', ['planid' => $planid]));
    }

    /**
     * Create a new subscription plan.
     *
     * @param \stdClass $data   Must contain name, price, status, course_access_type, expiry_type.
     *                          Optionally: items (array of {courseid, lesson_access_type, cmid})
     * @return int New plan id
     */
    public static function create_plan(\stdClass $data): int {
        global $DB, $USER;

        $now = time();
        $record = new \stdClass();
        $record->name               = $data->name;
        $record->description        = $data->description ?? '';
        $record->price              = (float)($data->price ?? 0);
        $record->status             = $data->status ?? self::STATUS_ACTIVE;
        $record->course_access_type = $data->course_access_type ?? self::COURSE_ACCESS_SPECIFIC;
        $record->expiry_type        = $data->expiry_type ?? self::EXPIRY_DAYS;
        $record->expiry_days        = isset($data->expiry_days) ? (int)$data->expiry_days : null;
        $record->expiry_date        = isset($data->expiry_date) ? (int)$data->expiry_date : null;
        $record->unlock_limit       = isset($data->unlock_limit) ? max(0, (int)$data->unlock_limit) : 0;
        $record->timecreated        = $now;
        $record->timemodified       = $now;
        $record->created_by         = $USER->id;

        $planid = $DB->insert_record('local_subscriptions_plans', $record);

        if (!empty($data->items) && is_array($data->items)) {
            self::save_plan_items($planid, $data->items);
        }

        // Log creation.
        self::log_history($planid, 'created', null, null, json_encode((array)$record), $USER->id);

        return $planid;
    }

    /**
     * Update an existing plan.
     *
     * @param int       $planid
     * @param \stdClass $data
     */
    public static function update_plan(int $planid, \stdClass $data): void {
        global $DB, $USER;

        $existing = $DB->get_record('local_subscriptions_plans', ['id' => $planid], '*', MUST_EXIST);
        $now = time();

        $fields = ['name', 'description', 'price', 'status', 'course_access_type',
                   'expiry_type', 'expiry_days', 'expiry_date', 'unlock_limit'];

        $record = new \stdClass();
        $record->id           = $planid;
        $record->timemodified = $now;

        foreach ($fields as $field) {
            if (isset($data->$field)) {
                $record->$field = $data->$field;
                // Log each changed field.
                if ((string)$existing->$field !== (string)$data->$field) {
                    self::log_history($planid, 'updated', $field,
                        (string)$existing->$field, (string)$data->$field, $USER->id);
                }
            }
        }

        $DB->update_record('local_subscriptions_plans', $record);

        if (isset($data->items) && is_array($data->items)) {
            self::save_plan_items($planid, $data->items);
            self::log_history($planid, 'items_updated', null, null,
                json_encode($data->items), $USER->id);
        }
    }

    /**
     * Replace all plan items for a plan.
     *
     * @param int   $planid
     * @param array $items  Each element: ['courseid' => int, 'lesson_access_type' => string, 'cmid' => int|null]
     */
    public static function save_plan_items(int $planid, array $items): void {
        global $DB;

        $DB->delete_records('local_subscriptions_items', ['planid' => $planid]);

        foreach ($items as $item) {
            $record = new \stdClass();
            $record->planid             = $planid;
            $record->courseid           = (int)($item['courseid'] ?? $item->courseid ?? 0);
            $record->lesson_access_type = $item['lesson_access_type'] ?? $item->lesson_access_type ?? self::LESSON_ACCESS_ALL;
            $record->cmid               = !empty($item['cmid'] ?? $item->cmid ?? null)
                                            ? (int)($item['cmid'] ?? $item->cmid)
                                            : null;
            if ($record->courseid) {
                $DB->insert_record('local_subscriptions_items', $record);
            }
        }
    }

    /**
     * Deactivate a plan (set status to inactive).
     *
     * @param int $planid
     */
    public static function deactivate_plan(int $planid): void {
        global $DB, $USER;
        $DB->set_field('local_subscriptions_plans', 'status', self::STATUS_INACTIVE, ['id' => $planid]);
        $DB->set_field('local_subscriptions_plans', 'timemodified', time(), ['id' => $planid]);
        self::log_history($planid, 'deactivated', 'status',
            self::STATUS_ACTIVE, self::STATUS_INACTIVE, $USER->id);
    }

    /**
     * Activate a plan (set status to active).
     *
     * @param int $planid
     */
    public static function activate_plan(int $planid): void {
        global $DB, $USER;
        $DB->set_field('local_subscriptions_plans', 'status', self::STATUS_ACTIVE, ['id' => $planid]);
        $DB->set_field('local_subscriptions_plans', 'timemodified', time(), ['id' => $planid]);
        self::log_history($planid, 'activated', 'status',
            self::STATUS_INACTIVE, self::STATUS_ACTIVE, $USER->id);
    }

    /**
     * Delete a plan (only if no subscribers).
     *
     * @param int $planid
     * @throws \moodle_exception if the plan has subscribers
     */
    public static function delete_plan(int $planid): void {
        global $DB, $USER;

        $count = $DB->count_records('local_subscriptions_users', ['planid' => $planid]);
        if ($count > 0) {
            throw new \moodle_exception('cannot_delete_has_subscribers', 'local_subscriptions');
        }

        self::log_history($planid, 'deleted', null, null, null, $USER->id);
        $DB->delete_records('local_subscriptions_items', ['planid' => $planid]);
        $DB->delete_records('local_subscriptions_plans', ['id' => $planid]);
    }

    /**
     * Manually assign a subscription to a user.
     *
     * @param int    $planid
     * @param int    $userid
     * @param float  $amount
     * @param int    $assigned_by
     * @param string $note
     * @return int New subscription id
     */
    public static function assign_to_user(
        int $planid,
        int $userid,
        float $amount,
        int $assigned_by,
        string $note = ''
    ): int {
        global $DB;

        $plan = $DB->get_record('local_subscriptions_plans', ['id' => $planid], '*', MUST_EXIST);

        $now         = time();
        $expiry_time = self::compute_expiry($plan);
        $snapshot    = self::build_snapshot($planid);

        $record = new \stdClass();
        $record->planid         = $planid;
        $record->userid         = $userid;
        $record->status         = self::STATUS_ACTIVE;
        $record->source         = self::SOURCE_MANUAL;
        $record->start_time     = $now;
        $record->expiry_time    = $expiry_time;
        $record->amount_paid    = $amount;
        $record->order_id       = '';
        $record->transaction_id = '';
        $record->assigned_by    = $assigned_by;
        $record->cancelled_by   = null;
        $record->cancelled_time = null;
        $record->cancel_reason  = $note ?: null;
        $record->refund_amount  = null;
        $record->snapshot       = $snapshot;
        $record->timecreated    = $now;

        return $DB->insert_record('local_subscriptions_users', $record);
    }

    /**
     * Cancel a user's subscription.
     *
     * @param int    $sub_id
     * @param int    $cancelled_by
     * @param string $reason
     * @param float  $refund
     */
    public static function unsubscribe_user(
        int $sub_id,
        int $cancelled_by,
        string $reason,
        float $refund = 0.0
    ): void {
        global $DB, $CFG;

        $now = time();
        $record = new \stdClass();
        $record->id             = $sub_id;
        $record->status         = self::STATUS_CANCELLED;
        $record->cancelled_by   = $cancelled_by;
        $record->cancelled_time = $now;
        $record->cancel_reason  = $reason;
        $record->refund_amount  = $refund > 0 ? $refund : null;

        $DB->update_record('local_subscriptions_users', $record);

        // Immediately revoke access granted by this subscription (US-AD-2-2):
        // remove the user from the videopay groups its unlocks added. Unlock rows
        // are kept as history; only the live access (group membership) is revoked.
        require_once($CFG->dirroot . '/group/lib.php');
        $unlocks = $DB->get_records('local_subscriptions_unlocks', ['subscriptionid' => $sub_id]);
        foreach ($unlocks as $u) {
            if ((int)$u->groupid > 0
                    && $DB->record_exists('groups_members', ['userid' => $u->userid, 'groupid' => $u->groupid])) {
                groups_remove_member((int)$u->groupid, (int)$u->userid);
            }
        }
    }

    /**
     * Check if a user has an active subscription that gives access to a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function user_has_access_to_course(int $userid, int $courseid): bool {
        global $DB;

        $sub = self::get_active_subscription($userid);
        if (!$sub) {
            return false;
        }

        $plan = $DB->get_record('local_subscriptions_plans', ['id' => $sub->planid]);
        if (!$plan) {
            return false;
        }

        if ($plan->course_access_type === self::COURSE_ACCESS_ALL) {
            return true;
        }

        // Specific access: check plan items.
        return $DB->record_exists('local_subscriptions_items', [
            'planid'   => $sub->planid,
            'courseid' => $courseid,
        ]);
    }

    /**
     * Check if a user has an active subscription that gives access to a course module.
     *
     * @param int $userid
     * @param int $cmid
     * @return bool
     */
    public static function user_has_access_to_module(int $userid, int $cmid): bool {
        global $DB;

        $sub = self::get_active_subscription($userid);
        if (!$sub) {
            return false;
        }

        $plan = $DB->get_record('local_subscriptions_plans', ['id' => $sub->planid]);
        if (!$plan) {
            return false;
        }

        if ($plan->course_access_type === self::COURSE_ACCESS_ALL) {
            return true;
        }

        // Get the course for this cm.
        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
        if (!$cm) {
            return false;
        }

        // Check if there's an item for this course.
        $item = $DB->get_record('local_subscriptions_items', [
            'planid'   => $sub->planid,
            'courseid' => $cm->course,
        ]);

        if (!$item) {
            return false;
        }

        // Course found: if lesson_access_type is all, grant access.
        if ($item->lesson_access_type === self::LESSON_ACCESS_ALL) {
            return true;
        }

        // Specific lesson: check exact cmid.
        return $DB->record_exists('local_subscriptions_items', [
            'planid'   => $sub->planid,
            'courseid' => $cm->course,
            'cmid'     => $cmid,
        ]);
    }

    /**
     * Expire subscriptions that have passed their expiry_time.
     *
     * @return int Number of subscriptions expired
     */
    public static function expire_subscriptions(): int {
        global $DB;
        $now = time();
        $sql = "UPDATE {local_subscriptions_users}
                SET status = :expired
                WHERE status = :active AND expiry_time < :now";
        $DB->execute($sql, [
            'expired' => self::STATUS_EXPIRED,
            'active'  => self::STATUS_ACTIVE,
            'now'     => $now,
        ]);
        // Count how many were actually updated via a count query just before (approximation).
        return $DB->count_records_select(
            'local_subscriptions_users',
            'status = :status AND expiry_time < :now',
            ['status' => self::STATUS_EXPIRED, 'now' => $now]
        );
    }

    /**
     * Get all subscriptions for a user.
     *
     * @param int $userid
     * @return array
     */
    public static function get_user_subscriptions(int $userid): array {
        global $DB;
        $sql = "SELECT s.*, p.name AS plan_name, p.price AS plan_price
                FROM {local_subscriptions_users} s
                JOIN {local_subscriptions_plans} p ON p.id = s.planid
                WHERE s.userid = :userid
                ORDER BY s.timecreated DESC";
        return array_values($DB->get_records_sql($sql, ['userid' => $userid]));
    }

    /**
     * Get all subscribers for a plan.
     *
     * @param int $planid
     * @return array
     */
    public static function get_subscribers(int $planid): array {
        global $DB;
        $sql = "SELECT s.*, u.firstname, u.lastname, u.email
                FROM {local_subscriptions_users} s
                JOIN {user} u ON u.id = s.userid
                WHERE s.planid = :planid
                ORDER BY s.timecreated DESC";
        return array_values($DB->get_records_sql($sql, ['planid' => $planid]));
    }

    /**
     * Get overall subscription statistics.
     *
     * @return array
     */
    public static function get_stats(): array {
        global $DB;

        $total     = $DB->count_records('local_subscriptions_users');
        $active    = $DB->count_records('local_subscriptions_users', ['status' => self::STATUS_ACTIVE]);
        $expired   = $DB->count_records('local_subscriptions_users', ['status' => self::STATUS_EXPIRED]);
        $cancelled = $DB->count_records('local_subscriptions_users', ['status' => self::STATUS_CANCELLED]);
        $online    = $DB->count_records('local_subscriptions_users', ['source' => self::SOURCE_ONLINE]);
        $manual    = $DB->count_records('local_subscriptions_users', ['source' => self::SOURCE_MANUAL]);

        $amount_row = $DB->get_record_sql(
            "SELECT SUM(amount_paid) AS total FROM {local_subscriptions_users}"
        );
        $refund_row = $DB->get_record_sql(
            "SELECT SUM(refund_amount) AS total FROM {local_subscriptions_users} WHERE refund_amount IS NOT NULL"
        );

        return [
            'total'        => $total,
            'active'       => $active,
            'expired'      => $expired,
            'cancelled'    => $cancelled,
            'online'       => $online,
            'manual'       => $manual,
            'total_amount' => (float)($amount_row->total ?? 0),
            'total_refund' => (float)($refund_row->total ?? 0),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Credit-based lesson unlocking.
    // A plan with unlock_limit > 0 lets a subscriber unlock up to N lessons from
    // the plan's pool. "Unlocking" = adding the user to that lesson's videopay
    // group (same mechanism paying uses), so access is permanent and survives
    // subscription expiry.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compute the pool of unlockable lessons for a plan: the paid (videopay-gated)
     * resource2 modules covered by the plan's items.
     *
     * @param int $planid
     * @return array  [cmid => groupid] for every paid lesson in the plan's pool
     */
    public static function get_pool_cmids(int $planid): array {
        global $DB;

        // No videopay = no gated lessons = empty pool.
        if (!$DB->get_manager()->table_exists('local_videopay_prices')) {
            return [];
        }

        $items = self::get_plan_items($planid);
        if (!$items) {
            return [];
        }

        $pool = [];

        // Course-level items where all lessons are included: every paid resource2 in the course.
        $allcourses = [];
        $specificcms = [];
        foreach ($items as $item) {
            if ($item->lesson_access_type === self::LESSON_ACCESS_SPECIFIC && !empty($item->cmid)) {
                $specificcms[(int)$item->cmid] = true;
            } else {
                $allcourses[(int)$item->courseid] = true;
            }
        }

        if ($allcourses) {
            list($insql, $params) = $DB->get_in_or_equal(array_keys($allcourses), SQL_PARAMS_NAMED);
            $sql = "SELECT p.cmid, p.groupid
                      FROM {local_videopay_prices} p
                      JOIN {course_modules} cm ON cm.id = p.cmid
                     WHERE cm.course $insql
                       AND p.is_free = 0
                       AND p.groupid > 0";
            foreach ($DB->get_records_sql($sql, $params) as $r) {
                $pool[(int)$r->cmid] = (int)$r->groupid;
            }
        }

        if ($specificcms) {
            list($insql, $params) = $DB->get_in_or_equal(array_keys($specificcms), SQL_PARAMS_NAMED);
            $sql = "SELECT p.cmid, p.groupid
                      FROM {local_videopay_prices} p
                     WHERE p.cmid $insql
                       AND p.is_free = 0
                       AND p.groupid > 0";
            foreach ($DB->get_records_sql($sql, $params) as $r) {
                $pool[(int)$r->cmid] = (int)$r->groupid;
            }
        }

        return $pool;
    }

    /**
     * Get the cmids a subscription has already unlocked.
     *
     * @param int $subscriptionid
     * @return int[] list of cmids
     */
    public static function get_unlocked_cmids(int $subscriptionid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select(
            'local_subscriptions_unlocks', 'cmid', 'subscriptionid = :sid', ['sid' => $subscriptionid]
        ));
    }

    /**
     * The unlock limit that applies to a given subscription. Read from the stored
     * snapshot so later plan edits don't change what an existing buyer paid for;
     * falls back to the live plan if no snapshot is present.
     *
     * @param \stdClass $sub  a local_subscriptions_users record
     * @return int
     */
    public static function get_unlock_limit_for(\stdClass $sub): int {
        if (!empty($sub->snapshot)) {
            $snap = json_decode($sub->snapshot, true);
            if (is_array($snap) && isset($snap['unlock_limit'])) {
                return max(0, (int)$snap['unlock_limit']);
            }
        }
        $plan = self::get_plan((int)$sub->planid);
        return $plan ? max(0, (int)$plan->unlock_limit) : 0;
    }

    /**
     * How many unlocks remain on a subscription.
     *
     * @param \stdClass $sub
     * @return int
     */
    public static function get_remaining_unlocks(\stdClass $sub): int {
        global $DB;
        $limit = self::get_unlock_limit_for($sub);
        if ($limit <= 0) {
            return 0;
        }
        $used = $DB->count_records('local_subscriptions_unlocks', ['subscriptionid' => $sub->id]);
        return max(0, $limit - $used);
    }

    /**
     * Unlock a lesson for a user against their active subscription.
     * All checks are enforced server-side.
     *
     * @param int $userid
     * @param int $cmid
     * @return array  ['status' => 'success'|'error', 'message' => string, 'remaining' => int]
     */
    public static function unlock_lesson(int $userid, int $cmid): array {
        global $DB, $CFG;

        $error = function(string $key, int $remaining = 0) {
            return [
                'status'    => 'error',
                'message'   => get_string($key, 'local_subscriptions'),
                'remaining' => $remaining,
            ];
        };

        // 1) Must have an active (non-expired) subscription.
        $sub = self::get_active_subscription($userid);
        if (!$sub) {
            return $error('unlock_no_active_subscription');
        }

        // 2) Must be a credit plan.
        $limit = self::get_unlock_limit_for($sub);
        if ($limit <= 0) {
            return $error('unlock_not_credit_plan');
        }

        // 3) Lesson must belong to the plan's pool.
        $pool = self::get_pool_cmids((int)$sub->planid);
        if (!isset($pool[$cmid])) {
            return $error('unlock_not_in_plan');
        }
        $groupid = (int)$pool[$cmid];

        // 4) Already unlocked on this subscription → idempotent success.
        if ($DB->record_exists('local_subscriptions_unlocks',
                ['subscriptionid' => $sub->id, 'cmid' => $cmid])) {
            return [
                'status'    => 'success',
                'message'   => get_string('already_unlocked', 'local_subscriptions'),
                'remaining' => self::get_remaining_unlocks($sub),
            ];
        }

        // 5) Must have remaining credits.
        if (self::get_remaining_unlocks($sub) <= 0) {
            return $error('unlock_limit_reached');
        }

        // 6) Grant access = add to the lesson's videopay group, then record it.
        require_once($CFG->dirroot . '/group/lib.php');

        $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, course', MUST_EXIST);

        if (!$DB->record_exists('groups_members', ['userid' => $userid, 'groupid' => $groupid])) {
            groups_add_member($groupid, $userid);
        }

        $rec = new \stdClass();
        $rec->subscriptionid = (int)$sub->id;
        $rec->userid         = $userid;
        $rec->planid         = (int)$sub->planid;
        $rec->courseid       = (int)$cm->course;
        $rec->cmid           = $cmid;
        $rec->groupid        = $groupid;
        $rec->timecreated    = time();
        $DB->insert_record('local_subscriptions_unlocks', $rec);

        return [
            'status'    => 'success',
            'message'   => get_string('unlock_success', 'local_subscriptions'),
            'remaining' => self::get_remaining_unlocks($sub),
        ];
    }

    /**
     * Log a plan history entry.
     *
     * @param int         $planid
     * @param string      $change_type
     * @param string|null $field_name
     * @param string|null $old_value
     * @param string|null $new_value
     * @param int         $changed_by
     */
    private static function log_history(
        int $planid,
        string $change_type,
        ?string $field_name,
        ?string $old_value,
        ?string $new_value,
        int $changed_by
    ): void {
        global $DB;
        $record = new \stdClass();
        $record->planid      = $planid;
        $record->change_type = $change_type;
        $record->field_name  = $field_name;
        $record->old_value   = $old_value;
        $record->new_value   = $new_value;
        $record->changed_by  = $changed_by;
        $record->timecreated = time();
        $DB->insert_record('local_subscriptions_history', $record);
    }
}
