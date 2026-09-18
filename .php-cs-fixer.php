<?php

declare(strict_types=1);

/*
 * PHP CS Fixer config for nqphp.
 *
 * Phase 1 baseline rules — chosen to match PSR-12 + Symfony Coding
 * Standards + a few framework-specific tweaks. Project-specific
 * rules can override these.
 *
 * Run via:
 *   composer cs:check    # dry-run + diff (CI-friendly)
 *   composer cs:fix      # apply (dev only)
 *
 * Ruleset notes:
 *   - PSR-12 baseline (PSR1 + PSR2 rules).
 *   - declare(strict_types=1) enforced at the top of every file.
 *   - ordered_class_elements = null; we don't enforce method ordering.
 *   - No rules on doc-comment style (Symfony normalizes but is
 *     opinionated; keeping it light for Phase 1 lets us land the
 *     tool first, tighten style second).
 */

$finder = (new PhpCsFixer\Finder())
    ->in('src')
    ->in('tests')
    ->in('bin');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PSR1' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'no_trailing_whitespace' => true,
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => true,
    ])
    ->setFinder($finder);
