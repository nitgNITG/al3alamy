<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_registrationcodes_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    // ── 2026071001: add groupname column ─────────────────────────────────────
    if ($oldversion < 2026071001) {
        $table = new xmldb_table('local_regcodes');

        $field = new xmldb_field('groupname', XMLDB_TYPE_CHAR, '100', null, false, null, null, 'prefix');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('idx_groupname', XMLDB_INDEX_NOTUNIQUE, ['groupname']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026071001, 'local', 'registrationcodes');
    }

    // ── 2026071002: create student profile fields ─────────────────────────────
    if ($oldversion < 2026071002) {
        local_registrationcodes_ensure_profile_fields($DB);
        upgrade_plugin_savepoint(true, 2026071002, 'local', 'registrationcodes');
    }

    return true;
}

/**
 * Create (or verify) the four student profile fields used on the signup form.
 * Safe to call multiple times — skips fields that already exist.
 *
 * @param \moodle_database $DB
 */
function local_registrationcodes_ensure_profile_fields($DB) {
    // ── Category ────────────────────────────────────────────────────────────
    $catname = 'بيانات الطالب';
    $catid   = $DB->get_field('user_info_category', 'id', ['name' => $catname]);
    if (!$catid) {
        $maxsort    = (int)($DB->get_field_sql('SELECT MAX(sortorder) FROM {user_info_category}') ?? 0);
        $cat        = new stdClass();
        $cat->name  = $catname;
        $cat->sortorder = $maxsort + 1;
        $catid      = $DB->insert_record('user_info_category', $cat);
    }

    // ── Governorate options (27 Egyptian governorates) ────────────────────
    $governorates = implode("\n", [
        'القاهرة', 'الإسكندرية', 'الجيزة', 'القليوبية', 'الشرقية',
        'الدقهلية', 'البحيرة', 'الغربية', 'المنوفية', 'كفر الشيخ',
        'دمياط', 'بورسعيد', 'الإسماعيلية', 'السويس', 'شمال سيناء',
        'جنوب سيناء', 'الفيوم', 'بني سويف', 'المنيا', 'أسيوط',
        'سوهاج', 'قنا', 'الأقصر', 'أسوان', 'البحر الأحمر',
        'الوادي الجديد', 'مطروح',
    ]);

    // ── Baccalaureate tracks ───────────────────────────────────────────────
    $tracks = implode("\n", [
        'مسار الطب وعلوم الحياة',
        'مسار الهندسة وعلوم الحاسب',
        'مسار الأعمال',
        'مسار الآداب والفنون',
    ]);

    // ── Field definitions ──────────────────────────────────────────────────
    // [shortname, display name, datatype, required, sortorder, param1, param2]
    $fields = [
        ['parentphone', 'رقم هاتف ولي الأمر', 'text',     1, 1, '15',          '20'],
        ['governorate', 'المحافظة',            'menu',     1, 2, $governorates,  null],
        ['track',       'المسار',              'menu',     1, 3, $tracks,         null],
        ['address',     'العنوان',             'textarea', 0, 4, '3',            '40'],
    ];

    foreach ($fields as [$shortname, $name, $datatype, $required, $sortorder, $param1, $param2]) {
        if ($DB->record_exists('user_info_field', ['shortname' => $shortname])) {
            continue; // already exists — skip.
        }

        $f                    = new stdClass();
        $f->shortname         = $shortname;
        $f->name              = $name;
        $f->datatype          = $datatype;
        $f->description       = '';
        $f->descriptionformat = FORMAT_HTML;
        $f->categoryid        = $catid;
        $f->sortorder         = $sortorder;
        $f->required          = $required;
        $f->locked            = 0;
        $f->visible           = 2;   // PROFILE_VISIBLE_ALL
        $f->forceunique       = 0;
        $f->signup            = 0;   // we control signup display ourselves
        $f->defaultdata       = '';
        $f->defaultdataformat = FORMAT_PLAIN;
        $f->param1            = $param1;
        $f->param2            = $param2;
        $f->param3            = null;
        $f->param4            = null;
        $f->param5            = null;

        $DB->insert_record('user_info_field', $f);
    }
}
