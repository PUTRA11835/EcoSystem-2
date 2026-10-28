---
key: master-employee-settings
chapter: administration
order: 40
title: Master Employee Settings
summary: Manage the dropdown options and data limits used by each tab of the employee profile.
requires: [management.employee, management.employee.basic-data, management.employee.address, management.employee.identification, management.employee.family, management.employee.education, management.employee.qualification, management.employee.contract, management.employee.bank, management.employee.payment, management.employee.attachment]
updated: 2026-10-27
---
Master Employee Settings controls the choices that employee forms offer (for example titles, religions, countries, document types or banks) and the limits the data is saved under (for example maximum lengths). One page exists for each tab of the employee profile. This topic is for administrators and HR.

## Who uses this

Administrators and HR data owners. Under `Management` › `Employee` you see one entry per tab, such as `Basic Data`, `Address`, `Identification`, `Family`, `Education`, `Qualification`, `Contract`, `Bank`, `Payment` and `Attachment`. You see only the tabs your role allows. `View` opens a tab, `Create` adds an option, `Edit` changes options and rules, and `Delete` removes an option.

## Before you start

- Decide what the option should be called before you add it. A name that is saved with employee records is hard to tidy later.
- Position, department and other organisation lists are master data of their own and are not managed here.

## Step by step

### Add an option

1. Open `Management` › `Employee` and choose the tab.
2. Find the list, for example `Titles` or `Countries`.
3. Under `Add an option`, type the label shown to users. If the page asks for `Saved as`, enter the value saved with the record.
4. Confirm to add it. The option appears in the list.

### Change, switch off or remove an option

1. Edit the label and click the save icon (`Save ...`) on the row.
2. Use the move controls on a row to change the order in the dropdown.
3. To stop offering an option, switch it off.
4. To remove it, use the delete icon. An option that employee records already use cannot be deleted. The `Used by` column shows how many records hold it, so switch it off instead.

### Change the rules

1. Scroll to the limits and switches of the tab, such as maximum lengths or whether something is required.
2. Change a value inside the allowed range.
3. Click `Save changes`.

A `Strict list` switch makes the system refuse a value that is not on the list. Leave it off for free-typed fields, so imported records with other values still save.

> [!WARNING]
> Renaming or removing options changes what every employee form offers. Existing records keep the value they were saved with. Test a change on one employee first.

## What happens next

Forms and imports use the new options and limits immediately. For example, a new bank appears in the bank dropdown of the employee form.

## Common questions

**Why can't I delete an option?** Employee records use it. Switch it off.

**Why do I not see a tab?** Each tab has its own access, ask an administrator.

## Related topics

- [[employee-data]]
- [[roles-and-menu-access]]
- [[my-profile]]

<!-- sources: routes/web.php, app/Support/Management/EmployeeMasterSettings.php, app/Http/Controllers/Management/EmployeeSettingsController.php, resources/views/management/employee/settings.blade.php, resources/views/management/employee/components/settings-panel.blade.php -->
