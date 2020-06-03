<?php

declare(strict_types=1);

namespace Rowbot\Idna\Test;

use Symfony\Polyfill\Intl\Idn\Idn;

/**
 * This is intentionally not included as part of the test suite. This exists and is included here
 * for the sole intention of seeing how well the Symfony idn polyfill does against the official
 * tests provided by the Unicode Consortium.
 *
 * If you wish to run this test for yourself, you will need to add the symfony idn polyfill using
 * composer. This can be done by doing:
 *
 * composer require --dev symfony/polyfill-intl-idn
 *
 * then
 *
 * vendor/bin/phpunit tests/SymfonyPolyfill.php
 *
 * The results from the last time I ran this were:
 * Polyfill version 1.17.0
 *
 * Time: 2.94 seconds, Memory: 465.09 MB
 *
 * ERRORS!
 * Tests: 18675, Assertions: 27112, Errors: 7, Failures: 17803.
 */
class SymfonyPolyfill extends IdnaV2TestCase
{
    /**
     * @return array<int, array<int, string>>
     */
    public function getData(): array
    {
        return $this->loadTestData(self::getUnicodeVersion());
    }

    /**
     * @dataProvider getData
     */
    public function testToUtf8(
        string $source,
        string $toUnicode,
        string $toUnicodeStatus,
        string $toAsciiN,
        string $toAsciiNStatus,
        string $toAsciiT,
        string $toAsciiTStatus
    ): void {
        [
            $toUnicode,
            $toUnicodeStatus,
            $toAsciiN,
            $toAsciiNStatus,
            $toAsciiT,
            $toAsciiTStatus,
        ] = $this->translate($source, $toUnicode, $toUnicodeStatus, $toAsciiN, $toAsciiNStatus, $toAsciiT, $toAsciiTStatus);

        $options = IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ | IDNA_USE_STD3_RULES | IDNA_NONTRANSITIONAL_TO_UNICODE;
        Idn::idn_to_utf8($source, $options, INTL_IDNA_VARIANT_UTS46, $info);

        self::assertSame($toUnicode, $info['result']);

        if ($toUnicodeStatus === []) {
            self::assertSame(0, $info['errors']);
        } else {
            self::assertNotSame(0, $info['errors']);
        }
    }

    /**
     * @dataProvider getData
     */
    public function testToAsciiNonTransitional(
        string $source,
        string $toUnicode,
        string $toUnicodeStatus,
        string $toAsciiN,
        string $toAsciiNStatus,
        string $toAsciiT,
        string $toAsciiTStatus
    ): void {
        [
            $toUnicode,
            $toUnicodeStatus,
            $toAsciiN,
            $toAsciiNStatus,
            $toAsciiT,
            $toAsciiTStatus,
        ] = $this->translate($source, $toUnicode, $toUnicodeStatus, $toAsciiN, $toAsciiNStatus, $toAsciiT, $toAsciiTStatus);

        $options = IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ | IDNA_USE_STD3_RULES | IDNA_NONTRANSITIONAL_TO_ASCII;
        Idn::idn_to_ascii($source, $options, INTL_IDNA_VARIANT_UTS46, $info);

        self::assertSame($toAsciiN, $info['result']);

        if ($toAsciiNStatus === []) {
            self::assertSame(0, $info['errors']);
        } else {
            self::assertNotSame(0, $info['errors']);
        }
    }

    /**
     * @dataProvider getData
     */
    public function testToAsciiTransitional(
        string $source,
        string $toUnicode,
        string $toUnicodeStatus,
        string $toAsciiN,
        string $toAsciiNStatus,
        string $toAsciiT,
        string $toAsciiTStatus
    ): void {
        [
            $toUnicode,
            $toUnicodeStatus,
            $toAsciiN,
            $toAsciiNStatus,
            $toAsciiT,
            $toAsciiTStatus,
        ] = $this->translate($source, $toUnicode, $toUnicodeStatus, $toAsciiN, $toAsciiNStatus, $toAsciiT, $toAsciiTStatus);

        $options = IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ | IDNA_USE_STD3_RULES;
        Idn::idn_to_ascii($source, $options, INTL_IDNA_VARIANT_UTS46, $info);

        // There is currently a bug in the test data, where it is expected that the following 2
        // source strings result in an empty string. However, due to the way the test files are setup
        // it currently isn't possible to represent an empty string as an expected value. So, we
        // skip these 2 problem tests. I have notified the Unicode Consortium about this and they
        // have passed the information along to the spec editors.
        if ($source === "\u{200C}" || $source === "\u{200D}") {
            $toAsciiT = '';
        }

        self::assertSame($toAsciiT, $info['result']);

        if ($toAsciiTStatus === []) {
            self::assertSame(0, $info['errors']);
        } else {
            self::assertNotSame(0, $info['errors']);
        }
    }
}
