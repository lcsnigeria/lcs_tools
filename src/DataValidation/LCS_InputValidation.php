<?php
namespace LCSNG\Tools\DataValidation;

use LCSNG\Tools\DataValidation\LCS_InputSanitizer;

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
     * Removes accents and special characters from a string, converting them to their closest ASCII equivalents.
     *
     * This function is useful for sanitizing input by replacing accented and special characters with standard
     * Latin characters, which can help prevent issues with encoding and storage in databases.
     *
     * @param string $string The input string from which to remove accents.
     * @return string The sanitized string with accents and special characters replaced.
     */
    public static function removeAccents($string) {

        if (!class_exists('Normalizer')) {
            throw new \Exception('The Normalizer class is not available. Please enable the intl extension.');
        }

        // Normalize the string to the canonical decomposition form
        $string = \Normalizer::normalize($string, \Normalizer::FORM_D);
        
        // Mapping of accented and special characters to their ASCII equivalents.
        $chars = array(

            // Latin-1 Supplement
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'TH', 'ß' => 's',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y',

            // Latin Extended-A
            'Ā' => 'A', 'ā' => 'a', 'Ă' => 'A', 'ă' => 'a', 'Ą' => 'A', 'ą' => 'a', 'Ć' => 'C', 'ć' => 'c', 'Ĉ' => 'C', 'ĉ' => 'c', 'Ċ' => 'C', 'ċ' => 'c',
            'Č' => 'C', 'č' => 'c', 'Ď' => 'D', 'ď' => 'd', 'Đ' => 'D', 'đ' => 'd', 'Ē' => 'E', 'ē' => 'e', 'Ĕ' => 'E', 'ĕ' => 'e', 'Ė' => 'E', 'ė' => 'e',
            'Ę' => 'E', 'ę' => 'e', 'Ě' => 'E', 'ě' => 'e', 'Ĝ' => 'G', 'ĝ' => 'g', 'Ğ' => 'G', 'ğ' => 'g', 'Ġ' => 'G', 'ġ' => 'g', 'Ģ' => 'G', 'ģ' => 'g',
            'Ĥ' => 'H', 'ĥ' => 'h', 'Ħ' => 'H', 'ħ' => 'h', 'Ĩ' => 'I', 'ĩ' => 'i', 'Ī' => 'I', 'ī' => 'i', 'Ĭ' => 'I', 'ĭ' => 'i', 'Į' => 'I', 'į' => 'i',
            'İ' => 'I', 'ı' => 'i', 'Ĳ' => 'IJ', 'ĳ' => 'ij', 'Ĵ' => 'J', 'ĵ' => 'j', 'Ķ' => 'K', 'ķ' => 'k', 'ĸ' => 'k', 'Ĺ' => 'L', 'ĺ' => 'l', 'Ļ' => 'L',
            'ļ' => 'l', 'Ľ' => 'L', 'ľ' => 'l', 'Ŀ' => 'L', 'ŀ' => 'l', 'Ł' => 'L', 'ł' => 'l', 'Ń' => 'N', 'ń' => 'n', 'Ņ' => 'N', 'ņ' => 'n', 'Ň' => 'N',
            'ň' => 'n', 'ŉ' => 'n', 'Ŋ' => 'N', 'ŋ' => 'n', 'Ō' => 'O', 'ō' => 'o', 'Ŏ' => 'O', 'ŏ' => 'o', 'Ő' => 'O', 'ő' => 'o', 'Œ' => 'OE', 'œ' => 'oe',
            'Ŕ' => 'R', 'ŕ' => 'r', 'Ŗ' => 'R', 'ŗ' => 'r', 'Ř' => 'R', 'ř' => 'r', 'Ś' => 'S', 'ś' => 's', 'Ŝ' => 'S', 'ŝ' => 's', 'Ş' => 'S', 'ş' => 's',
            'Š' => 'S', 'š' => 's', 'Ţ' => 'T', 'ţ' => 't', 'Ť' => 'T', 'ť' => 't', 'Ŧ' => 'T', 'ŧ' => 't', 'Ũ' => 'U', 'ũ' => 'u', 'Ū' => 'U', 'ū' => 'u',
            'Ŭ' => 'U', 'ŭ' => 'u', 'Ů' => 'U', 'ů' => 'u', 'Ű' => 'U', 'ű' => 'u', 'Ų' => 'U', 'ų' => 'u', 'Ŵ' => 'W', 'ŵ' => 'w', 'Ŷ' => 'Y', 'ŷ' => 'y',
            'Ÿ' => 'Y', 'Ź' => 'Z', 'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z', 'Ž' => 'Z', 'ž' => 'z', 'ſ' => 's',

            // Latin Extended-B
            'Ș' => 'S', 'ș' => 's', 'Ț' => 'T', 'ț' => 't',

            // Euro and GBP
            '€' => 'E', '£' => '',

            // Other special characters
            'ƒ' => 'f',

            // Greek
            'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => '8', 'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 
            'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P', 'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W',
            'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I', 'Ϋ' => 'Y',
            'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8', 'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 
            'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p', 'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w', 
            'ά' => 'a', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'w', 'ς' => 's', 'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',

            // Turkish
            'Ş' => 'S', 'ş' => 's', 'İ' => 'I', 'ı' => 'i', 'Ç' => 'C', 'ç' => 'c', 'Ü' => 'U', 'ü' => 'u', 'Ö' => 'O', 'ö' => 'o', 'Ğ' => 'G', 'ğ' => 'g',

            // Russian
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Ts',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ы' => 'Y', 'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

            // Ukrainian
            'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
            'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',

            // Czech
            'Č' => 'C', 'č' => 'c', 'Ď' => 'D', 'ď' => 'd', 'Ě' => 'E', 'ě' => 'e', 'Ň' => 'N', 'ň' => 'n', 'Ř' => 'R', 'ř' => 'r', 'Š' => 'S', 'š' => 's',
            'Ť' => 'T', 'ť' => 't', 'Ů' => 'U', 'ů' => 'u', 'Ž' => 'Z', 'ž' => 'z',

            // Polish
            'Ą' => 'A', 'ą' => 'a', 'Ć' => 'C', 'ć' => 'c', 'Ę' => 'E', 'ę' => 'e', 'Ł' => 'L', 'ł' => 'l', 'Ń' => 'N', 'ń' => 'n', 'Ś' => 'S', 'ś' => 's',
            'Ź' => 'Z', 'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z',

            // Latvian
            'Ā' => 'A', 'ā' => 'a', 'Č' => 'C', 'č' => 'c', 'Ē' => 'E', 'ē' => 'e', 'Ģ' => 'G', 'ģ' => 'g', 'Ī' => 'I', 'ī' => 'i', 'Ķ' => 'K', 'ķ' => 'k',
            'Ļ' => 'L', 'ļ' => 'l', 'Ņ' => 'N', 'ņ' => 'n', 'Š' => 'S', 'š' => 's', 'Ū' => 'U', 'ū' => 'u', 'Ž' => 'Z', 'ž' => 'z',

            // Lithuanian
            'Ą' => 'A', 'ą' => 'a', 'Č' => 'C', 'č' => 'c', 'Ę' => 'E', 'ę' => 'e', 'Ė' => 'E', 'ė' => 'e', 'Į' => 'I', 'į' => 'i', 'Š' => 'S', 'š' => 's',
            'Ų' => 'U', 'ų' => 'u', 'Ū' => 'U', 'ū' => 'u', 'Ž' => 'Z', 'ž' => 'z',

            // Vietnamese
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O',
            'Õ' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Ă' => 'A', 'ă' => 'a', 'Đ' => 'D', 'đ' => 'd', 'Ĩ' => 'I', 'ĩ' => 'i', 'Ũ' => 'U', 'ũ' => 'u', 'Ơ' => 'O',
            'ơ' => 'o', 'Ư' => 'U', 'ư' => 'u', 'Ạ' => 'A', 'ạ' => 'a', 'Ả' => 'A', 'ả' => 'a', 'Ấ' => 'A', 'ấ' => 'a', 'Ầ' => 'A', 'ầ' => 'a', 'Ẩ' => 'A',
            'ẩ' => 'a', 'Ẫ' => 'A', 'ẫ' => 'a', 'Ậ' => 'A', 'ậ' => 'a', 'Ắ' => 'A', 'ắ' => 'a', 'Ằ' => 'A', 'ằ' => 'a', 'Ẳ' => 'A', 'ẳ' => 'a', 'Ẵ' => 'A',
            'ẵ' => 'a', 'Ặ' => 'A', 'ặ' => 'a', 'Ẹ' => 'E', 'ẹ' => 'e', 'Ẻ' => 'E', 'ẻ' => 'e', 'Ẽ' => 'E', 'ẽ' => 'e', 'Ế' => 'E', 'ế' => 'e', 'Ề' => 'E',
            'ề' => 'e', 'Ể' => 'E', 'ể' => 'e', 'Ễ' => 'E', 'ễ' => 'e', 'Ệ' => 'E', 'ệ' => 'e', 'Ỉ' => 'I', 'ỉ' => 'i', 'Ị' => 'I', 'ị' => 'i', 'Ọ' => 'O',
            'ọ' => 'o', 'Ỏ' => 'O', 'ỏ' => 'o', 'Ố' => 'O', 'ố' => 'o', 'Ồ' => 'O', 'ồ' => 'o', 'Ổ' => 'O', 'ổ' => 'o', 'Ỗ' => 'O', 'ỗ' => 'o', 'Ộ' => 'O',
            'ộ' => 'o', 'Ớ' => 'O', 'ớ' => 'o', 'Ờ' => 'O', 'ờ' => 'o', 'Ở' => 'O', 'ở' => 'o', 'Ỡ' => 'O', 'ỡ' => 'o', 'Ợ' => 'O', 'ợ' => 'o', 'Ụ' => 'U',
            'ụ' => 'u', 'Ủ' => 'U', 'ủ' => 'u', 'Ứ' => 'U', 'ứ' => 'u', 'Ừ' => 'U', 'ừ' => 'u', 'Ử' => 'U', 'ử' => 'u', 'Ữ' => 'U', 'ữ' => 'u', 'Ự' => 'U', 
            'ự' => 'u',

            // Other characters
            '†' => ' ', '“' => '"', '”' => '"', '‘' => "'", '’' => "'", '•' => ' ', '–' => '-', '—' => '-', '¡' => '!', '¿' => '?', '©' => '(c)', '®' => '(r)',

            // Arabic
            'أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ؤ' => 'w', 'ئ' => 'y', 'ء' => '', 'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 
            'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'dh', 'ع' => 'a', 'غ' => 'gh',
            'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h', 'و' => 'w', 'ي' => 'y', 'ى' => 'a', 'ة' => 't',

            // Hebrew
            'א' => 'a', 'ב' => 'b', 'ג' => 'g', 'ד' => 'd', 'ה' => 'h', 'ו' => 'v', 'ז' => 'z', 'ח' => 'h', 'ט' => 't', 'י' => 'y', 'ך' => 'k', 'כ' => 'k',
            'ל' => 'l', 'ם' => 'm', 'מ' => 'm', 'ן' => 'n', 'נ' => 'n', 'ס' => 's', 'ע' => 'a', 'ף' => 'p', 'פ' => 'p', 'ץ' => 'ts', 'צ' => 'ts', 'ק' => 'k',
            'ר' => 'r', 'ש' => 'sh', 'ת' => 't',

            // Thai
            'ก' => 'k', 'ข' => 'kh', 'ฃ' => 'kh', 'ค' => 'kh', 'ฅ' => 'kh', 'ฆ' => 'kh', 'ง' => 'ng', 'จ' => 'ch', 'ฉ' => 'ch', 'ช' => 'ch', 'ซ' => 's', 'ฌ' => 'ch',
            'ญ' => 'y', 'ฎ' => 'd', 'ฏ' => 't', 'ฐ' => 'th', 'ฑ' => 'th', 'ฒ' => 'th', 'ณ' => 'n', 'ด' => 'd', 'ต' => 't', 'ถ' => 'th', 'ท' => 'th', 'ธ' => 'th',
            'น' => 'n', 'บ' => 'b', 'ป' => 'p', 'ผ' => 'ph', 'ฝ' => 'f', 'พ' => 'ph', 'ฟ' => 'f', 'ภ' => 'ph', 'ม' => 'm', 'ย' => 'y', 'ร' => 'r', 'ฤ' => 'rue',
            'ล' => 'l', 'ฦ' => 'lue', 'ว' => 'w', 'ศ' => 's', 'ษ' => 's', 'ส' => 's', 'ห' => 'h', 'ฬ' => 'l', 'อ' => 'o', 'ฮ' => 'h',

            // Georgian
            'ა' => 'a', 'ბ' => 'b', 'გ' => 'g', 'დ' => 'd', 'ე' => 'e', 'ვ' => 'v', 'ზ' => 'z', 'თ' => 't', 'ი' => 'i', 'კ' => 'k', 'ლ' => 'l', 'მ' => 'm',
            'ნ' => 'n', 'ო' => 'o', 'პ' => 'p', 'ჟ' => 'zh', 'რ' => 'r', 'ს' => 's', 'ტ' => 't', 'უ' => 'u', 'ფ' => 'p', 'ქ' => 'k', 'ღ' => 'gh', 'ყ' => 'q',
            'შ' => 'sh', 'ჩ' => 'ch', 'ც' => 'ts', 'ძ' => 'dz', 'წ' => 'ts', 'ჭ' => 'ch', 'ხ' => 'kh', 'ჯ' => 'j', 'ჰ' => 'h',

            // Armenian
            'Ա' => 'A', 'Բ' => 'B', 'Գ' => 'G', 'Դ' => 'D', 'Ե' => 'E', 'Զ' => 'Z', 'Է' => 'E', 'Ը' => 'Y', 'Թ' => 'T', 'Ժ' => 'Zh', 'Ի' => 'I', 'Լ' => 'L',
            'Խ' => 'Kh', 'Ծ' => 'Ts', 'Կ' => 'K', 'Հ' => 'H', 'Ձ' => 'Dz', 'Ղ' => 'Gh', 'Ճ' => 'Ch', 'Մ' => 'M', 'Յ' => 'Y', 'Ն' => 'N', 'Շ' => 'Sh', 'Ո' => 'Vo',
            'Չ' => 'Ch', 'Պ' => 'P', 'Ջ' => 'J', 'Ռ' => 'R', 'Ս' => 'S', 'Վ' => 'V', 'Տ' => 'T', 'Ր' => 'R', 'Ց' => 'C', 'Ւ' => 'U', 'Փ' => 'P', 'Ք' => 'Q',
            'Օ' => 'O', 'Ֆ' => 'F', 'ա' => 'a', 'բ' => 'b', 'գ' => 'g', 'դ' => 'd', 'ե' => 'e', 'զ' => 'z', 'է' => 'e', 'ը' => 'y', 'թ' => 't', 'ժ' => 'zh',
            'ի' => 'i', 'լ' => 'l', 'խ' => 'kh', 'ծ' => 'ts', 'կ' => 'k', 'հ' => 'h', 'ձ' => 'dz', 'ղ' => 'gh', 'ճ' => 'ch', 'մ' => 'm', 'յ' => 'y', 'ն' => 'n',
            'շ' => 'sh', 'ո' => 'vo', 'չ' => 'ch', 'պ' => 'p', 'ջ' => 'j', 'ռ' => 'r', 'ս' => 's', 'վ' => 'v', 'տ' => 't', 'ր' => 'r', 'ց' => 'c', 'ւ' => 'u',
            'փ' => 'p', 'ք' => 'q', 'օ' => 'o', 'ֆ' => 'f',


            // Cyrillic
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'E', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I', 'Й' => 'I', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'KH', 'Ц' => 'TS',
            'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SHCH', 'Ы' => 'Y', 'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA', 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
            'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e',
            'ю' => 'yu', 'я' => 'ya',
        );

        // Replace characters using the mapping
        $string = strtr($string, $chars);

        // Remove any remaining combining marks
        $string = preg_replace('/[\p{Mn}]/u', '', $string);

        return $string;
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