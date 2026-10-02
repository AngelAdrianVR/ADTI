# 10 — Landing Page & Kiosk Module

---

## Business Purpose

Two public-facing (or semi-public) interfaces:

1. **Landing Page** — A product catalog for external visitors. Browse categories → subcategories → product detail. Search products. No authentication required.
2. **Kiosk** — An attendance terminal interface for employees to check in/out. Uses the same attendance logic as the Users module but with a simplified, kiosk-optimized UI.

---

## Key Files

| File | Role |
|------|------|
| `app/Http/Controllers/KioskController.php` | Minimal controller — just renders the kiosk view |
| `resources/js/Pages/Welcome.vue` | Landing page: category grid |
| `resources/js/Pages/LandingPage/ShowCategory.vue` | Category detail with subcategory listing |
| `resources/js/Pages/LandingPage/ShowSubcategory.vue` | Subcategory detail with product listing |
| `resources/js/Pages/LandingPage/ShowProduct.vue` | Product detail view |
| `resources/js/Pages/Kiosk/Index.vue` | Kiosk terminal interface |

---

## Landing Page Routes (Public)

| Route | Action |
|-------|--------|
| `GET /` | Landing page — shows all categories with subcategories and media |
| `GET /show-category/{category_id}` | Render category detail shell (only `category_id`) |
| `GET /show-subcategory/{subcategory_id}` | Render subcategory detail shell (`subcategory_id` + `total_products`) |
| `GET /show-product/{product_id}` | Render product detail shell (only `product_id`) |
| `GET /products-search` | Search products by query string |
| `GET /categories-fetch-show/{category}` | `CategoryController@fetchShowData` — JSON: category + `media` + `subcategories.media` |
| `GET /subcategories-fetch-show/{subcategory}` | `SubcategoryController@fetchShowData` — JSON: subcategory + `media` + `category.subcategories.media` + `category.media` |
| `GET /products-fetch-show/{product}` | `ProductController@fetchShowData` — JSON: product + `media` + `subcategory.category.subcategories` |
| `GET /products-fetch-subcategory-products/{subcategory_id}` | `ProductController@fetchSubcategoryProducts` — JSON: products of a subcategory |

The `/show-*` routes use inline closures in `web.php` and are intentionally **thin**: they only pass the entity id (plus the cheap `count()` of products for subcategories) so the Inertia response returns immediately. The heavy eager-load of `media`/relations happens in the `*-fetch-show` JSON endpoints, which the Vue pages request with `axios` on `mounted()`. This keeps navigation instantaneous and shows `Loading.vue` while the data arrives. All `*-fetch-show` routes only use the `web` middleware (public).

### Search
`GET /products-search` accepts a `?query=` parameter and performs a `LIKE` search on `products.part_number_supplier`. Results are returned as Inertia-rendered page.

---

## Kiosk Route

| Route | Action |
|-------|--------|
| `GET /kiosks` | `KioskController@index` — Renders kiosk terminal |

The kiosk is behind `auth:sanctum` middleware. It uses the same attendance endpoints from the Users module (`users-get-next-attendance`, `users-set-pause`, `users-set-attendance`) via AJAX calls from the Vue component. The kiosk UI is designed for a touchscreen terminal — large buttons for check-in/check-out.

---

## Data Flow (Landing Page)

```
Welcome.vue
  └── fetches: Category::with('subcategories', 'media')->get()
  └── user clicks category → /show-category/{id}
        └── navigates with { category_id } only (instant render + <Loading />)
        └── ShowCategory.vue → GET /categories-fetch-show/{id}
              └── Category::with('media', 'subcategories.media')->find(id)
        └── user clicks subcategory → /show-subcategory/{id}
              └── navigates with { subcategory_id, total_products }
              └── ShowSubcategory.vue → GET /subcategories-fetch-show/{id}
                    └── Subcategory::with('media', 'category.subcategories.media', 'category.media')->find(id)
              └── ShowSubcategory.vue → GET /products-fetch-subcategory-products/{id} (only if total_products > 0)
              └── user clicks product → /show-product/{id}
                    └── navigates with { product_id } only (instant render + <Loading />)
                    └── ShowProduct.vue → GET /products-fetch-show/{id}
                          └── Product::with('media', 'subcategory.category.subcategories')->find(id)
```

---

## Dependencies

- **Catalog module** — All data comes from categories/subcategories/products
- **Users module** — Kiosk uses attendance logic from `UserController` and `User` model
- **Payroll module** — Kiosk attendance records go into `payroll_user`

---

## Known Limitations & Technical Debt

1. **Landing "shell" routes are inline closures**: The `/show-*` routes remain closures in `web.php` (the `*-fetch-show` JSON routes do use controllers). `php artisan route:cache` cannot serialize closure routes.
2. **No pagination on landing pages**: All products in a subcategory are loaded at once. For large catalogs, this could cause performance issues.
3. **Search is limited**: Only searches `part_number_supplier`, not product name or description. Uses `LIKE '%query%'` which doesn't scale well.
4. **Kiosk has no dedicated backend**: The kiosk reuses user attendance endpoints. There's no kiosk-specific session management, PIN-based auth, or hardware integration.
5. **No landing page customization**: The Welcome page hardcodes all categories. There's no CMS or featured products mechanism.
6. **Media loading is deferred but still heavy**: Media is eager-loaded inside the `*-fetch-show` endpoints, so it no longer blocks navigation, but a category with many high-resolution images will still make the detail view wait once mounted.
