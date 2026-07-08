<?php
/**
 * Admin management page for registration codes.
 *
 * Site administration → Users → Registration Codes → Manage Codes
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_registrationcodes\manager;
use local_registrationcodes\event\code_deleted;

// Access control.
admin_externalpage_setup('local_registrationcodes_admin');
require_capability('local/registrationcodes:manage', context_system::instance());

$PAGE->set_url(new moodle_url('/local/registrationcodes/admin.php'));
$PAGE->set_title(get_string('manage_codes', 'local_registrationcodes'));
$PAGE->set_heading(get_string('manage_codes', 'local_registrationcodes'));

// ── Handle POST actions ────────────────────────────────────────────────────

$action = optional_param('action', '', PARAM_ALPHA);
$ids    = optional_param_array('codeids', [], PARAM_INT);

if ($action && in_array($action, ['enable', 'disable', 'delete'])) {
    require_sesskey();
    // Single-row quick action.
    $singleid = optional_param('id', 0, PARAM_INT);
    if ($singleid) {
        $ids = [$singleid];
    }

    if (!empty($ids)) {
        if ($action === 'delete') {
            require_capability('local/registrationcodes:delete', context_system::instance());
            manager::delete_codes($ids);
            // Fire event.
            $event = code_deleted::create([
                'context' => context_system::instance(),
                'other'   => ['count' => count($ids)],
            ]);
            $event->trigger();
        } elseif ($action === 'enable') {
            manager::set_status_bulk($ids, manager::STATUS_UNUSED);
        } elseif ($action === 'disable') {
            manager::set_status_bulk($ids, manager::STATUS_DISABLED);
        }
        redirect(
            new moodle_url('/local/registrationcodes/admin.php'),
            get_string('action_done', 'local_registrationcodes'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// ── Handle Generate form submission ───────────────────────────────────────

$generateform = new \local_registrationcodes\form\generate_form();
$generated    = null;

if ($formdata = $generateform->get_data()) {
    require_capability('local/registrationcodes:generate', context_system::instance());

    $qty = (int)$formdata->quantity_preset;
    if ($qty === 0) {
        $qty = max(1, min(5000, (int)$formdata->quantity_custom));
    }

    $prefix  = isset($formdata->prefix) ? trim($formdata->prefix) : '';
    $expiry  = !empty($formdata->timeexpiry) ? (int)$formdata->timeexpiry : null;
    $notes   = isset($formdata->notes) ? trim($formdata->notes) : '';

    $generated = manager::generate_codes($qty, $prefix, $expiry, $notes, $USER->id);

    redirect(
        new moodle_url('/local/registrationcodes/admin.php'),
        get_string('codes_generated', 'local_registrationcodes', count($generated)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── Search / filter params ────────────────────────────────────────────────

$search      = optional_param('search', '', PARAM_TEXT);
$filterstatus = optional_param('filterstatus', '', PARAM_ALPHA);
$page        = optional_param('page', 0, PARAM_INT);
$perpage     = 50;

// ── Query codes ───────────────────────────────────────────────────────────

$params     = [];
$where      = [];
$sqlsearch  = '';

if ($search !== '') {
    $where[]           = '(' . $DB->sql_like('c.code', ':search1', false) . ' OR ' . $DB->sql_like('c.notes', ':search2', false) . ')';
    $params['search1'] = '%' . $DB->sql_like_escape($search) . '%';
    $params['search2'] = '%' . $DB->sql_like_escape($search) . '%';
}

if ($filterstatus !== '' && in_array($filterstatus, [manager::STATUS_UNUSED, manager::STATUS_USED, manager::STATUS_EXPIRED, manager::STATUS_DISABLED])) {
    $where[]              = 'c.status = :filterstatus';
    $params['filterstatus'] = $filterstatus;
}

$wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalcount = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {local_regcodes} c $wheresql",
    $params
);

$codes = $DB->get_records_sql(
    "SELECT c.*,
            " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS creatorname,
            u.id AS creator_id
       FROM {local_regcodes} c
  LEFT JOIN {user} u ON u.id = c.created_by
  $wheresql
   ORDER BY c.timecreated DESC, c.id DESC",
    $params,
    $page * $perpage,
    $perpage
);

// ── Stats ─────────────────────────────────────────────────────────────────

$stats = manager::get_stats();

// ── Output ────────────────────────────────────────────────────────────────

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_codes', 'local_registrationcodes'));

// ─ Stats bar ─
echo '<div class="d-flex flex-wrap gap-3 mb-4">';
$statdata = [
    ['label' => get_string('stats_total',    'local_registrationcodes'), 'val' => $stats['total'],    'cls' => 'secondary'],
    ['label' => get_string('stats_unused',   'local_registrationcodes'), 'val' => $stats['unused'],   'cls' => 'success'],
    ['label' => get_string('stats_used',     'local_registrationcodes'), 'val' => $stats['used'],     'cls' => 'primary'],
    ['label' => get_string('stats_expired',  'local_registrationcodes'), 'val' => $stats['expired'],  'cls' => 'warning'],
    ['label' => get_string('stats_disabled', 'local_registrationcodes'), 'val' => $stats['disabled'], 'cls' => 'danger'],
    ['label' => get_string('stats_usage',    'local_registrationcodes'), 'val' => $stats['usage_pct'] . '%', 'cls' => 'info'],
];
foreach ($statdata as $s) {
    echo '<div class="card text-center border-' . $s['cls'] . '" style="min-width:110px;">';
    echo '<div class="card-body p-2">';
    echo '<div class="h3 mb-0 text-' . $s['cls'] . '">' . $s['val'] . '</div>';
    echo '<small class="text-muted">' . $s['label'] . '</small>';
    echo '</div></div>';
}
echo '</div>';

// ─ Generate form ─
echo '<div class="card mb-4"><div class="card-body">';
$generateform->display();
echo '</div></div>';

// ─ Search / filter bar ─
$filterurl = new moodle_url('/local/registrationcodes/admin.php');
echo '<form method="get" action="' . $filterurl->out(false) . '" class="form-inline mb-3">';
echo '<input type="text" name="search" class="form-control mr-2 mb-1" placeholder="' . get_string('search_code', 'local_registrationcodes') . '" value="' . s($search) . '">';
echo '<select name="filterstatus" class="custom-select mr-2 mb-1">';
echo '<option value="">' . get_string('filter_all', 'local_registrationcodes') . '</option>';
foreach ([manager::STATUS_UNUSED, manager::STATUS_USED, manager::STATUS_EXPIRED, manager::STATUS_DISABLED] as $st) {
    $sel = ($filterstatus === $st) ? ' selected' : '';
    echo '<option value="' . $st . '"' . $sel . '>' . get_string('status_' . $st, 'local_registrationcodes') . '</option>';
}
echo '</select>';
echo '<button type="submit" class="btn btn-secondary mb-1">' . get_string('search', 'local_registrationcodes') . '</button>';
if ($search || $filterstatus) {
    echo ' <a href="' . $filterurl->out(false) . '" class="btn btn-link mb-1">✕ Clear</a>';
}
echo '</form>';

// ─ Bulk action form + table ─
$bulkurl = new moodle_url('/local/registrationcodes/admin.php', ['sesskey' => sesskey()]);

echo '<form method="post" action="' . $bulkurl->out(false) . '" id="codesform">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

if (empty($codes)) {
    echo $OUTPUT->notification(get_string('no_codes', 'local_registrationcodes'), 'info');
} else {
    // Bulk action buttons.
    echo '<div class="mb-2">';
    echo '<button type="submit" name="action" value="enable"  class="btn btn-sm btn-success mr-1">' . get_string('bulk_enable',  'local_registrationcodes') . '</button>';
    echo '<button type="submit" name="action" value="disable" class="btn btn-sm btn-warning mr-1">' . get_string('bulk_disable', 'local_registrationcodes') . '</button>';
    echo '<button type="submit" name="action" value="delete"  class="btn btn-sm btn-danger"'
        . ' onclick="return confirm(\'' . addslashes(get_string('confirm_delete', 'local_registrationcodes')) . '\')">'
        . get_string('bulk_delete', 'local_registrationcodes') . '</button>';
    echo '</div>';

    // Table.
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-bordered table-hover generaltable">';
    echo '<thead class="thead-light"><tr>';
    echo '<th><input type="checkbox" id="selectall" title="Select all"></th>';
    echo '<th>' . get_string('code',        'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('status',      'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('created_by',  'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('timecreated', 'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('timeexpiry',  'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('used_by',     'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('timeused',    'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('notes',       'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('actions',     'local_registrationcodes') . '</th>';
    echo '</tr></thead><tbody>';

    $statusbadge = [
        manager::STATUS_UNUSED   => 'badge-success',
        manager::STATUS_USED     => 'badge-primary',
        manager::STATUS_EXPIRED  => 'badge-warning',
        manager::STATUS_DISABLED => 'badge-danger',
    ];

    foreach ($codes as $c) {
        $badge  = $statusbadge[$c->status] ?? 'badge-secondary';
        $expiry = $c->timeexpiry ? userdate($c->timeexpiry, get_string('strftimedatefullshort', 'langconfig')) : '—';
        $usedby = '';
        if ($c->used_by) {
            $usedbyurl = new moodle_url('/user/profile.php', ['id' => $c->used_by]);
            $usedby    = html_writer::link($usedbyurl, $c->used_by);
        }
        $usedtime  = $c->timeused ? userdate($c->timeused, get_string('strftimedatefullshort', 'langconfig')) : '—';
        $rowurl    = new moodle_url('/local/registrationcodes/admin.php', ['sesskey' => sesskey(), 'id' => $c->id]);

        echo '<tr>';
        echo '<td><input type="checkbox" name="codeids[]" value="' . $c->id . '"></td>';
        echo '<td><code>' . s($c->code) . '</code></td>';
        echo '<td><span class="badge ' . $badge . '">' . get_string('status_' . $c->status, 'local_registrationcodes') . '</span></td>';
        echo '<td>' . (isset($c->creatorname) && $c->creatorname ? html_writer::link(new moodle_url('/user/profile.php', ['id' => $c->creator_id]), s($c->creatorname)) : '—') . '</td>';
        echo '<td>' . userdate($c->timecreated, get_string('strftimedatefullshort', 'langconfig')) . '</td>';
        echo '<td>' . $expiry . '</td>';
        echo '<td>' . ($usedby ?: '—') . '</td>';
        echo '<td>' . $usedtime . '</td>';
        echo '<td>' . s($c->notes ?? '') . '</td>';
        echo '<td class="nowrap">';

        if ($c->status !== manager::STATUS_USED) {
            if ($c->status === manager::STATUS_DISABLED) {
                echo html_writer::link(
                    new moodle_url($rowurl, ['action' => 'enable']),
                    get_string('enable', 'local_registrationcodes'),
                    ['class' => 'btn btn-xs btn-success mr-1']
                );
            } else {
                echo html_writer::link(
                    new moodle_url($rowurl, ['action' => 'disable']),
                    get_string('disable', 'local_registrationcodes'),
                    ['class' => 'btn btn-xs btn-warning mr-1']
                );
            }
            echo html_writer::link(
                new moodle_url($rowurl, ['action' => 'delete']),
                get_string('delete', 'local_registrationcodes'),
                [
                    'class'   => 'btn btn-xs btn-danger',
                    'onclick' => 'return confirm(\'' . addslashes(get_string('confirm_delete', 'local_registrationcodes')) . '\')',
                ]
            );
        }

        echo '</td></tr>';
    }

    echo '</tbody></table></div>';

    // Pagination.
    $paginationurl = new moodle_url('/local/registrationcodes/admin.php', [
        'search'       => $search,
        'filterstatus' => $filterstatus,
    ]);
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $paginationurl);
}

echo '</form>';

// ─ Select-all JS ─
echo <<<'JS'
<script>
document.getElementById('selectall').addEventListener('change', function() {
    document.querySelectorAll('input[name="codeids[]"]').forEach(function(cb) {
        cb.checked = document.getElementById('selectall').checked;
    });
});
</script>
JS;

echo $OUTPUT->footer();
