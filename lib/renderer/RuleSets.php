<?php
/**
 * Omegadex runtime rulesets.
 */

abstract class OmegadexRuleSetBase
{
    abstract public function extractContentSections(string $content, string $fileName): array;

    public function postProcessHtml(string $htmlContent, string $dir, string $fileName): string
    {
        return $htmlContent;
    }
}

class OmegadexVariantsRuleSet extends OmegadexRuleSetBase
{
    private const VARIANT_CLASSES = [
        'cosmic' => 'cosmic',
        'elemental' => 'elemental',
        'ethereal' => 'ethereal',
        'guardian' => 'guardian',
        'lucky' => 'lucky',
        'mythical' => 'mythical',
        'nature' => 'nature',
        'nightmare' => 'nightmare',
        'rage' => 'rage',
        'resource' => 'resource',
        'summoner' => 'summoner',
        'unstable' => 'unstable',
        'utility' => 'utility',
    ];

    public function extractContentSections(string $content, string $fileName): array
    {
        $base = basename($fileName);
        $isIndexFile = ($base === 'index.txt');

        $preprocessNewlines = static function (string $text): string {
            return preg_replace('/\n(?!\n|\d|-)/', ' ', $text) ?? $text;
        };

        $mainIndexFile = false;
        if ($isIndexFile && strpos($content, 'Groups and Variants') !== false) {
            $mainIndexFile = true;
            $content = $preprocessNewlines(trim($content));
        }

        $parts = preg_split('/-{4,}/', trim($content)) ?: [];
        $headerInfo = $parts[0] ?? '';
        if ($base !== 'index.txt' && $headerInfo !== '') {
            $headerInfo = self::stripVariantLeadingNameFromHeader($headerInfo, $fileName);
        }
        $rest = array_slice($parts, 1);
        $restContent = $rest[0] ?? '';

        if (!$mainIndexFile) {
            $headerInfo = preg_replace('/\n(?!\n)/', ' ', $headerInfo) ?? $headerInfo;
            $headerInfo = preg_replace('/\n(?!\n)/', "\n\n", $headerInfo) ?? $headerInfo;
        }

        $keyValuePattern = '/^(.*?):\s(.*?)$/m';

        if ($isIndexFile) {
            $sections = ['', '', ''];
        } else {
            $sections = ['', '', '', ''];
        }
        $sectionContent = [];

        if ($isIndexFile && count($rest) > 1) {
            $sections[2] = $rest[1];
        }

        $lines = preg_split("/\r\n|\n|\r/", $restContent) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match($keyValuePattern, $line, $m)) {
                $key = $m[1];
                $value = $m[2];
                if (strpos($key, 'Taking Damage') !== false) {
                    $key = str_replace('Taking Damage', '<span class="taking-damage">Taking Damage</span>', $key);
                }
                if (strpos($key, 'Dealing Damage') !== false) {
                    $key = str_replace('Dealing Damage', '<span class="dealing-damage">Dealing Damage</span>', $key);
                }
                $sectionContent[] = '<div class="omegadex-variant-row"><span class="key">' . $key . ':</span> <span class="value">' . $value . '</span></div>';
            } else {
                if (strpos($line, 'Variant Information:') !== false) {
                    $sections[1] = implode('', $sectionContent);
                    $sectionContent = [];
                    $sectionContent[] = '<h2>' . $line . '</h2>';
                } elseif (strpos($line, 'Other Information:') !== false) {
                    $sections[2] = implode('', $sectionContent);
                    $sectionContent = [];
                    $sectionContent[] = '<h2>' . $line . '</h2>';
                } else {
                    if (str_starts_with($line, '<')) {
                        $sectionContent[] = $line;
                    } else {
                        $sectionContent[] = '<p class="omegadex-para">' . $line . '</p>';
                    }
                }
            }
        }

        $sections[0] = self::variantBlockify($headerInfo);
        if ($isIndexFile) {
            $sections[1] = implode('', $sectionContent);
        } else {
            $sections[3] = implode('', $sectionContent);
        }

        return $sections;
    }

    private static function variantBlockify(string $text): string
    {
        $keyValuePattern = '/^(.*?):\s(.*?)$/u';
        $out = [];
        $lines = preg_split("/\r\n|\n|\r/", trim($text)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match($keyValuePattern, $line, $m)) {
                $key = $m[1];
                $value = $m[2];
                if (strpos($key, 'Taking Damage') !== false) {
                    $key = str_replace('Taking Damage', '<span class="taking-damage">Taking Damage</span>', $key);
                }
                if (strpos($key, 'Dealing Damage') !== false) {
                    $key = str_replace('Dealing Damage', '<span class="dealing-damage">Dealing Damage</span>', $key);
                }
                $out[] = '<div class="omegadex-variant-row"><span class="key">' . $key . ':</span> <span class="value">' . $value . '</span></div>';
                continue;
            }
            if (str_starts_with($line, '<')) {
                $out[] = $line;
            } else {
                $out[] = '<p class="omegadex-para">' . $line . '</p>';
            }
        }

        return implode('', $out);
    }

    public function postProcessHtml(string $htmlContent, string $dir, string $fileName): string
    {
        $dirName = basename(str_replace('\\', '/', $dir));
        $key = strtolower($dirName);
        $variantClass = self::VARIANT_CLASSES[$key] ?? 'default';
        $htmlContent = str_replace('VariantClass', $variantClass, $htmlContent);
        if (basename($fileName) === 'index.txt') {
            if ($variantClass === 'default') {
                $htmlContent = str_replace('Index', 'Variants', $htmlContent);
            } else {
                $htmlContent = str_replace('Index', strtoupper($variantClass), $htmlContent);
            }
        }
        return $htmlContent;
    }

    private static function stripVariantLeadingNameFromHeader(string $headerInfo, string $fileName): string
    {
        $stem = pathinfo($fileName, PATHINFO_FILENAME);
        $stem = (string) preg_replace('/^#\d+\s*/u', '', $stem);
        if ($stem === '') {
            return $headerInfo;
        }
        $lines = preg_split("/\r\n|\n|\r/", $headerInfo, 2) ?: [];
        if ($lines === []) {
            return $headerInfo;
        }
        $first = $lines[0];
        $noSuper = static function (string $s): string {
            return (string) preg_replace('/[⁰¹²³⁴⁵⁶⁷⁸⁹₇₈₉₀\u2070-\u2099]+/u', '', $s);
        };
        if (preg_match('/^([^\n:]+):(\s*)/u', $first, $m)) {
            $label = $m[1];
            if ($noSuper($label) === $noSuper($stem) || $label === $stem) {
                $lines[0] = ltrim(substr($first, strlen($m[0])), " \t");
            }
        } elseif (preg_match('/^' . preg_quote($stem, '/') . ':\s*/u', $first)) {
            $lines[0] = ltrim((string) preg_replace('/^' . preg_quote($stem, '/') . ':\s*/u', '', $first, 1), " \t");
        }
        if (isset($lines[1])) {
            return $lines[0] . "\n" . $lines[1];
        }
        return $lines[0];
    }
}

