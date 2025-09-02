<?php
namespace LCSNG\Tools\Debugging;

use Exception;

/**
 * Class LCS_Logs
 *
 * Provides a centralized utility for logging, error reporting, and debugging.
 * Supports multiple reporting modes such as logging with error_log, triggering PHP errors, or throwing exceptions.
 * Designed to be extended with additional debugging, logging, or diagnostic tools.
 *
 * @package Debugging\LCS_Logs
 */
class Logs extends Exception
{
    /**
     * Default logging state.
     * - 1: use error_log with timestamp
     * - 2: trigger_error
     * - 3: throw Exception
     *
     * @var int
     */
    private static $logState = 1;

    /**
     * Reports an error message based on the specified logging state.
     *
     * @param string $message The error message to report.
     * @param int $logState
     *        Defines behavior:
     *        - 1: use error_log with timestamp (default)
     *        - 2: trigger_error with specified error type
     *        - 3: throw Exception
     * @param int $errorType The error type for trigger_error when logState=2 (default E_USER_NOTICE)
     *
     * @throws Exception If logState is 3 or if an invalid logState is provided.
     */
    public static function reportError(string $message, $logState = 1, int $errorType = E_USER_NOTICE)
    {
        $logState = $logState ?? self::$logState;

        if (!in_array($logState, [1, 2, 3], true)) {
            throw new Exception("Invalid logState: $logState");
        }

        if ($logState == 1) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[$timestamp] $message");
        } elseif ($logState == 2) {
            trigger_error($message, $errorType);
        } elseif ($logState == 3) {
            throw new Exception($message);
        }
    }
}