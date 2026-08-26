<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

if (! function_exists('encryptOpenSSL')) {
    /**
     * Encrypts a given value using OpenSSL, with an initialization vector (IV).
     * The encrypted value is URL-safe.
     *
     * @param  string  $plainValue  The value to encrypt
     * @return string The encrypted value, with the IV prepended and URL-safe encoded
     */
    function encryptOpenSSL(string $plainValue): string
    {
        $method = config('openssl.method'); // You can use AES-128, AES-192, etc.
        $key = config('openssl.key');
        // Generate an initialization vector (IV) for AES
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        // Encrypt the data
        $encrypted = openssl_encrypt($plainValue, $method, $key, 0, $iv);
        // Combine the IV and encrypted string (needed for decryption)
        $combined = $iv.$encrypted;

        // Use URL-safe base64 encoding
        return base64url_encode($combined);
    }
}

if (! function_exists('decryptOpenSSL')) {
    /**
     * Decrypts a given value using OpenSSL, with an initialization vector (IV).
     * The input value is expected to be URL-safe encoded.
     *
     * @param  string  $encryptedWithIvValue  The value to decrypt, with the IV prepended and URL-safe encoded
     * @return string|false The decrypted value, or `false` on failure
     */
    function decryptOpenSSL(string $encryptedWithIvValue): false|string
    {
        $method = config('openssl.method'); // You can use AES-128, AES-192, etc.
        $key = config('openssl.key');
        // Decode the URL-safe base64 encoded string
        $decodedData = base64url_decode($encryptedWithIvValue);
        $iv = substr($decodedData, 0, openssl_cipher_iv_length($method));
        $encryptedData = substr($decodedData, openssl_cipher_iv_length($method));

        return openssl_decrypt($encryptedData, $method, $key, 0, $iv);
    }
}

if (!function_exists('base64url_encode')) {
    /**
     * Encodes data using URL-safe base64 encoding.
     *
     * @param string $data The data to encode
     * @return string The URL-safe base64 encoded data
     */
    function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    /**
     * Decodes data using URL-safe base64 encoding.
     *
     * @param string $data The URL-safe base64 encoded data
     * @return string The decoded data
     */
    function base64url_decode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}


/**
 * Compares two values and returns the given string if they match.
 * @param mixed $value1
 * @param mixed $value2
 * @param string $return
 * @return string
 */
function selected(mixed $value1, mixed $value2, string $return): string
{
    return $value1 == $value2 ? $return : "";
}


/**
 * Checks if given value is present in array and return given value
 * @param $needle
 * @param array $haystack
 * @param $return
 * @return string
 */
function selector($needle, array $haystack, $return): string
{
    return in_array($needle, $haystack) ? $return : '';
}


/**
 * Generates a random alphanumeric string of the specified length.
 *
 * @param int $length The length of the generated string.
 * @return string The generated random string.
 */
function generateRandomString(int $length): string
{
    return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
}


/**
 * Generates a random string of the specified length, consisting of alphanumeric
 * characters and the following special characters: !@#%^*()_+-~{}[];:|.<>
 *
 * @param int $length The length of the generated string.
 * @return string The generated random string.
 */
function crazyString(int $length): string
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#%^*()_+-~{}[];:|.<>';
    return substr(str_shuffle(str_repeat($characters, $length)), 0, $length);
}


/**
 * Returns a human-readable string describing the time elapsed since the given datetime.
 *
 * If $full is true, the string will contain all units of time, otherwise only the most significant one.
 *
 * @param string $datetime The datetime to calculate from.
 * @param bool $full Whether to include all time units or not.
 * @return string The human-readable time elapsed string.
 * @throws DateMalformedStringException
 * @throws Exception
 */
function time_elapsed_string(string $datetime, bool $full = false): string
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $units = [
        'y' => 'year', 'm' => 'month', 'w' => 'week',
        'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'
    ];

    $diff->w = floor($diff->d / 7);
    $diff->d %= 7;

    $result = [];
    foreach ($units as $key => $unit) {
        if ($diff->$key) {
            $result[] = $diff->$key . " $unit" . ($diff->$key > 1 ? 's' : '');
        }
    }

    return $result
        ? implode(', ', $full ? $result : [reset($result)]) . ' ago'
        : 'just now';
}


