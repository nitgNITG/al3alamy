<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$string['pluginname']                  = 'الاشتراكات';
$string['pluginname_nav']              = 'الاشتراكات';
$string['manage_plans']                = 'إدارة خطط الاشتراك';
$string['create_plan']                 = 'إنشاء خطة';
$string['edit_plan']                   = 'تعديل الخطة';
$string['delete_plan']                 = 'حذف الخطة';
$string['deactivate_plan']             = 'تعطيل الخطة';
$string['plans_list']                  = 'خطط الاشتراك';
$string['plan_name']                   = 'اسم الخطة';
$string['plan_description']            = 'الوصف';
$string['plan_price']                  = 'السعر (جنيه)';
$string['plan_status']                 = 'الحالة';
$string['plan_status_active']          = 'نشطة';
$string['plan_status_inactive']        = 'معطلة';
$string['course_access_type']          = 'صلاحية الوصول للمقررات';
$string['course_access_all']           = 'جميع المقررات';
$string['course_access_specific']      = 'مقررات محددة';
$string['lesson_access_all']           = 'جميع الدروس';
$string['lesson_access_specific']      = 'دروس محددة';
$string['expiry_type']                 = 'نوع انتهاء الصلاحية';
$string['expiry_days_label']           = 'المدة (أيام)';
$string['expiry_date_label']           = 'تاريخ الانتهاء';
$string['expiry_type_days']            = 'مدة بالأيام';
$string['expiry_type_date']            = 'تاريخ محدد';
$string['my_subscriptions']            = 'اشتراكاتي';
$string['subscribe_now']               = 'اشترك الآن';
$string['buy_subscription']            = 'شراء الاشتراك';
$string['active_subscription']         = 'الاشتراك الفعال';
$string['no_subscriptions']            = 'لا توجد اشتراكات متاحة';
$string['no_active_subscription']      = 'ليس لديك اشتراك فعال';
$string['subscription_active_until']   = 'فعال حتى';
$string['already_subscribed']          = 'لديك اشتراك فعال بالفعل';
$string['payment_success']             = 'تم تفعيل الاشتراك بنجاح!';
$string['payment_failed']              = 'فشل الدفع. حاول مرة أخرى.';
$string['confirm_purchase']            = 'تأكيد الشراء';
$string['assign_subscription']         = 'تعيين اشتراك';
$string['unsubscribe_user']            = 'إلغاء اشتراك المستخدم';
$string['unsubscribe_reason']          = 'السبب';
$string['refund_returned']             = 'تم إرجاع المبلغ';
$string['refund_not_returned']         = 'لم يتم إرجاع المبلغ';
$string['refund_amount']               = 'المبلغ المُرجع';
$string['confirm_delete']              = 'هل أنت متأكد من حذف هذه الخطة؟';
$string['confirm_deactivate']          = 'هل أنت متأكد من تعطيل هذه الخطة؟';
$string['cannot_delete_has_subscribers'] = 'لا يمكن الحذف: هذه الخطة لها مشتركون. قم بتعطيلها بدلاً من ذلك.';
$string['no_plans']                    = 'لا توجد خطط اشتراك.';
$string['save_plan']                   = 'حفظ الخطة';
$string['courses_section']             = 'المقررات المتضمنة';
$string['select_courses']              = 'اختر المقررات';
$string['expiry_section']              = 'إعدادات انتهاء الصلاحية';
$string['subscribers_count']           = 'المشتركون';
$string['reports']                     = 'تقارير الاشتراكات';
$string['back_to_plans']               = 'العودة للخطط';
$string['start_date']                  = 'تاريخ البدء';
$string['expiry_date_field']           = 'تاريخ الانتهاء';
$string['amount_paid']                 = 'المبلغ المدفوع';
$string['source_online']               = 'شراء إلكتروني';
$string['source_manual']               = 'تعيين يدوي';
$string['status_active']               = 'فعال';
$string['status_expired']              = 'منتهي';
$string['status_cancelled']            = 'ملغى';

// فتح الدروس بالرصيد (الاشتراك).
$string['unlock_limit']                    = 'عدد الدروس المسموح للطالب بفتحها';
$string['unlock_limit_help']               = 'عدد الدروس التي يمكن للطالب فتحها من الدروس المحددة (0 = غير محدود / كل الدروس). الدروس المفتوحة تظل متاحة للطالب للأبد حتى بعد انتهاء الاشتراك.';
$string['unlock_with_subscription']        = 'افتح بالاشتراك';
$string['unlocks_remaining']               = 'المتبقي';
$string['no_credits_left']                 = 'لا يوجد رصيد للفتح';
$string['unlocking']                       = 'جارٍ الفتح…';
$string['unlock_success']                  = 'تم فتح الدرس بنجاح.';
$string['already_unlocked']                = 'هذا الدرس مفتوح بالفعل.';
$string['unlock_no_active_subscription']   = 'ليس لديك اشتراك فعّال.';
$string['unlock_not_credit_plan']          = 'خطة اشتراكك لا تستخدم نظام فتح الدروس.';
$string['unlock_limit_reached']            = 'لقد استخدمت كل رصيد الفتح المتاح.';
$string['unlock_not_in_plan']              = 'هذا الدرس ليس ضمن خطة اشتراكك.';
$string['unlocked_lessons']                = 'الدروس المفتوحة';
$string['no_unlocks_yet']                  = 'لم تفتح أي درس بعد.';

