<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * resource2 module admin settings and defaults
 *
 * @package    mod_resource2
 * @copyright  2009 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    // ── Existing: delete from Vimeo on module delete ──────────────────────────
    $settings->add(new admin_setting_configcheckbox(
        'resource2/delete_from_vimeo',
        get_string('delete_from_vimeo', 'resource2'),
        get_string('delete_from_vimeo_desc', 'resource2'),
        0   // default: disabled (safe)
    ));

    // ── Video Quota — super admin only ────────────────────────────────────────
    if (is_siteadmin()) {

        $settings->add(new admin_setting_heading(
            'resource2/quota_heading',
            get_string('quota_heading', 'resource2'),
            get_string('quota_heading_desc', 'resource2')
        ));

        // Max number of videos allowed on the platform.
        $settings->add(new admin_setting_configtext(
            'resource2/max_video_count',
            get_string('max_video_count', 'resource2'),
            get_string('max_video_count_desc', 'resource2'),
            500,
            PARAM_INT
        ));

        // Max size of a single uploaded video (MB).
        $settings->add(new admin_setting_configtext(
            'resource2/max_video_size_mb',
            get_string('max_video_size_mb', 'resource2'),
            get_string('max_video_size_mb_desc', 'resource2'),
            500,
            PARAM_INT
        ));

        // Max total storage for all videos (GB).
        $settings->add(new admin_setting_configtext(
            'resource2/max_total_storage_gb',
            get_string('max_total_storage_gb', 'resource2'),
            get_string('max_total_storage_gb_desc', 'resource2'),
            50,
            PARAM_INT
        ));

        // ── Read-only usage counters ──────────────────────────────────────────
        $current_count    = (int)(get_config('resource2', 'video_count') ?: 0);
        $current_bytes    = (int)(get_config('resource2', 'total_storage_bytes') ?: 0);
        $current_gb       = round($current_bytes / (1024 * 1024 * 1024), 2);
        $max_count        = (int)(get_config('resource2', 'max_video_count') ?: 500);
        $max_gb           = (int)(get_config('resource2', 'max_total_storage_gb') ?: 50);

        $usage_html = html_writer::tag('ul',
            html_writer::tag('li',
                get_string('quota_usage_count', 'resource2',
                    (object)['current' => $current_count, 'max' => $max_count])) .
            html_writer::tag('li',
                get_string('quota_usage_storage', 'resource2',
                    (object)['current' => $current_gb, 'max' => $max_gb])),
            ['style' => 'margin:4px 0 0;padding-inline-start:20px;']
        );

        $settings->add(new admin_setting_heading(
            'resource2/quota_status_heading',
            get_string('quota_status_heading', 'resource2'),
            $usage_html
        ));
    }
}

// if ($ADMIN->fulltree) {
//     require_once("$CFG->libdir/resource2lib.php");

//     $displayoptions = resource2lib_get_displayoptions(array(resource2LIB_DISPLAY_AUTO,
//                                                            resource2LIB_DISPLAY_EMBED,
//                                                            resource2LIB_DISPLAY_FRAME,
//                                                            resource2LIB_DISPLAY_DOWNLOAD,
//                                                            resource2LIB_DISPLAY_OPEN,
//                                                            resource2LIB_DISPLAY_NEW,
//                                                            resource2LIB_DISPLAY_POPUP,
//                                                           ));
//     $defaultdisplayoptions = array(resource2LIB_DISPLAY_AUTO,
//                                    resource2LIB_DISPLAY_EMBED,
//                                    resource2LIB_DISPLAY_DOWNLOAD,
//                                    resource2LIB_DISPLAY_OPEN,
//                                    resource2LIB_DISPLAY_POPUP,
//                                   );

//     //--- general settings -----------------------------------------------------------------------------------
//     $settings->add(new admin_setting_configtext('resource2/framesize',
//         get_string('framesize', 'resource2'), get_string('configframesize', 'resource2'), 130, PARAM_INT));
//     $settings->add(new admin_setting_configmultiselect('resource2/displayoptions',
//         get_string('displayoptions', 'resource2'), get_string('configdisplayoptions', 'resource2'),
//         $defaultdisplayoptions, $displayoptions));

//     //--- modedit defaults -----------------------------------------------------------------------------------
//     $settings->add(new admin_setting_heading('resource2modeditdefaults', get_string('modeditdefaults', 'admin'), get_string('condifmodeditdefaults', 'admin')));

//     $settings->add(new admin_setting_configcheckbox('resource2/printintro',
//         get_string('printintro', 'resource2'), get_string('printintroexplain', 'resource2'), 1));
//     $settings->add(new admin_setting_configselect('resource2/display',
//         get_string('displayselect', 'resource2'), get_string('displayselectexplain', 'resource2'), resource2LIB_DISPLAY_AUTO,
//         $displayoptions));
//     $settings->add(new admin_setting_configcheckbox('resource2/showsize',
//         get_string('showsize', 'resource2'), get_string('showsize_desc', 'resource2'), 0));
//     $settings->add(new admin_setting_configcheckbox('resource2/showtype',
//         get_string('showtype', 'resource2'), get_string('showtype_desc', 'resource2'), 0));
//     $settings->add(new admin_setting_configcheckbox('resource2/showdate',
//         get_string('showdate', 'resource2'), get_string('showdate_desc', 'resource2'), 0));
//     $settings->add(new admin_setting_configtext('resource2/popupwidth',
//         get_string('popupwidth', 'resource2'), get_string('popupwidthexplain', 'resource2'), 620, PARAM_INT, 7));
//     $settings->add(new admin_setting_configtext('resource2/popupheight',
//         get_string('popupheight', 'resource2'), get_string('popupheightexplain', 'resource2'), 450, PARAM_INT, 7));
//     $options = array('0' => get_string('none'), '1' => get_string('allfiles'), '2' => get_string('htmlfilesonly'));
//     $settings->add(new admin_setting_configselect('resource2/filterfiles',
//         get_string('filterfiles', 'resource2'), get_string('filterfilesexplain', 'resource2'), 0, $options));
// }
