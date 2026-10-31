<?php

namespace AReportDpmXBRL\Library;

use AReportDpmXBRL\Config\Config;

/**
 * The kind of value a metric holds, read from the taxonomy's metric dictionary
 * (…/dict/met/met.xsd, and …/dict/met/<version>/met.xsd for prefixes like eba_met_3.5).
 *
 * DPM 2.0 metric names (eba_met:qABJ) no longer encode the type the way DPM 1.0
 * names did (eba_met:mi53), so the element's XBRL type is the only reliable source.
 * Falls back to the DPM 1.0 name prefix when no dictionary is found.
 */
class MetricTypes
{
    /** @var array<string, array<string, string>> dictionary file => [local name => xbrl type] */
    private static $cache = [];

    private const KINDS = [
        'xbrli:monetaryItemType' => 'monetary',
        'num:percentItemType' => 'percentage',
        'xbrli:integerItemType' => 'integer',
        'xbrli:decimalItemType' => 'decimal',
    ];

    /** 'monetary' | 'percentage' | 'integer' | 'decimal' | null (non-numeric). */
    public static function kind(?string $moduleDir, ?string $metric): ?string
    {
        if (!$metric) {
            return null;
        }

        $type = $moduleDir ? self::type($moduleDir, $metric) : null;

        if ($type !== null) {
            return self::KINDS[$type] ?? null;
        }

        return FactValue::kind($metric);
    }

    /** The metric's XBRL item type from the dictionary, or null if not found. */
    public static function type(string $moduleDir, string $metric): ?string
    {
        if (strpos($metric, ':') === false) {
            return null;
        }

        [$prefix, $local] = explode(':', $metric, 2);

        // eba_met → dict/met/met.xsd; eba_met_3.5 → dict/met/3.5/met.xsd
        if (!preg_match('/^([a-z]+)_met(?:_([0-9.]+))?$/i', $prefix, $m)) {
            return null;
        }

        $owner = strtolower($m[1]);
        $root = rtrim(Config::publicDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($moduleDir, DIRECTORY_SEPARATOR);

        if ($owner === 'eba') {
            $file = $root . '/www.eba.europa.eu/eu/fr/xbrl/crr/dict/met' . (empty($m[2]) ? '' : '/' . $m[2]) . '/met.xsd';

            return self::dictionary($file)[$local] ?? null;
        }

        // Another owner's metrics (a national regulator's fba_met, …): its dictionary sits
        // under its own host, …/dict/met/met.xsd — look the metric up in those.
        foreach (self::ownerDictionaries($root) as $file) {
            if (isset(self::dictionary($file)[$local])) {
                return self::dictionary($file)[$local];
            }
        }

        return null;
    }

    /** @var array<string, array<int, string>> taxonomy root => non-EBA metric dictionaries */
    private static $owners = [];

    private static function ownerDictionaries(string $root): array
    {
        if (!array_key_exists($root, self::$owners)) {
            $files = array_merge(glob($root . '/*/eu/fr/xbrl/*/dict/met/met.xsd') ?: [], glob($root . '/*/eu/fr/xbrl/*/dict/met/*/met.xsd') ?: []);
            self::$owners[$root] = array_values(array_filter($files, fn ($f) => strpos($f, '/www.eba.europa.eu/') === false));
        }

        return self::$owners[$root];
    }

    private static function dictionary(string $file): array
    {
        if (!array_key_exists($file, self::$cache)) {
            $map = [];
            if (is_file($file)) {
                preg_match_all('/<xs:element\b[^>]*\bname="([^"]+)"[^>]*\btype="([^"]+)"/', (string) file_get_contents($file), $all, PREG_SET_ORDER);
                foreach ($all as $el) {
                    $map[$el[1]] = $el[2];
                }
            }
            self::$cache[$file] = $map;
        }

        return self::$cache[$file];
    }
}
