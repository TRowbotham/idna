<?php

declare(strict_types=1);

use Rowbot\Idna\Bin\Collection;
use Rowbot\Idna\Bin\IdnaDataLoader;
use Rowbot\Idna\Bin\MappingTableParser;
use Rowbot\Idna\Bin\PropertyParser;
use Rowbot\Idna\Bin\UnicodeDataLoader;

const DS = DIRECTORY_SEPARATOR;
const ROOT_DIR = __DIR__ . DS . '..';
const OUTPUT_DIR = ROOT_DIR . DS . 'resources';

require ROOT_DIR . DS . 'vendor' . DS . 'autoload.php';

$data = file_get_contents(ROOT_DIR . DS . 'composer.json');

if ($data === false) {
    throw new RuntimeException('Failed to read composer.json.');
}

$composerConfig = json_decode($data);

if ($composerConfig === null || json_last_error() !== JSON_ERROR_NONE) {
    throw new RuntimeException(sprintf('JSON decoding composer.json failed: %s', json_last_error_msg()));
}

$loader = new IdnaDataLoader($composerConfig->extra->idna->unicode_version);
$parser = new MappingTableParser($loader);
$parser->parse('IdnaMappingTable.txt')->writeTo(OUTPUT_DIR . DS . 'mappingTable.php');

$loader = new UnicodeDataLoader($composerConfig->extra->idna->unicode_version);
$parser = new PropertyParser($loader);
$sort = static function (array $a, array $b): int {
    return $a[0][0] <=> $b[0][0];
};
$buildRegex = static function (Collection $collection): string {
    $out = '';

    foreach ($collection as $codePoints) {
        if ($codePoints[0][0] !== $codePoints[0][1]) {
            $out .= sprintf('\x{%04X}-\x{%04X}', ...$codePoints[0]);

            continue;
        }

        $out .= sprintf('\x{%04X}', $codePoints[0][0]);
    }

    return $out;
};
$only = static function (string ...$filters): Closure {
    return static function (array $value) use ($filters): bool {
        foreach ($filters as $filter) {
            if ($value[1] === $filter) {
                return true;
            }
        }

        return false;
    };
};
$allExcept = static function (string ...$filters): Closure {
    return static function (array $value) use ($filters): bool {
        foreach ($filters as $filter) {
            if ($value[1] === $filter) {
                return false;
            }
        }

        return true;
    };
};

$normalizationNfcProps = $parser->parse('DerivedNormalizationProps.txt');

/** @var \Generator<int, array{0: array<int, int>, 1: string, 2: string}> */
$iter = $normalizationNfcProps->filter($only('NFC_QC'))->getIterator();
$map = [];

foreach ($iter as $props) {
    $diff = $props[0][1] - $props[0][0] + 1;

    for ($i = 0; $i < $diff; ++$i) {
        $map[$props[0][0] + $i] = $props[2];
    }
}

(new Collection($map))->writeTo(OUTPUT_DIR . DS . 'normalizationProps.php');

$combiningClass = $parser->parse('extracted/DerivedCombiningClass.txt');
$map = [];

foreach ($combiningClass->filter($allExcept('0'))->getIterator() as $cc) {
    $diff = $cc[0][1] - $cc[0][0] + 1;

    for ($i = 0; $i < $diff; ++$i) {
        $map[$cc[0][0] + $i] = (int) $cc[1];
    }
}

(new Collection($map))->writeTo(OUTPUT_DIR . DS . 'combiningClass.php');
$map = [];

foreach ($combiningClass->sort($sort)->filter($only('9'))->getIterator() as $cc) {
    $diff = $cc[0][1] - $cc[0][0] + 1;

    for ($i = 0; $i < $diff; ++$i) {
        $map[$cc[0][0] + $i] = (int) $cc[1];
    }
}

(new Collection($map))->writeTo(OUTPUT_DIR . DS . 'virama.php');

$bidi = $parser->parse('extracted/DerivedBidiClass.txt')->sort($sort);

$rtlLabel = sprintf('/[%s]/u', $buildRegex($bidi->filter($only('R', 'AL', 'AN'))));

// Step 1. The first character must be a character with Bidi property L, R, or AL.  If it has the R
// or AL property, it is an RTL label; if it has the L property, it is an LTR label.
//
// Because any code point not explicitly listed in DerivedBidiClass.txt is considered to have the
// 'L' property, we negate a character class matching all code points explicitly listed in
// DerivedBidiClass.txt minus the ones explicitly marked as 'L'.
$bidiStep1Ltr = sprintf('/^[^%s]/u', $buildRegex($bidi->filter($allExcept('L'))));
$bidiStep1Rtl = sprintf('/^[%s]/u', $buildRegex($bidi->filter($only('R', 'AL'))));

