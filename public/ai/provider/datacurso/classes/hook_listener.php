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

namespace aiprovider_datacurso;

use core_ai\hook\after_ai_provider_form_hook;
use core_ai\hook\after_ai_action_settings_form_hook;

/**
 * Hook listener for the Datacurso AI Provider.
 *
 * @package     aiprovider_datacurso
 * @copyright   2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Extend the AI provider configuration form for Datacurso.
     *
     * This method is triggered by the hook after_ai_provider_form_hook
     * to append custom elements, including the Rate Limit configuration.
     *
     * @param after_ai_provider_form_hook $hook The hook object containing the form instance.
     */
    public static function set_form_definition_for_aiprovider_datacurso(after_ai_provider_form_hook $hook): void {
        global $PAGE;

        if ($hook->plugin !== 'aiprovider_datacurso') {
            return;
        }

        $mform = $hook->mform;

        // License Key and Warning (Unchanged from original).
        $mform->addElement(
            'passwordunmask',
            'licensekey',
            get_string('licensekey', 'aiprovider_datacurso'),
            ['size' => 50]
        );
        $mform->addHelpButton('licensekey', 'licensekey', 'aiprovider_datacurso');
        $mform->addRule('licensekey', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'static',
            'warningconfig_instance',
            '',
            \html_writer::div(
                get_string('warningconfig_instance', 'aiprovider_datacurso'),
                'alert alert-warning'
            )
        );

        // Rate Limit Settings (For Service).
        $services = \aiprovider_datacurso\provider::get_services();
        \core_collator::asort_array_of_arrays_by_key($services, 'name');

        foreach ($services as $service) {
            $sid = $service['id'];
            $sname = $service['name'];

            // Service Header for Rate Limit section.
            $mform->addElement(
                'header',
                "ratelimit_{$sid}_header",
                format_string($sname)
            );

            // Generic Rate Limit fields (Enabled, Limit, Window).
            $mform->addElement(
                'advcheckbox',
                "ratelimit_{$sid}_enable",
                get_string('ratelimit_enable', 'aiprovider_datacurso'),
                get_string('ratelimit_enable_desc', 'aiprovider_datacurso')
            );
            $mform->setType("ratelimit_{$sid}_enable", PARAM_BOOL);

            $mform->addElement(
                'text',
                "ratelimit_{$sid}_limit",
                get_string('ratelimit_limit', 'aiprovider_datacurso'),
                ['size' => 10]
            );
            $mform->setType("ratelimit_{$sid}_limit", PARAM_INT);
            $mform->addHelpButton("ratelimit_{$sid}_limit", 'ratelimit_limit', 'aiprovider_datacurso');
            $mform->hideIf("ratelimit_{$sid}_limit", "ratelimit_{$sid}_enable", 'eq', 0);

            $options = [
                'seconds' => get_string('seconds'),
                'minutes' => get_string('minutes'),
                'hours' => get_string('hours'),
                'days' => get_string('days'),
                'months' => get_string('months'),
                'years' => get_string('years'),
            ];

            $mform->addElement(
                'text',
                "ratelimit_{$sid}_window_value",
                get_string('ratelimit_window_value', 'aiprovider_datacurso'),
                ['size' => 5]
            );
            $mform->setType("ratelimit_{$sid}_window_value", PARAM_INT);

            $mform->addElement(
                'select',
                "ratelimit_{$sid}_window_unit",
                get_string('ratelimit_window_unit', 'aiprovider_datacurso'),
                $options
            );
            $mform->setType("ratelimit_{$sid}_window_unit", PARAM_ALPHANUMEXT);

            $mform->hideIf("ratelimit_{$sid}_window_value", "ratelimit_{$sid}_enable", 'eq', 0);
            $mform->hideIf("ratelimit_{$sid}_window_unit", "ratelimit_{$sid}_enable", 'eq', 0);

            // Dynamic injection of Service-Specific elements.
            $classname = "\\aiprovider_datacurso\\local\\ratelimit\\{$sid}";

            // Check if a service-specific rate limit configuration class exists.
            if (class_exists($classname)) {
                $ratelimitserviceconfig = new $classname();

                if (method_exists($ratelimitserviceconfig, 'add_form_elements')) {
                    $ratelimitserviceconfig->add_form_elements($mform, $sid);
                }
            }
        }

        $mform->addElement(
            'header',
            'connection_header',
            get_string('connection', 'aiprovider_datacurso')
        );

        $PAGE->requires->js_call_amd('aiprovider_datacurso/hiden_fields', 'init');
    }

    /**
     * Extend the AI action settings form for Datacurso.
     *
     * @param after_ai_action_settings_form_hook $hook
     */
    public static function set_model_form_definition_for_aiprovider_datacurso(after_ai_action_settings_form_hook $hook): void {
        if ($hook->plugin !== 'aiprovider_datacurso') {
            return;
        }

        $mform = $hook->mform;

        if (isset($mform->_elementIndex['modeltemplate'])) {
            $model = $mform->getElementValue('modeltemplate');

            if (is_array($model)) {
                $model = $model[0];
            }
        }
    }
}
