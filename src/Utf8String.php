<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Normalizer;
use Rowbot\Idna\Exception\MappingException;
use Rowbot\Punycode\CodePoint;
use Rowbot\Punycode\CodePointString;

use function explode;
use function sprintf;

class Utf8String
{
    /**
     * @var bool
     */
    protected $transitionalDifferent;

    /**
     * @var int
     */
    protected $errors;

    /**
     * @var string
     */
    protected $string;

    public function __construct(string $string)
    {
        $this->transitionalDifferent = false;
        $this->errors = 0;
        $this->string = $string;
    }

    public function isEmpty(): bool
    {
        return $this->string === '';
    }

    /**
     * @see https://www.unicode.org/reports/tr46/#ProcessingStepMap
     *
     * @param array<string, bool> $options
     */
    public function mapCodePoints(array $options): self
    {
        $codePoints = new CodePointString($this->string);
        $table = new MappingTable();
        $str = '';
        $useSTD3ASCIIRules = $options['UseSTD3ASCIIRules'];
        $transitional = $options['Transitional_Processing'];

        foreach ($codePoints as $codePoint) {
            $data = $table->lookup($codePoint, $useSTD3ASCIIRules);

            switch ($data['status']) {
                case 'disallowed':
                    $this->errors |= Idna::ERROR_DISALLOWED;

                    // no break.

                case 'valid':
                    $str .= CodePoint::encode($codePoint);

                    break;

                case 'ignored':
                    // Do nothing.
                    break;

                case 'mapped':
                    $str .= $data['mapping'];

                    break;

                case 'deviation':
                    $this->transitionalDifferent = true;
                    $str .= ($transitional ? $data['mapping'] : CodePoint::encode($codePoint));

                    break;

                default:
                    throw new MappingException(sprintf(
                        'Unknown mapping status %s.',
                        $data['status']
                    ));
            }
        }

        $copy = clone $this;
        $copy->string = $str;

        return $copy;
    }

    public function normalize(): self
    {
        if (Normalizer::isNormalized($this->string, Normalizer::FORM_C)) {
            return $this;
        }

        $copy = clone $this;
        $copy->string = Normalizer::normalize($this->string, Normalizer::FORM_C);

        return $copy;
    }

    public function toDomain(): Domain
    {
        $labels = [];

        foreach (explode('.', $this->string) as $label) {
            $labels[] = new Label($label);
        }

        $domain = new Domain($labels, $this->errors);

        if ($this->transitionalDifferent) {
            $domain->setTransitionalDifferent();
        }

        return $domain;
    }
}
