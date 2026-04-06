@echo off
REM ====================================================================
REM Cleanup Script for ECOSYSTEM Integration
REM This script removes unnecessary migrations and files that already
REM exist in ECOSYSTEM (clients, employees functionality)
REM ====================================================================

echo.
echo ========================================
echo  ECOSYSTEM Integration Cleanup
echo ========================================
echo.
echo This script will DELETE the following files that are
echo not needed because they already exist in ECOSYSTEM:
echo.
echo MIGRATIONS:
echo - Client-related migrations (3 files)
echo - Employee-related migrations (3 files)
echo.
echo Press Ctrl+C to cancel or
pause

echo.
echo Deleting unnecessary migrations...
echo.

REM Delete client-related migrations
if exist "database\migrations\2025_09_02_090220_create_clients_table.php" (
    del "database\migrations\2025_09_02_090220_create_clients_table.php"
    echo [DELETED] 2025_09_02_090220_create_clients_table.php
)

if exist "database\migrations\2025_09_11_070212_add_address_and_city_to_clients_table.php" (
    del "database\migrations\2025_09_11_070212_add_address_and_city_to_clients_table.php"
    echo [DELETED] 2025_09_11_070212_add_address_and_city_to_clients_table.php
)

if exist "database\migrations\2025_09_11_071714_add_client_code_to_clients_table.php" (
    del "database\migrations\2025_09_11_071714_add_client_code_to_clients_table.php"
    echo [DELETED] 2025_09_11_071714_add_client_code_to_clients_table.php
)

REM Delete employee-related migrations
if exist "database\migrations\2025_09_12_034357_create_employees_table.php" (
    del "database\migrations\2025_09_12_034357_create_employees_table.php"
    echo [DELETED] 2025_09_12_034357_create_employees_table.php
)

if exist "database\migrations\2025_09_12_034434_create_employees_table.php" (
    del "database\migrations\2025_09_12_034434_create_employees_table.php"
    echo [DELETED] 2025_09_12_034434_create_employees_table.php (duplicate)
)

if exist "database\migrations\2025_09_12_061959_create_project_employee_table.php" (
    del "database\migrations\2025_09_12_061959_create_project_employee_table.php"
    echo [DELETED] 2025_09_12_061959_create_project_employee_table.php
)

echo.
echo ========================================
echo  Cleanup Complete!
echo ========================================
echo.
echo NOTE: The project_employee table is now created in the
echo consolidated migration file:
echo 2026_01_01_000000_create_project_delivery_tables.php
echo.
echo Next Steps:
echo 1. Review ECOSYSTEM_INTEGRATION_GUIDE.md
echo 2. Copy necessary files to ECOSYSTEM
echo 3. Run the consolidated migration in ECOSYSTEM
echo.
pause
