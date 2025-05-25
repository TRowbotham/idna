<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Normalizer;
use Rowbot\Punycode\Exception\PunycodeException;
use Rowbot\Punycode\Punycode;

use function array_merge;
use function count;
use function explode;
use function implode;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * @see https://www.unicode.org/reports/tr46/
 */
final class Idna
{
    public const UNICODE_VERSION = '16.0.0';

    private const DEFAULT_UNICODE_OPTIONS = [
        'CheckHyphens'            => true,
        'CheckBidi'               => true,
        'CheckJoiners'            => true,
        'UseSTD3ASCIIRules'       => true,
        'IgnoreInvalidPunycode'   => false,
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
     * @see https://www.unicode.org/reports/tr46/#ProcessingStepMap
     *
     * @param array<string, bool> $options
     */
    private static function mapCodePoints(string $domain, array $options, DomainInfo $info): string
    {
        $str = '';
        $transitional = $options['Transitional_Processing'];
        $transitionalDifferent = false;

        foreach (CodePoint::utf8Decode($domain) as $codePoint) {
            $data = CodePointStatus::lookup($codePoint);

            switch ($data['status']) {
                case 'disallowed':
                case 'valid':
                    $str .= CodePoint::encode($codePoint);

                    break;

                case 'ignored':
                    // Do nothing.
                    break;

                case 'mapped':
                    $str .= ($transitional && $codePoint === 0x1E9E ? 'ss' : $data['mapping']);

                    break;

                case 'deviation':
                    $transitionalDifferent = true;
                    $str .= ($transitional ? $data['mapping'] : CodePoint::encode($codePoint));

                    break;
            }
        }

        if ($transitionalDifferent) {
            $info->setTransitionalDifferent();
        }

        return $str;
    }

    /**
     * @see https://www.unicode.org/reports/tr46/#Processing
     *
     * @param array<string, bool> $options
     *
     * @return list<string>
     */
    private static function process(string $domain, array $options, DomainInfo $info): array
    {
        if ($options['Transitional_Processing']) {
            trigger_error('Setting Transitional_Processing to true is deprecated.', E_USER_DEPRECATED);
        }

        // If VerifyDnsLength is not set, we are doing ToUnicode otherwise we are doing ToASCII and
        // we need to respect the VerifyDnsLength option.
        $checkForEmptyLabels = !isset($options['VerifyDnsLength']) || $options['VerifyDnsLength'];

        if ($checkForEmptyLabels && $domain === '') {
            $info->addError(self::ERROR_EMPTY_LABEL);

            return [''];
        }

        // Step 1. Map each code point in the domain name string
        $domain = self::mapCodePoints($domain, $options, $info);

        // Step 2. Normalize the domain name string to Unicode Normalization Form C.
        if (!Normalizer::isNormalized($domain, Normalizer::FORM_C)) {
            $originalDomain = $domain;
            $domain = Normalizer::normalize($domain, Normalizer::FORM_C);

            // This shouldn't be possible since the input string is run through the UTF-8 decoder
            // when mapping the code points above, but lets account for it anyway.
            if ($domain === false) {
                $info->addError(self::ERROR_INVALID_ACE_LABEL);
                $domain = $originalDomain;
            }

            unset($originalDomain);
        }

        // Step 3. Break the string into labels at U+002E (.) FULL STOP.
        /** @phpstan-ignore argument.type */
        $labels = explode('.', $domain);
        $lastLabelIndex = count($labels) - 1;
        $validator = new LabelValidator($info);

        // Step 4. Convert and validate each label in the domain name string.
        foreach ($labels as $i => $label) {
            $validationOptions = $options;

            if (str_starts_with($label, 'xn--')) {
                // Step 4.1. If the label contains any non-ASCII code point (i.e., a code point greater than U+007F),
                // record that there was an error, and continue with the next label.
                if (preg_match('/[^\x00-\x7F]/', $label) === 1) {
                    $info->addError(self::ERROR_PUNYCODE);

                    continue;
                }

                // Step 4.2. Attempt to convert the rest of the label to Unicode according to Punycode [RFC3492]. If
                // that conversion fails and if not IgnoreInvalidPunycode, record that there was an error, and continue
                // with the next label. Otherwise replace the original label in the string by the results of the
                // conversion.
                try {
                    $label = Punycode::decode(substr($label, 4));
                } catch (PunycodeException $e) {
                    if (!$validationOptions['IgnoreInvalidPunycode']) {
                        $info->addError(self::ERROR_PUNYCODE);
                    }

                    continue;
                }

                $labels[$i] = $label;

                // Step 4.3. If the label is empty, or if the label contains only ASCII code points, record that there
                // was an error.
                if ($label === '') {
                    $info->addError(self::ERROR_EMPTY_LABEL);
                }

                if (preg_match('/[^\x00-\x7F]/', $label) === 0) {
                    $info->addError(self::ERROR_INVALID_ACE_LABEL);
                }

                // Step 4.4. Verify that the label meets the validity criteria in Section 4.1, Validity Criteria for
                // Nontransitional Processing. If any of the validity criteria are not satisfied, record that there was
                // an error.
                $validationOptions['Transitional_Processing'] = false;
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
        $labels = self::process($domain, $options, $info);

        foreach ($labels as $i => $label) {
            // Only convert labels to punycode that contain non-ASCII code points
            if (preg_match('/[^\x00-\x7F]/', $label) === 1) {
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
        $labels = self::process($domain, $options, $info);

        return new IdnaResult(implode('.', $labels), $info);
    }

    /**
     * @param list<string> $labels
     */
    private static function validateDomainAndLabelLength(array $labels, DomainInfo $info): void
    {
        $labelCount = count($labels);

        // Account for each label separator in the total length
        $totalLength = $labelCount - 1;

        for ($i = 0; $i < $labelCount; ++$i) {
            $bytes = strlen($labels[$i]);
            $totalLength += $bytes;

            if ($bytes === 0) {
                $info->addError(self::ERROR_EMPTY_LABEL);
            } elseif ($bytes > self::MAX_LABEL_SIZE) {
                $info->addError(self::ERROR_LABEL_TOO_LONG);
            }
        }

        if ($totalLength > self::MAX_DOMAIN_SIZE) {
            $info->addError(self::ERROR_DOMAIN_NAME_TOO_LONG);
        }
    }
}