class OmegadexDinosRuleSet extends OmegadexRuleSetBase
{
    public function extractContentSections(string $content, string $fileName): array
    {
        $isIndexFile = basename($fileName) === 'index.txt';
        $keyValuePattern = '/^(.*?):\s(.*?)$/m';
        $speciesName = '';
        if (!$isIndexFile) {
            $speciesName = (string) preg_replace('/^#\d+\s*/u', '', pathinfo($fileName, PATHINFO_FILENAME));
            $speciesName = trim(str_replace('-', ' ', $speciesName));
        }
        $speciesLabel = htmlspecialchars($speciesName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $noUniquesMessage = $speciesLabel !== ''
            ? 'No unique dino types are available for ' . $speciesLabel . ' yet.'
            : 'No unique dino types are available for this species yet.';

        if ($isIndexFile) {
            $sections = [''];
        } else {
            $sections = ['', '', '', '', '', '', '', ''];
        }
        $sectionContent = [];
        $section = 0;
        $foodListOpen = false;
        $additionalInfoOpen = false;
        $additionalInfoLines = [];
        $inUniquesSection = false;
        $uniquesHasContent = false;

        $flushAdditionalInfoParagraph = static function (array &$sectionContent, array &$additionalInfoLines): void {
            if ($additionalInfoLines === []) {
                return;
            }
            $paragraph = trim((string) preg_replace('/\s+/u', ' ', implode(' ', $additionalInfoLines)));
            if ($paragraph !== '') {
                $sectionContent[] = '<p class="omegadex-para">' . $paragraph . '</p>';
            }
            $additionalInfoLines = [];
        };

        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                if ($additionalInfoOpen) {
                    $flushAdditionalInfoParagraph($sectionContent, $additionalInfoLines);
                    continue;
                }
                if ($inUniquesSection && !$uniquesHasContent) {
                    $sectionContent[] = '<p class="omegadex-para">' . $noUniquesMessage . '</p>';
                    $uniquesHasContent = true;
                }
                $inUniquesSection = false;
                if ($foodListOpen) {
                    $sectionContent[] = '</ul>';
                    $foodListOpen = false;
                }
                $sections[$section] = $sectionContent ? implode('', $sectionContent) : '';
                $section++;
                $sectionContent = [];
                continue;
            }
            if (preg_match('/^Additional Information:\s*(.*)$/i', $line, $m)) {
                if ($inUniquesSection && !$uniquesHasContent) {
                    $sectionContent[] = '<p class="omegadex-para">' . $noUniquesMessage . '</p>';
                    $uniquesHasContent = true;
                }
                $inUniquesSection = false;
                if ($foodListOpen) {
                    $sectionContent[] = '</ul>';
                    $foodListOpen = false;
                }
                if ($additionalInfoOpen) {
                    $flushAdditionalInfoParagraph($sectionContent, $additionalInfoLines);
                } else {
                    $sectionContent[] = '<h3>Additional Information:</h3>';
                    $additionalInfoOpen = true;
                }
                if (trim($m[1]) !== '') {
                    $additionalInfoLines[] = trim($m[1]);
                }
                continue;
            }
            if (preg_match($keyValuePattern, $line, $m)) {
                if ($inUniquesSection) {
                    $uniquesHasContent = true;
                }
                if ($additionalInfoOpen) {
                    $flushAdditionalInfoParagraph($sectionContent, $additionalInfoLines);
                    $additionalInfoOpen = false;
                }
                if ($foodListOpen) {
                    $sectionContent[] = '</ul>';
                    $foodListOpen = false;
                }
                $sectionContent[] = '<div class="omegadex-dino-row"><span class="dino-key">' . $m[1] . ':</span> <span class="dino-value">' . $m[2] . '</span></div>';
            } elseif ($isIndexFile) {
                $sectionContent[] = $line;
            } else {
                if (preg_match('/.*:$/', $line)) {
                    if ($inUniquesSection && !preg_match('/^Uniques:\s*$/i', $line) && !$uniquesHasContent) {
                        $sectionContent[] = '<p class="omegadex-para">' . $noUniquesMessage . '</p>';
                        $uniquesHasContent = true;
                    }
                    if (!preg_match('/^Uniques:\s*$/i', $line)) {
                        $inUniquesSection = false;
                    }
                    if ($additionalInfoOpen) {
                        $flushAdditionalInfoParagraph($sectionContent, $additionalInfoLines);
                        $additionalInfoOpen = false;
                    }
                    if ($foodListOpen) {
                        $sectionContent[] = '</ul>';
                        $foodListOpen = false;
                    }
                    $sectionContent[] = '<h3>' . $line . '</h3>';
                    if (strpos($line, 'Food Types') !== false) {
                        $foodListOpen = true;
                        $sectionContent[] = '<ul>';
                    }
                    if (preg_match('/^Uniques:\s*$/i', $line)) {
                        $inUniquesSection = true;
                        $uniquesHasContent = false;
                    }
                    if (stripos($line, 'Additional Information:') !== false) {
                        $additionalInfoOpen = true;
                    }
                } elseif ($additionalInfoOpen) {
                    $additionalInfoLines[] = $line;
                } elseif ($foodListOpen) {
                    $sectionContent[] = '<li>' . ltrim($line, "- \t") . '</li>';
                } else {
                    if ($inUniquesSection) {
                        $uniquesHasContent = true;
                    }
                    $sectionContent[] = '<p class="omegadex-para">' . $line . '</p>';
                }
            }
        }

