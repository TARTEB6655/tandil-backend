# Storage setup (product images)

Product images are stored under `storage/app/public/products/`. The **Product GET API** returns `image_url` and `data.images[].image_url` with a clean, professional path (no "storage" in the URL):

**`https://your-domain.com/media/products/xxx.jpg`**

Opening that URL in a browser or in the app should show the image. If you get **404**, follow the steps below.

---

## How it works

- **API response:** `data.image_url` and each image’s URL use `/media/products/...` (clean public path; Laravel serves the file; no symlink needed).
- **Route:** `GET /media/{path}` serves the file from `storage/app/public/` if it exists.
- **Old URLs:** Requests to `/storage/...` or `/app-storage/...` are redirected (301) to `/media/...`.

---

## Fix 404 on Cloudways / production

1. **Deploy the latest code** (config and routes that use `/app-storage/`).

2. **Set in `.env` on the server** (no trailing slash):
   ```env
   APP_URL=https://phpstack-1180784-6050385.cloudwaysapps.com
   ```
   Optional override for media base URL only:
   ```env
   STORAGE_PUBLIC_URL=https://phpstack-1180784-6050385.cloudwaysapps.com/media
   ```

3. **Clear config cache** so the new URL is used:
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

4. **Check the file exists** on the server:
   ```bash
   ls storage/app/public/products/
   ```
   The filename in the API response (e.g. `rza6uKuXzt3tOxeM1NQAr2NOlm3A6CEifP6WG5yR.jpg`) must exist there. If not, re-upload the product image from the React Native app.

5. **Test the URL** — from the Product GET API response, copy `data.image_url` and open it in the browser. It must look like:
   `https://phpstack-1180784-6050385.cloudwaysapps.com/media/products/xxxxx.jpg`

---

## Checklist

- **APP_URL** (or **STORAGE_PUBLIC_URL**) in `.env` matches your site (https, no trailing slash).
- After changing `.env`, run `php artisan config:clear` then `php artisan config:cache`.
- `storage/app/public` and `storage/app/public/products` exist and are writable so uploads from the app succeed.
- No need to run `php artisan storage:link` — the app serves files via the `/media/` route.
