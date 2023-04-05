<?php

namespace App\Services\Packagist;

use App\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

class Packagist
{
    public function findGithubUrl(Repository $repository): ?string
    {
        /** @var string|null $url */
        $url = Cache::remember(
            "packagist-$repository->fullName",
            now()->addMonth(),
            function () use ($repository) {
                try {
                    return (new ShowPackageRequest($repository->fullName))->send()->json('package.repository');
                } catch (Throwable) {
                    return false;
                }
            }
        );

        return $url ?: null;
    }
}