        if ($additionalInfoOpen) {
            $flushAdditionalInfoParagraph($sectionContent, $additionalInfoLines);
        }
        if ($inUniquesSection && !$uniquesHasContent) {
            $sectionContent[] = '<p class="omegadex-para">' . $noUniquesMessage . '</p>';
        }

        if ($isIndexFile) {
            // Index: many lines are raw text; newlines in output must be preserved.
            $sections[0] = $sectionContent ? implode("\n", $sectionContent) : '';
        } elseif ($foodListOpen) {
            $sectionContent[] = '</ul>';
            $sections[$section] = implode('', $sectionContent);
        } else {
            $sections[$section] = $sectionContent ? implode('', $sectionContent) : '';
        }

        return $sections;
    }

    public function postProcessHtml(string $htmlContent, string $dir, string $fileName): string
    {
        $htmlContent = str_replace('default', '', $htmlContent);
        $dirName = basename(str_replace('\\', '/', $dir));
        $parts = explode(' ', $dirName, 2);
        $dirName = $parts[1] ?? $dirName;

        if (basename($fileName) === 'index.txt') {
            $htmlContent = str_replace('Index', $dirName, $htmlContent);
        }
        return $htmlContent;
    }
}

class OmegadexListRuleSet extends OmegadexRuleSetBase
{
    private static function normalizeHeaderInfoToBlocks(string $headerInfo): string
    {
        $out = [];
        $lines = preg_split("/\r\n|\n|\r/", trim($headerInfo)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '<')) {
                $out[] = $line;
            } else {
                $out[] = '<p class="omegadex-para">' . $line . '</p>';
            }
        }
        return implode('', $out);
    }

    public function extractContentSections(string $content, string $fileName): array
    {
        $isChangelogName = (bool) preg_match('/^\d{2}-\d{2}-\d{2}\.txt$/', $fileName);
        $preprocessNewlines = static function (string $text): string {
            // Keep a break after block HTML so headings/tips/tables are not glued to the next paragraph.
            $text = preg_replace(
                '/(<\/(?:h[1-6]|div|figure|figcaption|table|ul|ol|p|blockquote|pre)>|<hr\s*\/?>)\n(?!\n)/',
                "$1\n\n",
                $text
            ) ?? $text;
            // A ---- rule must keep its own line, else the joined text turns it into a bullet.
            $text = preg_replace('/(^|\n)(-{4,})[ \t]*\n(?!\n)/', "$1$2\n\n", $text) ?? $text;
            // Join wrapped prose, including lines that start with inline tags like <b>.
            $text = preg_replace(
                '/\n(?!\n|\d|-|\||@|<(?:h[1-6]|div|figure|table|ul|ol|p|hr|blockquote|pre)\b)/',
                ' ',
                $text
            ) ?? $text;
            return $text;
        };

        $trimmedContent = trim($content);
        $preprocessed = $isChangelogName ? $trimmedContent : $preprocessNewlines($trimmedContent);
        // ---- on its own line is a visual rule, not a section split (splitting dropped later chapters).
        $restContent = $preprocessed;
        $oneSection = true;
        $headerInfo = '';

        $keyValuePattern = '/^(\b\w+\b\s?){0,3}:\s(.*?)$/m';

        $sections = ['', ''];
        $sectionContent = [];

        $listStart = false;
        $bulletListStart = false;
        $changelogTitleCaptured = false;
        $changelogListOpen = false;
        $changelogParentItemOpen = false;
        $changelogNestedOpen = false;

        $appendProse = static function (string $t, array &$out): void {
            $t = trim($t);
            if ($t === '') {
                return;
            }
            $asHtml = str_starts_with($t, '<')
                && (preg_match('/^<\s*\/?[a-zA-Z]/', $t) || preg_match('/^<!--/', $t) || preg_match('/^<!/i', $t) || preg_match('/^<\?/', $t));
            if ($asHtml) {
                $out[] = $t;
            } else {
                $out[] = '<p class="omegadex-para">' . $t . '</p>';
            }
        };

        $closeChangelogList = static function (array &$out) use (&$changelogListOpen, &$changelogParentItemOpen, &$changelogNestedOpen): void {
            if ($changelogNestedOpen) {
                $out[] = '</ul>';
                $changelogNestedOpen = false;
            }
            if ($changelogParentItemOpen) {
                $out[] = '</li>';
                $changelogParentItemOpen = false;
            }
            if ($changelogListOpen) {
                $out[] = '</ul>';
                $changelogListOpen = false;
            }
        };

        $bulletDepth = 0;
        $liOpen = [];
        $closeBulletsTo = static function (int $target) use (&$sectionContent, &$bulletDepth, &$liOpen, &$bulletListStart): void {
            while ($bulletDepth > $target) {
                if (!empty($liOpen[$bulletDepth])) {
                    $sectionContent[] = '</li>';
                    $liOpen[$bulletDepth] = false;
                }
                $sectionContent[] = '</ul>';
                unset($liOpen[$bulletDepth]);
                $bulletDepth--;
            }
            $bulletListStart = $bulletDepth > 0;
        };
        $addNestedBullet = static function (int $level, string $text) use (&$sectionContent, &$bulletDepth, &$liOpen, &$bulletListStart, $closeBulletsTo): void {
            $level = max(1, min(3, $level));
            $closeBulletsTo($level);
            while ($bulletDepth < $level) {
                $sectionContent[] = '<ul>';
                $bulletDepth++;
                if ($bulletDepth < $level) {
                    // Placeholder parent so -- / --- keep nested styling without a visible parent bullet.
                    $sectionContent[] = '<li class="omegadex-li-spacer">';
                    $liOpen[$bulletDepth] = true;
                } else {
                    $liOpen[$bulletDepth] = false;
                }
            }
            if (!empty($liOpen[$level])) {
                $sectionContent[] = '</li>';
            }
            $sectionContent[] = '<li>' . $text;
            $liOpen[$level] = true;
            $bulletListStart = true;
        };

        $lines = preg_split("/\r\n|\n|\r/", $restContent) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^-{4,}\s*$/', $line)) {
                $closeChangelogList($sectionContent);
                $closeBulletsTo(0);
                if ($listStart) {
                    $sectionContent[] = '</ol>';
                    $listStart = false;
                }
                $sectionContent[] = '<hr>';
                continue;
            }
            $isChangelogHeading = $isChangelogName
                && (bool) preg_match('/^Ark\s+Omega\b.*\bPatch\s+Notes:?\s*$/i', $line);
            if ($isChangelogHeading) {
                $closeChangelogList($sectionContent);
                $sectionContent[] = '<h2 class="omegadex-changelog-title">' . $line . '</h2>';
                $changelogTitleCaptured = true;
                continue;
            }
            if ($isChangelogName && !$changelogTitleCaptured && !preg_match('/^(---|-)\s*/', $line)) {
                $closeChangelogList($sectionContent);
                $sectionContent[] = '<h2 class="omegadex-changelog-title">' . $line . '</h2>';
                $changelogTitleCaptured = true;
                continue;
            }
            if ($isChangelogName && preg_match('/^(---|-)\s*(.+)$/', $line, $m)) {
                $level = ($m[1] === '---') ? 2 : 1;
                $itemText = trim($m[2]);
                if ($itemText === '') {
                    continue;
                }

                if (!$changelogListOpen) {
                    $sectionContent[] = '<ul>';
                    $changelogListOpen = true;
                }

                if ($level === 1) {
                    if ($changelogNestedOpen) {
                        $sectionContent[] = '</ul>';
                        $changelogNestedOpen = false;
                    }
                    if ($changelogParentItemOpen) {
                        $sectionContent[] = '</li>';
                    }
                    $sectionContent[] = '<li>' . $itemText;
                    $changelogParentItemOpen = true;
                } else {
                    if (!$changelogParentItemOpen) {
                        $sectionContent[] = '<li>' . $itemText . '</li>';
                        continue;
                    }
                    if (!$changelogNestedOpen) {
                        $sectionContent[] = '<ul>';
                        $changelogNestedOpen = true;
                    }
                    $sectionContent[] = '<li>' . $itemText . '</li>';
                }
            } elseif (preg_match($keyValuePattern, $line, $m) && !$isChangelogName) {
                $closeChangelogList($sectionContent);
                $closeBulletsTo(0);
                $sectionContent[] = '<div class="omegadex-list-row"><span class="list-key">' . $m[1] . ':</span> <span class="list-value">' . $m[2] . '</span></div>';
            } elseif (preg_match('/^(-{1,3})\s*(.+)$/', $line, $m) && !$isChangelogName) {
                $closeChangelogList($sectionContent);
                $dashCount = strlen($m[1]);
                $itemText = trim($m[2]);
                if ($itemText === '') {
                    continue;
                }
                $level = $dashCount === 1 ? 1 : ($dashCount === 2 ? 2 : 3);
                $addNestedBullet($level, $itemText);
            } elseif (preg_match('/^\d+\.\)/', $line) || preg_match('/^\d+\./', $line)) {
                $closeChangelogList($sectionContent);
                $closeBulletsTo(0);
                $line = preg_replace('/^\d+\.\)?/', '', $line);
                $line = trim($line);
                if (!$listStart) {
                    $sectionContent[] = '<ol>';
                    $listStart = true;
                }
                $sectionContent[] = '<li>' . $line . '</li>';
            } else {
                $closeChangelogList($sectionContent);
                $closeBulletsTo(0);
                if ($listStart) {
                    $sectionContent[] = '</ol>';
                    $listStart = false;
                }
                $appendProse($line, $sectionContent);
            }
        }

        $closeChangelogList($sectionContent);
        $closeBulletsTo(0);
        if ($listStart) {
            $sectionContent[] = '</ol>';
        }

        if (!$oneSection) {
            $sections[0] = self::normalizeHeaderInfoToBlocks($headerInfo);
            $sections[1] = $sectionContent ? implode('', $sectionContent) : '';
        } else {
            $sections[0] = $sectionContent ? implode('', $sectionContent) : '';
        }

        return $sections;
    }

    public function postProcessHtml(string $htmlContent, string $dir, string $fileName): string
    {
        $htmlContent = str_replace('default', '', $htmlContent);
        $dirName = basename(str_replace('\\', '/', $dir));
        $isChangelogDir = (strcasecmp($dirName, '#18 Changelog') === 0);

        if ($isChangelogDir) {
            // Changelog files already include their own heading line in content.
            $htmlContent = preg_replace('/<h1>.*?<\/h1>\s*/is', '', $htmlContent, 1) ?? $htmlContent;
        }

        $parts = explode(' ', $dirName, 2);
        $dirName = $parts[1] ?? $dirName;
        if (basename($fileName) === 'index.txt') {
            $htmlContent = str_replace('Index', $dirName, $htmlContent);
        }
        return $htmlContent;
    }
}

