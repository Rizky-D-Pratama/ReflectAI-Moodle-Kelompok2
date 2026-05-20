<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace aiprovider_datacurso\form;

defined('MOODLE_INTERNAL') || die();

use aiprovider_datacurso\local\user_token_limit_manager;
use context_system;
use core_form\dynamic_form;
use moodle_url;

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to create or edit per-user token limits.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_token_limit_form extends dynamic_form {
    /**
     * Define the form fields.
     */
    public function definition(): void {
        $mform = $this->_form;

        $id = (int)$this->optional_param('id', 0, PARAM_INT);
        $returnurl = (string)$this->optional_param('returnurl', '', PARAM_LOCALURL);
        $editing = $id > 0;

        if ($editing) {
            $userlabel = (string)$this->optional_param('userlabel', '', PARAM_TEXT);
            $mform->addElement(
                'static',
                'userlabel',
                get_string('usertokenlimit_user', 'aiprovider_datacurso'),
                format_string($userlabel)
            );
            $mform->addHelpButton('userlabel', 'usertokenlimit_user_readonly', 'aiprovider_datacurso');
            $mform->addElement('hidden', 'userid');
            $mform->setType('userid', PARAM_INT);
        } else {
            $options = [
                'ajax' => 'core_user/form_user_selector',
                'multiple' => false,
                'placeholder' => get_string('search'),
                'noselectionstring' => get_string('noselection', 'form'),
            ];
            $mform->addElement('autocomplete', 'userid', get_string('usertokenlimit_user', 'aiprovider_datacurso'), [], $options);
            $mform->addHelpButton('userid', 'usertokenlimit_user', 'aiprovider_datacurso');
            $mform->addRule('userid', get_string('required'), 'required', null, 'client');
            $mform->setType('userid', PARAM_INT);
        }

        $mform->addElement('text', 'tokenlimit', get_string('usertokenlimit_limit', 'aiprovider_datacurso'));
        $mform->addHelpButton('tokenlimit', 'usertokenlimit_limit', 'aiprovider_datacurso');
        $mform->setType('tokenlimit', PARAM_INT);
        $mform->addRule('tokenlimit', get_string('required'), 'required', null, 'client');
        $mform->addRule('tokenlimit', null, 'numeric', null, 'client');

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'returnurl', $returnurl);
        $mform->setType('returnurl', PARAM_LOCALURL);
    }

    /**
     * Check access to submit dynamically.
     */
    protected function check_access_for_dynamic_submission(): void {
        $context = context_system::instance();
        require_capability('aiprovider/datacurso:managetokenlimits', $context);
    }

    /**
     * Context for validation.
     */
    public function get_context_for_dynamic_submission(): \context {
        return context_system::instance();
    }

    /**
     * Page URL for dynamic submission.
     */
    public function get_page_url_for_dynamic_submission(): moodle_url {
        $returnurl = (string)$this->optional_param('returnurl', '', PARAM_LOCALURL);
        if (!empty($returnurl)) {
            return new moodle_url($returnurl);
        }
        return new moodle_url('/ai/provider/datacurso/admin/user_token_limits.php');
    }

    /**
     * Preload data when editing.
     */
    public function set_data_for_dynamic_submission(): void {
        $id = (int)$this->optional_param('id', 0, PARAM_INT);
        if ($id) {
            $record = user_token_limit_manager::get_by_id($id);
            if ($record) {
                $user = \core_user::get_user($record->userid, '*', MUST_EXIST);
                $data = new \stdClass();
                $data->id = $record->id;
                $data->userid = $record->userid;
                $data->tokenlimit = $record->tokenlimit;
                $data->userlabel = fullname($user) . ' (' . $user->email . ')';
                $this->set_data($data);
            }
        }
    }

    /**
     * Process submission and return redirect URL.
     *
     * @return string
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        user_token_limit_manager::save($data->userid, $data->tokenlimit, $data->id);

        $returnurl = !empty($data->returnurl)
            ? new moodle_url($data->returnurl)
            : new moodle_url('/ai/provider/datacurso/admin/user_token_limits.php');
        return $returnurl->out(false);
    }

    /**
     * Validate form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $tokenlimit = (int)($data['tokenlimit'] ?? 0);
        if ($tokenlimit <= 0) {
            $errors['tokenlimit'] = get_string('usertokenlimit_limit_invalid', 'aiprovider_datacurso');
        }

        if (empty($data['id']) && empty($data['userid'])) {
            $errors['userid'] = get_string('required');
        }

        return $errors;
    }
}
