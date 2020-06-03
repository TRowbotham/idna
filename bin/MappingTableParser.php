<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use Rowbot\Punycode\CodePoint;
use RuntimeException;

use function array_map;
use function explode;
use function intval;
use function preg_match_all;

/**
 * @extends \Rowbot\Idna\Bin\AbstractParser<array{
 *  codepoints: array<int, int>,
 *  status: string,
 *  mapping?: string,
 *  idna2008_status?: string
 * }>
 */
class MappingTableParser extends AbstractParser
{
    private const VALID_STATUSES = [
        'valid',
        'ignored',
        'mapped',
        'deviation',
        'disallowed',
        'disallowed_STD3_valid',
        'disallowed_STD3_mapped',
    ];

    public function parse(string $filename): Collection
    {
        $data = $this->loader->fetch($filename);
        $table = [];

        foreach (explode("\n", $data) as $line) {
            if ($line === '' || $line[0] === "\n" || $line[0] === '#') {
                continue;
            }

            $tempData = [];
            [$data] = array_map('trim', explode('#', $line));
            $parts = array_map('trim', explode(';', $data));
            $tempData['codepoints'] = $this->parseCodePoints($parts[0]);
            $tempData['status'] = $parts[1];

            if (isset($parts[2])) {
                $tempData['mapping'] = $this->parseMapping($parts[2]);
            }

            if (isset($parts[3])) {
                $tempData['idna2008_status'] = $parts[3];
            }

            $this->validateData($tempData);
            $table[] = $tempData;
        }

        return new Collection($table);
    }

    private function parseMapping(string $mapping): string
    {
        if (preg_match_all('/[[:xdigit:]]+/', $mapping, $matches) === false) {
            throw new RuntimeException();
        }

        $codePoints = '';

        foreach ($matches[0] as $codePoint) {
            $codePoints .= CodePoint::encode(intval($codePoint, 16));
        }

        return $codePoints;
    }

    /**
     * @param array{codepoints: array<int, int>, status: string, mapping?: string, idna2008_status?: string} $data
     */
    private function validateData(array $data): void
    {
        assert(
            count($data['codepoints']) === 2,
            sprintf(
                'There must be 2 code points, representing the start and end of a range of code points. Found %d.',
                count($data['codepoints'])
            )
        );
        assert(
            is_int($data['codepoints'][0]) && is_int($data['codepoints'][1]),
            'Both code points must be an integer.'
        );
        assert(
            $data['codepoints'][0] >= 0 && $data['codepoints'][0] <= 0x10FFFF,
            sprintf('Code point must be a in the range 0..10FFFF. Found 0x%X.', $data['codepoints'][0])
        );
        assert(
            $data['codepoints'][1] >= 0 && $data['codepoints'][1] <= 0x10FFFF,
            sprintf('Code point must be a in the range 0..10FFFF. Found 0x%X.', $data['codepoints'][1])
        );

        assert(
            isset($data['status']) && is_string($data['status']),
            'The "status" key must exist and must be a string.'
        );
        assert(
            in_array($data['status'], self::VALID_STATUSES, true),
            sprintf('Status must be a valid status string. Found "%s".', $data['status'])
        );

        switch ($data['status']) {
            case 'mapped':
            case 'deviation':
            case 'disallowed_STD3_mapped':
                // Contrary to {@link https://www.unicode.org/reports/tr46/#Table_Data_File_Fields} the "ignored"
                // status does not have a mapping.
                assert(
                    isset($data['mapping']),
                    sprintf('Status "%s" must have an associated mapping.', $data['status'])
                );

                break;

            case 'valid':
                if (isset($data['idna2008_status'])) {
                    assert(
                        $data['idna2008_status'] === 'NV8' || $data['idna2008_status'] === 'XV8',
                        sprintf('IDNA2008 status must be "NV8" or "XV8". Found "%s".', $data['idna2008_status'])
                    );
                }

                break;

            default:
                assert(
                    !isset($data['mapping']),
                    sprintf('Status "%s" must NOT have an associated mapping.', $data['status'])
                );
        }
    }
}
