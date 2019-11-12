<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog;

final class Utils
{
    const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * @internal
     */
    public static function getClass($object): string
    {
        $class = \get_class($object);

        return 'c' === $class[0] && 0 === strpos($class, "class@anonymous\0") ? get_parent_class($class).'@anonymous' : $class;
    }

    public static function substr(string $string, int $start, ?int $length = null)
    {
        if (extension_loaded('mbstring')) {
            return mb_strcut($string, $start, $length);
        }

        return substr($string, $start, $length);
    }

    /**
     * Return the JSON representation of a value
     *
     * @param  mixed             $data
     * @param  int               $encodeFlags flags to pass to json encode, defaults to JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
     * @param  bool              $ignoreErrors whether to ignore encoding errors or to throw on error, when ignored and the encoding fails, "null" is returned which is valid json for null
     * @throws \RuntimeException if encoding fails and errors are not ignored
     * @return string when errors are ignored and the encoding fails, "null" is returned which is valid json for null
     */
    public static function jsonEncode($data, ?int $encodeFlags = null, bool $ignoreErrors = false): string
    {
        if (null === $encodeFlags) {
            $encodeFlags = self::DEFAULT_JSON_FLAGS;
        }

        $json = json_encode($data, $encodeFlags);

        if (false === $json) {
            if ($ignoreErrors) {
                return 'null';
            }

            $json = self::handleJsonError(json_last_error(), $data);
        }

        return $json;
    }

    /**
     * Handle a json_encode failure.
     *
     * If the failure is due to invalid string encoding, try to clean the
     * input and encode again. If the second encoding attempt fails, the
     * inital error is not encoding related or the input can't be cleaned then
     * raise a descriptive exception.
     *
     * @param  int               $code return code of json_last_error function
     * @param  mixed             $data data that was meant to be encoded
     * @param  int               $encodeFlags flags to pass to json encode, defaults to JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
     * @throws \RuntimeException if failure can't be corrected
     * @return string            JSON encoded data after error correction
     */
    public static function handleJsonError(int $code, $data, ?int $encodeFlags = null): string
    {
        if ($code !== JSON_ERROR_UTF8) {
            self::throwEncodeError($code, $data);
        }

        if (is_string($data)) {
            self::detectAndCleanUtf8($data);
        } elseif (is_array($data)) {
            array_walk_recursive($data, array('Monolog\Utils', 'detectAndCleanUtf8'));
        } else {
            self::throwEncodeError($code, $data);
        }

        if (null === $encodeFlags) {
            $encodeFlags = self::DEFAULT_JSON_FLAGS;
        }

        $json = json_encode($data, $encodeFlags);

        if ($json === false) {
            self::throwEncodeError(json_last_error(), $data);
        }

        return $json;
    }

    /**
     * Throws an exception according to a given code with a customized message
     *
     * @param  int               $code return code of json_last_error function
     * @param  mixed             $data data that was meant to be encoded
     * @throws \RuntimeException
     */
    private static function throwEncodeError(int $code, $data)
    {
        switch ($code) {
            case JSON_ERROR_DEPTH:
                $msg = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $msg = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $msg = 'Unexpected control character found';
                break;
            case JSON_ERROR_UTF8:
                $msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $msg = 'Unknown error';
        }

        throw new \RuntimeException('JSON encoding failed: '.$msg.'. Encoding: '.var_export($data, true));
    }

    /**
     * Detect invalid UTF-8 string characters and convert to valid UTF-8.
     *
     * Valid UTF-8 input will be left unmodified, but strings containing
     * invalid UTF-8 codepoints will be reencoded as UTF-8 with an assumed
     * original encoding of ISO-8859-15. This conversion may result in
     * incorrect output if the actual encoding was not ISO-8859-15, but it
     * will be clean UTF-8 output and will not rely on expensive and fragile
     * detection algorithms.
     *
     * Function converts the input in place in the passed variable so that it
     * can be used as a callback for array_walk_recursive.
     *
     * @param mixed &$data Input to check and convert if needed
     */
    private static function detectAndCleanUtf8(&$data)
    {
        if (is_string($data) && !preg_match('//u', $data)) {
            $data = preg_replace_callback(
                '/[\x80-\xFF]+/',
                function ($m) {
                    return utf8_encode($m[0]);
                },
                $data
            );
            $data = str_replace(
                ['¤', '¦', '¨', '´', '¸', '¼', '½', '¾'],
                ['€', 'Š', 'š', 'Ž', 'ž', 'Œ', 'œ', 'Ÿ'],
                $data
            );
        }
    }
}
