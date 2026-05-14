# API localization (en / ar / ur)

This backend resolves the active locale on **every web and API request** via `App\Http\Middleware\SetRequestLocale` (aliases: `set.request.locale`, `locale`). Spatie **laravel-translatable** reads `app()->getLocale()` when returning translated attributes.

## Supported locales

Configured in `config/locales.php`:

- `supported`: `en`, `ar`, `ur`
- `fallback`: `APP_FALLBACK_LOCALE` (default `en`)
- `rtl`: `ar`, `ur`

Add a new language by appending to `supported`, updating `rtl` if needed, and storing translations in JSON columns for each locale key.

## Resolution order (first match wins)

1. Query `lang` (e.g. `?lang=ur`) — recommended for explicit client control  
2. Query `locale` (legacy / alternate)  
3. Header `X-Locale`  
4. Header `Accept-Language` (first tag, e.g. `ar-SA` → `ar`)  
5. Authenticated user `preferred_locale`  
6. Session `app_locale` / `admin_locale`  
7. `locales.fallback`

## Example requests

### English (default)

```http
GET /api/localized-articles/welcome-guide
Accept: application/json
```

### Arabic via `Accept-Language`

```http
GET /api/localized-articles/welcome-guide
Accept: application/json
Accept-Language: ar
```

### Urdu via `lang`

```http
GET /api/localized-articles/welcome-guide?lang=ur
Accept: application/json
```

### All stored translations (CMS / admin tooling)

```http
GET /api/localized-articles/welcome-guide?include_translations=1&lang=en
Accept: application/json
```

`lang` overrides `Accept-Language` when both are sent.

## Example JSON responses

Responses use `App\Helpers\ApiResponse` (`success`, `message`, `data`).

### English — `GET /api/localized-articles/welcome-guide`

```json
{
  "success": true,
  "message": "Article retrieved.",
  "data": {
    "id": 1,
    "slug": "welcome-guide",
    "locale": "en",
    "title": "Welcome to Tandil",
    "description": "This is sample multilingual content. The API returns title and description in the active locale.",
    "used_fallback_for": []
  }
}
```

### Arabic — same URL with `Accept-Language: ar`

```json
{
  "success": true,
  "message": "Article retrieved.",
  "data": {
    "id": 1,
    "slug": "welcome-guide",
    "locale": "ar",
    "title": "مرحبًا بك في تنديل",
    "description": "هذا مثال على محتوى متعدد اللغات. تعيد واجهة API العنوان والوصف حسب اللغة النشطة.",
    "used_fallback_for": []
  }
}
```

### Urdu — `GET /api/localized-articles/welcome-guide?lang=ur`

```json
{
  "success": true,
  "message": "Article retrieved.",
  "data": {
    "id": 1,
    "slug": "welcome-guide",
    "locale": "ur",
    "title": "تندیل میں خوش آمدید",
    "description": "یہ نمونہ کثیر لسانی مواد ہے۔ API فعال زبان کے مطابق عنوان اور تفصیل واپس کرتی ہے۔",
    "used_fallback_for": []
  }
}
```

### Fallback — missing `ar` copy (fields fall back to `en`)

`data.used_fallback_for` lists which attributes used the fallback locale.

## Spatie translatable

- Package: `spatie/laravel-translatable`  
- Fallback for missing keys is wired in `App\Providers\AppServiceProvider` to `config('locales.fallback')`.  
- Example model: `App\Models\LocalizedArticle` (`HasTranslations`, JSON `title` / `description`).  
- Example migration: `database/migrations/2026_05_14_120000_create_localized_articles_table.php`  
- Demo seeder: `php artisan db:seed --class=LocalizedArticleSeeder`

## New translatable models

1. Add JSON columns (or one JSON column per attribute) in a migration.  
2. Use `HasTranslations` and declare `public array $translatable = ['field', ...]`.  
3. Read `$model->field` in controllers after locale middleware has run — values follow the current locale with Spatie fallback rules.
