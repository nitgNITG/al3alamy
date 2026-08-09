<?php
/**
 * Scheduled task: clean up orphaned vimeo_files2 rows.
 *
 * An orphan is a vimeo_files2 row where resource2_id = 0 and timecreated is
 * older than GRACE_SECONDS (default 24 h). These arise when a teacher starts
 * an upload inside the mod_form, the video is received and sent to Vimeo in
 * the background, but the teacher never saves the form (closes the tab, etc.).
 *
 * For each orphan:
 *   • If url is a numeric Vimeo video ID: call Vimeo DELETE API (respects
 *     the delete_from_vimeo admin setting) and decrement quota counters.
 *   • Delete the vimeo_files2 DB row.
 *
 * Rows with timecreated = 0 (inserted before this column existed) are treated
 * as infinitely old and are always cleaned up.
 *
 * @package    mod_resource2
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_resource2\task;

defined('MOODLE_INTERNAL') || die();

class cleanup_orphan_uploads extends \core\task\scheduled_task {

    /** Minimum age in seconds before an orphan is eligible for deletion. */
    const GRACE_SECONDS = 86400; // 24 hours

    public function get_name() {
        return get_string('task_cleanup_orphans', 'resource2');
    }

    public function execute() {
        global $CFG, $DB;

        $cutoff = time() - self::GRACE_SECONDS;

        // Find all orphaned rows: resource2_id = 0 AND old enough.
        // timecreated = 0 means the row pre-dates the column — treat as ancient.
        $orphans = $DB->get_records_select(
            'vimeo_files2',
            'resource2_id = 0 AND (timecreated = 0 OR timecreated < :cutoff)',
            ['cutoff' => $cutoff]
        );

        if (empty($orphans)) {
            mtrace('mod_resource2 cleanup: no orphaned rows found.');
            return;
        }

        mtrace('mod_resource2 cleanup: found ' . count($orphans) . ' orphaned row(s).');

        // Load Vimeo SDK once.
        $vimeo_loaded = false;
        $vimeoclient  = null;
        try {
            require_once($CFG->dirroot . '/vimeo/vendor/autoload.php');
            $vimeoclient = new \Vimeo\Vimeo(
                "4dad588b7f47a44426afc26f398fe2367ea49c92",
                "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s",
                "195c95a4e775fca8d6e70cb8db4aca73"
            );
            $vimeo_loaded = true;
        } catch (\Exception $e) {
            mtrace('mod_resource2 cleanup: could not load Vimeo SDK — ' . $e->getMessage());
        }

        $delete_from_vimeo = (bool)get_config('mod_resource2', 'delete_from_vimeo');

        foreach ($orphans as $row) {
            $video_id = trim((string)($row->url ?? ''));

            if ($vimeo_loaded && $vimeoclient && $video_id && ctype_digit($video_id)) {
                // Fetch video size so we can decrement the quota accurately.
                $size_bytes = 0;
                try {
                    $meta = $vimeoclient->request(
                        '/videos/' . $video_id . '?fields=upload.size', [], 'GET');
                    if (!empty($meta['body']['upload']['size'])) {
                        $size_bytes = (int)$meta['body']['upload']['size'];
                    }
                } catch (\Exception $e) {
                    mtrace('mod_resource2 cleanup: could not fetch size for video '
                        . $video_id . ' — ' . $e->getMessage());
                }

                // Delete from Vimeo if the admin setting allows it.
                if ($delete_from_vimeo) {
                    try {
                        $vimeoclient->request('/videos/' . $video_id, [], 'DELETE');
                        mtrace('mod_resource2 cleanup: deleted Vimeo video ' . $video_id);
                    } catch (\Exception $e) {
                        mtrace('mod_resource2 cleanup: Vimeo DELETE failed for video '
                            . $video_id . ' — ' . $e->getMessage());
                        // Continue — still remove the DB row.
                    }
                }

                // Decrement quota counters.
                $cur_count   = (int)(get_config('resource2', 'video_count')         ?: 0);
                $cur_storage = (int)(get_config('resource2', 'total_storage_bytes') ?: 0);
                set_config('video_count',         max(0, $cur_count   - 1),                 'resource2');
                set_config('total_storage_bytes', max(0, $cur_storage - $size_bytes),       'resource2');
                mtrace('mod_resource2 cleanup: decremented quota (count=' . max(0, $cur_count - 1)
                    . ', bytes=' . max(0, $cur_storage - $size_bytes) . ')');
            }

            // Delete the DB row.
            $DB->delete_records('vimeo_files2', ['id' => $row->id]);
            mtrace('mod_resource2 cleanup: deleted orphan row id=' . $row->id
                . ' (resource2_id=0, vimeo=' . ($video_id ?: 'none') . ')');
        }

        mtrace('mod_resource2 cleanup: done.');
    }
}
