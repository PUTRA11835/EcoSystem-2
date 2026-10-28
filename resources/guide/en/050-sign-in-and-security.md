---
key: sign-in-and-security
chapter: getting-started
order: 50
title: Sign in and account security
summary: How to sign in, change your password, use two-step verification and keep your account safe.
requires: []
updated: 2026-10-28
---
This topic explains how to sign in to EcoSystem, where to change your password, what two-step verification is for, and the habits that keep your account safe.

## Who uses this

Everyone who signs in to EcoSystem.

## Before you start

- Have your own email or employee ID and your password ready. They belong to you alone.
- If your account uses two-step verification, keep your authenticator app on your phone close.

## Step by step

### Sign in

![The EcoSystem sign-in page with the email or employee ID field, the password field and the Sign In button](images/guide/sign-in-login.png)

*Screenshot with sample data.*

1. Open the EcoSystem address given by your company.
2. Enter your `Email or Employee ID` and your `Password`.
3. Tick `Keep me signed in` only on a computer that only you use.
4. Select `Sign In`.
5. If asked, enter the 6-digit code from your authenticator app and confirm.

The code changes every 30 seconds. If it is refused, wait for the next code and try again.

### If you forgot your password

1. On the sign-in page select `Forgot password?`.
2. Follow the instructions sent to your registered email.

### Change your password

![The Security tab in Settings with the password form, two-factor authentication and active sessions](images/guide/settings-security.png)

*Screenshot with sample data.*

1. Open `Settings` and choose the `Security` tab.
2. In the `Password` section enter the new password (at least 8 characters) and enter it again in `Confirm new password`.
3. Select `Update password`.

### Use two-step verification

1. In the same `Security` tab, find `Two-factor authentication`. It shows `Not configured` until you set it up.
2. Select `Enable 2FA` and follow the on-screen steps.
3. Save the recovery codes the page shows in a safe place that only you can open.

Some roles are required to use two-step verification. If yours is one of them, you are guided to set it up when you sign in.

### Check where you are signed in

The `Active sessions` section of the `Security` tab lists the sessions of your account. The line marked `Current session` is the browser you are using now.

> [!IMPORTANT]
> Never share your password or your verification code with anyone, including colleagues, your supervisor or someone who says they are from IT. Do not write them in chat, email or ticket notes.

> [!TIP]
> Sign out when you leave a shared computer.

## What happens next

Repeated failed sign-in attempts lock the account for a short time to protect it. Wait for the lock to end, or ask an administrator if you are still blocked.

## Common questions

**I do not receive a verification code.** The code comes from your authenticator app, not from email or SMS. Check that the time on your phone is correct, then try the next code.

**I think someone else used my account.** Change your password at once and tell an administrator.

<!-- sources: resources/views/auth/login.blade.php, resources/views/settings/index.blade.php, routes/web.php -->
