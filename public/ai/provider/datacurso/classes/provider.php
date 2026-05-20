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

use core_ai\form\action_settings_form;
use Psr\Http\Message\RequestInterface;

/**
 * Datacurso AI provider for Moodle 5.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /**
     * Returns the list of AI actions supported by this provider.
     *
     * @return array
     */
    public static function get_action_list(): array {
        return [
            \core_ai\aiactions\generate_text::class,
            \core_ai\aiactions\generate_image::class,
            \core_ai\aiactions\summarise_text::class,
            \core_ai\aiactions\explain_text::class,
        ];
    }

    /**
     * Add authentication headers to a request.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return \Psr\Http\Message\RequestInterface
     */
    public function add_authentication_headers(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\RequestInterface {
        return $request->withAddedHeader('Authorization', "Bearer {$this->config['licensekey']}");
    }

    /**
     * Returns the form used to configure specific action settings.
     *
     * @param string $action
     * @param array $customdata
     * @return action_settings_form|bool
     */
    #[\Override]
    public static function get_action_settings(string $action, array $customdata = []): action_settings_form|bool {
        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $customdata['actionname'] = $actionname;
        $customdata['action'] = $action;

        if (in_array($actionname, ['generate_text', 'summarise_text', 'explain_text'])) {
            return new form\action_generate_text_form(customdata: $customdata);
        } else if ($actionname === 'generate_image') {
            return false;
        }

        return false;
    }


    /**
     * Checks if this provider has the minimal configuration (license key set).
     *
     * @return bool
     */
    public function is_provider_configured(): bool {
        return !empty($this->config['licensekey']);
    }

    /**
     * Return all available AI services for this provider.
     *
     * @return array
     */
    public static function get_services(): array {
        return [
            ['id' => 'local_coursegen', 'name' => get_string('pluginname_coursegen', 'aiprovider_datacurso')],
            ['id' => 'local_datacurso_ratings', 'name' => get_string('pluginname_datacurso_ratings', 'aiprovider_datacurso')],
            ['id' => 'local_forum_ai', 'name' => get_string('pluginname_forum_ai', 'aiprovider_datacurso')],
            ['id' => 'local_assign_ai', 'name' => get_string('pluginname_assign_ai', 'aiprovider_datacurso')],
            ['id' => 'aiprovider_datacurso', 'name' => get_string('pluginname', 'aiprovider_datacurso')],
            ['id' => 'local_dttutor', 'name' => get_string('pluginname_dttutor', 'aiprovider_datacurso')],
            ['id' => 'local_socialcert', 'name' => get_string('pluginname_socialcert', 'aiprovider_datacurso')],
            ['id' => 'report_lifestory', 'name' => get_string('pluginname_lifestory', 'aiprovider_datacurso')],
            ['id' => 'local_coursedynamicrules', 'name' => get_string('pluginname_smartrules', 'aiprovider_datacurso')],
        ];
    }

    /**
     * Return all available AI actions for this provider.
     *
     * @return array
     */
    public static function get_actions(): array {
        return [
            ['id' => '/provider/chat/completions', 'name' => get_string('generate_text', 'aiprovider_datacurso')],
            ['id' => '/provider/images/generations', 'name' => get_string('generate_image', 'aiprovider_datacurso')],
            ['id' => '/course/execute', 'name' => get_string('generate_creation_course', 'aiprovider_datacurso')],
            ['id' => '/course/start', 'name' => get_string('generate_plan_course', 'aiprovider_datacurso')],
            ['id' => '/resources/create-mod', 'name' => get_string('generate_activitie', 'aiprovider_datacurso')],
            ['id' => '/assign/answer', 'name' => get_string('generate_assign_answer', 'aiprovider_datacurso')],
            ['id' => '/forum/chat', 'name' => get_string('generate_forum_chat', 'aiprovider_datacurso')],
            ['id' => '/rating/general', 'name' => get_string('generate_analysis_general', 'aiprovider_datacurso')],
            ['id' => '/rating/course', 'name' => get_string('generate_analysis_course', 'aiprovider_datacurso')],
            ['id' => '/rating/query', 'name' => get_string('generate_analysis_comments', 'aiprovider_datacurso')],
            ['id' => '/context/upload', 'name' => get_string('read_context_course', 'aiprovider_datacurso')],
            ['id' => '/context/upload-model-context', 'name' => get_string('read_context_course_model', 'aiprovider_datacurso')],
            ['id' => '/resources/create-mod/stream', 'name' => get_string('generate_activitie', 'aiprovider_datacurso')],
            ['id' => '/certificate/answer', 'name' => get_string('generate_certificate_answer', 'aiprovider_datacurso')],
            ['id' => '/story/analysis', 'name' => get_string('generate_analysis_story_student', 'aiprovider_datacurso')],
            ['id' => '/smartrules/create-mod', 'name' => get_string('generate_ai_reinforcement_activity', 'aiprovider_datacurso')],
            ['id' => '/chat/message', 'name' => get_string('generate_chat_message', 'aiprovider_datacurso')],
        ];
    }
}
