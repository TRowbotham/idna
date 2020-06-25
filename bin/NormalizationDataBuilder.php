<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use function array_map;
use function explode;
use function fclose;
use function fgets;
use function file_put_contents;
use function var_export;

class NormalizationDataBuilder extends Builder
{
    public static function buildCombiningClassHashMap(string $output): void
    {
        $handle = self::getUnicodeDataResource('extracted/DerivedCombiningClass.txt');
        $combiningClasses = [];

        while (($line = fgets($handle)) !== false) {
            if ($line === "\n" || $line[0] === '#') {
                continue;
            }

            [$data] = explode('#', $line);
            $data = array_map('trim', explode(';', $data));
            [$codePoints, $prop] = $data;

            if ($prop === '0') {
                continue;
            }

            [$start, $end] = self::parseCodePoints($codePoints);
            $diff = $end - $start + 1;

            for ($i = 0; $i < $diff; ++$i) {
                $combiningClasses[$start + $i] = (int) $prop;
            }
        }

        fclose($handle);
        file_put_contents($output . DS . 'combiningClass.php', "<?php\n\nreturn " . var_export($combiningClasses, true) . ";\n");
    }

    public static function buildNormalizationPropsHashMap(string $output): void
    {
        $handle = self::getUnicodeDataResource('DerivedNormalizationProps.txt');
        $nfcData = [];

        while (($line = fgets($handle)) !== false) {
            if ($line === "\n" || $line[0] === '#') {
                continue;
            }

            [$data] = explode('#', $line);
            $data = array_map('trim', explode(';', $data));
            [$codePoints, $prop] = $data;

            if ($prop !== 'NFC_QC') {
                continue;
            }

            [$start, $end] = self::parseCodePoints($codePoints);
            $diff = $end - $start + 1;

            for ($i = 0; $i < $diff; ++$i) {
                $nfcData[$start + $i] = $data[2];
            }
        }

        fclose($handle);
        file_put_contents($output . DS . 'normalizationProps.php', "<?php\n\nreturn " . var_export($nfcData, true) . ";\n");
    }
}
