# Create an Admin account

The supported CLI flow creates a tenant and its **initial Admin account**
together. It generates a random password that is never displayed; the Admin
sets their own password using a one-time reset code.

## Requirements

- The Laravel backend is configured in `backend-laravel/.env` and can connect
  to the initialized database.
- You know the tenant name, an unused subdomain, its three-letter currency,
  locale, and the initial Admin's email address.
- For password setup by email, configure a working email delivery provider.
  In local development, the configured `log` provider writes the OTP to the
  application log; it is not returned by the API.

## 1. Provision the tenant and initial Admin

From the repository root, run the command in `backend-laravel`. The lifecycle
CLI is disabled by default, so enable it for this command only and identify the
operator with a non-secret reference:

```sh
cd backend-laravel
TENANT_LIFECYCLE_CLI_ENABLED=true TENANT_CLI_OPERATOR_REF=your-operator-id \
  php artisan tenant:provision "Shop name" shopname USD en \
  --admin-email=admin@example.test
```

Replace the example values with the approved tenant and Admin details. Use a
valid DNS-label subdomain (for example, `shop-east`), an ISO 4217 currency
code, and a supported language or language-region locale (for example, `en`
or `en-US`).

On success, record the tenant UUID reported by the command. The new tenant is
active, and the supplied email is its initial Admin login. The command does
not print or create a usable password. Provisioning is audited with the
operator reference. If provisioning fails, resolve the reported error before
retrying; do not create the account by inserting rows manually.

## 2. Open the tenant's Admin sign-in page

The Admin host is `<subdomain>.<ADMIN_BASE_DOMAIN>`. For local development,
with `ADMIN_BASE_DOMAIN=admin.localhost`, open:

```text
http://shopname.admin.localhost:5173
```

Use the port and host configured for your environment. Ensure the tenant host
resolves correctly and the Admin frontend can reach the Laravel API.

## 3. Set the Admin password

On the Admin sign-in page, choose **Forgot password?**, enter the Admin email,
and request a reset code. The app displays a uniform response whether or not
an eligible account exists. Retrieve the six-digit OTP from the configured
delivery channel. In local development with the `log` email provider, check
the Laravel application log.

Choose **Already have a reset code? Enter it here**, enter the email and OTP,
and set a new password. Reset codes expire after 10 minutes and can be used
only once. After a successful reset, sign in with the Admin email and new
password.

### If email delivery is unavailable

An authorized operator may issue the reset OTP in an interactive terminal.
Use the tenant UUID from step 1 and keep the lifecycle gate enabled only for
this command:

```sh
cd backend-laravel
TENANT_LIFECYCLE_CLI_ENABLED=true TENANT_CLI_OPERATOR_REF=your-operator-id \
  php artisan tenant:reset-admin-password TENANT_UUID admin@example.test
```

The command is TTY-only, rate-limited, and displays the OTP once in that
terminal. It expires in 10 minutes. Pass it to the Admin through a secure
channel; do not put it in this file, logs, or shared command history. Enter
the code on the Admin app's reset-code page and choose a password.

## Adding an Admin to an existing tenant

There is currently no supported CLI or Admin UI flow for creating an
additional Admin on an existing tenant. `tenant:provision` always creates a
new tenant and its initial Admin; do not use it to add a user to an existing
tenant or insert user records manually. Use the existing Admin password-reset
flow only for an account that already exists.