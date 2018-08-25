<?php

namespace LukasBestle\Shareable;

use Exception;

/**
 * BijectiveEncoder
 * Converts between an integer and its ASCII representation
 *
 * @package   Shareable
 * @author    Lukas Bestle <project-shareable@lukasbestle.com>
 * @copyright Lukas Bestle
 * @license   MIT
 */
class BijectiveEncoder
{
    // default alphabet
    // doesn't contain the characters 01loIO (might be confused when typing URLs manually)
    // and the characters aeiouAEIOU (to avoid creating actual words)
    public static $alphabet = '23456789bcdfghjkmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ';

    /**
     * Converts an integer to its ASCII representation
     * Uses the alphabet in BijectiveEncoder::$alphabet
     *
     * @param  int    $integer
     * @return string          String in the configured alphabet
     */
    public static function encode(int $integer): string
    {
        // special cases
        if ($integer === 0) {
            return static::$alphabet[0];
        } elseif ($integer < 0) {
            throw new Exception('Only positive integers are supported');
        }

        $string = '';
        $base   = strlen(static::$alphabet);
        while ($integer > 0) {
            $string  = static::$alphabet[($integer % $base)] . $string;
            $integer = floor($integer / $base);
        }

        return $string;
    }

    /**
     * Converts an ASCII value to its integer representation
     * Uses the alphabet in BijectiveEncoder::$alphabet
     *
     * @param  string $string String in the configured alphabet
     * @return int
     */
    public static function decode(string $string): int
    {
        $integer = 0;
        $base    = strlen(static::$alphabet);
        $string  = str_split($string);

        foreach ($string as $char) {
            $pos = strpos(static::$alphabet, $char);
            if (!is_int($pos)) {
                throw new Exception(sprintf('Char "%s" is not in the alphabet', $char));
            }

            $integer = $integer * $base + $pos;
        }

        return $integer;
    }

    /**
     * Generates a random string with the current alphabet
     *
     * @param  int    $chars Number of chars of the resulting string
     * @return string
     */
    public static function randomString(int $chars): string
    {
        if ($chars < 1) {
            throw new Exception('$chars must be at least 1');
        }

        $max    = strlen(static::$alphabet) - 1;
        $result = '';
        for ($i = 0; $i < $chars; $i++) {
            $result .= static::$alphabet[random_int(0, $max)];
        }

        return $result;
    }
}
