<?php

declare(strict_types=1);

if (! function_exists('clean_html')) {
    /**
     * Sanitise HTML content by allowing only safe tags for rich text display.
     * Plain text (no HTML tags) is auto-converted to formatted HTML.
     */
    function clean_html(?string $html): string
    {
        if (! $html) {
            return '';
        }

        // If content has no HTML tags, convert plain text to HTML
        if (strip_tags($html) === $html) {
            return nl2br_structured($html);
        }

        $allowed = '<h3><h4><p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><hr><table><thead><tbody><tr><th><td><span><div>';

        return strip_tags($html, $allowed);
    }
}

if (! function_exists('nl2br_structured')) {
    /**
     * Convert plain text with line breaks into structured HTML.
     * Detects numbered lists, bullet points, and paragraphs.
     */
    function nl2br_structured(?string $text): string
    {
        if (! $text) {
            return '';
        }

        $lines = preg_split('/\r?\n/', $text);
        $html = '';
        $inOl = false;
        $inUl = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                // Close any open list
                if ($inOl) { $html .= '</ol>'; $inOl = false; }
                if ($inUl) { $html .= '</ul>'; $inUl = false; }
                continue;
            }

            // Numbered list: "1. ", "2) ", etc.
            if (preg_match('/^\d+[\.\)]\s+(.+)$/', $trimmed, $m)) {
                if ($inUl) { $html .= '</ul>'; $inUl = false; }
                if (! $inOl) { $html .= '<ol>'; $inOl = true; }
                $html .= '<li>' . e($m[1]) . '</li>';
                continue;
            }

            // Bullet list: "- ", "• ", "* "
            if (preg_match('/^[\-\•\*]\s+(.+)$/', $trimmed, $m)) {
                if ($inOl) { $html .= '</ol>'; $inOl = false; }
                if (! $inUl) { $html .= '<ul>'; $inUl = true; }
                $html .= '<li>' . e($m[1]) . '</li>';
                continue;
            }

            // Close any open list before a paragraph
            if ($inOl) { $html .= '</ol>'; $inOl = false; }
            if ($inUl) { $html .= '</ul>'; $inUl = false; }

            // Bold markers: **text**
            $escaped = e($trimmed);
            $escaped = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped);

            $html .= '<p>' . $escaped . '</p>';
        }

        if ($inOl) { $html .= '</ol>'; }
        if ($inUl) { $html .= '</ul>'; }

        return $html;
    }
}
