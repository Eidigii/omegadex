<?php
require_once __DIR__ . '/PlainDataMarkup.php';
/**
 * @deprecated Replaced by PlainDataMarkup; kept for includes that still require this indirection.
 */
class OmegadexOcrEnrich
{
    public static function maybeEnrich(string $content): string
    {
        if (PlainDataMarkup::isPlainOcrish($content)) {
            return PlainDataMarkup::apply($content);
        }

        return $content;
    }
}
