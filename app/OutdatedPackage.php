<?php

namespace App;

class OutdatedPackage
{
    public function __construct(
        public string $name,
        public string $current,
        public string $latest,
        public bool $safe,
        public bool $abandoned
    ) {
    }
}
