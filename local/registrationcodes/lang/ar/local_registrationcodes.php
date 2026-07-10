<?php
defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'أكواد التسجيل';

// Signup form.
$string['regcode']              = 'كود التسجيل';
$string['regcode_help']         = 'أدخل كود التسجيل المقدَّم إليك. بدون كود صحيح لا يمكنك إنشاء حساب.';
$string['error_code_required']  = 'كود التسجيل مطلوب.';
$string['error_code_invalid']   = 'كود التسجيل غير صحيح.';
$string['error_code_used']      = 'تم استخدام هذا الكود مسبقًا.';
$string['error_code_disabled']  = 'هذا الكود معطَّل.';
$string['error_code_expired']   = 'انتهت صلاحية هذا الكود.';

// Admin navigation.
$string['manage_codes']   = 'إدارة الأكواد';
$string['reports']        = 'تقارير الاستخدام';
$string['pluginname_nav'] = 'أكواد التسجيل';

// Admin page.
$string['generate_codes']      = 'توليد الأكواد';
$string['quantity']            = 'الكمية';
$string['custom']              = 'مخصص…';
$string['custom_quantity']     = 'كمية مخصصة';
$string['prefix']              = 'بادئة';
$string['prefix_help']         = 'بادئة اختيارية تُضاف قبل كل كود، مثال "VIP" → VIP-XXXXXXXXXXXX.';
$string['expiry_date']         = 'تاريخ الانتهاء';
$string['notes']               = 'ملاحظات';
$string['generate']            = 'توليد';
$string['codes_generated']     = 'تم توليد {$a} كود/أكواد بنجاح.';
$string['error_invalid_quantity'] = 'يجب أن تكون الكمية بين 1 و 5000.';

// Code list.
$string['code']              = 'الكود';
$string['status']            = 'الحالة';
$string['created_by']        = 'أُنشئ بواسطة';
$string['timecreated']       = 'تاريخ الإنشاء';
$string['timeexpiry']        = 'تاريخ الانتهاء';
$string['used_by']           = 'استُخدم بواسطة';
$string['timeused']          = 'تاريخ الاستخدام';
$string['actions']           = 'الإجراءات';
$string['no_codes']          = 'لا توجد أكواد.';

// Status labels.
$string['status_unused']   = 'غير مستخدم';
$string['status_used']     = 'مستخدم';
$string['status_expired']  = 'منتهي الصلاحية';
$string['status_disabled'] = 'معطَّل';

// Actions.
$string['enable']         = 'تفعيل';
$string['disable']        = 'تعطيل';
$string['delete']         = 'حذف';
$string['confirm_delete'] = 'هل أنت متأكد من حذف الأكواد المحددة؟ لا يمكن التراجع عن هذا الإجراء.';
$string['bulk_enable']    = 'تفعيل المحدد';
$string['bulk_disable']   = 'تعطيل المحدد';
$string['bulk_delete']    = 'حذف المحدد';
$string['action_done']    = 'تم تنفيذ الإجراء بنجاح.';

// Groups.
$string['groupname']              = 'اسم المجموعة';
$string['groupname_help']         = 'تسمية لهذه الدفعة من الأكواد، مثال "1stSecBatch01". تشترك جميع أكواد الدفعة في نفس اسم المجموعة ويمكن تصفيتها أو حذفها معًا.';
$string['delete_group']           = 'حذف المجموعة';
$string['confirm_delete_group']   = 'هل تريد حذف جميع أكواد مجموعة "{$a}"؟ لا يمكن التراجع عن هذا الإجراء.';
$string['group_deleted']          = 'تم حذف المجموعة: تم إزالة {$a} كود/أكواد.';
$string['all_groups']             = 'جميع المجموعات';
$string['groups']                 = 'المجموعات';
$string['stats_groups']           = 'المجموعات';
$string['clear_group_filter']     = 'إلغاء تصفية المجموعة';

// Export.
$string['file_title']             = 'عنوان الملف';
$string['export_default_title']   = 'registration_codes';

// Search / filter.
$string['search']         = 'بحث';
$string['filter_status']  = 'تصفية حسب الحالة';
$string['filter_all']     = 'جميع الحالات';
$string['search_code']    = 'بحث في الكود أو الملاحظات';
$string['filter_creator'] = 'أُنشئ بواسطة';

// Stats dashboard.
$string['stats_total']    = 'الإجمالي';
$string['stats_unused']   = 'غير مستخدم';
$string['stats_used']     = 'مستخدم';
$string['stats_expired']  = 'منتهي الصلاحية';
$string['stats_disabled'] = 'معطَّل';
$string['stats_usage']    = 'نسبة الاستخدام';

// Report page.
$string['report_title']   = 'تقرير استخدام أكواد التسجيل';
$string['fullname']       = 'الاسم الكامل';
$string['email']          = 'البريد الإلكتروني';
$string['regdate']        = 'تاريخ التسجيل';
$string['export_csv']     = 'تصدير CSV';
$string['export_excel']   = 'تصدير Excel';
$string['no_records']     = 'لا توجد سجلات استخدام.';

// User profile.
$string['regcode_info']       = 'كود التسجيل';
$string['profile_code']       = 'الكود المستخدم';
$string['profile_regdate']    = 'تاريخ التسجيل';
$string['profile_code_by']    = 'مُنشئ الكود';
$string['profile_not_found']  = 'لا يوجد كود تسجيل مسجَّل.';

// Events.
$string['event_code_created'] = 'إنشاء كود تسجيل';
$string['event_code_used']    = 'استخدام كود تسجيل';
$string['event_code_deleted'] = 'حذف كود تسجيل';

// Capabilities.
$string['local/registrationcodes:generate']    = 'توليد أكواد التسجيل';
$string['local/registrationcodes:manage']      = 'إدارة أكواد التسجيل';
$string['local/registrationcodes:viewreports'] = 'عرض تقارير أكواد التسجيل';
$string['local/registrationcodes:delete']      = 'حذف أكواد التسجيل غير المستخدمة';
