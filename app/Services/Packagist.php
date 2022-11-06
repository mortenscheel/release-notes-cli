<?php

namespace App\Services;

use App\Repository;
use Cache;
use Packagist\Api\Client;

class Packagist
{
    public function __construct(private Client $client)
    {
    }

    public function findGithubUrl(Repository $repository): ?string
    {
        $repository = Cache::remember(
            "packagist-$repository->fullName",
            now()->addMonth(),
            function () use ($repository) {
                try {
                    return $this->client->get($repository->fullName)->getRepository();
                } catch (\Throwable) {
                    return false;
                }
            }
        );

        return $repository ?: null;
    }
}
