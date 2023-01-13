<?php

namespace App\Services;

use App\Release;
use App\Repository;
use Github\AuthMethod;
use Github\Client;
use Github\ResultPager;
use Illuminate\Support\Facades\Cache;
use Throwable;

class Github
{
    private const MINUTE = 60;

    private const DAY = self::MINUTE * 60 * 24;

    private const MONTH = self::DAY * 30;

    private static array $lifetime = [
        'repo-lookup' => self::MONTH,
        'latest-release' => self::MINUTE * 5,
        'tag-release' => self::MONTH * 12,
        'all-releases' => self::MINUTE * 30,
    ];

    private Client $client;

    public function __construct(?string $token)
    {
        $this->client = new Client();
        if ($token) {
            $this->client->authenticate(
                tokenOrLogin: $token,
                authMethod: AuthMethod::ACCESS_TOKEN
            );
        }
    }

    public function repositoryExists(Repository $repository): bool
    {
        return Cache::remember(
            "repo-lookup--$repository->fullName",
            self::$lifetime['repo-lookup'],
            function () use ($repository) {
                try {
                    $this->client->repositories()->show($repository->username, $repository->repository);

                    return true;
                } catch (Throwable) {
                    return null;
                }
            }
        ) ?? false;
    }

    public function getLatestRelease(Repository $repository): ?Release
    {
        $data = Cache::remember(
            "latest-release-$repository->fullName",
            self::$lifetime['latest-release'],
            function () use ($repository) {
                try {
                    return $this->client->repositories()->releases()->latest($repository->username, $repository->repository);
                } catch (Throwable) {
                    return null;
                }
            }
        );
        if (! $data) {
            return null;
        }

        return Release::fromApi($data);
    }

    public function getReleaseForTag(Repository $repository, string $tag): ?Release
    {
        $data = Cache::remember(
            "tag-release-$repository->fullName-$tag",
            self::$lifetime['tag-release'],
            function () use ($repository, $tag) {
                try {
                    return $this->client->repositories()->releases()->tag(
                        $repository->username,
                        $repository->repository,
                        $tag
                    );
                } catch (Throwable) {
                    return null;
                }
            }
        );
        if (! $data) {
            return null;
        }

        return Release::fromApi($data);
    }

    /**
     * @param  \App\Repository  $repository
     * @return Release[]
     */
    public function getAllReleases(Repository $repository): array
    {
        $data = Cache::remember(
            "all-releases-$repository->fullName",
            self::$lifetime['all-releases'],
            function () use ($repository) {
                try {
                    return (new ResultPager($this->client))->fetchAll($this->client->repository()->releases(), 'all', [
                        $repository->username,
                        $repository->repository,
                    ]);
                } catch (Throwable) {
                    return null;
                }
            }
        );
        if (! $data) {
            return [];
        }

        return collect($data)->map(fn (array $item) => Release::fromApi($item))
            ->sortBy('normalizedVersion', SORT_NATURAL)
            ->values()
            ->toArray();
    }
}
