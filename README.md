## Installation
Requirements: Linux or Mac with PHP 8.0 and Composer installed.
```shell
composer global require mortenscheel/release-notes-cli
```
## Usage
```
release-notes <name> [options]

Arguments:
  name                  Name of the repository or package

Options:
  --tag[=TAG]           Specific tag
  --from[=FROM]         From version
  --to[=TO]             To version
  --help                Display help
  
Note:
  If neither --tag, --from or --too is provided, only the latest release will be displayed
```
#### Show latest release notes
```shell
release-notes laravel/framework
```
#### Show release notes for specific tag
```shell
release-notes laravel/framework --tag v9.34.0
```

#### Show all releases since specific version
```shell
release-notes laravel/framework --from 9.0
```

## Caching
To enable caching, run
```shell
release-notes cache:init
```
The cache can be flushed manually by running
```shell
release-notes cache:clear
```

## Docker
```shell
docker run --rm -it mono2990/release-notes {repo}
```
You can optionally pass your Github token using an environment variable:
```shell
docker run --rm -it -e RELEASE_NOTES_GITHUB_TOKEN={token} mono2990/release-notes {repo}
```
Caching is not supported when running in docker
