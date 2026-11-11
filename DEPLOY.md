# Deploying the REFRA store

## Server requirements

- PHP 8.2 or newer with `gd` (WebP enabled), `zip`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `dom`
- MySQL 8 or MariaDB 10.6+
- HTTPS. The live camera try-on and secure cookies both need it.
- `upload_max_filesize` of 64M and `post_max_size` of 70M, for 3D models and 360° archives

Check WebP on the server with: `php -r "var_dump(gd_info()['WebP Support']);"`

## 1. Build on your computer

```
npm ci
npm run build
```

This produces `public/build`, which must be uploaded. The server does not need Node.

## 2. Upload

Upload the project **except** `node_modules`, `.env`, `tests`, `storage/logs/*`, `storage/framework/sessions/*`, `storage/framework/views/*`, and the `public/storage` link.

Point the domain's web root at the **`public`** folder. If the host can't do that, the `.htaccess` in the project root routes requests there and blocks `.env`, but pointing at `public` is safer.

## 3. Configure

Copy `.env.production.example` to `.env` and fill in every value marked CHANGE.

## 4. Install

```
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
```

Run `db:seed` once only, on a new empty database. In production it creates the admin, catalogue and content pages, and no sample customers or orders.

**If you import your local database instead of seeding:** skip `db:seed`, still run `migrate`, then change the admin password and delete the sample customers straight away. Locally, the admin and all 6 sample customers use the password `password`.

Make `storage` and `bootstrap/cache` writable by the web server.

## 5. Add the cron job

One entry, every minute. It runs photo processing, 360° extraction and cleanup:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## 6. Before taking orders

In the admin console:

- [ ] Sign in and confirm the admin password is strong (My account)
- [ ] **Payments → Methods:** only Cash on Delivery starts active
  - To use bKash or Nagad, enter your real merchant number, then activate
  - **Do not activate Card, Apple/Google Pay or Klarna until a payment gateway is connected.** They do not charge anyone
- [ ] **Settings:** replace the store name, email, phone, address and social links
- [ ] Review the Terms, Privacy and Returns pages

Then test on the live site:

- [ ] Place a real Cash on Delivery order and confirm the email arrives
- [ ] Use "Forgot password" and confirm the email arrives
- [ ] Upload a product photo and confirm it is processed within two minutes
- [ ] Confirm `https://your-domain/.env` does not show the file

## Updating later

```
php artisan down
# upload the changed files, including public/build
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan up
```