/**
 * Adds a specified number of years, months, and days to a given date.
 *
 * @param string $date The original date in 'Y-m-d' format.
 * @param int $years Number of years to add.
 * @param int $months Number of months to add.
 * @param int $days Number of days to add.
 * @return string The new date in 'Y-m-d' format after addition.
 */
function dateAdd(string $date, int $years, int $months, int $days): string
{
    return date('Y-m-d', strtotime("+$years years +$months months +$days days", strtotime($date)));
}


/**
 * Subtracts a specified number of years, months, and days from a given date.
 *
 * @param string $date The original date in 'Y-m-d' format.
 * @param int $years Number of years to subtract.
 * @param int $months Number of months to subtract.
 * @param int $days Number of days to subtract.
 * @return string The new date in 'Y-m-d' format after subtraction.
 */
function dateSub($date, int $years, int $months, int $days): string
{
    return date('Y-m-d', strtotime("-$years years -$months months -$days days", strtotime($date)));
}


/**
 * Determines if the file extension of a given file is present in a specified array of extensions.
 *
 * @param string $file_name The name of the file whose extension is to be checked.
 * @param array $search_array An array of file extensions to search for.
 * @return int Returns 1 if the file extension is found in the array, otherwise returns 0.
 */
function file_type(string $file_name, array $search_array): int
{
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    return in_array("$ext", $search_array) ? 1 : 0;
}


/**
 * Uploads a file from a specified temporary location to a target directory.
 *
 * @param string $file_name The original name of the uploaded file.
 * @param string $temp_name The temporary name of the uploaded file.
 * @param string $upload_dir The directory path where the uploaded file is to be saved.
 * @return int|string Returns the new name of the uploaded file if successful, otherwise returns 0.
 */
function uploadFile(string $file_name, string $temp_name, string $upload_dir): int|string
{
    $ext = pathinfo($file_name, PATHINFO_EXTENSION);
    $new_file_name = generateRandomString(10) . ".$ext";
    $file_path = $upload_dir . $new_file_name;
    return move_uploaded_file($temp_name, $file_path) ? $new_file_name : 0;
}

/**
 * Get either a Gravatar URL or complete image tag for a specified email address.
 *
 * @param string $email The email address
 * @param int $s Size in pixels, defaults to 80px [ 1 - 2048 ]
 * @param string $d Default imageset to use [ 404 | mp | identicon | monsterid | wavatar ]
 * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
 * @param bool $img True to return a complete IMG tag False for just the URL
 * @param array $atts Optional, additional key/value attributes to include in the IMG tag
 * @return String containing either just a URL or a complete image tag
 * @source https://gravatar.com/site/implement/images/php/
 */
function get_gravatar(string $email, int $s = 80, string $d = 'mp', string $r = 'g', bool $img = false, array $atts = array()): string
{
    $url = 'https://www.gravatar.com/avatar/';
    $url .= md5(strtolower(trim($email)));
    $url .= "?s=$s&d=$d&r=$r";
    if ($img) {
        $url = '<img src="' . $url . '"';
        foreach ($atts as $key => $val)
            $url .= ' ' . $key . '="' . $val . '"';
        $url .= ' />';
    }
    return $url;
}


/**
 * Recursively searches for a value within a multidimensional array.
 *
 * @param mixed $needle The value to search for.
 * @param array $haystack The array to search within.
 * @param bool $strict [optional] If true, performs a strict comparison (===).
 * @return bool Returns true if the needle is found, false otherwise.
 */
function in_array_r(mixed $needle, array $haystack, bool $strict = false): bool
{
    foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
            return true;
        }
    }
    return false;
}


/**
 * Recursively converts an object or array to an array
 *
 * @param object|array $object The object or array to convert
 * @return array|object
 */
function objectToArray(object|array $object): array|object
{
    if (!is_object($object) && !is_array($object)) return $object;
    return array_map('objectToArray', (array)$object);
}


/**
 * Returns the value if it is set and not empty, otherwise returns the specified return value.
 *
 * @param mixed $value The value to check.
 * @param mixed $return The value to return if the input value is not set or is empty.
 * @return mixed The original value if set and not empty, otherwise the return value.
 */
function not_empty($value, $return): mixed
{
    return (isset($value) and !empty($value)) ? $value : $return;
}


/**
 * Validate an email address.
 *
 * @param string $email The email address to validate.
 * @param bool $checkDNS [optional] Whether to check DNS records for the domain part of the email address.
 *                       Defaults to false.
 *
 * @return bool Whether the email address is valid.
 */
