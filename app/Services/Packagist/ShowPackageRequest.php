<?php

namespace App\Services\Packagist;

use Saloon\Enums\Method;
use Saloon\Http\SoloRequest;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

class ShowPackageRequest extends SoloRequest
{
    use AlwaysThrowOnErrors;

    protected Method $method = Method::GET;

    public function __construct(
        private string $package
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('https://packagist.org/packages/%s.json', $this->package);
    }
}
