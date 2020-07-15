<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Normalizer;
use Rowbot\Punycode\CodePointString as PunycodeCodePointString;

use function count;

use const DIRECTORY_SEPARATOR as DS;

class CodePointString extends PunycodeCodePointString
{
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
}
