<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use function explode;
use function intval;

/**
 * @template TValue
 */
abstract class AbstractParser
{
    /**
     * @var \Rowbot\Idna\Bin\AbstractDataLoader
     */
    protected $loader;

    public function __construct(AbstractDataLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * @return \Rowbot\Idna\Bin\Collection<TValue>
     */
    abstract public function parse(string $filename): Collection;

    /**
     * @return array<int, int>
     */
    protected function parseCodePoints(string $codePoints): array
    {
        $range = explode('..', $codePoints);
        $start = intval($range[0], 16);
        $end = isset($range[1]) ? intval($range[1], 16) : $start;

        return [$start, $end];
    }
}
