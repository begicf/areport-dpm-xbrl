<?php

namespace AReportDpmXBRL\Domain;

use AReportDpmXBRL\Config\Config;
use AReportDpmXBRL\Library\Data;
use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\Library\Format;


/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Class Domain
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class Domain
{

    private static $dom;

    private static function appendCandidate(array &$candidates, string $candidate): void
    {
        $candidate = trim($candidate, '/');

        if ($candidate !== '' && !in_array($candidate, $candidates, true)) {
            $candidates[] = $candidate;
        }
    }

    private static function getRoleCandidates(string $path): array
    {
        $url = parse_url($path);

        $host = strtolower($url['host'] ?? Config::$owner);
        $pathInfo = trim((string) ($url['path'] ?? ''), '/');
        $pathInfo = preg_replace('#/role/#', '/', $pathInfo, 1);
        $pathInfo = strtolower($pathInfo);

        $candidates = [];
        self::appendCandidate($candidates, $host . '/' . $pathInfo);

        if (strpos($pathInfo, 'xbrl/') === 0) {
            self::appendCandidate($candidates, $host . '/eu/fr/' . $pathInfo);
        }

        foreach ($candidates as $candidate) {
            $segments = explode('/', $candidate);

            if (count($segments) > 1) {
                array_pop($segments);
                self::appendCandidate($candidates, implode('/', $segments));
            }
        }

        return $candidates;
    }

    private static function legacyGetPath($path, $root)
    {
        $url = parse_url($path, PHP_URL_PATH);
        $info = substr($url, strpos($url, "role/") + 5);
        $exp = explode('/', $info);
        array_pop($exp);

        $_path = implode(DIRECTORY_SEPARATOR, array_map('strtolower', $exp));

        return current(DomToArray::getPath(Config::publicDir() . $root . DIRECTORY_SEPARATOR, [$_path]));
    }

    private static function removeVersionFromRole(string $path): string
    {
        return preg_replace('#(/role/dict/dom/[^/]+)/\d+(?:\.\d+)*(?=/)#', '$1', $path) ?? $path;
    }

    private static function collectArtifacts(array $paths): array
    {
        $artifacts = [
            'hier' => [],
            'mem' => [],
        ];

        foreach ($paths as $row) {
            if (!file_exists($row)) {
                continue;
            }

            if (strpos($row, 'hier.xsd') !== false) {
                $artifacts['hier'] = Data::getTax($row);
            } elseif (strpos($row, 'mem.xsd') !== false) {
                $artifacts['mem'] = self::mem($row);
            }
        }

        return $artifacts;
    }

    private static function _getPath($path, $root)
    {
        $taxonomyRoot = rtrim(Config::publicDir() . $root, DIRECTORY_SEPARATOR);
        $candidates = self::getRoleCandidates((string) $path);

        foreach ($candidates as $candidate) {
            $exactPath = $taxonomyRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);

            if (is_dir($exactPath)) {
                $files = glob($exactPath . DIRECTORY_SEPARATOR . '*.xsd') ?: [];

                if (!empty($files)) {
                    return array_values($files);
                }
            }
        }

        foreach ($candidates as $candidate) {
            $matches = current(DomToArray::getPath($taxonomyRoot . DIRECTORY_SEPARATOR, [$candidate])) ?: [];

            if (!empty($matches)) {
                return $matches;
            }
        }

        return [];
    }

    private static function getHierarchyRows($hierarchy, $hierDef): array
    {
        if (!empty($hierDef['pre'][$hierarchy]) && is_array($hierDef['pre'][$hierarchy])) {
            return $hierDef['pre'][$hierarchy];
        }

        if (empty($hierDef['def'][$hierarchy]) || !is_array($hierDef['def'][$hierarchy])) {
            return [];
        }

        $definition = $hierDef['def'][$hierarchy];
        $locators = [];
        $rows = [];

        foreach (($definition['link:loc'] ?? []) as $row) {
            if (!empty($row['label'])) {
                $locators[$row['label']] = $row;
            }
        }

        foreach (($definition['link:definitionArc'] ?? []) as $row) {
            if (($row['arcrole'] ?? null) !== 'http://xbrl.org/int/dim/arcrole/domain-member') {
                continue;
            }

            $locator = $locators[$row['to'] ?? ''] ?? null;

            if (empty($locator)) {
                continue;
            }

            $key = (string) ($row['order'] ?? count($rows));

            while (isset($rows[$key])) {
                $key .= '_';
            }

            $rows[$key] = array_merge($locator, [
                'from' => $row['from'] ?? null,
                'to' => $row['to'] ?? null,
                'order' => $row['order'] ?? null,
                'arcrole' => $row['arcrole'] ?? null,
            ]);
        }

        return $rows;
    }

    private static function findMemberLabel(array $members, array $hier): ?array
    {
        if (!empty($hier['label'])) {
            $search = DomToArray::search_multdim($members, 'from', $hier['label']);

            if (!empty($search)) {
                return current($search);
            }
        }

        if (!empty($hier['href'])) {
            $search = DomToArray::search_multdim($members, 'href', $hier['href']);

            if (!empty($search)) {
                return current($search);
            }

            $fragment = Format::getAfterSpecChar($hier['href'], '#');

            foreach ($members as $row) {
                if (
                    !empty($row['href'])
                    && Format::getAfterSpecChar($row['href'], '#') === $fragment
                ) {
                    return $row;
                }
            }
        }

        return null;
    }

    public static function resolveHierarchyData($path, $root): array
    {
        $exactRole = (string) $path;
        $fallbackRole = self::removeVersionFromRole($exactRole);

        $exactArtifacts = self::collectArtifacts(self::_getPath($exactRole, $root));
        $fallbackArtifacts = ($fallbackRole !== $exactRole)
            ? self::collectArtifacts(self::_getPath($fallbackRole, $root))
            : ['hier' => [], 'mem' => []];

        $attempts = [];

        if (!empty($exactArtifacts['hier']) && !empty($exactArtifacts['mem'])) {
            $attempts[] = [$exactRole, $exactArtifacts['hier'], $exactArtifacts['mem']];
        }

        if (!empty($exactArtifacts['hier']) && !empty($fallbackArtifacts['mem'])) {
            $attempts[] = [$exactRole, $exactArtifacts['hier'], $fallbackArtifacts['mem']];
        }

        if (!empty($fallbackArtifacts['hier']) && !empty($fallbackArtifacts['mem'])) {
            $attempts[] = [$fallbackRole, $fallbackArtifacts['hier'], $fallbackArtifacts['mem']];
        }

        foreach ($attempts as [$role, $hier, $mem]) {
            $presentation = self::getHierarchyPresentation($role, $hier, $mem);

            if (!empty($presentation)) {
                return [
                    'presentation' => $presentation,
                    'namespace' => $mem['namespace'] ?? [],
                    'imports' => $mem['imports'] ?? [],
                ];
            }
        }

        return [
            'presentation' => [],
            'namespace' => $fallbackArtifacts['mem']['namespace'] ?? $exactArtifacts['mem']['namespace'] ?? [],
            'imports' => $fallbackArtifacts['mem']['imports'] ?? $exactArtifacts['mem']['imports'] ?? [],
        ];
    }

    private static function _setDom()
    {
        self::$dom = DomToArray::invoke(self::$path);
    }

    /**
     * @param $path
     * @param $root
     * @return array
     */
    public static function getDomain($path, $root): array
    {
        $resolved = self::resolveHierarchyData($path, $root);

        if (!empty($resolved['presentation'])) {
            return $resolved['presentation'];
        }

        $legacyPath = self::legacyGetPath($path, $root);
        $hier = [];
        $mem = [];

        foreach ((array) $legacyPath as $row):
            if (!file_exists($row)):
                continue;
            endif;

            if (strpos($row, 'hier.xsd') !== false && empty($hier)) {
                $hier = Data::getTax($row);
            } elseif (strpos($row, 'mem.xsd') !== false && empty($mem)) {
                $mem = self::mem($row);
            }
        endforeach;

        if (!empty($hier) && !empty($mem)) {
            return self::getHierarchyPresentation($path, $hier, $mem);
        }

        return [];
    }

    /**
     * @param $schema
     * @return array
     */
    public static function mem($schema): array
    {

        $_memOwner = array();

        if (strpos($schema, Config::$owner) === false):

            $_memOwner = Data::getTax($schema);

        endif;


        $_mem = Data::getTax($schema);

        return array_merge($_mem, $_memOwner);
    }

    /**
     * @param $hierarchy
     * @param $hierDef
     * @param $mem
     * @return array
     */
    public static function getHierarchyPresentation($hierarchy, $hierDef, $mem): array
    {
        $lang = Data::checkLang($mem);

        if (empty($lang)) {
            return [];
        }

        $rows = self::getHierarchyRows($hierarchy, $hierDef);

        if (empty($rows)) {
            return [];
        }

        $pre_ = [];
        $pre__ = [];

        foreach ($rows as $key => $hier):
            $search = self::findMemberLabel($mem[$lang], $hier);
            if (!empty($search) && !empty($hier['order'])):

                if (strpos(Format::getAfterSpecChar($hier['href'], '_'), '_') !== false):

                    $pre__['__' . $hier['order']] = array_merge($hier, $search);

                else:
                    $pre_['_' . $hier['order']] = array_merge($hier, $search);
                endif;
            endif;
        endforeach;

        $pre = $pre_ + $pre__;

        ksort($pre, SORT_NATURAL);

        return $pre;
    }

    /**
     * @param array $arr
     * @return array
     */
    public static function sortPre($arr = []): array
    {

        uasort($arr,
            function ($a, $b) {

                return $a['order'] <=> $b['order'];

            });

        return $arr;
    }

}
