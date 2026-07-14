<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

require_login();
$context = context_system::instance();
require_capability('local/subscriptions:viewreports', $context);

admin_externalpage_setup('local_subscriptions_report');

use local_subscriptions\manager;

// Filters.
$filter_plan   = optional_param('planid', 0, PARAM_INT);
$filter_status = optional_param('status', '', PARAM_ALPHA);
$filter_source = optional_param('source', '', PARAM_ALPHA);
$filter_refund = optional_param('refund', '', PARAM_ALPHA); // refunded | notrefunded
$filter_from   = optional_param('from', '', PARAM_TEXT);
$filter_to     = optional_param('to', '', PARAM_TEXT);
$export        = optional_param('export', '', PARAM_ALPHA);
$page          = optional_param('page', 0, PARAM_INT);
$perpage       = 30;

$stats  = manager::get_stats();
$plans  = manager::get_plans();

// Build query.
$where  = [];
$params = [];

if ($filter_plan) {
    $where[]           = 's.planid = :planid';
    $params['planid']  = $filter_plan;
}
if ($filter_status) {
    $where[]           = 's.status = :status';
    $params['status']  = $filter_status;
}
if ($filter_source) {
    $where[]           = 's.source = :source';
    $params['source']  = $filter_source;
}
if ($filter_refund === 'refunded') {
    $where[] = 's.refund_amount IS NOT NULL AND s.refund_amount > 0';
} else if ($filter_refund === 'notrefunded') {
    $where[] = '(s.refund_amount IS NULL OR s.refund_amount = 0)';
}
$from_ts = $filter_from ? strtotime($filter_from) : 0;
$to_ts   = $filter_to ? strtotime($filter_to . ' 23:59:59') : 0;
if ($from_ts) {
    $where[]         = 's.timecreated >= :fromts';
    $params['fromts'] = $from_ts;
}
if ($to_ts) {
    $where[]        = 's.timecreated <= :tots';
    $params['tots'] = $to_ts;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// CSV export (US-AD-2-3): stream the full filtered set, no paging.
if ($export === 'csv') {
    $rows = $DB->get_records_sql(
        "SELECT s.*, p.name AS plan_name, u.firstname, u.lastname, u.email
           FROM {local_subscriptions_users} s
           JOIN {local_subscriptions_plans} p ON p.id = s.planid
           JOIN {user} u ON u.id = s.userid
           $where_sql
          ORDER BY s.timecreated DESC", $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscriptions_report_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel.
    fputcsv($out, ['ID', 'User', 'Email', 'Plan', 'Source', 'Status', 'Start', 'Expiry',
        'Amount', 'Refund', 'Order ID', 'Transaction ID']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r->id,
            trim($r->firstname . ' ' . $r->lastname),
            $r->email,
            $r->plan_name,
            $r->source,
            $r->status,
            $r->start_time ? userdate($r->start_time, '%Y-%m-%d') : '',
            $r->expiry_time ? userdate($r->expiry_time, '%Y-%m-%d') : '',
            number_format((float)$r->amount_paid, 2, '.', ''),
            $r->refund_amount ? number_format((float)$r->refund_amount, 2, '.', '') : '',
            $r->order_id,
            $r->transaction_id,
        ]);
    }
    fclose($out);
    exit;
}

$count_sql = "SELECT COUNT(*) FROM {local_subscriptions_users} s $where_sql";
$total     = $DB->count_records_sql($count_sql, $params);

$sql = "SELECT s.*, p.name AS plan_name,
               u.firstname, u.lastname, u.email
        FROM {local_subscriptions_users} s
        JOIN {local_subscriptions_plans} p ON p.id = s.planid
        JOIN {user} u ON u.id = s.userid
        $where_sql
        ORDER BY s.timecreated DESC";

$subscriptions = array_values($DB->get_records_sql($sql, $params, $page * $perpage, $perpage));

$PAGE->set_title(get_string('reports', 'local_subscriptions'));
$PAGE->set_heading(get_string('reports', 'local_subscriptions'));