class OmegadexBossesRuleSet extends OmegadexRuleSetBase
{
    public function extractContentSections(string $content, string $fileName): array
    {
        $preprocessNewlines = static function (string $text): string {
            $text = str_replace("\n\n", '[newline]', $text);
            $text = preg_replace('/\n(?!\n)(?!\d{1,3}\.\s)(?!-)/', ' ', $text) ?? $text;
            $text = str_replace('[newline]', "\n\n", $text);
            return $text;
        };

        $preprocessed = $preprocessNewlines(trim($content));
        $parts = preg_split('/-{4,}/', $preprocessed) ?: [];
        $headerInfo = $parts[0] ?? '';
        $rest = array_slice($parts, 1);
        $restContent = $rest[0] ?? '';

        $keyValuePattern = '/^((?:\b\w+\b\s?){1,4}):\s*(.*?)$/m';

        $sections = ['', ''];
        $sectionContent = [];

        if ($restContent === '') {
            $restContent = $headerInfo;
            $oneSection = true;
        } else {
            $oneSection = false;
        }

        $listStart = false;
        $bulletListStart = false;

        $lines = preg_split("/\r\n|\n|\r/", $restContent) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match($keyValuePattern, $line, $m)) {
                if ($listStart) {
                    $sectionContent[] = '</ol>';
                    $listStart = false;
                }
                if ($bulletListStart) {
                    $sectionContent[] = '</ul>';
                    $bulletListStart = false;
                }
                $sectionContent[] = '<h3 class="list-key">' . $m[1] . ':</h3>';
                $value = trim($m[2]);
                if ($value !== '') {
                    $sectionContent[] = '<p class="omegadex-para">' . $value . '</p>';
                }
            } elseif (str_starts_with($line, '- ') || str_starts_with($line, '-')) {
                if (!$bulletListStart) {
                    $sectionContent[] = '<ul>';
                    $bulletListStart = true;
                }
                $sectionContent[] = '<li>' . trim(substr($line, 1)) . '</li>';
            } elseif (preg_match('/^\d+\.\)/', $line) || preg_match('/^\d+\./', $line)) {
                if ($bulletListStart) {
                    $sectionContent[] = '</ul>';
                    $bulletListStart = false;
                }
                $line = preg_replace('/^\d+\.\)?/', '', $line);
                $line = trim($line);
                if (!$listStart) {
                    $sectionContent[] = '<ol>';
                    $listStart = true;
                }
                $sectionContent[] = '<li>' . $line . '</li>';
            } else {
                if ($listStart) {
                    $sectionContent[] = '</ol>';
                    $listStart = false;
                }
                if ($bulletListStart) {
                    $sectionContent[] = '</ul>';
                    $bulletListStart = false;
                }
                if (str_starts_with($line, '<')) {
                    $sectionContent[] = $line;
                } else {
                    $sectionContent[] = '<p class="omegadex-para">' . $line . '</p>';
                }
            }
        }

        if ($listStart) {
            $sectionContent[] = '</ol>';
        }
        if ($bulletListStart) {
            $sectionContent[] = '</ul>';
        }

        if (!$oneSection) {
            $sections[0] = trim($headerInfo);
            $sections[1] = $sectionContent ? implode('', $sectionContent) : '';
        } else {
            $sections[0] = $sectionContent ? implode('', $sectionContent) : '';
        }

        return $sections;
    }

    public function postProcessHtml(string $htmlContent, string $dir, string $fileName): string
    {
        $htmlContent = str_replace('default', '', $htmlContent);
        $dirName = basename(str_replace('\\', '/', $dir));
        $parts = explode(' ', $dirName, 2);
        $dirName = $parts[1] ?? $dirName;
        if (basename($fileName) === 'index.txt') {
            $htmlContent = str_replace('Index', $dirName, $htmlContent);
        }
        return $htmlContent;
    }
}

