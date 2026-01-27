---
description: "Create a new release with changelog update and git tag"
argument-hint: "<version> (e.g., 1.1.0)"
allowed-tools: Bash(git *), Bash(gh release *), Bash(composer test), Bash(composer lint), Read, Edit
---

Create a release for version $1.

## Steps

1. **Validate version format** - Must be semver (X.Y.Z)

2. **Run pre-release checks**:
   - `composer test` - All tests must pass
   - `composer lint` - Code must be formatted

3. **Review unreleased changes** - Check git log since last tag:
   ```bash
   git log $(git describe --tags --abbrev=0)..HEAD --oneline
   ```

4. **Update CHANGELOG.md**:
   - Add section `## [$1] - YYYY-MM-DD` (use today's date)
   - Categorize changes under: Added, Changed, Fixed, Removed
   - Follow Keep a Changelog format

5. **Commit and tag**:
   ```bash
   git add CHANGELOG.md
   git commit -m "Prepare release v$1"
   git tag v$1
   ```

6. **Push** (ask for confirmation first):
   ```bash
   git push origin master --tags
   ```

7. **Create GitHub release** (optional, ask user):
   ```bash
   gh release create v$1 --title "v$1" --notes "See CHANGELOG.md for details."
   ```

Reference: @RELEASE.md for version numbering guidelines.
