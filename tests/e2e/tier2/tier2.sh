#!/usr/bin/env bash
# ============================================================================
# Tier 2 end-to-end test — install the module into a real WHMCS, deliver
# callbacks against a real invoice, and assert what the billing system does.
#
# WHMCS is licensed, proprietary software. Two consequences shape this harness:
#
#   * The release zip is NEVER committed here. Supply it yourself:
#       WHMCS_PACKAGE=/path/to/whmcs_vX_full.zip ./tier2.sh
#     (default: ~/spectrocoin-plugin-audit/.artifacts/whmcs_v8135_full.zip)
#
#   * WHMCS refuses to bootstrap with an empty $license, so provide your
#     development licence key out of band - never on a command line and never
#     in this repository:
#       WHMCS_LICENSE_FILE=/path/to/key.txt ./tier2.sh
#     The key is written into the container's configuration.php and is never
#     printed. Without one the run falls back to a placeholder: WHMCS boots and
#     the module can be exercised, but the shop is unlicensed and its own
#     admin/client pages will complain. That is fine for testing our module and
#     is not a substitute for a licence.
#
# Because of that, this suite is intended to be run locally, not in public CI.
#
# The SpectroCoin API is stood in for by a stub answering as spectrocoin.com
# inside the compose network, over TLS signed by a CA generated here. No
# credentials, no live orders, no calls to the real API.
#
# Usage:
#   ./tier2.sh          # run the full flow
#   ./tier2.sh --keep   # leave the stack running for inspection
# ============================================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/../../.." && pwd)"
WHMCS_PACKAGE="${WHMCS_PACKAGE:-$HOME/spectrocoin-plugin-audit/.artifacts/whmcs_v8135_full.zip}"
WHMCS_LICENSE_FILE="${WHMCS_LICENSE_FILE:-}"
# A node-locked licence validates only against the domain it was issued for.
WHMCS_DOMAIN="${WHMCS_DOMAIN:-shop.test}"
KEEP=0

while [ $# -gt 0 ]; do
  case "$1" in
    --keep) KEEP=1; shift ;;
    *) echo "unknown argument: $1" >&2; exit 2 ;;
  esac
done

say()  { printf '\n\033[1m== %s\033[0m\n' "$*"; }
pass() { printf '  \033[32mPASS\033[0m  %s\n' "$*"; }
fail() { printf '  \033[31mFAIL\033[0m  %s\n' "$*"; FAILED=$((FAILED+1)); }
warn() { printf '  \033[33mNOTE\033[0m  %s\n' "$*"; }
FAILED=0

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

cd "$HERE"

# --------------------------------------------------------------------------
# 1. Inputs.
# --------------------------------------------------------------------------
say "Checking inputs"
if [ -s "$WHMCS_PACKAGE" ]; then
  pass "WHMCS package supplied ($(( $(wc -c < "$WHMCS_PACKAGE") / 1024 / 1024 )) MB)"
else
  fail "no WHMCS package at '$WHMCS_PACKAGE' - set WHMCS_PACKAGE to your release zip"
  echo; echo "tier 2 FAILED (1 check(s))"; exit 1
fi

if [ -n "$WHMCS_LICENSE_FILE" ] && [ -s "$WHMCS_LICENSE_FILE" ]; then
  pass "licence key supplied from a file (contents never printed)"
else
  warn "no WHMCS_LICENSE_FILE - using a placeholder; the shop will be unlicensed"
fi

rm -rf .certs && mkdir -p .certs
openssl req -x509 -newkey rsa:2048 -nodes -days 3650 \
  -keyout .certs/ca.key -out .certs/ca.crt \
  -subj "/CN=SpectroCoin Tier2 Test CA" >/dev/null 2>&1
openssl req -newkey rsa:2048 -nodes -keyout .certs/server.key -out .certs/server.csr \
  -subj "/CN=spectrocoin.com" >/dev/null 2>&1
printf 'subjectAltName=DNS:spectrocoin.com\n' > .certs/ext
openssl x509 -req -in .certs/server.csr -CA .certs/ca.crt -CAkey .certs/ca.key \
  -CAcreateserial -out .certs/server.crt -days 3650 -extfile .certs/ext >/dev/null 2>&1
