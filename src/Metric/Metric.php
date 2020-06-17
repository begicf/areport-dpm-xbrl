<?php

namespace AReportDpmXBRL\Metric;

use AReportDpmXBRL\Config\Config;
use AReportDpmXBRL\Domain\Domain;
use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\Library\Data;
use AReportDpmXBRL\Library\Directory;
use AReportDpmXBRL\Library\Format;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Class Metric
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class Metric
{


    private static $path;
    private static $dom;

    private static function _setPath($path)
    {
        self::$path = $path;
    }

    private static function _setDom()
    {
        self::$dom = DomToArray::invoke(self::$path);
    }

    public static function getMetric($path, $xpathQuery)
    {

        if ((strpos($path, "http") !== false) or (strpos($path, "https") !== false)):

            $path =
                Config::publicDir() . Directory::getRootName($path) . DIRECTORY_SEPARATOR . substr($path, strpos($path, 'http://') + 7);

        endif;

        if (file_exists($path)):


            self::_setPath($path);
            self::_setDom();


            $xpath = new \DomXpath(self::$dom);

            foreach ($xpath->query('//xs:element[@id="' . $xpathQuery . '"]') as $element) {

                $metric['type_metric'] = $element->getAttribute('type');
                $metric['substitutionGroup'] = $element->getAttribute('substitutionGroup');
                $metric['periodType'] = $element->getAttribute('xbrli:periodType');
                $metric['nillable'] = $element->getAttribute('nillable');
                $metric['fromDate'] = $element->getAttribute('model:fromDate');
                $metric['creationDate'] = $element->getAttribute('model:fromDate');
                $metric['hierarchy'] = $element->getAttribute('model:hierarchy');
                $metric['modificationDate'] = $element->getAttribute('model:modificationDate');
                $metric['domain'] = $element->getAttribute('model:domain');
            }

            if (!empty($metric['domain'])):

                $hier = self::hier($metric['domain'], $xpath);

            endif;

            $host = Format::getHostFromUrl($metric['hierarchy']);

            if (isset($hier[$host]['def'][$metric['hierarchy']])):

                $mem = Domain::mem(dirname($hier[$host]['dir']) . DIRECTORY_SEPARATOR . 'mem.xsd');

                $metric['namespace'] = $mem['namespace'];
                $metric['imports'] = $mem['imports'];


                $metric['presentation'] = Domain::getHierarchyPresentation($metric['hierarchy'], $hier[$host], $mem);

            endif;


            return $metric;
        endif;
        return [];
    }

    private static function getTargetNamespace()
    {

        $xPath = new \DOMXPath(self::$dom);

        $xPath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        $targets = $xPath->evaluate("//xs:schema/@targetNamespace");

        foreach ($targets as $target) {

            return $target->nodeValue;
        }
    }

    private static function hier($domain, $xpath)
    {

        $_domain = substr(strstr($domain, ':'), 1);
        $hier = array();
        foreach ($xpath->query('//xs:import') as $import):


            $namespace = $import->getAttribute('namespace');

            if (strpos($namespace, $_domain) !== false) :

                $schema = $import->getAttribute('schemaLocation');


                if ((strpos($schema, "http") !== false) or (strpos($schema, "https") !== false)):

                    $dir = self::normalizePath($schema);
                else:
                    $dir = dirname(self::$path) . DIRECTORY_SEPARATOR . $schema;
                endif;

                $_prefix = Directory::getOwnerUrl($dir);

                $hier[$_prefix] = Data::getTax($dir);


                $hier[$_prefix]['dir'] = $dir;

            endif;


        endforeach;


        return $hier;
    }

    /**
     * @param $url
     * @return string
     */
    private static function normalizePath($url): string
    {

        $tmpUrl = parse_url($url);

        $path =
            Config::publicDir() . Directory::getRootName(self::$path) . DIRECTORY_SEPARATOR . $tmpUrl['host'] . $tmpUrl['path'];

        return $path;
    }


}
