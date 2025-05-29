<?php
namespace lcsTools\Tools;

/**
 * Class LCS_htmlOps
 *
 * This class provides a set of tools and utilities for handling HTML operations.
 * It is part of the LCS external library and is located in the `Tools` namespace.
 *
 * @package LCS_ext_library\Tools
 */
class LCS_htmlOps 
{
    /**
     * Decode and clean an HTML‐encoded string:
     *  1. Reverses htmlspecialchars (so &lt; becomes “<”).
     *  2. Decodes all HTML entities (e.g. &nbsp;, &#039;).
     *  3. Strips any backslashes added by addslashes()/magic quotes.
     *
     * @param  string $string  The HTML‐encoded input.
     * @return string          The fully decoded, unescaped string.
     *
     * @example
     * ```php
     * $raw = "Hello &lt;strong&gt;world&lt;/strong&gt;\\\"!";
     * echo MyClass::decodeHTML($raw);
     * // Outputs: Hello <strong>world</strong>\"!
     * ```
     */
    public static function decodeHTML( string $string ): string
    {
        return stripslashes(
            html_entity_decode(
                htmlspecialchars_decode($string, ENT_QUOTES),
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }

    /**
     * Strips all HTML tags and their attributes from the content.
     *
     * This function is used to remove all HTML tags from a given content, leaving only plain text.
     * It is useful for situations where you need to extract text from HTML content or ensure that no HTML tags are present.
     *
     * @param string $content The content to strip HTML tags from. This may include various HTML tags and attributes.
     * @return string The content with all HTML tags and attributes removed, leaving only plain text.
     * 
     * @example
     * // Example usage:
     * $content = "<div>Hello <p>world!</p> <a href='#'>link</a>.</div>";
     * $stripped_content = lcs_strip_html($content);
     * echo $stripped_content; // Outputs: Hello world! link.
     */
    public static function stripHTML( string $content ): string {
        // Remove all HTML tags and their attributes
        $strippedContent = preg_replace('/<[^>]*>/', '', $content);

        return $strippedContent;
    }

    /**
     * Sanitize rich HTML content by retaining only a whitelist of safe tags
     * and optionally stripping out <script> blocks or inline event handlers to prevent XSS.
     *
     * This method processes the input `$content` by:
     * 1. Validating the `$allowedTagNames` parameter.
     * 2. Removing `<script>…</script>` blocks if `$allowScripts === false`.
     * 3. Stripping out any `on*` event attributes (e.g., `onclick`, `onload`)
     *    if `$allowIEH === false`.
     * 4. Whitelisting only the specified tags (via PHP’s `strip_tags`).
     *
     * @param  string               $content           The raw HTML content to sanitize.
     * @param  string|string[]|null|false      $allowedTagNames   One tag name or an array of tag names
     *                                                 (e.g. 'div' or ['p','a','strong']). 
     *                                                 Defaults to ['br']. Empty|null|false provision removes all tags.
     * @param  bool                 $allowScripts      If true, keep <script>…</script> blocks; 
     *                                                 if false, remove them. Default: false.
     * @param  bool                 $allowIEH          If true, keep inline event handlers 
     *                                                 (`on*` attributes); if false, strip them. Default: false.
     * @return string                                   The cleaned HTML containing only allowed tags.
     *
     *
     * @example
     * ```php
     * $content = "<div onclick='alert(1)'>Hello <p>world!</p>"
     *          . "<a href='#' onmouseover='bad()'>link</a>"
     *          . "<script>alert('xss');</script></div>";
     *
     * // Remove scripts and inline events:
     * echo MyClass::sanitizeHTML(
     *     $content,
     *     ['div','p','a'],
     *     false,
     *     false
     * );
     * // Outputs: <div>Hello <p>world!</p> <a href='#'>link</a></div>
     *
     * // Keep scripts, strip events:
     * echo MyClass::sanitizeHTML(
     *     $content,
     *     ['div','p','a','script'],
     *     true,
     *     false
     * );
     * // Outputs: <div>Hello <p>world!</p> <a href='#'>link</a><script>alert('xss');</script></div>
     * ```
     */
    public static function sanitizeHTML(
        string $content,
        string|array|null|false $allowedTagNames = 'br',
        bool $allowScripts = false,
        bool $allowIEH = false
    ): string {
        $tagNames = !empty($allowedTagNames) ? (is_array($allowedTagNames)
            ? $allowedTagNames : [$allowedTagNames]) : [];

        // Build the allowed-tags string for strip_tags()
        $allowedTags = '';
        foreach ($tagNames as $tn) {
            $allowedTags .= "<{$tn}>";
        }

        // Reset to null if empty
        if (empty($allowedTags)) {
            $allowedTags = null;
        }

        // Optionally remove entire <script> blocks
        if (! $allowScripts) {
            $content = preg_replace(
                '/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is',
                '',
                $content
            );
        }

        // Optionally remove inline event handlers like onclick="..."
        if (! $allowIEH) {
            $content = preg_replace(
                '/\s*on\w+\s*=\s*"[^"]*"/i',
                '',
                $content
            );
        }

        // Strip all tags except those whitelisted
        return strip_tags($content, $allowedTags);
    }


}