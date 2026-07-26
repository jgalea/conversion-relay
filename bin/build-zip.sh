#!/usr/bin/env bash
# Build the WordPress.org distribution: production autoloader + bundled Action
# Scheduler, dev tooling stripped. Output goes OUTSIDE the plugin directory so
# Plugin Check never scans the build as part of the plugin.
set -euo pipefail

cd "$(dirname "$0")/.."
SLUG="conversion-relay"
OUT="$(cd .. && pwd)/wpch-build"

rm -rf "$OUT"
mkdir -p "$OUT/${SLUG}"

composer install --no-dev --optimize-autoloader --no-interaction

# composer.json ships because vendor/ ships; Plugin Check reports
# missing_composer_json_file otherwise.
rsync -a ./ "$OUT/${SLUG}/" \
	--exclude '.git' \
	--exclude '.github' \
	--exclude '.gitattributes' \
	--exclude '.distignore' \
	--exclude '.wp-env.json' \
	--exclude '.wordpress-org' \
	--exclude 'bin' \
	--exclude 'tests' \
	--exclude 'docs' \
	--exclude 'node_modules' \
	--exclude 'composer.lock' \
	--exclude 'phpunit.xml.dist' \
	--exclude 'phpcs.xml.dist' \
	--exclude 'phpstan.neon' \
	--exclude '*.md' \
	--exclude '.phpunit.result.cache' \
	--exclude '.phpcs.cache' \
	--exclude '.DS_Store'

( cd "$OUT" && zip -rq "${SLUG}.zip" "${SLUG}" )

# Restore dev dependencies for local work.
composer install --no-interaction >/dev/null 2>&1 || true

echo "Built ${OUT}/${SLUG}.zip"
echo "Package dir: ${OUT}/${SLUG}"
