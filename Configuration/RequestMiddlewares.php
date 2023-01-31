<?php
/**
 * An array consisting of implementations of middlewares for a middleware stack to be registered
 *
 *  'stackname' => [
 *      'middleware-identifier' => [
 *         'target' => classname or callable
 *         'before/after' => array of dependencies
 *      ]
 *   ]
 */
return [
    'frontend' => [
	'qbus/dlm/dynamic-language-mode' => [
            'target' => \Qbus\DynamicLanguageMode\Middleware\DynamicLanguageMode::class,
            'before' => [
                /* Note: prepare-tsfe-rendering is not a Stable target and may be removed in TYPO3 v10 */
                'typo3/cms-frontend/prepare-tsfe-rendering',
                'typo3/cms-frontend/tsfe',
            ],
            'after' => [
                 'typo3/cms-frontend/page-argument-validator'
            ]
        ]
    ]
];
