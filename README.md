<div align="center">

# Ultramod

**A code-first modular application platform for Laravel.**

Install apps. Extend them without forking. Keep writing normal Laravel.

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php)](https://php.net)
[![Tests](https://img.shields.io/badge/tests-57%20passing-brightgreen?style=flat-square)]()
[![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)]()

*"Apps are code. The platform provides infrastructure. Applications provide behavior.
Apps should be extensible without being forked."*

</div>

---

## Why Ultramod?

Every Laravel project rebuilds the same things: auth, roles, settings, audit logs,
file handling, a dashboard, a navigation menu. Ultramod ships all of that as
**platform capabilities** — and gives you a module system where applications are
regular Laravel code living in `apps/`, independently installed, enabled and removed.

**The one thing you must see:** an app can extend another app *without touching its
source code*. Upgrade the base app; the extension keeps working. That is the whole
point of this project.

```
Platform
   │
   ├── Core Capabilities ── Identity · Roles & Permissions · Settings
   │                          Files · Audit Log · Extension Slots · Menus
   │
   ├── Applications ──────── Notes · Kanban · Calendar · Bookmarks · AI Assistant
   │
   └── Extensions ────────── Notes Status (extends Notes — zero fork)
```

## It's just Laravel

No DSL. No visual builder. No schema builder. No magic abstraction layer.

An Ultramod app contains the things you already know:

```
apps/notes/
├── platform.json                    ← app manifest (id, version, permissions, deps)
├── src/
│   ├── NotesServiceProvider.php     ← registers routes, views, migrations
│   ├── Http/Controllers/            ← plain controllers
│   ├── Http/Requests/               ← form requests
│   ├── Models/                      ← Eloquent models
│   └── Events/                      ← extension points
├── routes/web.php
├── resources/views/
├── database/migrations/
└── tests/
```

Open any app and you will think: *"this is just Laravel."* That is deliberate.

## Quick start

```bash
git clone <your-repo> ultramod && cd ultramod
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite     # or configure MySQL/Postgres in .env
php artisan migrate --seed

php artisan serve
```

Log in at `http://localhost:8000` with the seeded admin:

| Email | Password |
|---|---|
| `admin@example.com` | `password` |

The first user to self-register on a fresh install automatically becomes the
super admin.

## Meet the starter apps

Six working applications ship in the box — not skeletons, real features:

| App | What you get |
|---|---|
| **Notes** | Markdown notes, tags, search, attachments, revision history + restore |
| **Kanban** | Boards, columns, drag-and-drop tasks, priority, due dates, assignees, tags |
| **Calendar** | Month grid, events, all-day, locations, attendees, reminders |
| **Bookmarks** | Collections, tags, favorites, search, async metadata/favicon fetching (queued) |
| **AI Assistant** | Conversations, chat UI, OpenAI-compatible + Ollama, per-app settings |
| **Notes Status** | The showcase extension — see below |

Enable what you need, ignore the rest:

```bash
php artisan platform:app:discover
php artisan platform:app:install notes
php artisan platform:app:enable notes
```

## The showcase: Notes + Notes Status

This is the architectural proof the platform is built around.

`notes-status` is a **separate package** that adds a status workflow
(Draft → Review → Published → Archived) to Notes. It owns its own tables
(`note_statuses`, `note_status_assignments`), defines its own permissions, and
injects its UI into Notes pages — **without changing a single line of Notes code.**

```
Notes 1.0  ──listens──▶  Notes Status 1.0
   ↑ publishes                consumes
   NoteCreated                events + UI slots
   NoteUpdated                owns its own tables
   NoteDeleted                survives Notes upgrades
```

Try the full lifecycle yourself:

```bash
php artisan platform:app:install notes && php artisan platform:app:enable notes
php artisan platform:app:install notes-status && php artisan platform:app:enable notes-status
```

- Create a note → it automatically gets a **Draft** badge and a status dropdown.
- Disable `notes-status` → the status UI vanishes. Notes keeps working, data intact.
- Re-enable it → status returns instantly.
- Try `php artisan platform:app:disable notes` while the extension is enabled →
  rejected: *"Cannot disable Notes because Notes Status depends on it."*
- Upgrade Notes 1.0 → 1.1 → Notes Status doesn't need a single change.

### How extensions plug in

Four small, intentional mechanisms — all Laravel-native:

| Mechanism | How |
|---|---|
| **Events** | Apps dispatch `NoteCreated`, `NoteUpdated`, …; extensions listen. The app never knows who's listening. |
| **UI slots** | Apps render `<div>@extensionslot('note.metadata', ['note' => $note])</div>`; extensions register views for that slot. Generic — no app names hardcoded. |
| **Permissions** | Extensions declare `notes-status.view` etc. in their manifest; the platform registers gates automatically. |
| **Menus** | Any enabled app can contribute nav entries via `MenuProvider`. |

## Build your own app in 60 seconds

```bash
php artisan platform:make-app weather
```

Scaffolds a working skeleton under `apps/weather/` — service provider, controller,
route, view, migration folder, manifest, Composer autoload entry — then
install & enable it from the dashboard or CLI.

Want full CRUD? Use the **scaffolder in the web UI** (`/platform/apps/create`):
enter a table name and columns, and Ultramod generates migration, model
(fillable + casts), controller, form request with validation, resource routes
and Bootstrap index/form views. Generated code is ordinary Laravel — edit it freely.

```
App name:  inventory
Table:     products
Columns:   name:string · price:decimal · active:boolean
                ↓ generate
Migration · Product model · Controller · StoreProductRequest · routes · views
```

## Core capabilities

Infrastructure every app consumes — no app rebuilds these:

| Capability | What it provides |
|---|---|
| **Identity & Auth** | Login/register/logout (rate-limited), login history, profile, password change |
| **Roles & Permissions** | Roles UI, per-app permission catalogues, dynamic gates, super-admin bypass. Apps just declare `notes.create` and call `$user->can('notes.create')` |
| **Settings** | `SettingsManager` — platform-wide and per-app key/value settings with cache |
| **Files** | `FileManager` — upload/download/delete with metadata; morph attachments to any app model |
| **Audit log** | `AuditLogger` — actor, action, target, metadata for every meaningful event |
| **App registry** | Discovery, lifecycle, version constraint checks, dependency graph |

All admin pages (users, roles, apps) are Bootstrap, responsive, and permission-gated.

## App lifecycle

```
discovered ──install──▶ installed ──enable──▶ enabled
     ▲                     │  ▲                  │
     └────── uninstall ◀── disabled ◀──disable───┘
```

```bash
php artisan platform:app:list          # all apps + status
php artisan platform:app:discover      # scan apps/ for new manifests
php artisan platform:app:install notes # run migrations, register permissions
php artisan platform:app:enable notes  # routes/views/listeners go live
php artisan platform:app:update notes  # new version? runs new migrations only
php artisan platform:app:disable notes # out of the runtime, data kept
php artisan platform:app:uninstall notes
```

Dependencies are declared in the manifest and enforced in every direction:

```json
{
    "id": "notes-status",
    "type": "extension",
    "requires": { "platform": "^1.0", "notes": "^1.0" },
    "extends": ["notes"]
}
```

## Project layout

```
app/
├── Platform/               # the platform core (AppManager, contracts, services, models)
├── Http/Controllers/       # platform shell (dashboard, roles, users, profile)
└── Providers/
apps/                       # every application & extension lives here
database/migrations/        # platform-owned tables (platform_*)
resources/views/platform/   # shell UI (Bootstrap)
tests/Feature/              # 57 tests: lifecycle, auth, roles, every starter app
```

Core owns `platform_*` tables. Apps own their own tables (`notes`, `kanban_*`,
`calendar_events`, …). Extensions own the data they introduce — Notes Status
never adds a column to `notes`.

## Testing

```bash
php artisan test    # 57 tests, 336 assertions
```

Coverage includes app lifecycle & dependency rules, the Notes ⇄ Notes Status
extension contract (install/enable/disable, data isolation, upgrade safety),
auth (login, rate limit, registration), role/permission enforcement, and
end-to-end feature tests for all starter apps.

## Non-goals (on purpose)

Ultramod is a **modular monolith**, not a framework factory. It will never grow
into a marketplace, low-code builder, plugin sandbox, or microservice runtime.
If you're looking for Filament/Nova-style admin generation or a visual app
builder, this is not that — and that's the point.

## License

MIT
