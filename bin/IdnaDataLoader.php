<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use function sprintf;

class IdnaDataLoader extends AbstractDataLoader
{
    protected function buildBaseUrl(string $version): string
    {
        return sprintf('%s/idna/%s/', self::BASE_URI, $version);
    }
}
