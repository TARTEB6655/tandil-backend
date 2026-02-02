# PHP GD Extension Setup

The **PHP GD** extension is required for:

- **Image uploads** – Category images, product images, visit photos
- **Tests** – Some feature tests use fake image uploads (e.g. `CategoryImageTest`, `FileUploadTest`, visit photo tests)

Without GD, those tests are skipped and image processing may fail in production.

---

## Windows (XAMPP / PHP from php.net)

### 1. Locate `php.ini`

- **XAMPP:** `C:\xampp\php\php.ini`
- **Laragon:** `C:\laragon\bin\php\php-8.x.x\php.ini`
- **Standalone PHP:** Run `php --ini` in a terminal; it shows the path to the loaded `php.ini`.

### 2. Enable the GD extension

Open `php.ini` in a text editor and find:

```ini
;extension=gd
```

Remove the semicolon to enable it:

```ini
extension=gd
```

On some setups the line may be:

```ini
;extension=php_gd.dll
```

Change to:

```ini
extension=php_gd.dll
```

Save the file.

### 3. Restart and verify

- Restart Apache (XAMPP/Laragon) or your PHP server.
- In a terminal, run:

```bash
php -m | findstr -i gd
```

You should see `gd`. Or run:

```bash
php -r "echo extension_loaded('gd') ? 'GD is loaded' : 'GD is NOT loaded';"
```

---

## Windows (Chocolatey)

If PHP was installed via Chocolatey:

```powershell
choco install php --params "/InstallDir:C:\tools\php"
# GD is often included; if not:
# Edit C:\tools\php\php.ini and enable extension=gd
```

Then verify with `php -m | findstr -i gd`.

---

## Linux (Ubuntu / Debian)

```bash
sudo apt update
sudo apt install php8.2-gd
# Or for your PHP version: php8.1-gd, php8.3-gd, etc.

sudo systemctl restart php8.2-fpm   # if using PHP-FPM
# Or restart Apache: sudo systemctl restart apache2
```

Verify:

```bash
php -m | grep -i gd
```

---

## macOS (Homebrew)

```bash
brew install php
# GD is usually included. If not:
pecl install gd
```

Then enable it in `php.ini` (path shown by `php --ini`):

```ini
extension=gd
```

---

## After enabling GD

Run the full test suite; image-related tests should run instead of being skipped:

```bash
php artisan test
```

You should see the category image and visit photo upload tests execute (no longer skipped for “GD extension is not installed”).
