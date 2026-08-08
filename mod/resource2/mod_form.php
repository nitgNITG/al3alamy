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
        global $CFG, $DB;
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

        //-------------------------------------------------------
        $this->standard_coursemodule_elements();

        //-------------------------------------------------------
        // ── Video upload section (new modules only) ──────────────────────
        if (!$this->current->instance) {
            // Quota pre-check: if limits exceeded, show error and skip upload UI.
            $max_count      = (int)(get_config('resource2', 'max_video_count')      ?: 500);
            $cur_count      = (int)(get_config('resource2', 'video_count')          ?: 0);
            $max_size_mb    = (int)(get_config('resource2', 'max_video_size_mb')    ?: 500);
            $max_storage_gb = (int)(get_config('resource2', 'max_total_storage_gb') ?: 50);
            $cur_bytes      = (int)(get_config('resource2', 'total_storage_bytes')  ?: 0);
            $cur_gb         = round($cur_bytes / (1024 * 1024 * 1024), 2);
            $quota_ok       = ($cur_count < $max_count) && ($cur_gb < $max_storage_gb);

            $mform->addElement('header', 'vimeo_upload_header',
                get_string('vimeo_upload_section', 'resource2'));
            $mform->setExpanded('vimeo_upload_header');

            if (!$quota_ok) {
                // Show which quota is exceeded.
                if ($cur_count >= $max_count) {
                    $mform->addElement('static', 'quota_error_display', '',
                        html_writer::tag('div',
                            get_string('quota_error_count', 'resource2', $max_count),
                            ['class' => 'alert alert-danger']));
                } else {
                    $mform->addElement('static', 'quota_error_display', '',
                        html_writer::tag('div',
                            get_string('quota_error_storage', 'resource2'),
                            ['class' => 'alert alert-danger']));
                }
            } else {
                // Hidden fields — populated by JS when upload completes.
                $mform->addElement('hidden', 'vimeo_pending_record_id', 0);
                $mform->setType('vimeo_pending_record_id', PARAM_INT);

                $mform->addElement('hidden', 'video_type', 2);
                $mform->setType('video_type', PARAM_INT);

                // Video type selector.
                $type_options = [
                    1 => get_string('vimeo_type_quiz',     'resource2'),
                    2 => get_string('vimeo_type_lecture',  'resource2'),
                    3 => get_string('vimeo_type_homework', 'resource2'),
                    4 => get_string('vimeo_type_summary',  'resource2'),
                    5 => get_string('vimeo_type_revision', 'resource2'),
                ];
                $mform->addElement('select', 'video_type_visible',
                    get_string('vimeo_type_label', 'resource2'), $type_options);
                $mform->setDefault('video_type_visible', 2);

                // Upload UI rendered as raw HTML.
                $upload_url = new moodle_url('/mod/resource2/upload.php');
                $sesskey    = sesskey();
                $courseid   = $this->_course->id;

                $upload_html = '
<div id="r2-upload-wrap" style="margin:10px 0;">
  <div class="form-group row">
    <label class="col-sm-3 col-form-label" for="r2-file-input">'
    . get_string('vimeo_file_label', 'resource2') . '</label>
    <div class="col-sm-9">
      <input type="file" id="r2-file-input" class="form-control-file"
             accept="video/mp4,video/quicktime,video/x-msvideo,video/*">
    </div>
  </div>
  <div class="form-group row" id="r2-upload-controls" style="display:none;">
    <div class="col-sm-9 offset-sm-3">
      <button type="button" id="r2-upload-btn" class="btn btn-primary">'
    . get_string('vimeo_upload_btn', 'resource2') . '</button>
      <div class="progress mt-2" style="height:20px;">
        <div id="r2-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
             role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
      </div>
      <p id="r2-upload-status" class="mt-1 text-muted"></p>
    </div>
  </div>
  <div id="r2-upload-done" class="alert alert-success" style="display:none;"></div>
  <div id="r2-upload-error" class="alert alert-danger" style="display:none;"></div>
</div>
<script>
(function () {
  var CHUNK_SIZE  = 10 * 1024 * 1024; // 10 MB
  var UPLOAD_URL  = ' . json_encode($upload_url->out(false)) . ';
  var SESSKEY     = ' . json_encode($sesskey) . ';
  var COURSEID    = ' . json_encode((string)$courseid) . ';
  var MAX_SIZE_MB = ' . json_encode($max_size_mb) . ';

  var fileInput   = document.getElementById("r2-file-input");
  var uploadBtn   = document.getElementById("r2-upload-btn");
  var controls    = document.getElementById("r2-upload-controls");
  var progressBar = document.getElementById("r2-progress-bar");
  var statusEl    = document.getElementById("r2-upload-status");
  var doneEl      = document.getElementById("r2-upload-done");
  var errorEl     = document.getElementById("r2-upload-error");
  var pendingField= document.getElementById("id_vimeo_pending_record_id");
  var typeField   = document.getElementById("id_video_type");
  var typeVisible = document.getElementById("id_video_type_visible");

  // Sync visible select → hidden field.
  if (typeVisible && typeField) {
    typeVisible.addEventListener("change", function () {
      typeField.value = typeVisible.value;
    });
    typeField.value = typeVisible.value;
  }

  // Show upload controls when a file is chosen.
  fileInput.addEventListener("change", function () {
    errorEl.style.display = "none";
    doneEl.style.display  = "none";
    controls.style.display = fileInput.files.length ? "block" : "none";
  });

  // Upload button handler.
  uploadBtn.addEventListener("click", async function () {
    var file = fileInput.files[0];
    if (!file) return;

    var sizeMb = file.size / (1024 * 1024);
    if (MAX_SIZE_MB > 0 && sizeMb > MAX_SIZE_MB) {
      showError("' . get_string('quota_error_size', 'resource2', (object)['size' => "' + Math.round(sizeMb) + '", 'max' => "' + MAX_SIZE_MB + '"]) . '");
      return;
    }

    uploadBtn.disabled = true;
    errorEl.style.display = "none";

    // Read video title from the activity name field (or filename).
    var nameField = document.getElementById("id_name");
    var vname = (nameField && nameField.value.trim()) ? nameField.value.trim() : file.name;

    var tempKey = "u' . $USER->id . '_" + Date.now();
    var chunks  = Math.ceil(file.size / CHUNK_SIZE);

    try {
      var recordId = await uploadChunked(file, vname, tempKey, chunks);
      // Success.
      pendingField.value = recordId;
      // Keep video_type in sync.
      typeField.value = typeVisible ? typeVisible.value : "2";
      setProgress(100);
      doneEl.textContent = ' . json_encode(get_string('vimeo_upload_done', 'resource2')) . ';
      doneEl.style.display = "block";
      statusEl.textContent = "";
      // Enable the submit buttons.
      enableSaveButtons();
    } catch (err) {
      showError(err.message || String(err));
      uploadBtn.disabled = false;
    }
  });

  async function uploadChunked(file, vname, tempKey, totalChunks) {
    for (var i = 0; i < totalChunks; i++) {
      var start = i * CHUNK_SIZE;
      var end   = Math.min(start + CHUNK_SIZE, file.size);
      var blob  = file.slice(start, end);

      var fd = new FormData();
      fd.append("file",       blob, file.name);
      fd.append("chunk",      i);
      fd.append("chunks",     totalChunks);
      fd.append("temp_key",   tempKey);
      fd.append("total_size", file.size);
      fd.append("vname",      vname);
      fd.append("description", vname);
      fd.append("video_type",  typeVisible ? typeVisible.value : "2");
      fd.append("sesskey",     SESSKEY);
      fd.append("courseid",    COURSEID);

      var pct = Math.round(((i + 1) / totalChunks) * 100);
      setProgress(pct);
      statusEl.textContent = "' . get_string('vimeo_uploading', 'resource2', "' + pct + '") . '";

      var resp = await fetch(UPLOAD_URL, { method: "POST", body: fd });
      var data = await resp.json();

      if (!data.OK) {
        throw new Error(data.info || "Upload error on chunk " + i);
      }
      if (data.record_id) {
        return data.record_id; // Last chunk returned the DB record id.
      }
    }
    throw new Error("No record_id returned from server.");
  }

  function setProgress(pct) {
    progressBar.style.width  = pct + "%";
    progressBar.textContent  = pct + "%";
    progressBar.setAttribute("aria-valuenow", pct);
  }

  function showError(msg) {
    errorEl.textContent    = msg;
    errorEl.style.display  = "block";
    doneEl.style.display   = "none";
    pendingField.value     = 0;
    disableSaveButtons("Please fix the upload error before saving.");
  }

  function enableSaveButtons() {
    var btns = document.querySelectorAll(
      "#id_submitbutton, #id_submitbutton2, [name=submitbutton], [name=submitbutton2]");
    btns.forEach(function (b) {
      b.disabled = false;
      b.removeAttribute("title");
    });
  }

  function disableSaveButtons(msg) {
    var btns = document.querySelectorAll(
      "#id_submitbutton, #id_submitbutton2, [name=submitbutton], [name=submitbutton2]");
    btns.forEach(function (b) {
      b.disabled = true;
      if (msg) b.title = msg;
    });
  }

  // On page load: disable save buttons until upload completes (new module only).
  document.addEventListener("DOMContentLoaded", function () {
    if (!pendingField || pendingField.value == "0" || pendingField.value === "") {
      disableSaveButtons("' . get_string('vimeo_upload_required', 'resource2') . '");
    }
  });
})();
</script>';

                $mform->addElement('html', $upload_html);
            }
        }

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

        // For new modules: require a video upload to be started.
        if (empty($this->current->instance) && empty($data['vimeo_pending_record_id'])) {
            $errors['vimeo_upload_header'] = get_string('vimeo_upload_required', 'resource2');
        }

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
