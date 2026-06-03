# Variable Product API — Mobile / App Integration Guide

This document explains how to integrate **admin product APIs** for **variable products** (option groups + options + option images).

**Base URL:** `{{base_url}}/api/admin`  
**Auth:** `Authorization: Bearer {access_token}`  
**Header:** `Accept: application/json`

---

## Quick concepts

| Term | Meaning |
|------|---------|
| **Option group** | One choice axis, e.g. `"Color"`, `"Cutting"` |
| **Option** | One selectable value inside a group, e.g. `"Red"`, `"Arabic cut (8 pieces)"` |
| **`id`** | Real database ID (from GET response). **Always use this on update.** |
| **`temp_key`** | String used only to **link a file upload to an option** in multipart requests |
| **`option_groups_json`** | JSON **string** (array of groups) sent as a form field or in JSON body |
| **`option_images[...]`** | Multipart **file** field for one option’s thumbnail |

### `temp_key` — where it comes from

| When | Who sets `temp_key` | Example |
|------|---------------------|---------|
| **Create** (new options, no DB id yet) | **Your app** — any unique string per new option | `opt_color_red` |
| **GET** (product detail) | **Server** — always `opt_{optionId}` | Option id `434` → `opt_434` |

`temp_key` is **not stored** in the database. On GET it is computed as `'opt_' + option.id`.

**On update:** you do **not** need a custom `temp_key`. Use:

```text
option_images[opt_{optionId}]
```

Example: option `id = 434` → file field `option_images[opt_434]` (also works: `option_images[434]`).

---

## How option data is updated (important)

There is **no separate API** like:

```text
PUT /api/admin/options/{option_id}   ❌ does not exist
```

Options are always updated **through the product update API**, same as product `id` in the URL — but **option `id` goes inside JSON**, not in the URL.

| ID type | Where to send | Example |
|---------|---------------|---------|
| **Product id** | URL path | `POST /api/admin/products/92` |
| **Option group id** | Inside `option_groups_json` → `"id": 156` | From GET `data.option_groups[0].id` |
| **Option id** | Inside `option_groups_json` → `"options": [{ "id": 434, ... }]` | From GET `data.option_groups[0].options[0].id` |

### Visual: one request, nested IDs

```text
POST /api/admin/products/92          ← product_id = 92 (URL only)

Body (multipart or JSON):
  product_type = variable
  option_groups_json = '
    [
      {
        "id": 156,                  ← group id (GET se)
        "name": "Color",
        "options": [
          {
            "id": 434,              ← option id (GET se) — YAHAN bhejni hai
            "label": "Red",
            "price_modifier": 10
          },
          {
            "id": 435,
            "label": "Blue",
            "price_modifier": 0
          }
        ]
      }
    ]
  '
  option_images[opt_434] = <file>    ← optional; only if option 434 ki image change ho
```

### Flow for the app developer

1. **GET** `/api/admin/products/92` → read `option_groups[].id` and `options[].id`.
2. User edits option (e.g. Red price 0 → 10).
3. **POST** `/api/admin/products/92` with `option_groups_json` containing that option object **with `"id": 434`** and new `price_modifier`.
4. Server matches `id` 434 and updates that row in `product_options` table.

You **do not** add `option_id` as a top-level form field (unless you only upload an image — then URL still product id, file key `option_images[opt_434]`, and minimal JSON with `"id": 434`).

### Update one option vs all options

| Case | What to put in `option_groups_json` |
|------|-------------------------------------|
| Change **one** option’s label/price | Send the **group** that contains it, with **all options in that group** you want to keep (each with `id`). Easiest: copy whole group from GET, change one field. |
| Change **only** option image | Same group + option with `"id": 434` + file `option_images[opt_434]`. |
| Change **product name only** | Omit `option_groups_json` entirely — no option `id` needed. |

If you send a group **without** option `id`s, the server may treat them as **new** options and remove old ones. **Always include `id` from GET for existing options.**

---

## Endpoints

| Action | Method | URL |
|--------|--------|-----|
| List products | GET | `/products` |
| Product detail | GET | `/products/{product_id}` |
| Create product | POST | `/products` |
| Update product (JSON only) | PUT | `/products/{product_id}` |
| Update product (**with files**) | **POST** | `/products/{product_id}` |

Use **POST** for update when uploading `main_image`, `images[]`, or `option_images[...]`.  
PUT multipart is supported but POST is more reliable on mobile stacks.

---

## 1. Get product first (required before update)

```http
GET /api/admin/products/{product_id}
Authorization: Bearer {token}
```

**Save from `data`:**

- `data.option_groups[].id` → **group_id**
- `data.option_groups[].options[].id` → **option_id**
- `data.option_groups[].options[].image_url` → show in UI
- File upload key for that option: **`opt_{option_id}`**

**Example option in response:**

