<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use function chr;
use function ord;
use function strlen;

final class CodePoint
{
    /**
     * Takes a Unicode code point and encodes it. The return behavior is undefined if the given
     * code point is outside the range 0..10FFFF.
     *
     * @see https://encoding.spec.whatwg.org/#utf-8-encoder
     */
    public static function encode(int $codePoint): string
    {
        if ($codePoint >= 0x00 && $codePoint <= 0x7F) {
            return chr($codePoint);
        }

        $count = 0;
        $offset = 0;

        if ($codePoint >= 0x0080 && $codePoint <= 0x07FF) {
            $count = 1;
            $offset = 0xC0;
        } elseif ($codePoint >= 0x0800 && $codePoint <= 0xFFFF) {
            $count = 2;
            $offset = 0xE0;
        } elseif ($codePoint >= 0x10000 && $codePoint <= 0x10FFFF) {
            $count = 3;
            $offset = 0xF0;
        }

        $bytes = chr(($codePoint >> (6 * $count)) + $offset);

        while ($count > 0) {
            $temp = $codePoint >> (6 * ($count - 1));
            $bytes .= chr(0x80 | ($temp & 0x3F));
            --$count;
        }

        return $bytes;
    }

    /**
     * @return list<int>
     */
    public static function utf8Decode(string $input): array
    {
        $codePoints = [];
        $shifts = [
            ['byte' => [], 'shifts' => []],
            ['byte' => [0xC0], 'shifts' => [6]],
            ['byte' => [0xE0, 0x80], 'shifts' => [12, 6]],
            ['byte' => [0xF0, 0x80, 0x80], 'shifts' => [18, 12, 6]],
        ];

        foreach (mb_str_split($input, 1, 'utf-8') as $s) {
            $bytes = strlen($s);

            if ($bytes === 1) {
                $codePoints[] = ord($s);

                continue;
            }

            $bytesLength = $bytes - 1;
            $x = 0;

            for ($i = 0; $i < $bytesLength; ++$i) {
                $x += (ord($s[$i]) - $shifts[$bytesLength]['byte'][$i]) << $shifts[$bytesLength]['shifts'][$i];
            }

            $x += ord($s[$bytesLength]) - 0x80;
            $codePoints[] = $x;
        }

        return $codePoints;
    }
}
