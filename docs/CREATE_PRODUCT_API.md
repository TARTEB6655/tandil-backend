# Create Product API – With Image Upload (Single Request)

The admin app creates a product in **one request**. When the user uploads images from the device (gallery/camera), the app sends **product fields + image files** together to the **same create-product endpoint** using **multipart/form-data**. No separate upload API is used.

---

## 1. Endpoint (same as current create product)

| Item     | Value |
|----------|--------|
| **Method** | POST |
| **URL**    | `{{baseUrl}}/api/admin/products` |
| **Auth**   | Authorization: Bearer {{admin_token}} |

The backend accepts **both**:

- **JSON** body: product fields + optional `image_urls` (array of URLs), **or**
- **Multipart/form-data** body: product fields as form fields + image **files** in `images[]` (and optionally `image_urls` for pasted URLs or `image_url[]` repeated).

---

## 2. Option A – JSON (for URL-only images)

When the user only adds **image URLs** (no device upload), the app sends JSON:

**Content-Type:** `application/json`

**Body (example):**

```json
{
  "name": "Test Product",
  "description": "Description here",
  "price": 99.99,
  "stock": 10,
  "status": "active",
  "category_id": 2,
  "weight_unit": "kg",
  "sku": "SKU-UNIQUE-002",
  "handle": "test-product-unique",
  "image_urls": ["https://example.com/image1.jpg", "https://example.com/image2.jpg"]
}
```

`image_urls` is optional (array of strings).  
Backend stores these URLs as product images and returns the same response as below.

---

## 3. Option B – Multipart/form-data (product + image files)

When the user **uploads images from the device**, the app sends **multipart/form-data** to the **same** `POST /api/admin/products` URL with:

- All product fields as **form fields** (string values).
- Device-selected images as **file fields** `images[]`.
- Optional **image URLs** (pasted by user): one form field `image_urls` as a **JSON string**, or repeated form fields `image_url[]` (one URL per field). Backend merges these with file uploads.

### 3.1 Form fields (all strings)

| Field          | Type   | Required | Description |
|----------------|--------|----------|-------------|
| name           | string | Yes      | Product name |
| description    | string | No       | Product description |
| price          | string | Yes      | e.g. `"99.99"` |
| stock          | string | No       | e.g. `"10"` |
| status         | string | No       | e.g. `"active"`, `"draft"` |
| category_id    | string | No       | e.g. `"2"` or empty |
| weight_unit    | string | No       | e.g. `"kg"` |
| sku            | string | No       | SKU (optional; unique if provided) |
| handle         | string | No       | URL handle/slug (optional; auto-generated from name if empty) |

### 3.2 Image files (from device)

| Field       | Type | Required | Description |
|-------------|------|----------|-------------|
| **images[]** | File | No*      | Multiple image files (JPEG, PNG, WebP). One product can have multiple images. |
| **image**    | File | No*      | Single image file (alternative to `images[]`). |

\*Required when the user chooses “Upload from device” and selects at least one image.

**Backend expects:**

- **Multiple files:** field name **`images[]`** (Laravel-style). Send one or more files with the same key `images[]`.
- **Single file:** field name **`image`**.

Accepted: jpg, jpeg, png, webp. Max 5MB per file.

### 3.3 Optional: pasted image URLs (in multipart)

When the user also adds **image URLs** in text fields, the app can send them in multipart in one of these ways (backend supports both):

- **Option 1:** One form field `image_urls` = JSON string, e.g.  
  `image_urls` = `["https://example.com/a.jpg", "https://example.com/b.jpg"]`
- **Option 2:** Repeated form fields `image_url[]` = one URL per field.

Backend merges these URLs with any images uploaded as files and stores all as product images (files first, then URLs by order).

---

## 4. Backend behaviour (implemented)

1. **Same URL:** `POST /api/admin/products`.
2. **Content-Type detection:**
   - If `Content-Type: application/json` → parse JSON, use `image_urls` (array of URLs) if present. No file handling.
   - If `Content-Type: multipart/form-data` → read product fields from form data and image files from `images[]` or `image`. Optionally read `image_urls` (JSON string) or `image_url[]` (repeated) and **merge** with file uploads.
3. **Storage:** Uploaded files are saved to `storage/app/public/products`; public URLs are generated. External `image_urls` are stored as-is (path = URL). All are linked to the product via `product_images`.
4. **Response:** Same as current create-product response:

```json
{
  "status": true,
  "message": "Product created successfully.",
  "data": {
    "id": 2,
    "name": "Test Product",
    "description": "Description here",
    "sku": "SKU-UNIQUE-002",
    "price": 99.99,
    "stock": 10,
    "status": "active",
    "weight_unit": "kg",
    "handle": "test-product-unique",
    "category_id": 2,
    "image": "products/xyz.jpg",
    "image_url": "https://your-domain.com/storage/products/xyz.jpg",
    "images": [ { "id": 1, "image_path": "...", "image_url": "...", "sort_order": 0, "is_primary": true } ],
    "primaryImage": { ... },
    "created_at": "...",
    "updated_at": "..."
  }
}
```

List/detail APIs return the same image data (`image`, `image_url`, `images`, `primaryImage`) so the app can show product images.

---

## 5. Summary for backend developer

| Item | Details |
|------|---------|
| **Endpoint** | Same as now: `POST /api/admin/products` |
| **No separate upload API** | All product data + images go in this one request when user uploads from device. |
| **Two ways to send** | (1) **JSON:** product fields + optional `image_urls` (array of URLs). (2) **Multipart:** product fields as form fields + image files in **`images[]`** or **`image`** + optional `image_urls` (JSON string) or **`image_url[]`** (repeated). |
| **Auth** | Admin Bearer token for both. |
| **Response** | Same success/error JSON as current create product; includes `image`, `image_url`, `images`, `primaryImage` in `data`. |

---

## 6. What the app sends

**User only adds image URLs (no device upload):**  
`POST /api/admin/products` with **JSON** body (product fields + `image_urls` array). Same as current.

**User uploads one or more images from device:**  
`POST /api/admin/products` with **multipart/form-data**: product fields as form fields + optional `image_urls` (JSON string array) or `image_url[]` (repeated) + **`images[]`** with one file per field (e.g. product-image-0.jpg, product-image-1.jpg).  
Backend accepts **`images[]`** for multiple image files and **`image`** for a single file.
