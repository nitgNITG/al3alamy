<?php
/**
 * Scheduled task: clean up orphaned video upload artefacts.
 *
 * Since upload.php no longer writes to the database, orphans are simply
 * assembled temp files sitting in {dataroot}/resource2_tmp_uploads/ that
 * were never consumed by resource2_add_instance() (teacher closed the tab,
 * etc.).  We delete any `*_video.part_assembled` file older than GRACE_SECONDS.
 *
 * As a secondary safety net we also delete any stray vimeo_files2 rows that
 * still have resource2_id = 0 (could exist from before the architectural
 * change), optionally removing the Vimeo video too.
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

        $cutoff     = time() - self::GRACE_SECONDS;
        $upload_dir = $CFG->dataroot . '/resource2_tmp_uploads';

        // ── 1. Delete stale assembled temp files on disk ──────────────────────
        if (is_dir($upload_dir)) {
            $files = glob($upload_dir . '/*_video.part_assembled');
            if (!empty($files)) {
                $deleted = 0;
                foreach ($files as $f) {
                    if (!is_file($f)) {
                        continue;
                    }
                    if (filemtime($f) < $cutoff) {
                        @unlink($f);
                        mtrace('mod_resource2 cleanup: deleted stale temp file ' . basename($f));
                        $deleted++;
                    }
                }
                mtrace('mod_resource2 cleanup: removed ' . $deleted . ' stale temp file(s).');
            } else {
                mtrace('mod_resource2 cleanup: no stale temp files found.');
            }

            // Also delete any stray .part (incomplete chunk) files older than grace period.
            $parts = glob($upload_dir . '/*.part');
            if (!empty($parts)) {
                foreach ($parts as $f) {
                    if (is_file($f) && filemtime($f) < $cutoff) {
                        @unlink($f);
                        mtrace('mod_resource2 cleanup: deleted stale .part file ' . basename($f));
                    }
                }
            }

            // Delete stale params JSON files.
            $jsons = glob($upload_dir . '/vimeo_params_*.json');
            if (!empty($jsons)) {
                foreach ($jsons as $f) {
                    if (is_file($f) && filemtime($f) < $cutoff) {
                        @unlink($f);
                        mtrace('mod_resource2 cleanup: deleted stale params file ' . basename($f));
                    }
                }
            }
        } else {
            mtrace('mod_resource2 cleanup: temp directory does not exist — nothing to clean.');
        }

        // ── 2. Safety net: remove any legacy vimeo_files2 rows with resource2_id=0 ──
        $orphans = $DB->get_records_select(
            'vimeo_files2',
            'resource2_id = 0 AND (timecreated = 0 OR timecreated < :cutoff)',
            ['cutoff' => $cutoff]
        );

        if (empty($orphans)) {
            mtrace('mod_resource2 cleanup: no orphaned DB rows found.');
            mtrace('mod_resource2 cleanup: done.');
            return;
        }

        mtrace('mod_resource2 cleanup: found ' . count($orphans) . ' orphaned DB row(s).');

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
                // Fetch video size to decrement quota accurately.
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

                if ($delete_from_vimeo) {
                    try {
                        $vimeoclient->request('/videos/' . $video_id, [], 'DELETE');
                        mtrace('mod_resource2 cleanup: deleted Vimeo video ' . $video_id);
                    } catch (\Exception $e) {
                        mtrace('mod_resource2 cleanup: Vimeo DELETE failed for video '
                            . $video_id . ' — ' . $e->getMessage());
                    }
                }

                // Decrement quota counters.
                $cur_count   = (int)(get_config('resource2', 'video_count')         ?: 0);
                $cur_storage = (int)(get_config('resource2', 'total_storage_bytes') ?: 0);
                set_config('video_count',         max(0, $cur_count   - 1),           'resource2');
                set_config('total_storage_bytes', max(0, $cur_storage - $size_bytes), 'resource2');
            }

            $DB->delete_records('vimeo_files2', ['id' => $row->id]);
            mtrace('mod_resource2 cleanup: deleted orphan DB row id=' . $row->id
                . ' (vimeo=' . ($video_id ?: 'none') . ')');
        }

        mtrace('mod_resource2 cleanup: done.');
    }
}
