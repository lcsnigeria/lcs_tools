<?php
namespace LCSNG_EXT\DataValidation;

use LCSNG_EXT\DataValidation\LCS_InputSanitizer;

class LCS_InputValidation 
{
   
    /**
     * Extract name and email address from a string.
     *
     * @param string $input The input string in the format 'Name <email@example.com>', 'Name:email@example.com', or 'email@example.com'.
     * @return array|false Associative array with 'name' and 'email' on success, false on failure.
     */
    public static function extractMailerAddress($input) {

        $result = ['name' => '', 'email' => ''];

        // Regular expression to match the name and email address in either format
        if (preg_match('/(?:(.+?)\s*[:<]\s*)?([^>]+)>?/', $input, $matches)) {
            // Check if we have matches
            if (!empty($matches)) {
                foreach ( $matches as $match ) {
                    if (self::isEmailValid($match)) {
                        $result['email'] = LCS_InputSanitizer::sanitizeEmail(trim($match));
                    } else {
                        $result['name'] = trim($match);
                    }
                }
            }
        }
        return ($result['email'] !== '') ? $result : false;
    }

    /**
     * Validate email address.
     * 
     * @param string $email
     * @return bool
     */
    public static function isEmailValid($email) {
        // Remove all illegal characters from email
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        // Validate email address
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true; // Valid email
        } else {
            return false; // Invalid email
        }
    }

    /**
     * Validate username.
     * 
     * @param string $username
     * @return bool
     */
    public static function isUsernameValid($username) {
        // Example username validation: only allow alphanumeric characters and underscores, 3-20 characters long
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
    }

    /**
     * Check if a name contains any illegal marks such as digits and special characters.
     *
     * @param string $value The name to be checked.
     *
     * @return bool True if the name is valid, false otherwise.
     */
    public static function isNameValid($value) {
        if (empty($value)) {
            return false;
        }
        // Define a regular expression pattern to match unacceptable characters
        $invalidPattern = '/[\p{N}\p{S}]/u';

        // Check if the value contains unacceptable characters
        return !preg_match($invalidPattern, $value);
    }

    /**
     * Validate gender input.
     * 
     * @param string $gender
     * @return bool
     */
    public static function isGenderValid($gender) {
        // Example gender validation: only allow 'male', 'female', or 'other'
        $valid_genders = ['male', 'female', 'unspecified'];
        return in_array(strtolower($gender), $valid_genders);
    }

    /**
     * Extract the username from an email address and remove any dots.
     *
     * @param string $email The email address to process.
     *
     * @return string The username part of the email address with dots removed.
     */
    public static function extractEmailUsername($email) {
        // Get the part of the email before the "@" symbol
        $username = substr($email, 0, strpos($email, '@'));
        
        // Remove any dots from the username
        $username = str_replace('.', '', $username);

        return $username;
    }

    /**
     * Retrieve a substring of specified length from a string starting at a given point.
     *
     * @param string $characters The string from which to extract the substring.
     * @param int $limit The number of characters to extract.
     * @param int|null $startingPoint Optional. The starting point to begin extraction. Default is null, which sets the starting point to 0.
     *
     * @return string The extracted substring.
     */
    public static function retrieveCharacters($characters, $limit, $startingPoint = null) {
        // If starting point is not provided, set it to 0
        if ($startingPoint === null) {
            $startingPoint = 0;
        }
        
        // Extract the substring based on the starting point and limit
        $trimmed_str = substr($characters, $startingPoint, $limit);
        
        return $trimmed_str;
    }

    /**
     * Trim a name by removing all illegal marks such as digits and special characters.
     *
     * @param string $value The name to be trimmed.
     *
     * @return string The trimmed name.
     */
    public static function trimName($value) {
        // Use a regular expression to remove digits and special characters
        // while preserving diacritical marks and other marks
        return preg_replace('/[^\p{L}\s\'-]/u', '', $value);
    }

    /**
     * Validate and transform a date of birth with month names to the format `d-m-Y`.
     *
     * @param string $date The date string in various formats, potentially with month names.
     *
     * @return string|null The transformed date in the format `d-m-Y` or null if the date is invalid.
     */
    public static function validateDOB($date) {
        // Replace slashes, commas, and spaces with hyphens for consistency
        $date = str_replace(['/', ',', ' '], '-', $date);

        // Define patterns for matching month names and converting them to numeric representation
        $months = [
            'January' => '01', 'Jan' => '01',
            'February' => '02', 'Feb' => '02',
            'March' => '03', 'Mar' => '03',
            'April' => '04', 'Apr' => '04',
            'May' => '05',
            'June' => '06', 'Jun' => '06',
            'July' => '07', 'Jul' => '07',
            'August' => '08', 'Aug' => '08',
            'September' => '09', 'Sep' => '09',
            'October' => '10', 'Oct' => '10',
            'November' => '11', 'Nov' => '11',
            'December' => '12', 'Dec' => '12',
        ];

        // Match and replace month names with numeric values
        foreach ($months as $monthName => $monthNumber) {
            $date = str_ireplace($monthName, $monthNumber, $date);
        }

        // Convert the normalized date string to a DateTime object
        $dateTime = \DateTime::createFromFormat('d-m-Y', $date) ?: \DateTime::createFromFormat('Y-m-d', $date);

        // If the date is successfully parsed, return it in `d-m-Y` format
        if ($dateTime) {
            return $dateTime->format('d-m-Y');
        }

        // If the date is invalid, return null
        return null;
    }

    /**
     * Validate and transform a date of birth with various formats to the format `d-m-Y`.
     *
     * This method is a wrapper for `self::validateDOB`, providing compatibility for date of birth inputs.
     *
     * @param string $dateOfBirth The date of birth string in various formats, potentially with month names.
     *
     * @return string|null The transformed date in the format `d-m-Y`, or null if the date is invalid.
     */
    public static function validateDateOfBirth($dateOfBirth) {
        return self::validateDOB($dateOfBirth);
    }

    /**
     * Validate and transform a birthday with various formats to the format `d-m-Y`.
     *
     * This method is a wrapper for `self::validateDOB`, providing compatibility for birthday inputs.
     *
     * @param string $birthday The birthday string in various formats, potentially with month names.
     *
     * @return string|null The transformed date in the format `d-m-Y`, or null if the date is invalid.
     */
    public static function validateBirthday($birthday) {
        return self::validateDOB($birthday);
    }

    /**
     * Calculate the age of a user from their date of birth.
     *
     * @param string $dateOfBirth The date of birth in various formats.
     *
     * @return int The calculated age in years, or 0 if the format is incorrect.
     */
    public static function getAge($dateOfBirth) {
        // Define an array of possible date formats
        $formats = ['Y m d', 'Y-m-d', 'd/m/Y', 'd m Y', 'd-m-Y', 'Y/m/d'];

        foreach ($formats as $format) {
            $dob = \DateTime::createFromFormat($format, $dateOfBirth);
            if ($dob !== false) {
                $now = new \DateTime();
                $age = $now->diff($dob)->y;
                return $age;
            }
        }

        // If all parsing attempts fail, return 0 or handle as needed
        return 0;
    }

    /**
     * Generate a string that represents a conjunction of names.
     *
     * @param array $names An array of names to be joined into a string.
     *
     * @return string A string that joins the names appropriately based on their count.
     */
    public static function namesConjunction($names) {
        // Get the total number of names
        $total_names = count($names);

        if ($total_names == 1) {
            return $names[0];
        } elseif ($total_names == 2) {
            return $names[0] . " and " . $names[1];
        } else {
            // If more than 2 names are present
            $remaining_count = $total_names - 2;
            return $names[0] . ", " . $names[1] . " and " . $remaining_count . " other" . ($remaining_count > 1 ? 's' : '');
        }
    }

}