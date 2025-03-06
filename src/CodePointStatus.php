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
     * @return array{status: 'valid'|'ignored'|'disallowed' }|array{ status: 'mapped'|'deviation', mapping: string }
     */
    public static function lookup(int $codePoint): array
    {
        if (!self::$dataLoaded) {
            self::$dataLoaded = true;
            /** @phpstan-ignore assign.propertyType */
            self::$mapped = require self::RESOURCE_DIR . 'mapped.php';
            /** @phpstan-ignore assign.propertyType */
            self::$ignored = require self::RESOURCE_DIR . 'ignored.php';
            /** @phpstan-ignore assign.propertyType */
            self::$deviation = require self::RESOURCE_DIR . 'deviation.php';
            /** @phpstan-ignore assign.propertyType */
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