class OmegadexFaqsRuleSet extends OmegadexRuleSetBase
{
    public function extractContentSections(string $content, string $fileName): array
    {
        // Preserve line breaks before Q:/A: blocks.
        $preprocessNewlines = static function (string $text): string {
            return preg_replace('/\n(?!\n|\d|-|Q:|A:)/', ' ', $text) ?? $text;
        };

        $parseLists = static function (array $lines): string {
            $result = [];
            $listStart = false;
            $bulletListStart = false;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (str_starts_with($line, '- ') || str_starts_with($line, '-')) {
                    if (!$bulletListStart) {
                        $result[] = '<ul>';
                        $bulletListStart = true;
                    }
                    $result[] = '<li>' . trim(substr($line, 1)) . '</li>';
                } elseif (preg_match('/^\d+\.\)/', $line) || preg_match('/^\d+\./', $line)) {
                    if ($bulletListStart) {
                        $result[] = '</ul>';
                        $bulletListStart = false;
                    }
                    $line = preg_replace('/^\d+\.\)?/', '', $line);
                    $line = trim($line);
                    if (!$listStart) {
                        $result[] = '<ol>';
                        $listStart = true;
                    }
                    $result[] = '<li>' . $line . '</li>';
                } else {
                    if ($listStart) {
                        $result[] = '</ol>';
                        $listStart = false;
                    }
                    if ($bulletListStart) {
                        $result[] = '</ul>';
                        $bulletListStart = false;
                    }
                    if (str_starts_with($line, '<')) {
                        $result[] = $line;
                    } else {
                        $result[] = '<p class="omegadex-para">' . $line . '</p>';
                    }
                }
            }
            if ($listStart) {
                $result[] = '</ol>';
            }
            if ($bulletListStart) {
                $result[] = '</ul>';
            }
            return implode('', $result);
        };

