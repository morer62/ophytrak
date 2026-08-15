# Cron Jobs

## Overview

Ophyra uses a single daily cron entrypoint:

```text
src/cron/daily.php
```

The same daily automation can also be triggered through the protected HTTP endpoint:

```text
POST /api/system/cron/daily
https://ophyra.com/api/system/cron/daily
```

It runs daily automation for:

- automatic membership/autopay renewals;
- failed payment retries;
- daily database backup;
- backup email delivery;
- operational logs.

Affiliate payouts are manual only. Manual payment methods require admin review.

## What The Daily Cron Does

1. Loads `.env`.
2. Validates it is running from CLI.
3. Runs automatic renewals through the existing `AutopayService`.
4. Runs eligible failed payment retries through the existing retry queue.
5. Writes explicit log lines confirming affiliate payouts are not automated.
6. Writes explicit log lines confirming manual payments are not auto-approved.
7. Creates a compressed database backup.
8. Emails the backup to the configured address.
9. Cleans old backup files according to retention settings.

CLI and HTTP execution both use the same service:

```text
src/Services/DailyAutomationService.php
```

Do not duplicate cron behavior in separate scripts or endpoints.

## What The Daily Cron Does Not Do

- It does not pay affiliates automatically.
- It does not approve manual payments automatically.
- It does not delete customer data after a failed payment.
- It does not expose backups in a public route.
- It does not print database passwords or payment secrets in logs.
- A GET request to the HTTP endpoint does not execute charges.
- A dry run does not charge, retry, create backups, send backup emails or update records.

## Scripts

Main script:

```bash
php src/cron/daily.php
```

Protected HTTP endpoint:

```text
src/views/api/system/cron/daily/index.php
```

Backup-only test:

```bash
php src/cron/database-backup-email.php
```

Retry-only test:

```bash
php src/cron/payment-retry.php
```

Existing autopay renewal script:

```bash
php src/cron/autopay-renewals.php
```

For cPanel, configure only the main `daily.php` unless you intentionally need separate schedules.

## cPanel Cron Command

Configure the cron to run every day at 6:00 AM.

Schedule:

```text
Minute: 0
Hour: 6
Day: *
Month: *
Weekday: *
```

Cron expression:

```text
0 6 * * *
```

Command template:

```bash
/usr/local/bin/php /home/CPANEL_USER/path/to/project/src/cron/daily.php >> /home/CPANEL_USER/logs/ophyra-daily-cron.log 2>&1
```

If the project is installed in `public_html`, use:

```bash
/usr/local/bin/php /home/CPANEL_USER/public_html/src/cron/daily.php >> /home/CPANEL_USER/logs/ophyra-daily-cron.log 2>&1
```

Replace `CPANEL_USER` and `path/to/project` with the real cPanel username and project path.

To find the real path from cPanel Terminal:

```bash
pwd
```

If `/usr/local/bin/php` is not available, run:

```bash
which php
```

Then use the path returned by cPanel.

## cPanel HTTP Fallback Command

Use this only when the hosting plan cannot execute the PHP CLI script reliably.

Schedule:

```text
0 6 * * *
```

Command:

```bash
/usr/bin/curl -fsS -X POST "https://ophyra.com/api/system/cron/daily" -H "X-Cron-Token: CRON_SECRET_VALUE" >> /home/CPANEL_USER/logs/ophyra-daily-http-cron.log 2>&1
```

Dry-run command for testing:

```bash
/usr/bin/curl -fsS -X POST "https://ophyra.com/api/system/cron/daily?dry_run=1" -H "X-Cron-Token: CRON_SECRET_VALUE"
```

If `curl` is not available, confirm its path in cPanel Terminal:

```bash
which curl
```

Do not expose `CRON_SECRET_VALUE` in public docs, screenshots or browser-visible pages.

## Required Environment Variables

Recommended `.env` values:

