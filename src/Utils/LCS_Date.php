<?php
namespace LCSNG\Tools\Utils;

/**
 * Class LCS_Date
 *
 * Provides utilities for date and time-related operations.
 */
class LCS_Date {
    /**
     * Provides a time-based greeting.
     *
     * This method determines the current time of day and returns an appropriate greeting:
     * "Good Morning," "Good Afternoon," or "Good Evening."
     *
     * - Morning: 12:00 AM to 11:59 AM
     * - Afternoon: 12:00 PM to 4:59 PM
     * - Evening: 5:00 PM to 11:59 PM
     *
     * @return string The appropriate greeting based on the current time.
     */
    public static function greetings(): string {
        // Get the current hour in 24-hour format
        $currentHour = (int)date('H');

        // Determine the greeting based on the time range
        if ($currentHour >= 0 && $currentHour < 12) {
            return "Good Morning";
        } elseif ($currentHour >= 12 && $currentHour < 17) {
            return "Good Afternoon";
        } else {
            return "Good Evening";
        }
    }

    /**
     * Get the current date or a specific part of it based on the specification and data type.
     *
     * This method returns the full current date and time if no specification is provided. If a specification
     * is given, it extracts that part of the date (year, month, day, time, or day name) and returns it in
     * either numeric or textual format based on the $text_type parameter. Invalid specifications default
     * to returning the day name.
     *
     * @param string|null $specification Optional. The part of the date to retrieve ('year', 'month', 'day', 'time', or defaults to day name if invalid).
     * @param bool $text_type Optional. If true, returns textual representation (e.g., "April" for month, "Tuesday" for day); defaults to false (numeric).
     * @return mixed|string The current date or the specified part of it.
     *
     * @example
     * echo ::getDate();                    // Outputs: "2025-04-01 14:30:00" (full date/time)
     * echo ::getDate('year');              // Outputs: "2025" (numeric year)
     * echo ::getDate('month');             // Outputs: "04" (numeric month)
     * echo ::getDate('month', true);       // Outputs: "April" (textual month)
     * echo ::getDate('day');               // Outputs: "1" (numeric day)
     * echo ::getDate('day', true);         // Outputs: "Tuesday" (textual day name)
     * echo ::getDate('time');              // Outputs: "14:30:00" (current time)
     * echo ::getDate('invalid');           // Outputs: "Tuesday" (default day name)
     */
    public static function getDate($specification = null, $text_type = false) {
        // Check if a specific part of the date is requested
        if ($specification !== null) {
            // Get the current date
            $current_date = date('Y-m-d');
            
            // Check if the specification is 'time'
            if ($specification === 'time') {
                // Return only the current time
                return date('H:i:s');
            }
            
            // Get the specified part of the date based on the specification
            switch ($specification) {
                case 'year':
                    $date_part = date('Y', strtotime($current_date));
                    break;
                case 'month':
                    $date_part = date('m', strtotime($current_date));
                    break;
                case 'day':
                    $date_part = date('j', strtotime($current_date)); // Use 'j' to get the day without leading zeros
                    break;
                default:
                    $date_part = date('l', strtotime($current_date)); // Get the name of the day by default
            }
            
            // Convert the date part to text if specified
            if ($text_type === true) {
                switch ($specification) {
                    case 'month':
                        $date_part = date('F', strtotime("2000-$date_part-01")); // Using a fixed year to avoid issues with non-English locales
                        break;
                    case 'day':
                        $date_part = date('l', strtotime($current_date)); // Get the name of the day
                        break;
                }
            }
            
            return $date_part;
        } else {
            // Return the normal date value
            return date('Y-m-d H:i:s');
        }
    }

