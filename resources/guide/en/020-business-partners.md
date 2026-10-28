---
key: business-partners
chapter: business-legal
order: 20
title: Business Partner
summary: Find, create and maintain customers and vendors, and organize them into customer groups.
requires: [master.customer]
updated: 2026-10-27
---
Business Partner is the master list of your customers and vendors. Each partner has a profile with address, contacts, identification, bank account and attachments, and can be placed in a customer group.

## Who uses this

Business, Finance and administrative staff who maintain customer and vendor data. Which profile tabs you see, and whether you can edit them, depends on your access. If a tab is missing or read-only, ask an administrator.

> [!NOTE]
> The `Business Dev` menu exists but only shows a "Coming Soon" page, so it is not covered in this guide.

## Step by step

### Find a business partner

1. Open `Business & Legal` › `Business Partner`.
2. Type an email or company name in the search box.
3. Narrow the list with `Status` (`Active` or `Blocked`), `Type` (`Customer` or `Vendor`) or `Customer Group`.
4. Click a row to open the profile. A partner flagged for deletion is shown as `Flagged for Deletion`.

### Create a business partner

1. Click `Create Business Partner`.
2. Fill in the general data, such as `Company Name`, the type (`Customer` or `Vendor`), `Company Email`, `Industry Sector` and language.
3. Fill in the address. Choose the country, region, city and district in order. You can also link a `Parent Customer` (only a partner of the same type) and a `Customer Group`.
4. Click `Create`. The new partner opens in the list.

Only the essential fields are asked here. Complete the rest on the profile.

### Complete the profile

1. Open the partner.
2. Move between the tabs `Basic Data`, `Address`, `Contact Person`, `Identification`, `Bank Account`, `Credential` and `Attachment`.
3. Edit the fields and save. Tabs you may view but not edit are shown locked.

### Use customer groups

1. Click `View Grouping` on the list to open `Customer Grouping`.
2. Create a group with a group name, or edit and delete groups using the icons on each group.
3. Use the member icon to add partners to a group. Partners in the same group are shown together.

## What happens next

The partner appears in the list and can be filtered by type, status and group. Changes are visible to everyone with access to this page.

## Common questions

**Why can't I choose a parent?** A parent must be a business partner of the same type.

**Who can change or remove a partner?** Only people with the matching access. If you cannot, ask an administrator.

## Related topics

- [[employee-data]]
- [[legal-basis]]

<!-- sources: routes/web.php, routes/api.php, app/Http/Controllers/CustomerController.php, resources/views/master/customer/index.blade.php, resources/views/master/customer/show.blade.php, resources/views/master/customer/grouping.blade.php -->
