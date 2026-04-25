<?php
/**
 * Plain-text (OCR-friendly) markup → HTML. No angle brackets in source; paste from OCR/notes.
 * See PARITY in repo or inline: ##/###/#### headings, ** ** bold, *italic*, __underline__, [label](url), <<br>>,
 * @ini:color … @end (INI / config blocks), !small! line, Damage Reduction: X !tip:tooltip
 * !INCLUDE:file.html! (resolved in OmegadexRenderer; companion file must live next to the .txt).
 */
class PlainDataMarkup
{
    /** Masks `<<br>>` so a lone `<` check does not skip plain layout (avoids clashing with real HTML). */
    private const LINE_BR_PL = "\u{E000}";

    /**
     * True if the file has no real angle-bracket HTML (OCR line breaks `<<br>>` are ignored for this test).
     */
    public static function isPlainOcrish(string $content): bool
    {
        if ($content === '') {
            return true;
        }

        return !str_contains(str_replace('<<br>>', self::LINE_BR_PL, $content), '<');
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

    public static function apply(string $content): string
    {
        if ($content === '') {
            return $content;
        }

        $s = str_replace('<<br>>', self::LINE_BR_PL, $content);
        if (str_contains($s, '<')) {
            return $content;
        }

        $s = self::expandIniBlocks($s);
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
            $out[] = self::inlineFromPlain($line);
        }
        $s = implode("\n", $out);
        $s = str_replace(self::LINE_BR_PL, '<br />', $s);

        return $s;
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

    private static function expandIniBlocks(string $s): string
    {
        return preg_replace_callback(
            '/^@ini:([a-z0-9_-]+)\R([\s\S]*?)^@end$/m',
            static function (array $m): string {
                $color = trim($m[1]) ?: 'lightblue';
                $body = rtrim($m[2], "\n");
                $colorEsc = htmlspecialchars($color, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $lines = preg_split('/\R/', $body) ?: [];
                $spans = [];
                foreach (array_filter(array_map('trim', $lines)) as $i => $ln) {
                    $enc = htmlspecialchars($ln, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    if ($i === 0) {
                        $spans[] = " <br />\n" . ' <span style="color:' . $colorEsc . '">' . $enc . '</span>';
                    } else {
                        $spans[] = '<br />' . "\n" . ' <span style="color:' . $colorEsc . '">' . $enc . '</span>';
                    }
                }
                if ($spans === []) {
                    return '';
                }

                return implode("\n", $spans);
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
