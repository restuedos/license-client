# EDZero License Client

Composer package **`edzero/license-client`**: activation UI (`/license/activate`), HTTP middleware, RSA verify of offline license tokens, and `php artisan license:activate`.

Used by the main EDZero app via path repository ([`composer.json`](../../composer.json)). Config is merged from [`config/license-client.php`](config/license-client.php) (publish with `php artisan vendor:publish --tag=license-client-config` if you need overrides).

**Keys:** this package does not ship or generate RSA material. Install the two **public** keys from your license-server operator (created by `license-server/scripts/generate-keys.sh` → `client-keys/`). Never put server **private** keys in the app.

## Documentation map

| Doc | Content |
|-----|---------|
| [EDZero root `README.md`](../../README.md) | Docker, `EnforceLicenseWhenConfigured`, first-time license setup |
| [`license/README.md`](../README.md) | Keys, server API, customer-facing checklist |
| [`license/license-server/README.md`](../license-server/README.md) | Verify/generate API, server env |

## Environment variables (app `.env` / `.env.local` / `.env.prod`)

| Variable | Purpose |
|----------|---------|
| `LICENSE_VERIFY_URL` | `POST` verify endpoint (e.g. `https://license.example.com/api/license/verify`). **Empty** = HTTP license enforcement **off** (development without a server). |
| `LICENSE_ENFORCEMENT_ENABLED` | When `LICENSE_VERIFY_URL` is set, defaults to **true**. Set `false` only for local debugging — **never** in production. |
| `LICENSE_PRODUCTION_ENVIRONMENTS` | Comma-separated `APP_ENV` values treated as **production** for activation buckets and token claims. **Must match** `LICENSE_PRODUCTION_ENVIRONMENTS` on license-server. |
| `LICENSE_REQUIRE_HTTPS` | Default **true**: verify URL must be `https://` and host must appear in `LICENSE_ALLOWED_HOSTS`. |
| `LICENSE_ALLOWED_HOSTS` | Exact hostname(s) from `LICENSE_VERIFY_URL` (comma-separated). |
| `LICENSE_ENCRYPTION_PUBLIC_KEY_PATH` | RSA public key to encrypt license key for verify payload. |
| `LICENSE_VERIFICATION_PUBLIC_KEY_PATH` | RSA public key to verify signed `license_token` offline. |
| `LICENSE_STATUS_FILE` | JSON path for activated state (default `storage/app/private/license.json`). |
| `LICENSE_REQUEST_HMAC_SECRET` | Optional; must match license-server when HMAC is enabled there. |
| `LICENSE_LOCAL_HMAC_SECRET` | HMAC over stored status (defaults to `APP_KEY`). |
| `LICENSE_VERIFY_TIMEOUT` | HTTP timeout in seconds for **both** verify and heartbeat requests (default 10). Config key `license-client.request_timeout`. |
| `LICENSE_CLOCK_SKEW_SECONDS` | Allowed skew when checking token `expires_at` (default 120). |
| `LICENSE_DEVICE_FINGERPRINT` | Optional stable override for activation device id (max 128 chars). If unset, the default hash uses `php_uname('n')`, which can differ between **php-fpm** and **`php artisan`** on the same app — after activation, the fingerprint used at verify is stored in `LICENSE_STATUS_FILE` so **heartbeat** matches without this override. Re-run **activate** once after upgrading `license-client` if your `license.json` has no `device_fingerprint` key yet. |
| `LICENSE_HEARTBEAT_ENABLED` | When **true** with enforcement on, HTTP access requires a fresh **`last_heartbeat_at`** (see **`LICENSE_HEARTBEAT_MAX_STALE_HOURS`**). Run **`php artisan license:heartbeat`** daily via **`schedule:run`**. |
| `LICENSE_HEARTBEAT_URL` | Optional; default = same host as verify URL with path **`/api/license/heartbeat`**. |
| `LICENSE_HEARTBEAT_MAX_STALE_HOURS` | After this many hours without a successful heartbeat, the gate treats the license as invalid (default **48**). |

Templates in the monorepo: [`.env.example`](../../.env.example), [`.env.local.example`](../../.env.local.example), [`.env.prod.example`](../../.env.prod.example).

## Commands & routes

- Web: `GET|POST /license/activate` (package routes, `web` middleware).
- CLI: `php artisan license:activate`, `php artisan license:heartbeat` (heartbeat skips when disabled or enforcement off).

## Middleware

- Global in EDZero: `EnforceLicenseWhenConfigured` → `EnsureLicenseIsValid` when enforcement applies.
- Alias: `license.valid` (for route groups), defined in [`LicenseClientServiceProvider`](src/LicenseClientServiceProvider.php).

PHPUnit: EDZero skips the gate via `PHPUNIT_COMPOSER_INSTALL` — do not mimic that in `public/index.php`.
