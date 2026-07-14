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
$string['devicelimitreached'] = 'أنت مسجّل الدخول حالياً على جهاز آخر. يُرجى تسجيل الخروج من الجهاز الآخر أولاً قبل تسجيل الدخول من هنا.';

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

// Admin: force logout tool.
$string['forcelogout_title']               = 'تسجيل خروج المستخدمين قسريًا';
$string['forcelogout_intro']               = 'ابحث عن مستخدم لعرض جلساته الحالية وتسجيل خروجه من جهاز آخر إذا كانت جلسة قديمة تمنعه من تسجيل الدخول.';
$string['forcelogout_search_placeholder']  = 'اسم المستخدم أو البريد الإلكتروني أو الاسم';
$string['forcelogout_search_btn']          = 'بحث';
$string['forcelogout_nomatch']             = 'لا يوجد مستخدمون مطابقون.';
$string['forcelogout_nosessions']          = 'لا توجد جلسات نشطة لهذا المستخدم.';
$string['forcelogout_col_started']         = 'بدأت';
$string['forcelogout_col_lastactive']      = 'آخر نشاط';
$string['forcelogout_col_ip']              = 'عنوان IP';
$string['forcelogout_action']              = 'تسجيل خروج';
$string['forcelogout_action_all']          = 'تسجيل الخروج من جميع الجلسات';
$string['forcelogout_confirm_one']         = 'هل تريد تسجيل خروج هذه الجلسة؟';
$string['forcelogout_confirm_all']         = 'هل تريد تسجيل خروج هذا المستخدم من جميع أجهزته؟';
$string['forcelogout_session_done']        = 'تم تسجيل خروج الجلسة بنجاح.';
$string['forcelogout_all_done']            = 'تم تسجيل خروج المستخدم من جميع الجلسات بنجاح.';

// Privacy.
$string['privacy:metadata'] = 'إضافة تسجيل الأجهزة تخزّن إعدادات الموقع فقط ولا تخزّن أي بيانات شخصية.';
