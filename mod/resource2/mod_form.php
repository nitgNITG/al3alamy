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
 * resource2 configuration form
 *
 * @package    mod_resource2
 * @copyright  2009 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/resource2/locallib.php');
require_once($CFG->libdir.'/filelib.php');

class mod_resource2_mod_form extends moodleform_mod {
    function definition() {
        global $CFG, $DB, $USER;
        $mform =& $this->_form;

        $config = get_config('resource2');

        if ($this->current->instance and $this->current->tobemigrated) {
            // resource2 not migrated yet
            $resource2_old = $DB->get_record('resource2_old', array('oldid'=>$this->current->instance));
            $mform->addElement('static', 'warning', '', get_string('notmigrated', 'resource2', $resource2_old->type));
            $mform->addElement('cancel');
            $this->standard_hidden_coursemodule_elements();
            return;
        }

        //-------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'48'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();
        $element = $mform->getElement('introeditor');
        $attributes = $element->getAttributes();
        $attributes['rows'] = 5;
        $element->setAttributes($attributes);
        $filemanager_options = array();
        $filemanager_options['accepted_types'] = '*';
        $filemanager_options['maxbytes'] = 0;
        $filemanager_options['maxfiles'] = -1;
        $filemanager_options['mainfile'] = true;

        // $mform->addElement('filemanager', 'files', get_string('selectfiles'), null, $filemanager_options);

        // add legacy files flag only if used
        if (isset($this->current->legacyfiles) and $this->current->legacyfiles != resource2LIB_LEGACYFILES_NO) {
            $options = array(resource2LIB_LEGACYFILES_DONE   => get_string('legacyfilesdone', 'resource2'),
                             resource2LIB_LEGACYFILES_ACTIVE => get_string('legacyfilesactive', 'resource2'));
            $mform->addElement('select', 'legacyfiles', get_string('legacyfiles', 'resource2'), $options);
        }

        //-------------------------------------------------------
        $mform->addElement('header', 'optionssection', get_string('appearance'));

        if ($this->current->instance) {
            $options = resource2lib_get_displayoptions(explode(',', $config->displayoptions), $this->current->display);
        } else {
            $options = resource2lib_get_displayoptions(explode(',', $config->displayoptions));
        }

        if (count($options) == 1) {
            $mform->addElement('hidden', 'display');
            $mform->setType('display', PARAM_INT);
            reset($options);
            $mform->setDefault('display', key($options));
        } else {
            $mform->addElement('select', 'display', get_string('displayselect', 'resource2'), $options);
            $mform->setDefault('display', $config->display);
            $mform->addHelpButton('display', 'displayselect', 'resource2');
        }

        $mform->addElement('checkbox', 'showsize', get_string('showsize', 'resource2'));
        $mform->setDefault('showsize', $config->showsize);
        $mform->addHelpButton('showsize', 'showsize', 'resource2');
        $mform->addElement('checkbox', 'showtype', get_string('showtype', 'resource2'));
        $mform->setDefault('showtype', $config->showtype);
        $mform->addHelpButton('showtype', 'showtype', 'resource2');
        $mform->addElement('checkbox', 'showdate', get_string('showdate', 'resource2'));
        $mform->setDefault('showdate', $config->showdate);
        $mform->addHelpButton('showdate', 'showdate', 'resource2');

        if (array_key_exists(resource2LIB_DISPLAY_POPUP, $options)) {
            $mform->addElement('text', 'popupwidth', get_string('popupwidth', 'resource2'), array('size'=>3));
            if (count($options) > 1) {
                $mform->hideIf('popupwidth', 'display', 'noteq', resource2LIB_DISPLAY_POPUP);
            }
            $mform->setType('popupwidth', PARAM_INT);
            $mform->setDefault('popupwidth', $config->popupwidth);
            $mform->setAdvanced('popupwidth', true);

            $mform->addElement('text', 'popupheight', get_string('popupheight', 'resource2'), array('size'=>3));
            if (count($options) > 1) {
                $mform->hideIf('popupheight', 'display', 'noteq', resource2LIB_DISPLAY_POPUP);
            }
            $mform->setType('popupheight', PARAM_INT);
            $mform->setDefault('popupheight', $config->popupheight);
            $mform->setAdvanced('popupheight', true);
        }

        if (array_key_exists(resource2LIB_DISPLAY_AUTO, $options) or
          array_key_exists(resource2LIB_DISPLAY_EMBED, $options) or
          array_key_exists(resource2LIB_DISPLAY_FRAME, $options)) {
            $mform->addElement('checkbox', 'printintro', get_string('printintro', 'resource2'));
            $mform->hideIf('printintro', 'display', 'eq', resource2LIB_DISPLAY_POPUP);
            $mform->hideIf('printintro', 'display', 'eq', resource2LIB_DISPLAY_DOWNLOAD);
            $mform->hideIf('printintro', 'display', 'eq', resource2LIB_DISPLAY_OPEN);
            $mform->hideIf('printintro', 'display', 'eq', resource2LIB_DISPLAY_NEW);
            $mform->setDefault('printintro', $config->printintro);
        }

        $options = array('0' => get_string('none'), '1' => get_string('allfiles'), '2' => get_string('htmlfilesonly'));
        $mform->addElement('select', 'filterfiles', get_string('filterfiles', 'resource2'), $options);
        $mform->setDefault('filterfiles', $config->filterfiles);
        $mform->setAdvanced('filterfiles', true);

        // ── Video management section (existing activities only) ───────────────
        if ($this->current->instance) {
            $mform->addElement('header', 'videomgmtsection', get_string('video_manage_header', 'resource2'));
            $mform->setExpanded('videomgmtsection', true);

            // Hidden field for the replacement video token
            $mform->addElement('hidden', 'vimeo_replace_file_token');
            $mform->setType('vimeo_replace_file_token', PARAM_ALPHANUMEXT);
            $mform->setDefault('vimeo_replace_file_token', '');

            // ── Current video status ───────────────────────────────────────
            $vimeo_record = $DB->get_record('vimeo_files2',
                ['resource2_id' => $this->current->instance]);

            if ($vimeo_record && !empty($vimeo_record->url)
                    && ctype_digit(trim($vimeo_record->url))) {
                $vimeo_id   = trim($vimeo_record->url);
                $vimeo_link = 'https://vimeo.com/' . $vimeo_id;
                $status_html = '<span style="color:#1a7a1a;font-weight:bold;">&#10003; '
                    . get_string('vimeo_current_video', 'resource2') . '</span> &nbsp;'
                    . '<a href="' . $vimeo_link . '" target="_blank">'
                    . get_string('vimeo_view_on_vimeo', 'resource2') . '</a>';
            } elseif ($vimeo_record && $vimeo_record->url === '') {
                $status_html = '<span style="color:#e67e00;font-weight:bold;">'
                    . get_string('vimeo_status_uploading', 'resource2') . '</span>';
            } else {
                $status_html = '<span style="color:#c00;">'
                    . get_string('vimeo_status_none', 'resource2') . '</span>';
            }

            // ── Upload URL / session params ────────────────────────────────
            $upload_url = new moodle_url('/mod/resource2/upload.php');
            $sesskey    = sesskey();
            $courseid   = $this->_course->id;
            $chunk_size = 5 * 1024 * 1024;
            $r2_max_mb  = (int)(get_config('resource2', 'max_video_size_mb') ?: 500);

            $html = '
<div id="r2-mgmt-box" style="max-width:600px;">

  <!-- Current status -->
  <p style="margin:0 0 12px;">' . $status_html . '</p>

  <!-- Replace video heading -->
  <p style="font-weight:bold;margin:14px 0 4px;">' . get_string('vimeo_replace_section', 'resource2') . '</p>
  <small style="display:block;margin-bottom:6px;color:#555;">'
      . get_string('upload_mp4_only_hint', 'resource2') . '</small>
  <input type="file" id="r2_replace_file" accept=".mp4,video/mp4" style="margin-bottom:8px;">

  <!-- Progress -->
  <div id="r2-replace-progress-wrap" style="display:none;margin-top:8px;">
    <progress id="r2-replace-bar" value="0" max="100" style="width:100%;height:22px;"></progress>
    <span id="r2-replace-label" style="display:block;margin-top:4px;font-size:0.9em;color:#555;"></span>
  </div>

  <!-- Status message -->
  <div id="r2-replace-status" style="margin-top:8px;font-weight:bold;color:#c00;"></div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {

    function fmtBytes(b) {
        if (b < 1024*1024)        return (b/1024).toFixed(1) + \' KB\';
        if (b < 1024*1024*1024)   return (b/(1024*1024)).toFixed(1) + \' MB\';
        return (b/(1024*1024*1024)).toFixed(2) + \' GB\';
    }

    var MAX_MB     = ' . $r2_max_mb . ';
    var CHUNK_SIZE = ' . $chunk_size . ';
    var UPLOAD_URL = "' . $upload_url->out(false) . '";
    var SESSKEY    = "' . $sesskey . '";
    var COURSE_ID  = "' . $courseid . '";
    var USER_ID    = "' . $USER->id . '";

    var fileInput    = document.getElementById("r2_replace_file");
    var progressWrap = document.getElementById("r2-replace-progress-wrap");
    var progressBar  = document.getElementById("r2-replace-bar");
    var progressLabel = document.getElementById("r2-replace-label");
    var statusDiv    = document.getElementById("r2-replace-status");
    var pendingField = document.querySelector(\'input[name="vimeo_replace_file_token"]\');
    var submitBtns   = document.querySelectorAll(\'#id_submitbutton,#id_submitbutton2,[name="submitbutton"],[name="submitbutton2"]\');

    fileInput.addEventListener("change", function() {
        var file = fileInput.files[0];
        if (!file) { return; }

        var ext = file.name.split(\'.\').pop().toLowerCase();
        if (ext !== \'mp4\') {
            statusDiv.style.color = "#c00";
            statusDiv.textContent = "' . get_string('upload_error_mp4_only', 'resource2') . '";
            fileInput.value = "";
            return;
        }

        var fileMB = file.size / (1024*1024);
        if (MAX_MB > 0 && fileMB > MAX_MB) {
            statusDiv.style.color = "#c00";
            statusDiv.textContent = "' . addslashes(get_string('quota_error_size', 'resource2',
                (object)['size' => '{size}', 'max' => $r2_max_mb])) . '"
                .replace("{size}", fileMB.toFixed(1));
            fileInput.value = "";
            return;
        }

        statusDiv.textContent = "";
        progressWrap.style.display = "block";
        progressBar.value = 0;
        progressLabel.textContent = "0% — " + fmtBytes(0) + " / " + fmtBytes(file.size);

        submitBtns.forEach(function(b) { b.disabled = true; });

        var totalChunks   = Math.ceil(file.size / CHUNK_SIZE);
        var chunkIndex    = 0;
        var uploadedBytes = 0;
        var tempKey       = "rep_" + USER_ID + "_" + Date.now();

        function uploadChunk() {
            var start = chunkIndex * CHUNK_SIZE;
            var end   = Math.min(start + CHUNK_SIZE, file.size);
            var blob  = file.slice(start, end);

            var fd = new FormData();
            fd.append("file",       blob, file.name);
            fd.append("chunk",      chunkIndex);
            fd.append("chunks",     totalChunks);
            fd.append("temp_key",   tempKey);
            fd.append("total_size", file.size);
            fd.append("sesskey",    SESSKEY);
            fd.append("courseid",   COURSE_ID);

            var xhr = new XMLHttpRequest();
            xhr.open("POST", UPLOAD_URL, true);

            xhr.upload.onprogress = function(e) {
                if (!e.lengthComputable) { return; }
                var sent = uploadedBytes + e.loaded;
                var pct  = Math.min(99, Math.round((sent / file.size) * 100));
                progressBar.value = pct;
                progressLabel.textContent = pct + "% — " + fmtBytes(sent) + " / " + fmtBytes(file.size);
            };

            xhr.onload = function() {
                var resp;
                try { resp = JSON.parse(xhr.responseText); }
                catch(err) { resp = {OK:0, info:"Parse error"}; }

                if (!resp.OK) {
                    statusDiv.style.color = "#c00";
                    statusDiv.textContent = resp.info || "' . get_string('upload_error', 'resource2') . '";
                    progressWrap.style.display = "none";
                    submitBtns.forEach(function(b) { b.disabled = false; });
                    return;
                }

                uploadedBytes += (end - start);

                if (resp.file_token) {
                    pendingField.value = resp.file_token;
                    progressBar.value  = 100;
                    progressLabel.textContent = "100% — " + fmtBytes(file.size) + " / " + fmtBytes(file.size);
                    statusDiv.style.color = "#1a7a1a";
                    statusDiv.textContent = "' . get_string('vimeo_replace_done', 'resource2') . '";
                    submitBtns.forEach(function(b) { b.disabled = false; });
                } else {
                    chunkIndex++;
                    uploadChunk();
                }
            };

            xhr.onerror = function() {
                statusDiv.style.color = "#c00";
                statusDiv.textContent = "' . get_string('upload_error', 'resource2') . '";
                submitBtns.forEach(function(b) { b.disabled = false; });
            };

            xhr.send(fd);
        }

        uploadChunk();
    });
});
</script>';

            $mform->addElement('html', $html);
        }

        // ── Video upload section (new activities only) ────────────────────────
        if (!$this->current->instance) {
            $mform->addElement('header', 'videosection', get_string('video_upload_header', 'resource2'));
            $mform->setExpanded('videosection', true);

            // Hidden field to carry the pre-upload token through to add_instance
            $mform->addElement('hidden', 'vimeo_pending_file_token');
            $mform->setType('vimeo_pending_file_token', PARAM_ALPHANUMEXT);
            $mform->setDefault('vimeo_pending_file_token', '');

            // ── Quota values from config ───────────────────────────────────────
            $r2_cur_count  = (int)(get_config('resource2', 'video_count')          ?: 0);
            $r2_max_count  = (int)(get_config('resource2', 'max_video_count')       ?: 500);
            $r2_cur_bytes  = (int)(get_config('resource2', 'total_storage_bytes')   ?: 0);
            $r2_max_gb     = (int)(get_config('resource2', 'max_total_storage_gb')  ?: 50);
            $r2_max_mb     = (int)(get_config('resource2', 'max_video_size_mb')     ?: 500);
            $r2_cur_gb     = round($r2_cur_bytes / (1024 * 1024 * 1024), 2);
            $r2_count_ok   = $r2_cur_count < $r2_max_count;
            $r2_storage_ok = $r2_cur_gb < $r2_max_gb;
            $r2_can_upload = $r2_count_ok && $r2_storage_ok;

            $tick   = '&#10003;';  // ✓
            $cross  = '&#10007;';  // ✗
            $ok_col = '#1a7a1a';
            $no_col = '#c00';

            // ── Upload URL / session params ────────────────────────────────────
            $upload_url = new moodle_url('/mod/resource2/upload.php');
            $sesskey    = sesskey();
            $courseid   = $this->_course->id;
            $chunk_size = 5 * 1024 * 1024; // 5 MB chunks

            $html = '
<div id="r2-upload-box" style="margin:10px 0;max-width:600px;">

  <!-- ── Quota info table ───────────────────────────────────────────────── -->
  <table style="width:100%;border-collapse:collapse;margin-bottom:14px;font-size:0.92em;">
    <thead>
      <tr style="background:#f0f0f0;">
        <th style="text-align:left;padding:5px 8px;border:1px solid #ddd;">Item</th>
        <th style="text-align:center;padding:5px 8px;border:1px solid #ddd;">Used</th>
        <th style="text-align:center;padding:5px 8px;border:1px solid #ddd;">Limit</th>
        <th style="text-align:center;padding:5px 8px;border:1px solid #ddd;">Status</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="padding:5px 8px;border:1px solid #ddd;">Videos count</td>
        <td style="text-align:center;padding:5px 8px;border:1px solid #ddd;">' . $r2_cur_count . '</td>
        <td style="text-align:center;padding:5px 8px;border:1px solid #ddd;">' . $r2_max_count . '</td>
        <td style="text-align:center;padding:5px 8px;border:1px solid #ddd;color:' . ($r2_count_ok ? $ok_col : $no_col) . ';font-weight:bold;">'
            . ($r2_count_ok ? $tick : $cross) . '</td>
      </tr>
      <tr>
        <td style="padding:5px 8px;border:1px solid #ddd;">Storage used</td>
        <td style="text-align:center;padding:5px 8px;border:1px solid #ddd;">' . $r2_cur_gb . ' GB</td>
        <td style="text-align:center;padding:5px 8px;border:1px solid #ddd;">' . $r2_max_gb . ' GB</td>
        <td style="text-align:center;padding:5px 8px;border:1px solid #ddd;color:' . ($r2_storage_ok ? $ok_col : $no_col) . ';font-weight:bold;">'
            . ($r2_storage_ok ? $tick : $cross) . '</td>
      </tr>
      <tr>
        <td style="padding:5px 8px;border:1px solid #ddd;">Max file size</td>
        <td style="text-align:center;padding:5px 8px;border:1px solid #ddd;" id="r2-file-size-cell">—</td>
        <td style="text-align:center;padding:5px 8px;border:1px solid #ddd;">' . $r2_max_mb . ' MB</td>
        <td style="text-align:center;padding:5px 8px;border:1px solid #ddd;font-weight:bold;" id="r2-file-size-status">—</td>
      </tr>
    </tbody>
  </table>

  ' . (!$r2_can_upload ? '<div style="color:' . $no_col . ';font-weight:bold;margin-bottom:10px;">'
      . get_string('quota_error_' . (!$r2_count_ok ? 'count' : 'storage'), 'resource2',
          !$r2_count_ok ? $r2_max_count : null)
      . '</div>' : '') . '

  <!-- ── File picker ───────────────────────────────────────────────────── -->
  <label for="r2_video_file" style="display:block;margin-bottom:6px;font-weight:bold;">
    ' . get_string('choose_video_file', 'resource2') . '
  </label>
  <small style="display:block;margin-bottom:6px;color:#555;">
    ' . get_string('upload_mp4_only_hint', 'resource2') . '
  </small>
  <input type="file" id="r2_video_file" accept=".mp4,video/mp4"
         style="margin-bottom:8px;"' . (!$r2_can_upload ? ' disabled' : '') . '>

  <!-- ── Progress bar ──────────────────────────────────────────────────── -->
  <div id="r2-progress-wrap" style="display:none;margin-top:8px;">
    <progress id="r2-progress-bar" value="0" max="100"
              style="width:100%;height:22px;"></progress>
    <span id="r2-progress-label"
          style="display:block;margin-top:4px;font-size:0.9em;color:#555;"></span>
  </div>

  <!-- ── Status message ────────────────────────────────────────────────── -->
  <div id="r2-status" style="margin-top:8px;font-weight:bold;color:#c00;"></div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {

    /* ── helpers ──────────────────────────────────────────────────────── */
    function fmtBytes(b) {
        if (b < 1024 * 1024)             return (b / 1024).toFixed(1) + \' KB\';
        if (b < 1024 * 1024 * 1024)      return (b / (1024 * 1024)).toFixed(1) + \' MB\';
        return (b / (1024 * 1024 * 1024)).toFixed(2) + \' GB\';
    }

    var MAX_MB      = ' . $r2_max_mb . ';
    var CAN_UPLOAD  = ' . ($r2_can_upload ? 'true' : 'false') . ';
    var CHUNK_SIZE  = ' . $chunk_size . ';
    var UPLOAD_URL  = "' . $upload_url->out(false) . '";
    var SESSKEY     = "' . $sesskey . '";
    var COURSE_ID   = "' . $courseid . '";
    var USER_ID     = "' . $USER->id . '";

    var fileInput     = document.getElementById("r2_video_file");
    var progressWrap  = document.getElementById("r2-progress-wrap");
    var progressBar   = document.getElementById("r2-progress-bar");
    var progressLabel = document.getElementById("r2-progress-label");
    var statusDiv     = document.getElementById("r2-status");
    var fileSizeCell  = document.getElementById("r2-file-size-cell");
    var fileSizeStat  = document.getElementById("r2-file-size-status");
    var pendingField  = document.querySelector(\'input[name="vimeo_pending_file_token"]\');
    var submitBtn     = document.querySelector(\'#id_submitbutton\') ||
                        document.querySelector(\'[name="submitbutton"]\');

    if (!pendingField) { return; }

    /* Disable Save until upload finishes */
    if (submitBtn) { submitBtn.disabled = true; }

    /* ── File picked ─────────────────────────────────────────────────── */
    fileInput.addEventListener("change", function() {
        var file = fileInput.files[0];
        if (!file) { return; }

        /* ── MP4-only check ───────────────────────────────────────── */
        var ext = file.name.split(\'.\').pop().toLowerCase();
        if (ext !== \'mp4\') {
            statusDiv.style.color = "#c00";
            statusDiv.textContent = "' . get_string('upload_error_mp4_only', 'resource2') . '";
            fileInput.value = "";
            fileSizeCell.textContent = "—";
            fileSizeStat.textContent = "—";
            return;
        }

        var fileMB = file.size / (1024 * 1024);
        var sizeOK = (MAX_MB <= 0 || fileMB <= MAX_MB);

        /* Update quota table row */
        fileSizeCell.textContent = fmtBytes(file.size);
        fileSizeStat.style.color  = sizeOK ? "#1a7a1a" : "#c00";
        fileSizeStat.textContent  = sizeOK ? "\u2713" : "\u2717";

        if (!CAN_UPLOAD) { return; }

        if (!sizeOK) {
            statusDiv.style.color   = "#c00";
            statusDiv.textContent   = "' . addslashes(get_string('quota_error_size', 'resource2',
                (object)['size' => '{size}', 'max' => $r2_max_mb])) . '"
                .replace("{size}", fileMB.toFixed(1));
            progressWrap.style.display = "none";
            return;
        }

        /* ── Start chunked upload ─────────────────────────────────── */
        statusDiv.textContent  = "";
        progressWrap.style.display = "block";
        progressBar.value      = 0;
        progressLabel.textContent = "0% — " + fmtBytes(0) + " / " + fmtBytes(file.size);
        if (submitBtn) { submitBtn.disabled = true; }

        var totalChunks    = Math.ceil(file.size / CHUNK_SIZE);
        var chunkIndex     = 0;
        var uploadedBytes  = 0;   /* bytes from fully-sent chunks */
        var tempKey        = "pre_" + USER_ID + "_" + Date.now();

        function uploadChunk() {
            var start = chunkIndex * CHUNK_SIZE;
            var end   = Math.min(start + CHUNK_SIZE, file.size);
            var blob  = file.slice(start, end);

            var fd = new FormData();
            fd.append("file",       blob, file.name);
            fd.append("chunk",      chunkIndex);
            fd.append("chunks",     totalChunks);
            fd.append("temp_key",   tempKey);
            fd.append("total_size", file.size);
            fd.append("sesskey",    SESSKEY);
            fd.append("courseid",   COURSE_ID);

            var xhr = new XMLHttpRequest();
            xhr.open("POST", UPLOAD_URL, true);

            /* ── Real-time progress for THIS chunk ────────────────── */
            xhr.upload.onprogress = function(e) {
                if (!e.lengthComputable) { return; }
                var sent = uploadedBytes + e.loaded;
                var pct  = Math.min(99, Math.round((sent / file.size) * 100));
                progressBar.value     = pct;
                progressLabel.textContent = pct + "% — " + fmtBytes(sent) + " / " + fmtBytes(file.size);
            };

            xhr.onload = function() {
                var resp;
                try { resp = JSON.parse(xhr.responseText); }
                catch(err) { resp = {OK: 0, info: "Parse error"}; }

                if (!resp.OK) {
                    statusDiv.style.color = "#c00";
                    statusDiv.textContent = resp.info || "' . get_string('upload_error', 'resource2') . '";
                    progressWrap.style.display = "none";
                    return;
                }

                uploadedBytes += (end - start);   /* chunk fully ACKed */

                if (resp.file_token) {
                    /* ── All done ─────────────────────────────────── */
                    pendingField.value        = resp.file_token;
                    progressBar.value         = 100;
                    progressLabel.textContent = "100% — " + fmtBytes(file.size) + " / " + fmtBytes(file.size);
                    statusDiv.style.color     = "#1a7a1a";
                    statusDiv.textContent     = "' . get_string('upload_complete', 'resource2') . '";
                    if (submitBtn) { submitBtn.disabled = false; }
                } else {
                    /* ── Next chunk ───────────────────────────────── */
                    chunkIndex++;
                    uploadChunk();
                }
            };

            xhr.onerror = function() {
                statusDiv.style.color = "#c00";
                statusDiv.textContent = "' . get_string('upload_error', 'resource2') . '";
            };

            xhr.send(fd);
        }

        uploadChunk();
    });
});
</script>';

            $mform->addElement('html', $html);
        }

        //-------------------------------------------------------
        $this->standard_coursemodule_elements();

        //-------------------------------------------------------
        $this->add_action_buttons();

        //-------------------------------------------------------
        $mform->addElement('hidden', 'revision');
        $mform->setType('revision', PARAM_INT);
        $mform->setDefault('revision', 1);
    }

