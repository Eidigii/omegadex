<?php

require_once __DIR__ . '/RuleSets.php';
require_once __DIR__ . '/PlainDataMarkup.php';
require_once __DIR__ . '/OcrEnrich.php';
require_once dirname(__DIR__) . '/omegadex_config.php';

class OmegadexRenderer
{
    private const TEMPLATE_GLOBAL = '<h1><span class="[OmegaClass]" style="background-color: rgba(211, 211, 211, 0.5); padding: 5px; border-radius: 10px;">[Title]</span></h1>
<div class="omegadex-body" style="background-color: rgba(111, 111, 111, 0.6); padding: 10px; border-radius: 10px; display: inline-block; max-width: 100%;">[Sections]
</div>';

    /** @var array<string, array{class: string, config: string}> */
    private const DIR_CLASS_MAP = [
        '#1 Welcome' => ['class' => OmegadexListRuleSet::class, 'config' => 'list_config.json'],
        '#2 Getting Started' => ['class' => OmegadexListRuleSet::class, 'config' => 'list_config.json'],
        '#3 Progression Guide' => ['class' => OmegadexListRuleSet::class, 'config' => 'list_config.json'],
        '#4 Variants' => ['class' => OmegadexVariantsRuleSet::class, 'config' => 'variants_config.json'],
        '#5 Dinos' => ['class' => OmegadexDinosRuleSet::class, 'config' => 'dinos_config.json'],
        '#6 Equipment' => ['class' => OmegadexListRuleSet::class, 'config' => 'list_config.json'],
        '#5 Unique Saddles' => ['class' => OmegadexUniqueSaddlesRuleSet::class, 'config' => 'default_config.json'],
        '#7 Bosses' => ['class' => OmegadexBossesRuleSet::class, 'config' => 'bosses_config.json'],
        '#8 Items' => ['class' => OmegadexVariantsRuleSet::class, 'config' => 'list_config.json'],
        '#9 Quantum Storage' => ['class' => OmegadexListRuleSet::class, 'config' => 'list_config.json'],
        '#11 Mating' => ['class' => OmegadexListRuleSet::class, 'config' => 'list_config.json'],
        '#12 Paragons' => ['class' => OmegadexListRuleSet::class, 'config' => 'list_config.json'],
        '#13 NPCs' => ['class' => OmegadexListRuleSet::class, 'config' => 'list_config.json'],
        '#14 Egg Chart' => ['class' => OmegadexListRuleSet::class, 'config' => 'list_config.json'],
        '#15 Saddle Creator' => ['class' => OmegadexSaddleRuleSet::class, 'config' => 'default_config.json'],
        '#16 FAQs' => ['class' => OmegadexFaqsRuleSet::class, 'config' => 'faqs_config.json'],
        '#17 Links' => ['class' => OmegadexDefaultRuleSet::class, 'config' => 'default_config.json'],
        '#18 Changelog' => ['class' => OmegadexListRuleSet::class, 'config' => 'list_config.json'],
    ];

    public static function resolveRuleForDir(string $dirAbs): array
    {
        $dirAbs = str_replace('\\', '/', $dirAbs);
        $dataRoot = str_replace('\\', '/', realpath(OMEGADEX_ROOT . '/Data')) ?: '';
        $rel = '';
        if ($dataRoot !== '' && str_starts_with($dirAbs, $dataRoot)) {
            $rel = trim(substr($dirAbs, strlen($dataRoot)), '/');
        }

        $segments = $rel === '' ? [] : explode('/', $rel);
        $ruleClass = OmegadexDefaultRuleSet::class;
        $configName = 'default_config.json';

        foreach (array_reverse($segments) as $seg) {
            if ($seg === '#10 Uniques') {
                break;
            }
            if (isset(self::DIR_CLASS_MAP[$seg])) {
                $ruleClass = self::DIR_CLASS_MAP[$seg]['class'];
                $configName = self::DIR_CLASS_MAP[$seg]['config'];
                break;
            }
        }

        $configPath = OMEGADEX_ROOT . '/lib/renderer/config/' . $configName;
        if (!is_file($configPath)) {
            $configPath = OMEGADEX_ROOT . '/lib/renderer/config/default_config.json';
            $ruleClass = OmegadexDefaultRuleSet::class;
        }

        $config = json_decode((string) file_get_contents($configPath), true);
        if (!is_array($config)) {
            $config = ['num_sections' => 1, 'header_map' => [], 'span_class' => 'default-omega-class'];
        }

        return [$ruleClass, $configPath, $config];
    }

