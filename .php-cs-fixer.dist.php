<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor');

$config = new PhpCsFixer\Config();

return $config->setRules([
    '@PER' => true,
    '@PHP82Migration' => true,
])
    ->setFinder($finder)
    ->setUsingCache(false);