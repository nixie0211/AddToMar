# AddToMar — Pharmacy Inventory Management System

## Setup Instructions (XAMPP)

1. **Copy the folder**
   Copy the entire `AddToMar` folder into your XAMPP `htdocs` directory, so the path is:
   `C:\xampp\htdocs\AddToMar` (Windows) or `/Applications/XAMPP/htdocs/AddToMar` (Mac).

2. **Start Apache and MySQL**
   Open the XAMPP Control Panel and start both **Apache** and **MySQL**.

3. **Create the database**
   - Go to `http://localhost/phpmyadmin`
   - Click **New**, or just import directly (the SQL file creates the database itself)
   - Click **Import**, choose `AddToMar/sql/addtomar_db.sql`, and click **Go**
   - This creates the `addtomar_db` database, all tables, and seed data automatically.

4. **Visit the site**
   Open `http://localhost/AddToMar` in your browser.

## Default Accounts

| Role       | Username / Login | Password   |
|------------|-------------------|------------|
| Pharmacist | `admin`           | `admin123` |
| Customer   | `juan`            | `admin123` |

(You can also register a new customer account from the login page.)

## Notes

- Database credentials are set in `config/database.php` (defaults to XAMPP's `root` user with no password — the XAMPP default).
- Uploaded images are stored in `assets/uploads/medicines/` and `assets/uploads/payments/`. Make sure these folders are writable.
- The default down payment percentage is configurable from **Pharmacist → Settings**.
- Order numbers follow the format `ADM-YYYY-0001`.
- When a pharmacist approves an order, stock is automatically deducted. If stock is insufficient, approval is blocked with a warning.

## Folder Structure

```
AddToMar/
├── assets/            css, js, images, uploads
├── config/            database.php
├── includes/          header, footer, navbar, sidebar, auth helpers
├── customer/          customer-facing pages
├── pharmacist/         admin/pharmacist pages
├── ajax/              AJAX endpoints (cart, medicine CRUD)
├── reports/           inventory / sales / order reports
├── sql/               addtomar_db.sql (import this first)
├── login.php / register.php / logout.php / index.php
```
