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

            $this->lang = Library\Data::checkLang($mod);

            if (isset($mod['pre'])):
                foreach ($mod['pre'] as $key => $row):


                    if (!isset($row['order']) && isset($row['label'])):

                        $name =
                            (empty($this->lang)) ? $row['label'] : call_user_func_array("array_merge", DomToArray::search_multdim($mod[$this->lang], 'from', $row['label']));
                        $data[] = [
                            'parent' => $id,
                            'children' => true,
                            'data' => $path,
                            'id' => $id . '#' . $row['label'],
                            'ext' => 'tab',
                            "text" => (empty($this->lang)) ? $row['label'] : $name['@content'],
                            "mod" => ((is_file($mod['mod_path'])) ? $mod['mod_path'] : Config::publicDir() . DIRECTORY_SEPARATOR . $mod['mod_path']),
                            'type' => 'mod'
                        ];

                    endif;

                endforeach;
            endif;
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

                            $ext_code = null;
                            if (isset($linkSource['lab-codes'])):
                                $ext_code =
                                    DomToArray::search_multdim_multival($linkSource['lab-codes'], $row['label'], 'http://www.eba.europa.eu/xbrl/role/dpm-db-id');

                            endif;

                            //Get XBRL specification destination
                            $linkDestination = Data::getTax($getFileXsdSource, Data::getLangSpec('mod'));

                            $link = array_merge($linkSource, $linkDestination);
                            //  echo "<pre>", print_r($link), "</pre>";

                            $this->lang = Library\Data::checkLang($link);


                            try {

                                $name =
                                    (empty($this->lang)) ? $row['label'] : current(DomToArray::search_multdim($link[$this->lang], 'from', $row['label']));

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
                            'ext_code' => $ext_code,
                            "text" => (empty($name)) ? $row['href'] : $name['@content'],
                            "table_xsd" => $pathXsd,
                            'type' => $type
                        ];

                    endif;

                endforeach;
            endif;

        endforeach;

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

        foreach ($modules['mod'] as $key => $mod):

            $module[$key] = Data::getTax($mod);
            $module[$key]['mod_path'] = Normalise::taxPath($mod);

        endforeach;

        return $module;

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
