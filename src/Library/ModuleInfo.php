<?php

namespace AReportDpmXBRL\Library;

use AReportDpmXBRL\Config\Config;

/**
 * What the EBA module JSON (DPM 2.0 / xBRL-CSV entry point) says about a module:
 * its version, the reference dates it applies to, its filing indicators, and the
 * codes that make up the EBA package name
 * {LEI}.{CON|IND}_{country}_{FRAMEWORK}{version}_{MODULE}_{refDate}_{timestamp}.
 */
class ModuleInfo
{
    /**
     * @param string $moduleDir  taxonomy folder under the public taxonomy root (e.g. "eba42")
     * @param string $modulePath module schema path relative to that folder (…/mod/corep_of.xsd)
     * @return array|null null when the module has no JSON metadata (pre-DPM 2.0 XML-only modules)
     */
    public static function load(string $moduleDir, string $modulePath): ?array
    {
        $jsonRelative = preg_replace('/\.xsd$/', '.json', str_replace('\\', '/', $modulePath));
        $path = rtrim(Config::publicDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . trim($moduleDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $jsonRelative), DIRECTORY_SEPARATOR);

        if (!is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json)) {
            return null;
        }

        $doc = $json['documentInfo']['eba:documentation'] ?? [];

        $indicators = [];
        foreach ($json['tables'] ?? [] as $table) {
            $code = $table['eba:documentation']['FilingIndicator'] ?? null;
            if ($code === null && !empty($table['template'])) {
                $code = str_replace('-', '.', $table['template']);
            }
            if ($code === null) {
                continue;
            }
            $indicators[$code][] = strtolower(pathinfo((string) ($table['url'] ?? ''), PATHINFO_FILENAME));
        }
        ksort($indicators, SORT_NATURAL);

        return [
            'version' => $doc['ModuleVersion'] ?? null,
            'version_code' => self::versionCode($doc['ModuleVersion'] ?? null),
            'from' => ($doc['FromReferenceDate'] ?? '') ?: null,
            'to' => ($doc['toReferenceDate'] ?? $doc['ToReferenceDate'] ?? '') ?: null,
            'framework' => self::frameworkCode($modulePath),
            'module' => self::moduleCode($modulePath),
            // filing indicator code => table codes (lowercase, as in table paths)
            'filing_indicators' => $indicators,
        ];
    }

    /** "4.1.0" → "040100" (two digits per part, as in EBA package names). */
    public static function versionCode(?string $version): ?string
    {
        if (!$version || !preg_match('/^\d+(\.\d+)*$/', $version)) {
            return null;
        }

        $parts = array_pad(array_slice(explode('.', $version), 0, 3), 3, '0');

        return implode('', array_map(fn ($p) => str_pad($p, 2, '0', STR_PAD_LEFT), $parts));
    }

    /** …/fws/corep/4.2/mod/corep_of.xsd → "COREP". */
    public static function frameworkCode(string $modulePath): ?string
    {
        return preg_match('#/fws/([^/]+)/#i', str_replace('\\', '/', $modulePath), $m) ? strtoupper($m[1]) : null;
    }

    /** …/mod/corep_of.xsd → "COREPOF". */
    public static function moduleCode(string $modulePath): string
    {
        return strtoupper(str_replace(['_', '-'], '', pathinfo($modulePath, PATHINFO_FILENAME)));
    }

    /**
     * EBA report package base name, e.g.
     * DUMMYLEI123456789012.CON_FR_COREP040100_COREPOF_2026-12-31_20260108130834761.
     *
     * @param string $entityId identifier value without scheme prefix (LEI.CON)
     */
    public static function packageName(string $entityId, string $country, array $info, string $refDate, ?\DateTimeInterface $at = null): ?string
    {
        if (empty($info['framework']) || empty($info['version_code']) || $country === '') {
            return null;
        }

        $at = $at ?: new \DateTimeImmutable('now');
        $stamp = $at->format('YmdHis') . substr($at->format('u'), 0, 3);

        return $entityId . '_' . strtoupper($country) . '_' . $info['framework'] . $info['version_code']
            . '_' . $info['module'] . '_' . $refDate . '_' . $stamp;
    }
}
