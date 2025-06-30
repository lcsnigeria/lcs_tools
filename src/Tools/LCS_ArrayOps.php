<?php
namespace lcsTools\Tools;

/**
 * Class LCS_ArrayOps
 *
 * This class provides utility methods for performing various operations on arrays.
 * It is part of the LCS external library tools.
 *
 * @package LCS_Ext_Library\Tools
 */
class LCS_ArrayOps 
{
    /**
     * Encode an associative array or object into a URL-encoded query string,
     * supporting nested structures with custom separators and optional encoding.
     *
     * This method processes the input `$data` by:
     * 1. Validating that `$data` is an array or object (throws if not).
     * 2. Recursively handling nested arrays/objects to build bracketed keys.
     * 3. URL-encoding keys and values if `$urlencode === true`.
     * 4. Joining key-value pairs with the specified separator.
     *
     * @param  array|object  $data        The data to encode (array or object).
     * @param  string        $prefix      Optional prefix before each key (unused default '').
     * @param  string        $sep         Separator between pairs (defaults to '&').
     * @param  string        $key         Internal use for nested keys (do not set manually).
     * @param  bool          $urlencode   Whether to URL-encode keys and values (default true).
     * @return string                     The URL-encoded query string.
     *
     * @throws \InvalidArgumentException  If `$data` is not an array or object.
     *
     * @example
     * ```php
     * $params = [
     *     'user' => [
     *         'name'  => 'John Doe',
     *         'roles' => ['admin', 'editor']
     *     ],
     *     'page' => 2
     * ];
     *
     * echo MyClass::encodeURLQuery($params);
     * // Outputs: user[name]=John%20Doe&user[roles][0]=admin&user[roles][1]=editor&page=2
     * ```
     */
    public static function encodeURLQuery(
        array|object $data,
        string $prefix = '',
        string $sep = '&',
        string $key = '',
        bool $urlencode = true
    ): string {
        // Ensure correct input type
        if (!is_array($data) && !is_object($data)) {
            throw new \InvalidArgumentException('Input data must be an array or object.');
        }

        $query = [];
        foreach ((array)$data as $k => $v) {
            $encodedKey = $urlencode ? urlencode($k) : $k;
            $composedKey = $key !== '' ? "{$key}[{$encodedKey}]" : $encodedKey;

            if (is_array($v) || is_object($v)) {
                $nested = self::encodeURLQuery($v, $prefix, $sep, $composedKey, $urlencode);
                if ($nested !== '') {
                    $query[] = $nested;
                }
            } else {
                $encodedValue = $urlencode ? urlencode((string)$v) : (string)$v;
                $query[] = "{$composedKey}={$encodedValue}";
            }
        }

        return implode($sep, $query);
    }

    /**
     * Decode a URL-encoded query string into an associative structure.
     *
     * This method supports:
     * - Standard query strings (e.g. `key=value&key2=value2`).
     * - Nested parameters (e.g. `user[name]=John&user[roles][0]=admin`).
     *
     * @param  string  $queryString     The raw query string to decode.
     * @param  bool    $returnAsObject  If true, returns a nested stdClass object;
     *                                  if false (default), returns an associative array.
     * @return array|object             The decoded data as an array or object.
     *
     * @example
     * ```php
     * $query = 'user[name]=John%20Doe&user[roles][0]=admin&user[roles][1]=editor&page=2';
     *
     * // As array (default):
     * $arr = MyClass::decodeURLQuery($query);
     * // [
     * //   'user' => [
     * //     'name'  => 'John Doe',
     * //     'roles' => ['admin','editor']
     * //   ],
     * //   'page' => '2'
     * // ]
     *
     * // As object:
     * $obj = MyClass::decodeURLQuery($query, true);
     * // stdClass {
     * //   user => stdClass {
     * //     name  => 'John Doe',
     * //     roles => ['admin','editor']
     * //   },
     * //   page => '2'
     * // }
     * ```
     */
    public static function decodeURLQuery(string $queryString, bool $returnAsObject = false): array|object
    {
        // Parse into an associative array
        parse_str($queryString, $result);

        if (! $returnAsObject) {
            return $result;
        }

        // Recursively convert arrays to stdClass
        $arrayToObject = static function ($data) use (&$arrayToObject) {
            if (! is_array($data)) {
                return $data;
            }
            $obj = new \stdClass();
            foreach ($data as $key => $value) {
                // If numeric-keyed array, keep it as a PHP array
                if (is_int($key)) {
                    $obj->{$key} = is_array($value) ? array_map($arrayToObject, $value) : $value;
                } else {
                    $obj->{$key} = $arrayToObject($value);
                }
            }
            return $obj;
        };

        return $arrayToObject($result);
    }

