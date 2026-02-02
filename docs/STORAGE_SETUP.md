# Storage setup (product images)

Product images are stored under `storage/app/public/products/`. For URLs like:

`https://your-domain.com/storage/products/xxx.jpg`

to work, do **one** of the following.

---

## Option 1: Create the storage link (recommended)

On the server, run:

```bash
php artisan storage:link
```

This creates a symbolic link: `public/storage` → `storage/app/public`.  
The web server then serves files from `public/storage/` (e.g. `/storage/products/xxx.jpg`).

**Cloudways / shared hosting:** Run this once after deploy (e.g. in Deploy or SSH).

---

## Option 2: Fallback (no symlink)

If you cannot create a symlink (e.g. host restriction), the app will still serve storage files via a route. Requests to `/storage/{path}` are handled by Laravel and the file is streamed from `storage/app/public/`. No extra setup needed.

---

## Checklist

1. **APP_URL** in `.env` must match your site (e.g. `https://phpstack-1180784-6050385.cloudwaysapps.com`). Image URLs are built from this.
2. Run `php artisan storage:link` on the server so `/storage/...` works.
3. Ensure `storage/app/public` (and `storage/app/public/products`) is writable by the web server so uploads succeed.
