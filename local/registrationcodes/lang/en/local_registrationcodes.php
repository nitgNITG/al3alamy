<?php
defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'Registration Codes';

// Signup form — registration code.
$string['regcode']              = 'Registration Code';
$string['regcode_help']         = 'Enter the registration code provided to you. Without a valid code, you cannot create an account.';
$string['error_code_required']  = 'A registration code is required.';
$string['error_code_invalid']   = 'This registration code is not valid.';
$string['error_code_used']      = 'This registration code has already been used.';
$string['error_code_disabled']  = 'This registration code has been disabled.';
$string['error_code_expired']   = 'This registration code has expired.';

// Signup form — student profile fields.
$string['field_parentphone']             = 'Parent / Guardian Phone';
$string['field_parentphone_help']        = 'Enter the parent or guardian\'s Egyptian mobile number (e.g. 01012345678). Must be a valid Vodafone, Orange, Etisalat or WE number.';
$string['field_parentphone_placeholder'] = '01012345678';
$string['error_invalid_phone']           = 'Please enter a valid Egyptian mobile number (01[0/1/2/5]XXXXXXXX).';

$string['field_governorate']    = 'Governorate';
$string['field_governorate_help'] = 'Select the Egyptian governorate where the student resides.';

$string['field_track']          = 'Baccalaureate Track';
$string['field_track_help']     = 'Select the Baccalaureate track the student is enrolled in.';

$string['field_address']        = 'Address';
$string['field_address_help']   = 'Optional: enter the student\'s full address.';

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

// Groups.
$string['groupname']              = 'Group Name';
$string['groupname_help']         = 'A label for this batch of codes, e.g. "1stSecBatch01". All codes in a batch share the same group name and can be filtered or deleted together.';
$string['delete_group']           = 'Delete Group';
$string['confirm_delete_group']   = 'Delete ALL codes in group "{$a}"? This cannot be undone.';
$string['group_deleted']          = 'Group deleted: {$a} code(s) removed.';
$string['all_groups']             = 'All Groups';
$string['groups']                 = 'Groups';
$string['stats_groups']           = 'Groups';
$string['clear_group_filter']     = 'Clear Group Filter';

// Export.
$string['file_title']             = 'File Title';
$string['export_default_title']   = 'registration_codes';

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
