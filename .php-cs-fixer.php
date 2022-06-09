<?php

$header = <<<EOF
This file is part of the Monolog package.

(c) Jordi Boggiano <j.boggiano@seld.be>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

$finder = PhpCsFixer\Finder::create()
    ->files()
    ->name('*.php')
    ->exclude('Fixtures')
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
;

$config = new PhpCsFixer\Config();
return $config->setRules(array(
        '@PSR2' => true,
        // some rules disabled as long as 1.x branch is maintained
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => [
            'default' => null,
        ],
        'blank_line_before_statement' => ['statements' => ['continue', 'declare', 'return', 'throw', 'try']],
        'cast_spaces' => ['space' => 'single'],
        'header_comment' => ['header' => $header],
        'include' => true,
        'class_attributes_separation' => array('elements' => array('method' => 'one', 'trait_import' => 'none')),
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => true,
        'no_leading_import_slash' => true,
        'no_leading_namespace_whitespace' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'no_trailing_comma_in_singleline_array' => true,
        'no_unused_imports' => true,
        'no_whitespace_in_blank_line' => true,
        'object_operator_without_whitespace' => true,
        'phpdoc_align' => true,
        'phpdoc_indent' => true,
        'phpdoc_no_access' => true,
        'phpdoc_no_package' => true,
        'phpdoc_order' => true,
        //'phpdoc_scalar' => true,
        'phpdoc_trim' => true,
        //'phpdoc_types' => true,
        'psr_autoloading' => ['dir' => 'src'],
        'declare_strict_types' => true,
        'single_blank_line_before_namespace' => true,
        'standardize_not_equals' => true,
        'ternary_operator_spaces' => true,
        'trailing_comma_in_multiline' => true,
    ))
    ->setUsingCache(true)
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
