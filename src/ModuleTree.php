<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AReportDpmXBRL;

ini_set('max_execution_time', 300);
ini_set('memory_limit', '1024M');

use AReportDpmXBRL\Config\Config;
use AReportDpmXBRL\Library\Data;
use AReportDpmXBRL\Library\Directory;
use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\Library\Format;
use AReportDpmXBRL\Library\Normalise;

/**
 * Class Mod
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class ModuleTree
{

    private $path;
    private $lang;
    private static $groupTable = [];

    public function __construct($path = NULL, $lang = NULL)
    {

        $this->path = $path;
        $this->lang = $lang;

    }

    public function module($id, $ext, $path, $mod = null)
    {

        switch ($ext):

            case 'fws':

                $fws = Directory::searchFileExclude($this->path, 'fws.xsd');
                return $this->getFrameworks($fws);

            case 'tax':

                $taxonomy = Directory::searchFileExclude($path, 'tax.xsd');
                return $this->getTaxonomy($id, $taxonomy);

            case 'mod':

                return $this->getModules($id, $path);

            case 'tab':

                return $this->getTable($id, $path, $mod);

        endswitch;


    }

    public function getFrameworks($frameworks)
    {

        $data = [];

        foreach ($frameworks as $fws):

            $fw = Data::getTax($fws->getRealPath(), null, null);

            foreach ($fw['elements'] as $row):

                $data[] = [
                    'parent' => '#',
                    'children' => true,
                    'data' => $fws->getPath() . DIRECTORY_SEPARATOR . strtolower($row['name'] . DIRECTORY_SEPARATOR),
                    'id' => $row['id'],
                    'text' => $row['name'],
                    'type' => "fws"

                ];


            endforeach;
        endforeach;

        sort($data);

        return $data;
    }

    public function getTaxonomy($id, $taxonomy)
    {

        $data = [];

        foreach ($taxonomy as $key => $rows):

            $tax = Data::getTax($rows->getRealPath(), null, null);

            foreach ($tax['elements'] as $k => $row):

                $data[] = [
                    'parent' => $id,
                    'children' => true,
                    'data' => $rows->getPath(),
                    'id' => str_replace(".", "", $row['name']),
                    'text' => $row['name'] . ' / ' . $row['creationDate'],
                    'type' => 'tax',
                    'creationDate' => $row['creationDate']

                ];
            endforeach;

        endforeach;

        usort($data, function ($a, $b) {
            return $a['creationDate'] <=> $b['creationDate'];
        });
        return $data;

    }

    public function getModules($id, $path)
    {

        $data = [];

        $module = $this->fetchModule($path);

        foreach ($module as $mod):
            $moduleNodeCount = 0;

            $this->lang = Library\Data::checkLang($mod);

            if (isset($mod['pre'])):
                foreach ($mod['pre'] as $key => $row):


                    if (!isset($row['order']) && isset($row['label'])):
                        $name = $this->resolveLocatorLabelContent($mod, $row['label'], $row['href'] ?? null);
                        $data[] = [
                            'parent' => $id,
                            'children' => true,
                            'data' => $path,
                            'id' => $id . '#' . $row['label'],
                            'ext' => 'tab',
                            "text" => $name ?? $this->normalizeDisplayTitle($row['label']),
                            "mod" => ((is_file($mod['mod_path'])) ? $mod['mod_path'] : Config::publicDir() . DIRECTORY_SEPARATOR . $mod['mod_path']),
                            'type' => 'mod'
                        ];
                        $moduleNodeCount++;

                    endif;

                endforeach;
            endif;

            if ($moduleNodeCount === 0) {
                $fallbackNode = $this->buildModuleNodeFromMetadata($id, $path, $mod);

                if (!empty($fallbackNode)) {
                    $data[] = $fallbackNode;
                }
            }
        endforeach;

        return $data;
    }

    public function getTable($id, $path, $modulePath = null): ?array
    {

        $data = [];
        $ids = Format::getAfterSpecChar($id, '#');

        $module = $this->fetchModule($path);

        foreach ($module as $mod):

            $this->lang = Library\Data::checkLang($mod);

            if (isset($mod['pre'])):
                foreach ($mod['pre'] as $key => $row):


                    if (isset($row['from']) && $row['from'] == $ids && isset($row['label'])):

                        $_path = pathinfo(strtok($row['href'], "#"));

                        if (strpos($_path['filename'], '-rend')):

                            $row['href'] =
                                preg_replace('#^https?://#', '', $_path['dirname']) . DIRECTORY_SEPARATOR . str_replace('-rend', '.xsd', $_path['filename']);

                            $_path['extension'] = 'xsd';
                            $type = 'file';
                            $children = false;

                            $mod['mod_path'] = $modulePath;

                        else:

                            $type = 'group';
                            $children = true;

                        endif;

                        if ($_path['extension'] == 'xsd'):


                            if (strpos($row['href'], 'www') !== false):

                                $str = preg_replace('#^https?://#', '', $row['href']);
                                $pathXsd = $this->path . DIRECTORY_SEPARATOR . strtok($str, "#");

                            else:

                                $pathXsd =
                                    dirname($mod['pre']['path']) . DIRECTORY_SEPARATOR . strtok($row['href'], "#");
                            endif;


                            $getFile = pathinfo($pathXsd);


                            if ($type == 'file'):

                                $getTableXsd = Format::findStringInArray($mod['imports'], $getFile['basename']);

                                $getFileXsdSource =
                                    $path . DIRECTORY_SEPARATOR . 'mod' . DIRECTORY_SEPARATOR . current($getTableXsd);

                            else:

                                $getFileXsdSource = (current(Directory::searchFile($path, 'tab.xsd')))->getPathName();
                                // $getFileXsdSource =$modulePath;
                            endif;


                            try {

                                $linkSource = Data::getTax($getFileXsdSource, Data::getLangSpec('mod'));

                            } catch (\Exception $e) {

                                echo \Exception(("Not found $getFileXsdSource"));

                            }

                            //Get XBRL specification destination
                            $linkDestination = Data::getTax($getFileXsdSource, Data::getLangSpec('mod'));

                            $link = array_merge($linkSource, $linkDestination);
                            //  echo "<pre>", print_r($link), "</pre>";

                            $this->lang = Library\Data::checkLang($link);


                            try {
                                $name = $this->resolveLocatorLabelContent($link, $row['label'], $row['href'] ?? null);

                            } catch (\Exception $e) {
                                throw new \Exception('The name is not set for: ' . $row['label']);

                            }
                        endif;

                        $data[$row['order'] - 1] = [
                            'parent' => ((strpos($id, '#') !== false) ? $id : $row['from']),
                            "children" => $children,
                            'data' => $path,
                            'lang' => preg_replace('/lab-/', '', $this->lang, 1),
                            'id' => $row['to'],
                            "text" => $name ?? $this->normalizeDisplayTitle($row['href']),
                            "table_xsd" => $pathXsd,
                            'type' => $type
                        ];

                    endif;

                endforeach;
            endif;

        endforeach;

        if (!empty($data)) {
            ksort($data);
            return array_values($data);
        }

        if (!empty($modulePath)) {
            return $this->buildJsonFallbackTableNodes($id, $modulePath, $path);
        }

        return $data;

    }

    /**
     * @param $path
     * @return array
     */
    private function fetchModule($path): ?array
    {

        $modules = Directory::getPath($path, ['mod' => 'mod' . DIRECTORY_SEPARATOR]);
        $module = [];

        if (empty($modules['mod'])) {
            return $module;
        }

        foreach ($modules['mod'] as $key => $mod):

            $module[$key] = Data::getTax($mod);
            $module[$key]['mod_path'] = Normalise::taxPath($mod);

        endforeach;

        return $module;

    }

    public static function getModuleTableMapFromJson(string $modulePath): array
    {
        $jsonPath = self::getModuleJsonPath($modulePath);

        if (empty($jsonPath) || !is_file($jsonPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($jsonPath), true);

        if (!is_array($decoded)) {
            return [];
        }

        $tablePaths = [];

        foreach (($decoded['documentInfo']['extends'] ?? []) as $extendPath) {
            if (!is_string($extendPath) || substr($extendPath, -5) !== '.json') {
                continue;
            }

            if (strpos($extendPath, 'FilingIndicators.json') !== false || strpos($extendPath, 'FootNotes.json') !== false) {
                continue;
            }

            $localJsonPath = self::resolveRelativeFile(dirname($jsonPath), $extendPath);

            if ($localJsonPath === null) {
                continue;
            }

            $localXsdPath = preg_replace('/\.json$/', '.xsd', $localJsonPath);

            if ($localXsdPath === null || !is_file($localXsdPath)) {
                continue;
            }

            $tableCode = strtoupper(pathinfo($localXsdPath, PATHINFO_FILENAME));
            $tablePaths[$tableCode] = $localXsdPath;
        }

        return $tablePaths;
    }

    private function buildJsonFallbackTableNodes(string $id, string $modulePath, string $path): array
    {
        $tableMap = self::getModuleTableMapFromJson($modulePath);
        $data = [];

        foreach ($tableMap as $tableCode => $tableXsdPath) {
            $label = self::getTableDisplayName($tableXsdPath);

            $data[] = [
                'parent' => $id,
                'children' => false,
                'data' => $path,
                'lang' => 'en',
                'id' => $id . '#' . preg_replace('/[^a-zA-Z0-9]+/', '', $tableCode),
                'text' => $label ?: $tableCode,
                'table_xsd' => $tableXsdPath,
                'type' => 'file',
            ];
        }

        usort($data, function ($left, $right) {
            return strnatcasecmp((string) $left['text'], (string) $right['text']);
        });

        return $data;
    }

    private function buildModuleNodeFromMetadata(string $id, string $path, array $module): ?array
    {
        $modulePath = $module['mod_path'] ?? null;

        if (!is_string($modulePath) || $modulePath === '') {
            return null;
        }

        $displayName = $this->resolveModuleDisplayName($module, $modulePath);
        $moduleCode = strtoupper(pathinfo($modulePath, PATHINFO_FILENAME));

        return [
            'parent' => $id,
            'children' => true,
            'data' => $path,
            'id' => $id . '#' . preg_replace('/[^a-zA-Z0-9]+/', '', $moduleCode),
            'ext' => 'tab',
            'text' => $displayName ?: $moduleCode,
            'mod' => ((is_file($modulePath)) ? $modulePath : Config::publicDir() . DIRECTORY_SEPARATOR . $modulePath),
            'type' => 'mod',
        ];
    }

    private static function getModuleJsonPath(string $modulePath): ?string
    {
        $jsonPath = preg_replace('/\.xsd$/', '.json', $modulePath);

        if (!is_string($jsonPath) || $jsonPath === $modulePath) {
            return null;
        }

        return $jsonPath;
    }

    private static function resolveRelativeFile(string $baseDirectory, string $relativePath): ?string
    {
        if (preg_match('/^https?:\/\//i', $relativePath)) {
            return null;
        }

        $resolved = realpath($baseDirectory . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));

        return $resolved === false ? null : $resolved;
    }

    private function resolveLocatorLabelContent(array $taxonomy, string $from, ?string $href = null): ?string
    {
        $languageKey = Library\Data::checkLang($taxonomy);

        if (empty($languageKey) || empty($taxonomy[$languageKey]) || !is_array($taxonomy[$languageKey])) {
            return $href !== null ? self::normalizeDisplayTitle(Format::getAfterSpecChar($href, '#')) : null;
        }

        $entries = DomToArray::search_multdim($taxonomy[$languageKey], 'from', $from) ?? [];

        foreach (self::preferredLabelRoles() as $role) {
            foreach ($entries as $entry) {
                if (($entry['role'] ?? null) !== $role) {
                    continue;
                }

                $content = self::normalizeDisplayTitle($entry['@content'] ?? null);

                if (!empty($content) && !in_array($content, ['Rows', 'Columns'], true)) {
                    return $content;
                }
            }
        }

        foreach ($entries as $entry) {
            $content = self::normalizeDisplayTitle($entry['@content'] ?? null);

            if (!empty($content) && !in_array($content, ['Rows', 'Columns'], true)) {
                return $content;
            }
        }

        return $href !== null ? self::normalizeDisplayTitle(Format::getAfterSpecChar($href, '#')) : null;
    }

    private function resolveModuleDisplayName(array $taxonomy, string $modulePath): ?string
    {
        $languageKey = Library\Data::checkLang($taxonomy);

        if (empty($languageKey) || empty($taxonomy[$languageKey]) || !is_array($taxonomy[$languageKey])) {
            return strtoupper(pathinfo($modulePath, PATHINFO_FILENAME));
        }

        $moduleFile = basename($modulePath);

        foreach (self::preferredLabelRoles() as $role) {
            foreach ($taxonomy[$languageKey] as $entry) {
                if (($entry['role'] ?? null) !== $role) {
                    continue;
                }

                if (strpos((string) ($entry['href'] ?? ''), $moduleFile . '#') === false) {
                    continue;
                }

                $content = self::normalizeDisplayTitle($entry['@content'] ?? null);

                if (!empty($content)) {
                    return $content;
                }
            }
        }

        return strtoupper(pathinfo($modulePath, PATHINFO_FILENAME));
    }

    public static function getTableDisplayName(string $tableXsdPath): ?string
    {
        try {
            $taxonomy = Data::getTax($tableXsdPath, Data::getLangSpec('mod'));
        } catch (\Throwable $exception) {
            return strtoupper(pathinfo($tableXsdPath, PATHINFO_FILENAME));
        }

        $languageKey = Library\Data::checkLang($taxonomy);

        if (empty($languageKey) || empty($taxonomy[$languageKey]) || !is_array($taxonomy[$languageKey])) {
            return strtoupper(pathinfo($tableXsdPath, PATHINFO_FILENAME));
        }

        $tableCode = pathinfo($tableXsdPath, PATHINFO_FILENAME);

        foreach (self::preferredLabelRoles() as $role) {
            foreach ($taxonomy[$languageKey] as $entry) {
                if (($entry['role'] ?? null) !== $role) {
                    continue;
                }

                if (strpos((string) ($entry['href'] ?? ''), $tableCode . '-rend.xml#') === false) {
                    continue;
                }

                $content = self::normalizeDisplayTitle($entry['@content'] ?? null);

                if (!empty($content) && !in_array($content, ['Rows', 'Columns'], true)) {
                    return strtoupper($tableCode) . ' - ' . $content;
                }
            }
        }

        return strtoupper($tableCode);
    }

    private static function preferredLabelRoles(): array
    {
        return [
            'http://www.xbrl.org/2008/role/verboseLabel',
            'http://www.xbrl.org/2003/role/verboseLabel',
            'http://www.xbrl.org/2008/role/label',
            'http://www.xbrl.org/2003/role/label',
        ];
    }

    private static function normalizeDisplayTitle($label): ?string
    {
        if (!is_string($label)) {
            return null;
        }

        $label = trim(preg_replace('/\s+/', ' ', $label));

        if ($label === '') {
            return null;
        }

        return preg_replace('/^[A-Z]_[0-9]{2}\.[0-9]{2}(?:\.[A-Za-z0-9]+)?\s*:\s*/', '', $label) ?: $label;
    }

    /**
     * @param $elements
     * @param $parentId
     * @return array
     */
    public static function makeTree($elements, $parentId): ?array
    {
        $branch = [];

        foreach ($elements as $element) {
            if (isset ($element['from']) && $element['from'] == $parentId) {
                $children = self::makeTree($elements, $element['to']);

                if ($children) {

                    $element['group'] = $children;

                }

                if (strpos($element['href'], 'rend.xml')):
                    $branch['table'][] = $element;
                else:
                    $branch[] = $element;
                endif;


            }
        }

        return $branch;

    }

    /**
     * @param $elements
     * @param $parentId
     * @return array
     */
    public static function getGroupTable($elements, $parentId): array
    {
        $tree = self::makeTree($elements, $parentId);

        self::extractGroupTable($tree);

        return self::$groupTable;

    }

    /**
     * @param $tree
     */
    private static function extractGroupTable($tree)
    {

        if (!is_null($tree) && count($tree) > 0) {

            foreach ($tree as $node) {

                if (isset($node['group']['table'])):

                    $key = Format::getAfterSpecChar($node['label'], '_tg', 3);
                    $tmp = [];

                    usort($node['group']['table'], function ($a, $b) {
                        return $a['order'] <=> $b['order'];
                    });
                    foreach ($node['group']['table'] as $row):
                        $_key = Format::getAfterSpecChar($row['href'], '#');
                        $tmp[$_key] = $row;
                    endforeach;


                    self::$groupTable[$key] = $tmp;

                elseif (isset($node['group'])):

                    self::extractGroupTable($node['group']);

                endif;
            }
        }
    }


}
