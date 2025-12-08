<?php
namespace LCSNG\Tools\Debugging;

use Exception;
use DateTime;

/**
 * Class Logs
 *
 * Provides a comprehensive centralized utility for logging, error reporting, and debugging.
 * Supports multiple reporting modes, custom error types, log viewing, and log management.
 * Designed for production-ready error tracking and debugging workflows.
 *
 * @package LCSNG\Tools\Debugging
 */
class Logs extends Exception
{
    /**
     * Default logging state.
     * - 1: use error_log with timestamp and file location
     * - 2: trigger_error with specified error type
     * - 3: throw Exception
     *
     * @var int
     */
    private static $logState = 1;

    /**
     * Custom error type constants.
     */
    const USER_ERROR = 'USER_ERROR';
    const USER_WARNING = 'USER_WARNING';
    const USER_INFO = 'USER_INFO';
    const LOGIN_ERROR = 'LOGIN_ERROR';
    const DATABASE_ERROR = 'DATABASE_ERROR';
    const API_ERROR = 'API_ERROR';
    const CRITICAL = 'CRITICAL';
    const DEBUG = 'DEBUG';
    const SECURITY = 'SECURITY';
    const PERFORMANCE = 'PERFORMANCE';

    /**
     * Initialize the logging system with a default log file.
     *
     * @param string|null $logFile Custom log file path. If null, uses default location.
     * @param int $logState Default logging state (1, 2, or 3)
     * @return void
     */
    public static function init(?string $logFile = null, int $logState = 1): void
    {
        self::$logState = $logState;

        // Create log directory if it doesn't exist
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Create log file if it doesn't exist
        if (!file_exists($logFile)) {
            touch($logFile);
            chmod($logFile, 0644);
        }
    }

    /**
     * Reports an error message based on the specified logging state.
     *
     * @param string $message The error message to report.
     * @param int $logState Defines behavior (1, 2, or 3)
     * @param string|int $errorType Custom error type string or PHP error constant
     * @param int $exceptionCode The exception code when throwing an Exception
     * @param string|null $logFile Custom log file path
     * @throws Exception If logState is 3 or if an invalid logState is provided.
     * @return bool Success status
     */
    public static function reportError(
        string $message,
        ?int $logState = null,
        $errorType = self::USER_ERROR,
        int $exceptionCode = 0,
        ?string $logFile = null
    ): bool {
        $logState = $logState ?? self::$logState;
        if ($logFile === null) {
            $logFile = !empty(ini_get('error_log')) ? ini_get('error_log') : dirname(__FILE__) . '/logs/app.log';
        }

        // Initialize file
        self::init($logFile);

        if (!in_array($logState, [1, 2, 3], true)) {
            throw new Exception("Invalid logState: $logState. Must be 1, 2, or 3.", $exceptionCode);
        }

        // Get caller information for file location
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? $backtrace[0];
        $file = $caller['file'] ?? 'unknown';
        $line = $caller['line'] ?? 0;
        $fileLocation = "$file:$line";

        // Normalize error type to string
        $errorTypeString = self::normalizeErrorType($errorType);

        // Format log entry
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$errorTypeString] $message | File: $fileLocation";

        if ($logState === 1) {
            // Write to custom log file
            return self::writeToLogFile($formattedMessage, $logFile);
        } elseif ($logState === 2) {
            // Map custom types to PHP error types for trigger_error
            $phpErrorType = self::mapToPhpErrorType($errorType);
            trigger_error($formattedMessage, $phpErrorType);
            return true;
        } elseif ($logState === 3) {
            throw new Exception($formattedMessage, $exceptionCode);
        }

