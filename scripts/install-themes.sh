#!/usr/bin/env bash
set -euo pipefail

THEMES=("storefront" "astra" "generatepress")

for theme in "${THEMES[@]}"; do
	echo "Installing $theme..."
	docker compose run --rm wpcli theme install "$theme" 2>/dev/null || true
done

docker compose run --rm wpcli theme activate storefront
echo "All themes installed. Active theme: storefront"
