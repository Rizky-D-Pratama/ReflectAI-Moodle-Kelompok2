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

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package     aiprovider_datacurso
 * @copyright   Josue <josue@datacurso.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../../config.php');
use aiprovider_datacurso\local\user_token_limit_manager;

// Define context for capability check and page setup.
$context = context_system::instance();
require_login();
require_capability('aiprovider/datacurso:managetokenlimits', $context);

// Fetch URL parameters for filtering and sorting.
$search = optional_param('search', '', PARAM_RAW_TRIMMED);
$sort = optional_param('sort', 'email', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

// Define the base URL for the page (used for sorting and pagination).
$pageurl = new moodle_url('/ai/provider/datacurso/admin/user_token_limits.php', [
    'search' => $search,
    'sort' => $sort,
    'dir' => $dir,
    'page' => $page,
    'perpage' => $perpage,
]);

// Set up page context, title, and layout.
$heading = get_string('link_usertokenlimits', 'aiprovider_datacurso');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($heading);
$PAGE->set_heading($SITE->fullname);


// Handle delete action (needs to run before fetching data).
if ($action === 'delete' && $id) {
    require_sesskey();
    user_token_limit_manager::delete($id);
    redirect(new moodle_url('/ai/provider/datacurso/admin/user_token_limits.php'));
}

// Data validation and fetching logic.
$allowedsorts = ['fullname', 'email', 'tokenlimit', 'tokensused'];
if (!in_array($sort, $allowedsorts, true)) {
    $sort = 'email';
}
$dir = (strtoupper($dir) === 'DESC') ? 'DESC' : 'ASC';

$total = user_token_limit_manager::count($search);
$offset = $page * $perpage;
$records = user_token_limit_manager::get_records($search, $sort, $dir, $offset, $perpage);

// URL to the edit/add page.
$addurl = new moodle_url('/ai/provider/datacurso/admin/user_token_limit_edit.php', [
    'returnurl' => $PAGE->url->out_as_local_url(false),
]);

// Build template data: Headers and Columns setup (for sortable table).
$headers = [
    'fullname' => get_string('fullname'),
    'email' => get_string('email'),
    'tokenlimit' => get_string('usertokenlimit_limit', 'aiprovider_datacurso'),
    'tokensused' => get_string('usertokenlimit_used', 'aiprovider_datacurso'),
    'actions' => get_string('actions', 'moodle'),
];

$columns = [];
foreach (['fullname', 'email', 'tokenlimit', 'tokensused', 'actions'] as $col) {
    $coldata = ['key' => $col, 'label' => $headers[$col]];
    $iscurrent = ($sort === $col);
    if ($iscurrent) {
        $coldata['current'] = true;
        $coldata['dirasc'] = ($dir === 'ASC');
        $coldata['dirdesc'] = ($dir === 'DESC');
    }
    if ($col !== 'actions') {
        $nextdir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
        $coldata['sorturl'] = (new moodle_url($PAGE->url, [
            'sort' => $col,
            'dir' => $nextdir,
            'search' => $search,
            'page' => $page,
            'perpage' => $perpage,
        ]))->out(false);
    }
    $columns[] = $coldata;
}

// Build template data: Rows setup.
$rows = [];
$editbase = new moodle_url('/ai/provider/datacurso/admin/user_token_limit_edit.php');
foreach ($records as $record) {
    $userobject = (object)[
        'firstname' => $record->firstname,
        'lastname' => $record->lastname,
        'firstnamephonetic' => '',
        'lastnamephonetic' => '',
        'middlename' => '',
        'alternatename' => '',
    ];

    $fullname = fullname($userobject);

    $editurl = new moodle_url($editbase, [
        'id' => $record->id,
        'returnurl' => $PAGE->url->out_as_local_url(false),
    ]);
    $rows[] = [
        'id' => (int)$record->id,
        'fullname' => $fullname,
        'email' => $record->email,
        'tokenlimit' => (int)$record->tokenlimit,
        'tokensused' => (int)$record->tokensused,
        'canreset' => $record->tokensused > 0,
        'editurl' => $editurl->out(false),
    ];
}

// Base URL for pagination bar.
$base = new moodle_url('/ai/provider/datacurso/admin/user_token_limits.php', [
    'search' => $search,
    'sort' => $sort,
    'dir' => $dir,
    'perpage' => $perpage,
]);

// Final template data array.
$templatadata = [
    'addurl' => $addurl->out(false),
    'searchaction' => (new moodle_url('/ai/provider/datacurso/admin/user_token_limits.php'))->out(false),
    'searchvalue' => $search,
    'sort' => $sort,
    'dir' => $dir,
    'perpage' => $perpage,
    'columns' => $columns,
    'rows' => $rows,
    'empty' => empty($rows),
    'nothingtodisplay' => get_string('nothingtodisplay'),
    'pagingbar' => $OUTPUT->paging_bar($total, $page, $perpage, $base),
];

// JS: Initialize delete confirmation modal.
$PAGE->requires->js_call_amd('aiprovider_datacurso/user_token_limits', 'init');

// Final page rendering.
echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
echo $OUTPUT->render_from_template('aiprovider_datacurso/user_token_limits', $templatadata);
echo $OUTPUT->footer();
