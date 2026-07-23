#!/usr/bin/env bash
# Build a distributable zip for WordPress.org: production autoloader + bundled
# Action Scheduler, dev tooling stripped. Restores dev dependencies afterwards.
set -euo pipefail

cd "$(dirname "$0")/.."
SLUG="wp-conversion-hub"

rm -rf build
mkdir -p "build/${SLUG}"

composer install --no-dev --optimize-autoloader --no-interaction

rsync -a ./ "build/${SLUG}/" \
	--exclude '.git' \
	--exclude '.github' \
	--exclude '.wordpress-org' \
	--exclude 'build' \
	--exclude 'bin' \
	--exclude 'tests' \
	--exclude 'docs' \
	--exclude 'node_modules' \
	--exclude 'composer.json' \
	--exclude 'composer.lock' \
	--exclude 'phpunit.xml.dist' \
	--exclude 'phpcs.xml.dist' \
	--exclude 'phpstan.neon' \
	--exclude '*.md'

( cd build && zip -rq "${SLUG}.zip" "${SLUG}" )

# Restore dev dependencies for local work.
composer install --no-interaction >/dev/null 2>&1 || true

echo "Built build/${SLUG}.zip"
