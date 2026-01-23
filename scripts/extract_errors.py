#!/usr/bin/env python3
"""Extract and summarize errors from execution logs.

Usage: buddy executions:show <id> --pipeline=<id> --logs --json | python extract_errors.py

Parses execution output for common error patterns and provides a summary.
"""

import json
import re
import sys
from collections import defaultdict


# Common error patterns to look for in logs
ERROR_PATTERNS = [
    # General errors
    (r"(?i)\b(error|fatal|exception):\s*(.+)", "Error"),
    (r"(?i)^\s*Error:\s*(.+)", "Error"),
    # Exit codes
    (r"exit(?:ed)?\s+(?:with\s+)?(?:code\s+)?(\d+)", "Exit Code"),
    (r"return(?:ed)?\s+(?:code\s+)?(\d+)", "Return Code"),
    # Build failures
    (r"(?i)build\s+failed", "Build Failed"),
    (r"(?i)compilation\s+(?:error|failed)", "Compilation Error"),
    # Test failures
    (r"(?i)(\d+)\s+(?:test[s]?\s+)?failed", "Test Failures"),
    (r"(?i)FAIL[ED]?\s+(.+)", "Test Failed"),
    # Dependency issues
    (r"(?i)(?:could\s+not\s+(?:find|resolve)|missing)\s+(?:dependency|package|module)\s*:?\s*(.+)", "Missing Dependency"),
    (r"(?i)npm\s+ERR!\s*(.+)", "NPM Error"),
    (r"(?i)composer\s+(?:error|failed)", "Composer Error"),
    # Permission/access issues
    (r"(?i)permission\s+denied", "Permission Denied"),
    (r"(?i)access\s+denied", "Access Denied"),
    (r"(?i)authentication\s+(?:failed|error|required)", "Auth Error"),
    # Network issues
    (r"(?i)connection\s+(?:refused|timed?\s*out|failed)", "Connection Error"),
    (r"(?i)timeout\s+(?:exceeded|error)", "Timeout"),
    # Resource issues
    (r"(?i)out\s+of\s+memory", "Out of Memory"),
    (r"(?i)disk\s+(?:full|space)", "Disk Space"),
]


def extract_errors_from_log(log_lines):
    """Extract errors from log lines using pattern matching."""
    errors = []
    for line in log_lines:
        for pattern, category in ERROR_PATTERNS:
            match = re.search(pattern, line)
            if match:
                # Get the matched detail or the full line
                detail = match.group(1) if match.groups() else line.strip()
                errors.append({
                    "category": category,
                    "detail": detail[:200],  # Truncate long messages
                    "line": line.strip()[:300]
                })
                break  # Only match first pattern per line
    return errors


def main():
    try:
        data = json.load(sys.stdin)
    except json.JSONDecodeError as e:
        print(f"ERROR: Invalid JSON input: {e}", file=sys.stderr)
        sys.exit(1)

    # Handle array or single object
    if isinstance(data, list):
        if len(data) == 0:
            print("No execution data found.")
            sys.exit(0)
        data = data[0]

    actions = data.get("action_executions", [])
    failed_actions = [a for a in actions if a.get("status") == "FAILED"]

    if not failed_actions:
        print("No failed actions found.")
        sys.exit(0)

    all_errors = defaultdict(list)

    for action in failed_actions:
        action_name = action.get("action", {}).get("name", "Unknown")
        log = action.get("log", [])

        if not log:
            all_errors["No Logs"].append({
                "action": action_name,
                "detail": "Action failed but no logs available"
            })
            continue

        errors = extract_errors_from_log(log)

        if not errors:
            # No patterns matched, get last few lines as context
            last_lines = log[-5:] if len(log) >= 5 else log
            all_errors["Unidentified"].append({
                "action": action_name,
                "detail": "\n".join(last_lines)
            })
        else:
            for error in errors:
                all_errors[error["category"]].append({
                    "action": action_name,
                    "detail": error["detail"]
                })

    # Output summary
    print("ERROR SUMMARY")
    print("=" * 40)

    for category, errors in sorted(all_errors.items()):
        print(f"\n{category} ({len(errors)} occurrence{'s' if len(errors) > 1 else ''}):")
        seen_details = set()
        for error in errors:
            # Deduplicate similar errors
            key = (error["action"], error["detail"][:50])
            if key in seen_details:
                continue
            seen_details.add(key)
            print(f"  [{error['action']}] {error['detail']}")

    print()
    print("FAILED ACTIONS:")
    for action in failed_actions:
        name = action.get("action", {}).get("name", "Unknown")
        action_type = action.get("action", {}).get("type", "-")
        print(f"  - {name} ({action_type})")


if __name__ == "__main__":
    main()
