# Piazzole Vismara

Camper pitch rental management system built with Laravel 12 and Blade.

## What it does

A management platform for a camper parking facility divided into numbered 
pitches. Handles the full rental lifecycle: from pitch and customer management 
to contracts, payment schedules, automatic renewals, and PDF invoice generation.

## Tech stack

- **Backend:** PHP 8.2, Laravel 12
- **Frontend:** Blade templates
- **PDF generation:** Laravel built-in (DOM/XML)
- **Error tracking:** Spatie Laravel Flare
- **Testing:** PestPHP

## Key features

- Pitch and customer registry management
- Contract creation with customizable duration and terms
- Automatic payment schedule generation with due date tracking
- Contract renewal automation
- PDF generation for payment due notices (print-ready)

## Setup
```bash
git clone https://github.com/cripantea/piazzole-vismara
cd piazzole-vismara
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
```
