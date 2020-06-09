<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Rowbot\Idna\Exception\LookupFailedException;

use function count;
use function sprintf;
use function strpos;
use function substr;

use const DIRECTORY_SEPARATOR as DS;

class MappingTable
{
    private const DISALLOWED_PREFIX = 'disallowed_STD3_';
    private const LOOKUP_TABLE = __DIR__ . DS . '..' . DS . 'resources' . DS . 'mappingTable.php';

    /**
     * @var array<int, array{codepoints: array<int, int>, status: string, mapping?: string, idna2008_status?: string}>
     */
    private static $mappingTable;

    public function __construct()
    {
        if (isset(self::$mappingTable)) {
            return;
        }

        self::$mappingTable = require self::LOOKUP_TABLE;
    }

    /**
     * @return array{codepoints: array<int, int>, status: string, mapping?: string, idna2008_status?: string}
     */
    public function lookup(int $codePoint, bool $useSTD3ASCIIRules): array
    {
        $start = 0;
        $end = count(self::$mappingTable) - 1;

        while ($start <= $end) {
            $mid = ($start + $end) >> 1;
            $target = self::$mappingTable[$mid];

            if ($target['codepoints'][0] <= $codePoint && $target['codepoints'][1] >= $codePoint) {
                $status = $target['status'];

                if (strpos($status, self::DISALLOWED_PREFIX) === 0) {
                    $target['status'] = $useSTD3ASCIIRules ? 'disallowed' : substr($status, 16);
                }

                return $target;
            }

            if ($target['codepoints'][0] > $codePoint) {
                $end = $mid - 1;
            } else {
                $start = $mid + 1;
            }
        }

        throw new LookupFailedException(sprintf(
            'Failed to find an entry for code point U+%04X in the mapping table.',
            $codePoint
        ));
    }
}
