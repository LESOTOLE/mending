# Mending Laundry Management System

A multi-outlet laundry management and Point of Sale (POS) web application built with native PHP and MySQL. The system integrates facial recognition for employee attendance verification, GPS geofencing, role-based access control (RBAC), real-time order lifecycle tracking, rack shelf storage indexing, expense tracking, and payroll generation.

---

## Table of Contents

- Overview
- Key Features
  - Multi-Outlet Management
  - Point of Sale (POS) and Order Processing
  - Facial Recognition and Geolocation Attendance
  - Human Resources and Payroll
  - Accounting and Financial Reports
  - Hosting and Multi-Environment Auto-Detection
- User Roles and Access Matrix
- Technology Stack
- Database Architecture
- Prerequisites
- Installation and Local Setup
- Production and Hosting Deployment
- Directory Structure
- Security Practices
- License

---

## Overview

Mending Laundry is designed to streamline daily laundry business operations across multiple branches. It bridges frontline counter staff, operations managers, HR personnel, and business owners within a centralized platform.

The system prevents attendance fraud by combining client-side neural network face recognition with server-side GPS distance validation using the Haversine formula.

---

## Key Features

### Multi-Outlet Management
- Centralized administration for multiple branch locations.
- Interactive map picker powered by Leaflet and OpenStreetMap for configuring geographic coordinates (latitude and longitude).
- Configurable geofence radius (in meters) per outlet to restrict where staff can log attendance.
- Dedicated service pricing catalogs per outlet.

### Point of Sale (POS) and Order Processing
- Dual pricing models: by weight (Kilogram) and per item (Satuan).
- Fast customer lookup with auto-complete and quick registration for new customers.
- Support for multiple payment methods: Cash, Bank Transfer, and QRIS.
- Dynamic discounts and automatic total rounding calculations.
- Integrated shelf rack management to track physical item storage location (e.g., Rack A-12).
- Real-time status workflow tracking: New (Baru) -> In Process (Dicuci) -> Ready (Selesai) -> Picked Up (Diambil).
- Direct receipt printing in standard thermal ESC/POS 58mm/80mm format and PDF invoice generation.

### Facial Recognition and Geolocation Attendance
- Browser-based biometric facial detection and 128-dimensional descriptor extraction using face-api.js and MobileNet / TinyFaceDetector.
- Real-time Euclidean distance comparison against registered employee facial descriptors (threshold <= 0.60).
- Geofence verification via browser Geolocation API and validated server-side using the Haversine spherical distance formula.
- Dual face enrollment options: live webcam snapshot or image file upload handled by HR administrators.
- Automatic capture and storage of check-in and check-out verification selfie photos.
- High-performance asset loading with parallel initialization, client-side CacheStorage integration, and HTTP caching headers.

### Human Resources and Payroll
- Complete employee data management linked to user credentials and designated outlet assignments.
- Real-time attendance logs with selfie verification photo viewer, status badges, and Google Maps GPS links.
- Automated payroll calculation factoring in base salary, bonuses, attendance counts, and deductions.
- Printable salary slips featuring cryptographically signed verification QR codes and HTML2PDF export.

### Accounting and Financial Reports
- Multi-category expense tracking with outlet-specific ledger entries.
- Executive owner dashboard with interactive revenue and expense analytics powered by Chart.js.
- Filterable financial statements with customizable date range queries.

### Hosting and Multi-Environment Auto-Detection
- Automatic base path and URL detection in configuration (`APP_URL`, `ASSETS_URL`, `BASE_URL`).
- Zero manual URL re-configuration required when migrating between local environments (XAMPP, Laragon, Docker) and shared hosting (cPanel, InfinityFree, VPS).

---

## User Roles and Access Matrix