        $parseAnswer = static function (array $lines, int $startIndex) use ($parseLists): array {
            $answerLines = [];
            $i = $startIndex;
            $n = count($lines);
            while ($i < $n) {
                $line = trim($lines[$i]);
                if (str_starts_with($line, 'Q:')) {
                    break;
                }
                if (str_starts_with($line, 'A:')) {
                    $answerLines[] = trim(substr($line, 2));
                } else {
                    $answerLines[] = $line;
                }
                $i++;
            }
            return [$parseLists($answerLines), $i - 1];
        };

        $processLine = static function (string $question, string $answer): string {
            return '<div class="omegadex-faq-item">'
                . '<h3 class="omegadex-faq-question">Q: ' . $question . '</h3>'
                . '<div class="omegadex-faq-answer">'
                . '<span class="omegadex-faq-answer-label">A:</span>'
                . '<div class="omegadex-faq-answer-content">' . $answer . '</div>'
                . '</div>'
                . '</div>';
        };

        $preprocessed = $preprocessNewlines(trim($content));
        $lines = preg_split("/\r\n|\n|\r/", $preprocessed) ?: [];

        $sectionContent = [];
        $i = 0;
        $n = count($lines);
        while ($i < $n) {
            $line = trim($lines[$i]);
            if (str_starts_with($line, 'Q:')) {
                $question = trim(substr($line, 2));
                $i++;
                while ($i < $n && trim($lines[$i]) === '') {
                    $i++;
                }
                if ($i < $n && str_starts_with(trim($lines[$i]), 'A:')) {
                    [$answer, $newIndex] = $parseAnswer($lines, $i);
                    $sectionContent[] = $processLine($question, $answer);
                    $i = $newIndex;
                }
            }
            $i++;
        }

