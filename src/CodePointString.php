<?php

declare(strict_types=1);

namespace Rowbot\Idna;

use Rowbot\Punycode\CodePointString as PunycodeCodePointString;

class CodePointString extends PunycodeCodePointString
{
    public function codePointAt(int $index): ?int
    {
        return $this->codePoints[$index] ?? null;
    }
}
