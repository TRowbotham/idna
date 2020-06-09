<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Rowbot\Punycode\CodePointString as PunycodeCodePointString;
use Normalizer;

use function count;

use const DIRECTORY_SEPARATOR as DS;

class CodePointString extends PunycodeCodePointString
{
    protected const RESOURCE_DIR = __DIR__ . DS . '..' . DS . 'resources';
    protected const NO = 0;
    protected const YES = 1;
    protected const MAYBE = -1;

    /**
     * @var array<int, string>
     */
    protected static $normalizationProps;

    /**
     * @var string
     */
    protected $input;

    public function __construct(string $input)
    {
        parent::__construct($input);

        $this->input = $input;
    }

    public function codePointAt(int $index): ?int
    {
        return $this->codePoints[$index] ?? null;
    }

    public function maybeNormalize(): string
    {
        if ($this->quickCheckNFC() === self::YES) {
            return $this->input;
        }

        return Normalizer::normalize($this->input, Normalizer::FORM_C);
    }

    public function isNormalized(): bool
    {
        $result = $this->quickCheckNFC();

        if ($result === self::YES) {
            return true;
        }

        if ($result === self::NO) {
            return false;
        }

        return $this->input === Normalizer::normalize($this->input, Normalizer::FORM_C);
    }

    /**
     * @see https://www.unicode.org/reports/tr15/#Detecting_Normalization_Forms
     */
    protected function quickCheckNFC(): int
    {
        if (!isset(self::$normalizationProps)) {
            self::$normalizationProps = require self::RESOURCE_DIR . DS . 'normalizationProps.php';
        }

        $lastCanonicalClass = 0;
        $result = self::YES;
        $length = count($this->codePoints);

        for ($i = 0; $i < $length; ++$i) {
            $codePoint = $this->codePoints[$i];
            $canonicalClass = CodePoint::getCombiningClass($codePoint);

            if ($lastCanonicalClass > $canonicalClass && $canonicalClass !== 0) {
                return self::NO;
            }

            $isAllowed = self::YES;

            if (isset(self::$normalizationProps[$codePoint])) {
                $isAllowed = self::$normalizationProps[$codePoint] === 'N' ? self::NO : self::MAYBE;
            }

            if ($isAllowed === self::NO) {
                return self::NO;
            }

            if ($isAllowed === self::MAYBE) {
                return self::MAYBE;
            }

            $lastCanonicalClass = $canonicalClass;
        }

        return $result;
    }
}
