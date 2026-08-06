# Sprint 1 — Authentication and RBAC

## Delivered

- Username/email authentication with generic failure responses.
- Login-attempt tracking and configurable brute-force lockout.
- Database-backed sessions with idle and absolute expiry.
- Periodic PHP session ID rotation.
- Optional User-Agent session binding.
- Explicit session revocation on logout, user disable, and password reset.
- Strong password policy shared by user creation, reset, and self-service change.
- Forced password change for newly created users and administrator resets.
- Self-service password change that revokes all other active sessions.
- Company-scoped roles and permissions.
- Protection against modifying Super Admin accounts without Super Admin authority.
- Protection against disabling or demoting the last active Super Admin.
- Audit events for login success/failure/blocking, logout, password changes, users, and roles.

## New Environment Settings

```dotenv
SESSION_ABSOLUTE_LIFETIME_MINUTES=720
SESSION_ROTATE_MINUTES=30
SESSION_BIND_USER_AGENT=true
PASSWORD_MIN_LENGTH=12
```

## Deployment

1. Back up the database and project folder.
2. Copy the updated source over the existing project.
3. Merge the new `.env.example` settings into `.env`.
4. Run `php bin/migrate.php` from the project root.
5. Sign out and sign in again so a hardened session record is created.

## Acceptance Tests

1. Valid active user can sign in with username or email.
2. Five failed attempts (default) block further attempts for 15 minutes.
3. Inactive users cannot sign in.
4. New users are redirected to `/account/password` after first login.
5. Administrator password reset revokes existing sessions and forces password change.
6. Passwords shorter than 12 characters or missing character classes are rejected.
7. Changing password signs out all other sessions but preserves the current session.
8. A non-Super Admin cannot assign or modify a Super Admin.
9. The last active Super Admin cannot be disabled or demoted.
10. Users without the required permission receive HTTP 403.
11. Invalid CSRF tokens receive HTTP 419.
12. Logout revokes the database session and removes the browser cookie.

## Recommended Next Sprint

- Dedicated middleware pipeline for authentication and permissions.
- Active-session management screen with “sign out other devices”.
- Password history and configurable expiration.
- Two-factor authentication for Super Admin and Owner accounts.
- Audit log viewer with filtering and export.
