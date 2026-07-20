# e-Registrar

**e-Registrar** is a web-based student document request and appointment scheduling system. It lets students and alumni request academic documents — Form 137, Transcript of Records (TOR), Good Moral Certificate, Certificate of Enrollment, and other school records — from anywhere, schedule an appointment to claim them, and track each request's status without visiting the registrar's office in person.

The goal is to replace the typical manual process (paper forms, in-person queues, repeat visits) with an online request-and-scheduling flow, while giving the registrar's office a single place to manage, approve, and release requests.

## Current status

This repository currently implements the **public-facing site**: the marketing/landing page (with About, Services, and Contact sections), and the account system (registration, login, password reset, email verification, profile and security settings) via Laravel Fortify. The actual document-request and appointment-scheduling workflow — the registrar-facing admin tools, request submissions, and status tracking — is not built yet.

## Tech stack

- PHP 8.4, [Laravel](https://laravel.com) 13
- [Livewire](https://livewire.laravel.com) 4 + [Flux UI](https://fluxui.dev) (free)
- [Laravel Fortify](https://laravel.com/docs/fortify) for authentication
- [Tailwind CSS](https://tailwindcss.com) 4 + [Vite](https://vitejs.dev)
- MySQL
- [Pest](https://pestphp.com) for testing

## Requirements

- PHP 8.4+ with the extensions Laravel needs (`pdo_mysql`, `mbstring`, etc.)
- Composer
- Node.js + npm
- MySQL (or another server-backed database — adjust `.env` accordingly)

## Getting started (cloning the project)

1. **Clone the repository**

   ```bash
   git clone <repository-url> e-registrar
   cd e-registrar
   ```

2. **Install PHP and JS dependencies**

   ```bash
   composer install
   npm install
   ```

3. **Configure your environment**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Then open `.env` and set your database credentials (defaults to MySQL):

   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=e-registrar
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Create the database**

   Create a MySQL database matching `DB_DATABASE` (e.g. via your DB client or `mysql -u root -p -e "CREATE DATABASE \`e-registrar\`;"`).

5. **Run migrations**

   ```bash
   php artisan migrate
   ```

6. **Build frontend assets**

   ```bash
   npm run build
   ```

7. **Serve the app**

   For local development (runs the server, queue listener, and Vite dev server together):

   ```bash
   composer run dev
   ```

   Or serve it directly:

   ```bash
   php artisan serve
   ```

   Then visit the app at the printed URL (e.g. `http://localhost:8000`).

> Steps 2–6 can also be run in one shot with `composer run setup`, as long as your database already exists and `.env` is configured first.

## Running tests

```bash
php artisan test
```

## Code style

```bash
vendor/bin/pint
```
