<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Arabic strings for local_deviceregistration.
 *
 * @package    local_deviceregistration
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'تسجيل الأجهزة';

// Settings — enable/disable.
$string['enabled']      = 'تفعيل التحكم في تسجيل الأجهزة';
$string['enabled_desc'] = 'عند التفعيل، لا يمكن للمستخدم تسجيل الدخول إلا من عدد محدود من الأجهزة المسجَّلة. وعند الإيقاف، يمكن للمستخدمين تسجيل عدد غير محدود من الأجهزة.';

// Settings — maximum devices.
$string['maxdevices']      = 'الحد الأقصى للأجهزة المسجَّلة لكل مستخدم';
$string['maxdevices_desc'] = 'الحد الأقصى لعدد الأجهزة التي يُسمح لكل مستخدم بتسجيلها. يجب أن يكون أكبر من صفر. يُطبَّق هذا الحد عند محاولة المستخدم تسجيل جهاز جديد. تغيير الحد لا يؤدي إلى إزالة الأجهزة المسجَّلة مسبقًا.';

// Validation.
$string['error_maxdevices'] = 'يجب أن يكون الحد الأقصى لعدد الأجهزة أكبر من صفر.';

// Management page.
$string['page_intro']           = 'تحكَّم في عدد الأجهزة التي يمكن لكل مستخدم استخدامها لتسجيل الدخول إلى المنصّة.';
$string['settings_heading']     = 'إعدادات تسجيل الأجهزة';
$string['settings_saved']       = 'تم حفظ إعدادات تسجيل الأجهزة.';
$string['savechanges']          = 'حفظ التغييرات';
$string['label_feature_status'] = 'حالة الميزة';
$string['label_current_limit']  = 'عدد الأجهزة لكل مستخدم';
$string['status_enabled']       = 'مُفعَّلة';
$string['status_disabled']      = 'متوقّفة';
$string['unlimited']            = 'غير محدود';

// Enforcement.
$string['devicelimitreached'] = 'لقد وصلت إلى الحد الأقصى لعدد الأجهزة المسجَّلة المسموح بها لحسابك. تواصل مع المسؤول لإزالة أحد أجهزتك المسجَّلة حتى تتمكن من تسجيل الدخول من هذا الجهاز.';

// My devices page.
$string['mydevices']          = 'أجهزتي';
$string['mydevices_intro']    = 'هذه هي الأجهزة التي استخدمتها لتسجيل الدخول. أزِل جهازًا لتوفير مكان إذا بلغت الحد الأقصى.';
$string['devices_registered'] = 'الأجهزة المسجَّلة';
$string['devices_allowed']    = 'الأجهزة المسموح بها';
$string['nodevices']          = 'لا توجد لديك أجهزة مسجَّلة بعد.';
$string['device']             = 'الجهاز';
$string['lastip']             = 'آخر عنوان IP';
$string['firstseen']          = 'أول تسجيل';
$string['lastseen']           = 'آخر استخدام';
$string['actions']            = 'إجراءات';
$string['remove']             = 'إزالة';
$string['confirm_remove']     = 'هل تريد إزالة هذا الجهاز؟ سيلزم تسجيله من جديد عند تسجيل الدخول منه في المرة القادمة.';
$string['device_removed']     = 'تمت إزالة الجهاز.';
$string['thisdevice']         = 'هذا الجهاز';
$string['unknowndevice']      = 'جهاز غير معروف';

// Admin page — tabs.
$string['tab_sessions'] = 'الجلسات النشطة';
$string['tab_devices']  = 'الأجهزة المسجَّلة';

// Admin: device management tab.
$string['devmgr_intro']          = 'اعرض الأجهزة المسجَّلة لأي مستخدم وأزِل ما تشاء منها. إزالة الجهاز تُحرِّر مكانًا — سيتمكن المستخدم من تسجيل جهاز جديد في تسجيل الدخول التالي. تسجيل الخروج لا يُحرِّر مكانًا.';
$string['devmgr_devices_for']    = 'الأجهزة المسجَّلة لـ: {$a}';
$string['devmgr_col_devices']    = 'الأجهزة';
$string['devmgr_col_limit']      = 'الحد الأقصى';
$string['devmgr_count']          = 'مستخدمون لديهم أجهزة مسجَّلة: {$a}';
$string['devmgr_none']           = 'لا توجد أجهزة مسجَّلة.';
$string['devmgr_manage']         = 'إدارة الأجهزة';
$string['devmgr_revoke']         = 'إلغاء';
$string['devmgr_confirm_revoke'] = 'هل تريد إلغاء هذا الجهاز نهائيًا؟ سيحتاج المستخدم إلى تسجيله مجددًا عند تسجيل الدخول التالي.';
$string['devmgr_back']           = 'العودة إلى قائمة المستخدمين';
$string['device_revoked']        = 'تم إلغاء الجهاز للمستخدم {$a->name}.';

// Admin: force logout tool.
$string['forcelogout_title']               = 'تسجيل خروج المستخدمين';
$string['forcelogout_intro']               = 'هؤلاء هم المستخدمون المسجّلون دخول حالياً. اضغط "تسجيل خروج" لإنهاء كل جلسات المستخدم فوراً — مفيد لو جلسة قديمة عالقة بتمنعه من تسجيل الدخول.';
$string['forcelogout_filter_placeholder']  = 'تصفية بالاسم أو البريد أو اسم المستخدم…';
$string['forcelogout_filter_btn']          = 'تصفية';
$string['forcelogout_clear']               = 'إظهار الكل';
$string['forcelogout_none_loggedin']       = 'لا يوجد مستخدمون مسجّلون دخول حالياً.';
$string['forcelogout_nomatch']             = 'لا يوجد مستخدمون مطابقون للتصفية.';
$string['forcelogout_count']               = 'عدد المستخدمين المسجّلين حالياً: {$a}';
$string['forcelogout_col_user']            = 'المستخدم';
$string['forcelogout_col_sessions']        = 'الجلسات النشطة';
$string['forcelogout_col_lastactive']      = 'آخر نشاط';
$string['forcelogout_action']              = 'تسجيل خروج';
$string['forcelogout_confirm_all']         = 'هل تريد تسجيل خروج هذا المستخدم من جميع أجهزته؟';
$string['forcelogout_confirm_title']       = 'تأكيد الإجراء';
$string['forcelogout_confirm_yes']         = 'نعم، سجّل الخروج';
$string['forcelogout_done']                = 'تم تسجيل خروج {$a->name} — إنهاء {$a->count} جلسة.';

// Privacy.
$string['privacy:metadata'] = 'إضافة تسجيل الأجهزة تخزّن إعدادات الموقع فقط ولا تخزّن أي بيانات شخصية.';
