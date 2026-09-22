#!/bin/sh

PATHSRC=$(pwd)

echo
echo "Getting composer via official docker image"
echo

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

echo "Composer has been installed: version $(composer --version)" 