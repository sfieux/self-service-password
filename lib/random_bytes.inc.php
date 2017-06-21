<?php
#==============================================================================
# LTB Self Service Password
#
# Copyright (C) 2009 Clement OUDOT
# Copyright (C) 2009 LTB-project.org
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# GPL License: http://www.gnu.org/licenses/gpl.txt
#
#==============================================================================

# This file has strong inspiration from paragonie/random_compat library
# under MIT licence, see https://github.com/paragonie/random_compat

if (!is_callable('RandomCompat_intval')) {
    /**
     * Cast to an integer if we can, safely.
     *
     * If you pass it a float in the range (~PHP_INT_MAX, PHP_INT_MAX)
     * (non-inclusive), it will sanely cast it to an int. If you it's equal to
     * ~PHP_INT_MAX or PHP_INT_MAX, we let it fail as not an integer. Floats
     * lose precision, so the <= and => operators might accidentally let a float
     * through.
     *
     * @param int|float $number    The number we want to convert to an int
     * @param boolean   $fail_open Set to true to not throw an exception
     *
     * @return float|int
     *
     * @throws TypeError
     */
    function RandomCompat_intval($number, $fail_open = false)
    {
        if (is_int($number) || is_float($number)) {
            $number += 0;
        } elseif (is_numeric($number)) {
            $number += 0;
        }
        if (
            is_float($number)
            &&
            $number > ~PHP_INT_MAX
            &&
            $number < PHP_INT_MAX
        ) {
            $number = (int) $number;
        }
        if (is_int($number)) {
            return (int) $number;
        } elseif (!$fail_open) {
            throw new TypeError(
                'Expected an integer.'
            );
        }
        return $number;
    }
}

if (!is_callable('RandomCompat_strlen')) {
    if (
        defined('MB_OVERLOAD_STRING') &&
        ini_get('mbstring.func_overload') & MB_OVERLOAD_STRING
    ) {
        /**
         * strlen() implementation that isn't brittle to mbstring.func_overload
         *
         * This version uses mb_strlen() in '8bit' mode to treat strings as raw
         * binary rather than UTF-8, ISO-8859-1, etc
         *
         * @param string $binary_string
         *
         * @throws TypeError
         *
         * @return int
         */
        function RandomCompat_strlen($binary_string)
        {
            if (!is_string($binary_string)) {
                throw new TypeError(
                    'RandomCompat_strlen() expects a string'
                );
            }
            return (int) mb_strlen($binary_string, '8bit');
        }
    } else {
        /**
         * strlen() implementation that isn't brittle to mbstring.func_overload
         *
         * This version just used the default strlen()
         *
         * @param string $binary_string
         *
         * @throws TypeError
         *
         * @return int
         */
        function RandomCompat_strlen($binary_string)
        {
            if (!is_string($binary_string)) {
                throw new TypeError(
                    'RandomCompat_strlen() expects a string'
                );
            }
            return (int) strlen($binary_string);
        }
    }
}

if (!defined('RANDOM_COMPAT_READ_BUFFER')) {
    define('RANDOM_COMPAT_READ_BUFFER', 8);
}

/**
 * Unless open_basedir is enabled, use /dev/urandom for
 * random numbers in accordance with best practices
 *
 * Why we use /dev/urandom and not /dev/random
 * @ref http://sockpuppet.org/blog/2014/02/25/safely-generate-random-numbers
 *
 * @param int $bytes
 *
 * @throws Exception
 *
 * @return string
 */
function random_bytes($bytes)
{
    static $fp = null;
    /**
     * This block should only be run once
     */
    if (empty($fp)) {
        /**
         * We use /dev/urandom if it is a char device.
         * We never fall back to /dev/random
         */
        $fp = fopen('/dev/urandom', 'rb');
        if (!empty($fp)) {
            $st = fstat($fp);
            if (($st['mode'] & 0170000) !== 020000) {
                fclose($fp);
                $fp = false;
            }
        }
        if (!empty($fp)) {
            /**
             * stream_set_read_buffer() does not exist in HHVM
             *
             * If we don't set the stream's read buffer to 0, PHP will
             * internally buffer 8192 bytes, which can waste entropy
             *
             * stream_set_read_buffer returns 0 on success
             */
            if (is_callable('stream_set_read_buffer')) {
                stream_set_read_buffer($fp, RANDOM_COMPAT_READ_BUFFER);
            }
            if (is_callable('stream_set_chunk_size')) {
                stream_set_chunk_size($fp, RANDOM_COMPAT_READ_BUFFER);
            }
        }
    }
    try {
        $bytes = RandomCompat_intval($bytes);
    } catch (TypeError $ex) {
        throw new TypeError(
            'random_bytes(): $bytes must be an integer'
        );
    }
    if ($bytes < 1) {
        throw new Error(
            'Length must be greater than 0'
        );
    }
    /**
     * This if() block only runs if we managed to open a file handle
     *
     * It does not belong in an else {} block, because the above
     * if (empty($fp)) line is logic that should only be run once per
     * page load.
     */
    if (!empty($fp)) {
        /**
         * @var int
         */
        $remaining = $bytes;
        /**
         * @var string|bool
         */
        $buf = '';
        /**
         * We use fread() in a loop to protect against partial reads
         */
        do {
            /**
             * @var string|bool
             */
            $read = fread($fp, $remaining);
            if (!is_string($read)) {
                if ($read === false) {
                    /**
                     * We cannot safely read from the file. Exit the
                     * do-while loop and trigger the exception condition
                     *
                     * @var string|bool
                     */
                    $buf = false;
                    break;
                }
            }
            /**
             * Decrease the number of bytes returned from remaining
             */
            $remaining -= RandomCompat_strlen($read);
            /**
             * @var string|bool
             */
            $buf = $buf . $read;
        } while ($remaining > 0);
        /**
         * Is our result valid?
         */
        if (is_string($buf)) {
            if (RandomCompat_strlen($buf) === $bytes) {
                /**
                 * Return our random entropy buffer here:
                 */
                return $buf;
            }
        }
    }
    /**
     * If we reach here, PHP has failed us.
     */
    throw new Exception(
        'Error reading from source device'
    );
}

?>