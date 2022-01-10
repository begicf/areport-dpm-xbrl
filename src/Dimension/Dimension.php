<?php

namespace AReportDpmXBRL\Dimension;

use AReportDpmXBRL\Config\Config;
use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\Library\Directory;
use AReportDpmXBRL\Library\Format;
use AReportDpmXBRL\Library\Normalise;



/**
 * Class Dimension
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 10/01/2022
 */

class Dimension
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

    public static function getDimension($path, $xpathQuery): ?array
    {

        if ((strpos($path, "http") !== false) or (strpos($path, "https") !== false)):

            $path =
                Config::publicDir() . Directory::getRootName($path) . DIRECTORY_SEPARATOR . substr($path, strpos($path, 'http://') + 7);

        endif;

        $dimension = array();

        if (!file_exists($path)){
            throw new \Exception("File not found! {$path} ");
        }


            self::_setPath($path);
            self::_setDom();


            $xpath = new \DomXpath(self::$dom);


            foreach ($xpath->query('//xs:element[@id="' . $xpathQuery . '"]') as $element) {

                $dimension['name'] = $element->getAttribute('name');
                $dimension['abstract'] = $element->getAttribute('abstract');
                $dimension['substitutionGroup'] = $element->getAttribute('substitutionGroup');
                $dimension['type'] = $element->getAttribute('type');
                $dimension['periodType'] = $element->getAttribute('xbrli:periodType');
                $dimension['nillable'] = $element->getAttribute('nillable');
                $dimension['typedDomainRef'] = $element->getAttribute('xbrldt:typedDomainRef');
                $dimension['fromDate'] = $element->getAttribute('model:fromDate');
                $dimension['creationDate'] = $element->getAttribute('model:fromDate');
                $dimension['id'] = $element->getAttribute('id');
            }

            if (!empty($dimension['typedDomainRef'])):

                $dim = dirname(Normalise::_normalise($path));
                $type = strtok($dimension['typedDomainRef'], '#');

                $tmp_path = Normalise::_normalise($dim . DIRECTORY_SEPARATOR . $type);
                $typePath = (file_exists($tmp_path))? $tmp_path :  '/'.$tmp_path;

                $typ = self::getDimension($typePath, Format::getAfterSpecChar($dimension['typedDomainRef'], '#'));

                $namespace = self::getNamespace();

                $key = array_search(self::getTarget(), $namespace);
                $typ['key'] = $key;
                $dimension['namespace'] = $namespace;
                $dimension['typ'] = $typ;


            endif;

            return $dimension;

    }

    private static function getNamespace()
    {
        $namespace = array();

        $xPath = new \DOMXPath(self::$dom);

        $xPath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

        $context = self::$dom->documentElement;

        foreach ($xPath->query('namespace::*', $context) as $node) {

            $namespace[$node->prefix] = $node->nodeValue;
        }

        return $namespace;
    }

    private static function getTarget()
    {

        $context = self::$dom->documentElement;

        return $context->getAttribute('targetNamespace');
    }

}
