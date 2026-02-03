# Project Delivery Module - ECOSYSTEM Integration Guide

## Overview
This document provides instructions for integrating the Project Delivery (Project Planning) module into the ECOSYSTEM application.

## Quick Integration Checklist

- [ ] **Step 1:** Verify ECOSYSTEM has `clients`, `employees`, and `users` tables
- [ ] **Step 2:** Copy all necessary files (controllers, models, services, exports, views)
- [ ] **Step 3:** Run the consolidated migration file
- [ ] **Step 4:** Update `Project` model to reference ECOSYSTEM's Client and Employee models (check TODO comments)
- [ ] **Step 5:** Add planning routes to ECOSYSTEM's `routes/web.php`
- [ ] **Step 6:** Add "Project Planning" menu item to ECOSYSTEM's navigation
- [ ] **Step 7:** Install required packages (maatwebsite/excel, barryvdh/laravel-dompdf)
- [ ] **Step 8:** Run `composer dump-autoload`
- [ ] **Step 9:** Test the module at `/delivery/projects/planning`
- [ ] **Step 10:** (Optional) Seed default project phases

## Prerequisites

### ECOSYSTEM Requirements
Before integrating this module, ensure your ECOSYSTEM has the following tables:

1. **`clients` table** with at least:
   - `id` (primary key)
   - Basic client information fields

2. **`employees` table** with at least:
   - `id` (primary key)
   - Basic employee information fields

3. **`users` table** (Laravel standard authentication)

## Integration Steps

### Step 1: Copy Files

Copy the following directories from this project to ECOSYSTEM:

```bash
# Controllers
app/Http/Controllers/
├── ProjectPlanningController.php
├── ProjectPlanningExportController.php
├── ActivityManagementController.php
├── StageManagementController.php
├── ProjectDataController.php
└── DynamicPhaseController.php

# Models
app/Models/
├── Project.php
├── ProjectPhase.php
├── ProjectPlanning.php
├── ProjectActivity.php
├── ActivityStage.php
├── ProjectUpdate.php
└── Document.php

# Services
app/Services/
└── ProjectPlanningService.php

# Exports
app/Exports/
├── TableViewExport.php
├── GanttViewExport.php
└── SCurveExport.php

# Views
resources/views/project-planning/
└── (all blade files)

# Routes
# Add routes from routes/web.php (planning module section)
```

### Step 2: Run Migration

Run the consolidated migration:

```bash
php artisan migrate --path=database/migrations/2026_01_01_000000_create_project_delivery_tables.php
```

This single migration creates all necessary tables for the Project Delivery module.

### Step 3: Update Routes

Add the following routes to your ECOSYSTEM's `routes/web.php`:

