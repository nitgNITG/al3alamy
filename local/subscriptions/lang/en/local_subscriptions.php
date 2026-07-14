<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$string['pluginname']                  = 'Subscriptions';
$string['pluginname_nav']              = 'Subscriptions';
$string['manage_plans']                = 'Manage Subscription Plans';
$string['create_plan']                 = 'Create Plan';
$string['edit_plan']                   = 'Edit Plan';
$string['delete_plan']                 = 'Delete Plan';
$string['deactivate_plan']             = 'Deactivate Plan';
$string['plans_list']                  = 'Subscription Plans';
$string['plan_name']                   = 'Plan Name';
$string['plan_description']            = 'Description';
$string['plan_price']                  = 'Price (EGP)';
$string['plan_status']                 = 'Status';
$string['plan_status_active']          = 'Active';
$string['plan_status_inactive']        = 'Inactive';
$string['course_access_type']          = 'Course Access';
$string['course_access_all']           = 'All Courses';
$string['course_access_specific']      = 'Specific Courses';
$string['lesson_access_all']           = 'All Lessons';
$string['lesson_access_specific']      = 'Specific Lessons';
$string['expiry_type']                 = 'Expiry Type';
$string['expiry_days_label']           = 'Duration (Days)';
$string['expiry_date_label']           = 'Expiry Date';
$string['expiry_type_days']            = 'Duration in Days';
$string['expiry_type_date']            = 'Specific Date';
$string['my_subscriptions']            = 'My Subscriptions';
$string['subscribe_now']               = 'Subscribe Now';
$string['buy_subscription']            = 'Purchase Subscription';
$string['active_subscription']         = 'Active Subscription';
$string['no_subscriptions']            = 'No subscriptions available';
$string['no_active_subscription']      = 'You have no active subscription';
$string['subscription_active_until']   = 'Active until';
$string['already_subscribed']          = 'You already have an active subscription';
$string['current_plan']                = 'Your current plan';
$string['current_plan_badge']          = 'Current plan';
$string['payment_success']             = 'Subscription activated successfully!';
$string['payment_failed']              = 'Payment failed. Please try again.';
$string['confirm_purchase']            = 'Confirm Purchase';
$string['assign_subscription']         = 'Assign Subscription';
$string['unsubscribe_user']            = 'Unsubscribe User';
$string['unsubscribe_reason']          = 'Reason';
$string['refund_returned']             = 'Amount Returned';
$string['refund_not_returned']         = 'Not Returned';
$string['refund_amount']               = 'Returned Amount';
$string['confirm_delete']              = 'Are you sure you want to delete this plan?';
$string['confirm_deactivate']          = 'Are you sure you want to deactivate this plan?';
$string['cannot_delete_has_subscribers'] = 'Cannot delete: this plan has active subscribers. Deactivate it instead.';
$string['no_plans']                    = 'No subscription plans found.';
$string['save_plan']                   = 'Save Plan';
$string['courses_section']             = 'Included Courses';
$string['select_courses']              = 'Select Courses';
$string['expiry_section']              = 'Expiry Settings';
$string['subscribers_count']           = 'Subscribers';
$string['reports']                     = 'Subscription Reports';
$string['back_to_plans']               = 'Back to Plans';
$string['start_date']                  = 'Start Date';
$string['expiry_date_field']           = 'Expiry Date';
$string['amount_paid']                 = 'Amount Paid';
$string['source_online']               = 'Online Purchase';
$string['source_manual']               = 'Manual Assignment';
$string['status_active']               = 'Active';
$string['status_expired']              = 'Expired';
$string['status_cancelled']            = 'Cancelled';

