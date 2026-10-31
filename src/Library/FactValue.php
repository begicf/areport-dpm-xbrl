<?php

namespace AReportDpmXBRL\Library;

/**
 * Turns a value as entered on the reporting form into the value an XBRL fact carries.
 *
 * The form takes monetary amounts in a chosen unit (e.g. thousands) and percentages
 * as percentage points; the report carries absolute amounts and pure ratios. Both the
 * xBRL-XML and the xBRL-CSV writers go through here so the two formats always agree.
 * Scaling moves the decimal point on the digit string — no floating point rounding.
 */
class FactValue
{
    /** Metric kind from its QName: eba_met:mi123 → 'monetary', pi → 'percentage', ii → 'integer'. */
    public static function kind(?string $metric): ?string
    {
        if (!$metric) {
            return null;
        }

        $pos = strrpos($metric, ':');
        $local = strtolower($pos === false ? $metric : substr($metric, $pos + 1));

        if (strpos($local, 'mi') === 0) {
            return 'monetary';
        }
        if (strpos($local, 'pi') === 0) {
            return 'percentage';
        }
        if (strpos($local, 'ii') === 0) {
            return 'integer';
        }

        return null;
    }

    /**
     * The fact value for a metric: monetary × $monetaryScale, percentage ÷ 100,
     * everything else unchanged. Non-numeric input is returned as given.
     */
    public static function forMetric(?string $metric, $value, int $monetaryScale = 1000): string
    {
        return self::forKind(self::kind($metric), $value, $monetaryScale);
    }

    /** Same as forMetric() for a known kind ('monetary', 'percentage', ...; see MetricTypes). */
    public static function forKind(?string $kind, $value, int $monetaryScale = 1000): string
    {
        $value = trim((string) $value);

        switch ($kind) {
            case 'monetary':
                return self::shift($value, (int) round(log10(max(1, $monetaryScale))));
            case 'percentage':
                return self::shift($value, -2);
            default:
                return $value;
        }
    }

    /** Move the decimal point $places to the right (negative = left) on a decimal string. */
    public static function shift(string $value, int $places): string
    {
        $value = str_replace(' ', '', trim($value));

        if (!preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $value, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            return $value;
        }

        $sign = $m[1] === '-' ? '-' : '';
        $int = $m[2];
        $frac = $m[3] ?? '';
        $digits = $int . $frac;
        $point = strlen($int) + $places;

        if ($point <= 0) {
            $digits = str_repeat('0', 1 - $point) . $digits;
            $point = 1;
        } elseif ($point > strlen($digits)) {
            $digits .= str_repeat('0', $point - strlen($digits));
        }

        $intPart = ltrim(substr($digits, 0, $point), '0');
        $fracPart = rtrim(substr($digits, $point), '0');
        $intPart = $intPart === '' ? '0' : $intPart;

        $result = $fracPart === '' ? $intPart : $intPart . '.' . $fracPart;

        return ($result === '0' ? '' : $sign) . $result;
    }
}
