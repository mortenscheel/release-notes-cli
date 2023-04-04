<?php

namespace App\Services\Github;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Request\HasConnector;

class ShowRepoRequest extends Request
{
    use HasConnector;

    protected Method $method = Method::GET;

    protected string $connector = GithubConnector::class;

    public function __construct(
        private string $username,
        private string $repository
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/repos/%s/%s', rawurlencode($this->username), rawurlencode($this->repository));
    }
}
