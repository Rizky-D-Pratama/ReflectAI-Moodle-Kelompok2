<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => 'local_ai_reflection\hook\output_hook::before_footer_html_generation',
        'priority' => 100,
    ],
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => 'local_ai_reflection\hook\output_hook::before_standard_head_html_generation',
        'priority' => 100,
    ],
];