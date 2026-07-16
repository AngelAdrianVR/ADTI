# 11 — Frontend Architecture

---

## Business Purpose

Describes the Vue 3 + Inertia.js frontend architecture, component organization, UI patterns, and conventions used across all modules.

---

## Tech Stack (Frontend)

| Technology | Version | Purpose |
|------------|---------|---------|
| Vue 3 | ^3.3 | Component framework (Composition API, `<script setup>`) |
| Inertia.js | ^1.0 (Laravel adapter) | Server-driven SPA — no manual API calls |
| Vite | ^5.0 | Build tool with Laravel plugin |
| Tailwind CSS | ^3.4 | Utility-first CSS |
| Element Plus | ^2.8 | UI component library (tables, forms, modals, notifications) |
| date-fns | ^3.6 | Date formatting and manipulation |
| jsbarcode | ^3.11 | Client-side barcode generation |
| mitt | ^3.0 | Lightweight event bus for cross-component communication |
| axios | ^1.6 | HTTP client (used by Inertia internally) |
| Ziggy | ^2.0 | Laravel route names available in JS (`route('users.index')`) |

---

## Directory Structure

```
resources/js/
├── app.js                    # Inertia + Vue + Ziggy initialization
├── bootstrap.js              # Axios config, CSRF token
├── eventBus.js               # mitt instance (import { emitter } from '@/eventBus')
├── ziggy.js                  # Ziggy route generation
├── Pages/                    # Inertia page components (one folder per feature)
│   ├── Auth/                 # Login, Register, ForgotPassword, etc.
│   ├── Category/             # Category management
│   ├── Product/              # Product management
│   ├── User/                 # User index, MyPayrolls
│   ├── Payroll/              # Payroll index, show, templates
│   ├── Project/              # Project index, create, show
│   ├── Setting/              # Settings hub
│   ├── Holiday/              # Holiday management
│   ├── Kiosk/                # Kiosk terminal
│   ├── VacationRequest/      # Vacation approval
│   ├── LandingPage/          # Public catalog
│   ├── Profile/              # User profile (Jetstream)
│   ├── Dashboard.vue         # Main dashboard
│   ├── Welcome.vue           # Public landing
│   └── PrivacyPolicy.vue, TermsOfService.vue
├── Components/               # Shared Vue components
│   ├── MyComponents/         # Custom project-specific components
│   └── *.vue                 # Jetstream default components (Modal, DialogModal, etc.)
├── Composables/
│   └── payroll/              # Reusable payroll logic
└── Layouts/                  # App layout (sidebar nav, header, main content area)
```

---

## How Inertia Works in This Project

Inertia.js replaces the traditional Laravel Blade + AJAX flow:

1. **Server-side**: Controllers return `Inertia::render('PageName', [props])` instead of `view()`.
2. **Client-side**: Inertia's Vue adapter receives props and renders the matching page component from `Pages/`.
3. **Navigation**: Use `<Link href={route('users.index')}>` instead of `<a>`. Inertia intercepts clicks, makes XHR requests, and swaps components without full page reloads.
4. **Forms**: Use `useForm()` from `@inertiajs/vue3` for form submissions with automatic CSRF, validation errors, and loading states.

**Example controller pattern:**
```php
return Inertia::render('Product/Index', compact('products'));
```

This expects `resources/js/Pages/Product/Index.vue` to exist and receive `products` as a prop.

---

## Component Patterns

### Page Components (`Pages/`)
- Each is a full Inertia page.
- Receives data as props from the controller.
- Uses Element Plus components for UI (`el-table`, `el-form`, `el-dialog`, `el-button`, `el-notification`).
- Composition API with `<script setup>`.

### Shared Components (`Components/`)
- Reusable UI elements (mostly Jetstream defaults).
- `MyComponents/` contains project-specific reusable widgets (e.g., attendance cards, custom tables).

### Composables (`Composables/`)
- `payroll/` contains shared state/logic for payroll-related pages.
- Follows Vue 3 composable pattern: `export function usePayroll() { ... }`.

### Event Bus (`eventBus.js`)
- `mitt` instance for cross-component events.
- Used for notifications, global UI updates (e.g., refreshing sidebar data).

---

## Element Plus Integration

Element Plus is globally registered and used extensively:
- **Tables**: `el-table` with `el-table-column` for data grids (payroll, users, products).
- **Forms**: `el-form`, `el-form-item`, `el-input`, `el-select`, `el-date-picker`.
- **Modals**: `el-dialog` for create/edit forms.
- **Notifications**: `ElNotification` for success/error messages.
- **Icons**: Element Plus icon components.
- **Loading**: `v-loading` directive on containers.

---

## Layout System

The authenticated area uses a persistent layout (defined in `Layouts/`):
- Sidebar navigation with links to all modules.
- Top header with user menu, notifications.
- Main content area where Inertia pages render.

The layout is likely set via Inertia's `layout` property on page components or globally in `app.js`.

---

## Build & Dev Workflow

```bash
npm run dev      # Vite dev server with HMR
npm run build    # Production build → public/build/
```

Vite config (`vite.config.js`) uses `laravel-vite-plugin` which:
- Auto-detects `resources/js/app.js` and `resources/css/app.css` as entry points.
- Handles HMR with Valet/Docker/Artisan serve.
- In production, generates hashed filenames in `public/build/`.

---

## Known Patterns & Conventions

1. **Ziggy for routes**: Never hardcode URLs. Use `route('users.index')` in JS, which Ziggy generates from Laravel's named routes.
2. **Inertia forms**: Use `useForm()` for all submissions. It handles `form.post()`, `form.put()`, `form.delete()`, `form.errors`, `form.processing`, and `form.reset()`.
3. **CSRF**: Axios is configured in `bootstrap.js` to include the CSRF token from the meta tag automatically.
4. **Element Plus locale**: Likely configured for Spanish (`es` locale).
5. **Profile photos**: User avatars use the `profile_photo_url` accessor from Jetstream's `HasProfilePhoto` trait.

---

## Known Limitations & Technical Debt

1. **No TypeScript**: The entire frontend is plain JavaScript. Adding TypeScript would require significant refactoring of all `.vue` and `.js` files.
2. **No Pinia/Vuex**: All state lives in component `ref()`/`reactive()` or comes from Inertia props. Cross-component state uses the `mitt` event bus. For complex state, this can become unwieldy.
3. **Element Plus is heavy**: The full library is imported. Tree-shaking may not be optimal. Consider using `unplugin-element-plus` for on-demand imports.
4. **Jetstream components are modified**: The default Jetstream Vue components (Modal, DialogModal, etc.) may have been customized. Upgrading Jetstream could cause merge conflicts.
5. **No automated frontend tests**: No Jest, Vitest, or Cypress setup. All testing is manual.
6. **No i18n library**: Translations are likely handled server-side (Laravel `__()` helper) or hardcoded in Vue components. There's no `vue-i18n`.
