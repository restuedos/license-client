# EDZero License Client

Composer package **`edzero/license-client`**: activation UI (`/license/activate`), HTTP middleware, RSA verify of offline license tokens, and `php artisan license:activate`.

Used by the main EDZero app via path repository ([`composer.json`](../../composer.json)). Config is merged from [`config/license-client.php`](config/license-client.php) (publish with `php artisan vendor:publish --tag=license-client-config` if you need overrides).

## Documentation map

| Doc | Content |
|-----|---------|
| [EDZero root `README.md`](../../README.md) | Docker, `EnforceLicenseWhenConfigured`, first-time license setup |
| [`License/README.md`](../README.md) | Keys, server API, customer-facing checklist |
| [`License/license-server/README.md`](../license-server/README.md) | Verify/generate API, server env |

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
| `LICENSE_VERIFY_TIMEOUT` | HTTP timeout seconds (default 10). |
| `LICENSE_CLOCK_SKEW_SECONDS` | Allowed skew when checking token `expires_at` (default 120). |
| `LICENSE_DEVICE_FINGERPRINT` | Optional stable override for activation device id (max 128 chars). |

Templates in the monorepo: [`.env.example`](../../.env.example), [`.env.local.example`](../../.env.local.example), [`.env.prod.example`](../../.env.prod.example).

## Commands & routes

- Web: `GET|POST /license/activate` (package routes, `web` middleware).
- CLI: `php artisan license:activate` (no-op message if enforcement is off).

## Middleware

- Global in EDZero: `EnforceLicenseWhenConfigured` → `EnsureLicenseIsValid` when enforcement applies.
- Alias: `license.valid` (for route groups), defined in [`LicenseClientServiceProvider`](src/LicenseClientServiceProvider.php).

PHPUnit: EDZero skips the gate via `PHPUNIT_COMPOSER_INSTALL` — do not mimic that in `public/index.php`.
