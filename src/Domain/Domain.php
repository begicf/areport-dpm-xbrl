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
 * Areport @package DpmXbrl\Domain
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class Domain
{

    private static $dom;

    private static function _getPath($path, $root)
    {

        $url = parse_url($path, PHP_URL_PATH);
        $info = substr($url, strpos($url, "role/") + 5);
        $exp = explode('/', $info);
        array_pop($exp);


        $_path = implode(DIRECTORY_SEPARATOR, array_map('strtolower', $exp));


        return current(DomToArray::getPath(Config::publicDir() . $root . DIRECTORY_SEPARATOR, [$_path]));
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


        $_path = self::_getPath($path, $root);


        foreach ($_path as $row):

            if (file_exists($row)):


                if (strpos($row, 'hier.xsd') !== false) {

                    $hier = Data::getTax($row);
                } elseif (strpos($row, 'mem.xsd') !== false) {
                    $mem = self::mem($row);
                }

            endif;
        endforeach;

        $presentation = self::getHierarchyPresentation($path, $hier, $mem);

        return $presentation;
    }

    /**
     * @param $schema
     * @return array
     */
    public static function mem($schema): array
    {

        $_memOwner = array();

        if ((strpos($schema, Config::$owner) == false)):

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


        $pre_ = [];
        $pre__ = [];


        foreach ($hierDef['pre'][$hierarchy] as $key => $hier):
            $search = DomToArray::search_multdim($mem[$lang], 'from', $hier['label']);
            if (!empty($search) && !empty($hier['order'])):

                if (strpos(Format::getAfterSpecChar($hier['href'], '_'), '_') !== false):

                    $pre__['__' . $hier['order']] = array_merge($hier, $search[key($search)]);

                else:
                    $pre_['_' . $hier['order']] = array_merge($hier, $search[key($search)]);
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
