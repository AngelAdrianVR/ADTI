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
| `GET /show-category/{category_id}` | Show category detail (subcategories with images) |
| `GET /show-subcategory/{subcategory_id}` | Show subcategory detail (products + count) |
| `GET /show-product/{product_id}` | Show product detail |
| `GET /products-search` | Search products by query string |

All landing page routes use inline closures in `web.php` (not dedicated controllers). They eager-load the `media` relationship for display.

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
        └── fetches: Category::with('media', 'subcategories.media')->find(id)
        └── user clicks subcategory → /show-subcategory/{id}
              └── fetches: Subcategory::with('media', 'products', 'category.subcategories.media', 'category.media')->find(id)
              └── user clicks product → /show-product/{id}
                    └── fetches: Product::with('media', 'subcategory.category.subcategories')->find(id)
```

---

## Dependencies

- **Catalog module** — All data comes from categories/subcategories/products
- **Users module** — Kiosk uses attendance logic from `UserController` and `User` model
- **Payroll module** — Kiosk attendance records go into `payroll_user`

---

## Known Limitations & Technical Debt

1. **Landing routes are inline closures**: All landing page logic is in `web.php` as closures rather than a dedicated controller. This works but makes it impossible to cache routes (`php artisan route:cache` requires controller-based routes).
2. **No pagination on landing pages**: All products in a subcategory are loaded at once. For large catalogs, this could cause performance issues.
3. **Search is limited**: Only searches `part_number_supplier`, not product name or description. Uses `LIKE '%query%'` which doesn't scale well.
4. **Kiosk has no dedicated backend**: The kiosk reuses user attendance endpoints. There's no kiosk-specific session management, PIN-based auth, or hardware integration.
5. **No landing page customization**: The Welcome page hardcodes all categories. There's no CMS or featured products mechanism.
6. **Media loading**: All media is eager-loaded on listing pages. For categories with many high-resolution images, page load could be slow.
