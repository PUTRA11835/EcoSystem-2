---
key: inventory-assets
chapter: human-capital
order: 120
title: Inventory & Assets
summary: Keep the stock of supplies and the register of company assets, and see what needs attention.
requires: [general.inventory.overview, general.inventory.items, general.inventory.assets, general.inventory.settings]
updated: 2026-10-27
---
`Inventory & Assets` keeps two registers: consumable supplies with their stock, and company assets with one row per unit. An overview shows totals and what needs attention.

## Who uses this

GA and HR staff who manage supplies and equipment.

## Before you start

Open `Human Capital` › `Inventory & Assets`. It has up to four tabs, shown by access: `Overview`, `Inventory`, `Assets` and `Settings`. Buttons to add, edit or delete appear only when you have those permissions.

Set up the dropdown choices first in `Settings` so the forms offer the right categories, units and locations.

## Step by step

### Read the overview

`Overview` shows the totals and lists. Look at `Inventory by category` (value is unit price times quantity in stock), `Assets by category` (value is the purchase price), `Assets by status`, and `Needs attention`, which lists low or empty stock, damaged assets and assets under maintenance.

### Add a supply item

1. Open `Inventory` and select `New Inventory Item`.
2. Fill in `Item code`, `Item name`, `Category`, `Unit`, `Qty`, `Minimum stock` and `Unit price (Rp)`. Add a `Location`, `Status`, `Notes` and a photo if you want.
3. Save.

The list shows `Qty`, `Min. stock`, `Unit price` and `Stock value`. The status is `In stock`, `Low stock`, `Out of stock` or `Inactive`. It is worked out from the quantity unless you set it by hand.

### Add an asset

1. Open `Assets` and select `New Asset`.
2. Fill in `Asset code`, `Asset name`, `Category`, `Brand / make`, `Serial number`, `Assignee`, `Purchase date` and `Purchase price (Rp)`.
3. Set the `Condition`, `Asset status` and `Location`, and add `Notes`.
4. Save.

To hand an asset to someone, edit it and choose the `Assignee`. The list shows `Assignee`, `Condition` and `Status`. Search and filter with the funnel icons in the headers.

### Change the dropdown lists

1. Open `Settings` and choose a list: Inventory `Category` and `Unit`, Assets `Category`, `Condition` and `Status`, or `Location` (shared by both registers).
2. Select `Add Option`, enter a `Name` and a `Badge colour`, and save.
3. Use `Move up` and `Move down` to order options, switch an option off with `Active`, or delete one that is not used.

> [!NOTE]
> Renaming an option also renames it on every record that uses it.

## What happens next

Use the `Assignee` column to see which assets a person holds, for example when they leave, see [[offboarding]].

## Common questions

**Can I delete an item?** Yes, with the delete permission. Options in use by records cannot be deleted.

**Where are photos stored?** With the record, and only people with access to the tab can open them.

## Related topics

- [[offboarding]]
- [[employee-data]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/inventory/overview.blade.php, resources/views/hr-general/inventory/items.blade.php, resources/views/hr-general/inventory/assets.blade.php, resources/views/hr-general/inventory/settings.blade.php, resources/views/hr-general/inventory/components/asset-modal.blade.php, resources/views/hr-general/inventory/components/item-modal.blade.php, app/Models/InventoryOption.php -->
