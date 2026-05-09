#!/usr/bin/env bash
set -euo pipefail

echo "Installing composer dependencies (if needed)..."
composer install --no-interaction

echo "Running Bedrock connector CLI test..."
php tests/bedrock_test.php "$@"
