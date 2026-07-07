<?php
namespace local_registrationcodes\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event fired when one or more registration codes are deleted.
 */
class code_deleted extends \core\event\base {

    protected function init() {
        $this->data['crud']        = 'd';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_regcodes';
    }

    public static function get_name() {
        return get_string('event_code_deleted', 'local_registrationcodes');
    }

    public function get_description() {
        $count = isset($this->other['count']) ? $this->other['count'] : 1;
        return "User with id '{$this->userid}' deleted {$count} registration code(s).";
    }

    public function get_url() {
        return new \moodle_url('/local/registrationcodes/admin.php');
    }
}
