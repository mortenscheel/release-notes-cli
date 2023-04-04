<?php

namespace App\Services;

use App\Release;
use App\Repository;
use App\Services\Github\GithubConnector;
use App\Services\Github\IndexReleasesRequest;
use App\Services\Github\ShowLatestReleaseRequest;
use App\Services\Github\ShowReleaseForTagRequest;
use App\Services\Github\ShowRepoRequest;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Response;
use Throwable;

class Github
{
    private const MINUTE = 60;

    private const DAY = self::MINUTE * 60 * 24;

    private const MONTH = self::DAY * 30;

    /** @var array|int[] */
    private static array $lifetime = [
        'repo-lookup' => self::MONTH,
        'latest-release' => self::MINUTE * 5,
        'tag-release' => self::MONTH * 12,
        'all-releases' => self::MINUTE * 30,
    ];

    public function repositoryExists(Repository $repository): bool
    {
        return Cache::remember(
            "repo-lookup--$repository->fullName",
            self::$lifetime['repo-lookup'],
            function () use ($repository) {
                try {
                    (new ShowRepoRequest($repository->username, $repository->repository))->send();

                    return true;
                } catch (Throwable) {
                    return null;
                }
            }
        ) ?? false;
    }

    public function getLatestRelease(Repository $repository): ?Release
    {
        /** @var array{tag_name: string, html_url: string, published_at: string, body: string}|null $data */
        $data = Cache::remember(
            "latest-release-$repository->fullName",
            self::$lifetime['latest-release'],
            function () use ($repository) {
                try {
                    return (new ShowLatestReleaseRequest($repository->username, $repository->repository))->send()->json();
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
        /** @var array{tag_name: string, html_url: string, published_at: string, body: string}|null $data */
        $data = Cache::remember(
            "tag-release-$repository->fullName-$tag",
            self::$lifetime['tag-release'],
            function () use ($repository, $tag) {
                try {
                    return (new ShowReleaseForTagRequest($repository->username, $repository->repository, $tag))->send()->json();
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
     * @return Release[]
     */
    public function getAllReleases(Repository $repository): array
    {
        /** @var array<int, array{tag_name: string, html_url: string, published_at: string, body: string}> $data */
        $data = Cache::remember(
            "all-releases-$repository->fullName",
            self::$lifetime['all-releases'],
            function () use ($repository) {
                try {
                    $connector = new GithubConnector();
                    $paginator = $connector->paginate(new IndexReleasesRequest($repository->username, $repository->repository));
                    /** @var \Illuminate\Support\LazyCollection<int, Response> $responses */
                    $responses = $paginator->collect();

                    return $responses->map(fn (Response $response) => $response->json())->flatten(1)->toArray();
                } catch (Throwable) {
                    return null;
                }
            }
        );
        if (! $data) {
            return [];
        }
        /** @var Release[] $releases */
        $releases = collect($data)->map(function (array $item) {
            /** @var array{tag_name: string, html_url: string, published_at: string, body: string} $item */
            return Release::fromApi($item);
        })
            ->sortBy('normalizedVersion', SORT_NATURAL)
            ->values()
            ->toArray();

        return $releases;
    }
}
