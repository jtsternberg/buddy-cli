# Release Process

## Quick Start

Use the `/release` command in Claude Code to automate the release process:

```
/release
```

The command auto-detects the appropriate version bump by analyzing commits since the last tag, runs tests, updates the changelog, and creates the git tag.

## Version Numbering

This project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html):

- **MAJOR** (x.0.0): Breaking changes to CLI commands, options, or output formats
- **MINOR** (0.x.0): New commands, options, or features (backwards compatible)
- **PATCH** (0.0.x): Bug fixes, documentation updates (backwards compatible)

### Examples

| Change | Version Bump |
|--------|--------------|
| Remove or rename a command | MAJOR |
| Change required arguments | MAJOR |
| Change JSON output structure | MAJOR |
| Add new command | MINOR |
| Add new option to existing command | MINOR |
| Add new output format | MINOR |
| Fix bug in existing command | PATCH |
| Update documentation | PATCH |
| Update dependencies (no API change) | PATCH |

## Manual Release Steps

For reference, or if not using Claude Code:

1. **Update CHANGELOG.md**
   - Add new section: `## [X.Y.Z] - YYYY-MM-DD`
   - Document all changes under appropriate headers (Added, Changed, Fixed, Removed)
   - Follow [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format

2. **Commit the changelog**
   ```bash
   git add CHANGELOG.md
   git commit -m "Prepare release vX.Y.Z"
   ```

3. **Create and push the tag**
   ```bash
   git tag vX.Y.Z
   git push origin master --tags
   ```

4. **Create GitHub release** (optional)
   ```bash
   gh release create vX.Y.Z --title "vX.Y.Z" --notes-file - <<< "See [CHANGELOG.md](CHANGELOG.md) for details."
   ```

## Pre-release Checklist

- [ ] All tests pass (`composer test`)
- [ ] Code is formatted (`composer lint`)
- [ ] CHANGELOG.md is updated
- [ ] Version bump is appropriate for changes