echo $OUTPUT->header();
?>
<style>
.subs-stats-bar { display:flex; gap:14px; margin-bottom:20px; flex-wrap:wrap; }
.subs-stat-card { background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:12px 18px; text-align:center; min-width:110px; flex:1; }
.subs-stat-card .num { font-size:1.8em; font-weight:bold; color:#2d6a9f; display:block; }
.subs-stat-card .lbl { font-size:.82em; color:#666; margin-top:3px; display:block; }
.filter-bar { background:#f8f9fa; border:1px solid #dee2e6; border-radius:6px; padding:14px; margin-bottom:16px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
.filter-bar label { font-size:.85em; font-weight:600; display:block; margin-bottom:3px; }
.filter-bar select { padding:6px 10px; border:1px solid #ced4da; border-radius:4px; font-size:.9em; }
.filter-bar button { padding:6px 16px; background:#2d6a9f; color:#fff; border:none; border-radius:4px; cursor:pointer; }
.subs-table { width:100%; border-collapse:collapse; font-size:.9em; }
.subs-table th, .subs-table td { padding:8px 12px; border:1px solid #dee2e6; vertical-align:middle; }
.subs-table thead th { background:#2d6a9f; color:#fff; font-weight:600; }
.subs-table tbody tr:nth-child(even) { background:#f8f9fa; }
.badge-active   { background:#28a745; color:#fff; padding:2px 8px; border-radius:10px; font-size:.8em; }
.badge-expired  { background:#6c757d; color:#fff; padding:2px 8px; border-radius:10px; font-size:.8em; }
.badge-cancelled{ background:#dc3545; color:#fff; padding:2px 8px; border-radius:10px; font-size:.8em; }
.btn-unsub { background:#dc3545; color:#fff; border-radius:4px; padding:4px 12px; font-size:.82em; text-decoration:none; display:inline-block; }
.btn-unsub:hover { background:#c82333; color:#fff; }
</style>

<div class="subs-stats-bar">
    <div class="subs-stat-card">
        <span class="num"><?php echo $stats['total']; ?></span>
        <span class="lbl">إجمالي الاشتراكات</span>
    </div>
    <div class="subs-stat-card">
        <span class="num" style="color:#28a745"><?php echo $stats['active']; ?></span>
        <span class="lbl">فعال</span>
    </div>
    <div class="subs-stat-card">
        <span class="num" style="color:#6c757d"><?php echo $stats['expired']; ?></span>
        <span class="lbl">منتهي</span>
    </div>
    <div class="subs-stat-card">
        <span class="num" style="color:#dc3545"><?php echo $stats['cancelled']; ?></span>
        <span class="lbl">ملغى</span>
    </div>
    <div class="subs-stat-card">
        <span class="num" style="color:#17a2b8"><?php echo $stats['online']; ?></span>
        <span class="lbl">إلكتروني</span>
    </div>
    <div class="subs-stat-card">
        <span class="num" style="color:#856404"><?php echo $stats['manual']; ?></span>
        <span class="lbl">يدوي</span>
    </div>
    <div class="subs-stat-card">
        <span class="num" style="color:#155724"><?php echo number_format($stats['total_amount'], 2); ?></span>
        <span class="lbl">إجمالي المبيعات (ج)</span>
    </div>
    <div class="subs-stat-card">
        <span class="num" style="color:#721c24"><?php echo number_format($stats['total_refund'], 2); ?></span>
        <span class="lbl">إجمالي المُرجع (ج)</span>
    </div>
</div>

<!-- Filters -->
<form method="get" class="filter-bar">
    <div>
        <label>الخطة</label>
        <select name="planid">
            <option value="0">كل الخطط</option>
            <?php foreach ($plans as $p): ?>
                <option value="<?php echo $p->id; ?>" <?php echo ($filter_plan == $p->id) ? 'selected' : ''; ?>>
                    <?php echo s($p->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>الحالة</label>
        <select name="status">
            <option value="">كل الحالات</option>
            <option value="active"    <?php echo ($filter_status === 'active')    ? 'selected' : ''; ?>>فعال</option>
            <option value="expired"   <?php echo ($filter_status === 'expired')   ? 'selected' : ''; ?>>منتهي</option>
            <option value="cancelled" <?php echo ($filter_status === 'cancelled') ? 'selected' : ''; ?>>ملغى</option>
        </select>
    </div>
    <div>
        <label>المصدر</label>
        <select name="source">
            <option value="">الكل</option>
            <option value="online" <?php echo ($filter_source === 'online') ? 'selected' : ''; ?>>إلكتروني</option>
            <option value="manual" <?php echo ($filter_source === 'manual') ? 'selected' : ''; ?>>يدوي</option>
        </select>
    </div>
    <div>
        <label><?php echo get_string('refund_status', 'local_subscriptions'); ?></label>
        <select name="refund">
            <option value="">الكل</option>
            <option value="refunded"    <?php echo ($filter_refund === 'refunded')    ? 'selected' : ''; ?>><?php echo get_string('pay_status_refunded', 'local_subscriptions'); ?></option>
            <option value="notrefunded" <?php echo ($filter_refund === 'notrefunded') ? 'selected' : ''; ?>><?php echo get_string('refund_not_returned', 'local_subscriptions'); ?></option>
        </select>
    </div>
    <div>
        <label><?php echo get_string('report_from', 'local_subscriptions'); ?></label>
        <input type="date" name="from" value="<?php echo s($filter_from); ?>">
    </div>
    <div>
        <label><?php echo get_string('report_to', 'local_subscriptions'); ?></label>
        <input type="date" name="to" value="<?php echo s($filter_to); ?>">
    </div>
    <div>
        <button type="submit">تصفية</button>
    </div>
    <div>
        <?php
        $exporturl = new moodle_url('/local/subscriptions/admin/report.php', [
            'planid' => $filter_plan, 'status' => $filter_status, 'source' => $filter_source,
            'refund' => $filter_refund, 'from' => $filter_from, 'to' => $filter_to, 'export' => 'csv',
        ]);
        ?>
        <a href="<?php echo $exporturl->out(); ?>"
           style="display:inline-block;padding:6px 16px;background:#1a9c5b;color:#fff;border-radius:4px;text-decoration:none;font-size:.9em">
            ⬇ <?php echo get_string('export_csv', 'local_subscriptions'); ?>
        </a>
    </div>
</form>

<p style="color:#666; font-size:.9em">إجمالي النتائج: <strong><?php echo $total; ?></strong></p>

<?php if (empty($subscriptions)): ?>
    <p class="alert alert-info">لا توجد اشتراكات تطابق الفلتر.</p>
<?php else: ?>
<table class="subs-table">
    <thead>
        <tr>
            <th>#</th>
            <th>المستخدم</th>
            <th>الخطة</th>
            <th>المبلغ</th>
            <th>الحالة</th>
            <th>المصدر</th>
            <th><?php echo get_string('start_date', 'local_subscriptions'); ?></th>
            <th><?php echo get_string('expiry_date_field', 'local_subscriptions'); ?></th>
            <th>رقم الطلب</th>
            <th>الإجراءات</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($subscriptions as $sub): ?>
    <tr>
        <td><?php echo $sub->id; ?></td>
        <td>
            <strong><?php echo s($sub->firstname . ' ' . $sub->lastname); ?></strong>
            <br><small style="color:#888"><?php echo s($sub->email); ?></small>
        </td>
        <td><?php echo s($sub->plan_name); ?></td>
        <td><?php echo number_format((float)$sub->amount_paid, 2); ?> ج</td>
        <td>
            <?php if ($sub->status === 'active'): ?>
                <span class="badge-active">فعال</span>
            <?php elseif ($sub->status === 'expired'): ?>
                <span class="badge-expired">منتهي</span>
            <?php else: ?>
                <span class="badge-cancelled">ملغى</span>
            <?php endif; ?>
        </td>
        <td><?php echo $sub->source === 'online' ? 'إلكتروني' : 'يدوي'; ?></td>
        <td><?php echo $sub->start_time ? userdate($sub->start_time, '%d/%m/%Y') : '-'; ?></td>
        <td><?php echo $sub->expiry_time ? userdate($sub->expiry_time, '%d/%m/%Y') : '-'; ?></td>
        <td><small><?php echo s($sub->order_id ?: '-'); ?></small></td>
        <td style="white-space:nowrap">
            <a href="<?php echo (new moodle_url('/local/subscriptions/admin/report_detail.php', ['subid' => $sub->id]))->out(); ?>"
               style="display:inline-block;padding:4px 10px;background:#eef3f9;border:1px solid #cfe0f0;border-radius:4px;text-decoration:none;color:#2d6a9f;font-size:.82em">
                <?php echo get_string('details', 'local_subscriptions'); ?>
            </a>
            <?php if ($sub->status === manager::STATUS_ACTIVE && has_capability('local/subscriptions:manage', $context)): ?>
                <a class="btn-unsub"
                   href="<?php echo (new moodle_url('/local/subscriptions/admin/unsubscribe.php', ['subid' => $sub->id]))->out(); ?>">
                    <?php echo get_string('unsubscribe_user', 'local_subscriptions'); ?>
                </a>
            <?php elseif ($sub->status === 'cancelled'): ?>
                <small style="color:#888">
                    <?php echo $sub->refund_amount ? number_format((float)$sub->refund_amount, 2) . ' ج ' . get_string('refund_returned', 'local_subscriptions') : get_string('refund_not_returned', 'local_subscriptions'); ?>
                </small>
            <?php else: ?>
                &mdash;
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php
// Pagination.
$baseurl = new moodle_url('/local/subscriptions/admin/report.php', [
    'planid' => $filter_plan,
    'status' => $filter_status,
    'source' => $filter_source,
]);
echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
?>
<?php endif; ?>

<?php echo $OUTPUT->footer(); ?>
