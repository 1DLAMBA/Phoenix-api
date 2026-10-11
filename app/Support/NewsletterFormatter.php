<?php

namespace App\Support;

/**
 * Turns the plain text an admin types into safe email HTML.
 *
 * Supported: blank line = new paragraph, "## Heading", "- item" bullets,
 * **bold**, and [label](https://link). Everything else is escaped.
 */
class NewsletterFormatter
{
    public static function toHtml(string $text): string
    {
        $blocks = preg_split("/\r?\n\s*\r?\n/", trim($text)) ?: [];
        $html = '';

        foreach ($blocks as $block) {
            $lines = preg_split("/\r?\n/", trim($block)) ?: [];
            if (!$lines || $lines === ['']) {
                continue;
            }

            if (count($lines) === 1 && str_starts_with($lines[0], '## ')) {
                $html .= '<h2 style="margin:28px 0 8px;font-size:18px;line-height:1.3;color:#17224d;">'
                    . self::inline(substr($lines[0], 3)) . '</h2>';
                continue;
            }

            if (self::allBullets($lines)) {
                $html .= '<ul style="margin:0 0 18px;padding-left:20px;">';
                foreach ($lines as $line) {
                    $html .= '<li style="margin:0 0 6px;">' . self::inline(substr(ltrim($line), 2)) . '</li>';
                }
                $html .= '</ul>';
                continue;
            }

            $html .= '<p style="margin:0 0 18px;">' . implode('<br>', array_map([self::class, 'inline'], $lines)) . '</p>';
        }

        return $html;
    }

    private static function allBullets(array $lines): bool
    {
        foreach ($lines as $line) {
            if (!preg_match('/^\s*[-*] \S/', $line)) {
                return false;
            }
        }
        return true;
    }

    private static function inline(string $line): string
    {
        $safe = e($line);
        $safe = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe);
        // Links: e() has already escaped quotes/brackets-safe chars, so only allow http(s) targets.
        return preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
            fn ($m) => '<a href="' . $m[2] . '" style="color:#0055aa;text-decoration:underline;">' . $m[1] . '</a>',
            $safe
        );
    }
}
