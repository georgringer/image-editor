<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Image Editor for the backend',
    'description' => 'Edit images directly in the TYPO3 file list using the Filerobot Image Editor.',
    'category' => 'be',
    'author' => 'Georg Ringer',
    'author_email' => 'mail@ringer.it',
    'state' => 'beta',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.4.99',
            'typo3' => '13.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'GeorgRinger\\ImageEditor\\' => 'Classes/',
        ],
    ],
];
