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

/**
 * Generate text action provider settings form.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_generate_text_form extends action_form {
    #[\Override]
    protected function definition(): void {
        parent::definition();

        $mform = $this->_form;

        // System Instructions.
        $mform->addElement(
            'textarea',
            'systeminstruction',
            get_string("action:{$this->actionname}:systeminstruction", 'aiprovider_datacurso'),
            [
                'wrap' => 'virtual',
                'rows' => 5,
                'cols' => 20,
            ]
        );
        $mform->setType('systeminstruction', PARAM_TEXT);

        // Assign default value.
        $actionconfig = $this->_customdata['actionconfig'] ?? [];
        $actionclass = $this->_customdata['action'];

        $mform->setDefault(
            'systeminstruction',
            $actionconfig['systeminstruction'] ?? $actionclass::get_system_instruction()
        );

        $mform->addHelpButton(
            'systeminstruction',
            "action:{$this->actionname}:systeminstruction",
            'aiprovider_datacurso'
        );

        // Add the action class as a hidden field.
        $mform->addElement('hidden', 'action', $this->action);
        $mform->setType('action', PARAM_TEXT);

        // Add the provider class as a hidden field.
        $mform->addElement('hidden', 'provider', $this->providername);
        $mform->setType('provider', PARAM_TEXT);

        // Add the provider id as a hidden field.
        $mform->addElement('hidden', 'providerid', $this->providerid);
        $mform->setType('providerid', PARAM_INT);
    }

    /**
     * Return default values for this form.
     *
     * @return array
     */
    public function get_defaults(): array {
        $actionclass = $this->_customdata['action'];

        return [
            'systeminstruction' => $actionclass::get_system_instruction(),
        ];
    }
}
