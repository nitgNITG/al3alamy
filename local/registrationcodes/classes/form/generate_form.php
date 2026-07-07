<?php
namespace local_registrationcodes\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for generating one or more registration codes.
 */
class generate_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'generateheader', get_string('generate_codes', 'local_registrationcodes'));

        // Quantity.
        $quantities = [
            1   => '1',
            10  => '10',
            50  => '50',
            100 => '100',
            500 => '500',
            0   => get_string('custom', 'local_registrationcodes'),
        ];
        $mform->addElement('select', 'quantity_preset', get_string('quantity', 'local_registrationcodes'), $quantities);
        $mform->setDefault('quantity_preset', 1);

        $mform->addElement('text', 'quantity_custom', get_string('custom_quantity', 'local_registrationcodes'), ['size' => 8]);
        $mform->setType('quantity_custom', PARAM_INT);
        $mform->hideIf('quantity_custom', 'quantity_preset', 'neq', '0');

        // Prefix.
        $mform->addElement('text', 'prefix', get_string('prefix', 'local_registrationcodes'), ['size' => 10, 'maxlength' => 10]);
        $mform->setType('prefix', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('prefix', 'prefix', 'local_registrationcodes');

        // Expiry date.
        $mform->addElement('date_selector', 'timeexpiry', get_string('expiry_date', 'local_registrationcodes'), ['optional' => true]);

        // Notes.
        $mform->addElement('text', 'notes', get_string('notes', 'local_registrationcodes'), ['size' => 50]);
        $mform->setType('notes', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('generate', 'local_registrationcodes'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $qty = (int)$data['quantity_preset'];
        if ($qty === 0) {
            $custom = (int)($data['quantity_custom'] ?? 0);
            if ($custom < 1 || $custom > 5000) {
                $errors['quantity_custom'] = get_string('error_invalid_quantity', 'local_registrationcodes');
            }
        }

        return $errors;
    }
}
