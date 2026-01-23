#!/usr/bin/env python3
"""Parse execution JSON and output a readable status summary.

Usage: buddy executions:show <id> --pipeline=<id> --json | python format_status.py

Highlights failed actions with clear indicators.
"""

import json
import sys
from datetime import datetime


def parse_time(ts):
    """Parse ISO timestamp to datetime, return None if invalid."""
    if not ts:
        return None
    try:
        return datetime.fromisoformat(ts.replace("Z", "+00:00"))
    except (ValueError, AttributeError):
        return None


def format_duration(start, end):
    """Calculate and format duration between two timestamps."""
    start_dt = parse_time(start)
    end_dt = parse_time(end)
    if not start_dt or not end_dt:
        return "-"
    delta = end_dt - start_dt
    total_seconds = int(delta.total_seconds())
    if total_seconds < 60:
        return f"{total_seconds}s"
    minutes, seconds = divmod(total_seconds, 60)
    return f"{minutes}m {seconds}s"


def status_indicator(status):
    """Return visual indicator for status."""
    indicators = {
        "SUCCESSFUL": "✓",
        "FAILED": "✗",
        "INPROGRESS": "→",
        "ENQUEUED": "○",
        "SKIPPED": "⊘",
        "TERMINATED": "□",
    }
    return indicators.get(status, "?")


def main():
    try:
        data = json.load(sys.stdin)
    except json.JSONDecodeError as e:
        print(f"ERROR: Invalid JSON input: {e}", file=sys.stderr)
        sys.exit(1)

    # Handle both single execution and array
    if isinstance(data, list):
        if len(data) == 0:
            print("No executions found.")
            sys.exit(0)
        data = data[0]

    # Execution header
    exec_id = data.get("id", "Unknown")
    status = data.get("status", "UNKNOWN")
    branch = data.get("branch", {}).get("name", "-")
    revision = data.get("to_revision", {}).get("revision", "-")[:8]
    creator = data.get("creator", {}).get("name", "-")
    duration = format_duration(data.get("start_date"), data.get("finish_date"))

    print(f"Execution #{exec_id}")
    print(f"  Status:   {status_indicator(status)} {status}")
    print(f"  Branch:   {branch}")
    print(f"  Revision: {revision}")
    print(f"  Creator:  {creator}")
    print(f"  Duration: {duration}")
    print()

    # Actions summary
    actions = data.get("action_executions", [])
    if not actions:
        print("No actions in this execution.")
        sys.exit(0)

    failed = [a for a in actions if a.get("status") == "FAILED"]
    successful = [a for a in actions if a.get("status") == "SUCCESSFUL"]

    print(f"Actions: {len(successful)}/{len(actions)} passed")
    print()

    # List all actions with status
    for action in actions:
        name = action.get("action", {}).get("name", "Unknown")
        action_status = action.get("status", "UNKNOWN")
        action_duration = format_duration(action.get("start_date"), action.get("finish_date"))
        indicator = status_indicator(action_status)

        line = f"  {indicator} {name}"
        if action_duration != "-":
            line += f" ({action_duration})"
        print(line)

    # Highlight failures
    if failed:
        print()
        print("FAILED ACTIONS:")
        for action in failed:
            name = action.get("action", {}).get("name", "Unknown")
            action_type = action.get("action", {}).get("type", "-")
            print(f"  - {name} ({action_type})")


if __name__ == "__main__":
    main()
