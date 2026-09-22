#!/bin/sh

PATHSRC=$(pwd)

echo
echo "Checking Composer installation..."
echo

# Verify composer exists
if ! command -v composer >/dev/null 2>&1; then
    echo "ERROR: Composer is not installed or not in PATH"
    exit 1
fi

echo "Composer found:"
composer --version
echo

echo "Installing/updating required packages..."
echo

cd "$PATHSRC"

# Use install if composer.lock exists, otherwise update
if [ -f "composer.lock" ]; then
    composer install --no-interaction
else
    composer update --no-interaction
fi

exit $?