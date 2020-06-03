<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use function sprintf;

class UnicodeDataLoader extends AbstractDataLoader
{
    protected function buildBaseUrl(string $version): string
    {
        return sprintf('%s/%s/ucd/', self::BASE_URI, $version);
    }
}
