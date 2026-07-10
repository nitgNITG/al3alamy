<?php
/**
 * Usage report page for registration codes.
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_registrationcodes\manager;

admin_externalpage_setup('local_registrationcodes_report');
require_capability('local/registrationcodes:viewreports', context_system::instance());

$PAGE->set_url(new moodle_url('/local/registrationcodes/report.php'));
$PAGE->set_title(get_string('report_title', 'local_registrationcodes'));
$PAGE->set_heading(get_string('report_title', 'local_registrationcodes'));

// ── Filter params ─────────────────────────────────────────────────────────

$search       = optional_param('search',       '', PARAM_TEXT);
$filterstatus = optional_param('filterstatus', '', PARAM_ALPHA);
$filteruser   = optional_param('filteruser',   '', PARAM_TEXT);
$filtergroup  = optional_param('filtergroup',  '', PARAM_TEXT);
$datefrom     = optional_param('datefrom',      0, PARAM_INT);
$dateto       = optional_param('dateto',        0, PARAM_INT);
$export       = optional_param('export',       '', PARAM_ALPHA);
$filetitle    = optional_param('filetitle',    '', PARAM_TEXT);
$page         = optional_param('page',          0, PARAM_INT);
$perpage      = 50;

if ($filetitle === '') {
    $filetitle = get_string('export_default_title', 'local_registrationcodes');
}
$filetitle = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $filetitle);

// ── Build query ───────────────────────────────────────────────────────────

$where  = [];
$params = [];

if ($filterstatus !== '') {
    $where[]              = 'c.status = :filterstatus';
    $params['filterstatus'] = $filterstatus;
}
if ($filtergroup !== '') {
    $where[]             = 'c.groupname = :filtergroup';
    $params['filtergroup'] = $filtergroup;
}
if ($search !== '') {
    $where[]           = '(' . $DB->sql_like('c.code', ':csearch', false) . ' OR ' . $DB->sql_like('u2.username', ':usearch', false) . ')';
    $params['csearch'] = '%' . $DB->sql_like_escape($search) . '%';
    $params['usearch'] = '%' . $DB->sql_like_escape($search) . '%';
}
if ($filteruser !== '') {
    $where[]       = '(' . $DB->sql_like('u2.firstname', ':fu1', false) . ' OR ' . $DB->sql_like('u2.lastname', ':fu2', false) . ' OR ' . $DB->sql_like('u2.email', ':fu3', false) . ')';
    $params['fu1'] = '%' . $DB->sql_like_escape($filteruser) . '%';
    $params['fu2'] = '%' . $DB->sql_like_escape($filteruser) . '%';
    $params['fu3'] = '%' . $DB->sql_like_escape($filteruser) . '%';
}
if ($datefrom > 0) {
    $where[]           = 'c.timeused >= :datefrom';
    $params['datefrom'] = $datefrom;
}
if ($dateto > 0) {
    $where[]         = 'c.timeused <= :dateto';
    $params['dateto'] = $dateto;
}

$wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$basesql = "
      FROM {local_regcodes} c
 LEFT JOIN {user} u2 ON u2.id = c.used_by
 LEFT JOIN {user} u3 ON u3.id = c.created_by
  $wheresql";

$countsql  = "SELECT COUNT(*) $basesql";
$selectsql = "SELECT c.*,
                     u2.username   AS reg_username,
                     u2.firstname  AS reg_firstname,
                     u2.lastname   AS reg_lastname,
                     u2.email      AS reg_email,
                     " . $DB->sql_fullname('u2.firstname', 'u2.lastname') . " AS reg_fullname,
                     " . $DB->sql_fullname('u3.firstname', 'u3.lastname') . " AS creator_fullname
              $basesql
              ORDER BY c.timeused DESC NULLS LAST, c.timecreated DESC";

$totalcount = $DB->count_records_sql($countsql, $params);

// ── Export handling ───────────────────────────────────────────────────────

if ($export !== '') {
    $rows      = $DB->get_records_sql($selectsql, $params);
    $safetitle = str_replace(' ', '_', trim($filetitle));

    $headers = [
        get_string('groupname',  'local_registrationcodes'),
        get_string('code',       'local_registrationcodes'),
        get_string('status',     'local_registrationcodes'),
        get_string('fullname',   'local_registrationcodes'),
        get_string('email',      'local_registrationcodes'),
        get_string('regdate',    'local_registrationcodes'),
        get_string('created_by', 'local_registrationcodes'),
        get_string('timeexpiry', 'local_registrationcodes'),
        get_string('notes',      'local_registrationcodes'),
    ];

    if ($export === 'csv') {
        $filename = $safetitle . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r->groupname ?? '',
                $r->code,
                get_string('status_' . $r->status, 'local_registrationcodes'),
                $r->reg_fullname ?? '',
                $r->reg_email    ?? '',
                $r->timeused   ? userdate($r->timeused)   : '',
                $r->creator_fullname ?? '',
                $r->timeexpiry ? userdate($r->timeexpiry) : '',
                $r->notes ?? '',
            ]);
        }
        fclose($out);
        exit;

    } elseif ($export === 'excel') {
        require_once($CFG->libdir . '/excellib.class.php');
        $filename = $safetitle . '_' . date('Ymd_His') . '.xls';
        $workbook = new MoodleExcelWorkbook('-');
        $workbook->send($filename);
        $sheet    = $workbook->add_worksheet(get_string('report_title', 'local_registrationcodes'));
        $col = 0;
        foreach ($headers as $h) { $sheet->write(0, $col++, $h); }
        $row = 1;
        foreach ($rows as $r) {
            $sheet->write($row, 0, $r->groupname ?? '');
            $sheet->write($row, 1, $r->code);
            $sheet->write($row, 2, get_string('status_' . $r->status, 'local_registrationcodes'));
            $sheet->write($row, 3, $r->reg_fullname ?? '');
            $sheet->write($row, 4, $r->reg_email    ?? '');
            $sheet->write($row, 5, $r->timeused   ? userdate($r->timeused)   : '');
            $sheet->write($row, 6, $r->creator_fullname ?? '');
            $sheet->write($row, 7, $r->timeexpiry ? userdate($r->timeexpiry) : '');
            $sheet->write($row, 8, $r->notes ?? '');
            $row++;
        }
        $workbook->close();
        exit;
    }
}

// ── Paginated fetch ───────────────────────────────────────────────────────

$records = $DB->get_records_sql($selectsql, $params, $page * $perpage, $perpage);
$groups  = manager::get_groups();

// ── Output ────────────────────────────────────────────────────────────────

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_title', 'local_registrationcodes'));

// Filter form.
$filterurl = new moodle_url('/local/registrationcodes/report.php');
echo '<form method="get" action="' . $filterurl->out(false) . '" class="form-inline mb-3 flex-wrap">';
echo '<input type="text" name="search" class="form-control mr-2 mb-1" placeholder="' . get_string('search_code', 'local_registrationcodes') . '" value="' . s($search) . '">';
echo '<input type="text" name="filteruser" class="form-control mr-2 mb-1" placeholder="' . get_string('filter_creator', 'local_registrationcodes') . '" value="' . s($filteruser) . '">';
echo '<select name="filterstatus" class="custom-select mr-2 mb-1">';
echo '<option value="">' . get_string('filter_all', 'local_registrationcodes') . '</option>';
foreach ([manager::STATUS_UNUSED, manager::STATUS_USED, manager::STATUS_EXPIRED, manager::STATUS_DISABLED] as $st) {
    $sel = ($filterstatus === $st) ? ' selected' : '';
    echo '<option value="' . $st . '"' . $sel . '>' . get_string('status_' . $st, 'local_registrationcodes') . '</option>';
}
echo '</select>';
if (!empty($groups)) {
    echo '<select name="filtergroup" class="custom-select mr-2 mb-1">';
    echo '<option value="">' . get_string('all_groups', 'local_registrationcodes') . '</option>';
    foreach ($groups as $g) {
        $sel = ($filtergroup === $g) ? ' selected' : '';
        echo '<option value="' . s($g) . '"' . $sel . '>' . s($g) . '</option>';
    }
    echo '</select>';
}
echo '<button type="submit" class="btn btn-secondary mb-1 mr-2">' . get_string('search', 'local_registrationcodes') . '</button>';
echo '</form>';

// Export row: configurable title + CSV/Excel buttons.
$exportparams = ['search' => $search, 'filterstatus' => $filterstatus, 'filteruser' => $filteruser, 'filtergroup' => $filtergroup];
echo '<form method="get" action="' . $filterurl->out(false) . '" class="form-inline mb-4 flex-wrap align-items-center">';
foreach ($exportparams as $k => $v) {
    echo '<input type="hidden" name="' . s($k) . '" value="' . s($v) . '">';
}
echo '<label class="mr-2 mb-1"><strong>' . get_string('file_title', 'local_registrationcodes') . ':</strong></label>';
echo '<input type="text" name="filetitle" class="form-control mr-2 mb-1" style="min-width:200px;"'
    . ' placeholder="' . get_string('export_default_title', 'local_registrationcodes') . '"'
    . ' value="' . s($filetitle) . '">';
echo '<button type="submit" name="export" value="csv"   class="btn btn-outline-secondary mb-1 mr-1">' . get_string('export_csv',   'local_registrationcodes') . '</button>';
echo '<button type="submit" name="export" value="excel" class="btn btn-outline-secondary mb-1">'      . get_string('export_excel', 'local_registrationcodes') . '</button>';
echo '</form>';

// Table.
if (empty($records)) {
    echo $OUTPUT->notification(get_string('no_records', 'local_registrationcodes'), 'info');
} else {
    $statusbadge = [
        manager::STATUS_UNUSED   => 'badge-success',
        manager::STATUS_USED     => 'badge-primary',
        manager::STATUS_EXPIRED  => 'badge-warning',
        manager::STATUS_DISABLED => 'badge-danger',
    ];

    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-bordered table-hover generaltable">';
    echo '<thead class="thead-light"><tr>';
    echo '<th>' . get_string('groupname',  'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('code',       'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('status',     'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('fullname',   'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('email',      'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('regdate',    'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('created_by', 'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('timeexpiry', 'local_registrationcodes') . '</th>';
    echo '<th>' . get_string('notes',      'local_registrationcodes') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($records as $r) {
        $badge    = $statusbadge[$r->status] ?? 'badge-secondary';
        $expiry   = $r->timeexpiry ? userdate($r->timeexpiry, get_string('strftimedatefullshort', 'langconfig')) : '—';
        $regdate  = $r->timeused   ? userdate($r->timeused,   get_string('strftimedatefullshort', 'langconfig')) : '—';
        $fullname = $email = '';
        if ($r->used_by) {
            $profileurl = new moodle_url('/user/profile.php', ['id' => $r->used_by]);
            $fullname   = html_writer::link($profileurl, s($r->reg_fullname ?? ''));
            $email      = s($r->reg_email ?? '');
        }
        $groupcell = !empty($r->groupname)
            ? html_writer::link(new moodle_url('/local/registrationcodes/report.php', ['filtergroup' => $r->groupname]), s($r->groupname), ['class' => 'badge badge-info'])
            : '<span class="text-muted">—</span>';

        echo '<tr>';
        echo '<td>' . $groupcell . '</td>';
        echo '<td><code>' . s($r->code) . '</code></td>';
        echo '<td><span class="badge ' . $badge . '">' . get_string('status_' . $r->status, 'local_registrationcodes') . '</span></td>';
        echo '<td>' . ($fullname ?: '—') . '</td>';
        echo '<td>' . ($email    ?: '—') . '</td>';
        echo '<td>' . $regdate . '</td>';
        echo '<td>' . s($r->creator_fullname ?? '—') . '</td>';
        echo '<td>' . $expiry . '</td>';
        echo '<td>' . s($r->notes ?? '') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';

    $paginationurl = new moodle_url('/local/registrationcodes/report.php', [
        'search'       => $search,
        'filterstatus' => $filterstatus,
        'filteruser'   => $filteruser,
        'filtergroup'  => $filtergroup,
    ]);
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $paginationurl);
}

echo $OUTPUT->footer();