    public static function titleFromBasename(string $baseFilename): string
    {
        $base = preg_replace('/\.txt$/i', '', $baseFilename);
        $title = preg_replace('/^#\d+\s/', '', $base) ?? $base;
        $title = str_replace('-', ' ', $title);
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

    private static function createDynamicTemplate(int $numSections, array $headerMap, string $spanClass): string
    {
        $sectionsTemplate = '';
        for ($i = 0; $i < $numSections; $i++) {
            $headerSize = $headerMap[(string) ($i + 1)] ?? null;
            $sectionHeader = '';
            if ($headerSize !== null && $headerSize !== '') {
                $sectionHeader = '<h' . (int) $headerSize . '>Section ' . ($i + 1) . '</h' . (int) $headerSize . '>';
            }
            $sectionContent = '<span class="section' . ($i + 1) . '" style="">[Section ' . ($i + 1) . ' Content]</span><hr>';
            $sectionsTemplate .= $sectionHeader . "\n" . $sectionContent;
        }

        return str_replace(
            ['[OmegaClass]', '[Sections]'],
            [$spanClass, $sectionsTemplate],
            self::TEMPLATE_GLOBAL
        );
    }

    /**
     * Render a .txt file to runtime HTML fragment output.
     */
    public static function renderFromTxtPath(string $txtAbsPath): ?string
    {
        $txtAbsPath = realpath($txtAbsPath);
        if ($txtAbsPath === false || !is_file($txtAbsPath)) {
            return null;
        }

        $dir = dirname($txtAbsPath);
        $fileName = basename($txtAbsPath);

        if ($fileName === 'additional.txt' || str_starts_with($fileName, '_')) {
            return null;
        }

        [$ruleClass, $configPath, $config] = self::resolveRuleForDir($dir);

        $numSections = $config['num_sections'] ?? 1;
        $headerMap = $config['header_map'] ?? [];
        $spanClass = $config['span_class'] ?? 'default-omega-class';

        if ($ruleClass === OmegadexVariantsRuleSet::class) {
            $numSections = ($fileName === 'index.txt') ? 3 : 4;
        }

        $sidecarMtime = self::sidecarFragmentMtime($dir);
        $cacheKey = self::cacheKey($txtAbsPath, $configPath, $ruleClass, $sidecarMtime);
        $cacheFile = OMEGADEX_RENDER_CACHE_DIR . DIRECTORY_SEPARATOR . $cacheKey . '.html';
        if (
            is_file($cacheFile)
            && filemtime($cacheFile) >= filemtime($txtAbsPath)
            && filemtime($cacheFile) >= filemtime($configPath)
            && filemtime($cacheFile) >= $sidecarMtime
        ) {
            return (string) file_get_contents($cacheFile);
        }

        if (!is_dir(OMEGADEX_RENDER_CACHE_DIR)) {
            @mkdir(OMEGADEX_RENDER_CACHE_DIR, 0775, true);
        }

        $content = (string) file_get_contents($txtAbsPath);
        $content = PlainDataMarkup::resolveInclude($content, $txtAbsPath);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        if (PlainDataMarkup::isPlainOcrish($content)) {
            $content = PlainDataMarkup::apply($content);
        }

        /** @var OmegadexRuleSetBase $rule */
        $rule = new $ruleClass();

        $saddleDir = str_ends_with(str_replace('\\', '/', $dir), '#15 Saddle Creator');
        if ($saddleDir) {
            $final = $rule->postProcessHtml($content, $dir, $fileName);
            file_put_contents($cacheFile, $final);
            return $final;
        }

        $sections = [];
        try {
            $sections = $rule->extractContentSections($content, $fileName);
        } catch (Throwable $e) {
            return null;
        }

        $template = self::createDynamicTemplate((int) $numSections, $headerMap, (string) $spanClass);
        $title = self::titleFromBasename($fileName);
        $htmlContent = str_replace('[Title]', $title, $template);

        foreach ($sections as $i => $sectionContentItem) {
            if ($sectionContentItem !== null && trim((string) $sectionContentItem) !== '') {
                $htmlContent = str_replace('[Section ' . ($i + 1) . ' Content]', (string) $sectionContentItem, $htmlContent);
            }
        }

        $htmlContent = preg_replace(
            '/(?:<h\d>Section \d+<\/h\d>\n)?<span class="section\d+" style="">\[Section \d+ Content\]<\/span><hr>/s',
            '',
            $htmlContent
        ) ?? $htmlContent;
        $htmlContent = preg_replace('/<h\d>Section \d+<\/h\d>/s', '', $htmlContent) ?? $htmlContent;

        try {
            $final = $rule->postProcessHtml($htmlContent, $dir, $fileName);
        } catch (Throwable $e) {
            return null;
        }

        file_put_contents($cacheFile, $final);
        return $final;
    }

    private static function sidecarFragmentMtime(string $dir): int
    {
        $m = 0;
        foreach (glob($dir . DIRECTORY_SEPARATOR . '_*.html') ?: [] as $f) {
            if (is_file($f)) {
                $m = max($m, (int) filemtime($f));
            }
        }
        return $m;
    }

    private static function cacheKey(string $txtAbs, string $configPath, string $ruleClass, int $sidecarMtime = 0): string
    {
        $payload = OMEGADEX_RENDERER_VERSION
            . '|' . $txtAbs . '|' . $ruleClass . '|' . filemtime($txtAbs) . '|' . filemtime($configPath) . '|' . $sidecarMtime;
        return hash('sha256', $payload);
    }

    /**
     * Virtual path like Data/#5 Dinos/Rex (no extension) → absolute .txt path or null.
     */
    public static function virtualPathToTxtAbs(string $virtualDecoded): ?string
    {
        $virtualDecoded = str_replace('\\', '/', $virtualDecoded);
        $virtualDecoded = ltrim($virtualDecoded, '/');
        if (str_contains($virtualDecoded, '..')) {
            return null;
        }
        if (!str_starts_with($virtualDecoded, 'Data/')) {
            return null;
        }
        $full = OMEGADEX_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $virtualDecoded) . '.txt';
        $resolved = realpath($full);
        $data = realpath(OMEGADEX_ROOT . DIRECTORY_SEPARATOR . 'Data');
        if ($resolved === false || $data === false || !str_starts_with($resolved, $data)) {
            return null;
        }
        return $resolved;
    }

    public static function extensionlessToTxt(string $virtualDecoded): ?string
    {
        $virtualDecoded = str_replace('\\', '/', $virtualDecoded);
        $virtualDecoded = ltrim($virtualDecoded, '/');
        if (str_contains($virtualDecoded, '..')) {
            return null;
        }
        if (!str_starts_with($virtualDecoded, 'Data/')) {
            return null;
        }
        $full = OMEGADEX_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $virtualDecoded) . '.txt';
        $resolved = realpath($full);
        $data = realpath(OMEGADEX_ROOT . DIRECTORY_SEPARATOR . 'Data');
        if ($resolved === false || $data === false || !str_starts_with($resolved, $data) || !is_file($resolved)) {
            return null;
        }
        return $resolved;
    }
}
