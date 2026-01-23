# Skill Evaluations

Manual test scenarios for evaluating skill effectiveness, following [Anthropic's recommended evaluation-driven development](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices#build-evaluations-first).

## Usage

1. **Baseline**: Run prompts in Claude Code *without* the plugin loaded
2. **With skills**: Run the same prompts *with* the plugin (`--plugin-dir`)
3. **Compare**: Check if expected commands were invoked and workflow was complete

## Files

- `skill-evaluations.json` - Test scenarios with prompts and expected behaviors

## Example

```bash
# Baseline (no plugin)
claude
> Why did my build fail?

# With plugin
claude --plugin-dir /path/to/buddy-cli/claude-plugin
> Why did my build fail?
```

Compare whether the skill-enhanced response uses the expected commands (`executions:list`, `executions:failed`, etc.) and follows a better troubleshooting workflow.
