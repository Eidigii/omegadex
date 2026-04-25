<?php

require_once __DIR__ . '/omegadex_config.php';

/**
 * Runtime search index builder. JSON schema must match search.php expectations.
 */
class OmegadexSearchIndex
{
    private static bool $ensureRanThisRequest = false;

    public static function ensureFresh(): void
    {
        if (self::$ensureRanThisRequest) {
            return;
        }
        self::$ensureRanThisRequest = true;

        $indexPath = OMEGADEX_ROOT . DIRECTORY_SEPARATOR . 'search_index.json';
        $metaPath = OMEGADEX_SEARCH_META_PATH;
        $metaDir = dirname($metaPath);
        if (!is_dir($metaDir)) {
            @mkdir($metaDir, 0775, true);
        }

        $fp = self::computeDataFingerprint();

        $meta = [];
        if (is_file($metaPath)) {
            $decoded = json_decode((string) file_get_contents($metaPath), true);
            if (is_array($decoded) && isset($decoded['fingerprint'])) {
                $meta = $decoded;
            }
        }

        if (is_file($indexPath) && ($meta['fingerprint'] ?? '') === $fp) {
            return;
        }

        self::buildAndWrite($indexPath);
        file_put_contents($metaPath, json_encode(['fingerprint' => $fp, 'updated_at' => time()], JSON_UNESCAPED_UNICODE));
    }

