<?php
// Time rules (same as the old API, now in one place):
// - Business dates (join/expiry dates, expenses, reports, "today"): app timezone → today().
// - Attendance check-in/out timestamps: stored in UTC, returned as ISO-8601 "…Z" → utcNow(), isoUtc().

function today()
{
    return date('Y-m-d');
}

function utcNow()
{
    return gmdate('Y-m-d H:i:s');
}

function utcToday()
{
    return gmdate('Y-m-d');
}

function addMonths($date, $months)
{
    return date('Y-m-d', strtotime('+' . (int)$months . ' months', strtotime($date)));
}

/**
 * "2026-01-05 07:30:00" (UTC) → "2026-01-05T07:30:00Z". Null stays null.
 */
function isoUtc($dateTime)
{
    if (empty($dateTime)) {
        return null;
    }
    if (strpos($dateTime, 'T') !== false && substr($dateTime, -1) === 'Z') {
        return $dateTime;
    }
    $ts = strtotime($dateTime . ' UTC');
    return $ts === false ? null : gmdate('Y-m-d\TH:i:s\Z', $ts);
}
