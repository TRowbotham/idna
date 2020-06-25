<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use RuntimeException;

use function array_map;
use function explode;
use function fclose;
use function file_get_contents;
use function fopen;
use function intval;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function sprintf;
use function usort;

use const DIRECTORY_SEPARATOR as DS;
use const JSON_ERROR_NONE;

abstract class Builder
{
    protected const BASE_URL = 'https://www.unicode.org/Public';

    protected static function getUnicodeVersion(): string
    {
        $data = file_get_contents(__DIR__ . DS . '..' . DS . 'composer.json');

        if ($data === false) {
            throw new RuntimeException('Failed to read composer.json.');
        }

        $composerConfig = json_decode($data);

        if ($composerConfig === null || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf('JSON decoding composer.json failed: %s', json_last_error_msg()));
        }

        return $composerConfig->extra->idna->unicode_version;
    }

    /**
     * @return resource
     */
    protected static function getIdnaDataResource(string $file)
    {
        $file = sprintf('%s/idna/%s/%s', self::BASE_URL, self::getUnicodeVersion(), $file);
        $handle = fopen($file, 'r');

        if ($handle === false) {
            throw new RuntimeException('Failed to open ' . $file);
        }

        return $handle;
    }

    /**
     * @return resource
     */
    protected static function getUnicodeDataResource(string $file)
    {
        $file = sprintf('%s/%s/ucd/%s', self::BASE_URL, self::getUnicodeVersion(), $file);
        $handle = fopen($file, 'r');

        if ($handle === false) {
            throw new RuntimeException('Failed to open ' . $file);
        }

        return $handle;
    }

    /**
     * @return list<int>
     */
    protected static function parseCodePoints(string $codePoints): array
    {
        $range = explode('..', $codePoints);
        $start = intval($range[0], 16);
        $end = isset($range[1]) ? intval($range[1], 16) : $start;

        return [$start, $end];
    }

    /**
     * @return array<int, array<int, array<int, int>|string>>
     */
    protected static function parseProperties(string $file): array
    {
        $handle = self::getUnicodeDataResource($file);
        $retVal = [];

        while (($line = fgets($handle)) !== false) {
            if ($line === "\n" || $line[0] === '#') {
                continue;
            }

            [$data] = explode('#', $line);
            $data = array_map('trim', explode(';', $data));
            $data[0] = self::parseCodePoints($data[0]);
            $retVal[] = $data;
        }

        fclose($handle);
        usort($retVal, static function (array $a, array $b): int {
            return $a[0][0] <=> $b[0][0];
        });

        return $retVal;
    }
}
