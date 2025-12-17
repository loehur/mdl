# Scripts Directory

This folder contains utility scripts for testing and database migration.

## Migration Scripts
- `add_salon_id_column.php` - Add salon_id column to users table
- `remove_salon_id_unique.php` - Remove unique constraint from salon_id
- `update_existing_users_salon_id.php` - Update existing users with salon_id

## Test Scripts
- `test_login.php` - Test login endpoint
- `test_register.php` - Test register endpoint
- `test_create_user.php` - Test create user endpoint
- `create_test_cashier.php` - Create test cashier user
- `check_users.php` - Check all users in database

## Utility Scripts
- `clear_all_users.php` - Clear all users from database

## Usage
Run scripts from project root:
```bash
php api/scripts/script_name.php
```

⚠️ **Warning**: Some scripts modify database. Use with caution!
