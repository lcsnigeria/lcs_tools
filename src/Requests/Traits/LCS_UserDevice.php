<?php
namespace LCSNG\Tools\Requests\Traits;

use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;

/**
 * Trait LCS_UserDevice
 *
 * Provides reusable methods to extract detailed device and client metadata
 * from a given user agent string using DeviceDetector and Client Hints.
 *
 * This trait can be used in any class that needs access to parsed device info
 * such as OS, browser, device type, brand, model, and formatted display strings.
 *
 * @package LCSNG\Tools\Requests\Traits
 */
trait LCS_UserDevice
{
    /**
     * Returns an associative array of parsed device or bot details from a given user agent string.
     *
     * The array includes operating system, browser, device brand and model,
     * device type (e.g. smartphone, desktop), and a formatted device info string.
     * If the request is from a bot, minimal bot information is returned instead.
     *
     * @param string|null $userAgent        The user-agent string to parse (defaults to current HTTP request).
     * @param bool        $truncateVersion  Whether to truncate version info (default: false).
     *
     * @return array An array of device or bot details.
     */
    protected static function getDeviceInfo(?string $userAgent = null, bool $truncateVersion = false): array
    {
        // Default to current request's user-agent
        $userAgent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN');

        // Control version truncation
        if (!$truncateVersion) {
            AbstractDeviceParser::setVersionTruncation(AbstractDeviceParser::VERSION_TRUNCATION_NONE);
        }

        // Create and parse DeviceDetector instance
        $dd = new DeviceDetector($userAgent);
        $dd->parse();

        if ($dd->isBot()) {
            $bot = $dd->getBot();
            return [
                'is_bot'      => true,
                'bot_name'    => $bot['name'] ?? 'Unknown Bot',
                'device_info' => 'Bot: ' . ($bot['name'] ?? 'Unknown')
            ];
        }

        $os     = $dd->getOs();
        $client = $dd->getClient();       // Browser
        $device = $dd->getDeviceName();   // smartphone, desktop, etc.
        $brand  = $dd->getBrandName();    // e.g. Samsung
        $model  = $dd->getModel();        // e.g. SM-A715F

        // Fallback: Use Client Hints if brand or model is empty
        $ch_platform = $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? null;
        $ch_model    = $_SERVER['HTTP_SEC_CH_UA_MODEL'] ?? null;
        $ch_mobile   = $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? null;

        // Safely trim headers, avoiding null values
        $ch_platform = is_string($ch_platform) ? trim($ch_platform, '"') : null;
        $ch_model    = is_string($ch_model) ? trim($ch_model, '"') : null;
        $ch_mobile   = is_string($ch_mobile) ? trim($ch_mobile, '?1') : null;

        if (!$brand && $ch_platform) {
            $brand = $ch_platform;
        }

        if (!$model && $ch_model) {
            $model = $ch_model;
        }

        $device_string = trim("{$brand} {$model}") ?: 'Unknown Device';
        $os_name = $os['name'] ?? ($ch_platform ?? 'Unknown OS');
        $os_version = $os['version'] ?? '';
        $client_name = $client['name'] ?? 'Unknown Browser';
        $client_version = $client['version'] ?? '';
        return [
            'is_bot'          => false,
            'device_type'     => $device ?? 'Unknown',
            'brand'           => $brand ?? 'Unknown',
            'model'           => $model ?? 'Unknown',
            'os_name'         => $os['name'] ?? ($ch_platform ?? 'Unknown'),
            'os_version'      => $os['version'] ?? '',
            'browser_name'    => $client_name,
            'browser_version' => $client_version,
            'device_info'     => "{$device_string} ({$os_name} {$os_version}) - {$client_name} {$client_version} [{$device}]"
        ];
    }

    /**
     * Determines whether the current device is a mobile device.
     *
     * @param string|null $userAgent
     * @return bool
     */
    protected static function isMobileDevice(?string $userAgent = null): bool
    {
        $info = self::getDeviceInfo($userAgent);
        return strtolower($info['device_type']) === 'smartphone';
    }

    /**
     * Determines whether the current device is a tablet.
     *
     * @param string|null $userAgent
     * @return bool
     */
    protected static function isTabletDevice(?string $userAgent = null): bool
    {
        $info = self::getDeviceInfo($userAgent);
        return strtolower($info['device_type']) === 'tablet';
    }

    /**
     * Determines whether the current device is a desktop.
     *
     * @param string|null $userAgent
     * @return bool
     */
    protected static function isDesktopDevice(?string $userAgent = null): bool
    {
        $info = self::getDeviceInfo($userAgent);
        return strtolower($info['device_type']) === 'desktop';
    }

    /**
     * Determines whether the current request is from a bot.
     *
     * @param string|null $userAgent
     * @return bool
     */
    protected static function isBot(?string $userAgent = null): bool
    {
        $info = self::getDeviceInfo($userAgent);
        return $info['is_bot'] ?? false;
    }

    /**
     * Returns a formatted device info string for logging or display.
     *
     * @param string|null $userAgent
     * @return string
     */
    protected static function getFormattedDeviceInfo(?string $userAgent = null): string
    {
        $info = self::getDeviceInfo($userAgent);
        return $info['device_info'] ?? 'Unknown Device';
    }
}