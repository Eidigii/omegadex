<?php
/**
 * Plain-text (OCR-friendly) markup → HTML. No angle brackets in source; paste from OCR/notes.
 * See PARITY in repo or inline: ##/###/#### headings, ** ** bold, *italic*, __underline__, [label](url), <<br>>,
 * @ini:color … @end (INI / config blocks), @tip:Title … @end, @code / @code:label … @end (raw / literal),
 * !small! line, !img:file.png! caption (caption may instead sit on the next line as `!small! caption`),
 * pipe tables, Damage Reduction: X !tip:tooltip
 * !INCLUDE:file.html! (resolved in OmegadexRenderer; companion file must live next to the .txt).
 */
class PlainDataMarkup
{
    /** Masks `<<br>>` so a lone `<` check does not skip plain layout (avoids clashing with real HTML). */
    private const LINE_BR_PL = "\u{E000}";

    /** Placeholder bookends for extracted @code blocks (body may contain `<`). */
    private const CODE_PL = "\u{E001}";

    /**
     * True if the file has no real angle-bracket HTML (OCR line breaks `<<br>>` are ignored for this test).
     * @code … @end bodies are ignored so literal `<` inside them does not disable markup.
     */
    public static function isPlainOcrish(string $content): bool
    {
        if ($content === '') {
            return true;
        }

        $masked = str_replace(["\r\n", "\r"], "\n", $content);
        $masked = preg_replace('/^@code(?::[^\n]*)?\n[\s\S]*?^@end\s*$/m', '', $masked) ?? $masked;
        return !str_contains(str_replace('<<br>>', self::LINE_BR_PL, $masked), '<');
    }

    public static function resolveInclude(string $content, string $txtAbsPath): string
    {
        $t = trim($content);
        if ($t === '' || !preg_match('/^!INCLUDE:([^!]+)!\s*$/s', $t, $m)) {
            return $content;
        }
        $name = str_replace(['/', '\\'], '', $m[1]);
        $name = basename($name);
        if ($name === '' || $name === '..' || str_contains($name, '..')) {
            return $content;
        }
        $path = dirname($txtAbsPath) . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) {
            return (string) file_get_contents($path);
        }