| Role Name | Access Level | Responsibilities |
|-----------|--------------|------------------|
| Owner | Full System Access | Multi-branch financial analytics, revenue charts, expense monitoring, and high-level audits |
| HRD | Human Resources | Employee directory, face registration, attendance verification audits, and payroll processing |
| Kasir | Front Office / POS | Counter POS operations, customer management, order status updates, rack storage, and daily branch expenses |
| Admin Outlet | Branch Operations | Branch-specific service catalog configuration and local outlet management |

---

## Technology Stack

### Backend
- Language: PHP 7.4 / PHP 8.x (Object-Oriented MySQLi)
- Database: MySQL 5.7+ / MariaDB 10.3+
- Architecture: Modular Procedural MVC-style structure with Prepared Statements

### Frontend
- Framework: Bootstrap 5
- Styling: Custom Web3 theme with full mobile responsiveness
- Scripting: Vanilla JavaScript (ES6+), jQuery
- Icons: Font Awesome 6 (locally hosted)
- Typography: Inter typeface (locally hosted)

### Libraries and Utilities
- Computer Vision: face-api.js (TinyFaceDetector, FaceLandmark68Net, FaceRecognitionNet)
- Maps: Leaflet.js with OpenStreetMap tiles
- Data Visualization: Chart.js
- Dialogs: SweetAlert2
- PDF Engine: HTML2PDF.js

---

## Database Architecture

The application database schema is maintained in `database/mending.sql`.

Key relational tables:
- `roles`: Defines system authorization levels (Owner, HRD, Kasir, Admin Outlet).
- `users`: Stores login credentials with bcrypt password hashing and cashier authorization PIN.
- `outlets`: Stores branch details, latitude, longitude, and attendance radius limits.
- `karyawan`: Contains employee profiles, outlet linkages, reference photo paths, and JSON-encoded 128D face descriptors.
- `absensi`: Daily check-in/out records, coordinates, snapshot paths, and verification status.
- `pelanggan`: Customer records with unique contact details.
- `layanan`: Service catalog specifying service type, pricing, and turnaround time per outlet.
- `transaksi`: Header table for laundry transactions, invoices, status, totals, and rack location.
- `transaksi_detail`: Itemized breakdown of services within each transaction.
- `kategori_pengeluaran`: Taxonomies for operational expenditure.
- `pengeluaran`: Operational expense records linked to outlets and recording users.
- `penggajian`: Monthly payroll ledger including base salary, bonuses, deductions, and notes.

For detailed table schemas and column descriptions, refer to `database_tables.md`.

---

## Prerequisites

Before running the application, ensure your environment meets the following specifications:
- Web Server: Apache 2.4+ (with `mod_rewrite` and `mod_headers` enabled) or Nginx
- PHP: Version 7.4 or 8.0+
- PHP Extensions: `mysqli`, `openssl`, `json`, `mbstring`, `fileinfo`, `gd`
- Database: MySQL 5.7+ or MariaDB 10.3+
- Modern Web Browser: Google Chrome, Mozilla Firefox, Microsoft Edge, or Safari with camera and geolocation permissions granted

---

## Installation and Local Setup

### 1. Clone the Repository
```bash
git clone https://github.com/LESOTOLE/mending.git
cd mending
```

### 2. Database Setup
1. Start your MySQL service (e.g., via XAMPP Control Panel).
2. Open phpMyAdmin or your MySQL CLI client:
```sql
CREATE DATABASE mending CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```
3. Import the database dump:
```bash
mysql -u root -p mending < database/mending.sql
```

### 3. Environment Configuration
Copy the template configuration file:
```bash
cp env.example.php env.php
```

Open `env.php` and verify your database connection settings:
```php
<?php
define('DB_SERVER',   'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME',     'mending');
```

### 4. Web Server Placement
- If using XAMPP on Windows, place the folder in `C:\xampp\htdocs\mending` or `D:\xampp\htdocs\mending`.
- If using Laragon, place the folder in `C:\laragon\www\mending`.
- If using Linux Apache, copy to `/var/www/html/mending` and ensure correct permissions.

### 5. Launch the Application
Navigate to the application URL in your browser:
```text
http://localhost/mending/admin/login.php
```

