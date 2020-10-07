<?php

namespace AReportDpmXBRL;

use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\Render\RenderOutput;
use AReportDpmXBRL\Render\RenderPDF;
use AReportDpmXBRL\Render\RenderHtmlTable;


/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 * Tools for rendering logical structure of the table(s).
 * ver 1.0.
 *
 */

/**
 * Class Set
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class Set
{

    public $schema;
    public $imports = [];
    public $namespace = [];
    public $elements = [];
    private $linkbase = [];
    private $linkArray = [];
    private $assertion = null;


    /*
     * @void set $linkArray
     */

    private function setLinkArray($arr)
    {
        if (!empty($arr)):
            $this->linkArray = $arr;
        else:
            $this->linkArray = Library\Data::getLangSpec('all');
        endif;
    }

    public function __construct($basePath, $arr = NULL, $assertion = NULL)
    {

        $this->setLinkArray($arr);
        $this->schema = DomToArray::invoke($basePath);
        $this->getImports();
        $this->getNamespace();
        $this->getLinkbases();
        $this->getElements();
        $this->assertion = $assertion;
    }

    /**
     * Get imports schema
     * @void  set $this->imports
     */
    private function getImports()
    {

        $xPath = new \DOMXPath($this->schema);

        $xPath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        $imports = $xPath->evaluate("//xs:schema/xs:import");

        foreach ($imports as $import) {

            $this->imports[$import->getAttribute('namespace')] = $import->getAttribute('schemaLocation');
        }
    }

    /**
     * @void set Namespace
     */
    private function getNamespace()
    {

        $xPath = new \DOMXPath($this->schema);

        $xPath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

        $context = $this->schema->documentElement;

        foreach ($xPath->query('namespace::*', $context) as $node) {

            $this->namespace[$node->prefix] = $node->nodeValue;
        }
    }

    /**
     * Used for fws.xsd
     * @void set Elements
     */
    private function getElements()
    {

        $xPath = new \DOMXPath($this->schema);

        $xPath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

        $context = $this->schema->documentElement;

        foreach ($xPath->query('//xs:element', $context) as $node) {
            $id = $node->getAttribute('id');
            $this->elements[$id]['name'] = $node->getAttribute('name');
            $this->elements[$id]['abstract'] = $node->getAttribute('abstract');
            $this->elements[$id]['substitutionGroup'] = $node->getAttribute('substitutionGroup');
            $this->elements[$id]['type'] = $node->getAttribute('type');
            $this->elements[$id]['periodType'] = $node->getAttribute('xbrli:periodType');
            $this->elements[$id]['nillable'] = $node->getAttribute('nillable');
            $this->elements[$id]['creationDate'] = $node->getAttribute('model:creationDate');
            $this->elements[$id]['id'] = $node->getAttribute('id');
        }

    }

    /**
     * Get namespace
     * @return type
     */
    public function getTargetNamespace()
    {

        $xPath = new \DOMXPath($this->schema);

        $xPath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        $targets = $xPath->evaluate("//xs:schema/@targetNamespace");

        foreach ($targets as $target) {

            return $target->nodeValue;
        }
    }

    /**
     * Get linkbase instance
     * @void set $this->linkbase
     *
     */
    public function getLinkbases()
    {

        $xPath = new \DOMXPath($this->schema);

        $xPath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        $xPath->registerNamespace('link', 'http://www.xbrl.org/2003/linkbase');
        $xPath->registerNamespace('xlink', 'http://www.w3.org/1999/xlink');

        $linkbase = $xPath->evaluate("//xs:schema/xs:annotation/xs:appinfo/link:linkbaseRef");


        foreach ($linkbase as $link) {
            $path = (dirname($this->schema->baseURI) . DIRECTORY_SEPARATOR . $link->getAttribute('xlink:href'));
            if (strpos($path, 'file:/') !== false):
                $path = str_replace('file:/', '', $path);
            endif;
            if (file_exists($path)):
                $this->getXbrlFileType($path, basename($this->schema->baseURI, ".xsd"));
            endif;
        }

    }

    /**
     * @param string $linkBasePath
     * Based on the xml file name, it determines the file type.
     */
    private function getXbrlFileType($linkBasePath, $name = NULL)
    {

        $file = pathinfo($linkBasePath);


        foreach ($this->linkArray as $key => $link):

            if ($file['filename'] == $name . '-' . $link):
                $this->linkbase[$key] = $linkBasePath;

            else:

                $this->linkbase[$file['filename']] = $linkBasePath;


            endif;
        endforeach;

    }

    /**
     * @param string $basePath
     * @param string $schemaName
     * @return Schema
     */
    public function load()
    {
        if (!empty($this->linkbase)):
            return new LinkBase($this->linkbase, $this->assertion);
        endif;
    }





}
