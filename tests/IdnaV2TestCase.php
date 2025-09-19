<?php

declare(strict_types=1);

namespace Rowbot\Idna\Test;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Rowbot\Idna\CodePoint;
use Rowbot\Idna\Idna;
use RuntimeException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

use function array_map;
use function count;
use function explode;
use function in_array;
use function preg_last_error;
use function preg_match_all;
use function sprintf;

use const DIRECTORY_SEPARATOR;
use const PREG_NO_ERROR;

class IdnaV2TestCase extends TestCase
{
    private const ERROR_CODE_MAP = [
        'P1' => Idna::ERROR_DISALLOWED,
        'P4' => [
            Idna::ERROR_EMPTY_LABEL,
            Idna::ERROR_DOMAIN_NAME_TOO_LONG,
            Idna::ERROR_LABEL_TOO_LONG,
            Idna::ERROR_PUNYCODE,
        ],
        'V1' => Idna::ERROR_INVALID_ACE_LABEL,
        'V2' => Idna::ERROR_HYPHEN_3_4,
        'V3' => [Idna::ERROR_LEADING_HYPHEN, Idna::ERROR_TRAILING_HYPHEN],
        'V4' => Idna::ERROR_PUNYCODE,
        'V5' => Idna::ERROR_LABEL_HAS_DOT,
        'V6' => Idna::ERROR_LEADING_COMBINING_MARK,
        'V7' => Idna::ERROR_DISALLOWED,
        // V8 and V9 are handled by C* and B* respectively.
        'A3' => Idna::ERROR_PUNYCODE,
        'A4_1' => Idna::ERROR_DOMAIN_NAME_TOO_LONG,
        'A4_2' => [Idna::ERROR_EMPTY_LABEL, Idna::ERROR_LABEL_TOO_LONG],
        'B1' => Idna::ERROR_BIDI,
        'B2' => Idna::ERROR_BIDI,
        'B3' => Idna::ERROR_BIDI,
        'B4' => Idna::ERROR_BIDI,
        'B5' => Idna::ERROR_BIDI,
        'B6' => Idna::ERROR_BIDI,
        'C1' => Idna::ERROR_CONTEXTJ,
        'C2' => Idna::ERROR_CONTEXTJ,
        // ContextO isn't tested here.
        // 'C3' => Idna::ERROR_CONTEXTO_PUNCTUATION,
        // 'C4' => Idna::ERROR_CONTEXTO_PUNCTUATION,
        // 'C5' => Idna::ERROR_CONTEXTO_PUNCTUATION,
        // 'C6' => Idna::ERROR_CONTEXTO_PUNCTUATION,
        // 'C7' => Idna::ERROR_CONTEXTO_PUNCTUATION,
        // 'C8' => Idna::ERROR_CONTEXTO_DIGITS,
        // 'C9' => Idna::ERROR_CONTEXTO_DIGITS,
        'X4_2' => Idna::ERROR_EMPTY_LABEL,
        'X3' => Idna::ERROR_EMPTY_LABEL,
        'U1' => Idna::ERROR_DISALLOWED,
    ];

    private const BASE_URI = 'https://www.unicode.org/Public/';
    private const TEST_FILE = 'IdnaTestV2.txt';
    private const CACHE_TTL = 86400 * 7; // 7 DAYS
    private const TEST_DATA_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'data';

    /**
     * @return array<int, array<int, string>>
     */
    public static function loadTestData(string $version): array
    {
        $cache = new FilesystemAdapter(
            'unicode-idna-test-data',
            self::CACHE_TTL,
            self::TEST_DATA_DIR
        );

        return $cache->get($version, static function () use ($version): array {
            $client = new Client([
                'base_uri' => match ($version) {
                    '16.0.0' => self::BASE_URI . 'idna/' . $version . '/',
                    default  => self::BASE_URI . $version . '/idna/',
                },
                'http_errors' => true,
            ]);
            $response = $client->get(self::TEST_FILE);

            return self::processResponse($response);
        });
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function processResponse(ResponseInterface $response): array
    {
        $output = [];

        foreach (explode("\n", (string) $response->getBody()) as $line) {
            // Ignore empty lines and comments.
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            [$data] = explode('#', $line);
            $columns = array_map('trim', explode(';', $data));
            assert(count($columns) === 7);
            $columns = preg_replace_callback(
                '/\\\\(?:u([[:xdigit:]]{4})|x{([[:xdigit:]]{4})})/u',
                static function (array $matches): string {
                    return CodePoint::encode(hexdec($matches[1]));
                },
                $columns
            );

            if ($columns === null) {
                throw new RuntimeException('Failed to unescape unicode characters.');
            }

            $output[] = $columns;
        }

        return $output;
    }

    /**
     * @param array<int, int|array<int, int>> $inherit
     *
     * @return array<int, int|array<int, int>>
     */
    private function resolveErrorCodes(string $statusCodes, array $inherit, array $ignore): array
    {
        if ($statusCodes === '') {
            return $inherit;
        }

        if ($statusCodes === '[]') {
            return [];
        }

        $matchCount = preg_match_all('/[PVUABCX][0-9](?:_[0-9])?/', $statusCodes, $matches);

        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new RuntimeException();
        }

        if ($matchCount === 0) {
            throw new RuntimeException();
        }

        $errors = [];

        foreach ($matches[0] as $match) {
            if (in_array($match, $ignore, true)) {
                continue;
            }

            if (!isset(self::ERROR_CODE_MAP[$match])) {
                throw new RuntimeException(sprintf('Unhandled error code %s.', $match));
            }

            $errors[] = self::ERROR_CODE_MAP[$match];
        }

        return $errors;
    }

    /**
     * @return array{
     *      0: string,
     *      1: array<int, int|array<int, int>>,
     *      2: string,
     *      3: array<int, int|array<int, int>>,
     *      4: string,
     *      5: array<int, int|array<int, int>>
     * }
     */
    public function translate(
        string $source,
        string $toUnicode,
        string $toUnicodeStatus,
        string $toAsciiN,
        string $toAsciiNStatus,
        string $toAsciiT,
        string $toAsciiTStatus,
        array $ignore = []
    ): array {
        if ($source === '""') {
            $source = '';
        }

        $toUnicode = match ($toUnicode) {
            '""' => '',
            '' => $source,
            default => $toUnicode,
        };

        $toAsciiN = match ($toAsciiN) {
            '""' => '',
            '' => $toUnicode,
            default => $toAsciiN,
        };

        $toAsciiT = match ($toAsciiT) {
            '""' => '',
            '' => $toAsciiN,
            default => $toAsciiT,
        };

        $toUnicodeStatus = $this->resolveErrorCodes($toUnicodeStatus, [], $ignore);
        $toAsciiNStatus = $this->resolveErrorCodes($toAsciiNStatus, $toUnicodeStatus, $ignore);
        $toAsciiTStatus = $this->resolveErrorCodes($toAsciiTStatus, $toAsciiNStatus, $ignore);

        return [
            $source,
            $toUnicode,
            $toUnicodeStatus,
            $toAsciiN,
            $toAsciiNStatus,
            $toAsciiT,
            $toAsciiTStatus,
        ];
    }
}
