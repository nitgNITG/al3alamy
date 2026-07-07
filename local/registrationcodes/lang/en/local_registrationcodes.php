<?php
defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'Registration Codes';

// Signup form.
$string['regcode']              = 'Registration Code';
$string['regcode_help']         = 'Enter the registration code provided to you. Without a valid code, you cannot create an account.';
$string['error_code_required']  = 'A registration code is required.';
$string['error_code_invalid']   = 'This registration code is not valid.';
$string['error_code_used']      = 'This registration code has already been used.';
$string['error_code_disabled']  = 'This registration code has been disabled.';
$string['error_code_expired']   = 'This registration code has expired.';

// Admin navigation.
$string['manage_codes']   = 'Manage Codes';
$string['reports']        = 'Usage Reports';
$string['pluginname_nav'] = 'Registration Codes';

// Admin page.
$string['generate_codes']      = 'Generate Codes';
$string['quantity']            = 'Quantity';
$string['custom']              = 'Custom…';
$string['custom_quantity']     = 'Custom quantity';
$string['prefix']              = 'Prefix';
$string['prefix_help']         = 'Optional prefix added before each code, e.g. "VIP" → VIP-XXXXXXXXXXXX.';
$string['expiry_date']         = 'Expiry Date';
$string['notes']               = 'Notes';
$string['generate']            = 'Generate';
$string['codes_generated']     = '{$a} code(s) generated successfully.';
$string['error_invalid_quantity'] = 'Quantity must be between 1 and 5000.';

// Code list.
$string['code']              = 'Code';
$string['status']            = 'Status';
$string['created_by']        = 'Created By';
$string['timecreated']       = 'Created';
$string['timeexpiry']        = 'Expires';
$string['used_by']           = 'Used By';
$string['timeused']          = 'Used On';
$string['actions']           = 'Actions';
$string['no_codes']          = 'No codes found.';

// Status labels.
$string['status_unused']   = 'Unused';
$string['status_used']     = 'Used';
$string['status_expired']  = 'Expired';
$string['status_disabled'] = 'Disabled';

// Actions.
$string['enable']        = 'Enable';
$string['disable']       = 'Disable';
$string['delete']        = 'Delete';
$string['confirm_delete'] = 'Are you sure you want to delete the selected code(s)? This cannot be undone.';
$string['bulk_enable']   = 'Enable selected';
$string['bulk_disable']  = 'Disable selected';
$string['bulk_delete']   = 'Delete selected';
$string['action_done']   = 'Action completed successfully.';

// Search / filter.
$string['search']         = 'Search';
$string['filter_status']  = 'Filter by status';
$string['filter_all']     = 'All statuses';
$string['search_code']    = 'Search code or notes';
$string['filter_creator'] = 'Created by';

// Stats dashboard.
$string['stats_total']    = 'Total';
$string['stats_unused']   = 'Unused';
$string['stats_used']     = 'Used';
$string['stats_expired']  = 'Expired';
$string['stats_disabled'] = 'Disabled';
$string['stats_usage']    = 'Usage';

// Report page.
$string['report_title']   = 'Registration Code Usage Report';
$string['fullname']       = 'Full Name';
$string['email']          = 'Email';
$string['regdate']        = 'Registration Date';
$string['export_csv']     = 'Export CSV';
$string['export_excel']   = 'Export Excel';
$string['no_records']     = 'No usage records found.';

// User profile.
$string['regcode_info']       = 'Registration Code';
$string['profile_code']       = 'Code Used';
$string['profile_regdate']    = 'Registered On';
$string['profile_code_by']    = 'Code Created By';
$string['profile_not_found']  = 'No registration code on record.';

// Events.
$string['event_code_created'] = 'Registration code created';
$string['event_code_used']    = 'Registration code used';
$string['event_code_deleted'] = 'Registration code deleted';

// Capabilities.
$string['local/registrationcodes:generate']    = 'Generate registration codes';
$string['local/registrationcodes:manage']      = 'Manage registration codes';
$string['local/registrationcodes:viewreports'] = 'View registration code reports';
$string['local/registrationcodes:delete']      = 'Delete unused registration codes';
