<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use Rowbot\Idna\CodePoint;
use RuntimeException;

use function array_map;
use function explode;
use function fclose;
use function fgets;
use function file_put_contents;
use function intval;
use function preg_match_all;

use const DIRECTORY_SEPARATOR as DS;

class IdnaDataBuilder extends Builder
{
    private const SRC_DIR = __DIR__ . DS . '..' . DS . 'src';

    public static function buildHashMaps(string $output): void
    {
        $handle = self::getIdnaDataResource('IdnaMappingTable.txt');
        $statuses = [
            'mapped'                 => [],
            'ignored'                => [],
            'deviation'              => [],
            'disallowed'             => [],
            'disallowed_STD3_mapped' => [],
            'disallowed_STD3_valid'  => [],
        ];
        $rangeFallback = '';

        while (($line = fgets($handle)) !== false) {
            if ($line === "\n" || $line[0] === '#') {
                continue;
            }

            [$data] = explode('#', $line);
            $data = array_map('trim', explode(';', $data));
            [$codePoints, $status] = $data;
            $codePoints = self::parseCodePoints($codePoints);
            $diff = $codePoints[1] - $codePoints[0] + 1;

            switch ($status) {
                case 'valid':
                    // skip valid.
                    break;

                case 'mapped':
                case 'deviation':
                case 'disallowed_STD3_mapped':
                    if (preg_match_all('/[[:xdigit:]]+/', $data[2], $matches) === false) {
                        throw new RuntimeException();
                    }

                    $mapped = '';

                    foreach ($matches[0] as $codePoint) {
                        $mapped .= CodePoint::encode(intval($codePoint, 16));
                    }

                    for ($i = 0; $i < $diff; ++$i) {
                        $statuses[$status][$codePoints[0] + $i] = $mapped;
                    }

                    break;

                case 'disallowed':
                    if ($diff > 30) {
                        if ($rangeFallback !== '') {
                            $rangeFallback .= "\n\n";
                        }

                        $rangeFallback .= <<<RANGE_FALLBACK
        if (\$codePoint >= {$codePoints[0]} && \$codePoint <= {$codePoints[1]}) {
            return ['status' => 'disallowed'];
        }
RANGE_FALLBACK;

                        continue 1;
                    }

                    for ($i = 0; $i < $diff; ++$i) {
                        $statuses[$status][$codePoints[0] + $i] = true;
                    }

                    break;

                case 'ignored':
                case 'disallowed_STD3_valid':
                    for ($i = 0; $i < $diff; ++$i) {
                        $statuses[$status][$codePoints[0] + $i] = true;
                    }

                    break;
            }
        }

        fclose($handle);
        file_put_contents($output . DS . 'mapped.php', "<?php\n\nreturn " . var_export($statuses['mapped'], true) . ";\n");
        file_put_contents($output . DS . 'ignored.php', "<?php\n\nreturn " . var_export($statuses['ignored'], true) . ";\n");
        file_put_contents($output . DS . 'deviation.php', "<?php\n\nreturn " . var_export($statuses['deviation'], true) . ";\n");
        file_put_contents($output . DS . 'disallowed.php', "<?php\n\nreturn " . var_export($statuses['disallowed'], true) . ";\n");
        file_put_contents($output . DS . 'disallowed_STD3_mapped.php', "<?php\n\nreturn " . var_export($statuses['disallowed_STD3_mapped'], true) . ";\n");
        file_put_contents($output . DS . 'disallowed_STD3_valid.php', "<?php\n\nreturn " . var_export($statuses['disallowed_STD3_valid'], true) . ";\n");
        $s = <<<CP_STATUS
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
    private static \$mapped;

    /**
     * @var array<int, bool>
     */
    private static \$ignored;

    /**
     * @var array<int, string>
     */
    private static \$deviation;

    /**
     * @var array<int, bool>
     */
    private static \$disallowed;

    /**
     * @var array<int, string>
     */
    private static \$disallowed_STD3_mapped;

    /**
     * @var array<int, bool>
     */
    private static \$disallowed_STD3_valid;

    /**
     * @var bool
     */
    private static \$dataLoaded = false;

    /**
     * @return array{status: string, mapping?: string}
     */
    public static function lookup(int \$codePoint, bool \$useSTD3ASCIIRules): array
    {
        if (!self::\$dataLoaded) {
            self::\$dataLoaded = true;
            self::\$mapped = require self::RESOURCE_DIR . 'mapped.php';
            self::\$ignored = require self::RESOURCE_DIR . 'ignored.php';
            self::\$deviation = require self::RESOURCE_DIR . 'deviation.php';
            self::\$disallowed = require self::RESOURCE_DIR . 'disallowed.php';
            self::\$disallowed_STD3_mapped = require self::RESOURCE_DIR . 'disallowed_STD3_mapped.php';
            self::\$disallowed_STD3_valid = require self::RESOURCE_DIR . 'disallowed_STD3_valid.php';
        }

        if (isset(self::\$mapped[\$codePoint])) {
            return ['status' => 'mapped', 'mapping' => self::\$mapped[\$codePoint]];
        }

        if (isset(self::\$ignored[\$codePoint])) {
            return ['status' => 'ignored'];
        }

        if (isset(self::\$deviation[\$codePoint])) {
            return ['status' => 'deviation', 'mapping' => self::\$deviation[\$codePoint]];
        }

        if (isset(self::\$disallowed[\$codePoint])) {
            return ['status' => 'disallowed'];
        }

        \$isDisallowedMapped = isset(self::\$disallowed_STD3_mapped[\$codePoint]);
        \$isDisallowedValid = isset(self::\$disallowed_STD3_valid[\$codePoint]);

        if (\$isDisallowedMapped || \$isDisallowedValid) {
            \$status = 'disallowed';

            if (!\$useSTD3ASCIIRules) {
                \$status = \$isDisallowedMapped ? 'mapped' : 'valid';
            }

            if (\$isDisallowedMapped) {
                return ['status' => \$status, 'mapping' => self::\$disallowed_STD3_mapped[\$codePoint]];
            }

            return ['status' => \$status];
        }

        // fall back to range checking for "disallowed"
{$rangeFallback}

        return ['status' => 'valid'];
    }

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
}

CP_STATUS;

        file_put_contents(self::SRC_DIR . DS . 'CodePointStatus.php', $s);
    }
}
