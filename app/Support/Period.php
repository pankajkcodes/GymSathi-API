<?php

class Period
{
    /**
     * Admin panel period filter → [from, to] (Y-m-d), or [null, null].
     * Accepts: today, yesterday, last_7_days, last_30_days, this_year, custom (+from/to), a year "2025",
     * and the display labels ("Last 7 Days").
     */
    public static function range($period, $from = null, $to = null)
    {
        switch (strtolower(str_replace(' ', '_', trim((string)$period)))) {
            case 'today':
                return [today(), today()];
            case 'yesterday':
                $d = date('Y-m-d', strtotime('-1 day'));
                return [$d, $d];
            case 'last_7_days':
                return [date('Y-m-d', strtotime('-6 days')), today()];
            case 'last_30_days':
                return [date('Y-m-d', strtotime('-29 days')), today()];
            case 'this_year':
                return [date('Y-01-01'), date('Y-12-31')];
            case 'custom':
                if ($from && $to && strtotime($from) && strtotime($to)) {
                    return [date('Y-m-d', strtotime($from)), date('Y-m-d', strtotime($to))];
                }
                return [null, null];
            default:
                return preg_match('/^\d{4}$/', (string)$period) ? [$period . '-01-01', $period . '-12-31'] : [null, null];
        }
    }

    /**
     * Same, reading ?period=&from=&to= from the query string.
     */
    public static function fromQuery()
    {
        return self::range(query('period', ''), query('from'), query('to'));
    }
}
