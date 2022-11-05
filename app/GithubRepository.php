<?php

namespace App;

use GrahamCampbell\GitHub\GitHubManager;

class GithubRepository
{
    public string $fullName;

    public string $url;

    public array $metadata = [];

    public bool $exists;

    public function __construct(public string $username, public string $repository)
    {
        $this->fullName = "$this->username/$this->repository";
        $this->url = "https://github.com/$this->fullName";
        try {
            $this->metadata = app(GitHubManager::class)->repository()->show($this->username, $this->repository);
            $this->exists = true;
        } catch (\Throwable) {
            $this->exists = false;
        }
    }
}