    public static function computeDataFingerprint(): string
    {
        $dataDir = OMEGADEX_ROOT . DIRECTORY_SEPARATOR . 'Data';
        if (!is_dir($dataDir)) {
            return 'nodata';
        }

        $parts = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($dataDir, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $current): bool {
                    $name = $current->getFilename();
                    return !str_starts_with($name, '.') && !str_starts_with($name, '_');
                }
            )
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $path = $fileInfo->getPathname();
            $rel = str_replace('\\', '/', substr($path, strlen(realpath($dataDir) ?: $dataDir) + 1));
            $parts[] = $rel . '|' . $fileInfo->getMTime() . '|' . $fileInfo->getSize();
        }
        sort($parts, SORT_STRING);
        return hash('sha256', implode("\n", $parts));
    }

    public static function buildAndWrite(?string $outputPath = null): void
    {
        $dataDir = realpath(OMEGADEX_ROOT . DIRECTORY_SEPARATOR . 'Data');
        if ($dataDir === false) {
            return;
        }
        $outputPath = $outputPath ?? (OMEGADEX_ROOT . DIRECTORY_SEPARATOR . 'search_index.json');

        $searchIndex = [];
        $indexedKeys = [];

        $add = static function (array $item) use (&$searchIndex, &$indexedKeys): void {
            $page = str_replace('\\', '/', $item['page_param']);
            $nav = str_replace('\\', '/', $item['nav_path']);
            $type = $item['type'];
            $key = $page . "\0" . $type . "\0" . $nav;
            if (isset($indexedKeys[$key])) {
                return;
            }
            $indexedKeys[$key] = true;
            $item['page_param'] = $page;
            $item['nav_path'] = $nav;
            $searchIndex[] = $item;
        };

        $dirIterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($dataDir, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $current): bool {
                    if (!$current->isDir()) {
                        return false;
                    }
                    $n = $current->getFilename();
                    return !str_starts_with($n, '.') && !str_starts_with($n, '_');
                }
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $dirInfo */
        foreach ($dirIterator as $dirInfo) {
            $currentDir = $dirInfo->getPathname();
            $files = scandir($currentDir);
            if ($files === false) {
                continue;
            }

            $originalDirName = basename($currentDir);
            $currentDirDataRel = '';
            if ($currentDir !== $dataDir) {
                $currentDirDataRel = substr($currentDir, strlen($dataDir) + 1);
                $currentDirDataRel = str_replace('\\', '/', $currentDirDataRel);
            }

            $indexTxt = $currentDir . DIRECTORY_SEPARATOR . 'index.txt';
            if (is_file($indexTxt)) {
                $pageParamVal = $currentDirDataRel;
                $navPathVal = $currentDirDataRel;
                if ($pageParamVal === '' && $originalDirName === 'Data') {
                    $pageParamVal = '#1 Welcome';
                    $navPathVal = '#1 Welcome';
                }
                $title = self::itemTitle($originalDirName, true);
                $searchable = (string) file_get_contents($indexTxt);
                $add([
                    'title' => $title,
                    'page_param' => $pageParamVal,
                    'nav_path' => $navPathVal,
                    'content_source_text' => $searchable,
                    'type' => 'folder_index',
                ]);
            }

            $tableMarker = $currentDir . DIRECTORY_SEPARATOR . 'table.txt';
            $columnFiles = [];
            foreach ($files as $f) {
                if (preg_match('/^c\d+_.*\.txt$/', $f)) {
                    $columnFiles[] = $currentDir . DIRECTORY_SEPARATOR . $f;
                }
            }
            if (is_file($tableMarker) && $columnFiles !== []) {
                $pageParamVal = $currentDirDataRel;
                $navPathVal = $currentDirDataRel;
                $title = self::itemTitle($originalDirName, true);
                $parts = [];
                foreach ($columnFiles as $cf) {
                    $parts[] = (string) file_get_contents($cf);
                }
                $add([
                    'title' => $title,
                    'page_param' => $pageParamVal,
                    'nav_path' => $navPathVal,
                    'content_source_text' => implode("\n", $parts),
                    'type' => 'folder_table',
                ]);
            }

            $imageExt = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            $imagePresent = false;
            foreach ($files as $f) {
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (in_array($ext, $imageExt, true)) {
                    $imagePresent = true;
                    break;
                }
            }
            $additionalTxt = $currentDir . DIRECTORY_SEPARATOR . 'additional.txt';
            if ($imagePresent) {
                $pageParamVal = $currentDirDataRel;
                $navPathVal = $currentDirDataRel;
                $title = self::itemTitle($originalDirName, true);
                $searchable = '';
                if (is_file($additionalTxt)) {
                    $searchable = (string) file_get_contents($additionalTxt);
                    $lines = preg_split("/\r\n|\n|\r/", $searchable) ?: [];
                    $first = trim($lines[0] ?? '');
                    if ($first !== '') {
                        $title = $first;
                    }
                }
                $add([
                    'title' => $title,
                    'page_param' => $pageParamVal,
                    'nav_path' => $navPathVal,
                    'content_source_text' => $searchable,
                    'type' => 'folder_image',
                ]);
            }

            foreach ($files as $fileNameTxt) {
                if (!str_ends_with(strtolower($fileNameTxt), '.txt')) {
                    continue;
                }
                if (in_array($fileNameTxt, ['index.txt', 'additional.txt', 'table.txt'], true)) {
                    continue;
                }
                if (preg_match('/^c\d+_.*\.txt$/', $fileNameTxt)) {
                    continue;
                }
                if (str_starts_with($fileNameTxt, '_')) {
                    continue;
                }

                $fileNameNoExt = pathinfo($fileNameTxt, PATHINFO_FILENAME);
                if ($currentDirDataRel !== '') {
                    $pageParamVal = 'Data/' . $currentDirDataRel . '/' . $fileNameNoExt;
                    $navPathVal = $currentDirDataRel . '/' . $fileNameNoExt;
                } else {
                    $pageParamVal = 'Data/' . $fileNameNoExt;
                    $navPathVal = $fileNameNoExt;
                }
                $title = self::itemTitle($fileNameTxt, false);
                $pathAbs = $currentDir . DIRECTORY_SEPARATOR . $fileNameTxt;
                $searchable = (string) file_get_contents($pathAbs);
                $add([
                    'title' => $title,
                    'page_param' => $pageParamVal,
                    'nav_path' => $navPathVal,
                    'content_source_text' => $searchable,
                    'type' => 'single_file',
                ]);
            }
        }

        file_put_contents(
            $outputPath,
            json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    private static function itemTitle(string $itemPathOrName, bool $isDirectory): string
    {
        if ($isDirectory) {
            $baseName = $itemPathOrName;
        } else {
            $baseName = pathinfo($itemPathOrName, PATHINFO_FILENAME);
        }
        $title = preg_replace('/^#\d+\s*/', '', $baseName) ?? $baseName;
        $title = str_replace(['-', '_'], ' ', $title);
        return self::normalizeTitleCase($title);
    }

    private static function normalizeTitleCase(string $s): string
    {
        $s = strtolower($s);
        return preg_replace_callback(
            '/\b[a-z]/',
            static fn (array $m) => strtoupper($m[0]),
            $s
        ) ?? $s;
    }
}