        return false;
    }

    /**
     * Logs a message with a specific log type.
     *
     * @param string $logType Log type (e.g., 'USER_ERROR', 'USER_INFO', 'CRITICAL')
     * @param string $message The message to log
     * @param string|null $logFile Custom log file path
     * @return bool Success status
     */
    public static function reportLogs(string $logType, string $message, ?string $logFile = null): bool
    {
        if ($logFile === null) {
            $logFile = !empty(ini_get('error_log')) ? ini_get('error_log') : dirname(__FILE__) . '/logs/app.log';
        }

        // Initialize file
        self::init($logFile);

        // Get caller information
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? $backtrace[0];
        $file = $caller['file'] ?? 'unknown';
        $line = $caller['line'] ?? 0;
        $fileLocation = "$file:$line";

        // Normalize log type
        $logType = strtoupper($logType);

        // Format log entry
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$logType] $message | File: $fileLocation";

        return self::writeToLogFile($formattedMessage, $logFile);
    }

    /**
     * Renders a beautiful HTML UI for viewing logs.
     *
     * @param string|null $errorFile Path to the log file. Uses default if null.
     * @param int $limit Maximum number of log entries to display (0 = all)
     * @return string HTML output
     */
    public static function renderLogsUI(?string $errorFile = null, int $limit = 100): string
    {
        if ($errorFile === null) {
            $errorFile = !empty(ini_get('error_log')) ? ini_get('error_log') : dirname(__FILE__) . '/logs/app.log';
        }

        if (!file_exists($errorFile)) {
            return self::generateNoLogsHTML($errorFile);
        }

        $logs = self::parseLogs($errorFile, $limit);
        return self::generateLogsHTML($logs, $errorFile);
    }

    /**
     * Clears logs from the specified file.
     *
     * @param string|null $errorFile Path to the log file. Uses default if null.
     * @param string|null $timestamp If provided, clears only logs after this timestamp
     * @return bool Success status
     */
    public static function clearLogs(?string $errorFile = null, ?string $timestamp = null): bool
    {
        if ($errorFile === null) {
            $errorFile = !empty(ini_get('error_log')) ? ini_get('error_log') : dirname(__FILE__) . '/logs/app.log';
        }

        if (!file_exists($errorFile)) {
            return false;
        }

        if ($timestamp === null) {
            // Clear everything
            return file_put_contents($errorFile, '') !== false;
        }

        // Clear logs after specific timestamp
        $logs = file($errorFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $filteredLogs = [];

        foreach ($logs as $log) {
            $logTimestamp = self::extractTimestamp($log);
            if ($logTimestamp && strtotime($logTimestamp) < strtotime($timestamp)) {
                $filteredLogs[] = $log;
            }
        }

        return file_put_contents($errorFile, implode(PHP_EOL, $filteredLogs) . PHP_EOL) !== false;
    }

    /**
     * Gets the total count of log entries.
     *
     * @param string|null $logFile Path to the log file
     * @param string|null $logType Filter by log type
     * @return int Number of log entries
     */
    public static function getLogCount(?string $logFile = null, ?string $logType = null): int
    {
        if ($logFile === null) {
            $logFile = !empty(ini_get('error_log')) ? ini_get('error_log') : dirname(__FILE__) . '/logs/app.log';
        }

        $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($logType === null) {
            return count($logs);
        }

        $count = 0;
        $logType = strtoupper($logType);
        
        foreach ($logs as $log) {
            if (stripos($log, "[$logType]") !== false) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Retrieves logs as an array.
     *
     * @param string|null $logFile Path to the log file
     * @param int $limit Maximum number of entries (0 = all)
     * @param string|null $logType Filter by log type
     * @return array Array of log entries
     */
    public static function getLogs(?string $logFile = null, int $limit = 0, ?string $logType = null): array
    {
        if ($logFile === null) {
            $logFile = !empty(ini_get('error_log')) ? ini_get('error_log') : dirname(__FILE__) . '/logs/app.log';
        }

        if (!file_exists($logFile)) {
            return [];
        }

        return self::parseLogs($logFile, $limit, $logType);
    }

    /**
     * Exports logs to a downloadable file.
     *
     * @param string|null $logFile Path to the log file
     * @param string $format Export format ('txt', 'json', 'csv')
     * @return void
     */
    public static function exportLogs(?string $logFile = null, string $format = 'txt'): void
    {
        if ($logFile === null) {
            $logFile = !empty(ini_get('error_log')) ? ini_get('error_log') : dirname(__FILE__) . '/logs/app.log';
        }

        if (!file_exists($logFile)) {
            header('HTTP/1.0 404 Not Found');
            echo "Log file not found.";
            return;
        }

        $logs = self::parseLogs($logFile);
        $filename = 'logs_export_' . date('Y-m-d_His') . '.' . $format;

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        if ($format === 'json') {
            echo json_encode($logs, JSON_PRETTY_PRINT);
        } elseif ($format === 'csv') {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Timestamp', 'Type', 'Message', 'File']);
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['timestamp'],
                    $log['type'],
                    $log['message'],
                    $log['file']
                ]);
            }
            fclose($output);
        } else {
            echo file_get_contents($logFile);
        }
        exit;
    }

    /**
     * Archives old logs to a separate file.
     *
     * @param string|null $logFile Path to the log file
     * @param int $daysOld Archive logs older than this many days
     * @return bool Success status
     */
    public static function archiveLogs(?string $logFile = null, int $daysOld = 30): bool
    {
        if ($logFile === null) {
            $logFile = !empty(ini_get('error_log')) ? ini_get('error_log') : dirname(__FILE__) . '/logs/app.log';
        }

        if (!file_exists($logFile)) {
            return false;
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-$daysOld days"));
        $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        $archiveLogs = [];
        $currentLogs = [];

        foreach ($logs as $log) {
            $logTimestamp = self::extractTimestamp($log);
            if ($logTimestamp && strtotime($logTimestamp) < strtotime($cutoffDate)) {
                $archiveLogs[] = $log;
            } else {
                $currentLogs[] = $log;
            }
        }

        if (empty($archiveLogs)) {
            return true; // Nothing to archive
        }

        // Create archive file
        $archiveFile = str_replace('.log', '_archive_' . date('Y-m-d') . '.log', $logFile);
        file_put_contents($archiveFile, implode(PHP_EOL, $archiveLogs) . PHP_EOL, FILE_APPEND);

        // Update current log file
        return file_put_contents($logFile, implode(PHP_EOL, $currentLogs) . PHP_EOL) !== false;
    }

    /**
     * Sets the default log state.
     *
     * @param int $state The log state (1, 2, or 3)
     * @return void
     */
    public static function setLogState(int $state): void
    {
        if (!in_array($state, [1, 2, 3], true)) {
            throw new Exception("Invalid log state: $state. Must be 1, 2, or 3.");
        }
        self::$logState = $state;
    }

    /**
     * Gets the current default log state.
     *
     * @return int Current log state
     */
    public static function getLogState(): int
    {
        return self::$logState;
    }

    /**
     * Gets the default log file path.
     *
     * @return string|null Default log file path
     */
    public static function getDefaultLogFile(): ?string
    {
        return !empty(ini_get('error_log')) ? ini_get('error_log') : dirname(__FILE__) . '/logs/app.log';
    }

    // ========== Private Helper Methods ==========

    /**
     * Writes a formatted message to the log file.
     *
     * @param string $message The formatted log message
     * @param string $logFile Path to the log file
     * @return bool Success status
     */
    private static function writeToLogFile(string $message, string $logFile): bool
    {
        // Ensure log file exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        return error_log($message . PHP_EOL, 3, $logFile);
    }

    /**
     * Normalizes error type to a string representation.
     *
     * @param string|int $errorType Error type
     * @return string Normalized error type string
     */
    private static function normalizeErrorType($errorType): string
    {
        if (is_string($errorType)) {
            return strtoupper($errorType);
        }

        // Map PHP error constants to strings
        $errorMap = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED',
        ];

        return $errorMap[$errorType] ?? 'UNKNOWN';
    }

    /**
     * Maps custom error types to PHP error constants for trigger_error.
     *
     * @param string|int $errorType Error type
     * @return int PHP error constant
     */
    private static function mapToPhpErrorType($errorType): int
    {
        if (is_int($errorType)) {
            return $errorType;
        }

        $typeMap = [
            self::USER_ERROR => E_USER_ERROR,
            self::USER_WARNING => E_USER_WARNING,
            self::USER_INFO => E_USER_NOTICE,
            self::CRITICAL => E_USER_ERROR,
            self::DEBUG => E_USER_NOTICE,
        ];

        return $typeMap[strtoupper($errorType)] ?? E_USER_NOTICE;
    }

    /**
     * Parses log file and returns structured array.
     *
     * @param string $logFile Path to log file
     * @param int $limit Maximum entries to return (0 = all)
     * @param string|null $logType Filter by log type
     * @return array Parsed log entries
     */
    private static function parseLogs(string $logFile, int $limit = 0, ?string $logType = null): array
    {
        $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $parsed = [];

        // Reverse to show newest first
        $logs = array_reverse($logs);

        foreach ($logs as $log) {
            $entry = self::parseLogEntry($log);
            
            if ($logType !== null && strcasecmp($entry['type'], $logType) !== 0) {
                continue;
            }

            $parsed[] = $entry;

            if ($limit > 0 && count($parsed) >= $limit) {
                break;
            }
        }

        return $parsed;
    }

    /**
     * Parses a single log entry.
     *
     * @param string $log Raw log line
     * @return array Parsed log entry
     */
    private static function parseLogEntry(string $log): array
    {
        $pattern = '/^\[([^\]]+)\]\s*\[([^\]]+)\]\s*(.+?)\s*\|\s*File:\s*(.+)$/';
        
        if (preg_match($pattern, $log, $matches)) {
            return [
                'timestamp' => $matches[1],
                'type' => $matches[2],
                'message' => trim($matches[3]),
                'file' => trim($matches[4]),
                'raw' => $log
            ];
        }

        return [
            'timestamp' => '',
            'type' => 'UNKNOWN',
            'message' => $log,
            'file' => '',
            'raw' => $log
        ];
    }

    /**
     * Extracts timestamp from log entry.
     *
     * @param string $log Log entry
     * @return string|null Timestamp or null
     */
    private static function extractTimestamp(string $log): ?string
    {
        if (preg_match('/^\[([^\]]+)\]/', $log, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Generates HTML for displaying logs.
     *
     * @param array $logs Parsed log entries
     * @param string $logFile Log file path
     * @return string HTML output
     */
    private static function generateLogsHTML(array $logs, string $logFile): string
    {
        $totalLogs = count($logs);
        $logFilename = basename($logFile);
        $currentTime = date('Y-m-d H:i:s');

        $html = <<<HTML
        <div class="lcs-logs-viewer-wrapper">
            <style>
                .lcs-logs-viewer-wrapper * { 
                    margin: 0; 
                    padding: 0; 
                    box-sizing: border-box; 
                }
                
                .lcs-logs-viewer-wrapper {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 20px;
                    min-height: 100vh;
                }
                
                .lcs-logs-container {
                    max-width: 1400px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    overflow: hidden;
                }
                
                .lcs-logs-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px;
                }
                
                .lcs-logs-header h1 {
                    font-size: 28px;
                    margin-bottom: 10px;
                }
                
                .lcs-logs-header-info {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-top: 15px;
                    flex-wrap: wrap;
                    gap: 15px;
                }
                
                .lcs-logs-stat {
                    background: rgba(255,255,255,0.2);
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-size: 14px;
                }
                
                .lcs-logs-controls {
                    padding: 20px 30px;
                    background: #f8f9fa;
                    border-bottom: 1px solid #e0e0e0;
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                
                .lcs-logs-btn {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 600;
                    transition: all 0.3s ease;
                    text-decoration: none;
                    display: inline-block;
                }
                
                .lcs-logs-btn-primary {
                    background: #667eea;
                    color: white;
                }
                
                .lcs-logs-btn-primary:hover {
                    background: #5568d3;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }
                
                .lcs-logs-btn-danger {
                    background: #f56565;
                    color: white;
                }
                
                .lcs-logs-btn-danger:hover {
                    background: #e53e3e;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(245, 101, 101, 0.4);
                }
                
                .lcs-logs-btn-secondary {
                    background: #48bb78;
                    color: white;
                }
                
                .lcs-logs-btn-secondary:hover {
                    background: #38a169;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
                }
                
                .lcs-logs-filter-section {
                    padding: 15px 30px;
                    background: white;
                    border-bottom: 1px solid #e0e0e0;
                }
                
                .lcs-logs-filter-group {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                    flex-wrap: wrap;
                }
                
                .lcs-logs-filter-label {
                    font-weight: 600;
                    color: #4a5568;
                }
                
                .lcs-logs-filter-tag {
                    padding: 6px 14px;
                    border-radius: 20px;
                    cursor: pointer;
                    font-size: 13px;
                    border: 2px solid #e0e0e0;
                    background: white;
                    transition: all 0.3s ease;
                }
                
                .lcs-logs-filter-tag:hover {
                    border-color: #667eea;
                    background: #f7fafc;
                }
                
                .lcs-logs-filter-tag.active {
                    background: #667eea;
                    color: white;
                    border-color: #667eea;
                }
                
                .lcs-logs-content {
                    padding: 30px;
                    max-height: 700px;
                    overflow-y: auto;
                }
                
                .lcs-log-entry {
                    background: #f7fafc;
                    border-left: 4px solid #cbd5e0;
                    padding: 16px;
                    margin-bottom: 12px;
                    border-radius: 8px;
                    transition: all 0.3s ease;
                }
                
                .lcs-log-entry:hover {
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    transform: translateX(4px);
                }
                
                .lcs-log-entry.USER_ERROR, 
                .lcs-log-entry.E_ERROR, 
                .lcs-log-entry.CRITICAL {
                    border-left-color: #f56565;
                    background: #fff5f5;
                }
                
                .lcs-log-entry.USER_WARNING, 
                .lcs-log-entry.E_WARNING {
                    border-left-color: #ed8936;
                    background: #fffaf0;
                }
                
                .lcs-log-entry.USER_INFO, 
                .lcs-log-entry.DEBUG {
                    border-left-color: #4299e1;
                    background: #ebf8ff;
                }
                
                .lcs-log-entry.LOGIN_ERROR, 
                .lcs-log-entry.SECURITY {
                    border-left-color: #9f7aea;
                    background: #faf5ff;
                }
                
                .lcs-log-entry.DATABASE_ERROR {
                    border-left-color: #ed64a6;
                    background: #fff5f7;
                }
                
                .lcs-log-entry.API_ERROR {
                    border-left-color: #f6ad55;
                    background: #fffaf0;
                }
                
                .lcs-log-entry.PERFORMANCE {
                    border-left-color: #48bb78;
                    background: #f0fff4;
                }
                
                .lcs-log-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 10px;
                    flex-wrap: wrap;
                    gap: 10px;
                }
                
                .lcs-log-type {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.5px;
                }
                
                .lcs-log-type.USER_ERROR, 
                .lcs-log-type.E_ERROR, 
                .lcs-log-type.CRITICAL {
                    background: #f56565;
                    color: white;
                }
                
                .lcs-log-type.USER_WARNING, 
                .lcs-log-type.E_WARNING {
                    background: #ed8936;
                    color: white;
                }
                
                .lcs-log-type.USER_INFO, 
                .lcs-log-type.DEBUG {
                    background: #4299e1;
                    color: white;
                }
                
                .lcs-log-type.LOGIN_ERROR, 
                .lcs-log-type.SECURITY {
                    background: #9f7aea;
                    color: white;
                }
                
                .lcs-log-type.DATABASE_ERROR {
                    background: #ed64a6;
                    color: white;
                }
                
                .lcs-log-type.API_ERROR {
                    background: #f6ad55;
                    color: white;
                }
                
                .lcs-log-type.PERFORMANCE {
                    background: #48bb78;
                    color: white;
                }
                
                .lcs-log-timestamp {
                    color: #718096;
                    font-size: 13px;
                    font-weight: 500;
                }
                
                .lcs-log-message {
                    color: #2d3748;
                    line-height: 1.6;
                    margin-bottom: 8px;
                    word-wrap: break-word;
                }
                
                .lcs-log-file {
                    color: #718096;
                    font-size: 12px;
                    font-family: 'Courier New', monospace;
                    background: rgba(0,0,0,0.05);
                    padding: 4px 8px;
                    border-radius: 4px;
                    display: inline-block;
                }
                
                .lcs-logs-empty-state {
                    text-align: center;
                    padding: 60px 20px;
                    color: #718096;
                }
                
                .lcs-logs-empty-icon {
                    font-size: 64px;
                    margin-bottom: 20px;
                }
                
                .lcs-logs-empty-state h3 {
                    font-size: 24px;
                    margin-bottom: 10px;
                    color: #4a5568;
                }
                
                .lcs-logs-content::-webkit-scrollbar {
                    width: 10px;
                }
                
                .lcs-logs-content::-webkit-scrollbar-track {
                    background: #f1f1f1;
                }
                
                .lcs-logs-content::-webkit-scrollbar-thumb {
                    background: #888;
                    border-radius: 5px;
                }
                
                .lcs-logs-content::-webkit-scrollbar-thumb:hover {
                    background: #555;
                }
            </style>
            
            <div class="lcs-logs-container">
                <div class="lcs-logs-header">
                    <h1>📋 System Logs Viewer</h1>
                    <div class="lcs-logs-header-info">
                        <div class="lcs-logs-stat">📁 File: {$logFilename}</div>
                        <div class="lcs-logs-stat">📊 Total Entries: {$totalLogs}</div>
                        <div class="lcs-logs-stat">🕒 Updated: {$currentTime}</div>
                    </div>
                </div>
                
                <div class="lcs-logs-controls">
                    <button class="lcs-logs-btn lcs-logs-btn-primary" onclick="location.reload()">🔄 Refresh</button>
                    <button class="lcs-logs-btn lcs-logs-btn-secondary" onclick="window.location.href='?export=json'">📥 Export JSON</button>
                    <button class="lcs-logs-btn lcs-logs-btn-secondary" onclick="window.location.href='?export=csv'">📥 Export CSV</button>
                    <button class="lcs-logs-btn lcs-logs-btn-danger" onclick="if(confirm('Are you sure you want to clear all logs?')) window.location.href='?clear=all'">🗑️ Clear All</button>
                </div>
                
                <div class="lcs-logs-filter-section">
                    <div class="lcs-logs-filter-group">
                        <span class="lcs-logs-filter-label">Filter:</span>
                        <span class="lcs-logs-filter-tag active" onclick="lcsFilterLogs('all', event)">All</span>
                        <span class="lcs-logs-filter-tag" onclick="lcsFilterLogs('USER_ERROR', event)">Errors</span>
                        <span class="lcs-logs-filter-tag" onclick="lcsFilterLogs('USER_WARNING', event)">Warnings</span>
                        <span class="lcs-logs-filter-tag" onclick="lcsFilterLogs('USER_INFO', event)">Info</span>
                        <span class="lcs-logs-filter-tag" onclick="lcsFilterLogs('CRITICAL', event)">Critical</span>
                        <span class="lcs-logs-filter-tag" onclick="lcsFilterLogs('SECURITY', event)">Security</span>
                    </div>
                </div>
                
                <div class="lcs-logs-content" id="lcsLogsContainer">
        HTML;

        if (empty($logs)) {
            $html .= <<<HTML
            <div class="lcs-logs-empty-state">
                <div class="lcs-logs-empty-icon">📭</div>
                <h3>No logs found</h3>
                <p>There are currently no log entries to display.</p>
            </div>
            HTML;
        } else {
            foreach ($logs as $log) {
                $typeClass = htmlspecialchars($log['type']);
                $timestamp = htmlspecialchars($log['timestamp']);
                $type = htmlspecialchars($log['type']);
                $message = htmlspecialchars($log['message']);
                $file = htmlspecialchars($log['file']);

                $html .= <<<HTML
                <div class="lcs-log-entry {$typeClass}" data-type="{$typeClass}">
                    <div class="lcs-log-header">
                        <span class="lcs-log-type {$typeClass}">{$type}</span>
                        <span class="lcs-log-timestamp">{$timestamp}</span>
                    </div>
                    <div class="lcs-log-message">{$message}</div>
                    <div class="lcs-log-file">📍 {$file}</div>
                </div>
                HTML;
            }
        }

        $html .= <<<HTML
            </div>
        </div>
        
        <script>
            function lcsFilterLogs(type, event) {
                const entries = document.querySelectorAll('.lcs-log-entry');
                const tags = document.querySelectorAll('.lcs-logs-filter-tag');
                
                tags.forEach(tag => tag.classList.remove('active'));
                event.target.classList.add('active');
                
                entries.forEach(entry => {
                    if (type === 'all') {
                        entry.style.display = 'block';
                    } else {
                        const entryType = entry.getAttribute('data-type');
                        entry.style.display = entryType.includes(type) ? 'block' : 'none';
                    }
                });
            }
        </script>
        </div>
        HTML;

        return $html;
    }

    /**
     * Generates HTML for no logs scenario.
     *
     * @param string $logFile Log file path
     * @return string HTML output
     */
    private static function generateNoLogsHTML(string $logFile): string
    {
        $logFilename = basename($logFile);

        return <<<HTML
        <div class="lcs-logs-viewer-wrapper">
            <style>
                .lcs-logs-viewer-wrapper * { 
                    margin: 0; 
                    padding: 0; 
                    box-sizing: border-box; 
                }
                
                .lcs-logs-viewer-wrapper {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 20px;
                }
                
                .lcs-logs-error-container {
                    background: white;
                    padding: 60px 40px;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    text-align: center;
                    max-width: 500px;
                }
                
                .lcs-logs-error-icon {
                    font-size: 80px;
                    margin-bottom: 20px;
                }
                
                .lcs-logs-error-container h1 {
                    color: #2d3748;
                    margin-bottom: 15px;
                    font-size: 28px;
                }
                
                .lcs-logs-error-container p {
                    color: #718096;
                    line-height: 1.6;
                    margin-bottom: 10px;
                }
                
                .lcs-logs-file-path {
                    background: #f7fafc;
                    padding: 10px;
                    border-radius: 8px;
                    font-family: 'Courier New', monospace;
                    font-size: 13px;
                    color: #4a5568;
                    margin-top: 20px;
                    word-break: break-all;
                }
            </style>
            
            <div class="lcs-logs-error-container">
                <div class="lcs-logs-error-icon">⚠️</div>
                <h1>Log File Not Found</h1>
                <p>The requested log file does not exist yet.</p>
                <p>Logs will be created automatically when errors are reported.</p>
                <div class="lcs-logs-file-path">📁 {$logFilename}</div>
            </div>
        </div>
        HTML;
    }
}