        return [implode('', $sectionContent)];
    }

    public function postProcessHtml(string $htmlContent, string $dir, string $fileName): string
    {
        $htmlContent = str_replace('default', '', $htmlContent);
        $dirName = basename(str_replace('\\', '/', $dir));
        $parts = explode(' ', $dirName, 2);
        $dirName = $parts[1] ?? $dirName;
        $htmlContent = str_replace('Index', $dirName, $htmlContent);
        return $htmlContent;
    }
}

class OmegadexDefaultRuleSet extends OmegadexRuleSetBase
{
    public function extractContentSections(string $content, string $fileName): array
    {
        $parts = preg_split('/-{4,}/', trim($content)) ?: [];
        $headerInfo = $parts[0] ?? '';
        $rest = array_slice($parts, 1);
        $restContent = $rest[0] ?? '';

        $headerInfo = preg_replace('/\n(?!\n)/', ' ', $headerInfo) ?? $headerInfo;
        $headerInfo = preg_replace('/\n(?!\n)/', "\n\n", $headerInfo) ?? $headerInfo;

        // Keep legacy behavior: section content built from rest block is not assigned to output sections.

        return [trim($headerInfo)];
    }

    public function postProcessHtml(string $htmlContent, string $dir, string $fileName): string
    {
        $htmlContent = str_replace('default', '', $htmlContent);
        $dirName = basename(str_replace('\\', '/', $dir));
        $parts = explode(' ', $dirName, 2);
        $dirName = $parts[1] ?? $dirName;
        if (basename($fileName) === 'index.txt') {
            $htmlContent = str_replace('Index', $dirName, $htmlContent);
        }
        return $htmlContent;
    }
}

class OmegadexSaddleRuleSet extends OmegadexRuleSetBase
{
    public function extractContentSections(string $content, string $fileName): array
    {
        return [$content];
    }

    public function postProcessHtml(string $htmlContent, string $dir, string $fileName): string
    {
        return $htmlContent;
    }
}

class OmegadexUniqueSaddlesRuleSet extends OmegadexRuleSetBase
{
    private const SADDLES_WEB_DIR = 'assets/saddles/';
    private const SADDLES_DISK_DIR = __DIR__ . '/../../assets/saddles/';

