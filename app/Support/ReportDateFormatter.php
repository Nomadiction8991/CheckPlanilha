<?php

declare(strict_types=1);

namespace App\Support;

final class ReportDateFormatter
{
    public static function format(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) === 1) {
            $year = (int) $parts[1];
            $month = (int) $parts[2];
            $day = (int) $parts[3];

            return checkdate($month, $day, $year)
                ? sprintf('%02d/%02d/%04d', $day, $month, $year)
                : $value;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) === 1) {
            return $value;
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('d/m/Y', $timestamp) : $value;
    }
}
