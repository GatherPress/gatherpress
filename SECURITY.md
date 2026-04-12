# Security Policy

## Supported Versions

We release patches for security vulnerabilities only for the current version:

| Version | Supported          |
| ------- | ------------------ |
| 0.33.x  | :white_check_mark: |
| < 0.33  | :x:                |

**We strongly recommend always using the latest stable version of GatherPress.** Security updates are only provided for the current release.

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you discover a security vulnerability within GatherPress, please send an email to [plugin@gatherpress.org](mailto:plugin@gatherpress.org). All security vulnerabilities will be promptly addressed.

### What to Include

When reporting a vulnerability, please include:

- Type of issue (e.g., SQL injection, XSS, authentication bypass, etc.)
- Full paths of source file(s) related to the manifestation of the issue
- The location of the affected source code (tag/branch/commit or direct URL)
- Any special configuration required to reproduce the issue
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

### Response Timeline

- **Initial Response**: Within 48 hours, we will acknowledge receipt of your vulnerability report
- **Status Update**: Within 7 days, we will send a more detailed response indicating the next steps
- **Fix Timeline**: We aim to release a security patch within 30 days of the initial report, depending on complexity
- **Disclosure**: We follow responsible disclosure practices and will coordinate with you on the disclosure timeline

## Security Update Policy

- Security updates are released as soon as possible after a vulnerability is confirmed
- Critical vulnerabilities receive immediate attention and emergency releases
- Security releases will be clearly marked in release notes
- Users will be notified through the WordPress.org plugin repository update system

## Security Best Practices

When using GatherPress, we recommend:

1. **Keep Updated**: Always run the latest version of GatherPress
2. **WordPress Core**: Keep WordPress core updated to the latest version
3. **PHP Version**: Use a supported PHP version (7.4 or higher recommended)
4. **Access Control**: Limit administrative access to trusted users only
5. **Backups**: Maintain regular backups of your WordPress site
6. **HTTPS**: Always use HTTPS for production sites
7. **File Permissions**: Follow WordPress recommended file permission settings

## Known Security Considerations

### Data Sanitization

GatherPress follows WordPress coding standards for:

- Input sanitization using WordPress sanitization functions
- Output escaping for all user-generated content
- Prepared statements for all database queries
- Nonce verification for all form submissions

### User Permissions

GatherPress respects WordPress capability checks:

- Event management requires appropriate WordPress capabilities
- RSVP functionality is restricted based on event settings
- Administrative functions require administrator capabilities

## Third-Party Dependencies

GatherPress uses the following third-party libraries:

- WordPress block editor packages (maintained by WordPress core team)
- Leaflet for map functionality
- Moment.js for date/time handling

We monitor these dependencies for security updates and update them promptly when security patches are released.

## Security Testing

GatherPress undergoes:

- Automated security scanning via SonarCloud
- Code quality checks with PHPStan and ESLint
- Manual security reviews for all major releases
- Community security audits through open-source collaboration

## Contact

For security concerns, please email: [plugin@gatherpress.org](mailto:plugin@gatherpress.org)

For general questions, please use our [GitHub Issues](https://github.com/GatherPress/gatherpress/issues).

## Attribution

We appreciate the security research community and will acknowledge security researchers who responsibly disclose vulnerabilities (unless you prefer to remain anonymous).
