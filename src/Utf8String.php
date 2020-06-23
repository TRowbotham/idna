<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Rowbot\Idna\Exception\MappingException;

use function sprintf;

class Utf8String
{
    /**
     * @var string
     */
    protected $string;

    public function __construct(string $string)
    {
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
    public function mapCodePoints(array $options, DomainInfo $info): self
    {
        $codePoints = new CodePointString($this->string);
        $table = new MappingTable();
        $str = '';
        $useSTD3ASCIIRules = $options['UseSTD3ASCIIRules'];
        $transitional = $options['Transitional_Processing'];
        $errors = 0;
        $transitionalDifferent = false;

        foreach ($codePoints as $codePoint) {
            $data = $table->lookup($codePoint, $useSTD3ASCIIRules);

            switch ($data['status']) {
                case 'disallowed':
                    $errors |= Idna::ERROR_DISALLOWED;

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
                    $transitionalDifferent = true;
                    $str .= ($transitional ? $data['mapping'] : CodePoint::encode($codePoint));

                    break;

                default:
                    throw new MappingException(sprintf(
                        'Unknown mapping status %s.',
                        $data['status']
                    ));
            }
        }

        $info->addError($errors);

        if ($transitionalDifferent) {
            $info->setTransitionalDifferent();
        }

        $copy = clone $this;
        $copy->string = $str;

        return $copy;
    }

    public function normalize(): self
    {
        $str = new CodePointString($this->string);
        $copy = clone $this;
        $copy->string = $str->maybeNormalize();

        return $copy;
    }

    public function toString(): string
    {
        return $this->string;
    }
}
