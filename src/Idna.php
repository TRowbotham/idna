<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Rowbot\Punycode\Exception\PunycodeException;
use Rowbot\Punycode\Punycode;

use function array_merge;

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
     */
    private static function process(Utf8String $input, array $options): Domain
    {
        // If VerifyDnsLength is not set, we are doing ToUnicode otherwise we are doing ToASCII and
        // we need to respect the VerifyDnsLength option.
        $checkForEmptyLabels = !isset($options['VerifyDnsLength']) || $options['VerifyDnsLength'];

        if ($checkForEmptyLabels && $input->isEmpty()) {
            $domain = $input->toDomain();
            $domain->addError(self::ERROR_EMPTY_LABEL);

            return $domain;
        }

        // Step 1. Map each code point in the domain name string
        $input = $input->mapCodePoints($options);

        // Step 2. Normalize the domain name string to Unicode Normalization Form C.
        $input = $input->normalize();

        // Step 3. Break the string into labels at U+002E (.) FULL STOP.
        $domain = $input->toDomain();
        $lastLabelIndex = $domain->count() - 1;

        // Step 4. Convert and validate each label in the domain name string.
        foreach ($domain as $i => $label) {
            $validationOptions = $options;

            if ($label->hasAcePrefix()) {
                try {
                    $label = new Label(Punycode::decode($label->withoutAcePrefix()->toString()));
                } catch (PunycodeException $e) {
                    $domain->addError(self::ERROR_PUNYCODE);

                    continue;
                }

                $validationOptions['Transitional_Processing'] = false;
                $domain->replaceLabelAt($i, $label);
            }

            $label->validate($domain, $validationOptions, $i > 0 && $i === $lastLabelIndex);
        }

        if ($domain->isBidi() && !$domain->isValidBidi()) {
            $domain->addError(Idna::ERROR_BIDI);
        }

        // Any input domain name string that does not record an error has been successfully
        // processed according to this specification. Conversely, if an input domain_name string
        // causes an error, then the processing of the input domain_name string fails. Determining
        // what to do with error input is up to the caller, and not in the scope of this document.
        return $domain;
    }

    /**
     * @see https://www.unicode.org/reports/tr46/#ToASCII
     *
     * @param array<string, bool> $options
     */
    public static function toAscii(string $domainName, array $options = []): IdnaResult
    {
        $options = array_merge(self::DEFAULT_ASCII_OPTIONS, $options);
        $domain = self::process(new Utf8String($domainName), $options);

        foreach ($domain as $i => $label) {
            // Only convert labels to punycode that contain non-ASCII code points and only if that
            // label does not contain a character from the gen-delims set specified in
            // {@link https://ietf.org/rfc/rfc3987.html#section-2.2}
            if ($label->containsNonAscii()) {
                if ($label->containsUrlDelimiter()) {
                    continue;
                }

                try {
                    $label = new Label('xn--' . Punycode::encode($label->toString()));
                } catch (PunycodeException $e) {
                    $domain->addError(self::ERROR_PUNYCODE);
                }

                $domain->replaceLabelAt($i, $label);
            }
        }

        if ($options['VerifyDnsLength']) {
            $domain->validateDomainAndLabelLength();
        }

        return new IdnaResult($domain);
    }

    /**
     * @see https://www.unicode.org/reports/tr46/#ToUnicode
     *
     * @param array<string, bool> $options
     */
    public static function toUnicode(string $domainName, array $options = []): IdnaResult
    {
        // VerifyDnsLength is not a valid option for toUnicode, so remove it if it exists.
        unset($options['VerifyDnsLength']);
        $options = array_merge(self::DEFAULT_UNICODE_OPTIONS, $options);
        $domain = self::process(new Utf8String($domainName), $options);

        return new IdnaResult($domain);
    }
}