    /**
     * Returns a formatted or timestamp-based interpretation of a relative date string.
     *
     * This utility method allows easy transformation of human-friendly or programmatic date strings into
     * either Unix timestamps or formatted date strings. Useful for interpreting user inputs, scheduling,
     * or formatting stored datetime values.
     *
     * Supported special format values (case-insensitive):
     *  - 'getAsTime', 'time', 'seconds' → Returns timestamp
     *  - 'standard' → 'YYYY-MM-DD'
     *  - 'modern' → 'DD-MM-YYYY'
     *  - 'standard with time' → 'YYYY-MM-DD HH:MM:SS'
     *  - 'modern with time' → 'DD-MM-YYYY HH:MM:SS'
     *  - Any other valid `DateTime::format()` string will be respected.
     *
     * @param string $string A relative or absolute date/time expression (e.g. 'now', '+1 week', '2024/12/31', 'next Monday').
     * @param string $format Output format type or a valid PHP date format string.
     *
     * @return int|string Returns a Unix timestamp (int) or a formatted date string.
     *
     * @throws \Exception If the input string is invalid or not parsable by DateTime.
     *
     * @example
     *   ::getRelativeDate();                              // returns current timestamp
     *   ::getRelativeDate('2 days');                      // timestamp two days from now
     *   ::getRelativeDate('2025/01/01', 'Y-m-d');         // '2025-01-01'
     *   ::getRelativeDate('next Friday', 'l');            // 'Friday'
     *   ::getRelativeDate('1 day', 'modern');             // '12-05-2025'
     *   ::getRelativeDate('now', 'standard with time');   // '2025-05-11 18:25:00'
     */
    public static function getRelativeDate($string = 'now', $format = 'getAsTime') {
        try {
            // Create a DateTime object from the input string
            $date = new \DateTime($string);
            $format = strtolower(trim($format));

            // Return timestamp for time-based requests
            if (in_array($format, ['getastime', 'time', 'seconds'], true)) {
                return $date->getTimestamp();
            }

            // Handle predefined readable formats
            switch ($format) {
                case 'standard':
                    return $date->format('Y-m-d');
                case 'modern':
                    return $date->format('d-m-Y');
                case 'standard with time':
                    return $date->format('Y-m-d H:i:s');
                case 'modern with time':
                    return $date->format('d-m-Y H:i:s');
                default:
                    // Assume it's a valid PHP date format string
                    return $date->format($format);
            }

        } catch (\Exception $e) {
            // Propagate error with descriptive message
            throw new \Exception("Invalid date string: '$string'. " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the current year.
     *
     * @return string The current year in numeric format.
     */
    public static function getYear(): string {
        return self::getDate('year');
    }

    /**
     * Get the current month.
     *
     * @param bool $text_type Optional. If true, returns the month in textual format (e.g., "April"); defaults to false (numeric).
     * @return string The current month in numeric or textual format.
     */
    public static function getMonth(bool $text_type = false): string {
        return self::getDate('month', $text_type);
    }

    /**
     * Get the current day.
     *
     * @param bool $text_type Optional. If true, returns the day in textual format (e.g., "Tuesday"); defaults to false (numeric).
     * @return string The current day in numeric or textual format.
     */
    public static function getDay(bool $text_type = false): string {
        return self::getDate('day', $text_type);
    }

    /**
     * Get the current time.
     *
     * @return string The current time in "HH:MM:SS" format.
     */
    public static function getTime(): string {
        return self::getDate('time');
    }

    /**
     * Get the name of the current day.
     *
     * @return string The name of the current day (e.g., "Monday").
     */
    public static function getDayName(): string {
        return date('l');
    }

    /**
     * Get the number of days in the current month.
     *
     * @return int The number of days in the current month.
     */
    public static function getDaysInMonth(): int {
        return (int)date('t');
    }

    /**
     * Check if a given year or the current year is a leap year.
     *
     * @param int|null $year Optional. The year to check. If null, the current year is used.
     * @return bool True if the given year or the current year is a leap year, false otherwise.
     */
    public static function isLeapYear(?int $year = null): bool {
        $year = $year ?? (int)self::getYear();
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }

    /**
     * Get the difference between two dates, either in days or seconds.
     *
     * Calculates the absolute difference between two dates. By default, returns the difference in days.
     * If $compareWithTime is true, returns the difference in seconds using Unix timestamps.
     *
     * @param string $date1 The first date in "YYYY-MM-DD" format.
     * @param string $date2 The second date in "YYYY-MM-DD" format.
     * @param bool $compareWithTime Optional. If true, returns the difference in seconds; defaults to false (days).
     * @return int The absolute difference between the two dates, in days or seconds based on $compareWithTime.
     */
    public static function getDateDifference(string $date1, string $date2, bool $compareWithTime = false): int {
        $datetime1 = new \DateTime($date1);
        $datetime2 = new \DateTime($date2);
        
        if ($compareWithTime) {
            // Convert to Unix timestamps and calculate difference in seconds
            $timestamp1 = $datetime1->getTimestamp();
            $timestamp2 = $datetime2->getTimestamp();
            return abs($timestamp1 - $timestamp2);
        }
        
        // Default: return difference in days
        return abs($datetime1->diff($datetime2)->days);
    }

    /**
     * Get the time difference between two dates in seconds.
     *
     * This method utilizes the getDateDifference method with $compareWithTime set to true.
     *
     * @param string $date1 The first date in "YYYY-MM-DD" format.
     * @param string $date2 The second date in "YYYY-MM-DD" format.
     * @return int The absolute difference between the two dates in seconds.
     */
    public static function getTimeDifference(string $date1, string $date2): int {
        return self::getDateDifference($date1, $date2, true);
    }

    /**
     * Add a specific number of days to a given date, preserving time if provided.
     *
     * @param string $date The starting date in "YYYY-MM-DD" or "YYYY-MM-DD HH:MM:SS" format.
     * @param int $days The number of days to add.
     * @return string The resulting date in "YYYY-MM-DD" format (if no time provided) or "YYYY-MM-DD HH:MM:SS" (if time included).
     */
    public static function addDaysToDate(string $date, int $days): string {
        $datetime = new \DateTime($date);
        $datetime->modify("+$days days");
        // Check if original input included time; adjust output format accordingly
        return strpos($date, ':') !== false ? $datetime->format('Y-m-d H:i:s') : $datetime->format('Y-m-d');
    }

    /**
     * Subtract a specific number of days from a given date, preserving time if provided.
     *
     * @param string $date The starting date in "YYYY-MM-DD" or "YYYY-MM-DD HH:MM:SS" format.
     * @param int $days The number of days to subtract.
     * @return string The resulting date in "YYYY-MM-DD" format (if no time provided) or "YYYY-MM-DD HH:MM:SS" (if time included).
     */
    public static function subtractDaysFromDate(string $date, int $days): string {
        $datetime = new \DateTime($date);
        $datetime->modify("-$days days");
        // Check if original input included time; adjust output format accordingly
        return strpos($date, ':') !== false ? $datetime->format('Y-m-d H:i:s') : $datetime->format('Y-m-d');
    }

}