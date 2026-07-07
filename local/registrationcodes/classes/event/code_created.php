<?php
namespace local_registrationcodes\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event fired when a registration code is created.
 */
class code_created extends \core\event\base {

    protected function init() {
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_regcodes';
    }

    public static function get_name() {
        return get_string('event_code_created', 'local_registrationcodes');
    }

    public function get_description() {
        $code = isset($this->other['code']) ? $this->other['code'] : '';
        return "User with id '{$this->userid}' created registration code '{$code}'.";
    }

    public function get_url() {
        return new \moodle_url('/local/registrationcodes/admin.php');
    }
}
