<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Rowbot\Punycode\Exception\PunycodeException;
use Rowbot\Punycode\Punycode;

use function array_merge;
use function count;
use function explode;
use function implode;
use function preg_match;
use function strlen;
use function strpbrk;

/**
 * @see https://www.unicode.org/reports/tr46/
 */
final class Idna
{
    private const DEFAULT_UNICODE_OPTIONS = [
        'CheckHyphens'            => true,
        'CheckBidi'               => true,
        'CheckJoiners'            => true,
        'UseSTD3ASCIIRules'       => true,
        'Transitional_Processing' => false,
    ];
    private const DEFAULT_ASCII_OPTIONS = self::DEFAULT_UNICODE_OPTIONS + [
        'VerifyDnsLength' => true,
    ];

    public const ERROR_EMPTY_LABEL            = 1;
    public const ERROR_LABEL_TOO_LONG         = 2;
    public const ERROR_DOMAIN_NAME_TOO_LONG   = 4;
    public const ERROR_LEADING_HYPHEN         = 8;
    public const ERROR_TRAILING_HYPHEN        = 0x10;
    public const ERROR_HYPHEN_3_4             = 0x20;
    public const ERROR_LEADING_COMBINING_MARK = 0x40;
    public const ERROR_DISALLOWED             = 0x80;
    public const ERROR_PUNYCODE               = 0x100;
    public const ERROR_LABEL_HAS_DOT          = 0x200;
    public const ERROR_INVALID_ACE_LABEL      = 0x400;
    public const ERROR_BIDI                   = 0x800;
    public const ERROR_CONTEXTJ               = 0x1000;
    public const ERROR_CONTEXTO_PUNCTUATION   = 0x2000;
    public const ERROR_CONTEXTO_DIGITS        = 0x4000;

    private const MAX_DOMAIN_SIZE = 253;
    private const MAX_LABEL_SIZE  = 63;

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * @see https://www.unicode.org/reports/tr46/#Processing
     *
     * @param array<string, bool> $options
     *
     * @return array<int, string>
     */
    private static function process(Utf8String $domain, array $options, DomainInfo $info): array
    {
        // If VerifyDnsLength is not set, we are doing ToUnicode otherwise we are doing ToASCII and
        // we need to respect the VerifyDnsLength option.
        $checkForEmptyLabels = !isset($options['VerifyDnsLength']) || $options['VerifyDnsLength'];

        if ($checkForEmptyLabels && $domain->isEmpty()) {
            $info->addError(self::ERROR_EMPTY_LABEL);

            return [''];
        }

        // Step 1. Map each code point in the domain name string
        $domain = $domain->mapCodePoints($options, $info);

        // Step 2. Normalize the domain name string to Unicode Normalization Form C.
        $domain = $domain->normalize();

        // Step 3. Break the string into labels at U+002E (.) FULL STOP.
        $labels = explode('.', $domain->toString());
        $lastLabelIndex = count($labels) - 1;
        $validator = new LabelValidator($info);

        // Step 4. Convert and validate each label in the domain name string.
        foreach ($labels as $i => $label) {
            $validationOptions = $options;

            if (substr($label, 0, 4) === 'xn--') {
                try {
                    $label = Punycode::decode(substr($label, 4));
                } catch (PunycodeException $e) {
                    $info->addError(self::ERROR_PUNYCODE);

                    continue;
                }

                $validationOptions['Transitional_Processing'] = false;
                $labels[$i] = $label;
            }

            $validator->validate($label, $validationOptions, $i > 0 && $i === $lastLabelIndex);
        }

        if ($info->isBidiDomain() && !$info->isValidBidiDomain()) {
            $info->addError(self::ERROR_BIDI);
        }

        // Any input domain name string that does not record an error has been successfully
        // processed according to this specification. Conversely, if an input domain_name string
        // causes an error, then the processing of the input domain_name string fails. Determining
        // what to do with error input is up to the caller, and not in the scope of this document.
        return $labels;
    }

    /**
     * @see https://www.unicode.org/reports/tr46/#ToASCII
     *
     * @param array<string, bool> $options
     */
    public static function toAscii(string $domain, array $options = []): IdnaResult
    {
        $options = array_merge(self::DEFAULT_ASCII_OPTIONS, $options);
        $info = new DomainInfo();
        $labels = self::process(new Utf8String($domain), $options, $info);

        foreach ($labels as $i => $label) {
            // Only convert labels to punycode that contain non-ASCII code points and only if that
            // label does not contain a character from the gen-delims set specified in
            // {@link https://ietf.org/rfc/rfc3987.html#section-2.2}
            if (preg_match('/[^\x00-\x7F]/', $label) === 1) {
                if (strpbrk($label, ':/?#[]@') !== false) {
                    continue;
                }

                try {
                    $label = 'xn--' . Punycode::encode($label);
                } catch (PunycodeException $e) {
                    $info->addError(self::ERROR_PUNYCODE);
                }

                $labels[$i] = $label;
            }
        }

        if ($options['VerifyDnsLength']) {
            self::validateDomainAndLabelLength($labels, $info);
        }

        return new IdnaResult(implode('.', $labels), $info);
    }

    /**
     * @see https://www.unicode.org/reports/tr46/#ToUnicode
     *
     * @param array<string, bool> $options
     */
    public static function toUnicode(string $domain, array $options = []): IdnaResult
    {
        // VerifyDnsLength is not a valid option for toUnicode, so remove it if it exists.
        unset($options['VerifyDnsLength']);
        $options = array_merge(self::DEFAULT_UNICODE_OPTIONS, $options);
        $info = new DomainInfo();
        $labels = self::process(new Utf8String($domain), $options, $info);

        return new IdnaResult(implode('.', $labels), $info);
    }

    /**
     * @param array<int, string> $labels
     */
    private static function validateDomainAndLabelLength(array $labels, DomainInfo $info): void
    {
        $maxDomainSize = self::MAX_DOMAIN_SIZE;
        $length = count($labels);
        $totalLength = $length - 1;

        // If the last label is empty and it is not the first label, then it is the root label.
        // Increase the max size by 1, making it 254, to account for the root label's "."
        // delimiter. This also means we don't need to check the last label's length for being too
        // long.
        if ($length > 1 && $labels[$length - 1] === '') {
            ++$maxDomainSize;
            --$length;
        }

        for ($i = 0; $i < $length; ++$i) {
            $bytes = strlen($labels[$i]);
            $totalLength += $bytes;

            if ($bytes > self::MAX_LABEL_SIZE) {
                $info->addError(self::ERROR_LABEL_TOO_LONG);
            }
        }

        if ($totalLength > $maxDomainSize) {
            $info->addError(self::ERROR_DOMAIN_NAME_TOO_LONG);
        }
    }
}