```php
// ====================================================================
// PROJECT DELIVERY / PLANNING ROUTES
// ====================================================================

Route::middleware('auth')->group(function () {

    // Planning index - list all projects
    Route::get('/delivery/projects/planning', [ProjectPlanningController::class, 'index'])
        ->name('planning.index');

    // Project-specific planning routes
    Route::prefix('delivery/projects/planning/{project}')->name('planning.')->group(function () {

        // Main planning views
        Route::get('/', [ProjectPlanningController::class, 'show'])->name('show');
        Route::get('/gantt', [ProjectPlanningController::class, 'gantt'])->name('gantt');
        Route::get('/scurve', [ProjectPlanningController::class, 'scurve'])->name('scurve');
        Route::get('/phases-list', [ProjectPlanningController::class, 'getPhases'])->name('phases');

        // Phase Management
        Route::prefix('phases')->name('phases.')->group(function () {
            Route::get('/', [DynamicPhaseController::class, 'index'])->name('index');
            Route::post('/create-custom', [DynamicPhaseController::class, 'createCustomPhase'])->name('create');
            Route::post('/add', [DynamicPhaseController::class, 'addPhase'])->name('add');
            Route::put('/{phase}', [DynamicPhaseController::class, 'updatePhase'])->name('update');
            Route::delete('/{phase}', [DynamicPhaseController::class, 'removePhase'])->name('remove');
            Route::post('/reorder', [DynamicPhaseController::class, 'reorderPhases'])->name('reorder');
            Route::post('/{phase}/toggle', [DynamicPhaseController::class, 'togglePhaseVisibility'])->name('toggle');
        });

        // View configuration
        Route::post('/view-config', [DynamicPhaseController::class, 'updateViewConfig'])->name('view-config');

        // Activity Management
        Route::prefix('activities')->name('activities.')->group(function () {
            Route::post('/', [ActivityManagementController::class, 'store'])->name('store');
            Route::get('/{activity}', [ActivityManagementController::class, 'show'])->name('show');
            Route::put('/{activity}', [ActivityManagementController::class, 'update'])->name('update');
            Route::delete('/{activity}', [ActivityManagementController::class, 'destroy'])->name('destroy');
        });

        // Stage Management
        Route::prefix('stages')->name('stages.')->group(function () {
            Route::post('/', [StageManagementController::class, 'store'])->name('store');
            Route::get('/{stage}', [StageManagementController::class, 'show'])->name('show');
            Route::put('/{stage}', [StageManagementController::class, 'update'])->name('update');
            Route::delete('/{stage}', [StageManagementController::class, 'destroy'])->name('destroy');
            Route::post('/{stage}/reorder', [StageManagementController::class, 'reorder'])->name('reorder');
        });

        // Data endpoints
        Route::prefix('data')->name('data.')->group(function () {
            Route::get('/table', [ProjectDataController::class, 'getTableData'])->name('table');
            Route::get('/gantt', [ProjectDataController::class, 'getGanttData'])->name('gantt');
            Route::get('/scurve', [ProjectDataController::class, 'getSCurveData'])->name('scurve');
        });

        // Export routes
        Route::prefix('export')->name('export.')->group(function () {
            Route::get('/table-pdf', [ProjectPlanningExportController::class, 'exportTablePDF'])->name('table-pdf');
            Route::get('/table-excel', [ProjectPlanningExportController::class, 'exportTableExcel'])->name('table-excel');
            Route::get('/gantt-pdf', [ProjectPlanningExportController::class, 'exportGanttPDF'])->name('gantt-pdf');
            Route::get('/gantt-excel', [ProjectPlanningExportController::class, 'exportGanttExcel'])->name('gantt-excel');
            Route::get('/scurve-pdf', [ProjectPlanningExportController::class, 'exportSCurvePDF'])->name('scurve-pdf');
            Route::get('/scurve-excel', [ProjectPlanningExportController::class, 'exportSCurveExcel'])->name('scurve-excel');
        });
    });
});
```

### Step 4: Update Navigation Menu

Add the Project Delivery menu item to your ECOSYSTEM sidebar navigation:

```html
<!-- Delivery Section -->
<div class="sidebar-section">
    <h4>Delivery</h4>
    <ul>
        <li>
            <a href="{{ route('planning.index') }}">
                <i class="fas fa-tasks"></i>
                <span>Project Planning</span>
            </a>
        </li>
    </ul>
</div>
```

### Step 5: Install Dependencies

Ensure these packages are installed in ECOSYSTEM:

```bash
composer require maatwebsite/excel
composer require barryvdh/laravel-dompdf
```

### Step 6: Seed Initial Data (Optional)

You can seed default project phases by running:

```bash
php artisan db:seed --class=ProjectPhaseSeeder
```

## Model Relationships

### IMPORTANT: Update Project Model References

After copying the files, you MUST update the `Project` model to reference ECOSYSTEM's Client and Employee models.

The `app/Models/Project.php` file contains `TODO` comments marking the lines that need to be updated:

1. **Update the imports at the top of the file:**
   ```php
   // Replace these with ECOSYSTEM's model namespaces
   use App\Models\Client;    // Change to your ECOSYSTEM's Client model path
   use App\Models\Employee;  // Change to your ECOSYSTEM's Employee model path
   ```

2. **The following relationship methods have TODO comments:**
   - `client()` - Line ~68
   - `teamMembers()` - Line ~96
   - `deliveryOwner()` - Line ~104
   - `deliveryManager()` - Line ~109

3. **Example update (adjust namespace to match your ECOSYSTEM):**
   ```php
   // If ECOSYSTEM has models in a different namespace, update like this:
   use Ecosystem\Core\Models\Client;
   use Ecosystem\Core\Models\Employee;

   // Then the relationships will automatically use the correct models
   ```

