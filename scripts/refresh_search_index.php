#!/usr/bin/env php
<?php
/**
 * CLI: rebuild search_index.json and fingerprint meta (for CI or manual refresh).
 */
$root = dirname(__DIR__);
require_once $root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'omegadex_config.php';
require_once $root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'search_index.php';

OmegadexSearchIndex::buildAndWrite();
$fp = OmegadexSearchIndex::computeDataFingerprint();
$metaDir = dirname(OMEGADEX_SEARCH_META_PATH);
if (!is_dir($metaDir)) {
    @mkdir($metaDir, 0775, true);
}
file_put_contents(
    OMEGADEX_SEARCH_META_PATH,
    json_encode(['fingerprint' => $fp, 'updated_at' => time()], JSON_UNESCAPED_UNICODE)
);
echo "search_index.json refreshed (" . strlen($fp) . " byte fingerprint)\n";
