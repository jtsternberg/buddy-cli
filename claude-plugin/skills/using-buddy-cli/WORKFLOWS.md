# buddy-cli Workflows

Common workflow patterns for CI/CD automation.

## Initial Setup

```bash
# 1. Set token (from https://app.buddy.works/api-tokens)
buddy config:set token <your-token>

# 2. Set default workspace and project
buddy config:set workspace my-workspace
buddy config:set project my-project

# 3. Verify configuration
buddy config:show
buddy pipelines:list  # Test connectivity
```

## Deploy to Production

```bash
# Find production pipeline
buddy pipelines:list | grep -i prod

# Run deployment and wait
buddy pipelines:run <id> --wait

# Check result
buddy executions:list --pipeline=<id>
```

## Monitor a Deployment

```bash
# Start deployment in background
buddy pipelines:run <id>

# Poll for status
watch -n 10 "buddy executions:list --pipeline=<id> --json | head -1"

# When done, check details
buddy executions:show <exec-id> --pipeline=<id>
```

## Handle a Failure

```bash
# 1. Check what failed
buddy executions:failed <exec-id> --pipeline=<id>

# 2. Review full logs if needed
buddy executions:show <exec-id> --pipeline=<id> --logs

# 3. After fixing: retry
buddy pipelines:retry <id>
```

## Branch Deployment

```bash
# Deploy specific branch
buddy pipelines:run <id> --branch=feature/my-feature --wait

# Deploy with custom variables
buddy pipelines:run <id> --var ENV=staging --var DEBUG=true
```

## Pipeline Configuration Management

### Export pipeline config
```bash
buddy pipelines:get <id> > pipeline.yaml
```

### Create pipeline from config
```bash
buddy pipelines:create pipeline.yaml
```

### Update existing pipeline
```bash
buddy pipelines:update <id> pipeline.yaml
```

## Environment Variables

### View all variables
```bash
buddy vars:list
```

### Set encrypted variable
```bash
buddy vars:set API_SECRET "sensitive-value" --encrypted
```

### Scope to pipeline
```bash
buddy vars:set TEST_VAR "value" --pipeline=<id>
```

## Automation Patterns

### Run deployment on merge (in CI)
```bash
#!/bin/bash
if [ "$GITHUB_REF" = "refs/heads/main" ]; then
  buddy pipelines:run <prod-pipeline-id> --wait
fi
```

### Status check script
```bash
#!/bin/bash
STATUS=$(buddy executions:list --pipeline=<id> --json | jq -r '.[0].status')
if [ "$STATUS" = "FAILED" ]; then
  echo "Last execution failed"
  exit 1
fi
```

### Retry with backoff
```bash
#!/bin/bash
for i in 1 2 3; do
  buddy pipelines:retry <id> && exit 0
  sleep $((i * 30))
done
echo "All retries failed"
exit 1
```
