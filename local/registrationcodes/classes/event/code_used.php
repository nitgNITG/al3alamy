<?php
namespace local_registrationcodes\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event fired when a registration code is consumed during sign-up.
 */
class code_used extends \core\event\base {

    protected function init() {
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_regcodes';
    }

    public static function get_name() {
        return get_string('event_code_used', 'local_registrationcodes');
    }

    public function get_description() {
        $code = isset($this->other['code']) ? $this->other['code'] : '';
        return "User with id '{$this->userid}' registered using code '{$code}'.";
    }

    public function get_url() {
        return new \moodle_url('/local/registrationcodes/report.php');
    }
}
