#!/usr/bin/env php
<?php
/**
 * Parse execution JSON and output a readable status summary.
 *
 * Usage: buddy executions:show <id> --pipeline=<id> --json | php format_status.php
 *
 * Highlights failed actions with clear indicators.
 */

function parseTime(?string $ts): ?DateTimeImmutable
{
    if (!$ts) {
        return null;
    }
    try {
        return new DateTimeImmutable($ts);
    } catch (Exception $e) {
        return null;
    }
}

function formatDuration(?string $start, ?string $end): string
{
    $startDt = parseTime($start);
    $endDt = parseTime($end);
    if (!$startDt || !$endDt) {
        return '-';
    }
    $diff = $endDt->getTimestamp() - $startDt->getTimestamp();
    if ($diff < 60) {
        return "{$diff}s";
    }
    $minutes = intdiv($diff, 60);
    $seconds = $diff % 60;
    return "{$minutes}m {$seconds}s";
}

function statusIndicator(string $status): string
{
    $indicators = [
        'SUCCESSFUL' => '[OK]',
        'FAILED' => '[X]',
        'INPROGRESS' => '[->]',
        'ENQUEUED' => '[..]',
        'SKIPPED' => '[--]',
        'TERMINATED' => '[##]',
    ];
    return $indicators[$status] ?? '[?]';
}

function main(): int
{
    $input = file_get_contents('php://stdin');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "ERROR: Invalid JSON input: " . json_last_error_msg() . "\n");
        return 1;
    }

    // Handle both single execution and array
    if (isset($data[0])) {
        if (count($data) === 0) {
            echo "No executions found.\n";
            return 0;
        }
        $data = $data[0];
    }

    // Execution header
    $execId = $data['id'] ?? 'Unknown';
    $status = $data['status'] ?? 'UNKNOWN';
    $branch = $data['branch']['name'] ?? '-';
    $revision = substr($data['to_revision']['revision'] ?? '-', 0, 8);
    $creator = $data['creator']['name'] ?? '-';
    $duration = formatDuration($data['start_date'] ?? null, $data['finish_date'] ?? null);

    echo "Execution #{$execId}\n";
    echo "  Status:   " . statusIndicator($status) . " {$status}\n";
    echo "  Branch:   {$branch}\n";
    echo "  Revision: {$revision}\n";
    echo "  Creator:  {$creator}\n";
    echo "  Duration: {$duration}\n";
    echo "\n";

    // Actions summary
    $actions = $data['action_executions'] ?? [];
    if (empty($actions)) {
        echo "No actions in this execution.\n";
        return 0;
    }

    $failed = array_filter($actions, fn($a) => ($a['status'] ?? '') === 'FAILED');
    $successful = array_filter($actions, fn($a) => ($a['status'] ?? '') === 'SUCCESSFUL');

    $total = count($actions);
    $passedCount = count($successful);
    echo "Actions: {$passedCount}/{$total} passed\n\n";

    // List all actions with status
    foreach ($actions as $action) {
        $name = $action['action']['name'] ?? 'Unknown';
        $actionStatus = $action['status'] ?? 'UNKNOWN';
        $actionDuration = formatDuration($action['start_date'] ?? null, $action['finish_date'] ?? null);
        $indicator = statusIndicator($actionStatus);

        $line = "  {$indicator} {$name}";
        if ($actionDuration !== '-') {
            $line .= " ({$actionDuration})";
        }
        echo $line . "\n";
    }

    // Highlight failures
    if (!empty($failed)) {
        echo "\nFAILED ACTIONS:\n";
        foreach ($failed as $action) {
            $name = $action['action']['name'] ?? 'Unknown';
            $type = $action['action']['type'] ?? '-';
            echo "  - {$name} ({$type})\n";
        }
    }

    return 0;
}

exit(main());
