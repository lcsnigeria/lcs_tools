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
