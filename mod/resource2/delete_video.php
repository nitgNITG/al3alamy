<?php
/**
 * Video deletion AJAX endpoint for mod_resource2.
 *
 * Deletes the vimeo_files2 row associated with a resource2 module,
 * optionally deletes the video from the Vimeo API (respects the
 * mod_resource2 / delete_from_vimeo admin setting), and decrements
 * the platform quota counters.
 *
 * POST params:
 *   resource2_id  — ID of the resource2 module whose video to delete
 *   sesskey       — Moodle sesskey (CSRF protection)
 *   courseid      — course ID for capability check
 *
 * Returns JSON: {"OK":1} on success, {"OK":0,"info":"..."} on error.
 *
 * @package    mod_resource2
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');

// ── Auth + CSRF ────────────────────────────────────────────────────────────
require_login();
require_sesskey();

header('Content-Type: application/json');

$courseid     = required_param('courseid',     PARAM_INT);
$resource2_id = required_param('resource2_id', PARAM_INT);
$context      = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// ── Load the vimeo record ──────────────────────────────────────────────────
$vimeo_rec = $DB->get_record('vimeo_files2', ['resource2_id' => $resource2_id]);
if (!$vimeo_rec) {
    die(json_encode(['OK' => 0, 'info' => 'No Vimeo record found for this resource.']));
}

$vimeo_video_id  = trim($vimeo_rec->url ?? '');
$vimeo_size_bytes = 0;

// ── Optionally delete from Vimeo API ──────────────────────────────────────
if ($vimeo_video_id && ctype_digit($vimeo_video_id)) {
    try {
        require_once($CFG->dirroot . '/vimeo/vendor/autoload.php');
        $vimeoclient = new \Vimeo\Vimeo(
            "4dad588b7f47a44426afc26f398fe2367ea49c92",
            "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s",
            "195c95a4e775fca8d6e70cb8db4aca73"
        );

        // Fetch size before deleting so we can update the quota counter.
        $meta = $vimeoclient->request('/videos/' . $vimeo_video_id . '?fields=upload.size', [], 'GET');
        if (!empty($meta['body']['upload']['size'])) {
            $vimeo_size_bytes = (int)$meta['body']['upload']['size'];
        }

        if (get_config('mod_resource2', 'delete_from_vimeo')) {
            $vimeoclient->request('/videos/' . $vimeo_video_id, [], 'DELETE');
            error_log('delete_video.php: deleted Vimeo video ' . $vimeo_video_id
                . ' for resource2 id=' . $resource2_id);
        }
    } catch (Exception $e) {
        error_log('delete_video.php: Vimeo API error for video ' . $vimeo_video_id
            . ' — ' . $e->getMessage());
        // Continue — still delete the DB record.
    }
}

// ── Decrement quota counters ───────────────────────────────────────────────
if ($vimeo_video_id && ctype_digit($vimeo_video_id)) {
    $cur_count   = (int)(get_config('resource2', 'video_count') ?: 0);
    $cur_storage = (int)(get_config('resource2', 'total_storage_bytes') ?: 0);
    set_config('video_count',         max(0, $cur_count   - 1),                  'resource2');
    set_config('total_storage_bytes', max(0, $cur_storage - $vimeo_size_bytes),  'resource2');
}

// ── Delete DB records ──────────────────────────────────────────────────────
$DB->delete_records('vimeo_files2',   ['id'           => $vimeo_rec->id]);
$DB->delete_records('reda_video_type2', ['resource2_id' => $resource2_id]);

error_log('delete_video.php: deleted vimeo_files2 id=' . $vimeo_rec->id
    . ' for resource2 id=' . $resource2_id);

die(json_encode(['OK' => 1]));
