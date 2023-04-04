<?php

namespace App;

use App\Services\Github;
use App\Services\Packagist;

class Repository
{
    public string $fullName;

    public function __construct(public string $username, public string $repository)
    {
        $this->fullName = "$this->username/$this->repository";
    }

    public static function resolve(string $name): ?self
    {
        $github = app(Github::class);
        $repository = self::parseRepository($name);
        if (! $github->repositoryExists($repository)) {
            $url = app(Packagist::class)->findGithubUrl($repository);
            if (! $url) {
                return null;
            }
            $repository = self::parseRepository($url);
            if (! $github->repositoryExists($repository)) {
                return null;
            }
        }

        return $repository;
    }

    private static function parseRepository(string $name): Repository
    {
        if ($urlPath = parse_url($name, PHP_URL_PATH)) {
            $path = $urlPath;
        } else {
            $path = $name;
        }
        $path = trim($path, '/');
        [$username, $repository] = explode('/', $path);

        return new Repository($username, $repository);
    }
}
