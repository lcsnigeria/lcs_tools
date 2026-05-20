<?php
namespace LCSNG\Tools\Utils;

/**
 * LCS_NumberOps
 *
 * A general-purpose utility class for performing common numerical operations.
 * Designed to provide static methods for safely handling, transforming, and computing
 * numeric values, whether in scalar, array, or string format.
 *
 * This class aims to simplify arithmetic logic, aggregation, and numeric conversions
 * in a centralized, reusable way — especially in dynamic input or data-processing contexts.
 *
 * Intended use cases include:
 * - Aggregating numbers from mixed input formats
 * - Performing arithmetic operations with flexible syntax
 * - Supporting calculations across different layers of an application
 * - Centralizing reusable number-handling logic
 *
 * All methods are static and stateless, making the class safe to use across various parts
 * of the system without instantiation.
 *
 * @package LCSNG\Tools\Utils
 */
class LCS_NumberOps 
{
    /**
     * Computes the result of a basic arithmetic operation on a set of numbers.
     *
     * Accepts either an array of numbers or a space-separated string of numeric values,
     * then applies the specified arithmetic operation (`+`, `-`, `*`, or `/`) on the operands.
     * 
     * ### Operator Aliases Supported:
     * - Addition: `'addition'`, `'add'`, `'plus'`, `'+'`
     * - Subtraction: `'subtraction'`, `'subtract'`, `'minus'`, `'-'`
     * - Multiplication: `'multiplication'`, `'multiply'`, `'*'`
     * - Division: `'division'`, `'divide'`, `'/'`
     * 
     * If fewer than two numeric operands are detected, the method returns the first valid value as a string,
     * or `'0'` if no valid values are found.
     * 
     * The method internally sanitizes inputs by filtering out non-numeric values.
     *
     * ### Example Usage:
     * ```php
     * LCS_NumberOps::sum("10 20 30", "add");           // returns "60"
     * LCS_NumberOps::sum([100, 50], "-");              // returns "50"
     * LCS_NumberOps::sum("3 4", "*");                  // returns "12"
     * LCS_NumberOps::sum("20 4", "divide");            // returns "5"
     * LCS_NumberOps::sum("hello 10", "+");             // returns "10" (non-numeric value ignored)
     * LCS_NumberOps::sum([], "+");                     // returns "0"
     * LCS_NumberOps::sum("4", "-");                    // returns "4" (only one number)
     * ```
     *
     * @param array|string $value    An array or space-separated string of numeric values.
     * @param string       $operator The arithmetic operation to perform.
     *                               Can be a symbol (`+`, `-`, `*`, `/`) or an alias keyword.
     * @return string                The result of the arithmetic operation as a string.
     */
    public static function sum($value, $operator = '+') {
        // Map various operator names to their symbols
        switch ($operator) {
            case 'addition':
            case 'add':
            case 'plus':
            case '+':
                $operator = '+';
                break;
            case 'subtraction':
            case 'subtract':
            case 'minus':
            case '-':
                $operator = '-';
                break;
            case 'division':
            case 'divide':
            case '/':
                $operator = '/';
                break;
            case 'multiplication':
            case 'multiply':
            case '*':
                $operator = '*';
                break;
            default:
                $operator = '+';
        }

        // Initialize the operands array
        $operands = [];

        // If value is not an array, split the string by spaces
        if (!is_array($value)) {
            $operands = explode(' ', $value);
        } else {
            $operands = $value;
        }

        // Filter out non-numeric values
        $operands = array_filter($operands, 'is_numeric');

        // If there are less than 2 valid operands, return the single value or '0' if empty
        if (count($operands) < 2) {
            return strval(implode('', $operands));
        }

        // Create the expression string
        $expression = implode(" $operator ", $operands);

        // Evaluate the expression and handle errors
        try {
            $result = eval("return $expression;");
        } catch (\ParseError $e) {
            return 'Error: Invalid expression.';
        }

        return strval($result);
    }