```json
{
  "id": 434,
  "temp_key": "opt_434",
  "label": "Arabic cut (8 pieces)",
  "subtitle": "Free",
  "price_modifier": 0,
  "image_path": "product-options/abc.jpg",
  "image_url": "https://your-domain.com/media/product-options/abc.jpg",
  "sort_order": 0
}
```

---

## 2. Create variable product

```http
POST /api/admin/products
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

### Required text fields (minimum)

| Field | Example |
|-------|---------|
| `name` | `T-Shirt` |
| `price` | `99` |
| `stock` | `50` |
| `status` | `active` |
| `product_type` | `variable` |
| `option_groups_json` | See below |

Optional: `description`, `category_id`, `sku`, `handle`, `main_image` (file), etc.

### `option_groups_json` — one group, four options (example: Color)

Send as **one text field** whose value is a JSON string:

```json
[
  {
    "name": "Color",
    "subtitle": "Required - Select one",
    "input_type": "single",
    "is_required": true,
    "sort_order": 0,
    "options": [
      {
        "temp_key": "opt_color_red",
        "label": "Red",
        "subtitle": "Free",
        "price_modifier": 0,
        "sort_order": 0
      },
      {
        "temp_key": "opt_color_blue",
        "label": "Blue",
        "subtitle": "Free",
        "price_modifier": 0,
        "sort_order": 1
      },
      {
        "temp_key": "opt_color_green",
        "label": "Green",
        "subtitle": "+5 SAR",
        "price_modifier": 5,
        "sort_order": 2
      },
      {
        "temp_key": "opt_color_black",
        "label": "Black",
        "subtitle": "+10 SAR",
        "price_modifier": 10,
        "sort_order": 3
      }
    ]
  }
]
```

| Field | Notes |
|-------|--------|
| `input_type` | `single` = pick one; `multi` = multiple allowed |
| `is_required` | `true` / `false` |
| `price_modifier` | Added to base `price` when this option is selected |
| `temp_key` | **Required for create + file** — must match file field name below |

### Option images on create (optional)

One multipart file per option; **bracket name = `temp_key`:**

| Form field (type: file) | Option |
|-------------------------|--------|
| `option_images[opt_color_red]` | Red |
| `option_images[opt_color_blue]` | Blue |
| `option_images[opt_color_green]` | Green |
| `option_images[opt_color_black]` | Black |

### Product-level images (not options)

| Field | Purpose |
|-------|---------|
| `main_image` | Product main photo |
| `images[]` | Extra gallery images |

### Response (201)

```json
{
  "status": true,
  "message": "Product created successfully.",
  "data": {
    "id": 92,
    "product_type": "variable",
    "option_groups": [ ... ]
  }
}
```

**After create:** store each `options[].id` in your app. Future updates use **`id`**, not your old `opt_color_red`.

---

## 3. Update product

```http
POST /api/admin/products/{product_id}
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

Or for **text-only** changes:

```http
PUT /api/admin/products/{product_id}
Content-Type: application/json
```

### Partial update rule

Only send fields you want to change.

| Goal | Send |
|------|------|
| Change name/price only | `name`, `price` — **do not** send `option_groups_json` |
| Change option labels/prices | `product_type=variable` + `option_groups_json` |
| Change one option image | `product_type=variable` + `option_groups_json` (minimal) + `option_images[opt_{id}]` |

Sending `option_groups_json: []` is **ignored** (will not wipe options).

---

## 4. Update scenarios (copy-paste patterns)

### A) Change product name/price only (keep all options & images)

```http
PUT /api/admin/products/92
Content-Type: application/json

{
  "name": "Updated Name",
  "price": 149.99
}
```

No `option_groups_json`, no `option_images`.

---

### B) Update one option image only

**Postman checklist (old image still in response?):**

1. URL must be `POST .../api/admin/products/{product_id}` — **not** `POST .../api/admin/products` without id.
2. Body type **form-data** (not raw JSON).
3. File row key exactly `option_images[opt_417]` if `temp_key` / `id` is `417` (`opt_417`).
4. Do **not** paste old `image_url` into `option_groups_json`.
5. Prefer **POST** over **PUT** for file upload.
6. After deploy of latest backend, `image_path` in response should change to a **new** `product-options/...` filename.

**Step 1:** GET product → option `id = 434`, group `id = 156`.

**Step 2:** POST multipart:

| Field | Value |
|-------|--------|
| `product_type` | `variable` |
| `option_groups_json` | JSON string below |
| `option_images[opt_434]` | **File** (new image) |

```json
[
  {
    "id": 156,
    "name": "Cutting",
    "input_type": "single",
    "is_required": true,
    "options": [
      {
        "id": 434,
        "label": "Arabic cut (8 pieces)",
        "subtitle": "Free",
        "price_modifier": 0
      }
    ]
  }
]
```

**Unique identifiers:** `product_id` (URL) + option `id` + file `option_images[opt_{id}]`.

---

### C) Update option text (label, price_modifier) — no new image

