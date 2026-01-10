#!/usr/bin/env bash
# Lightweight PHPUnit runner without Composer.
# Downloads phpunit-9.6 PHAR if missing and runs it with repo config.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHAR_PATH="$ROOT_DIR/tools/phpunit-9.6.phar"
PHP_BIN="${PHP_BIN:-php}"

if [ ! -f "$PHAR_PATH" ]; then
    echo "Downloading phpunit-9.6.phar..."
    curl -fsSL -o "$PHAR_PATH" https://phar.phpunit.de/phpunit-9.6.20.phar
    chmod +x "$PHAR_PATH"
fi

cd "$ROOT_DIR"
"$PHP_BIN" "$PHAR_PATH" -c "$ROOT_DIR/phpunit.xml" "$@"
