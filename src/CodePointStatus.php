<?php

declare(strict_types=1);

namespace Rowbot\Idna;

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
     * @var array<int, string>
     */
    private static $disallowed_STD3_mapped;

    /**
     * @var array<int, bool>
     */
    private static $disallowed_STD3_valid;

    /**
     * @var bool
     */
    private static $dataLoaded = false;

    /**
     * @return array{status: string, mapping?: string}
     */
    public static function lookup(int $codePoint, bool $useSTD3ASCIIRules): array
    {
        if (!self::$dataLoaded) {
            self::$dataLoaded = true;
            self::$mapped = require self::RESOURCE_DIR . 'mapped.php';
            self::$ignored = require self::RESOURCE_DIR . 'ignored.php';
            self::$deviation = require self::RESOURCE_DIR . 'deviation.php';
            self::$disallowed = require self::RESOURCE_DIR . 'disallowed.php';
            self::$disallowed_STD3_mapped = require self::RESOURCE_DIR . 'disallowed_STD3_mapped.php';
            self::$disallowed_STD3_valid = require self::RESOURCE_DIR . 'disallowed_STD3_valid.php';
        }

        if (isset(self::$mapped[$codePoint])) {
            return ['status' => 'mapped', 'mapping' => self::$mapped[$codePoint]];
        }

        if (isset(self::$ignored[$codePoint])) {
            return ['status' => 'ignored'];
        }

        if (isset(self::$deviation[$codePoint])) {
            return ['status' => 'deviation', 'mapping' => self::$deviation[$codePoint]];
        }

        if (isset(self::$disallowed[$codePoint])) {
            return ['status' => 'disallowed'];
        }

        $isDisallowedMapped = isset(self::$disallowed_STD3_mapped[$codePoint]);
        $isDisallowedValid = isset(self::$disallowed_STD3_valid[$codePoint]);

        if ($isDisallowedMapped || $isDisallowedValid) {
            $status = 'disallowed';

            if (!$useSTD3ASCIIRules) {
                $status = $isDisallowedMapped ? 'mapped' : 'valid';
            }

            if ($isDisallowedMapped) {
                return ['status' => $status, 'mapping' => self::$disallowed_STD3_mapped[$codePoint]];
            }

            return ['status' => $status];
        }

        // fall back to range checking for "disallowed"
        if ($codePoint >= 128 && $codePoint <= 159) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 2155 && $codePoint <= 2207) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 3676 && $codePoint <= 3712) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 3808 && $codePoint <= 3839) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 4059 && $codePoint <= 4095) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 4256 && $codePoint <= 4293) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 6849 && $codePoint <= 6911) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 11859 && $codePoint <= 11903) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 42955 && $codePoint <= 42996) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 55296 && $codePoint <= 57343) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 57344 && $codePoint <= 63743) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 64218 && $codePoint <= 64255) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 64976 && $codePoint <= 65007) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 65630 && $codePoint <= 65663) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 65953 && $codePoint <= 65999) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 66046 && $codePoint <= 66175) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 66518 && $codePoint <= 66559) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 66928 && $codePoint <= 67071) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 67432 && $codePoint <= 67583) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 67760 && $codePoint <= 67807) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 67904 && $codePoint <= 67967) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 68256 && $codePoint <= 68287) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 68528 && $codePoint <= 68607) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 68681 && $codePoint <= 68735) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 68922 && $codePoint <= 69215) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 69298 && $codePoint <= 69375) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 69466 && $codePoint <= 69551) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 70207 && $codePoint <= 70271) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 70517 && $codePoint <= 70655) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 70874 && $codePoint <= 71039) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 71134 && $codePoint <= 71167) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 71370 && $codePoint <= 71423) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 71488 && $codePoint <= 71679) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 71740 && $codePoint <= 71839) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 72026 && $codePoint <= 72095) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 72441 && $codePoint <= 72703) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 72887 && $codePoint <= 72959) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 73130 && $codePoint <= 73439) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 73465 && $codePoint <= 73647) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 74650 && $codePoint <= 74751) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 75076 && $codePoint <= 77823) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 78905 && $codePoint <= 82943) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 83527 && $codePoint <= 92159) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 92784 && $codePoint <= 92879) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 93072 && $codePoint <= 93759) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 93851 && $codePoint <= 93951) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 94112 && $codePoint <= 94175) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 101590 && $codePoint <= 101631) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 101641 && $codePoint <= 110591) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 110879 && $codePoint <= 110927) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 111356 && $codePoint <= 113663) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 113828 && $codePoint <= 118783) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 119366 && $codePoint <= 119519) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 119673 && $codePoint <= 119807) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 121520 && $codePoint <= 122879) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 122923 && $codePoint <= 123135) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 123216 && $codePoint <= 123583) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 123648 && $codePoint <= 124927) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 125143 && $codePoint <= 125183) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 125280 && $codePoint <= 126064) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 126133 && $codePoint <= 126208) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 126270 && $codePoint <= 126463) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 126652 && $codePoint <= 126703) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 126706 && $codePoint <= 126975) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 127406 && $codePoint <= 127461) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 127590 && $codePoint <= 127743) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 129202 && $codePoint <= 129279) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 129751 && $codePoint <= 129791) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 129995 && $codePoint <= 130031) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 130042 && $codePoint <= 131069) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 173790 && $codePoint <= 173823) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 191457 && $codePoint <= 194559) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 195102 && $codePoint <= 196605) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 201547 && $codePoint <= 262141) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 262144 && $codePoint <= 327677) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 327680 && $codePoint <= 393213) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 393216 && $codePoint <= 458749) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 458752 && $codePoint <= 524285) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 524288 && $codePoint <= 589821) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 589824 && $codePoint <= 655357) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 655360 && $codePoint <= 720893) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 720896 && $codePoint <= 786429) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 786432 && $codePoint <= 851965) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 851968 && $codePoint <= 917501) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 917536 && $codePoint <= 917631) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 917632 && $codePoint <= 917759) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 918000 && $codePoint <= 983037) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 983040 && $codePoint <= 1048573) {
            return ['status' => 'disallowed'];
        }

        if ($codePoint >= 1048576 && $codePoint <= 1114109) {
            return ['status' => 'disallowed'];
        }

        return ['status' => 'valid'];
    }

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
}
