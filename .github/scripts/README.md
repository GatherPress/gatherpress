# Coverage Check Scripts

This directory contains scripts for checking and validating test coverage on Pull Requests.

## Overview

The PR coverage check system ensures that new code changes meet minimum test coverage requirements without blocking on legacy code that may have lower coverage.

### Key Features

- **Checks only changed files** in Pull Requests (not entire codebase)
- **Shows uncovered line numbers** so engineers know exactly what needs testing
- **Centralized configuration** keeps PHPUnit, SonarCloud, and GitHub Actions in sync
- **Exemptions for special cases** like multisite-specific code

## Files

### `coverage-config.json`

Centralized configuration file that defines:
- `minCoverage`: Minimum required coverage percentage (default: 80%)
- `excludePatterns`: Files/directories to exclude from coverage checks
- `allowLowCoverage`: Files allowed to have coverage below the minimum
- `allowLowCoverageReason`: Explanation for why certain files are exempt

### `check-pr-coverage.php`

Main script that:
1. Reads the centralized configuration
2. Gets list of PHP files changed in the PR (from git diff)
3. Parses `build/logs/clover.xml` coverage report
4. Shows coverage percentage and uncovered line numbers for each file
5. Exits with error code if any files don't meet minimum coverage

### `validate-coverage-config.php`

Validation script that ensures coverage exclusions are consistent across:
- `.github/coverage-config.json` (centralized config)
- `phpunit.xml.dist` (PHPUnit configuration)
- `sonar-project.properties` (SonarCloud configuration)

## Usage

### Testing Locally

1. **Run PHP unit tests with coverage:**
   ```bash
   npm run test:unit:php
   ```

2. **Validate coverage configuration:**
   ```bash
   php .github/scripts/validate-coverage-config.php
   ```

3. **Check coverage for your changes:**
   ```bash
   # Make sure you have changes committed in a branch
   php .github/scripts/check-pr-coverage.php
   ```

### Example Output

When a file doesn't meet minimum coverage, you'll see:

```
❌ includes/core/classes/class-example.php - 75.0% coverage (45/60 lines)
   Uncovered lines: 12-15, 23, 45-52, 67-70
```

This tells you:
- **File name**: The specific file that needs more tests
- **Current coverage**: 75% (45 out of 60 lines covered)
- **Uncovered lines**: Exactly which line numbers need test coverage

### GitHub Actions

The workflow runs automatically on Pull Requests and:
1. Sets up PHP and Node.js environment
2. Installs dependencies
3. Starts WordPress test environment
4. Runs PHP unit tests with coverage
5. Validates configuration consistency
6. Checks coverage on changed files only
7. Reports results with uncovered line numbers

## Configuration

### Adding Exclusions

To exclude a file or directory from coverage checks, add it to `.github/coverage-config.json`:

```json
{
  "excludePatterns": [
    "*/vendor/*",
    "*/test/*",
    "*/your-new-exclusion/*"
  ]
}
```

### Allowing Low Coverage Exceptions

For files that cannot reasonably meet the minimum coverage (e.g., multisite-specific code):

```json
{
  "allowLowCoverage": [
    "includes/core/classes/class-setup.php",
    "includes/core/classes/class-your-special-file.php"
  ],
  "allowLowCoverageReason": "Contains multisite-specific code that cannot be tested in single-site environment"
}
```

### Changing Minimum Coverage

To adjust the required coverage percentage:

```json
{
  "minCoverage": 85
}
```

## Troubleshooting

### "No coverage data found" Error

This means the file is not included in the coverage report. Possible causes:
- File was excluded in `phpunit.xml.dist`
- No tests exist for the file yet
- File is new and hasn't been added to test suite

### "Coverage file not found" Error

Run the tests first to generate the coverage report:
```bash
npm run test:unit:php
```

### Validation Warnings

The validation script may show informational warnings about patterns not being in SonarCloud configuration. This is expected and doesn't indicate a problem.

**PHPUnit Include-Only Approach**: GatherPress uses an include-only approach in `phpunit.xml.dist` (only includes `./includes/core/classes`). This means all exclusions are implicit - files outside this directory are automatically excluded. The validation script recognizes this and skips checking for explicit exclusions.

## Best Practices

1. **Run tests locally** before pushing to ensure coverage is adequate
2. **Check validation** to ensure configurations stay in sync
3. **Review uncovered lines** to determine what tests are needed
4. **Add meaningful tests** rather than just hitting the coverage threshold
5. **Update allowLowCoverage** sparingly and only with good justification
