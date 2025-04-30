<?php
namespace LCSNG_EXT\Tools;

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
}
