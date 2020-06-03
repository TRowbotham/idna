<?php

declare(strict_types=1);

namespace Rowbot\Idna\Bin;

use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

use function sprintf;

use const DIRECTORY_SEPARATOR;

abstract class AbstractDataLoader
{
    protected const BASE_URI = 'https://www.unicode.org/Public';
    protected const CACHE_TTL = 86400 * 7; // 7 DAYS
    protected const CACHE_DIR = __DIR__
        . DIRECTORY_SEPARATOR
        . '..'
        . DIRECTORY_SEPARATOR
        . 'build'
        . DIRECTORY_SEPARATOR
        . 'cache';

    /**
     * @var string
     */
    private $version;

    public function __construct(string $version)
    {
        $this->version = $version;
    }

    abstract protected function buildBaseUrl(string $version): string;

    public function fetch(string $filename): string
    {
        $cache = new FilesystemAdapter(
            'unicode-data',
            self::CACHE_TTL,
            self::CACHE_DIR
        );
        $uniData = $cache->getItem(
            sprintf('%s_%s', $this->version, explode('/', $filename)[1] ?? $filename)
        );

        if ($uniData->isHit()) {
            return $uniData->get();
        }

        $client = new Client([
            'base_uri' => $this->buildBaseUrl($this->version)
        ]);

        $response = $client->request('GET', $filename);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException(
                sprintf('Recieved a %d response code.', $response->getStatusCode())
            );
        }

        $data = (string) $response->getBody();
        $uniData->set($data);
        $cache->save($uniData);

        return $data;
    }
}
