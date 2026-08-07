# Security policy

## Supported versions

Security fixes are applied to the latest release and the `main` branch.

## Reporting a vulnerability

Please do not open a public issue containing a vulnerability, password, session
cookie, database export, access token, email list, or other personal data.
Instead, use GitHub's private vulnerability reporting feature for this
repository. Include reproduction steps and the affected commit or release, but
replace all real user information with test data.

## Data-handling expectations

- Database files, uploaded artwork, logs, environment files, and production
  secrets must never be committed.
- Production must use HTTPS and secure session cookies.
- Database and object-storage credentials must be supplied through environment
  variables managed by the hosting provider.
- Logs must not contain passwords, session IDs, CSRF tokens, uploaded file
  contents, or raw query parameters.
- Backups must be encrypted, access-controlled, and tested for restoration.
- Production access should use individual administrator accounts with
  multi-factor authentication.

The repository's controls reduce common risks, but a production deployment
still requires operational monitoring, backups, patching, and periodic security
review.
