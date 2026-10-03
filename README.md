## Task Manager

A project-based task manager built with Laravel 12. Tasks are stored in MySQL and ordered within each project. Drag a task to update its priority; each project starts at priority 1.

## Requirements

- PHP 8.3 or newer with `pdo_mysql`
- Composer
- MySQL 8 or compatible MariaDB
- Node.js and npm

## Setup

Create the MySQL database. For a default XAMPP installation:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE task_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Install dependencies and initialize the application:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

The example environment uses the local XAMPP defaults (`127.0.0.1`, database `task_manager`, user `root`, and a blank password). Update `.env` if your MySQL credentials differ.

## Run

```powershell
php artisan serve
```

Open `http://127.0.0.1:8000`. Run `npm run dev` in a second terminal during frontend development.

## Tests

```powershell
php artisan test
```
