# Menu (Converted)

This repo contains a **static frontend** (plain HTML/CSS/JS) that talks to a **Laravel (PHP) REST API** with **SQLite**.

## Repo structure
- `backend/` – Laravel API (PHP + SQLite + Sanctum)
- `frontend/` – Static site (HTML/CSS/JS) that calls the API with `fetch()`

## Prerequisites
Backend:
- PHP 8.2+
- Composer

Frontend:
- Windows PowerShell (to use `frontend/serve.ps1`) or any static file server

## Quick start (development)
### 1) Start the backend API (Laravel)
```powershell
cd backend
composer install

# Create .env
# Windows:
Copy-Item .env.example .env
# macOS/Linux:
# cp .env.example .env

php artisan key:generate

# If the SQLite file is missing, create it (usually already present)
if (-not (Test-Path database\database.sqlite)) { New-Item -ItemType File -Path database\database.sqlite | Out-Null }

# Create tables + seed dev data
php artisan migrate --seed

# Start API server
php artisan serve --port=8000
```
API will be available at:
- `http://localhost:8000`

Optional: API docs / playground page (dev-only):
- `http://localhost:8000/docs` (available only when `APP_ENV=local`)

### 2) Start the frontend (static server)
In a second terminal:

Windows PowerShell:
```powershell
cd frontend
.\serve.ps1 -Port 8080
```

macOS/Linux (example):
```bash
cd frontend
python3 -m http.server 8080
```

Open the site:
- `http://localhost:8080/home.html`

## Backend convenience scripts (optional)
From `backend/` you can also use Composer scripts:
- `composer run setup` (installs deps, generates key, migrates, builds assets)
- `composer run dev` (runs API server + queue + logs + Vite)

Note: the repo’s `frontend/` is a separate static site; you can run it with `frontend/serve.ps1`.

## Frontend → backend connection
The frontend reads the API base URL from `frontend/global.js`:
- Default: `http://localhost:8000`
- Optional override: set `localStorage.api_base_url`

Example override (in browser devtools console):
```js
localStorage.setItem('api_base_url', 'http://localhost:8000')
```

## Authentication
- Admin pages store the auth token in `localStorage` under the key: `restaurant_jwt`
- Admin API calls send: `Authorization: Bearer <token>`
- Backend auth is handled by Laravel Sanctum (`auth:sanctum` middleware)

### Dev admin user
`backend/database/seeders/DatabaseSeeder.php` seeds a dev admin user.
To control the seeded credentials, set these in `backend/.env` **before** running `php artisan db:seed`:
- `ADMIN_EMAIL`
- `ADMIN_PASSWORD`

If you change these values later, re-seed:
```powershell
cd backend
php artisan db:seed
```

## API endpoints
Public:
- `GET /` (health)
- `GET /menu`
- `GET /menu/{id}`
- `GET /categories`
- `GET /categories/{id}`
- `POST /contact-messages` (expects `name`, `email`, `subject`, `message`)
- `POST /auth/login`
- `POST /auth/forgot-password`

Requires login:
- `GET /auth/me`
- `PATCH /auth/profile`

Admin-only (requires login + `ADMIN` role):
- `POST /menu`
- `PATCH /menu/{id}`
- `DELETE /menu/{id}`
- `POST /categories`
- `PATCH /categories/{id}`
- `DELETE /categories/{id}`
- `GET /contact-messages`

### Menu pagination (optional)
- `GET /menu` returns a plain array (backwards-compatible)
- `GET /menu?perPage=25&page=1` returns Laravel pagination JSON (`data`, `links`, `meta`)

## Running tests
```powershell
cd backend
php artisan test
```

## Troubleshooting
### "Could not open input file: vendor/phpunit/phpunit/phpunit"
Run tests from inside `backend/`:
```powershell
cd backend
php artisan test
```

### Frontend is still calling the wrong API base URL
Remove the override and reload:
```js
localStorage.removeItem('api_base_url')
```

### Don’t run the frontend as file://
Opening HTML files directly from disk can break `fetch()` for partials/assets. Use `frontend/serve.ps1` (or any static server).
