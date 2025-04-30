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

}