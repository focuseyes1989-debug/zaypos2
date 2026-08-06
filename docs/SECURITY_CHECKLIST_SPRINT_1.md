# Sprint 1 Security Checklist

- [x] Prepared statements are used for authentication and RBAC queries.
- [x] Passwords use PHP `password_hash()` and `password_verify()`.
- [x] Password hashes are automatically rehashed when required.
- [x] Session cookies are HttpOnly and SameSite configurable.
- [x] Secure cookies can be enabled for HTTPS deployments.
- [x] Session IDs rotate after login and periodically during use.
- [x] Idle and absolute session expiration are enforced.
- [x] Server-side session records can be revoked.
- [x] CSRF protection covers state-changing authentication and admin actions.
- [x] Login failure responses do not reveal whether an account exists.
- [x] Login attempts and security events are audited.
- [x] Company scope is enforced in user and role administration.
- [x] Last-Super-Admin safeguards protect administrative continuity.
