<?php
// Time rules (same as the old API, now in one place):
// - Business dates (join/expiry dates, expenses, reports, "today"): app timezone → today().
// - Attendance check-in/out timestamps: stored in UTC, returned as ISO-8601 "…Z" → utcNow(), isoUtc().
// - Attendance "day" (which date a visit belongs to): the gym's IST day → attendanceToday(), attendanceDaySql().
//   Using the UTC day split early-morning visits (before 05:30 IST) into the previous day.

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

const ATTENDANCE_TZ = 'Asia/Kolkata';
const ATTENDANCE_UTC_OFFSET = '+05:30';

function attendanceToday()
{
    return (new DateTime('now', new DateTimeZone(ATTENDANCE_TZ)))->format('Y-m-d');
}

/**
 * SQL expression for the IST calendar day of a UTC DATETIME column.
 */
function attendanceDaySql($column)
{
    return "DATE(CONVERT_TZ($column, '+00:00', '" . ATTENDANCE_UTC_OFFSET . "'))";
}

/**
 * UTC timestamp for $date (IST day) at the current IST time of day, for marking a past day.
 */
function attendanceUtcAt($date)
{
    $tz = new DateTimeZone(ATTENDANCE_TZ);
    $local = new DateTime($date . ' ' . (new DateTime('now', $tz))->format('H:i:s'), $tz);
    return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
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
