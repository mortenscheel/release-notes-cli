<?php

namespace App;

class LocalConfig
{
    public function createDatabaseFile(): void
    {
        if (! file_exists($this->getConfigPath()) &&
            ! mkdir($concurrentDirectory = $this->getConfigPath(), 0755) &&
            ! is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        if (! file_exists($this->getDatabasePath())) {
            touch($this->getDatabasePath());
        }
    }

    public function databaseExists(): bool
    {
        return file_exists($this->getDatabasePath());
    }

    public function getDatabasePath(): string
    {
        return $this->getConfigPath('/cache.sqlite');
    }

    private function getConfigPath(string $relative = ''): string
    {
        return rtrim($_SERVER['HOME'].DIRECTORY_SEPARATOR.'.release-notes'.DIRECTORY_SEPARATOR.ltrim($relative, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
    }
}
