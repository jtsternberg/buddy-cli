#!/bin/bash
# Validates buddy-cli configuration is complete and ready for use.
# Exits 0 if valid, non-zero with helpful message if invalid.

set -e

# Check for BUDDY_TOKEN environment variable
if [ -z "$BUDDY_TOKEN" ]; then
    # Check if token is set via config
    TOKEN_VIA_CONFIG=$(./bin/buddy config:show 2>/dev/null | grep -c "token:" || true)
    if [ "$TOKEN_VIA_CONFIG" -eq 0 ]; then
        echo "ERROR: BUDDY_TOKEN is not set."
        echo ""
        echo "Set it via environment variable:"
        echo "  export BUDDY_TOKEN=<your-token>"
        echo ""
        echo "Or via config:"
        echo "  buddy config:set token <your-token>"
        echo ""
        echo "Get a token at: https://app.buddy.works/api-tokens"
        exit 1
    fi
fi

# Check for workspace configuration
WORKSPACE=$(./bin/buddy config:show 2>/dev/null | grep "workspace:" | awk '{print $2}' || true)
if [ -z "$WORKSPACE" ] && [ -z "$BUDDY_WORKSPACE" ]; then
    echo "ERROR: No workspace configured."
    echo ""
    echo "Set via environment variable:"
    echo "  export BUDDY_WORKSPACE=<workspace-name>"
    echo ""
    echo "Or via config:"
    echo "  buddy config:set workspace <workspace-name>"
    echo ""
    echo "Or create .buddy-cli.json in your project root with:"
    echo '  {"workspace": "<workspace-name>"}'
    exit 1
fi

# Check for project configuration
PROJECT=$(./bin/buddy config:show 2>/dev/null | grep "project:" | awk '{print $2}' || true)
if [ -z "$PROJECT" ] && [ -z "$BUDDY_PROJECT" ]; then
    echo "WARNING: No project configured (may be required for some commands)."
    echo ""
    echo "Set via environment variable:"
    echo "  export BUDDY_PROJECT=<project-name>"
    echo ""
    echo "Or via config:"
    echo "  buddy config:set project <project-name>"
fi

echo "Configuration valid."
exit 0
