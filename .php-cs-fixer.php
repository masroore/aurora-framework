<?php

$rules = [
    '@PHP83Migration' => true,
    '@PhpCsFixer:risky' => true,
    '@Symfony' => true,
    '@Symfony:risky' => true,
    'cast_spaces' => ['space' => 'none'],
    'concat_space' => ['spacing' => 'one'],
];

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->notPath('vendor')
    ->notName('*.test.php')
    ->notName('*.js')
    ->notName('_ide*.php')
    ->notName('.phpstorm*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules($rules)
    //->setIndent("\t")
    ->setLineEnding("\n")
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php_cs.cache')
    ->setFinder($finder);

return $config;