chmod 644 .certs/*
[ -s .certs/server.crt ] && pass "issued a certificate for spectrocoin.com" \
  || fail "certificate generation failed"

# --------------------------------------------------------------------------
# 2. The stack.
# --------------------------------------------------------------------------
say "Starting WHMCS and the API stub"
docker compose down -v >/dev/null 2>&1 || true
docker compose up -d --build --wait >/dev/null 2>&1

wh()   { docker compose exec -T whmcs "$@"; }
stub() { docker compose exec -T spectrocoin "$@"; }
q()    { docker compose exec -T db mariadb -uroot -proot -N -B whmcs -e "$1" 2>/dev/null | tr -d '\r'; }
# Requests to the shop come from the stub container: that is where a callback
# comes from in production, and only a container on this network resolves the
# shop's hostname.
shopcurl() { docker compose exec -T spectrocoin curl "$@"; }

wh sh -c 'cat /certs/ca.crt >> /etc/ssl/certs/ca-certificates.crt' >/dev/null 2>&1 || true
wh php -r 'exit(extension_loaded("ionCube Loader") ? 0 : 1);' \
  && pass "ionCube Loader present (WHMCS ships encoded PHP)" \
  || fail "ionCube Loader missing - WHMCS cannot run"

# --------------------------------------------------------------------------
# 3. WHMCS.
# --------------------------------------------------------------------------
say "Installing WHMCS"
docker compose cp "$WHMCS_PACKAGE" whmcs:/tmp/whmcs.zip >/dev/null 2>&1
wh sh -c 'cd /tmp && rm -rf whmcs && unzip -q -o whmcs.zip \
          && cp -a /tmp/whmcs/. /var/www/html/ && chown -R www-data:www-data /var/www/html' \
  >/dev/null 2>&1
wh sh -c '[ -f /var/www/html/init.php ]' \
  && pass "WHMCS unpacked" || fail "WHMCS could not be unpacked"

# The licence key is passed through a file, so it never appears in a process
# listing, a log, or this script.
if [ -n "$WHMCS_LICENSE_FILE" ] && [ -s "$WHMCS_LICENSE_FILE" ]; then
  docker compose cp "$WHMCS_LICENSE_FILE" whmcs:/tmp/licence.txt >/dev/null 2>&1
else
  printf 'Dev-TIER2-PLACEHOLDER\n' > "$WORK/licence.txt"
  docker compose cp "$WORK/licence.txt" whmcs:/tmp/licence.txt >/dev/null 2>&1
fi

wh sh -c 'cat > /var/www/html/configuration.php <<PHP
<?php
\$license = trim(file_get_contents("/tmp/licence.txt"));
\$db_host = "db";
\$db_username = "root";
\$db_password = "root";
\$db_name = "whmcs";
\$cc_encryption_hash = "tier2tier2tier2tier2tier2tier2tier2tier2";
\$templates_compiledir = "templates_c";
PHP
chown www-data:www-data /var/www/html/configuration.php'

wh sh -c 'cd /var/www/html && echo "{\"admin\":{\"username\":\"admin\",\"password\":\"Tier2tier2!\",\"firstname\":\"Tier\",\"lastname\":\"Two\",\"email\":\"tier2@example.com\"}}" \
   | php install/bin/installer.php --install --non-interactive --config' \
  > "$WORK/install.log" 2>&1 || true
if [ "$(q "SELECT value FROM tblconfiguration WHERE setting='Version';")" != "" ]; then
  pass "WHMCS installed ($(q "SELECT value FROM tblconfiguration WHERE setting='Version';"))"
else
  fail "WHMCS install failed:"; tail -8 "$WORK/install.log" | sed 's/^/        /'
fi

# WHMCS refuses to run while the installer is still present.
wh sh -c 'rm -rf /var/www/html/install'

# The licence line must not be empty or WHMCS serves its "not installed" page
# for every request, including the callback.
wh sh -c 'cd /var/www/html && cat > /tmp/probe.php <<PHP
<?php
define("CLIENTAREA", true);
require "/var/www/html/init.php";
echo "INIT_OK";
PHP
php /tmp/probe.php 2>&1 | grep -q INIT_OK' \
  && pass "WHMCS bootstraps (init.php)" \
  || fail "WHMCS does not bootstrap - the callback cannot run"

# --------------------------------------------------------------------------
# 4. The module.
# --------------------------------------------------------------------------
say "Installing and configuring the module"
BUILD="$WORK/build"
mkdir -p "$BUILD"
( cd "$ROOT" && find . -maxdepth 1 -not -path '.' -not -path './.git' \
    -not -path './.github' -not -path './tests' -not -path './.gitignore' \
    -exec cp -r {} "$BUILD/" \; )
# Mirror release.yml exactly. The repository tracks spectrocoin/vendor/guzzlehttp/*
# as GITLINKS, so a checkout leaves them as empty directories - the defect
# behind SC-12882. release.yml removes the tree and installs it properly, and
# so must this, or the harness would test something no merchant ever receives.
rm -rf "$BUILD/spectrocoin/vendor"
( cd "$BUILD" && { composer install -d spectrocoin --no-dev --prefer-dist \
      --optimize-autoloader --no-interaction -q 2>/dev/null \
    || php "$ROOT/../composer.phar" install -d spectrocoin --no-dev --prefer-dist \
      --optimize-autoloader --no-interaction -q ; } ) > "$WORK/composer.log" 2>&1 \
  || { fail "composer install failed:"; tail -4 "$WORK/composer.log" | sed 's/^/        /'; }

docker compose cp "$BUILD/." whmcs:/tmp/module >/dev/null 2>&1
wh sh -c 'cp -a /tmp/module/spectrocoin.php /var/www/html/modules/gateways/ \
          && rm -rf /var/www/html/modules/gateways/spectrocoin \
          && cp -a /tmp/module/spectrocoin /var/www/html/modules/gateways/ \
          && mkdir -p /var/www/html/modules/gateways/callback \
          && cp -a /tmp/module/callback/spectrocoin.php /var/www/html/modules/gateways/callback/ \
          && chown -R www-data:www-data /var/www/html/modules/gateways' \
  && pass "module files installed into modules/gateways" \
  || fail "module files could not be installed"

# The module must ship its own HTTP client. This is worth asserting even though
# the module appears to work without it: WHMCS bundles Guzzle in its own vendor
# tree and init.php loads that first, so an empty vendor/guzzlehttp here is
# masked at runtime and the module silently depends on whatever version the
# host happens to ship. The repository tracks these as gitlinks (SC-12882), so
# only a release.yml-style build produces them.
guzzle=$(wh sh -c "find /var/www/html/modules/gateways/spectrocoin/vendor/guzzlehttp/guzzle/src -name '*.php' 2>/dev/null | wc -l" | tr -d ' \r')
if [ "${guzzle:-0}" -gt 10 ]; then
  pass "the module ships its own HTTP client ($guzzle files)"
else
  fail "guzzle is missing or empty ($guzzle php files) - the module would fall back to WHMCS's own copy"
fi

# Activate the gateway the way the admin area does.
q "DELETE FROM tblpaymentgateways WHERE gateway='spectrocoin';
   INSERT INTO tblpaymentgateways (gateway, setting, value, \`order\`) VALUES
     ('spectrocoin','name','Crypto payment by SpectroCoin',0),
     ('spectrocoin','type','Third Party Gateway',0),
     ('spectrocoin','visible','on',0),
     ('spectrocoin','projectId','tier2-project',0),
     ('spectrocoin','clientId','tier2-client',0),
     ('spectrocoin','clientSecret','tier2-secret',0);" >/dev/null 2>&1
[ "$(q "SELECT COUNT(*) FROM tblpaymentgateways WHERE gateway='spectrocoin';")" -ge 6 ] \
  && pass "gateway activated and configured" || fail "gateway could not be configured"

# --------------------------------------------------------------------------
# 5. A real invoice, and the matching SpectroCoin order.
# --------------------------------------------------------------------------
say "Creating the invoice"
q "SET SESSION sql_mode='';
   INSERT INTO tblclients (firstname, lastname, email, country, currency, status, datecreated)
   VALUES ('Tier','Two','tier2-client@example.com','LT',1,'Active',NOW());" >/dev/null 2>&1
CLIENT=$(q "SELECT id FROM tblclients ORDER BY id DESC LIMIT 1;")
q "SET SESSION sql_mode='';
   INSERT INTO tblinvoices (userid, invoicenum, date, duedate, subtotal, total, status, paymentmethod)
   VALUES ($CLIENT, 'TIER2-1', CURDATE(), CURDATE(), 12.34, 12.34, 'Unpaid', 'spectrocoin');" >/dev/null 2>&1
INV=$(q "SELECT id FROM tblinvoices ORDER BY id DESC LIMIT 1;")
[ -n "$INV" ] && pass "invoice #$INV created (12.34, Unpaid, paid via spectrocoin)" \
              || fail "invoice could not be created"

stub curl -fsS -X POST http://localhost/__test/reset >/dev/null 2>&1
stub curl -fsS -X POST -H 'Authorization: Bearer stub-access-token' -H 'Content-Type: application/json' \
  -d "{\"orderId\":\"$INV-tier2\",\"receiveAmount\":\"12.34\",\"receiveCurrencyCode\":\"EUR\",\"callbackUrl\":\"http://shop.test/\",\"projectId\":\"tier2-project\"}" \
  http://localhost/api/public/merchants/orders/create >/dev/null 2>&1
UUID=$(stub sh -c 'php -r "\$s=json_decode(file_get_contents(\"/tmp/stub-state.json\"),true); echo array_key_first(\$s[\"orders\"]);"' 2>/dev/null)
[ -n "$UUID" ] && pass "SpectroCoin order created (uuid ${UUID:0:8}…)" \
               || fail "no SpectroCoin order was created"

# The invoice currency must match what the callback compares against.
q "UPDATE tblcurrencies SET code='EUR' WHERE id=1;" >/dev/null 2>&1

# --------------------------------------------------------------------------
# 6. Deliver callbacks and assert what WHMCS does with each status.
# --------------------------------------------------------------------------
say "Delivering callbacks for every status on the wire"

CB="http://$WHMCS_DOMAIN/modules/gateways/callback/spectrocoin.php"

patch_order() {
  stub curl -fsS -X POST -H 'Content-Type: application/json' -d "$1" \
    http://localhost/__test/status >/dev/null 2>&1
}
reset_invoice() {
  q "UPDATE tblinvoices SET status='Unpaid' WHERE id=$INV;
     DELETE FROM tblaccounts WHERE invoiceid=$INV;" >/dev/null 2>&1
}
invoice_status() { q "SELECT status FROM tblinvoices WHERE id=$INV;"; }

deliver() {
  shopcurl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' \
    -d "{\"id\":\"$UUID\",\"merchantApiId\":\"tier2-project\"}" "$CB"
}

check_status() {
  local status="$1" want="$2" note="${3:-}"
  reset_invoice
  patch_order "{\"uuid\":\"$UUID\",\"status\":\"$status\"}"
  local code got
  code=$(deliver)
  got=$(invoice_status)
  if [ "$code" = "200" ] && [ "$got" = "$want" ]; then
    pass "$status -> $want${note:+ ($note)}"
  else
    fail "$status gave HTTP $code and invoice '$got', expected 200 and '$want'${note:+ ($note)}"
  fi
}

check_status NEW     Unpaid "acknowledged, no change"
check_status PENDING Unpaid "acknowledged, no change"
check_status PAID    Paid   "payment applied"
check_status FAILED          Cancelled
check_status CANCELLED       Cancelled
check_status REJECTED        Cancelled
check_status INVALID_PAYMENT Cancelled
check_status EXPIRED         Cancelled

# Informational statuses report on a payment already under way. The invoice
# must be left exactly as it was.
for s in PARTIAL_PAYMENT UNDERPAID LATE_CRYPTO_PAYMENT PENDING_LATE_CRYPTO_PAYMENT \
         PROCESSING_REFUND REFUNDED REJECTED_REFUND TEST TEST_PAID TEST_EXPIRED; do
  check_status "$s" Unpaid "informational, no change"
done

# A settlement must record an actual payment, not just flip the status.
reset_invoice
patch_order "{\"uuid\":\"$UUID\",\"status\":\"PAID\"}"
deliver >/dev/null
paid=$(q "SELECT ROUND(amountin,2) FROM tblaccounts WHERE invoiceid=$INV LIMIT 1;")
[ -n "$paid" ] && pass "the payment is recorded against the invoice ($paid)" \
               || fail "no payment row was written for the settled invoice"

# --------------------------------------------------------------------------
# 7. The callback endpoint is a public URL. It must refuse the obvious abuse.
# --------------------------------------------------------------------------
say "Callback endpoint guards"

code=$(shopcurl -s -o /dev/null -w '%{http_code}' "$CB")
[ "$code" != "200" ] && pass "GET is refused ($code)" \
                     || fail "GET returned 200 - the callback must be POST-only"

code=$(shopcurl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' \
  -d '{"id":"no-such-uuid","merchantApiId":"tier2-project"}' "$CB")
[ "$code" != "200" ] && pass "an unresolvable order is refused ($code)" \
                     || fail "unresolvable order returned 200"

# A settlement in the wrong currency.
patch_order "{\"uuid\":\"$UUID\",\"status\":\"PAID\",\"receiveCurrencyCode\":\"XXX\"}"
reset_invoice
code=$(deliver)
now=$(invoice_status)
if [ "$code" != "200" ] && [ "$now" = "Unpaid" ]; then
  pass "a settlement in the wrong currency is refused ($code)"
else
  fail "currency mismatch returned $code and left the invoice '$now'"
fi

# --------------------------------------------------------------------------
# 8. The module's own transaction log.
# --------------------------------------------------------------------------
say "Gateway log"
errs=$(q "SELECT COUNT(*) FROM tblgatewaylog WHERE gateway='spectrocoin' AND result LIKE '%Unhandled%';")
[ "${errs:-0}" = "0" ] && pass "no unhandled statuses logged" \
                       || fail "$errs unhandled-status entries in the gateway log"

if [ "$KEEP" -eq 1 ]; then
  echo -e "\nstack left running: add '127.0.0.1 $WHMCS_DOMAIN' to /etc/hosts, then"
  echo    "http://$WHMCS_DOMAIN:8092/admin (admin/Tier2tier2!)"
else
  docker compose down -v >/dev/null 2>&1 || true
  rm -rf .certs
fi

echo
[ "$FAILED" -eq 0 ] && echo "tier 2 PASSED" || echo "tier 2 FAILED ($FAILED check(s))"
exit $([ "$FAILED" -eq 0 ] && echo 0 || echo 1)