// التعيين اليدوي (US-AD-2-1).
$string['assign_intro']                = 'امنح اشتراكًا لمستخدم دفع خارج المنصة. يبدأ الاشتراك فورًا.';
$string['assign_user']                 = 'المستخدم';
$string['assign_user_help']            = 'أدخل اسم المستخدم أو البريد الإلكتروني بالضبط.';
$string['select_plan']                 = 'خطة الاشتراك';
$string['assign_amount']               = 'المبلغ المُستلم';
$string['assign_amount_placeholder']   = 'سعر الخطة';
$string['assign_amount_help']          = 'اتركه فارغًا لاستخدام سعر الخطة. يُسجَّل كدفع يدوي (أوفلاين).';
$string['assign_note']                 = 'ملاحظة (اختياري)';
$string['assign_review_btn']           = 'مراجعة';
$string['assign_review']               = 'تأكيد تعيين الاشتراك';
$string['assign_confirm']              = 'تأكيد التعيين';
$string['assign_success']              = 'تم تعيين الاشتراك بنجاح.';
$string['assign_no_such_user']         = 'لا يوجد مستخدم بهذا الاسم أو البريد الإلكتروني.';
$string['assign_user_has_active']      = 'هذا المستخدم لديه اشتراك فعّال بالفعل. مسموح باشتراك واحد فقط في المرة.';
$string['assign_invalid_plan']         = 'الخطة المختارة غير صالحة.';
$string['assign_amount_invalid']       = 'المبلغ يجب أن يكون 0 أو أكثر.';

// إلغاء الاشتراك اليدوي (US-AD-2-2).
$string['refund_status']               = 'حالة الاسترجاع';
$string['unsub_for']                   = 'إلغاء اشتراك المستخدم:';
$string['unsub_max']                   = 'بحد أقصى';
$string['unsub_confirm']               = 'تأكيد الإلغاء';
$string['unsub_success']               = 'تم إلغاء اشتراك المستخدم بنجاح وسحب الوصول.';
$string['unsub_reason_required']       = 'سبب الإلغاء مطلوب.';
$string['unsub_refund_invalid']        = 'المبلغ المُرجع لا يمكن أن يتجاوز المبلغ المدفوع أصلًا.';
$string['unsub_not_active']            = 'يمكن إلغاء الاشتراكات الفعّالة فقط.';

// صفحة تفاصيل الخطة (US-SB-1-1).
$string['plan_unavailable']            = 'هذه الخطة غير متاحة.';
$string['plan_detail_credit']          = 'مع هذه الخطة يمكنك فتح حتى {$a} درسًا من اختيارك.';
$string['plan_detail_lessons']         = 'درس';

// تفاصيل سجل الدفع (US-SB-1-3).
$string['payment_method']              = 'طريقة الدفع';
$string['payment_status']              = 'حالة الدفع';
$string['pay_method_online']           = 'إلكتروني';
$string['pay_method_offline']          = 'أوفلاين';
$string['pay_status_paid']             = 'مدفوع';
$string['pay_status_refunded']         = 'مسترجع';
$string['pay_status_cancelled']        = 'ملغى';
$string['details']                     = 'تفاصيل';
$string['order_id_label']              = 'رقم الطلب';
$string['transaction_id_label']        = 'رقم المعاملة';
$string['at_purchase']                 = 'وقت الشراء';

// استكمال التقرير (US-AD-2-3).
$string['report_from']                 = 'من تاريخ';
$string['report_to']                   = 'إلى تاريخ';
$string['export_csv']                  = 'تصدير CSV';
$string['report_detail_title']         = 'تفاصيل الاشتراك';
$string['report_subscriber']           = 'المشترك';
$string['report_assigned_by']          = 'عُيّن بواسطة';
$string['report_cancelled_by']         = 'أُلغي بواسطة';
$string['report_snapshot']             = 'البيانات وقت الشراء (لقطة)';
$string['report_current']              = 'بيانات الخطة الحالية';
$string['report_no_snapshot']          = 'لا توجد لقطة محفوظة.';
$string['report_plan_deleted']         = 'تم حذف الخطة.';
$string['report_usage']                = 'الاستخدام';
$string['report_change_history']       = 'سجل تغييرات الخطة';
$string['report_change_when']          = 'التاريخ';
$string['report_change_type']          = 'التغيير';
$string['report_change_field']         = 'الحقل';
$string['report_change_old']           = 'القديم';
$string['report_change_new']           = 'الجديد';
$string['report_no_changes']           = 'لا توجد تغييرات مسجلة لهذه الخطة.';
