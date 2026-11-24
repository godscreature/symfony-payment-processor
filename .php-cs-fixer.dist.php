<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'docker'])
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'declare_strict_types' => true,
        // In final classes and enums static:: means the same as self::.
        'self_static_accessor' => true,
        // No Yoda conditions: write $value === null, not null === $value.
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
    ])
    ->setFinder($finder)
;
