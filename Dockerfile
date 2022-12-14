FROM php:8.1-cli-alpine

ENV RELEASE_NOTES_GITHUB_TOKEN=""

RUN $(php -r '$extensionInstalled = array_map("strtolower", \get_loaded_extensions(false));$requiredExtensions = ["zlib"];$extensionsToInstall = array_diff($requiredExtensions, $extensionInstalled);if ([] !== $extensionsToInstall) {echo \sprintf("docker-php-ext-install %s", implode(" ", $extensionsToInstall));}echo "echo \"No extensions\"";')

COPY builds/release-notes /release-notes

ENTRYPOINT ["/release-notes"]
