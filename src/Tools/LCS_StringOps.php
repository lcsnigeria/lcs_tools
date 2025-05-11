<?php
namespace LCSNG_EXT\Tools;

class LCS_StringOps {
    /**
     * Capitalizes each word in a string (e.g., "united states" → "United States").
     *
     * @param string $str The input string to capitalize.
     * @return string|bool The capitalized string or false if invalid input.
     */
    public static function capitalizeWords( string $str ):string|bool {
        if (!$str || !preg_match('/^[A-Za-z\s]+$/', $str)) return false;
        return ucwords(strtolower($str));
    }

    /**
     * Truncates a string to a specified maximum length, appending a custom ellipsis if truncated.
     *
     * @param string $str The input string to truncate.
     * @param int $maxLength The maximum allowed length of the result (including ellipsis).
     * @param string $ellipsis The string to append when truncation occurs.
     * @return string The original string if shorter than maxLength, otherwise a truncated version.
     */
    public static function truncateString( string $str, int $maxLength, string $ellipsis = '…' ):string {
        if (!is_string($str) || !is_numeric($maxLength) || $maxLength <= 0) {
            return '';
        }
        if (strlen($str) <= $maxLength) return $str;
        $sliceLength = $maxLength - strlen($ellipsis);
        return $sliceLength > 0 
            ? substr($str, 0, $sliceLength) . $ellipsis 
            : substr($ellipsis, 0, $maxLength);
    }

    /**
     * Converts a string into a URL-friendly slug.
     *
     * @param string $str The input string.
     * @param string $separator The character(s) to use between words in the slug.
     * @return string The slugified version of the string.
     */
    public static function slugify( string $str, string $separator = '-' ):string {
        if (!is_string($str)) return '';
        $str = strtolower(trim($str));
        $str = preg_replace('/[^\w\s.-]/', '', $str);
        $str = preg_replace('/[\s_.-]+/', $separator, $str);
        return trim($str, $separator);
    }

    /**
     * De-slugify a string into readable text, applying flexible casing and optional substring removal.
     *
     * - Replaces all occurrences of `$separator` with spaces (if present).
     * - Applies one of several casing styles (UTF-8–safe).
     * - Removes the first occurrence of `$strip` (case-insensitive), if provided.
     * - Trims leading and trailing whitespace.
     *
     * Available casing styles:
     *   • `uppercase`   — ALL UPPERCASE  
     *   • `lowercase`   — all lowercase  
     *   • `capitalize`  — Capitalize Each Word  
     *   • `titlecase`   — Capitalize first character only  
     *   • `camelcase`   — firstWord lower, SubsequentWords capitalized, no spaces  
     *   • `pascalcase`  — EveryWord Capitalized, no spaces  
     *
     * @param  string  $str         The input slug (e.g. "fixed-price" or "i_love_you_babe").
     * @param  string  $separator   The delimiter used in the slug (default `"-"`).
     * @param  string  $casing      One of: `uppercase`, `lowercase`, `capitalize`,  
     *                              `titlecase`, `camelcase`, `pascalcase` (default `"titlecase"`).
     * @param  string  $strip       Optional substring to remove (all matches, case-insensitive).
     * @return string               The de-slugified, cased, and stripped result.
     *
     * Examples:
     * ```php
     * deSlugify('fixed-price')                         // → "Fixed price"
     *
     * deSlugify('fixed_price', '_', 'uppercase', 'price') // → "FIXED"
     *
     * deSlugify('fixed-price', '-', 'capitalize', 'price') // → "Fixed"
     *
     * deSlugify('fixed-price', '-', 'titlecase', '')       // → "Fixed price"
     *
     * deSlugify('i_love_you_babe', '_', 'camelcase', 'BABE') // → "iLoveYou"
     *
     * deSlugify('no_time_to_DIE', '_', 'pascalcase', 'die') // → "NoTimeTo"
     * ```
     */
    public static function deSlugify(
        string $str,
        string $separator = '-',
        string $casing = 'titlecase',
        string $strip = ''
    ): string {
        // 1) Replace separator with spaces if present
        $text = strpos($str, $separator) !== false
            ? str_replace($separator, ' ', $str)
            : $str;

        // Normalize to lowercase for consistent transforms
        $lower = mb_strtolower($text, 'UTF-8');

        // 2) Apply casing (note: 'capitalize' ≃ title-case, 'titlecase' ≃ sentence-case)
        switch (strtolower($casing)) {
            case 'uppercase':
                $text = mb_strtoupper($text, 'UTF-8');
                break;

            case 'lowercase':
                $text = $lower;
                break;

            case 'capitalize':
                // Capitalize the first letter of each word
                $text = mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
                break;

            case 'titlecase':
                // Capitalize only the very first character
                $first = mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8');
                $text  = $first . mb_substr($lower, 1, null, 'UTF-8');
                break;

            case 'camelcase':
                $words = explode(' ', $lower);
                $first = array_shift($words);
                $camel = $first;
                foreach ($words as $w) {
                    $camel .= mb_convert_case($w, MB_CASE_TITLE, 'UTF-8');
                }
                $text = $camel;
                break;

            case 'pascalcase':
                $words = explode(' ', $lower);
                $pascal = '';
                foreach ($words as $w) {
                    $pascal .= mb_convert_case($w, MB_CASE_TITLE, 'UTF-8');
                }
                $text = $pascal;
                break;

            default:
                // Unknown casing: leave text as-is
                break;
        }

        // 3) Strip first occurrence of $strip (case-insensitive)
        if ($strip !== '') {
            $pattern = '/' . preg_quote($strip, '/') . '/i';
            $text = preg_replace($pattern, '', $text);
        }

        // 4) Trim whitespace
        return self::removeExtraSpaces(trim($text));
    }

