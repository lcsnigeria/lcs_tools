<?php
namespace LCSNG_EXT\Configs;

class LCS_IniConfig {

    /**
     * Enable OPcache and set recommended configuration values.
     *
     * @param int $memoryConsumption Memory consumption for OPcache in MB.
     * @param int $internedStringsBuffer Memory for interned strings in MB.
     * @param int $maxAcceleratedFiles Maximum number of files to cache.
     * @param int $revalidateFreq Time in seconds to check for script changes.
     *
     * @return bool True if OPcache is successfully enabled and configured, False otherwise.
     */
    public function set_op_cache($memoryConsumption = 128, $internedStringsBuffer = 8, $maxAcceleratedFiles = 10000, $revalidateFreq = 60)
    {
        // Enable OPcache
        if (!ini_set('opcache.enable', '1')) {
            return false; // Return false if enabling OPcache failed
        }

        // Set memory consumption for OPcache
        if (!ini_set('opcache.memory_consumption', $memoryConsumption)) {
            return false; // Return false if setting memory consumption failed
        }

        // Set interned strings buffer size
        if (!ini_set('opcache.interned_strings_buffer', $internedStringsBuffer)) {
            return false; // Return false if setting interned strings buffer failed
        }

        // Set max accelerated files
        if (!ini_set('opcache.max_accelerated_files', $maxAcceleratedFiles)) {
            return false; // Return false if setting max accelerated files failed
        }

        // Set revalidate frequency (how often OPcache checks for script changes)
        if (!ini_set('opcache.revalidate_freq', $revalidateFreq)) {
            return false; // Return false if setting revalidate frequency failed
        }

        return true; // Return true if all settings were successfully applied
    }

    /**
     * Disable OPcache.
     *
     * @return bool True if OPcache is successfully disabled, False otherwise.
     */
    public function unset_op_cache()
    {
        // Disable OPcache
        if (!ini_set('opcache.enable', '0')) {
            return false; // Return false if disabling OPcache failed
        }

        return true; // Return true if OPcache was successfully disabled
    }

    /**
     * Check if OPcache is enabled and configured properly.
     *
     * @return bool True if OPcache is enabled and configured, False otherwise.
     */
    public function is_set_op_cache()
    {
        // Check if OPcache is enabled
        if (ini_get('opcache.enable') == '1') {
            // Check if OPcache memory consumption is set properly
            if (ini_get('opcache.memory_consumption') && ini_get('opcache.memory_consumption') > 0) {
                // Check if OPcache max files is set
                if (ini_get('opcache.max_accelerated_files') && ini_get('opcache.max_accelerated_files') > 0) {
                    // Check if revalidate frequency is set properly
                    if (ini_get('opcache.revalidate_freq') && ini_get('opcache.revalidate_freq') >= 0) {
                        return true; // OPcache is enabled and configured properly
                    }
                }
            }
        }
        return false; // OPcache is either not enabled or not properly configured
    }

