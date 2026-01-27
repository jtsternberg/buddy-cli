---
description: "Create a new release with changelog update and git tag"
argument-hint: "[version] - optional, auto-detected if omitted"
allowed-tools: Bash(git *), Bash(gh release *), Bash(composer test), Bash(composer lint), Read, Edit, AskUserQuestion
---

Create a new release. Version can be provided as $1, or auto-detected from commits.

## Steps

1. **Determine version**:
   - Get current version: `git describe --tags --abbrev=0 2>/dev/null || echo "v0.0.0"`
   - Get commits since last tag: `git log $(git describe --tags --abbrev=0 2>/dev/null || echo "")..HEAD --oneline`
   - If $1 provided, use that version
   - Otherwise, analyze commits to suggest version bump:
     - **MAJOR**: commits with "BREAKING", "breaking change", removed commands/options
     - **MINOR**: commits with "add", "new", "feature", "feat:"
     - **PATCH**: commits with "fix", "bug", "patch", "docs", "chore", or any other changes
   - Present the suggested version and let user confirm or override

2. **Run pre-release checks**:
   - `composer test` - All tests must pass
   - `composer lint` - Code must be formatted

3. **Generate changelog content**:
   - Analyze commits and categorize under: Added, Changed, Fixed, Removed
   - Format as markdown following Keep a Changelog format
   - Store this content as `$CHANGELOG_CONTENT` to reuse in CHANGELOG.md and GitHub release

4. **Update CHANGELOG.md**:
   - Add section `## [X.Y.Z] - YYYY-MM-DD` (use today's date)
   - Insert the generated changelog content ($CHANGELOG_CONTENT) from step 3

5. **Commit and tag**:
   ```bash
   git add CHANGELOG.md
   git commit -m "Prepare release vX.Y.Z"
   git tag vX.Y.Z
   ```

6. **Push** (ask for confirmation first):
   ```bash
   git push origin master --tags
   ```

7. **Create GitHub release** (optional, ask user):
   - Use the changelog content from step 3 as the release notes
   ```bash
   gh release create vX.Y.Z --title "vX.Y.Z" --notes "$CHANGELOG_CONTENT"
   ```

Reference: @RELEASE.md for version numbering guidelines.
