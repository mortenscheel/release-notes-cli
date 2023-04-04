<?php

namespace App\Services;

use App\Repository;
use Illuminate\Support\Facades\Cache;
use Packagist\Api\Client;

class Packagist
{
    public function __construct(private Client $client)
    {
    }

    public function findGithubUrl(Repository $repository): ?string
    {
        /** @var string|null $url */
        $url = Cache::remember(
            "packagist-$repository->fullName",
            now()->addMonth(),
            function () use ($repository) {
                try {
                    /** @var \Packagist\Api\Result\Package $package */
                    $package = $this->client->get($repository->fullName);

                    return $package->getRepository();
                } catch (\Throwable) {
                    return false;
                }
            }
        );

        return $url ?: null;
    }
}