        return $content;
    }

    public static function apply(string $content, string $txtAbsPath = ''): string
    {
        if ($content === '') {
            return $content;
        }

        $s = str_replace(["\r\n", "\r"], "\n", $content);
        // Pull @code out first so literal `<`, `*`, `-`, etc. never break the rest of the page.
        $codeBlocks = [];
        $s = self::extractCodeBlocks($s, $codeBlocks);
        $s = str_replace('<<br>>', self::LINE_BR_PL, $s);
        if (str_contains($s, '<')) {
            return $content;
        }

        $s = self::expandTipBlocks($s);
        $s = self::expandIniBlocks($s);
        $s = self::expandImages($s, $txtAbsPath);
        $s = self::expandTables($s);
        $s = self::expandSmallLines($s);
        $s = self::expandDamageTipLines($s);

        $out = [];
        $lines = preg_split('/\R/', $s) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/^(\#{2,4})\s+(.+)$/', ltrim($line, " \t"), $m)) {
                $n = strlen($m[1]);
                $tag = $n === 2 ? 'h2' : ($n === 3 ? 'h3' : 'h4');
                $inner = self::inlineFromPlain($m[2]);
                $out[] = '<' . $tag . '>' . $inner . '</' . $tag . '>';
                continue;
            }
            if ($line === '' || str_starts_with(ltrim($line, " \t"), '<')) {
                $out[] = $line;
                continue;
            }
            // Keep code placeholders intact (do not run inline markdown on them).
            if (str_starts_with($line, self::CODE_PL)) {
                $out[] = $line;
                continue;
            }
            $out[] = self::inlineFromPlain($line);
        }
        $s = implode("\n", $out);
        $s = str_replace(self::LINE_BR_PL, '<br />', $s);
        foreach ($codeBlocks as $i => $html) {
            $s = str_replace(self::CODE_PL . $i . self::CODE_PL, $html, $s);
        }

        return $s;
    }

    /**
     * @param array<int, string> $blocks
     */
    private static function extractCodeBlocks(string $s, array &$blocks): string
    {
        $blocks = [];
        return preg_replace_callback(
            '/^@code(?::([^\n]*))?\n([\s\S]*?)^@end\s*$/m',
            static function (array $m) use (&$blocks): string {
                $title = trim($m[1] ?? '');
                $body = $m[2];
                // Drop only the newline that sat immediately before @end.
                $body = preg_replace('/\n$/', '', $body) ?? $body;
                $escaped = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                // Keep the block on one physical line so RuleSets line-joining cannot flatten it.
                $escaped = str_replace("\n", '&#10;', $escaped);
                if ($title !== '') {
                    $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $html = '<div class="omegadex-code-wrap"><div class="omegadex-code-title">' . $titleEsc
                        . '</div><pre class="omegadex-code">' . $escaped . '</pre></div>';
                } else {
                    $html = '<pre class="omegadex-code">' . $escaped . '</pre>';
                }
                $i = count($blocks);
                $blocks[$i] = $html;

                return self::CODE_PL . $i . self::CODE_PL;
            },
            $s
        ) ?? $s;
    }

    private static function inlineFromPlain(string $t): string
    {
        if ($t === '') {
            return $t;
        }
        if (str_contains($t, '<') && str_contains($t, '</')) {
            return $t;
        }
        $o = $t;
        $o = preg_replace_callback(
            '/\*\*([^*]+)\*\*/u',
            static function (array $m): string {
                return '<b>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>';
            },
            $o
        ) ?? $o;
        $o = preg_replace_callback(
            '/__([^_]+)__/u',
            static function (array $m): string {
                return '<u>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</u>';
            },
            $o
        ) ?? $o;
        $o = preg_replace_callback(
            '/(?<!\*)\*([^*]+)\*(?!\*)/u',
            static function (array $m): string {
                return '<i>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</i>';
            },
            $o
        ) ?? $o;
        $o = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/u',
            static function (array $m): string {
                $label = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $url = htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return '<a href="' . $url . '">' . $label . '</a>';
            },
            $o
        ) ?? $o;

        return $o;
    }

    private static function expandTipBlocks(string $s): string
    {
        return preg_replace_callback(
            '/^@tip:([^\n]*)\n([\s\S]*?)^@end\s*$/m',
            static function (array $m): string {
                $title = trim($m[1]);
                if ($title === '') {
                    $title = 'Tip';
                }
                if (!preg_match('/^tip\b/i', $title)) {
                    $title = 'TIP ' . $title;
                } else {
                    $title = preg_replace('/^tip\b/i', 'TIP', $title, 1) ?? $title;
                }
                $titleHtml = self::inlineFromPlain($title);
                $bodyHtml = self::blockFromPlainLines($m[2]);
                if ($bodyHtml === '') {
                    return '<div class="omegadex-tip"><div class="omegadex-tip-title">' . $titleHtml . '</div></div>';
                }

                return '<div class="omegadex-tip"><div class="omegadex-tip-title">' . $titleHtml
                    . '</div><div class="omegadex-tip-body">' . $bodyHtml . '</div></div>';
            },
            $s
        ) ?? $s;
    }

    private static function blockFromPlainLines(string $body): string
    {
        $lines = preg_split('/\R/', rtrim($body, "\n")) ?: [];
        $html = [];
        $para = [];
        $listItems = [];
        $flushPara = static function () use (&$html, &$para): void {
            if ($para === []) {
                return;
            }
            $joined = trim(preg_replace('/\s+/u', ' ', implode(' ', $para)) ?? implode(' ', $para));
            if ($joined !== '') {
                $html[] = '<p class="omegadex-para">' . self::inlineFromPlain($joined) . '</p>';
            }
            $para = [];
        };
        $flushList = static function () use (&$html, &$listItems): void {
            if ($listItems === []) {
                return;
            }
            $html[] = '<ul>';
            foreach ($listItems as $item) {
                $html[] = '<li>' . self::inlineFromPlain($item) . '</li>';
            }
            $html[] = '</ul>';
            $listItems = [];
        };
        $isCodePlaceholder = static function (string $line): bool {
            return str_starts_with($line, self::CODE_PL)
                && (bool) preg_match('/^' . preg_quote(self::CODE_PL, '/') . '\d+' . preg_quote(self::CODE_PL, '/') . '$/u', $line);
        };

        foreach ($lines as $raw) {
            $line = trim($raw);
            if ($line === '') {
                $flushList();
                $flushPara();
                continue;
            }
            // @code is extracted before @tip/@ini; keep the placeholder as its own block.
            if ($isCodePlaceholder($line)) {
                $flushList();
                $flushPara();
                $html[] = $line;
                continue;
            }
            if (preg_match('/^-{1,3}\s*(.+)$/', $line, $m)) {
                $flushPara();
                $listItems[] = trim($m[1]);
                continue;
            }
            $flushList();
            $para[] = $line;
        }
        $flushList();
        $flushPara();

        return implode('', $html);
    }

    private static function expandImages(string $s, string $txtAbsPath): string
    {
        return preg_replace_callback(
            '/^!img:([^!\n]+)!(?:[ \t]+(\S[^\n]*))?[ \t]*(?:\n!small![ \t]+(\S[^\n]*))?$/m',
            static function (array $m) use ($txtAbsPath): string {
                $spec = trim($m[1]);
                $caption = trim($m[3] ?? '');
                if ($caption === '') {
                    $caption = trim($m[2] ?? '');
                    $caption = trim((string) preg_replace('/^!small!\s*/', '', $caption));
                }
                $alt = $caption !== '' ? $caption : pathinfo($spec, PATHINFO_FILENAME);
                $src = htmlspecialchars(
                    self::webSrcForMedia($txtAbsPath, $spec) ?? $spec,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
                $altEsc = htmlspecialchars(trim(strip_tags(str_replace(['*', '_'], '', $alt))), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html = '<figure class="omegadex-figure"><img src="' . $src . '" alt="' . $altEsc . '" />';
                if ($caption !== '') {
                    $html .= '<figcaption class="omegadex-caption">' . self::inlineFromPlain($caption) . '</figcaption>';
                }
                $html .= '</figure>';

                return $html;
            },
            $s
        ) ?? $s;
    }

    private static function webSrcForMedia(string $txtAbsPath, string $spec): ?string
    {
        $spec = str_replace('\\', '/', $spec);
        $spec = ltrim($spec, '/');
        if ($spec === '' || str_contains($spec, '..')) {
            return null;
        }
        $ext = strtolower((string) pathinfo($spec, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            return null;
        }

        $base = $txtAbsPath !== '' ? dirname($txtAbsPath) : '';
        $candidates = [];
        if ($base !== '') {
            $candidates[] = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $spec);
            $candidates[] = $base . DIRECTORY_SEPARATOR . '_images' . DIRECTORY_SEPARATOR . basename($spec);
        }

        $abs = '';
        foreach ($candidates as $path) {
            $resolved = realpath($path);
            if ($resolved !== false && is_file($resolved)) {
                $abs = $resolved;
                break;
            }
        }
        if ($abs === '') {
            if ($base === '') {
                return null;
            }
            $abs = $candidates[0];
        }

        $root = defined('OMEGADEX_ROOT') ? realpath(OMEGADEX_ROOT) : false;
        $rel = '';
        if ($root !== false && str_starts_with(str_replace('\\', '/', $abs), str_replace('\\', '/', $root))) {
            $rel = ltrim(substr(str_replace('\\', '/', $abs), strlen(str_replace('\\', '/', $root))), '/');
        } else {
            $rel = $spec;
        }
        $encoded = implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $rel))));
        $mtime = is_file($abs) ? (string) filemtime($abs) : '0';

        return $encoded . '?v=' . rawurlencode($mtime);
    }

    private static function expandTables(string $s): string
    {
        $lines = preg_split('/\R/', $s) ?: [];
        $out = [];
        $n = count($lines);
        $i = 0;
        while ($i < $n) {
            if (!self::isTableRow($lines[$i])) {
                $out[] = $lines[$i];
                $i++;
                continue;
            }
            $block = [];
            while ($i < $n && self::isTableRow($lines[$i])) {
                $block[] = $lines[$i];
                $i++;
            }
            $out[] = self::tableToHtml($block);
        }

        return implode("\n", $out);
    }

    private static function isTableRow(string $line): bool
    {
        $t = trim($line);
        return $t !== '' && str_starts_with($t, '|') && substr_count($t, '|') >= 2;
    }

    private static function isTableSeparator(string $line): bool
    {
        $t = trim($line);

        return (bool) preg_match('/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?$/', $t);
    }

    private static function splitTableCells(string $line): array
    {
        $t = trim($line);
        if (str_starts_with($t, '|')) {
            $t = substr($t, 1);
        }
        if (str_ends_with($t, '|')) {
            $t = substr($t, 0, -1);
        }
        $parts = explode('|', $t);
        $cells = [];
        foreach ($parts as $part) {
            $cells[] = trim($part);
        }

        return $cells;
    }

    private static function tableToHtml(array $rows): string
    {
        if ($rows === []) {
            return '';
        }
        $header = self::splitTableCells($rows[0]);
        $bodyRows = array_slice($rows, 1);
        if ($bodyRows !== [] && self::isTableSeparator($bodyRows[0])) {
            $bodyRows = array_slice($bodyRows, 1);
        }
        $html = ['<div class="table-container"><table class="omegadex-table"><thead><tr>'];
        foreach ($header as $cell) {
            $html[] = '<th>' . self::inlineFromPlain($cell) . '</th>';
        }
        $html[] = '</tr></thead><tbody>';
        foreach ($bodyRows as $row) {
            if (self::isTableSeparator($row)) {
                continue;
            }
            $cells = self::splitTableCells($row);
            $html[] = '<tr>';
            $count = max(count($header), count($cells));
            for ($c = 0; $c < $count; $c++) {
                $html[] = '<td>' . self::inlineFromPlain($cells[$c] ?? '') . '</td>';
            }
            $html[] = '</tr>';
        }
        $html[] = '</tbody></table></div>';

        return implode('', $html);
    }

    private static function expandIniBlocks(string $s): string
    {
        return preg_replace_callback(
            '/^@ini:([a-z0-9_-]+)\R([\s\S]*?)^@end\s*$/m',
            static function (array $m): string {
                $color = trim($m[1]) ?: 'lightblue';
                $colorEsc = htmlspecialchars($color, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $lines = preg_split('/\R/', rtrim($m[2], "\n")) ?: [];
                $parts = [];
                foreach ($lines as $ln) {
                    $ln = trim($ln);
                    $parts[] = $ln === '' ? '' : self::inlineFromPlain($ln);
                }
                while ($parts !== [] && $parts[0] === '') {
                    array_shift($parts);
                }
                while ($parts !== [] && $parts[count($parts) - 1] === '') {
                    array_pop($parts);
                }
                if ($parts === []) {
                    return '';
                }

                // One block per @ini so a leading <br> cannot double the gap above,
                // and the following paragraph is not glued to inline spans.
                return '<div class="omegadex-ini" style="color:' . $colorEsc . '">'
                    . implode('<br />', $parts)
                    . '</div>';
            },
            $s
        ) ?? $s;
    }

    private static function expandSmallLines(string $s): string
    {
        return preg_replace_callback(
            '/^!small!\s+(.+)$/m',
            static function (array $m): string {
                return '<small><i>&emsp;' . self::inlineFromPlain($m[1]) . '</i></small>';
            },
            $s
        ) ?? $s;
    }

    private static function expandDamageTipLines(string $s): string
    {
        return preg_replace_callback(
            '/^Damage Reduction:\s*(.+?)\s*!\s*tip:\s*(.+)$/m',
            static function (array $m): string {
                $tip = htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $vEsc = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                return 'Damage Reduction: <span class="omegadex-drg" title="' . $tip . '">' . $vEsc . '</span>';
            },
            $s
        ) ?? $s;
    }
}
