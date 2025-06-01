<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use function chr;
use function mb_str_split;
use function mb_ord;
use function ord;
use function unpack;

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

        foreach (mb_str_split($input, 1, 'utf-8') as $s) {
            // First, try the common case of a valid UTF-8 codepoint. mb_ord() will return false if the code point is
            // not valid such as an unpaired surrogate.
            $code = mb_ord($s, 'utf-8');

            if ($code !== false) {
                $codePoints[] = $code;

                continue;
            }

            // Next, check if the code point was an unpaired surrogate as we still want to pass them through and
            // mb_str_split() will give us the unpaired surrogate despite mb_ord() returning false for it.
            if ($s >= "\u{D800}" && $s <= "\u{DB7F}" || $s >= "\u{DC00}" && $s <= "\u{DFFF}") {
                /** @var array<int>|false $code */
                $code = unpack('C*', $s);

                if ($code === false) {
                    // Is outputing a replacement character the right thing to do here? Should we throw instead, even
                    // though  nothing else throws?
                    $codePoints[] = 0xFFFD;

                    continue;
                }

                $codePoints[] = (($code[1] - 0xE0) << 12) + (($code[2] - 0x80) << 6) + $code[3] - 0x80;

                continue;
            }

            // Lastly, no codepoint could be formed and mb_str_split() should be returning individual bytes.
            $codePoints[] = ord($s);
        }

        return $codePoints;
    }
}
