<?php
namespace local_registrationcodes;

defined('MOODLE_INTERNAL') || die();

/**
 * Core business logic for the Registration Codes plugin.
 */
class manager {

    /** Digit-only charset for 8-digit numeric codes. */
    const CHARSET = '0123456789';

    /** Default code length: 8 digits. */
    const CODE_LENGTH = 8;

    // ── Status constants ──────────────────────────────────────────────────────

    const STATUS_UNUSED   = 'unused';
    const STATUS_USED     = 'used';
    const STATUS_EXPIRED  = 'expired';
    const STATUS_DISABLED = 'disabled';

    // ── Code generation ───────────────────────────────────────────────────────

    /**
     * Generate a single cryptographically random code string.
     *
     * @param string $prefix  Optional prefix (letters/digits only, will be uppercased).
     * @param int    $length  Length of the random segment.
     * @return string  e.g. "AL3-XJKM2NQP8WRT"
     */
    public static function generate_code_string(string $prefix = '', int $length = self::CODE_LENGTH): string {
        $chars  = self::CHARSET;
        $max    = strlen($chars) - 1;
        $random = '';
        for ($i = 0; $i < $length; $i++) {
            $random .= $chars[random_int(0, $max)];
        }
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', $prefix);
        return ($prefix !== '' ? strtoupper($prefix) . '-' : '') . $random;
    }

    /**
     * Generate $count codes and insert them into the DB.
     * Skips duplicates automatically.
     *
     * @param int         $count      Number of codes to generate.
     * @param string      $prefix     Optional prefix.
     * @param int|null    $timeexpiry Optional expiry timestamp.
     * @param string      $notes      Optional notes.
     * @param int         $createdby  User ID of the admin generating the codes.
     * @return string[]  Array of successfully generated code strings.
     */
    public static function generate_codes(
        int $count,
        string $prefix     = '',
        ?int $timeexpiry   = null,
        string $notes      = '',
        int $createdby     = 0
    ): array {
        global $DB;

        $generated = [];
        $maxattempts = $count * 20; // safety valve
        $attempts    = 0;

        while (count($generated) < $count && $attempts < $maxattempts) {
            $attempts++;
            $code = self::generate_code_string($prefix);

            if ($DB->record_exists('local_regcodes', ['code' => $code])) {
                continue; // collision — try again
            }

            $record              = new \stdClass();
            $record->code        = $code;
            $record->status      = self::STATUS_UNUSED;
            $record->created_by  = $createdby;
            $record->timecreated = time();
            $record->timeexpiry  = $timeexpiry;
            $record->notes       = $notes;
            $record->prefix      = strtoupper($prefix);

            $DB->insert_record('local_regcodes', $record);
            $generated[] = $code;

            // Fire event.
            $event = \local_registrationcodes\event\code_created::create([
                'context' => \context_system::instance(),
                'other'   => ['code' => $code, 'count' => count($generated)],
            ]);
            $event->trigger();
        }

        return $generated;
    }

    // ── Validation (used by signup hook) ──────────────────────────────────────

    /**
     * Validate a submitted code. Updates status to 'expired' in DB if TTL passed.
     *
     * @param string $rawcode  The code as submitted by the user.
     * @return array  ['valid' => bool, 'error' => string|null, 'record' => stdClass|null]
     */
    public static function validate_code(string $rawcode): array {
        global $DB;

        $code   = strtoupper(trim($rawcode));
        $record = $DB->get_record('local_regcodes', ['code' => $code]);

        if (!$record) {
            return ['valid' => false, 'error' => 'error_code_invalid', 'record' => null];
        }

        // Auto-expire if TTL passed.
        if ($record->status === self::STATUS_UNUSED
            && !empty($record->timeexpiry)
            && $record->timeexpiry < time()
        ) {
            $DB->set_field('local_regcodes', 'status', self::STATUS_EXPIRED, ['id' => $record->id]);
            $record->status = self::STATUS_EXPIRED;
        }

        if ($record->status === self::STATUS_USED)     { return ['valid' => false, 'error' => 'error_code_used',     'record' => $record]; }
        if ($record->status === self::STATUS_DISABLED) { return ['valid' => false, 'error' => 'error_code_disabled', 'record' => $record]; }
        if ($record->status === self::STATUS_EXPIRED)  { return ['valid' => false, 'error' => 'error_code_expired',  'record' => $record]; }

        return ['valid' => true, 'error' => null, 'record' => $record];
    }

    /**
     * Mark a code as used by a given user.
     *
     * @param string $rawcode
     * @param int    $userid
     * @return bool  True on success.
     */
    public static function consume_code(string $rawcode, int $userid): bool {
        global $DB;

        $code   = strtoupper(trim($rawcode));
        $record = $DB->get_record('local_regcodes', ['code' => $code, 'status' => self::STATUS_UNUSED]);

        if (!$record) {
            return false;
        }

        // Double-check expiry race condition.
        if (!empty($record->timeexpiry) && $record->timeexpiry < time()) {
            $DB->set_field('local_regcodes', 'status', self::STATUS_EXPIRED, ['id' => $record->id]);
            return false;
        }

        $record->status   = self::STATUS_USED;
        $record->used_by  = $userid;
        $record->timeused = time();
        $DB->update_record('local_regcodes', $record);

        $event = \local_registrationcodes\event\code_used::create([
            'objectid' => $record->id,
            'userid'   => $userid,
            'context'  => \context_system::instance(),
            'other'    => ['code' => $code],
        ]);
        $event->trigger();

        return true;
    }

    // ── Stat helpers (for dashboard) ──────────────────────────────────────────

    /**
     * Return counts keyed by status plus total.
     *
     * @return array ['total'=>int, 'unused'=>int, 'used'=>int, 'expired'=>int, 'disabled'=>int]
     */
    public static function get_stats(): array {
        global $DB;

        $statuses = [self::STATUS_UNUSED, self::STATUS_USED, self::STATUS_EXPIRED, self::STATUS_DISABLED];
        $stats    = ['total' => 0];

        foreach ($statuses as $s) {
            $stats[$s] = (int)$DB->count_records('local_regcodes', ['status' => $s]);
            $stats['total'] += $stats[$s];
        }

        $pct = $stats['total'] > 0 ? round($stats[self::STATUS_USED] / $stats['total'] * 100) : 0;
        $stats['usage_pct'] = $pct;

        return $stats;
    }

    // ── Bulk action helpers ───────────────────────────────────────────────────

    /**
     * Toggle status between 'unused' and 'disabled' for a list of IDs.
     *
     * @param int[]  $ids
     * @param string $newstatus  'unused' or 'disabled'
     */
    public static function set_status_bulk(array $ids, string $newstatus): void {
        global $DB;
        if (empty($ids)) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        // Only allow toggling codes that are not 'used'.
        $DB->execute(
            "UPDATE {local_regcodes} SET status = :newstatus WHERE id $insql AND status != 'used'",
            array_merge(['newstatus' => $newstatus], $params)
        );
    }

    /**
     * Delete codes by IDs — only deletes 'unused' and 'disabled' codes.
     *
     * @param int[] $ids
     */
    public static function delete_codes(array $ids): void {
        global $DB;
        if (empty($ids)) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $DB->execute(
            "DELETE FROM {local_regcodes} WHERE id $insql AND status IN ('unused','disabled','expired')",
            $params
        );
    }

    /**
     * Get a user's registration code record (the code they used to register).
     *
     * @param int $userid
     * @return \stdClass|false
     */
    public static function get_user_code(int $userid) {
        global $DB;
        return $DB->get_record('local_regcodes', ['used_by' => $userid]);
    }
}