---

## Production and Hosting Deployment

The codebase is built with zero-configuration URL handling. When deploying to shared hosting (cPanel, DirectAdmin, InfinityFree, Hostinger, or VPS):

1. Upload all files to the designated web root (e.g., `public_html` or a subfolder like `public_html/laundry`).
2. Create a MySQL database and user in your hosting control panel.
3. Import `database/mending.sql` via phpMyAdmin.
4. Create `env.php` in the root directory and update your database credentials:
```php
<?php
define('DB_SERVER',   'your-db-hostname');
define('DB_USERNAME', 'your-db-user');
define('DB_PASSWORD', 'your-db-password');
define('DB_NAME',     'your-db-name');
```
5. Ensure the `uploads/` directory has write permissions (`chmod 755` or `chmod 775`).
6. Web server headers and model asset caching are handled automatically by the included `.htaccess` files.
7. Access the application via your domain:
```text
https://your-domain.com/admin/login.php
```

---

## Directory Structure

```text
mending/
|-- admin/
|   |-- dashboard/             # Role-specific dashboard views (Owner, HRD)
|   |-- hrd/                   # Attendance log audits and payroll slip generation
|   |-- keuangan/              # POS terminal, transaction records, receipt printing, expenses
|   |-- manajemen_layanan/     # Service pricing catalog operations
|   |-- outlet/                # Outlet CRUD and interactive map coordinates picker
|   |-- staff/                 # User directory, employee editing, and facial attendance UI
|   |-- dashboard.php          # Main router for role-based dashboard landing
|   |-- kelola_karyawan.php    # Employee listing and face enrollment modal
|   |-- login.php              # Secure login controller and authentication interface
|   |-- logout.php             # Session destruction handler
|-- assets/
|   |-- css/                   # Web3 application stylesheet and responsive rules
|   |-- js/                    # Application client logic
|   |-- vendor/                # Vendored dependencies (Bootstrap, Font Awesome, Leaflet, face-api)
|       |-- face-api/          # face-api.js library and neural network weight shards
|-- database/
|   |-- migrations/            # Schema migration scripts
|   |-- mending.sql            # Complete database dump with tables and initial seed data
|   |-- run_migration.php      # CLI migration executor
|-- docs/                      # Architectural specifications and implementation plans
|-- includes/
|   |-- config.php             # Core configuration, URL auto-detect, DB connector, and auth guards
|   |-- csrf.php               # CSRF token generation and validation helpers
|   |-- footer.php             # Global HTML footer component
|   |-- header.php             # Global HTML header, meta tags, and style includes
|   |-- header_pos.php         # Dedicated distraction-free header for POS interface
|   |-- sidebar.php            # Dynamic RBAC navigation sidebar
|-- uploads/
|   |-- absensi/               # Captured verification selfie images
|   |-- karyawan/              # Registered reference face profile photos
|-- .gitignore                 # Version control exclusions
|-- .htaccess                  # Root web server configuration and caching headers
|-- database_tables.md         # Database dictionary and table specifications
|-- env.example.php            # Environment configuration template
`-- README.md                  # Project documentation
```

---

## Security Practices

- Password Hashing: User passwords are encrypted using the industry-standard bcrypt algorithm (`password_hash` with `PASSWORD_DEFAULT`).
- SQL Injection Prevention: Database queries utilize parameterized Prepared Statements (`$conn->prepare()`).
- Cross-Site Request Forgery (CSRF): Sensitive forms include dynamic CSRF tokens verified upon submission.
- Session Hardening: Sessions enforce `session_regenerate_id(true)` upon login to prevent session fixation attacks.
- Access Control: The `checkAuth()` middleware protects all administrative endpoints against unauthorized role escalation.
- Geofence Integrity: Distance calculations are validated on both client (UI feedback) and server side (attendance persistence) using the Haversine formula to prevent spoofing.

---

## License

This project is released under the MIT License. You are free to use, modify, and distribute this software in personal and commercial environments.
