<?php
namespace LCSNG_EXT\DataSanitizers;

/**
 * Class LCS_OutputEscape
 *
 * Provides methods for escaping data retrieved from the database/app before outputting it to the user.
 *
 * @package LCSNG_EXT\DataSanitizers
 */
class LCS_OutputEscape
{
    /**
     * Escape data retrieved from the database before outputting it to the user.
     *
     * @param mixed $data The data to escape (can be string, numeric, array, or object).
     * @return mixed The escaped data, or null if unsupported type.
     */
    public static function escapeOutput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'escapeOutput'], $data);
        }

        if (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = self::escapeOutput($value);
            }
            return $data;
        }

        if (is_numeric($data)) {
            return $data; // Numeric values are generally safe.
        }

        if (is_string($data)) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

            return $data;
        }

        return null; // Return null for unsupported types.
    }

    /**
     * Escape a URL to ensure it is safe for output.
     *
     * @param string $url The URL to escape.
     * @return string The escaped URL.
     */
    public static function escapeURL($url)
    {
        return filter_var($url, FILTER_SANITIZE_URL);
    }

    /**
     * Escape an HTML attribute to ensure it is safe for output.
     *
     * @param string $attr The attribute value to escape.
     * @return string The escaped attribute value.
     */
    public static function escapeAttr($attr)
    {
        return htmlspecialchars($attr, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Escape HTML content to ensure it is safe for output.
     *
     * @param string $html The HTML content to escape.
     * @return string The escaped HTML content.
     */
    public static function escapeHTML($html)
    {
        return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Escape JavaScript content to ensure it is safe for output.
     *
     * @param string $js The JavaScript content to escape.
     * @return string The escaped JavaScript content.
     */
    public static function escapeJS($js)
    {
        return json_encode($js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

}