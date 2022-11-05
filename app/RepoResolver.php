<?php

namespace App;

use Cache;
use Packagist\Api\Client;
use Throwable;

class RepoResolver
{
    public function __construct(
        private Client $packagist,
    ) {
    }

    /**
     * @param  string  $username
     * @param  string|null  $name
     * @return \App\GithubRepository
     *
     * @throws \App\RepositoryNotFoundException
     */
    public function find(string $username, ?string $name = null): GithubRepository
    {
        $repository = Cache::remember("resolved-repo-$username-$name", now()->addMonths(12), fn () => $this->resolve($username, $name));
        if ($repository === false) {
            throw new RepositoryNotFoundException('Failed to resolve repository');
        }

        return $repository;
    }

    private function resolve(string $username, ?string $name = null): GithubRepository|bool
    {
        if ($name === null) {
            if (($repository = $this->parseGithubUrl($username)) === null) {
                [
                    $username,
                    $name,
                ] = explode('/', $username);
                $repository = new GithubRepository($username, $name);
            }
        } else {
            $repository = new GithubRepository($username, $name);
        }
        if ($repository->exists) {
            return $repository;
        }
        if (($fromPackagist = $this->resolveFromPackagist($repository->fullName)) && $fromPackagist->exists) {
            return $fromPackagist;
        }

        return false;
    }

    private function parseGithubUrl(string $url): ?GithubRepository
    {
        if (preg_match('#^https?://github.com/(\w+)/(\w+)/?#', $url, $match)) {
            return new GithubRepository($match[1], $match[2]);
        }

        return null;
    }

    private function resolveFromPackagist(string $packageName): ?GithubRepository
    {
        try {
            return $this->parseGithubUrl($this->packagist->get($packageName)->getRepository());
        } catch (Throwable) {
            return null;
        }
    }
}
