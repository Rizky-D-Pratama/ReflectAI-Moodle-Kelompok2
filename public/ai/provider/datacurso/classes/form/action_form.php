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

namespace aiprovider_datacurso\form;

use core_ai\form\action_settings_form;

/**
 * Base action settings form for Datacurso provider.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_form extends action_settings_form {
    /**
     * @var array Action configuration.
     */
    protected array $actionconfig;

    /**
     * @var string|null Return URL.
     */
    protected ?string $returnurl;

    /**
     * @var string Action name.
     */
    protected string $actionname;

    /**
     * @var string Action class.
     */
    protected string $action;

    /**
     * @var int Provider ID.
     */
    protected int $providerid;

    /**
     * @var string Provider name.
     */
    protected string $providername;

    /**
     * Defines the form fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $this->actionconfig = $this->_customdata['actionconfig']['settings'] ?? [];
        $this->returnurl = $this->_customdata['returnurl'] ?? null;
        $this->actionname = $this->_customdata['actionname'];
        $this->action = $this->_customdata['action'];
        $this->providerid = $this->_customdata['providerid'] ?? 0;
        $this->providername = $this->_customdata['providername'] ?? 'aiprovider_datacurso';

        if (!empty($this->providerid)) {
            $mform->addElement('hidden', 'provider', $this->providerid);
            $mform->setType('provider', PARAM_INT);
        }

        $mform->addElement('header', 'generalsettingsheader', get_string('general', 'core'));
    }

    /**
     * Validates the form data.
     *
     * @param array $data The submitted form data.
     * @param array $files Uploaded files.
     * @return array List of validation errors, if any.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Validate the extra parameters.
        if (!empty($data['modelextraparams'])) {
            json_decode($data['modelextraparams']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['modelextraparams'] = get_string('invalidjson', 'aiprovider_datacurso');
            }
        }

        return $errors;
    }
}
