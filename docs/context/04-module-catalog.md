# 04 — Product Catalog Module

---

## Business Purpose

A hierarchical product catalog: Categories → Subcategories (tree) → Products. Supports rich media (images), custom feature key-value pairs, internal part number generation, barcode printing, and Excel import/export. Also provides a public-facing landing page for browsing.

---

## Key Files

| File | Role |
|------|------|
| `app/Http/Controllers/CategoryController.php` | CRUD + subcategory management + media |
| `app/Http/Controllers/SubcategoryController.php` | CRUD + media + Excel template generation |
| `app/Http/Controllers/ProductController.php` | CRUD + media + barcodes + Excel import + part number generation |
| `app/Models/Category.php` | `id, name, key`, hasMany → subcategories, Spatie Media |
| `app/Models/Subcategory.php` | `id, name, key, level, features, category_id, prev_subcategory_id`, hasMany → products, Spatie Media |
| `app/Models/Product.php` | `id, name, part_number (UNIQUE), part_number_supplier, consecutivo, features, features_keys, bread_crumbles, subcategory_id`, Spatie Media |
| `resources/js/Pages/Category/*` | Category management UI |
| `resources/js/Pages/Product/*` | Product management UI (index, create, show with navigation) |

---

## Hierarchy Model

```
Category
  └── Subcategory (level=1, prev_subcategory_id=null)
        └── Subcategory (level=2, prev_subcategory_id=parent id)
              └── Subcategory (level=3, ...)
                    └── Product (subcategory_id = deepest subcategory)
```

**Key:** The `bread_crumbles` JSON on Product stores the full path as `["CatName", "Sub1", "Sub2"]`. This is used for display and part number generation.

---

## Part Number Generation (Critical)

The internal `part_number` is auto-generated as:

```
{CATEGORY_KEY}-{FEATURE1_KEY}-{FEATURE2_KEY}-{CONSECUTIVO:03d}
```

Where:
- `CATEGORY_KEY` is the `key` field of the root Category
- Feature keys come from `features_keys` array on the Product
- `CONSECUTIVO` is a 3-digit sequential number per subcategory (001–999)

This logic lives in `ProductController@store` and `ProductController@storeProductsFromFile`.

### Race Condition Protection (see repo memory)
- A `Subcategory::lockForUpdate()` row lock prevents concurrent consecutivo collisions.
- `part_number` has a UNIQUE database constraint as a safety net.
- Both `store()` and `import()` methods have retry loops (max 5 attempts) catching `QueryException` for unique violations.

---

## Main Endpoints

### Categories
| Route | Type | Action |
|-------|------|--------|
| `GET /categories` | resource index | List all categories |
| `POST /categories` | resource store | Create category |
| `POST /categories/store-with-subcategories` | custom | Create category + nested subcategories in one request |
| `GET /categories/{category}` | resource show | Show category |
| `PUT /categories/{category}` | resource update | Update category |
| `POST /categories/update-with-media/{category}` | custom | Update with Spatie media |
| `POST /categories/update-with-subcategories/{category}` | custom | Update with nested subcategories |
| `DELETE /categories/{category}` | resource destroy | Delete |
| `GET /categories/fetch-subcategories/{category}` | custom | AJAX: get subcategories |
| `GET /categories-get-all` | custom | Get all categories (for dropdowns) |

### Subcategories
| Route | Type | Action |
|-------|------|--------|
| `GET /subcategories` | resource index | List |
| `POST /subcategories` | resource store | Create |
| `PUT /subcategories/{subcategory}` | resource update | Update |
| `POST /subcategories/update-with-media/{subcategory}` | custom | Update with media |
| `DELETE /subcategories/{subcategory}` | resource destroy | Delete |
| `GET /subcategories-download-excel-template/{subcategory}` | custom | Generate Excel template for product import |
| `GET /subcategories-get-products/{subcategory}` | custom | Get products in subcategory |

### Products
| Route | Type | Action |
|-------|------|--------|
| `GET /products` | resource index | List with breadcrumbs |
| `GET /products/create` | resource create | Form |
| `POST /products` | resource store | Create with part number generation |
| `GET /products/{product}` | resource show | Detail |
| `GET /products/{product}/edit` | resource edit | Edit form |
| `PUT /products/{product}` | resource update | Update |
| `POST /products/update-with-media/{product}` | custom | Update with media |
| `DELETE /products/{product}` | resource destroy | Delete |
| `POST /products/massive-delete` | custom | Bulk delete |
| `POST /products/import` | custom | Excel import |
| `POST /products/get-consecutivo/{subcategory_id}` | custom | AJAX: get next consecutivo |
| `DELETE /products/delete-file/{file_id}` | custom | Delete attached file |
| `GET /products/{id}/next` | custom | Navigate to next product |
| `GET /products/{id}/previous` | custom | Navigate to previous product |
| `GET /products-print-barcodes` | custom | Print barcode labels (uses jsbarcode) |
| `GET /products-search` | custom | Search products (public) |
| `GET /products-fetch-subcategory-products/{subcategory_id}` | custom | AJAX: products in subcategory |

---

## Excel Import/Export

### Import (Products)
Route: `POST /products/import`
- Expects `.xlsx` file upload
- Reads rows via PhpSpreadsheet
- Wraps entire import in `DB::transaction()`
- Uses `Subcategory::lockForUpdate()` per row for consecutivo safety
- Validates `part_number_supplier`, `description`, `location`, `line_cost`

### Export Template (Subcategories)
Route: `GET /subcategories-download-excel-template/{subcategory}`
- Generates an empty Excel template with correct columns for the given subcategory
- Used by admins to prepare bulk product uploads

---

## Media Handling

All three models (Category, Subcategory, Product) use Spatie Media Library:
- Images for categories/subcategories (displayed in landing page)
- Product images, attached files (specs, manuals)
- Barcode images generated client-side via jsbarcode

The `update-with-media` routes handle updating the model + uploading files in a single request, avoiding the two-step process of standard resource updates.

---

## Dependencies

- **Auth module** — all management routes require authentication
- **Landing page** — public routes read categories/subcategories/products for display
- **Frontend** — Element Plus tables, file upload components

---

## Known Limitations & Technical Debt

1. **Part number race condition**: Fixed but fragile. Any new code that creates products must follow the same locking pattern (lock `Subcategory`, not `Product`). The UNIQUE constraint on `part_number` is the last line of defense.
2. **No media cleanup on delete**: When products/categories are deleted, Spatie Media records may need manual cleanup depending on configuration.
3. **Breadcrumbs are denormalized**: `bread_crumbles` on Product is a snapshot of the hierarchy at creation time. If a subcategory is renamed or moved, existing products won't reflect the change.
4. **No search index**: Product search (`products-search`) uses simple Eloquent `LIKE` queries. For large catalogs, consider Laravel Scout or full-text search.
5. **Consecutivo limit**: 3 digits (max 999) per subcategory. If a subcategory exceeds 999 products, part numbers will break.