    /**
     * Set various PHP configuration options dynamically.
     *
     * This function allows setting key PHP configuration values at runtime, such as execution time,
     * memory limits, error reporting levels, file upload limits, and more.
     *
     * @param string $configType The configuration setting to modify. Supported options:
     *   - execution_time (max_execution_time): 
     *     The maximum time in seconds a script is allowed to run before it is terminated. 
     *     A value of `0` means unlimited execution time.
     *   - input_time (max_input_time): 
     *     The maximum time in seconds a script is allowed to parse input data, such as `$_POST` and `$_GET`. 
     *     A value of `-1` means unlimited input parsing time.
     *   - memory_limit (memory_limit): 
     *     The maximum amount of memory (in megabytes) a script is allowed to allocate. 
     *     Use `-1` for unlimited memory.
     *   - post_size (post_max_size): 
     *     The maximum size (in megabytes) of POST data that can be sent to the server. 
     *     This setting also affects file uploads, as it limits the combined size of all uploaded files.
     *   - upload_size (upload_max_filesize): 
     *     The maximum allowed size (in megabytes) for a single uploaded file. 
     *     Must be less than or equal to `post_max_size`.
     *   - file_uploads (file_uploads): 
     *     Enables (`1`) or disables (`0`) file uploads via PHP. 
     *     If disabled, file uploads will not be processed.
     *   - input_vars (max_input_vars): 
     *     The maximum number of input variables (`$_GET`, `$_POST`, and `$_COOKIE`) allowed per request. 
     *     Increasing this limit is useful for large forms.
     *   - display_errors (display_errors): 
     *     Determines whether errors should be displayed to the user (`1` for enabled, `0` for disabled). 
     *     Should be disabled in production environments.
     *   - log_errors (log_errors): 
     *     Enables (`1`) or disables (`0`) logging of errors to a file or system log. 
     *     Helps in debugging and monitoring errors without exposing them to users.
     *   - error_reporting (error_reporting): 
     *     Specifies which types of errors should be reported. 
     *     Accepts predefined constants like `E_ALL`, `E_NOTICE`, `E_WARNING`, etc.
     *   - session_lifetime (session.gc_maxlifetime): 
     *     The maximum lifetime (in seconds) of a session before it is considered expired and eligible for garbage collection.
     *   - session_save_path (session.save_path): 
     *     The directory where session files are stored. 
     *     Useful for setting a custom path when managing sessions manually.
     *   - date_timezone (date.timezone): 
     *     The default timezone for all date/time functions in PHP. 
     *     Example values: `UTC`, `America/New_York`, `Asia/Kolkata`.
     *
     * @param mixed $configValue The value to set for the configuration setting.
     *
     * @return bool True if the configuration was successfully updated, otherwise false.
     *
     * @throws \Exception If the provided configuration type is invalid or setting the value fails.
     *
     * @link https://www.php.net/manual/en/ini.list.php
     */
    public function set_php_config($configType, $configValue): bool
    {
        switch ($configType) {
            case 'execution_time':
                // Set maximum execution time (use set_time_limit)
                if (!is_numeric($configValue) || $configValue < 0) {
                    throw new \Exception('Invalid execution time limit');
                }
                set_time_limit((int) $configValue);
                break;
            case 'input_time':
                // Set maximum input time
                if (!ini_set('max_input_time', $configValue)) {
                    throw new \Exception('Failed to set input time limit');
                }
                break;
            case 'memory_limit':
                // Set memory limit
                if (!ini_set('memory_limit', $configValue . 'M')) {
                    throw new \Exception('Failed to set memory limit');
                }
                break;
            case 'post_size':
                // Set max POST size
                if (!ini_set('post_max_size', $configValue . 'M')) {
                    throw new \Exception('Failed to set post size limit');
                }
                break;
            case 'upload_size':
                // Set max upload file size
                if (!ini_set('upload_max_filesize', $configValue . 'M')) {
                    throw new \Exception('Failed to set upload size limit');
                }
                break;
            case 'file_uploads':
                // Enable or disable file uploads (1 or 0)
                if (!ini_set('file_uploads', (bool) $configValue ? '1' : '0')) {
                    throw new \Exception('Failed to set file uploads');
                }
                break;
            case 'input_vars':
                // Set max input variables
                if (!ini_set('max_input_vars', $configValue)) {
                    throw new \Exception('Failed to set input vars limit');
                }
                break;
            case 'display_errors':
                // Enable or disable error display
                if (!ini_set('display_errors', (bool) $configValue ? '1' : '0')) {
                    throw new \Exception('Failed to set display_errors');
                }
                break;
            case 'log_errors':
                // Enable or disable error logging
                if (!ini_set('log_errors', (bool) $configValue ? '1' : '0')) {
                    throw new \Exception('Failed to set log_errors');
                }
                break;
            case 'error_reporting':
                // Set the error reporting level
                if (!is_numeric($configValue) || !ini_set('error_reporting', (int) $configValue)) {
                    throw new \Exception('Failed to set error reporting level');
                }
                break;
            case 'session_lifetime':
                // Set session garbage collection max lifetime
                if (!ini_set('session.gc_maxlifetime', $configValue)) {
                    throw new \Exception('Failed to set session.gc_maxlifetime');
                }
                break;
            case 'session_save_path':
                // Set session save path
                if (!ini_set('session.save_path', $configValue)) {
                    throw new \Exception('Failed to set session save path');
                }
                break;
            case 'date_timezone':
                // Set default timezone
                if (!ini_set('date.timezone', $configValue)) {
                    throw new \Exception('Failed to set date.timezone');
                }
                break;
            default:
                throw new \Exception('Invalid configuration type provided');
        }

        return true;
    }

    /**
     * Retrieve the current value of a PHP configuration setting.
     *
     * This function fetches the current runtime value of key PHP settings.
     *
     * @param string $configType The configuration setting to retrieve. Supported options:
     *   - execution_time
     *   - input_time
     *   - memory_limit
     *   - post_size
     *   - upload_size
     *   - file_uploads
     *   - input_vars
     *   - display_errors
     *   - log_errors
     *   - error_reporting
     *   - session_lifetime
     *   - session_save_path
     *   - date_timezone
     * @return mixed The current value of the specified setting or false if invalid.
     *
     * @link https://www.php.net/manual/en/function.ini-get.php
     */
    public function get_php_config($configType): mixed
    {
        switch ($configType) {
            case 'execution_time':
                return ini_get('max_execution_time');
            case 'input_time':
                return ini_get('max_input_time');
            case 'memory_limit':
                return ini_get('memory_limit');
            case 'post_size':
                return ini_get('post_max_size');
            case 'upload_size':
                return ini_get('upload_max_filesize');
            case 'file_uploads':
                return ini_get('file_uploads') === '1';
            case 'input_vars':
                return ini_get('max_input_vars');
            case 'display_errors':
                return ini_get('display_errors') === '1';
            case 'log_errors':
                return ini_get('log_errors') === '1';
            case 'error_reporting':
                return ini_get('error_reporting');
            case 'session_lifetime':
                return ini_get('session.gc_maxlifetime');
            case 'session_save_path':
                return ini_get('session.save_path');
            case 'date_timezone':
                return ini_get('date.timezone');
            default:
                return false;
        }
    }

}