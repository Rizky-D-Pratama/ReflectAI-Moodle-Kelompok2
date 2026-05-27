<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_ai_reflection',
        get_string('pluginname', 'local_ai_reflection')
    );

    $ADMIN->add('localplugins', $settings);

    // URL Ollama
    $settings->add(new admin_setting_configtext(
        'local_ai_reflection/ollamaurl',
        get_string('setting_ollamaurl', 'local_ai_reflection'),
        get_string('setting_ollamaurl_desc', 'local_ai_reflection'),
        'http://localhost:11434',
        PARAM_URL
    ));

    // Nama model
    $settings->add(new admin_setting_configtext(
        'local_ai_reflection/ollamamodel',
        get_string('setting_ollamamodel', 'local_ai_reflection'),
        get_string('setting_ollamamodel_desc', 'local_ai_reflection'),
        'gemma3',
        PARAM_TEXT
    ));

    // Timeout request (detik)
    $settings->add(new admin_setting_configtext(
        'local_ai_reflection/ollamatimeout',
        get_string('setting_ollamatimeout', 'local_ai_reflection'),
        get_string('setting_ollamatimeout_desc', 'local_ai_reflection'),
        '120',
        PARAM_INT
    ));

    // Batch size gambar
    $settings->add(new admin_setting_configtext(
        'local_ai_reflection/ollamabatchsize',
        get_string('setting_ollamabatchsize', 'local_ai_reflection'),
        get_string('setting_ollamabatchsize_desc', 'local_ai_reflection'),
        '2',
        PARAM_INT
    ));
}