```text
CRON_SECRET=replace-with-a-long-random-secret
CRON_DAILY_ENABLED=true
CRON_AUTOPAY_ENABLED=true
CRON_RETRY_FAILED_PAYMENTS=true
CRON_BACKUP_ENABLED=true

DB_BACKUP_EMAIL_TO=jonny.dev2020@gmail.com
DB_BACKUP_PATH=/home/CPANEL_USER/backups/ophyra
DB_BACKUP_RETENTION_DAYS=7
DB_BACKUP_EMAIL_MAX_ATTACHMENT_MB=20

CRON_LOG_PATH=/home/CPANEL_USER/logs
CRON_LOCK_PATH=/home/CPANEL_USER/logs/ophyra-daily-cron.lock
```

Do not put database passwords directly in cPanel cron commands.

The backup service reads database configuration from the project `.env`, using `DATABASE_URL` and optional DB credential variables when present.

`CRON_SECRET` is required for the HTTP endpoint. Use a long random value and pass it with:

```text
X-Cron-Token: CRON_SECRET_VALUE
```

The endpoint also accepts `?token=CRON_SECRET_VALUE` as a fallback, but the header is preferred because it is less likely to appear in access logs.

## HTTP Endpoint

Endpoint:

```text
https://ophyra.com/api/system/cron/daily
```

Safe availability check:

```bash
curl -X GET "https://ophyra.com/api/system/cron/daily"
```

Expected GET behavior:

```json
{
  "success": true,
  "message": "Daily cron endpoint is available. Use POST with X-Cron-Token to run it.",
  "runs_charges": false,
  "supports_dry_run": true
}
```

Dry run:

```bash
curl -X POST "https://ophyra.com/api/system/cron/daily?dry_run=1" \
  -H "X-Cron-Token: CRON_SECRET_VALUE"
```

Dry-run response shape:

```json
{
  "success": true,
  "dry_run": true,
  "trigger": "http",
  "message": "Daily cron dry run completed. No charges, retries, emails or state changes were executed.",
  "results": {
    "autopay_renewals": null,
    "payment_retries": null,
    "backup": null,
    "checks": {}
  }
}
```

Real run:

```bash
curl -X POST "https://ophyra.com/api/system/cron/daily" \
  -H "X-Cron-Token: CRON_SECRET_VALUE"
```

Real response shape:

```json
{
  "success": true,
  "dry_run": false,
  "trigger": "http",
  "message": "Daily cron completed.",
  "results": {
    "autopay_renewals": {},
    "payment_retries": {},
    "backup": {}
  }
}
```

Invalid token response:

```json
{
  "success": false,
  "message": "Invalid cron token."
}
```

If another real cron is already running, the endpoint returns `409` with:

```json
{
  "success": false,
  "message": "Daily cron is already running."
}
```

## Execution Lock

Real runs use a file lock to prevent simultaneous execution.

Default lock path:

```text
CRON_LOG_PATH/ophyra-daily-cron.lock
```

Recommended cPanel lock path:

```text
/home/CPANEL_USER/logs/ophyra-daily-cron.lock
```

Dry runs do not acquire the real execution lock and do not modify payment, retry or backup state.

## Backup Storage

Backups should be stored outside public web folders.

Preferred:

```text
/home/CPANEL_USER/backups/ophyra
```

Avoid:

```text
/home/CPANEL_USER/public_html/backups
```

If `DB_BACKUP_PATH` is not configured, the service uses a folder beside the project directory:

```text
../ophyra-db-backups
```

The service also writes a defensive `.htaccess` file in the backup folder, but the folder should still be outside public web access whenever possible.

## Logs

Daily cron log:

```text
.logs/ophyra-daily-cron-YYYY-MM-DD.log
```

Or, if configured:

```text
CRON_LOG_PATH=/home/CPANEL_USER/logs
```

Autopay service also writes existing logs:

```text
.logs/autopay_YYYY-MM-DD.log
```

Logs include:

- start/end time;
- renewals processed;
- renewals successful;
- renewals failed;
- retries processed;
- retries successful;
- retries failed;
- retries abandoned;
- backup filename and size;
- email status;
- cleanup count;
- explicit manual-only affiliate/payment notices.

Logs must not contain card numbers, full tokens, database passwords or provider secrets.

## Autopay Behavior

The cron uses the existing `AutopayService`.

