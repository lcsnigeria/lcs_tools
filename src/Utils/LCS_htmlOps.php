<?php
namespace LCSNG\Tools\Utils;

/**
 * Class LCS_htmlOps
 *
 * A static utility class for HTML string manipulation and sanitization.
 *
 * Provides methods for decoding, stripping, sanitizing, and surgically
 * removing elements from raw HTML strings — designed for use in content
 * pipelines, rich-text editors, and output sanitization layers.
 *
 * Available operations:
 *  - {@see LCS_htmlOps::decodeHTML()}      — Decode HTML-encoded/escaped strings back to raw HTML.
 *  - {@see LCS_htmlOps::stripHTML()}       — Strip all HTML tags, leaving plain text only.
 *  - {@see LCS_htmlOps::sanitizeHTML()}    — Whitelist-based HTML sanitizer with XSS protection.
 *  - {@see LCS_htmlOps::removeElement()}   — Remove element(s) by class, ID, tag, or attribute (supports wildcards).
 *  - {@see LCS_htmlOps::removeElements()}  — Batch version of removeElement() for multiple removals in one call.
 *
 * All methods are static — no instantiation required.
 *
 * @package  LCSNG\Tools\Utils
 * @category HTML Utilities
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

    /**
     * Removes HTML element(s) from an HTML string based on a specified selector type and key.
     *
     * Supports removal by class name, ID, tag name, or any arbitrary HTML attribute.
     * When a matched element is nested inside wrapping <div> elements that contain
     * no other children, the outermost such empty wrapper is removed too, preventing
     * leftover empty containers in the output.
     *
     * Supports wildcard matching using a trailing '*' for 'class', 'id', and any attribute:
     *  - '*' alone           → matches all elements that have the attribute at all.
     *  - 'prefix-*'          → matches elements whose attribute value starts with 'prefix-'.
     *
     * @param string $html        The raw HTML string to process.
     *
     * @param string $removalKey  The type of selector to match against. Accepted values:
     *                            - 'class'    : Match by CSS class name (supports multi-class elements, wildcards)
     *                            - 'id'       : Match by element ID (supports wildcards)
     *                            - 'tag'      : Match by HTML tag name (e.g. 'img', 'div', 'script')
     *                            - any string : Treated as an HTML attribute name (e.g. 'data-type', 'name', 'src'),
     *                                           supports wildcards.
     *                            Defaults to 'class'.
     *
     * @param string $key         The value to match against the specified removalKey. Examples:
     *                            - For 'class'    : 'temp-file-insert-12' or 'temp-file-insert-*' (wildcard prefix)
     *                            - For 'id'       : 'my-element' or 'my-element-*' (wildcard prefix) or '*' (any id)
     *                            - For 'tag'      : 'img'
     *                            - For 'data-type': 'temp-file' or 'temp-*' (wildcard prefix) or '*' (any value)
     *
     * @return string             The processed HTML string with matched element(s) removed.
     *                            Returns the original HTML unchanged if no matches are found.
     *
     * @throws \InvalidArgumentException If $html or $key is an empty string.
     *
     * @example
     *   // Remove by class (exact)
     *   LCS_htmlOps::removeElement($html, 'class', 'temp-file-insert-12');
     *
     *   // Remove by class (wildcard prefix) — removes all temp-file-insert-* elements
     *   LCS_htmlOps::removeElement($html, 'class', 'temp-file-insert-*');
     *
     *   // Remove by ID (exact)
     *   LCS_htmlOps::removeElement($html, 'id', 'my-element');
     *
     *   // Remove by ID (wildcard prefix) — removes my-element-1, my-element-2, my-element-3 etc.
     *   LCS_htmlOps::removeElement($html, 'id', 'my-element-*');
     *
     *   // Remove by ID (wildcard all) — removes every element that has any id
     *   LCS_htmlOps::removeElement($html, 'id', '*');
     *
     *   // Remove by tag name
     *   LCS_htmlOps::removeElement($html, 'tag', 'script');
     *
     *   // Remove by attribute (exact)
     *   LCS_htmlOps::removeElement($html, 'data-type', 'temp-file');
     *   LCS_htmlOps::removeElement($html, 'name', 'my-input');
     *
     *   // Remove by attribute (wildcard prefix) — removes name="temp-a", name="temp-b" etc.
     *   LCS_htmlOps::removeElement($html, 'name', 'temp-*');
     *
     *   // Remove by attribute (wildcard all) — removes every element that has a 'name' attribute
     *   LCS_htmlOps::removeElement($html, 'name', '*');
     */
    public static function removeElement(string $html, string $removalKey = 'class', string $key): string
    {
        if (empty($html)) {
            throw new \InvalidArgumentException('The $html parameter must not be empty.');
        }

        if (empty($key)) {
            throw new \InvalidArgumentException('The $key parameter must not be empty.');
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath      = new \DOMXPath($dom);
        $isWildcard = str_ends_with($key, '*');
        $keyPrefix  = rtrim($key, '*');
        $keyLiteral = self::xpathLiteral($key);

        $query = match($removalKey) {
            'class' => $isWildcard
                // Wildcard: match any class that starts with the prefix
                ? "//*[contains(concat(' ', normalize-space(@class), ' '), ' ') and starts-with(normalize-space(@class), '$keyPrefix')]"
                // Exact: normalize-space trick ensures we don't partial-match within a class list
                : "//*[contains(concat(' ', normalize-space(@class), ' '), concat(' ', $keyLiteral, ' '))]",
            'id'    => $isWildcard
                // '*' alone → any element with an id; 'prefix-*' → id starts with prefix
                ? ($keyPrefix === '' ? "//*[@id]" : "//*[starts-with(@id, '$keyPrefix')]")
                : "//*[@id=$keyLiteral]",
            'tag'   => "//$key",
            default => $isWildcard
                // '*' alone → has the attribute at all; 'prefix-*' → attribute value starts with prefix
                ? ($keyPrefix === '' ? "//*[@$removalKey]" : "//*[starts-with(@$removalKey, '$keyPrefix')]")
                : "//*[@$removalKey=$keyLiteral]",
        };

        $nodes = $xpath->query($query);

        // Buffer nodes before removal to avoid modifying the DOM mid-iteration
        $toRemove = [];
        foreach ($nodes as $node) {
            // Safety: never remove structural elements injected by DOMDocument
            if (in_array($node->nodeName, ['html', 'body'])) {
                continue;
            }

            // Climb up through sole-child <div> wrappers to remove empty containers too
            $target = $node;
            while (
                $target->parentNode &&
                $target->parentNode->nodeName === 'div' &&
                $target->parentNode->childNodes->length === 1
            ) {
                $target = $target->parentNode;
            }

            $toRemove[] = $target;
        }

        // Deduplicate using object hashes to avoid double-removal errors
        $seen = [];
        foreach ($toRemove as $node) {
            $hash = spl_object_hash($node);

            if (isset($seen[$hash])) {
                continue;
            }

            $seen[$hash] = true;
            $node->parentNode?->removeChild($node);
        }

        return $dom->saveHTML();
    }

    /**
     * Removes multiple HTML elements from an HTML string in a single pass.
     *
     * Accepts an array of [$removalKey, $key] pairs and applies each removal
     * sequentially, passing the result of each step into the next. This avoids
     * repeatedly re-parsing the DOM for bulk cleanup operations.
     *
     * Each entry in $removals must be a two-element array matching the signature
     * of {@see LCS_htmlOps::removeElement()}.
     *
     * @param string  $html     The raw HTML string to process.
     * @param array[] $removals An array of [$removalKey, $key] pairs. Each pair:
     *                          - $removalKey : 'class', 'id', 'tag', or any attribute name.
     *                          - $key        : The value to match (supports wildcard '*' for class).
     *
     * @return string           The processed HTML string with all matched elements removed.
     *                          Returns the original HTML unchanged if $removals is empty
     *                          or no matches are found.
     *
     * @throws \InvalidArgumentException If $html is empty or any $key in a pair is empty.
     *
     * @example
     * ```php
     * LCS_htmlOps::removeElements($html, [
     *     ['class', 'temp-file-insert-*'],  // wildcard: all temp inserts
     *     ['tag',   'script'],              // all <script> tags
     *     ['id',    'draft-banner'],        // element with id="draft-banner"
     *     ['data-type', 'temp-file'],       // elements with data-type="temp-file"
     * ]);
     * ```
     */
    public static function removeElements(string $html, array $removals): string
    {
        foreach ($removals as [$removalKey, $key]) {
            $html = self::removeElement($html, $removalKey, $key);
        }

        return $html;
    }

    /**
     * Safely escapes a string value for use inside an XPath expression.
     * Handles values containing single quotes, double quotes, or both
     * by using XPath's concat() trick to avoid injection issues.
     *
     * @param string $value  The raw string to escape.
     * @return string        A valid XPath literal (quoted or concat expression).
     */
    private static function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'$value'";
        }

        if (!str_contains($value, '"')) {
            return "\"$value\"";
        }

        $parts = explode("'", $value);
        return "concat('" . implode("',\"'\",'", $parts) . "')";
    }
}