```http
PUT /api/admin/products/92
Content-Type: application/json

{
  "product_type": "variable",
  "option_groups_json": "[{\"id\":156,\"name\":\"Cutting\",\"input_type\":\"single\",\"is_required\":true,\"options\":[{\"id\":434,\"label\":\"New label\",\"subtitle\":\"Free\",\"price_modifier\":10}]}]"
}
```

Or send `option_groups_json` as a form text field in multipart.

---

### D) Add a new option to existing group

GET full group, include **all existing options with their `id`**, plus new option **without `id`**:

```json
[
  {
    "id": 156,
    "name": "Color",
    "input_type": "single",
    "is_required": true,
    "options": [
      { "id": 434, "label": "Red", "price_modifier": 0, "sort_order": 0 },
      { "id": 435, "label": "Blue", "price_modifier": 0, "sort_order": 1 },
      {
        "temp_key": "opt_color_yellow",
        "label": "Yellow",
        "subtitle": "New",
        "price_modifier": 3,
        "sort_order": 2
      }
    ]
  }
]
```

New option image (optional):

```text
option_images[opt_color_yellow]  →  file
```

---

### E) Replace entire option list

Send **complete** `option_groups_json` with every group/option you want to keep (each with `id`).  
Options missing from JSON may be **deleted** by sync.

---

## 5. All update parameters (reference)

| Parameter | Type | When to use |
|-----------|------|-------------|
| `name` | string | Product title |
| `description` | string | |
| `price` | number | Base price |
| `stock` | int | |
| `status` | string | `draft`, `active`, `archived` |
| `is_featured` | 0 / 1 | |
| `category_id` | int | |
| `service_id` | int | Single service link |
| `service_ids` | array | Multiple services |
| `weight_unit` | string | `kg`, `g`, `lb`, `oz` |
| `sku` | string | |
| `handle` | string | |
| `product_type` | string | `variable` when using options |
| `main_image` | file | Product main image |
| `images[]` | file(s) | Product gallery |
| `image_urls` | JSON / array | Image URLs (product level) |
| `option_groups_json` | string | Groups + options structure |
| `option_images[opt_{id}]` | file | **Option** thumbnail update |
| `option_images[{temp_key}]` | file | New option on create/add (match JSON `temp_key`) |

---

## 6. Recommended app flow

```
┌─────────────┐
│ GET product │  → cache group id, option id, image_url
└──────┬──────┘
       │
       ▼
┌──────────────────────┐
│ User edits           │
│ - product fields     │  → PUT/POST without option_groups_json
│ - option fields      │  → POST + option_groups_json (ids included)
│ - option image       │  → POST + option_groups_json + option_images[opt_{id}]
└──────┬───────────────┘
       │
       ▼
┌─────────────┐
│ Use response│  → data.option_groups[].options[].image_url
│ or GET again│
└─────────────┘
```

---

## 7. Common mistakes

| Mistake | Result |
|---------|--------|
| Update with `option_groups_json` but **without** option `id` | May create duplicates or lose images |
| Use create-time `temp_key` (`opt_cut_1`) on update without matching JSON | Image may not attach to correct option |
| File key `option_images[opt_pack_1]` but JSON `temp_key` is `opt_417` | **Keys must match exactly** |
| Call **POST `/products`** (no id) instead of **POST `/products/92`** | Creates new product; does not update option 417 |
| Send old `image_url` in JSON while uploading new file | Avoid; let server set path from file. Use `temp_key` + file only |
| Send empty `option_groups_json` `[]` expecting clear | Ignored (safe) |
| PUT multipart on iOS/Android without POST fallback | Files may not arrive — use **POST** |
| Confuse `main_image` with `option_images` | Wrong image type (product vs option) |
| Partial price update **with** full broken `option_groups_json` | Options/images may reset |

---

## 8. Success checks

After create/update:

- HTTP **200** / **201**
- `data.option_groups[].options[].image_path` not null (if image uploaded)
- `data.option_groups[].options[].image_url` loads in app WebView/Image

---

## 9. Shop (customer) — read only

Customers do not create products. To **display** options:

```http
GET /api/shop/products/{product_id}
```

Same `option_groups` shape (no admin token rules apply per your shop auth).

---

## 10. FAQ

**Q: Is `temp_key` mandatory on update?**  
A: No. Use `option_images[opt_{optionId}]` and include `id` in `option_groups_json`.

**Q: What is unique for option image update?**  
A: `product_id` + option `id` + file field.

**Q: JSON or multipart for `option_groups_json`?**  
A: Always a **string** in form-data (escaped JSON), or a JSON object field if your client sends `application/json` without files.

**Q: Can we update multiple option images in one request?**  
A: Yes. Add multiple fields: `option_images[opt_434]`, `option_images[opt_435]`, etc.

---

## Support

Backend repo: `tandil-backend`  
Implementation: `App\Http\Controllers\Admin\ProductController`  
Tests: `tests/Feature/Api/VariableProductUpdateE2ESmokeTest.php`, `VariableProductCreateOptionDataTest.php`

Postman collection: `postman/tandil_backend.json` → Admin → Products → Create / Update.
