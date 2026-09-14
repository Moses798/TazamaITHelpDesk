# TazamaDesk — PHP Edition

A server-rendered PHP port of the TazamaDesk IT Help Desk & Ticket Management
system. No database setup required — data is stored in a single JSON file
(`data/store.json`), auto-created from `data/seed.json` on first run.

## Requirements

- PHP 7.4+ (tested on PHP 8.3). No extensions beyond the PHP defaults are required.

## Running it in Visual Studio Code

1. Install the **PHP** extension (or **PHP Intelephense**) for syntax support — optional, just for editing.
2. Open this folder (`tazamadesk-php`) in VS Code.
3. Open a terminal in VS Code (`` Ctrl+` ``) and run:
   ```
   php -S localhost:8000
   ```
4. Open **http://localhost:8000** in your browser.

That's it — no `composer install`, no build step, no database. If you have the
**PHP Server** VS Code extension installed, you can also just right-click
`index.php` → "Start PHP Server" instead of step 3.

### Using XAMPP / WAMP / MAMP instead

Copy the `tazamadesk-php` folder into your `htdocs` (or `www`) directory and
visit `http://localhost/tazamadesk-php/`.

## Demo accounts

Login screen has one-click demo login buttons, or use manually:

| Role      | Email                          | Password      |
|-----------|---------------------------------|---------------|
| Employee  | angela.cruz@tazamadesk.com     | employee123   |
| Admin     | marcus.webb@tazamadesk.com     | admin123      |

Log in as **Marcus Webb (admin)** first — there's a floating message bubble
waiting bottom-right from Angela Cruz on ticket #4821, demonstrating the
employee → admin notification feature.

## What's implemented

- **Auth** — session-based login, credentials resolve to a role automatically, logout.
- **Role-based dashboards** — distinct Employee and Admin views.
- **Ticket list** — filterable by status/priority; list or kanban board view (admin).
- **Kanban board** — real drag-and-drop between columns (native HTML5 D&D + a small
  AJAX endpoint, `update_ticket_ajax.php`) that persists the status change.
- **Ticket detail** — SLA countdown ring, status stepper, assignment, status changes,
  and a **close-ticket flow** that requires a resolution note before closing.
- **Messaging** — employees and admins can message each other on a ticket; messages
  render as chat bubbles distinct from system log entries.
- **Floating notification bubble** — when the employee who owns a ticket messages the
  admin assigned to it, the admin sees a floating bubble (bottom-right) with a preview,
  until they open or dismiss it.
- **New ticket creation**, **Knowledge Base search**, global **search** (tickets + articles),
  **Team & Assignments** and **Reports** (admin), **Settings** page with a one-click
  demo data reset.

## Notes on the storage layer

`data/store.json` is created automatically and is the live working copy — edit
`data/seed.json` if you want to change the starting demo data, then delete
`data/store.json` (or use Settings → "Reset demo data") to regenerate it.

This uses simple file-based storage intentionally, so the whole app runs with
zero configuration. If you want to move this to MySQL later, the data-access
functions are all centralized in `includes/config.php` (`td_load_store()`,
`td_save_store()`, `td_find_ticket_index()`) — that's the layer you'd swap out.
