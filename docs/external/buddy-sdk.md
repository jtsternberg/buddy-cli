# Buddy Works PHP SDK Reference

## Installation

```bash
composer require buddy-works/buddy-works-php-api
```

## Compatibility

| PHP version | SDK version |
| ----------- | ----------- |
| ^8.0        | 1.4         |
| ^7.3        | 1.3         |
| ^7.2        | 1.2         |
| 5.5         | 1.1         |

## Initialization

### Using Access Token (Direct Token)

```php
use Buddy\Buddy;

$buddy = new Buddy([
	'accessToken' => 'your-token-here',
]);
```

Generate tokens at [API Tokens](https://app.buddy.works/api-tokens).

### Using OAuth

```php
$buddy = new Buddy([
	'clientId' => 'your-client-id',
	'clientSecret' => 'your-client-secret'
]);

// Get authorization URL
$url = $buddy->getOAuth()->getAuthorizeUrl($scopes, $state, $redirectUrl);

// Exchange code for access token
$auth = $buddy->getOAuth()->getAccessToken($state);
```

OAuth scopes: See [Buddy OAuth Docs](https://buddy.works/api/reference/getting-started/oauth#supported-scopes)

## API Methods

### Workspaces

```php
$buddy->getApiWorkspaces()->getWorkspaces();
$buddy->getApiWorkspaces()->getWorkspace($domain);
```

### Projects

```php
$buddy->getApiProjects()->getProjects($domain, $filters);
$buddy->getApiProjects()->addProject($data, $domain);
$buddy->getApiProjects()->getProject($domain, $projectName);
$buddy->getApiProjects()->editProject($data, $domain, $projectName);
$buddy->getApiProjects()->deleteProject($domain, $projectName);

// Project Members
$buddy->getApiProjects()->getProjectMembers($domain, $projectName, $filters);
$buddy->getApiProjects()->addProjectMember($domain, $projectName, $userId, $permissionId);
$buddy->getApiProjects()->getProjectMember($domain, $projectName, $userId);
$buddy->getApiProjects()->editProjectMember($domain, $projectName, $userId, $permissionId);
$buddy->getApiProjects()->deleteProjectMember($domain, $projectName, $userId);
```

### Pipelines

```php
$buddy->getApiPipelines()->getPipelines($domain, $projectName, $filters);
$buddy->getApiPipelines()->addPipeline($data, $domain, $projectName);
$buddy->getApiPipelines()->getPipeline($domain, $projectName, $pipelineId);
$buddy->getApiPipelines()->editPipeline($data, $domain, $projectName, $pipelineId);
$buddy->getApiPipelines()->deletePipeline($domain, $projectName, $pipelineId);

// Pipeline Actions
$buddy->getApiPipelines()->getPipelineActions($domain, $projectName, $pipelineId);
$buddy->getApiPipelines()->addPipelineAction($data, $domain, $projectName, $pipelineId);
$buddy->getApiPipelines()->getPipelineAction($domain, $projectName, $pipelineId, $actionId);
$buddy->getApiPipelines()->editPipelineAction($data, $domain, $projectName, $pipelineId, $actionId);
$buddy->getApiPipelines()->deletePipelineAction($domain, $projectName, $pipelineId, $actionId);
```

### Executions

```php
$buddy->getApiExecutions()->getExecutions($domain, $projectName, $pipelineId, $filters);
$buddy->getApiExecutions()->getExecution($domain, $projectName, $pipelineId, $executionId);
$buddy->getApiExecutions()->runExecution($data, $domain, $projectName, $pipelineId);
$buddy->getApiExecutions()->cancelExecution($domain, $projectName, $pipelineId, $executionId);
$buddy->getApiExecutions()->retryRelease($domain, $projectName, $pipelineId, $executionId);
```

### Branches

```php
$buddy->getApiBranches()->getBranches($domain, $projectName);
$buddy->getApiBranches()->getBranch($domain, $projectName, $name);
$buddy->getApiBranches()->addBranch($data, $domain, $projectName);
$buddy->getApiBranches()->deleteBranch($domain, $projectName, $name, $force);
```

### Commits

```php
$buddy->getApiCommits()->getCommits($domain, $projectName, $filters);
$buddy->getApiCommits()->getCommit($domain, $projectName, $revision);
$buddy->getApiCommits()->getCompare($domain, $projectName, $base, $head, $filters);
```

### Tags

```php
$buddy->getApiTags()->getTags($domain, $projectName);
$buddy->getApiTags()->getTag($domain, $projectName, $name);
```

### Source

```php
$buddy->getApiSource()->getContents($domain, $projectName, $path, $filters);
$buddy->getApiSource()->addFile($data, $domain, $projectName);
$buddy->getApiSource()->editFile($data, $domain, $projectName, $path);
$buddy->getApiSource()->deleteFile($data, $domain, $projectName, $path);
```

### Members

```php
$buddy->getApiMembers()->getWorkspaceMembers($domain, $filters);
$buddy->getApiMembers()->addWorkspaceMember($domain, $email);
$buddy->getApiMembers()->getWorkspaceMember($domain, $userId);
$buddy->getApiMembers()->editWorkspaceMember($domain, $userId, $isAdmin);
$buddy->getApiMembers()->deleteWorkspaceMember($domain, $userId);
$buddy->getApiMembers()->getWorkspaceMemberProjects($domain, $userId, $filters);
```

### Groups

```php
$buddy->getApiGroups()->getGroups($domain);
$buddy->getApiGroups()->addGroup($data, $domain);
$buddy->getApiGroups()->getGroup($domain, $groupId);
$buddy->getApiGroups()->editGroup($data, $domain, $groupId);
$buddy->getApiGroups()->deleteGroup($domain, $groupId);

// Group Members
$buddy->getApiGroups()->getGroupMembers($domain, $groupId);
$buddy->getApiGroups()->addGroupMember($domain, $groupId, $userId);
$buddy->getApiGroups()->getGroupMember($domain, $groupId, $userId);
$buddy->getApiGroups()->deleteGroupMember($domain, $groupId, $userId);
```

### Permissions

```php
$buddy->getApiPermissions()->getWorkspacePermissions($domain);
$buddy->getApiPermissions()->addWorkspacePermission($data, $domain);
$buddy->getApiPermissions()->getWorkspacePermission($domain, $permissionId);
$buddy->getApiPermissions()->editWorkspacePermission($data, $domain, $permissionId);
$buddy->getApiPermissions()->deleteWorkspacePermission($domain, $permissionId);
```

### Webhooks

```php
$buddy->getApiWebhooks()->getWebhooks($domain);
$buddy->getApiWebhooks()->addWebhook($data, $domain);
$buddy->getApiWebhooks()->getWebhook($domain, $webhookId);
$buddy->getApiWebhooks()->editWebhook($data, $domain, $webhookId);
$buddy->getApiWebhooks()->deleteWebhook($domain, $webhookId);
```

### SSH Keys

```php
$buddy->getApiSshKeys()->getKeys();
$buddy->getApiSshKeys()->addKey($data);
$buddy->getApiSshKeys()->getKey($keyId);
$buddy->getApiSshKeys()->deleteKey($keyId);
```

### Integrations

```php
$buddy->getApiIntegrations()->getIntegrations();
$buddy->getApiIntegrations()->getIntegration($integrationId);
```

### Profile

```php
$buddy->getApiProfile()->getAuthenticatedUser();
$buddy->getApiProfile()->editAuthenticatedUser($data);
```

### Emails

```php
$buddy->getApiEmails()->getAuthenticatedUserEmails();
$buddy->getApiEmails()->addAuthenticatedUserEmail($email);
$buddy->getApiEmails()->deleteAuthenticatedUserEmail($email);
```

## Execution Statuses

- `SUCCESSFUL`
- `FAILED`
- `INPROGRESS`
- `ENQUEUED`
- `SKIPPED`
- `TERMINATED`
- `INITIAL`

## Pipeline Trigger Modes

- `MANUAL`
- `SCHEDULED`
- `ON_EVERY_PUSH`

## Exceptions

- `Buddy\Exceptions\BuddyResponseException` - API errors
- `Buddy\Exceptions\BuddySDKException` - SDK errors

Both provide `getMessage()` for error details.

## Response Format

All API methods return `BuddyResponse`. Use `->getBody()` to get the array data.

```php
$response = $buddy->getApiProjects()->getProjects($domain);
$projects = $response->getBody(); // array
```

## Resources

- [Buddy API Documentation](https://buddy.works/api/reference/getting-started/overview)
- [GitHub Repository](https://github.com/buddy-works/buddy-works-php-api)