function isValidEmail($email, bool $checkDNS = false): bool
{
    // Check email format using a basic regular expression.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Check email length.
    if (strlen($email) > 254) {
        return false;
    }

    // Extract domain part and optionally check DNS records.
    $domain = substr(strrchr($email, "@"), 1);
    if ($checkDNS && !checkdnsrr($domain, "MX")) {
        return false;
    }

    return true;
}


/**
 * Get current page title from the URL.
 *
 * @return string|false the page title or false if no title found
 */
function get_title_page(): false|string
{
    $uri = $_SERVER['REQUEST_URI'];
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $p = explode('/', $url);
    $page = end($p);

    if (strpos($page, '?') !== false) {
        $p = explode('?', $page);
        $page = $p[0];
    }

    return $page;
}


/**
 * Returns the gender of a given user ID.
 *
 * @param int $uid The user ID.
 * @return string The gender of the user, either "Male" or "Female".
 */
function getGender($uid): string
{
    return ($uid == 1) ? "Male" : "Female";
}


/**
 * Checks if a given string is a valid name.
 * A valid name is defined as a string containing only alphabetic characters and having a length of at least 3.
 *
 * @param string $name The name to be validated.
 * @return bool True if the name is valid, false otherwise.
 */
function isValidName($name): bool
{
    $cleanedName = preg_replace('/\s+/', '', $name);
    if (!ctype_alpha($cleanedName)) return false;
    if (strlen($cleanedName) < 3) return false;
    return true;
}


/**
 * Checks if a given string is a valid identity number.
 * The string must be digits only, and its length must be at least 5 and at most 10.
 * Additional validation logic may be added in the future using APIs.
 * @param string $idNo The string to check.
 * @return bool True if $idNo is a valid identity number.
 */
function isValidIdNo($idNo): bool
{
    if (strlen($idNo) < 5 or strlen($idNo) > 10) return false;
    if (!preg_match('/^\d+$/', $idNo)) return false;
    // TODO: Additional validation logic using APIs
    return true;
}


/**
 * Checks if a given string is a valid phone number.
 *
 * The phone number should be in either of the following formats:
 * 254700000000 or 254100000000
 *
 * @param string $phoneNo The phone number to be validated
 * @return bool True if the phone number is valid, false otherwise
 */
function isValidPhoneNo($phoneNo): bool
{
    $cleanedNo = preg_replace('/[ -]/', '', $phoneNo);
    if (preg_match('/^2547\d{8}$/', $cleanedNo) or preg_match('/^2541\d{8}$/', $cleanedNo)) return true;
    else return false;
}


/**
 * Validates if a given string is a valid date in "YYYY-MM-DD" format.
 *
 * Checks if the date string is not empty, matches the "YYYY-MM-DD" pattern,
 * and can be successfully converted to a Unix timestamp.
 *
 * @param string $date The date string to validate.
 * @return bool Returns true if the date is valid, otherwise false.
 */
function isValidDate($date): bool
{
    return !empty($date) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $date) && strtotime($date) !== false;
}


/**
 * Checks if a given date of birth is valid and the age is 18 years and above
 *
 * @param string $dob Date of birth in the format "YYYY-MM-DD"
 * @return bool
 * @throws DateMalformedStringException
 */
function isValidDob($dob): bool
{
    if (isValidDate($dob)) {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;

        return $age >= 18;
    }
    return false;
}



/**
 * Pads a number with leading zeroes to a length of 4.
 *
 * @param int|null $number The number to be padded.
 * @return string The padded number.
 */
function set_number($number): string
{
    return str_pad($number ?? "0", 4, "0", STR_PAD_LEFT);
}


/**
 * Paginates a collection of items.
 * @param Collection $items
 * @param int $perPage
 * @param int|null $page
 * @param string $pageName
 * @return LengthAwarePaginator
 */
function paginate(Collection $items, int $perPage =  10, ?int $page = null, string $pageName = 'page'): LengthAwarePaginator
{
    $page ??= LengthAwarePaginator::resolveCurrentPage($pageName);

    return new LengthAwarePaginator(
        $items->forPage($page, $perPage)->values(),
        $items->count(),
        $perPage,
        $page,
        [
            'path' => request()->url(),
            'pageName' => $pageName,
            'query' => request()->query(),
        ]
    );
}

