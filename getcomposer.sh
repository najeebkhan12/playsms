#!/bin/sh

PATHSRC=$(pwd)

echo
echo "Getting composer from https://getcomposer.org"
echo
echo "Please wait while this script downloading composer"
echo

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

echo "Composer has been installed: version $(composer --version)" 