### Expected Relationships
The `Project` model expects relationships with ECOSYSTEM's models:

```php
// In app/Models/Project.php
public function client()
{
    return $this->belongsTo(\App\Models\Client::class);
}

public function teamMembers()
{
    return $this->belongsToMany(\App\Models\Employee::class, 'project_employee')
        ->withPivot('assignment', 'start_date', 'end_date')
        ->withTimestamps();
}

public function deliveryOwner()
{
    return $this->belongsTo(\App\Models\Employee::class, 'delivery_owner_id');
}

public function deliveryManager()
{
    return $this->belongsTo(\App\Models\Employee::class, 'delivery_manager_id');
}
```

Ensure your ECOSYSTEM's `Client` and `Employee` models have inverse relationships if needed.

## Files Already Removed from This Project

The following files have been REMOVED from this standalone project because they already exist in ECOSYSTEM:

### ✅ Migrations DELETED:
- `2025_09_02_090220_create_clients_table.php` ✅
- `2025_09_11_070212_add_address_and_city_to_clients_table.php` ✅
- `2025_09_11_071714_add_client_code_to_clients_table.php` ✅
- `2025_09_12_034357_create_employees_table.php` ✅
- `2025_09_12_034434_create_employees_table.php` ✅
- `2025_09_12_061959_create_project_employee_table.php` ✅

### ✅ Controllers DELETED:
- `app/Http/Controllers/ClientController.php` ✅
- `app/Http/Controllers/EmployeeController.php` ✅

### ✅ Models DELETED:
- `app/Models/Client.php` ✅
- `app/Models/Employee.php` ✅

### ✅ Views DELETED:
- `resources/views/clients/` directory ✅
- `resources/views/employees/` directory ✅

### ✅ Routes REMOVED:
- Client resource routes ✅
- Employee resource routes ✅

> **Note:** These files and routes have been removed to prevent conflicts with ECOSYSTEM's existing client and employee management functionality.

## Database Structure

### Core Tables Created:
1. **projects** - Main project records
2. **project_phases** - Phase templates (Preparation, Blueprint, Realization, etc.)
3. **project_project_phase** - Project-phase relationships with weights
4. **project_planning** - Hierarchical planning structure (groups, activities)
5. **activity_stages** - Breakdown of activities into stages
6. **project_activities** - Activity templates and instances
7. **documents** - Project documents
8. **project_updates** - Project update logs

### Relationships:
- Projects → Client (ECOSYSTEM's clients table)
- Projects → Employees (ECOSYSTEM's employees table, many-to-many)
- Projects → Phases (many-to-many with pivot data)
- Planning → Hierarchical (parent-child structure)
- Stages → Activities (one-to-many)

## Features

### 1. Dynamic Phase Management
- Add/remove phases per project
- Reorder phases
- Toggle phase visibility
- Configure phase weights

### 2. Hierarchical Planning
- Multi-level groups
- Activities under stages
- Weight-based progress calculation
- Status tracking (Not Started, In Progress, Completed, etc.)

### 3. Multiple Views
- **Table View**: Hierarchical list with expand/collapse
- **Gantt View**: Timeline visualization
- **S-Curve View**: Progress curve analysis

### 4. Export Capabilities
- PDF export for all views
- Excel export for all views
- Formatted with colors and structure

### 5. Progress Tracking
- Automatic progress calculation based on weights
- Manual progress updates
- Status-based filtering
- Actual vs. planned dates tracking

## Troubleshooting

### Issue: Foreign key constraints fail
**Solution**: Ensure ECOSYSTEM has `clients` and `employees` tables before running migration.

### Issue: Routes conflict
**Solution**: Check for route name conflicts. All planning routes use `planning.*` prefix.

### Issue: Views not found
**Solution**: Ensure all blade files are copied to `resources/views/project-planning/`

### Issue: Class not found errors
**Solution**: Run `composer dump-autoload` after copying files.

## Support

For issues or questions, contact the development team.

## Version
- Module Version: 2.0
- ECOSYSTEM Integration: January 2026
- Laravel Version: 11.x
