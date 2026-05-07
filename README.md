# OMS Laravel API

Order Management System (OMS) API menggunakan Laravel.

Project ini menyediakan API untuk:
- Authentication
- Product Management
- Order Management
- Payment Simulation
- Shipping Simulation

---

# Requirements

Pastikan sudah menginstall:

- PHP >= 8.2
- Composer
- MySQL / MariaDB
- Git (optional)
- Laragon / XAMPP / WAMP (disarankan Laragon)

---

# Rekomendasi Environment

Menggunakan:

- PHP 8.2+
- MySQL / MariaDB
- Laragon Full

Download Laragon:

https://laragon.org/download

---

# 1. Clone / Extract Project

Jika menggunakan Git:

```bash
git clone <repository-url>
```

Atau extract file project ZIP.

---

# 2. Masuk ke Folder Project

```bash
cd oms
```

atau:

```bash
cd oms-laravel
```

sesuaikan dengan nama folder project.

---

# 3. Install Dependency Laravel

Jalankan:

```bash
composer install
```

Tunggu hingga selesai.

Jika berhasil biasanya muncul:

```bash
Package manifest generated successfully
```

---

# 4. Setup File Environment

Copy file `.env.example` menjadi `.env`

Windows CMD:

```bash
copy .env.example .env
```

Git Bash / Linux / MacOS:

```bash
cp .env.example .env
```

---

# 5. Generate Laravel APP_KEY

Jalankan:

```bash
php artisan key:generate
```

Jika berhasil:

```bash
Application key set successfully.
```

---

# 6. Setup Database

Buka file:

```bash
.env
```

Lalu edit bagian database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oms_db
DB_USERNAME=root
DB_PASSWORD=
```

## Notes

Jika menggunakan Laragon default:

```env
DB_USERNAME=root
DB_PASSWORD=
```

password dikosongkan.

---

# 7. Setup Queue dan Cache

Masih di file `.env`, ubah:

```env
QUEUE_CONNECTION=sync
CACHE_STORE=file
```

Tujuannya agar project tidak membutuhkan Redis saat development.

---

# 8. Buat Database MySQL

## Cara via phpMyAdmin

Buka:

```text
http://localhost/phpmyadmin
```

Lalu buat database:

```text
oms_db
```

---

## Cara via Terminal MySQL

Masuk MySQL:

```bash
mysql -u root
```

Lalu buat database:

```sql
CREATE DATABASE oms_db;
```

Keluar:

```sql
exit;
```

---

# 9. Jalankan Migration

Jalankan:

```bash
php artisan migrate
```

Jika sebelumnya pernah error migration:

```bash
php artisan migrate:fresh
```

---

# 10. Jalankan Laravel Server

Jalankan:

```bash
php artisan serve
```

Jika berhasil:

```bash
Server running on http://127.0.0.1:8000
```

---

# 11. Test API

Base URL:

```text
http://localhost:8000/api/v1
```

---

# Available API Endpoints

## Authentication

### Register

```http
POST /api/v1/auth/register
```

### Login

```http
POST /api/v1/auth/login
```

### Logout

```http
POST /api/v1/auth/logout
```

### Profile

```http
GET /api/v1/auth/profile
```

---

# Products

### Get Products

```http
GET /api/v1/products
```

### Search Products

```http
GET /api/v1/products/search?q=laptop
```

### Categories

```http
GET /api/v1/products/categories
```

### Product Detail

```http
GET /api/v1/products/{productId}
```

---

# Orders

### Create Order

```http
POST /api/v1/orders
```

### Order List

```http
GET /api/v1/orders
```

### Order Detail

```http
GET /api/v1/orders/{orderId}
```

---

# Payments

### Initiate Payment

```http
POST /api/v1/orders/{orderId}/payment/initiate
```

### Payment Status

```http
GET /api/v1/orders/{orderId}/payment/status
```

---

# Shipping

### Calculate Shipping

```http
POST /api/v1/shipping/calculate
```

### Provinces

```http
GET /api/v1/shipping/provinces
```

### Cities

```http
GET /api/v1/shipping/cities
```

---

# Debugging

## Menampilkan Semua Route

```bash
php artisan route:list
```

---

# Clear Cache Laravel

```bash
php artisan optimize:clear
```

---

# Rebuild Autoload Composer

```bash
composer dump-autoload
```

---

# Restart Laravel Server

Stop server:

```bash
CTRL + C
```

Jalankan ulang:

```bash
php artisan serve
```

---

# Common Errors

## Table already exists

Gunakan:

```bash
php artisan migrate:fresh
```

---

## Composer not found

Install Composer:

https://getcomposer.org

---

## SQLSTATE database error

Pastikan:
- MySQL berjalan
- database sudah dibuat
- konfigurasi `.env` benar

---

# Development Notes

Project menggunakan:
- Laravel 11
- REST API Architecture
- Service Layer Pattern
- Custom API Response Trait

---

# API Testing Tools

Disarankan menggunakan:

- Postman


---

# Default Local URL

```text
http://localhost:8000
```

---

# Author

OMS Laravel API Project