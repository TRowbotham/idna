<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Rowbot\Idna\Resource\DisallowedRanges;

use const DIRECTORY_SEPARATOR as DS;

final class CodePointStatus
{
    private const RESOURCE_DIR = __DIR__ . DS . '..' . DS . 'resources' . DS;

    /**
     * @var array<int, string>
     */
    private static $mapped;

    /**
     * @var array<int, bool>
     */
    private static $ignored;

    /**
     * @var array<int, string>
     */
    private static $deviation;

    /**
     * @var array<int, bool>
     */
    private static $disallowed;

    /**
     * @var bool
     */
    private static $dataLoaded = false;

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * @return array{status: string, mapping?: string}
     */
    public static function lookup(int $codePoint): array
    {
        if (!self::$dataLoaded) {
            self::$dataLoaded = true;
            self::$mapped = require self::RESOURCE_DIR . 'mapped.php';
            self::$ignored = require self::RESOURCE_DIR . 'ignored.php';
            self::$deviation = require self::RESOURCE_DIR . 'deviation.php';
            self::$disallowed = require self::RESOURCE_DIR . 'disallowed.php';
        }

        return match (true) {
            isset(self::$mapped[$codePoint]) => ['status' => 'mapped', 'mapping' => self::$mapped[$codePoint]],
            isset(self::$ignored[$codePoint]) => ['status' => 'ignored'],
            isset(self::$deviation[$codePoint]) => ['status' => 'deviation', 'mapping' => self::$deviation[$codePoint]],
            isset(self::$disallowed[$codePoint]) || DisallowedRanges::inRange($codePoint) => ['status' => 'disallowed'],
            default => ['status' => 'valid'],
        };
    }
}
