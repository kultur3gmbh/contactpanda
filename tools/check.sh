#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

status=0
if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    if ! php -l "$file" >/dev/null; then
      status=1
    fi
  done < <(find contactpanda tests -type f -name '*.php' -print0)
  php tests/trash.php || status=1
  php tests/woocommerce-import.php || status=1
	php tests/order-summary-query.php || status=1
  php tests/groundhogg-migration-source.php || status=1
  php tests/marketing-status-icons.php || status=1
  php tests/github-updater.php || status=1
  php tests/github-updater.php --plus || status=1
	php tests/vcard-import.php || status=1
	php tests/events.php || status=1
	php tests/events-unavailable.php || status=1
	php tests/flow-hooks.php || status=1
	php tests/release-050.php || status=1
	php tests/release-060.php || status=1
elif command -v python3 >/dev/null 2>&1; then
  echo "PHP CLI is unavailable; running the offline structural fallback."
  python3 tools/check_php_structure.py contactpanda tests || status=1
	echo "SKIPPED: PHP trash, WooCommerce import and order summary, Groundhogg migration, marketing status icon, updater, vCard, event-adapter, and release contract behavior tests require PHP CLI."
else
  echo "PHP or Python 3 is required for source validation." >&2
  status=1
fi

if command -v node >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    if ! node --check "$file" >/dev/null; then
      status=1
    fi
  done < <(find contactpanda -type f -name '*.js' -print0)
fi

basic_header="$(awk -F': ' '/^ \* Version:/{print $2; exit}' contactpanda/contactpanda.php)"
basic_constant="$(sed -n "s/^define( 'CONTACTPANDA_VERSION', '\([^']*\)' );/\1/p" contactpanda/contactpanda.php | head -1)"
basic_stable="$(awk -F': ' '/^Stable tag:/{print $2; exit}' contactpanda/readme.txt)"

if [[ -z "$basic_header" || "$basic_header" != "$basic_constant" || "$basic_header" != "$basic_stable" ]]; then
  echo "ContactPanda Basic header, version constant, and stable tag do not match." >&2
  status=1
fi

if ! grep -q "Text Domain: contactpanda$" contactpanda/contactpanda.php; then
  echo "ContactPanda Basic text domain is missing or unexpected." >&2
  status=1
fi

if [[ -e contactpanda/includes/class-contactpanda-woocommerce.php || -e contactpanda/assets/contactpanda-checkout.css ]]; then
  echo "WooCommerce cart and checkout presentation belongs to ContactPanda Plus, not Basic." >&2
  status=1
fi

if [[ "$status" -ne 0 ]]; then
  exit "$status"
fi

echo "ContactPanda Basic checks passed."