// Credit-based lesson unlocking.
$string['unlock_limit']                    = 'Lessons a subscriber can unlock';
$string['unlock_limit_help']               = 'Number of lessons the student may unlock from the selected lessons (0 = unlimited / all lessons). Unlocked lessons stay accessible forever, even after the subscription expires.';
$string['unlock_with_subscription']        = 'Unlock with subscription';
$string['unlocks_remaining']               = 'remaining';
$string['no_credits_left']                 = 'No unlocks left';
$string['unlocking']                       = 'Unlocking…';
$string['unlock_success']                  = 'Lesson unlocked successfully.';
$string['already_unlocked']                = 'This lesson is already unlocked.';
$string['unlock_no_active_subscription']   = 'You have no active subscription.';
$string['unlock_not_credit_plan']          = 'Your subscription plan does not use lesson unlocking.';
$string['unlock_limit_reached']            = 'You have used all your unlocks.';
$string['unlock_not_in_plan']              = 'This lesson is not part of your subscription plan.';
$string['unlocked_lessons']                = 'Unlocked lessons';
$string['no_unlocks_yet']                  = 'You have not unlocked any lesson yet.';

// Manual assignment (US-AD-2-1).
$string['assign_intro']                = 'Manually grant a subscription to a user who paid offline. The subscription starts immediately.';
$string['assign_user']                 = 'User';
$string['assign_user_help']            = 'Enter the exact username or email of the user.';
$string['select_plan']                 = 'Subscription plan';
$string['assign_amount']               = 'Amount received';
$string['assign_amount_placeholder']   = 'Plan price';
$string['assign_amount_help']          = 'Leave blank to use the plan price. Recorded as an offline payment.';
$string['assign_note']                 = 'Note (optional)';
$string['assign_review_btn']           = 'Review';
$string['assign_review']               = 'Confirm subscription assignment';
$string['assign_confirm']              = 'Confirm assignment';
$string['assign_success']              = 'Subscription assigned successfully.';
$string['assign_no_such_user']         = 'No user found with that username or email.';
$string['assign_user_has_active']      = 'This user already has an active subscription. Only one is allowed at a time.';
$string['assign_invalid_plan']         = 'The selected plan is not valid.';
$string['assign_amount_invalid']       = 'The amount must be 0 or greater.';

// Manual unsubscribe (US-AD-2-2).
$string['refund_status']               = 'Refund status';
$string['unsub_for']                   = 'Unsubscribe user:';
$string['unsub_max']                   = 'max';
$string['unsub_confirm']               = 'Confirm unsubscribe';
$string['unsub_success']               = 'User unsubscribed successfully. Access revoked.';
$string['unsub_reason_required']       = 'An unsubscribe reason is required.';
$string['unsub_refund_invalid']        = 'The returned amount cannot exceed the amount originally paid.';
$string['unsub_not_active']            = 'Only active subscriptions can be unsubscribed.';

// Plan detail view (US-SB-1-1).
$string['plan_unavailable']            = 'This plan is not available.';
$string['plan_detail_credit']          = 'With this plan you can unlock up to {$a} lessons of your choice.';
$string['plan_detail_lessons']         = 'lessons';

// Payment history detail (US-SB-1-3).
$string['payment_method']              = 'Payment method';
$string['payment_status']              = 'Payment status';
$string['pay_method_online']           = 'Online';
$string['pay_method_offline']          = 'Offline';
$string['pay_status_paid']             = 'Paid';
$string['pay_status_refunded']         = 'Refunded';
$string['pay_status_cancelled']        = 'Cancelled';
$string['details']                     = 'Details';
$string['order_id_label']              = 'Order ID';
$string['transaction_id_label']        = 'Transaction ID';
$string['at_purchase']                 = 'at purchase';

// Report completion (US-AD-2-3).
$string['report_from']                 = 'From date';
$string['report_to']                   = 'To date';
$string['export_csv']                  = 'Export CSV';
$string['report_detail_title']         = 'Subscription details';
$string['report_subscriber']           = 'Subscriber';
$string['report_assigned_by']          = 'Assigned by';
$string['report_cancelled_by']         = 'Unsubscribed by';
$string['report_snapshot']             = 'Data at purchase (snapshot)';
$string['report_current']              = 'Current plan data';
$string['report_no_snapshot']          = 'No snapshot stored.';
$string['report_plan_deleted']         = 'The plan has been deleted.';
$string['report_usage']                = 'Usage';
$string['report_change_history']       = 'Plan change history';
$string['report_change_when']          = 'When';
$string['report_change_type']          = 'Change';
$string['report_change_field']         = 'Field';
$string['report_change_old']           = 'Old';
$string['report_change_new']           = 'New';
$string['report_no_changes']           = 'No changes recorded for this plan.';