    /**
     * Reverses the characters in a string.
     *
     * @param string $str The input string.
     * @return string The reversed string.
     */
    public static function reverseString( string $str ):string {
        return strrev($str);
    }

    /**
     * Removes extra spaces from a string.
     *
     * @param string $str The input string.
     * @return string A trimmed and cleaned-up string.
     */
    public static function removeExtraSpaces( string $str ):string {
        return trim(preg_replace('/\s+/', ' ', $str));
    }

    /**
     * Counts the number of words in a string.
     *
     * @param string $str The input string.
     * @return int The word count.
     */
    public static function countWords( string $str ):int {
        if (!is_string($str)) return 0;
        $str = trim($str);
        return $str ? str_word_count($str) : 0;
    }

    /**
     * Counts the total number of characters in a string, including spaces.
     *
     * @param string $str The input string.
     * @return int The total character count.
     */
    public static function countCharacters( string $str ):int {
        if (!is_string($str)) return 0;
        return strlen($str);
    }

    /**
     * Counts the number of vowels in a string.
     *
     * @param string $str The input string.
     * @return int The total vowel count.
     */
    public static function countVowels( string $str ):int {
        if (!is_string($str)) return 0;
        return preg_match_all('/[aeiou]/i', $str, $matches) ? count($matches[0]) : 0;
    }

    /**
     * Counts the number of consonants in a string.
     *
     * @param string $str The input string.
     * @return int The total consonant count.
     */
    public static function countConsonants( string $str ):int {
        if (!is_string($str)) return 0;
        return preg_match_all('/[b-df-hj-np-tv-z]/i', $str, $matches) ? count($matches[0]) : 0;
    }

    /**
     * Validates whether a string is a well-formed email address.
     *
     * @param string $str The email string to validate.
     * @return bool True if valid email; otherwise false.
     */
    public static function isEmail( string $str ):bool {
        if (!is_string($str)) return false;
        return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Checks if a string contains only alphabetic characters.
     *
     * @param string $str The string to test.
     * @return bool True if only letters; otherwise false.
     */
    public static function isAlpha( string $str ):bool {
        if (!is_string($str)) return false;
        return preg_match('/^[A-Za-z]+$/', $str) === 1;
    }

    /**
     * Removes a specified character from a string.
     *
     * @param string $character The character to remove.
     * @param string $string The string from which to remove the character.
     * @param int|null $position Optional zero-based index of occurrence to remove.
     * @return string The modified string.
     */
    public static function stripCharacter( string $character, string $string, int|null $position = null):string {
        if (!is_string($string) || !is_string($character)) {
            return $string;
        }

        if (is_numeric($position)) {
            $count = 0;
            $chars = str_split($string);
            foreach ($chars as $i => $char) {
                if ($char === $character) {
                    if ($count === $position) {
                        unset($chars[$i]);
                        break;
                    }
                    $count++;
                }
            }
            return implode('', $chars);
        }

        return str_replace($character, '', $string);
    }

    /**
     * Format a numeric amount as a localized currency string.
     *
     * - Supports optional currency symbol and decimal precision.
     * - Adds thousands separators (comma) and decimal point (dot).
     * - Optionally trims trailing zeros after decimal point.
     *
     * @param  float|int  $amount       The numeric amount to format.
     * @param  string     $symbol       Currency symbol to prefix (default: `"₦"`).
     * @param  int|null   $precision    Decimal places (null = auto-trim, default: `2`).
     * @return string                   The formatted amount string (e.g. "₦12,500.50").
     *
     * Examples:
     * ```php
     * formatAmount(12500)                   // → "₦12,500.00"
     *
     * formatAmount(12500.5, '₦', null)      // → "₦12,500.5"
     *
     * formatAmount(12500.00, '$', null)     // → "$12,500"
     *
     * formatAmount(12500.007, '€', 2)       // → "€12,500.01"
     * ```
     */
    public static function formatAmount(
        float|int $amount,
        string $symbol = '₦',
        ?int $precision = 2
    ): string {
        $formatted = is_null($precision)
            ? rtrim(rtrim(number_format((float) $amount, 2, '.', ','), '0'), '.')
            : number_format((float) $amount, $precision, '.', ',');

        return $symbol . $formatted;
    }

}