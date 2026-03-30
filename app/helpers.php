<?php

declare(strict_types=1);

if (! function_exists('clean_html')) {
    /**
     * Sanitise HTML content by allowing only safe tags for rich text display.
     */
    function clean_html(?string $html): string
    {
        if (! $html) {
            return '';
        }

        $allowed = '<h3><h4><p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><hr><table><thead><tbody><tr><th><td><span><div>';

        return strip_tags($html, $allowed);
    }
}
