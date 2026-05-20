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

use context_system;
use context_user;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use aiprovider_datacurso\privacy\provider;
use stdClass;

/**
 * Privacy provider tests for Datacurso AI provider.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2025 Wilber Narvaez <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class privacy_provider_test extends provider_testcase {
    /**
     * Test setup.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * get_contexts_for_userid returns user context only when data exists.
     *
     * @covers \aiprovider_datacurso\privacy\provider::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid($user->id));

        // Create data.
        $this->create_usage_record($user->id, 'local_coursegen');

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);

        $usercontext = context_user::instance($user->id);
        $this->assertEquals($usercontext->id, $contextlist->get_contextids()[0]);
    }

    /**
     * Only users with user context are fetched.
     *
     * @covers \aiprovider_datacurso\privacy\provider::get_users_in_context
     */
    public function test_get_users_in_context(): void {
        $component = 'aiprovider_datacurso';
        $user = $this->getDataGenerator()->create_user();
        $usercontext = context_user::instance($user->id);

        $userlist = new userlist($usercontext, $component);
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);

        // Create data.
        $this->create_usage_record($user->id, 'local_coursegen');

        provider::get_users_in_context($userlist);
        $this->assertCount(1, $userlist);
        $this->assertEquals([$user->id], $userlist->get_userids());

        // System context should not return any users.
        $userlist = new userlist(context_system::instance(), $component);
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);
    }

    /**
     * User data is exported correctly.
     *
     * @covers \aiprovider_datacurso\privacy\provider::export_user_data
     */
    public function test_export_user_data(): void {
        $user = $this->getDataGenerator()->create_user();
        $record = $this->create_usage_record($user->id, 'local_coursegen');

        $usercontext = context_user::instance($user->id);
        $writer = writer::with_context($usercontext);
        $this->assertFalse($writer->has_any_data());

        $approvedlist = new approved_contextlist($user, 'aiprovider_datacurso', [$usercontext->id]);
        provider::export_user_data($approvedlist);

        $data = $writer->get_data([
            get_string('privacy:metadata:aiprovider_datacurso', 'aiprovider_datacurso'),
            get_string('privacy:metadata:aiprovider_datacurso_rlimit', 'aiprovider_datacurso'),
        ]);

        // Compare expected fields (cast scalar for consistency).
        $expected = (array) $record;
        foreach ($expected as $k => $v) {
            if (in_array($k, ['id'])) {
                // Skip auto id.
                continue;
            }
            $this->assertEquals((string)$v, isset($data->$k) ? (string)$data->$k : null);
        }
    }

    /**
     * Delete all users in a user context removes records for that user.
     *
     * @covers \aiprovider_datacurso\privacy\provider::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $rec1 = $this->create_usage_record($user1->id, 'local_coursegen');
        $user1ctx = context_user::instance($user1->id);

        $user2 = $this->getDataGenerator()->create_user();
        $rec2 = $this->create_usage_record($user2->id, 'local_coursegen');

        $this->assertCount(1, $DB->get_records('aiprovider_datacurso_rlimit', ['userid' => $user1->id]));
        $this->assertCount(1, $DB->get_records('aiprovider_datacurso_rlimit', ['userid' => $user2->id]));

        provider::delete_data_for_all_users_in_context($user1ctx);

        $this->assertCount(0, $DB->get_records('aiprovider_datacurso_rlimit', ['userid' => $user1->id]));
        $this->assertCount(1, $DB->get_records('aiprovider_datacurso_rlimit', ['userid' => $user2->id]));
    }

    /**
     * Delete data for user via approved context list.
     *
     * @covers \aiprovider_datacurso\privacy\provider::delete_data_for_user
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $rec1 = $this->create_usage_record($user1->id, 'local_coursegen');
        $user1ctx = context_user::instance($user1->id);

        $user2 = $this->getDataGenerator()->create_user();
        $rec2 = $this->create_usage_record($user2->id, 'local_coursegen');

        $this->assertCount(1, $DB->get_records('aiprovider_datacurso_rlimit', ['userid' => $user1->id]));
        $this->assertCount(1, $DB->get_records('aiprovider_datacurso_rlimit', ['userid' => $user2->id]));

        $approved = new approved_contextlist($user1, 'aiprovider_datacurso', [$user1ctx->id]);
        provider::delete_data_for_user($approved);

        $this->assertCount(0, $DB->get_records('aiprovider_datacurso_rlimit', ['userid' => $user1->id]));
        $this->assertCount(1, $DB->get_records('aiprovider_datacurso_rlimit', ['userid' => $user2->id]));
    }

    /**
     * Delete data for users in an approved userlist in a given context.
     *
     * @covers \aiprovider_datacurso\privacy\provider::delete_data_for_users
     */
    public function test_delete_data_for_users(): void {
        $component = 'aiprovider_datacurso';

        $user1 = $this->getDataGenerator()->create_user();
        $this->create_usage_record($user1->id, 'local_coursegen');
        $ctx1 = context_user::instance($user1->id);

        $user2 = $this->getDataGenerator()->create_user();
        $this->create_usage_record($user2->id, 'local_coursegen');
        $ctx2 = context_user::instance($user2->id);

        $userlist1 = new userlist($ctx1, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(1, $userlist1);
        $this->assertEquals([$user1->id], $userlist1->get_userids());

        $userlist2 = new userlist($ctx2, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);
        $this->assertEquals([$user2->id], $userlist2->get_userids());

        $approved = new approved_userlist($ctx1, $component, $userlist1->get_userids());
        provider::delete_data_for_users($approved);

        // Re-fetch for context1: should now be empty.
        $userlist1 = new userlist($ctx1, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(0, $userlist1);

        // System context should have no effect.
        $approved = new approved_userlist(context_system::instance(), $component, $userlist2->get_userids());
        provider::delete_data_for_users($approved);

        // User 2 still present in their own context.
        $userlist2 = new userlist($ctx2, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);
    }

    /**
     * Helper to create a usage record in aiprovider_datacurso_rlimit for a user.
     *
     * @param int $userid
     * @param string $serviceid
     * @return stdClass
     */
    private function create_usage_record(int $userid, string $serviceid): stdClass {
        global $DB;
        $now = time();
        $rec = (object) [
            'userid' => $userid,
            'serviceid' => $serviceid,
            'windowstart' => $now - 3600,
            'tokensused' => 5,
            'lastsync' => $now - 60,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $rec->id = $DB->insert_record('aiprovider_datacurso_rlimit', $rec);
        return $rec;
    }
}
