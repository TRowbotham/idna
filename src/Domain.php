<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Countable;
use Generator;
use IteratorAggregate;

use function count;
use function implode;
use function strlen;

/**
 * @implements \IteratorAggregate<int, \Rowbot\Idna\Label>
 */
class Domain implements Countable, IteratorAggregate
{
    private const MAX_DOMAIN_SIZE = 253;

    /**
     * @var bool
     */
    protected $bidi;

    /**
     * @var int
     */
    protected $errors;

    /**
     * @var array<int, \Rowbot\Idna\Label>
     */
    protected $labels;

    /**
     * @var bool
     */
    protected $validBidi;

    /**
     * @var bool
     */
    protected $transitionalDifferent;

    /**
     * @param array<int, \Rowbot\Idna\Label> $labels
     */
    public function __construct(array $labels, int $errors)
    {
        $this->bidi = false;
        $this->errors = $errors;
        $this->labels = $labels;
        $this->transitionalDifferent = false;
        $this->validBidi = true;
    }

    public function addError(int $errors): void
    {
        $this->errors |= $errors;
    }

    public function count(): int
    {
        return count($this->labels);
    }

    public function getErrors(): int
    {
        return $this->errors;
    }

    /**
     * @return \Generator<int, \Rowbot\Idna\Label>
     */
    public function getIterator(): Generator
    {
        $length = count($this->labels);

        for ($i = 0; $i < $length; ++$i) {
            yield $i => $this->labels[$i];
        }
    }

    /**
     * A Bidi domain name is a domain name containing at least one character with Bidi_Class R, AL, or AN.
     *
     * @see https://www.unicode.org/reports/tr46/#Notation
     * @see https://www.unicode.org/reports/tr9/#Bidirectional_Character_Types
     */
    public function isBidi(): bool
    {
        return $this->bidi;
    }

    public function isTransitionalDifferent(): bool
    {
        return $this->transitionalDifferent;
    }

    public function isValidBidi(): bool
    {
        return $this->validBidi;
    }

    public function replaceLabelAt(int $index, Label $label): void
    {
        $this->labels[$index] = $label;
    }

    public function setBidi(): void
    {
        $this->bidi = true;
    }

    public function setInvalidBidi(): void
    {
        $this->validBidi = false;
    }

    public function setTransitionalDifferent(): void
    {
        $this->transitionalDifferent = true;
    }

    public function toString(): string
    {
        return implode('.', $this->labels);
    }

    public function validateDomainAndLabelLength(): void
    {
        $maxDomainSize = self::MAX_DOMAIN_SIZE;
        $length = count($this->labels);
        $totalLength = $length - 1;

        // If the last label is empty and it is not the first label, then it is the root label.
        // Increase the max size by 1, making it 254, to account for the root label's "."
        // delimiter. This also means we don't need to check the last label's length for being too
        // long.
        if ($length > 1 && $this->labels[$length - 1]->isEmpty()) {
            ++$maxDomainSize;
            --$length;
        }

        for ($i = 0; $i < $length; ++$i) {
            $bytes = $this->labels[$i]->getBytes();
            $totalLength += $bytes;

            if ($bytes > Label::MAX_LABEL_SIZE) {
                $this->errors |= Idna::ERROR_LABEL_TOO_LONG;
            }
        }

        if ($totalLength > $maxDomainSize) {
            $this->errors |= Idna::ERROR_DOMAIN_NAME_TOO_LONG;
        }
    }
}
