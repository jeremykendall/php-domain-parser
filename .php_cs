<?php

require_once __DIR__ . '/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
    ->in('src')
    ->in('tests');

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'yoda_style' => false,
        'is_null' => true,
        'modernize_types_casting' => true,
        'no_alias_functions' => true,
        'ordered_imports' => true,
        'phpdoc_order' => true,
        'pre_increment' => false,
        'psr4' => true,
        'random_api_migration' => true,
        'single_blank_line_before_namespace' => false,
    ])
    ->setFinder($finder);
