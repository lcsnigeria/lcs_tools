<?php
namespace lcsTools\Tools;

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
 * @package lcsTools\Tools
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

}