    public function extractContentSections(string $content, string $fileName): array
    {
        if (strtolower(basename($fileName)) === 'index.txt') {
            return [nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))];
        }

        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $speciesDefault = ucwords(str_replace('-', ' ', preg_replace('/^#\d+\s/', '', $base)));
        $speciesDefault = trim($speciesDefault);

        if (trim($content) === '') {
            return [$speciesDefault . ' has no unique saddles yet!'];
        }

        $saddles = $this->parseSaddles($content, $fileName);
        if (!$saddles) {
            return ['*' . $speciesDefault . '* has no unique saddles yet!'];
        }

        $cards = [];
        foreach ($saddles as $s) {
            $cards[] = $this->renderCard($s);
        }
        return [implode("\n", $cards)];
    }

    public function postProcessHtml(string $htmlContent, string $dir, string $fileName): string
    {
        $cssTag = '<link rel="stylesheet" href="assets/saddle-builder.css">';
        if (stripos($htmlContent, 'saddle-builder.css') === false) {
            $htmlContent = $cssTag . "\n" . $htmlContent;
        }
        return $htmlContent;
    }

    private function imageFilenameFromLabel(string $label): string
    {
        if ($label === '') {
            return 'Saddle.png';
        }
        $name = preg_replace('/\s+/', '_', trim($label));
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name);
        $name = preg_replace('/_+/', '_', $name);
        return $name . '.png';
    }

    private function parseSaddles(string $text, string $fileName): array
    {
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $speciesDefault = ucwords(str_replace('-', ' ', preg_replace('/^#\d+\s/', '', $base)));
        $speciesDefault = trim($speciesDefault);

        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $i = 0;
        $n = count($lines);
        $saddles = [];

        $eatBlank = function () use (&$i, $n, $lines): void {
            while ($i < $n && trim($lines[$i]) === '') {
                $i++;
            }
        };

        while ($i < $n) {
            $eatBlank();
            if ($i >= $n) {
                break;
            }

            $name = trim($lines[$i]);
            $i++;

            $species = $speciesDefault;
            if ($i < $n && str_starts_with(strtolower(trim($lines[$i])), 'unique saddle -')) {
                $ln = $lines[$i];
                $after = '';
                if (strpos($ln, '-') !== false) {
                    $after = trim(substr($ln, strpos($ln, '-') + 1));
                }
                $species = $after !== '' ? $after : $speciesDefault;
                $i++;
            }

            $flavorLines = [];
            while ($i < $n && trim($lines[$i]) !== '' && strtolower(trim($lines[$i])) !== 'unique bonuses:') {
                $flavorLines[] = $lines[$i];
                $i++;
            }

            if ($i < $n && strtolower(trim($lines[$i])) === 'unique bonuses:') {
                $i++;
            }

            $bonuses = [];
            while ($i < $n && trim($lines[$i]) !== '') {
                $bonuses[] = trim($lines[$i]);
                $i++;
            }

            if ($name !== '' || $bonuses !== [] || $flavorLines !== []) {
                $saddles[] = [
                    'name' => $name,
                    'species' => $species,
                    'flavor_lines' => $flavorLines,
                    'bonuses' => $bonuses,
                ];
            }

            $eatBlank();
        }

        return $saddles;
    }

    private function renderCard(array $s): string
    {
        $esc = static fn ($x) => htmlspecialchars((string) $x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $speciesLabel = $s['species'] ?? '';
        $imageFile = $this->imageFilenameFromLabel($speciesLabel);
        $fallbackWeb = self::SADDLES_WEB_DIR . 'Saddle.png';
        $iconDiskPath = self::SADDLES_DISK_DIR . $imageFile;
        $iconWeb = is_file($iconDiskPath) ? (self::SADDLES_WEB_DIR . $imageFile) : $fallbackWeb;

        $flavorHtml = implode('<br>', array_map($esc, $s['flavor_lines'] ?? []));
        $bonusesHtml = '';
        foreach ($s['bonuses'] ?? [] as $b) {
            $bonusesHtml .= '<li>' . $esc($b) . '</li>';
        }

        return '<div class="saddleContainer"> <div class="saddle"> <div class="title">' . $esc($s['name'] ?? '') . '</div> <div class="info-section"> <div class="image-container"> <img src="' . $iconWeb . '" alt="Saddle Icon" onerror="this.onerror=null;this.src=\'' . $fallbackWeb . '\';"> </div> <div class="text-container"> <div class="subtitle">Unique Saddle - ' . $esc($speciesLabel) . '</div> <div class="flavor-text">' . $flavorHtml . '</div> <div class="bonuses"> <strong>Unique Bonuses:</strong> <ul>' . $bonusesHtml . '</ul> </div> </div> </div> </div> </div>';
    }
}
