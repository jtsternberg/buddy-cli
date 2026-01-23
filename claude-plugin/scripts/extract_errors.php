#!/usr/bin/env php
<?php
/**
 * Extract and summarize errors from execution logs.
 *
 * Usage: buddy executions:show <id> --pipeline=<id> --logs --json | php extract_errors.php
 *
 * Parses execution output for common error patterns and provides a summary.
 */

// Common error patterns to look for in logs
const ERROR_PATTERNS = [
    // General errors
    ['/(?i)\b(error|fatal|exception):\s*(.+)/', 'Error'],
    ['/(?i)^\s*Error:\s*(.+)/', 'Error'],
    // Exit codes
    ['/exit(?:ed)?\s+(?:with\s+)?(?:code\s+)?(\d+)/', 'Exit Code'],
    ['/return(?:ed)?\s+(?:code\s+)?(\d+)/', 'Return Code'],
    // Build failures
    ['/(?i)build\s+failed/', 'Build Failed'],
    ['/(?i)compilation\s+(?:error|failed)/', 'Compilation Error'],
    // Test failures
    ['/(?i)(\d+)\s+(?:test[s]?\s+)?failed/', 'Test Failures'],
    ['/(?i)FAIL[ED]?\s+(.+)/', 'Test Failed'],
    // Dependency issues
    ['/(?i)(?:could\s+not\s+(?:find|resolve)|missing)\s+(?:dependency|package|module)\s*:?\s*(.+)/', 'Missing Dependency'],
    ['/(?i)npm\s+ERR!\s*(.+)/', 'NPM Error'],
    ['/(?i)composer\s+(?:error|failed)/', 'Composer Error'],
    // Permission/access issues
    ['/(?i)permission\s+denied/', 'Permission Denied'],
    ['/(?i)access\s+denied/', 'Access Denied'],
    ['/(?i)authentication\s+(?:failed|error|required)/', 'Auth Error'],
    // Network issues
    ['/(?i)connection\s+(?:refused|timed?\s*out|failed)/', 'Connection Error'],
    ['/(?i)timeout\s+(?:exceeded|error)/', 'Timeout'],
    // Resource issues
    ['/(?i)out\s+of\s+memory/', 'Out of Memory'],
    ['/(?i)disk\s+(?:full|space)/', 'Disk Space'],
];

function extractErrorsFromLog(array $logLines): array
{
    $errors = [];
    foreach ($logLines as $line) {
        foreach (ERROR_PATTERNS as [$pattern, $category]) {
            if (preg_match($pattern, $line, $match)) {
                $detail = isset($match[1]) ? $match[1] : trim($line);
                $errors[] = [
                    'category' => $category,
                    'detail' => substr($detail, 0, 200),
                    'line' => substr(trim($line), 0, 300),
                ];
                break; // Only match first pattern per line
            }
        }
    }
    return $errors;
}

function main(): int
{
    $input = file_get_contents('php://stdin');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "ERROR: Invalid JSON input: " . json_last_error_msg() . "\n");
        return 1;
    }

    // Handle array or single object
    if (isset($data[0])) {
        if (count($data) === 0) {
            echo "No execution data found.\n";
            return 0;
        }
        $data = $data[0];
    }

    $actions = $data['action_executions'] ?? [];
    $failedActions = array_filter($actions, fn($a) => ($a['status'] ?? '') === 'FAILED');

    if (empty($failedActions)) {
        echo "No failed actions found.\n";
        return 0;
    }

    $allErrors = [];

    foreach ($failedActions as $action) {
        $actionName = $action['action']['name'] ?? 'Unknown';
        $log = $action['log'] ?? [];

        if (empty($log)) {
            $allErrors['No Logs'][] = [
                'action' => $actionName,
                'detail' => 'Action failed but no logs available',
            ];
            continue;
        }

        $errors = extractErrorsFromLog($log);

        if (empty($errors)) {
            // No patterns matched, get last few lines as context
            $lastLines = array_slice($log, -5);
            $allErrors['Unidentified'][] = [
                'action' => $actionName,
                'detail' => implode("\n", $lastLines),
            ];
        } else {
            foreach ($errors as $error) {
                $allErrors[$error['category']][] = [
                    'action' => $actionName,
                    'detail' => $error['detail'],
                ];
            }
        }
    }

    // Output summary
    echo "ERROR SUMMARY\n";
    echo str_repeat('=', 40) . "\n";

    ksort($allErrors);
    foreach ($allErrors as $category => $errors) {
        $count = count($errors);
        $plural = $count > 1 ? 's' : '';
        echo "\n{$category} ({$count} occurrence{$plural}):\n";

        $seenDetails = [];
        foreach ($errors as $error) {
            $key = $error['action'] . '|' . substr($error['detail'], 0, 50);
            if (isset($seenDetails[$key])) {
                continue;
            }
            $seenDetails[$key] = true;
            echo "  [{$error['action']}] {$error['detail']}\n";
        }
    }

    echo "\nFAILED ACTIONS:\n";
    foreach ($failedActions as $action) {
        $name = $action['action']['name'] ?? 'Unknown';
        $type = $action['action']['type'] ?? '-';
        echo "  - {$name} ({$type})\n";
    }

    return 0;
}

exit(main());
