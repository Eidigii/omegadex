#!/usr/bin/env php
<?php
/**
 * Spot-check runtime PHP render vs legacy extensionless HTML (if present).
 * Usage: php scripts/render_parity.php
 */
$root = dirname(__DIR__);
require_once $root . '/lib/omegadex_config.php';
require_once $root . '/lib/renderer/OmegadexRenderer.php';

$samples = [
    'Data/#1 Welcome/index',
    'Data/#5 Dinos/Rex',
    'Data/#17 Links/index',
    'Data/#4 Variants/Resource/Wood',
    'Data/#16 FAQs/index',
];

$fail = 0;
foreach ($samples as $virt) {
    $txt = OmegadexRenderer::extensionlessToTxt($virt);
    if (!$txt) {
        echo "SKIP (no txt): $virt\n";
        continue;
    }
    $php = OmegadexRenderer::renderFromTxtPath($txt);
    $legacyPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $virt);
    if (!is_file($legacyPath)) {
        echo "OK (render only, no legacy file): $virt — " . strlen((string) $php) . " bytes\n";
        continue;
    }
    $legacy = (string) file_get_contents($legacyPath);
    $phpN = str_replace("\r", '', (string) $php);
    $legacyN = str_replace("\r", '', $legacy);
    if ($phpN === $legacyN) {
        echo "MATCH: $virt\n";
    } else {
        echo "DIFF:  $virt (PHP " . strlen($phpN) . " vs legacy " . strlen($legacyN) . " bytes)\n";
        $fail++;
    }
}

exit($fail > 0 ? 1 : 0);