    /**
     * Checks if the given array has sequential numeric keys starting from 0.
     *
     * Example:
     * ```php
     * LCS_ArrayOps::isArrayKeysSequential(['a', 'b', 'c']); // true
     * LCS_ArrayOps::isArrayKeysSequential([0 => 'a', 2 => 'b']); // false (missing 1)
     * LCS_ArrayOps::isArrayKeysSequential(['x' => 'a', 'y' => 'b']); // false (string keys)
     * ```
     *
     * @param array $array The array to check.
     * @return bool True if the array has keys 0,1,2,... without gaps; otherwise, false.
     */
    public static function isArrayKeysSequential(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Converts an array of strings into a human-readable conjunction string.
     *
     * You can control when to summarize the remaining items using the $summaryStage parameter.
     * If $summaryStage is 0 or greater than or equal to the number of items, no summarization will happen.
     *
     * Examples:
     * - arrayToConjunction(['John']) → "John"
     * - arrayToConjunction(['John', 'Mike']) → "John and Mike"
     * - arrayToConjunction(['John', 'Mike', 'Teddy'], 2) → "John, Mike and 1 other"
     * - arrayToConjunction(['John', 'Mike', 'Teddy', 'Lucy'], 0) → "John, Mike, Teddy and Lucy"
     * - arrayToConjunction(['John', 'Mike', 'Teddy', 'Lucy'], 3) → "John, Mike, Teddy and 1 other"
     *
     * @param array $items The array of strings to be joined.
     * @param int $summaryStage The number of items to list before summarizing the rest. Defaults to 2.
     * @return string The formatted conjunction string.
     */
    public static function arrayToConjunction(array $items, int $summaryStage = 2): string {
        $total = count($items);

        if ($total === 0) return '';
        if ($total === 1 || $summaryStage >= $total || $summaryStage === 0) {
            // Return all items unsummarized
            if ($total === 1) return $items[0];
            if ($total === 2) return $items[0] . ' and ' . $items[1];

            $allButLast = array_slice($items, 0, -1);
            $last = $items[$total - 1];
            return implode(', ', $allButLast) . ' and ' . $last;
        }

        // Summarize
        $listed = array_slice($items, 0, $summaryStage);
        $othersCount = $total - $summaryStage;
        return implode(', ', $listed) . ' and ' . $othersCount . ' other' . ($othersCount > 1 ? 's' : '');
    }

    /**
     * Isolate items into an array of associative arrays with a specific key or set of keys.
     *
     * This function takes an array of values and transforms it into an array of 
     * associative arrays. If $keys is a string, each item in $items must be scalar,
     * and the result will be [ ['key' => value], ... ]. If $keys is an array, each
     * item in $items must itself be an array of the same length as $keys, and the
     * result will map keys to corresponding values in each item.
     *
     * Examples:
     * ```php
     *   // Single key:
     *   isolateItemsWithKey([10, 20, 30], 'quantity');
     *   // yields:
     *   // [
     *   //   ['quantity' => 10],
     *   //   ['quantity' => 20],
     *   //   ['quantity' => 30],
     *   // ]
     *
     *   // Multiple keys:
     *   isolateItemsWithKey([
     *       ['Chinonso', 'Nigeria'],
     *       ['Mike', 'United States']
     *   ], ['name', 'country']);
     *   // yields:
     *   // [
     *   //   ['name' => 'Chinonso', 'country' => 'Nigeria'],
     *   //   ['name' => 'Mike',    'country' => 'United States'],
     *   // ]
     * ```
     *
     * @param array $items The array of values (or arrays) to isolate.
     *                     If $keys is a string, each element must be scalar.
     *                     If $keys is an array, each element must be an array of the same length.
     * @param string|array $keys The key (string) to use for each scalar item, 
     *                           or an array of keys for nested array items.
     *
     * @return array The transformed array of associative arrays.
     * @throws InvalidArgumentException If:
     *   - $items is empty.
     *   - $keys is a string but any item is not scalar.
     *   - $keys is an array but any item is not array, or counts do not match.
     *   - $keys is neither string nor array (defensive).
     */
    public static function isolateItemsWithKey(array $items, string|array $keys): array
    {
        // 1. Ensure the input array is not empty.
        if (empty($items)) {
            throw new \InvalidArgumentException("The input array must not be empty.");
        }

        // 2. Handle the case where $keys is a string: each item must be scalar.
        if (is_string($keys)) {
            $result = [];
            foreach ($items as $index => $item) {
                if (is_array($item) || is_object($item)) {
                    throw new \InvalidArgumentException(
                        "When \$keys is a string ('{$keys}'), each item must be a scalar value. "
                        . "Invalid item at index {$index}."
                    );
                }
                // Wrap scalar into associative array
                $result[$index] = [$keys => $item];
            }
            return $result;
        }

        // 3. Handle the case where $keys is an array: each item must be an array of same length.
        if (is_array($keys)) {
            $keyCount = count($keys);
            if ($keyCount === 0) {
                throw new \InvalidArgumentException("The keys array must contain at least one key.");
            }
            $result = [];
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException(
                        "When \$keys is an array, each item in \$items must be an array. "
                        . "Invalid (non-array) item at index {$index}."
                    );
                }
                if (count($item) !== $keyCount) {
                    throw new \InvalidArgumentException(
                        "Count mismatch at index {$index}: expected an array with {$keyCount} value"
                        . ($keyCount > 1 ? 's' : '') . " to match keys "
                        . json_encode($keys) . ", but got an array with " . count($item) . " element"
                        . (count($item) > 1 ? 's' : '') . "."
                    );
                }
                // Build associative mapping for this item
                $assoc = [];
                foreach ($keys as $kIndex => $keyName) {
                    // Optional: you may also check that $keyName is a non-empty string
                    if (!is_string($keyName) || $keyName === '') {
                        throw new \InvalidArgumentException(
                            "Invalid key at position {$kIndex} in keys array: keys must be non-empty strings."
                        );
                    }
                    $assoc[$keyName] = $item[$kIndex];
                }
                $result[$index] = $assoc;
            }
            return $result;
        }

        // 4. Defensive: if $keys is neither string nor array (signature enforces string|array),
        //    but just in case something unexpected arrives:
        throw new \InvalidArgumentException(
            'The $keys parameter must be a string or a non-empty array of strings.'
        );
    }


}