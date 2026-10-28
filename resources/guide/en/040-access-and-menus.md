---
key: access-and-menus
chapter: getting-started
order: 40
title: Access and menus
summary: Why your menus differ from a colleague's, and what to do when a menu or button is missing.
requires: []
updated: 2026-10-27
---
Two people in the same company often see different menus in EcoSystem. This is by design: the menus follow your access. This topic explains how it works and what to do when something you need is missing.

## Who uses this

Everyone. Administrators who manage access can use it as a plain-language summary.

## How your menus are decided

- **Role and Menu Access.** An administrator gives each Role a list of pages and tabs. For each one the Role can have `View` (open the page) and, where it applies, `Create`, `Edit` and `Delete`. You may hold more than one role.
- **ESS Settings.** The self-service items under `My Workspace` (such as `My Attendance`, `Overtime` or `Paystub`) can also be switched on or off for everyone by an administrator.
- **Being an Approver.** If you are named as an Approver in any approval chain, `My Approvals` appears for you even if you have no other HR menu. You then see only the documents that concern you.
- **Employee type.** External consultants see a shorter set of self-service menus. Their pages are described in the External chapter.
- **My Profile tabs.** Each tab of `My Profile` follows its own access too. A tab marked `View Only` can be read but not changed. HR can also lock a verified profile, which makes the main tabs `View Only` until HR unlocks it.

## What you can and cannot do on a page

Buttons follow the same rules. If you can open a page but do not see `Create`, `Edit`, `Delete`, `Approve` or `Export`, your role has `View` but not that action.

## Step by step

### When a menu is missing

1. Refresh the page and check the `Search menu...` box in the sidebar. The menu may be folded inside a section.
2. Look in the `User Guide` for the topic. The guide shows only topics for menus you can open. If a topic is missing here too, the menu is not in your access.
3. Ask your HR or administrator for access. Tell them the exact menu name and why you need it.

### When a button is missing

1. Check that the request is still in a state where the action is allowed. For example, `Cancel` appears only while nobody has acted on the request.
2. Check whether the page says `View Only`.
3. If you still need the action, ask your administrator to add the permission to your role.

### When a self-service item you expect is not there

Self-service items can be turned off company-wide in `ESS Settings`. Ask HR whether the feature is in use for your company.

> [!NOTE]
> You cannot change your own access. Roles and Menu Access are managed by administrators.

## What happens next

After an administrator changes your access, sign out and sign in again if the menu still does not appear.

## Common questions

**A colleague sees `Payroll` and I do not.** `Payroll` is a Finance menu. Access follows the role, not the department name.

**I can see a menu but a page says I have no access.** The page or one of its tabs is not included in your role. Ask your administrator.

## Related topics

- [[navigating-ecosystem]]
- [[my-profile]]
- [[my-approvals]]
- [[welcome]]

<!-- sources: resources/views/partials/sidebar.blade.php, resources/views/management/roles/menu-access.blade.php, app/Http/Controllers/Management/EssSettingsController.php, config/external_ess.php, app/Http/Controllers/ProfileController.php, resources/views/master/employee/show.blade.php, app/Services/Approval/ApprovalEngine.php -->
