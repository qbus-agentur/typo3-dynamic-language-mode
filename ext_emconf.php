<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Dynamic language mode (free vs connected)',
    'description' => 'Dynamic language mode (free vs connected)',
    'state' => 'stable',
    'author' => 'Benjamin Franzke',
    'author_email' => 'bfr@qbus.de',
    'author_company' => 'Qbus Internetagentur GmbH',
    'version' => '1.0.0',
    'clearCacheOnLoad' => true,
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-11.5.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Qbus\\DynamicLanguageMode\\' => 'Classes/',
        ],
    ],
];
