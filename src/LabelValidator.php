<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Rowbot\Idna\CodePointString;
use Rowbot\Idna\Resource\Regex;

use function preg_match;
use function strlen;
use function strpos;
use function substr;

use const PREG_OFFSET_CAPTURE;

class LabelValidator
{
    private const HYPHEN = 0x002D;
    public const MAX_LABEL_SIZE = 63;

    /**
     * @var \Rowbot\Idna\Domain
     */
    protected $domain;

    public function __construct(Domain $domain)
    {
        $this->domain = $domain;
    }

    protected function isValidContextJ(string $label, CodePointString $codePoints): bool
    {
        $offset = 0;

        foreach ($codePoints as $i => $codePoint) {
            if ($codePoint !== 0x200C && $codePoint !== 0x200D) {
                continue;
            }

            $prev = $codePoints->codePointAt($i - 1);

            if ($prev === null) {
                return false;
            }

            // If Canonical_Combining_Class(Before(cp)) .eq. Virama Then True;
            if (CodePoint::getCombiningClass($prev) === CodePoint::COMBINING_CLASS_VIRAMA) {
                continue;
            }

            // If RegExpMatch((Joining_Type:{L,D})(Joining_Type:T)*\u200C(Joining_Type:T)*(Joining_Type:{R,D})) Then
            // True;
            // Generated RegExp = ([Joining_Type:{L,D}][Joining_Type:T]*\u200C[Joining_Type:T]*)[Joining_Type:{R,D}]
            if (
                $codePoint === 0x200C
                && preg_match(Regex::ZWNJ, $label, $matches, PREG_OFFSET_CAPTURE, $offset) === 1
            ) {
                $offset += strlen($matches[1][0]);

                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @see https://www.unicode.org/reports/tr46/#Validity_Criteria
     *
     * @param array<string, bool> $options
     */
    public function validate(string $label, array $options, bool $canBeEmpty): void
    {
        if ($label === '') {
            if (
                !$canBeEmpty
                && (!isset($options['VerifyDnsLength']) || $options['VerifyDnsLength'])
            ) {
                $this->domain->addError(Idna::ERROR_EMPTY_LABEL);
            }

            return;
        }

        $codePoints = new CodePointString($label);

        // Step 1. The label must be in Unicode Normalization Form C.
        if (!$codePoints->isNormalized()) {
            $this->domain->addError(Idna::ERROR_INVALID_ACE_LABEL);
        }

        if ($options['CheckHyphens']) {
            // Step 2. If CheckHyphens, the label must not contain a U+002D HYPHEN-MINUS character
            // in both the thrid and fourth positions.
            if (
                $codePoints->codePointAt(2) === self::HYPHEN
                && $codePoints->codePointAt(3) === self::HYPHEN
            ) {
                $this->domain->addError(Idna::ERROR_HYPHEN_3_4);
            }

            // Step 3. If CheckHyphens, the label must neither begin nor end with a U+002D
            // HYPHEN-MINUS character.
            if (substr($label, 0, 1) === '-') {
                $this->domain->addError(Idna::ERROR_LEADING_HYPHEN);
            }

            if (substr($label, -1, 1) === '-') {
                $this->domain->addError(Idna::ERROR_TRAILING_HYPHEN);
            }
        }

        // Step 4. The label must not contain a U+002E (.) FULL STOP.
        if (strpos($label, '.') !== false) {
            $this->domain->addError(Idna::ERROR_LABEL_HAS_DOT);
        }

        // Step 5. The label must not begin with a combining mark, that is: General_Category=Mark.
        if (preg_match(Regex::COMBINING_MARK, $label, $matches) === 1) {
            $this->domain->addError(Idna::ERROR_LEADING_COMBINING_MARK);
        }

        // Step 6. Each code point in the label must only have certain status values according to
        // Section 5, IDNA Mapping Table:
        $table = new MappingTable();
        $transitional = $options['Transitional_Processing'];
        $useSTD3ASCIIRules = $options['UseSTD3ASCIIRules'];

        foreach ($codePoints as $codePoint) {
            $data = $table->lookup($codePoint, $useSTD3ASCIIRules);
            $status = $data['status'];

            if ($status === 'valid' || (!$transitional && $status === 'deviation')) {
                continue;
            }

            $this->domain->addError(Idna::ERROR_DISALLOWED);

            break;
        }

        // Step 7. If CheckJoiners, the label must satisify the ContextJ rules from Appendix A, in
        // The Unicode Code Points and Internationalized Domain Names for Applications (IDNA)
        // [IDNA2008].
        if ($options['CheckJoiners'] && !$this->isValidContextJ($label, $codePoints)) {
            $this->domain->addError(Idna::ERROR_CONTEXTJ);
        }

        // Step 8. If CheckBidi, and if the domain name is a  Bidi domain name, then the label must
        // satisfy all six of the numbered conditions in [IDNA2008] RFC 5893, Section 2.
        if ($options['CheckBidi'] && (!$this->domain->isBidi() || $this->domain->isValidBidi())) {
            $this->validateBidi($label);
        }
    }

    /**
     * @see https://tools.ietf.org/html/rfc5893#section-2
     */
    protected function validateBidi(string $label): void
    {
        if (preg_match(Regex::RTL_LABEL, $label) === 1) {
            $this->domain->setBidi();

            // Step 1. The first character must be a character with Bidi property L, R, or AL.
            // If it has the R or AL property, it is an RTL label
            if (preg_match(Regex::BIDI_STEP_1_RTL, $label) !== 1) {
                $this->domain->setInvalidBidi();

                return;
            }

            // Step 2. In an RTL label, only characters with the Bidi properties R, AL, AN, EN, ES,
            // CS, ET, ON, BN, or NSM are allowed.
            if (preg_match(Regex::BIDI_STEP_2, $label) === 1) {
                $this->domain->setInvalidBidi();

                return;
            }

            // Step 3. In an RTL label, the end of the label must be a character with Bidi property
            // R, AL, EN, or AN, followed by zero or more characters with Bidi property NSM.
            if (preg_match(Regex::BIDI_STEP_3, $label) !== 1) {
                $this->domain->setInvalidBidi();

                return;
            }

            // Step 4. In an RTL label, if an EN is present, no AN may be present, and vice versa.
            if (
                preg_match(Regex::BIDI_STEP_4_AN, $label) === 1
                && preg_match(Regex::BIDI_STEP_4_EN, $label) === 1
            ) {
                $this->domain->setInvalidBidi();

                return;
            }

            return;
        }

        // We are a LTR label
        // Step 1. The first character must be a character with Bidi property L, R, or AL.
        // If it has the L property, it is an LTR label.
        if (preg_match(Regex::BIDI_STEP_1_LTR, $label) !== 1) {
            $this->domain->setInvalidBidi();

            return;
        }

        // Step 5. In an LTR label, only characters with the Bidi properties L, EN,
        // ES, CS, ET, ON, BN, or NSM are allowed.
        if (preg_match(Regex::BIDI_STEP_5, $label) === 1) {
            $this->domain->setInvalidBidi();

            return;
        }

        // Step 6.In an LTR label, the end of the label must be a character with Bidi property L or
        // EN, followed by zero or more characters with Bidi property NSM.
        if (preg_match(Regex::BIDI_STEP_6, $label) !== 1) {
            $this->domain->setInvalidBidi();

            return;
        }
    }
}