    /**
     * Format a numeric amount as a localized currency string.
     *
     * - Supports optional currency symbol and decimal precision.
     * - Adds thousands separators (comma) and decimal point (dot).
     * - Optionally trims trailing zeros after decimal point.
     *
     * @param  float|int|string  $amount       The numeric amount to format.
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
        float|int|string $amount,
        string $symbol = '₦',
        ?int $precision = 2
    ): string {
        $amount = (float) $amount;
        $formatted = is_null($precision)
            ? rtrim(rtrim(number_format((float) $amount, 2, '.', ','), '0'), '.')
            : number_format((float) $amount, $precision, '.', ',');
        return $symbol . $formatted;
    }

    /**
     * Abbreviate large numbers into compact human-readable format.
     *
     * Converts numeric values into shortened representations using suffixes:
     *
     *  - K => Thousand
     *  - M => Million
     *  - B => Billion
     *  - T => Trillion
     *
     * Features:
     *  - Supports integers, floats, and numeric strings
     *  - Supports negative numbers
     *  - Configurable decimal precision
     *  - Removes unnecessary trailing zeros
     *  - Automatically handles rounding rollover
     *    (e.g. 999.999 => 1K)
     *
     * Supported examples:
     *  - 950             => 950
     *  - 1500            => 1.5K
     *  - 2500000         => 2.5M
     *  - 3500000000      => 3.5B
     *  - 7200000000000   => 7.2T
     *  - -12500          => -12.5K
     *  - 999.999         => 1K
     *
     * @param int|float|string $number
     *      The number to abbreviate.
     *      Accepts integers, floats, and numeric strings.
     *
     * @param int $precision
     *      Optional decimal precision for abbreviated values.
     *      Default: 1.
     *
     * @return string
     *      The abbreviated human-readable number.
     *      Returns '0' for invalid/non-numeric input.
     *
     * @example
     *  echo ::abbreviateNumber(950);
     *  // 950
     *
     * @example
     *  echo ::abbreviateNumber(1500);
     *  // 1.5K
     *
     * @example
     *  echo ::abbreviateNumber(2500000);
     *  // 2.5M
     *
     * @example
     *  echo ::abbreviateNumber(7200000000000);
     *  // 7.2T
     *
     * @example
     *  echo ::abbreviateNumber(-9876543.21);
     *  // -9.9M
     *
     * @example
     *  echo ::abbreviateNumber(1250.75, 2);
     *  // 1.25K
     *
     * @example
     *  echo ::abbreviateNumber(999.999, 2);
     *  // 1K
     */
    public static function abbreviateNumber($number, int $precision = 1): string {
        if (!is_numeric($number)) {
            return '0';
        }

        $number = (float)$number;

        /**
         * Detect negative values
         */
        $negative = $number < 0;

        /**
         * Work with absolute value internally
         */
        $number = abs($number);

        /**
         * Suffix powers
         */
        $suffixes = [
            12 => 'T', // Trillion
            9  => 'B', // Billion
            6  => 'M', // Million
            3  => 'K', // Thousand
        ];

        /**
         * Handle values below 1000
         */
        if ($number < 1000) {
            $rounded = round($number, $precision);

            /**
             * Handle rollover after rounding.
             *
             * Example:
             *  999.999 => 1000 => 1K
             */
            if ($rounded < 1000) {
                $formatted = self::trimTrailingZeros(
                    number_format($rounded, $precision, '.', '')
                );

                return $negative
                    ? "-{$formatted}"
                    : $formatted;
            }

            $number = $rounded;
        }

        /**
         * Determine best suffix
         */
        foreach ($suffixes as $power => $suffix) {
            $threshold = 10 ** $power;

            if ($number >= $threshold) {
                $value = $number / $threshold;

                $formatted = self::trimTrailingZeros(
                    number_format($value, $precision, '.', '')
                );

                return ($negative ? '-' : '') . $formatted . $suffix;
            }
        }

        /**
         * Fallback (should rarely occur)
         */
        return ($negative ? '-' : '') . (string)$number;
    }

    /**
     * Remove unnecessary trailing zeros from decimal numbers.
     *
     * Examples:
     *  - 1.0   => 1
     *  - 1.50  => 1.5
     *  - 2.340 => 2.34
     *
     * @param string $number Formatted numeric string.
     *
     * @return string Cleaned numeric string.
     *
     * @example
     *  echo self::trimTrailingZeros('1.0');
     *  // 1
     *
     * @example
     *  echo self::trimTrailingZeros('1.50');
     *  // 1.5
     */
    public static function trimTrailingZeros(string $number): string {
        return rtrim(rtrim($number, '0'), '.');
    }

}