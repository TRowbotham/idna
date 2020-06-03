<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use function array_map;
use function explode;

/**
 * @extends \Rowbot\Idna\Bin\AbstractParser<array{0: array<int, int>, 1: string}>
 */
class PropertyParser extends AbstractParser
{
    public function parse(string $filename): Collection
    {
        $data = $this->loader->fetch($filename);
        $parsedData = [];

        foreach (explode("\n", $data) as $line) {
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            [$temp] = explode('#', $line);
            $info = array_map('trim', explode(';', $temp));
            $info[0] = $this->parseCodePoints($info[0]);
            $parsedData[] = $info;
        }

        return new Collection($parsedData);
    }
}
