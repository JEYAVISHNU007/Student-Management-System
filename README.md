# CampusFlow Student Management System

## Requirements
- XAMPP with Apache and MySQL
- PHP with PDO MySQL enabled
- MySQL port configured as **3307** in this package's `config.php`

## Installation
1. Extract this folder to `C:\xampp\htdocs\sms`. If a previous `sms` folder exists, rename it to `sms_old` first so you do not overwrite your work.
2. Start Apache and MySQL in XAMPP.
3. Open `http://localhost/phpmyadmin`.
4. Select the **Import** tab and import `sql/schema.sql` from this folder. The SQL creates the `sms` database and starter tables.
5. Open `http://localhost/sms/`.

## Admin login
- Username: `admin`
- Password: `admin123`

On first login, the API creates the admin only if the `users` table is empty. If an admin already exists with an unknown password, use the reset instructions below.

## Reset admin password if needed
1. Keep this folder under `C:\xampp\htdocs\sms`.
2. Open `http://localhost/sms/reset-admin.php?token=reset-campus-admin-2026` once.
3. You should see `Admin password reset successfully`.
4. **Immediately delete `reset-admin.php` from the project folder.** Do not leave the reset endpoint on a public server.
5. Return to `http://localhost/sms/` and sign in with `admin` / `admin123`.

## Important
- Do not use VS Code Live Server. Open the project through `http://localhost/sms/` so PHP can run.
- If MySQL uses a different port, edit the PDO connection in `config.php`.
- This is a local student project starter. Change the default password before using it beyond local testing.
