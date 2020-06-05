<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Rowbot\Punycode\CodePoint as PunycodeCodePoint;

class CodePoint extends PunycodeCodePoint
{
    protected const RESOURCE_DIR = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'resources';

    public const COMBINING_CLASS_VIRAMA = 9;

    /**
     * @var array<int, int>
     */
    private static $combiningClass;

    public static function getCombiningClass(int $codePoint): int
    {
        if (!isset(self::$combiningClass)) {
            self::$combiningClass = require self::RESOURCE_DIR . DIRECTORY_SEPARATOR . 'combiningClass.php';
        }

        // We only store code points with a non-zero combining class, so if a code point isn't
        // found, then its combining class is 0.
        if (!isset(self::$combiningClass[$codePoint])) {
            return 0;
        }

        return self::$combiningClass[$codePoint];
    }
}
