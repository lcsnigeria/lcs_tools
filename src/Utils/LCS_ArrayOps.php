<?php
namespace LCSNG\Tools\Utils;

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

    /**
     * Find the parent key of an item in a nested array.
     *
     * - "key" mode:   Returns the parent key that contains the searched key.
     * - "value" mode: Returns the actual child key that directly holds the searched value.
     * - "both" mode:  Searches for both key and value matches.
     *
     * @param array  $array The array to search.
     * @param mixed  $item  The key or value to find.
     * @param string $mode  Search mode: "both", "key", or "value". Defaults to "both".
     *
     * @return string|null The matching key (parent or child depending on mode), or null if not found.
     *
     * @example
     * $data = [
     *     'parent1' => ['child' => 42],
     *     'parent2' => ['child' => 'hello']
     * ];
     *
     * // Search by key → returns the parent
     * echo YourClass::getArrayItemParent($data, 'child', 'key');
     * // Output: "parent1"
     *
     * // Search by value → returns the child
     * echo YourClass::getArrayItemParent($data, 42, 'value');
     * // Output: "child"
     *
     * echo YourClass::getArrayItemParent($data, 'hello', 'value');
     * // Output: "child"
     *
     * // Not found
     * var_dump(YourClass::getArrayItemParent($data, 'missing', 'both'));
     * // Output: NULL
     */
    public static function getArrayItemParent(array $array, $item, string $mode = 'both'): ?string
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                // Case 1: search keys → return parent key
                if (($mode === 'both' || $mode === 'key') && array_key_exists($item, $value)) {
                    return $key;
                }

                // Case 2: search values → return the matching child key
                if ($mode === 'both' || $mode === 'value') {
                    foreach ($value as $childKey => $childVal) {
                        if ($childVal === $item) {
                            return $childKey;
                        }
                    }
                }

                // Search deeper recursively
                $parent = self::getArrayItemParent($value, $item, $mode);
                if ($parent !== null) {
                    return $parent;
                }
            }
        }
        return null;
    }

    /**
     * Find the parent key of an item in a nested array, limited to a given ancestor subtree.
     *
     * This function searches for a given key or value only within the subtree under
     * a specified ancestor key. The behavior depends on the search mode:
     *
     * - "key":   Finds the parent key that contains the searched key.
     * - "value": Finds the actual key that holds the searched value.
     * - "both":  Searches for both key and value matches, returning either a parent key (for key matches)
     *            or the value's key (for value matches).
     *
     * @param array  $array       The array to search within.
     * @param mixed  $item        The key or value to search for.
     * @param string $ancestorKey The ancestor key that defines the subtree to limit the search.
     * @param string $mode        Search mode: "both", "key", or "value". Defaults to "both".
     *
     * @return string|null Returns the matching key depending on mode, or null if not found.
     *
     * @example
     * $data = [
     *     'root' => [
     *         'ancestor' => [
     *             'parent1' => ['child' => 42],
     *             'parent2' => ['child' => 'hello']
     *         ]
     *     ]
     * ];
     *
     * // Search by key
     * echo getArrayItemParentByAncestor($data, 'child', 'ancestor', 'key'); 
     * // Output: "parent1"
     *
     * // Search by value
     * echo getArrayItemParentByAncestor($data, 42, 'ancestor', 'value'); 
     * // Output: "child"
     *
     * echo getArrayItemParentByAncestor($data, 'hello', 'ancestor', 'value'); 
     * // Output: "child"
     *
     * // Not found
     * var_dump(getArrayItemParentByAncestor($data, 'missing', 'ancestor', 'value')); 
     * // Output: NULL
     */
    public static function getArrayItemParentByAncestor(array $array, $item, string $ancestorKey, string $mode = 'both'): ?string
    {
        // Step 1: find ancestor subtree
        $findAncestor = function ($arr) use (&$findAncestor, $ancestorKey) {
            foreach ($arr as $key => $value) {
                if ($key === $ancestorKey && is_array($value)) {
                    return $value;
                }
                if (is_array($value)) {
                    $found = $findAncestor($value);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
            return null;
        };

        $ancestorArray = $findAncestor($array);
        if ($ancestorArray === null) {
            return null;
        }

        // Step 2: search inside ancestor
        $search = function ($subArray) use (&$search, $item, $mode) {
            foreach ($subArray as $key => $value) {
                if (is_array($value)) {
                    // Case 1: search keys → return parent key
                    if (($mode === 'both' || $mode === 'key') && array_key_exists($item, $value)) {
                        return $key;
                    }
                    // Case 2: search values → return the matching key itself
                    if (($mode === 'both' || $mode === 'value')) {
                        foreach ($value as $childKey => $childVal) {
                            if ($childVal === $item) {
                                return $childKey;
                            }
                        }
                    }
                    $parent = $search($value);
                    if ($parent !== null) {
                        return $parent;
                    }
                }
            }
            return null;
        };

        return $search($ancestorArray);
    }


    /**
     * Get the children (keys or values) of an array item.
     *
     * This method retrieves the children of a specified key in a multidimensional array.
     * If the `$item` parameter is `null`, it returns the top-level keys of the array.
     * If the `$item` parameter is provided and exists in the array, it returns the keys of the child array at that key.
     * If the specified key does not exist or is not an array, it returns `null`.
     *
     * Example usage:
     * ```php
     * $data = [
     *     'fruits' => [
     *         'apple' => 'red',
     *         'banana' => 'yellow'
     *     ],
     *     'vegetables' => [
     *         'carrot' => 'orange',
     *         'lettuce' => 'green'
     *     ]
     * ];
     *
     * // Get top-level keys
     * $topKeys = LCS_ArrayOps::getArrayChildren($data); // ['fruits', 'vegetables']
     *
     * // Get children keys of 'fruits'
     * $fruitKeys = LCS_ArrayOps::getArrayChildren($data, 'fruits'); // ['apple', 'banana']
     *
     * // Get children keys of a non-existent key
     * $unknown = LCS_ArrayOps::getArrayChildren($data, 'meat'); // null
     * ```
     *
     * @param array $array The array to search.
     * @param string|null $item The key whose children to fetch. If null, returns top-level keys.
     * @return array|null Array of children (keys or values), or null if not found.
     */
    public static function getArrayChildren(array $array, ?string $item = null): ?array
    {
        // Case 1: no item provided → return top-level keys
        if ($item === null) {
            return array_keys($array);
        }

        foreach ($array as $key => $value) {
            if ($key === $item && is_array($value)) {
                // If children are arrays, return their keys
                // If children are scalar values, return the values
                return self::isAssoc($value) ? array_keys($value) : array_values($value);
            }
            if (is_array($value)) {
                $children = self::getArrayChildren($value, $item);
                if ($children !== null) {
                    return $children;
                }
            }
        }

        return null;
    }

    /**
     * Get the children (keys for associative arrays, values for numeric-indexed arrays)
     * of an array item, limited to an ancestor subtree.
     *
     * This method searches within a multidimensional array for the specified ancestor key,
     * and then retrieves the children of the given item under that ancestor.
     * - If the target item is an associative array (e.g., ['a' => 1, 'b' => 2]),
     *   this returns its **keys**: ['a', 'b'].
     * - If the target item is a numeric-indexed array (e.g., [10, 20]),
     *   this returns its **values**: [10, 20] (reindexed).
     *
     * If the ancestor or item is not found, it returns null.
     *
     * Example usage:
     * ```php
     * $data = [
     *     'root' => [
     *         'parent1' => [
     *             'childA' => 1,
     *             'childB' => 2,
     *         ],
     *         'parent2' => [
     *             'childC' => 3,
     *         ],
     *         'parentList' => [10, 20],
     *     ],
     * ];
     *
     * // Get children of 'parent1' under 'root' (associative → returns keys)
     * $children1 = LCS_ArrayOps::getArrayChildrenByAncestor($data, 'parent1', 'root');
     * // $children1 === ['childA', 'childB']
     *
     * // Get children of 'parent2' under 'root' (associative → returns keys)
     * $children2 = LCS_ArrayOps::getArrayChildrenByAncestor($data, 'parent2', 'root');
     * // $children2 === ['childC']
     *
     * // Get children of 'parentList' under 'root' (list → returns values)
     * $children3 = LCS_ArrayOps::getArrayChildrenByAncestor($data, 'parentList', 'root');
     * // $children3 === [10, 20]
     *
     * // Missing item or ancestor
     * $children4 = LCS_ArrayOps::getArrayChildrenByAncestor($data, 'missing', 'root');
     * // $children4 === null
     * ```
     *
     * @param array  $array    The array to search.
     * @param string $item     The key whose children to fetch (under the ancestor).
     * @param string $ancestor The ancestor key under which to search.
     * @return array|null      Array of children (keys or values), or null if not found.
     */
    public static function getArrayChildrenByAncestor(array $array, string $item, string $ancestor): ?array
    {
        // Step 1: find ancestor subtree
        $findAncestor = function ($arr) use (&$findAncestor, $ancestor) {
            foreach ($arr as $key => $value) {
                if ($key === $ancestor && is_array($value)) {
                    return $value;
                }
                if (is_array($value)) {
                    $found = $findAncestor($value);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
            return null;
        };

        $ancestorArray = $findAncestor($array);
        if ($ancestorArray === null) {
            return null;
        }

        // Step 2: return children of $item inside ancestor
        if (array_key_exists($item, $ancestorArray) && is_array($ancestorArray[$item])) {
            $value = $ancestorArray[$item];
            return self::isAssoc($value) ? array_keys($value) : array_values($value);
        }

        return null;
    }

    /**
     * Extend the value of an array at a given position by making the existing value
     * the key of a new nested array containing the new data.
     *
     * Rules:
     * - Only string, number, or boolean values can be extended into new keys.
     * - Empty arrays `[]` are not extendable (throws error).
     * - `position` (default = null): Which top-level key to operate on.
     *      - null → default depends on $drillPosition:
     *          - "end"   → last key
     *          - "start" → first key (default philosophy)
     *      - int → index of the key (0 = first, -1 = last, etc.)
     *      - string → exact array key
     * - `$drillPosition` (default = "start"):
     *      - "start" → drills into the first available nested array (default).
     *      - "end"   → drills into the last available nested array.
     *
     * @param array $arr - The array to extend
     * @param mixed $data - The new data to append
     * @param int|string|null $position - Key/index of the array to target
     * @param string $drillPosition - Where to drill when nested ("start"|"end")
     * @throws InvalidArgumentException If the target is not extendable
     * @return array The modified array (mutates original)
     *
     * @example
     * // Example 1: Basic usage (default → extends first key "a")
     * $arr1 = [ "a" => "apple", "b" => [ "k1" => "banana1", "k2" => "banana2" ], "c" => "cherry" ];
     * extendArrayValue($arr1, "X");
     * // → [ "a" => [ "apple" => "X" ], "b" => [ "k1" => "banana1", "k2" => "banana2" ], "c" => "cherry" ]
     *
     * @example
     * // Example 2: Explicit position (last key "c")
     * $arr2 = [ "a" => "apple", "b" => "banana", "c" => "cherry" ];
     * extendArrayValue($arr2, "Y", -1);
     * // → [ "a" => "apple", "b" => "banana", "c" => [ "cherry" => "Y" ] ]
     *
     * @example
     * // Example 3: Drill into nested array (first key of "b" → "k1")
     * $arr3 = [ "a" => "apple", "b" => [ "k1" => "banana1", "k2" => "banana2" ], "c" => "cherry" ];
     * extendArrayValue($arr3, "Z", "b", "start");
     * // → [ "a" => "apple", "b" => [ "k1" => [ "banana1" => "Z" ], "k2" => "banana2" ], "c" => "cherry" ]
     *
     * @example
     * // Example 4: Multi-level drilling
     * $arr4 = [ "a" => [ "sub1" => [ "sub2" => "deep" ] ] ];
     * extendArrayValue($arr4, "D", "a", "start");
     * // → [ "a" => [ "sub1" => [ "sub2" => [ "deep" => "D" ] ] ] ]
     *
     * @example
     * // Example 5: Error cases
     * extendArrayValue([], "oops");
     * // ❌ Error: extendArrayValue: Cannot operate on empty array.
     *
     * extendArrayValue([ "a" => [] ], "oops");
     * // ❌ Error: extendArrayValue: Cannot append into empty array.
     *
     * extendArrayValue([ "a" => [10, 20, 30] ], "oops");
     * // ❌ Error: extendArrayValue: Cannot append into non-scalar value (type: array).
     */
    public static function extendArrayValue(array &$arr, $data, $position = null, string $drillPosition = "start"): array {
        if (empty($arr)) {
            throw new InvalidArgumentException("extendArrayValue: Cannot operate on empty array.");
        }

        $keys = array_keys($arr);

        // Resolve target key
        if ($position === null) {
            $targetKey = $drillPosition === "end" ? end($keys) : reset($keys);
        } elseif (is_int($position)) {
            $idx = $position < 0 ? count($keys) + $position : $position;
            if (!isset($keys[$idx])) {
                throw new InvalidArgumentException("extendArrayValue: Position $position is out of bounds.");
            }
            $targetKey = $keys[$idx];
        } elseif (is_string($position)) {
            if (!array_key_exists($position, $arr)) {
                throw new InvalidArgumentException("extendArrayValue: Key \"$position\" not found.");
            }
            $targetKey = $position;
        } else {
            throw new InvalidArgumentException("extendArrayValue: Invalid position type.");
        }

        // Drill into nested arrays
        $parent =& $arr;
        $key = $targetKey;

        while (is_array($parent[$key])) {
            if (empty($parent[$key])) {
                throw new InvalidArgumentException("extendArrayValue: Cannot append into empty array.");
            }
            $nestedKeys = array_keys($parent[$key]);
            $key = $drillPosition === "end" ? end($nestedKeys) : reset($nestedKeys);
            $parent =& $parent[$key];
        }

        $oldValue = $parent[$key];

        // Only scalars are extendable
        if (is_array($oldValue) || is_object($oldValue)) {
            $type = is_array($oldValue) ? "array" : "object";
            throw new InvalidArgumentException("extendArrayValue: Cannot append into non-scalar value (type: $type).");
        }

        // Perform the extension
        $parent[$key] = [ $oldValue => $data ];

        return $arr;
    }

    /**
     * Helper: check if an array is associative (i.e., not a 0..n-1 numeric list).
     *
     * @example
     * LCS_ArrayOps::isAssoc(['a' => 1, 'b' => 2]); // true
     * LCS_ArrayOps::isAssoc([10, 20]);             // false
     *
     * @param array $arr
     * @return bool
     */
    public static function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }


}