// Step 2. In an RTL label, only characters with the Bidi properties R, AL, AN, EN, ES, CS, ET, ON,
// BN, or NSM are allowed.
$bidiStep2 = sprintf(
    '/[^%s]/u',
    $buildRegex($bidi->filter($only('R', 'AL', 'AN', 'EN', 'ES', 'CS', 'ET', 'ON', 'BN', 'NSM')))
);

// Step 3. In an RTL label, the end of the label must be a character with Bidi property R, AL, EN,
// or AN, followed by zero or more characters with Bidi property NSM.
$bidiStep3 = sprintf(
    '/[%s][%s]*$/u',
    $buildRegex($bidi->filter($only('R', 'AL', 'EN', 'AN'))),
    $buildRegex($bidi->filter($only('NSM')))
);

// Step 4. In an RTL label, if an EN is present, no AN may be present, and vice versa.
$bidiStep4EN = sprintf('/[%s]/u', $buildRegex($bidi->filter($only('EN'))));
$bidiStep4AN = sprintf('/[%s]/u', $buildRegex($bidi->filter($only('AN'))));

// Step 5. In an LTR label, only characters with the Bidi properties L, EN, ES, CS, ET, ON, BN, or
// NSM are allowed.
//
// Because any code point not explicitly listed in DerivedBidiClass.txt is considered to have the
// 'L' property, we negate a character class matching all code points explicitly listed in
// DerivedBidiClass.txt minus the ones explicitly marked as 'L', 'EN', 'ES', 'CS', 'ET', 'ON',
// 'BN', or 'NSM'.
$bidiStep5 = sprintf(
    '/[%s]/u',
    $buildRegex($bidi->filter($allExcept('L', 'EN', 'ES', 'CS', 'ET', 'ON', 'BN', 'NSM')))
);

// Step 6. In an LTR label, the end of the label must be a character with Bidi property L or EN,
// followed by zero or more characters with Bidi property NSM.
//
// Again, because any code point not explicitly listed in DerivedBidiClass.txt is considered to
// have the 'L' property, we negate a character class matching all code points explicitly listed in
// DerivedBidiClass.txt to match characters with the 'L' and 'EN' property.
$bidiStep6 = sprintf(
    '/[^%s][%s]*$/u',
    $buildRegex($bidi->filter($allExcept('L', 'EN'))),
    $buildRegex($bidi->filter($only('NSM')))
);

$joiningTypes = $parser->parse('extracted/DerivedJoiningType.txt');

// ((Joining_Type:{L,D})(Joining_Type:T)*\u200C(Joining_Type:T)*(Joining_Type:{R,D}))
// We use a capturing group around the first portion of the regex so we can count the byte length
// of the match and increment preg_match's offset accordingly.
$zwnj = sprintf(
    '/([%1$s%2$s][%3$s]*\x{200C}[%3$s]*)[%4$s%2$s]/u',
    $buildRegex($joiningTypes->filter($only('L'))),
    $buildRegex($joiningTypes->filter($only('D'))),
    $buildRegex($joiningTypes->filter($only('T'))),
    $buildRegex($joiningTypes->filter($only('R')))
);

$generalCategories = $parser->parse('extracted/DerivedGeneralCategory.txt');
$combiningMarks = sprintf(
    '/^[%s]/u',
    $buildRegex($generalCategories->filter($only('Mc', 'Me', 'Mn'))->sort($sort))
);

$foo = <<<RegexClass
<?php

// This file was auto generated by running 'php bin/generateDataFiles.php'

declare(strict_types=1);

namespace Rowbot\Idna\Resource;

final class Regex
{
    public const COMBINING_MARK = '{$combiningMarks}';

    public const RTL_LABEL = '{$rtlLabel}';

    public const BIDI_STEP_1_LTR = '{$bidiStep1Ltr}';
    public const BIDI_STEP_1_RTL = '{$bidiStep1Rtl}';
    public const BIDI_STEP_2 = '{$bidiStep2}';
    public const BIDI_STEP_3 = '{$bidiStep3}';
    public const BIDI_STEP_4_AN = '{$bidiStep4AN}';
    public const BIDI_STEP_4_EN = '{$bidiStep4EN}';
    public const BIDI_STEP_5 = '{$bidiStep5}';
    public const BIDI_STEP_6 = '{$bidiStep6}';

    public const ZWNJ = '{$zwnj}';

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
}
RegexClass;

$handle = fopen(OUTPUT_DIR . DS . 'Regex.php', 'w');

if ($handle === false) {
    throw new RuntimeException(sprintf('Failed to create file %s', OUTPUT_DIR . DS . 'Regex.php'));
}

fwrite($handle, $foo);
fclose($handle);
