# TazamaDesk — IT Help Desk

IT ticketing system for Tazama Petroleum Products Limited. Built for the IT department to log, track, and resolve employee support requests.

## What it does

Employees submit IT tickets through a web form. The IT team gets a shared dashboard where they can see open tickets, assign them, update status, and close them out. There's also a knowledge base for common issues and a reports page.

## How tickets work

When an employee logs a ticket, they pick their department and the type of issue. The system figures out the priority automatically based on those two things — the employee never sets it manually. A finance department network issue gets a different priority than the same issue in admin, because the business impact is different.

The priority matrix covers 9 departments and 7 issue types. It's enforced on the server, so no one can change it from the browser.

## Tech

- PHP (no framework)
- JSON flat-file storage (`data/store.json`)
- SQLite employee registry for phone-based auth
- No external dependencies beyond PHP

## Running it

```bash
php -S 0.0.0.0:8099
```

Open `http://localhost:8099`. Log in with your work email and password.

Demo accounts (development only):
- Admin: `moses.Chola@tazamadesk.com` / `admin123`
- Employee: `Chileshe.Chileshe@tazamadesk.com` / `employee123`

## Tazai AI assistant

The floating button on the site connects to Tazai, an AI IT support agent. It's currently disabled while we finish testing. When it's on, employees can describe their issue in plain language before filing a ticket — Tazai will try to help fix it first.

## Project status

Active development. Built by the Tazama IT team in collaboration with Vidmar AI.