    function data_preprocessing(&$default_values) {
        if ($this->current->instance and !$this->current->tobemigrated) {
            $draftitemid = file_get_submitted_draft_itemid('files');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_resource2', 'content', 0, array('subdirs'=>true));
            $default_values['files'] = $draftitemid;
        }
        if (!empty($default_values['displayoptions'])) {
            $displayoptions = (array) unserialize_array($default_values['displayoptions']);
            if (isset($displayoptions['printintro'])) {
                $default_values['printintro'] = $displayoptions['printintro'];
            }
            if (!empty($displayoptions['popupwidth'])) {
                $default_values['popupwidth'] = $displayoptions['popupwidth'];
            }
            if (!empty($displayoptions['popupheight'])) {
                $default_values['popupheight'] = $displayoptions['popupheight'];
            }
            if (!empty($displayoptions['showsize'])) {
                $default_values['showsize'] = $displayoptions['showsize'];
            } else {
                // Must set explicitly to 0 here otherwise it will use system
                // default which may be 1.
                $default_values['showsize'] = 0;
            }
            if (!empty($displayoptions['showtype'])) {
                $default_values['showtype'] = $displayoptions['showtype'];
            } else {
                $default_values['showtype'] = 0;
            }
            if (!empty($displayoptions['showdate'])) {
                $default_values['showdate'] = $displayoptions['showdate'];
            } else {
                $default_values['showdate'] = 0;
            }
        }
    }

    function definition_after_data() {
        if ($this->current->instance and $this->current->tobemigrated) {
            // resource2 not migrated yet
            return;
        }

        parent::definition_after_data();
    }

    function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        // if (!$files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['files'], 'sortorder, id', false)) {
        //     $errors['files'] = get_string('required');
        //     return $errors;
        // }
        if (count($files) == 1) {
            // no need to select main file if only one picked
            return $errors;
        } else if(count($files) > 1) {
            $mainfile = false;
            foreach($files as $file) {
                if ($file->get_sortorder() == 1) {
                    $mainfile = true;
                    break;
                }
            }
            // set a default main file
            if (!$mainfile) {
                $file = reset($files);
                file_set_sortorder($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(),
                                   $file->get_filepath(), $file->get_filename(), 1);
            }
        }
        return $errors;
    }
}
