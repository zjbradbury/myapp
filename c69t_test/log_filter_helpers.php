<?php

function filter_rows_with_values(array $rows, array $selectedColumns): array
{
    if (!$selectedColumns) return $rows;

    return array_values(array_filter($rows, static function (array $row) use ($selectedColumns): bool {
        foreach ($selectedColumns as $column) {
            if (!array_key_exists($column, $row) || $row[$column] === null || trim((string)$row[$column]) === '') {
                return false;
            }
        }
        return true;
    }));
}

function normalise_log_time_search(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';

    if (preg_match('/^\d{3,4}$/', $value)) {
        $value = str_pad($value, 4, '0', STR_PAD_LEFT);
        return substr($value, 0, 2) . ':' . substr($value, 2, 2);
    }

    return $value;
}

function filter_rows_by_log_time(array $rows, string $timeSearch): array
{
    $timeSearch = normalise_log_time_search($timeSearch);
    if ($timeSearch === '') return $rows;

    return array_values(array_filter($rows, static function (array $row) use ($timeSearch): bool {
        return strpos((string)($row['log_time'] ?? ''), $timeSearch) !== false;
    }));
}

function sample_log_rows_by_minutes(array $rows, int $incrementMinutes): array
{
    if (!$rows || $incrementMinutes <= 0) return $rows;

    $latestTimestamp = null;
    foreach ($rows as $row) {
        $stamp = trim((string)($row['log_date'] ?? '') . ' ' . (string)($row['log_time'] ?? ''));
        $timestamp = $stamp !== '' ? strtotime($stamp) : false;
        if ($timestamp !== false && ($latestTimestamp === null || $timestamp > $latestTimestamp)) {
            $latestTimestamp = $timestamp;
        }
    }

    if ($latestTimestamp === null) return $rows;

    $nextTarget = $latestTimestamp;
    $incrementSeconds = $incrementMinutes * 60;
    $filtered = [];
    foreach ($rows as $row) {
        $stamp = trim((string)($row['log_date'] ?? '') . ' ' . (string)($row['log_time'] ?? ''));
        $timestamp = $stamp !== '' ? strtotime($stamp) : false;
        if ($timestamp !== false && $timestamp <= $nextTarget) {
            $filtered[] = $row;
            $nextTarget = $timestamp - $incrementSeconds;
        }
    }

    return $filtered;
}
