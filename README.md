# PHP ERP Starter Kit v5

Reusable PHP/MySQL ERP foundation with a stable backend, reusable frontend functions, 10 complete variable-driven UI presets, a single customer-editable design file, stateless idle/absolute authentication, numeric permissions and reusable Mail / SMTP Configuration.

## Core features

- PDO + MySQL/MariaDB JSON APIs
- signed Bearer-token authentication
- fixed Absolute Login Expiry (default 24 hours)
- rolling Idle Expiry (default 60 minutes), with no `user_sessions` table
- per-project browser namespace using `APP_KEY`
- Platform / Plan / Tenant role scopes
- numeric Permission Action Master (1-54; future IDs continue 55+)
- CSV numeric permissions in `menus.available_action_ids` and `role_permissions.action_ids`
- Business, Branch, Role, Action, Sidebar Menu, Permission, Security, Theme and Mail masters
- encrypted SMTP password storage using `APP_ENCRYPTION_KEY`
- Platform Default SMTP + optional branch override/fallback
- Test Email and reusable `send_mail()` helper
- dynamic permission-based sidebar, tab-scoped collapse state, mobile off-canvas and collapsed flyouts
- collapsed submenu positioning that reads theme dimensions and stays below the topbar / inside the viewport
- Global Select
- reusable image/file/video upload
- DataTables engine with custom search, filters, export, loader and compact pagination
- validation + reusable regex rules
- toaster, profile, audit notifications
- light/dark mode
- 10 complete Theme Settings presets (Forest, Ocean, Violet, Ruby, Amber, Teal, Indigo, Rose, Slate, Midnight)
- preset-aware branch/platform inheritance with optional semantic color overrides
- Employee Form/List as a reusable CRUD example

## Final CSS architecture — one customer file

```text
assets/css/
├── core.css          # foundation/reset — do not customize
├── components.css    # reusable components — do not customize
└── theme.css         # CUSTOMER DESIGN — change this file
```

`theme.css` controls the complete visual system, not only colors. It contains semantic variables for typography, font sizes, spacing, density, radius, sidebar/topbar dimensions, submenu size, input/button/table sizes, shadows, motion, status colors and Light/Dark appearance. Theme Settings exposes 10 full presets from this same file; each preset can change color, typography, radius, density, layout dimensions, control sizes and shadows.

`core.css` and `components.css` contain no literal HEX/RGB colors and no customer-specific color-value variable names.

## Theme contract

Reusable code uses semantic names such as:

```text
--primary
--primary-soft
--success
--danger
--surface
--canvas
--text-primary
--text-muted
--border
--font-primary
--card-radius
--input-radius
--sidebar-expanded-width
--topbar-height
--submenu-width
--table-row-height
```

JavaScript controls behavior only. It does not own customer colors, fonts, radius or shadows.

## Security model

```text
Login
├── Absolute Login Expiry: default 24 hours (fixed)
└── Idle Expiry: default 60 minutes (rolling)
```

Real user interaction renews only the idle token window. Background API requests do not. The database stores only configuration values in `app_settings`; the token and per-login expiry timestamps are not stored in a session table.

## Mail configuration

PHPMailer is declared in `composer.json`. Run:

```bash
composer install
```

Then open **Administration → Mail Configuration** and configure SMTP host, port, TLS/SSL, authentication, username/password, From/Reply-To values and a test recipient. The SMTP password is encrypted before database storage and is never returned by the API.

## Fresh setup

1. Copy `.env.example` to `.env`.
2. Set a unique `APP_KEY`, for example `constructionerp`.
3. Create a database and import `database/starterkit_fresh.sql`.
4. Generate and keep fixed `TOKEN_SECRET` and `APP_ENCRYPTION_KEY`.
5. Run `composer install` for PHPMailer.
6. Open `platform-register.php` and create the first Platform Owner.
7. Login and configure Business, Branch, Roles, Menus, Permissions, Security, Theme and Mail Settings.
8. For a new customer, normally edit only `assets/css/theme.css` and add customer-specific business modules.

If upgrading v3, also run `database/upgrade_v3_to_v4_semantic_theme.sql` once to rename old Theme Settings keys.

See `docs/STARTER_KIT_ARCHITECTURE.md`, `docs/CSS_THEME_GUIDE.md`, `docs/COMPONENT_USAGE.md`, `docs/MAIL_CONFIGURATION.md` and `docs/NEW_PROJECT_WORKFLOW.md`.
