<?php return array(
    'root' => array(
        'name' => 'moodle/moodle',
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'reference' => null,
        'type' => 'moodle-core',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'moodle/lms' => array(
            'dev_requirement' => false,
            'provided' => array(
                0 => '5.1',
            ),
        ),
        'moodle/moodle' => array(
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'moodle-core',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'smalot/pdfparser' => array(
            'pretty_version' => 'v2.12.5',
            'version' => '2.12.5.0',
            'reference' => '2cfa0d92bd557875c9f52a75fde0e8392302a354',
            'type' => 'library',
            'install_path' => __DIR__ . '/../smalot/pdfparser',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'symfony/polyfill-mbstring' => array(
            'pretty_version' => 'v1.31.0',
            'version' => '1.31.0.0',
            'reference' => '85181ba99b2345b0ef10ce42ecac37612d9fd341',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/polyfill-mbstring',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
