<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();

return $config
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        '@PHP81Migration' => true,
        'class_definition' => [
            'multi_line_extends_each_single_line' => true,
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'control_structure_braces' => true,
        'declare_parentheses' => true,
        'global_namespace_import' => [
            'import_constants' => false,
            'import_functions' => false,
            'import_classes' => true,
        ],
        'no_multiple_statements_per_line' => true,
        'ordered_interfaces' => true,
        'self_static_accessor' => true,
        'simplified_null_return' => true,
        'simplified_if_return' => true,
        'single_line_empty_body' => false,
        'statement_indentation' => true,
        'trailing_comma_in_multiline' => [
            'elements' => [
                'arrays',
                'match',
                'parameters',
            ],
        ],
        'yoda_style' => false,
    ])
    ->setFinder($finder)
;
