#!/usr/bin/env bash

set -e

if ! $(pgrep -f docker > /dev/null); then
    echo "Please start the Docker daemon and try again."
    exit 1
fi
version="$(git describe --tags $(git rev-list --tags --max-count=1))"
read -p "Build and push $version? [y/N] "
[[ ! $REPLY =~ ^[Yy]$ ]] && exit 1

./release-notes app:build --build-version="$version"
docker build -t mono2990/release-notes:"$version" -t mono2990/release-notes:latest .
docker push mono2990/release-notes --all-tags


