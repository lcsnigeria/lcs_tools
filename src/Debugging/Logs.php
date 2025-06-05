<?php
namespace lcsTools\Debugging;

use Exception;

/**
 * Class LCS_Logs
 *
 * Provides a centralized utility for logging, error reporting, and debugging.
 * Supports multiple reporting modes such as throwing exceptions or triggering PHP notices.
 * Designed to be extended with additional debugging, logging, or diagnostic tools.
 *
 * @package Debugging\LCS_Logs
 */
class Logs extends Exception
{
    /**
     * Default logging state.
     * - 0 or false: silent
     * - 1 or true: trigger PHP notice
     * - 2: throw exception
     *
     * @var int|bool|null
     */
    private static $logState;

    /**
     * Reports an error message based on the current or provided logging state.
     *
     * @param string $message   The error message to report.
     * @param int|bool|null $logState
     *        Defines behavior:
     *        - 2: throw Exception
     *        - 1 or true: trigger E_USER_NOTICE
     *        - 0 or false: ignore (returns false)
     *
     * @return false|void Returns false if the error is ignored.
     *
     * @throws Exception If logState is 2.
     */
    public static function reportError(string $message, $logState = 1)
    {
        $logState = $logState ?? self::$logState ?? false;

        if ($logState == 2) {
            throw new Exception($message);
        } elseif ($logState == 1 || $logState === true) {
            trigger_error($message, E_USER_NOTICE);
        } else {
            return false;
        }
    }
}