Autopay can charge users when:

- autopay is enabled;
- the user is due for renewal;
- a valid saved payment method exists;
- the payment provider logic succeeds.

On success:

- membership is renewed;
- `payments_all` is updated through the existing audited path;
- billing status is set back to `current`;
- retry entry is completed when applicable.

On failure:

- transaction is marked failed;
- retry is scheduled for the next day;
- billing status is marked `past_due`;
- data is not deleted;
- account is not aggressively suspended by this cron.

## Failed Payment Retries

Rule:

```text
Every morning, retry eligible failed payments.
```

Retries use:

```text
autopay_retry_queue
autopay_transactions
AutopayService::processRetries()
```

The retry queue decides eligibility by `next_retry_date <= CURDATE()` and `status = pending`.

When max retries are reached, autopay can be disabled and the retry is abandoned. Admin can still recover the account manually.

## Payment Providers And Scope

Automatic payment cron must only use automatic methods that are already configured and valid.

Provider scope must respect:

- `id_user_business`;
- `id_owner`;
- `site_key`;
- brand/site settings;
- vendor/business payment settings where implemented;
- provider active status;
- saved payment method validity.

Manual methods, bank transfer and informational payment buttons are not charged automatically.

## Affiliate Payouts

Rule:

```text
Affiliate payouts are manual only.
```

The cron may coexist with commission calculation created by successful payments, but it must not send money to affiliates.

Admins can:

- review commissions;
- group commissions;
- mark commissions paid;
- upload proof;
- keep payout audit records.

## Manual Payments

Rule:

```text
Manual payment methods require admin review.
```

The cron does not approve:

- bank transfers;
- cash payments;
- manual invoices;
- offline payments;
- any vendor/business manual payment method.

Manual payment records must remain pending until an admin approves, rejects or cancels them.

## Manual Testing

Run the full daily cron:

```bash
/usr/local/bin/php /home/CPANEL_USER/path/to/project/src/cron/daily.php
```

Run from project root:

```bash
php src/cron/daily.php
```

Backup-only:

```bash
php src/cron/database-backup-email.php
```

Retry-only:

```bash
php src/cron/payment-retry.php
```

HTTP dry run:

```bash
curl -X POST "https://ophyra.com/api/system/cron/daily?dry_run=1" -H "X-Cron-Token: CRON_SECRET_VALUE"
```

## QA Checklist

Autopay:

- User has autopay enabled.
- User has valid saved payment method.
- User is due for renewal.
- Cron attempts charge.
- Successful charge renews membership.
- `payments_all` receives payment record.
- `autopay_transactions` records success.

Failed payment:

- Failed charge records transaction failure.
- Retry queue receives/updates pending retry.
- User billing status becomes `past_due`.
- Next daily cron retries eligible payment.
- Successful retry marks retry completed.

Manual payment:

- Manual payment remains pending review.
- Cron does not approve it.
- Admin can approve/reject manually.

Affiliate payouts:

- Commissions can remain pending.
- Cron does not pay affiliates.
- Admin payout flow remains manual.

Backup:

- Backup file is created as `.sql.gz`.
- Backup folder is not public.
- Email is sent to `jonny.dev2020@gmail.com`.
- If file is too large, email notice is sent without attachment.
- Logs show backup success or error.

HTTP endpoint:

- GET returns safe JSON and does not run charges.
- POST without token is rejected.
- POST with wrong token is rejected.
- POST with `dry_run=1` returns checks only.
- POST real run uses the same logic as CLI.
- Simultaneous real runs are blocked by the lock.

## Troubleshooting

If the cron does not run:

- Confirm cPanel cron path points to the real project path.
- Confirm PHP path with `which php`.
- Confirm `.env` exists in the project root.
- Confirm `vendor/autoload.php` exists.
- Confirm `.logs` or `CRON_LOG_PATH` is writable.

If backup email fails:

- Confirm SMTP `.env` variables are valid.
- Confirm mailbox attachment size limit.
- Reduce retention or attachment size setting.
- Check `.logs/ophyra-daily-cron-YYYY-MM-DD.log`.
