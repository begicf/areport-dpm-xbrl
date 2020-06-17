<?php

namespace AReportDpmXBRL\Gen;

use DOMXPath;
use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\XbrlInterface;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Class TableLinkbase
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class TableLinkbase implements XbrlInterface {

    private $dom;
    private $table;
    public $Xbrl;

    public function __construct($path) {


        $this->dom = DomToArray::invoke($path);

        $this->table['path'] = basename($path);
        $context = $this->dom->documentElement;


        $xpath = new DOMXPath($this->dom);

        $namespace = $xpath->query('namespace::*', $context);
        foreach ($namespace as $node):

            $this->table['namespace'][$node->prefix] = $node->nodeValue;
        endforeach;



        $table = $xpath->query("//table:table", $context);

        foreach ($table as $element):
            $this->table[$element->localName][$element->getAttribute('id')] = [
                'id' => $element->getAttribute('id'),
                'type' => $element->getAttribute('xlink:type'),
                'label' => $element->getAttribute('xlink:label'),
                'aspectModel' => $element->getAttribute('aspectModel'),
            ];

        endforeach;

        $breakdown = $xpath->query("//table:breakdown", $context);

        foreach ($breakdown as $element):
            $this->table[$element->localName][$element->getAttribute('id')] = [
                'id' => $element->getAttribute('id'),
                'type' => $element->getAttribute('xlink:type'),
                'label' => $element->getAttribute('xlink:label'),
                'parentChildOrder' => $element->getAttribute('parentChildOrder'),
            ];

        endforeach;


        $breakdownTreeArc = $xpath->query("//table:breakdownTreeArc", $context);

        foreach ($breakdownTreeArc as $element):
            $this->table[$element->localName][$element->getAttribute('xlink:from')] = [
                'from' => $element->getAttribute('xlink:from'),
                'to' => $element->getAttribute('xlink:to'),
                'type' => $element->getAttribute('xlink:type'),
                'arcrole' => $element->getAttribute('xlink:arcrole'),
                'order' => $element->getAttribute('order'),
            ];

        endforeach;


        $definitionNodeSubtreeArc = $xpath->query("//table:definitionNodeSubtreeArc", $context);

        foreach ($definitionNodeSubtreeArc as $element):
            $this->table[$element->localName][$element->getAttribute('xlink:to')] = [
                'from' => $element->getAttribute('xlink:from'),
                'to' => $element->getAttribute('xlink:to'),
                'type' => $element->getAttribute('xlink:type'),
                'arcrole' => $element->getAttribute('xlink:arcrole'),
                'order' => $element->getAttribute('order'),
            ];

        endforeach;


        $tableBreakdownArc = $xpath->query("//table:tableBreakdownArc", $context);

        foreach ($tableBreakdownArc as $element):
            $this->table[$element->localName][$element->getAttribute('xlink:to')] = [
                'from' => $element->getAttribute('xlink:from'),
                'to' => $element->getAttribute('xlink:to'),
                'type' => $element->getAttribute('xlink:type'),
                'arcrole' => $element->getAttribute('xlink:arcrole'),
                'axis' => $element->getAttribute('axis'),
                'order' => $element->getAttribute('order'),
            ];

        endforeach;



        $ruleNode = $xpath->query("//table:ruleNode", $context);

        foreach ($ruleNode as $element):


            $dimension = $element->getElementsByTagName("explicitDimension");
            $dimArr = array();
            foreach ($dimension as $dim):

                $dimArr[$dim->getAttribute("dimension")] = trim($dim->nodeValue);
            endforeach;


            $concept = $element->getElementsByTagName("concept");

            $this->table[$element->localName][$element->getAttribute('id')] = [
                'id' => $element->getAttribute('id'),
                'concept' => ($concept->length != 0) ? trim($concept[0]->nodeValue) : "false",
                'abstract' => ($element->getAttribute('abstract') == true) ? $element->getAttribute('abstract') : "false",
                'dimension' => (count($dimArr)) ? $dimArr : "false",
            ];

        endforeach;



        $aspectNode = $xpath->query("//table:aspectNode", $context);

        foreach ($aspectNode as $element):



            $this->table[$element->localName][$element->getAttribute('id')] = [
                'id' => $element->getAttribute('id'),
                'type' => $element->getAttribute('xlink:type'),
                'label' => $element->getAttribute('xlink:label'),
                'dimensionAspect' => trim($element->nodeValue)
            ];

        endforeach;



        $aspectNodeFilterArc = $xpath->query("//table:aspectNodeFilterArc", $context);

        foreach ($aspectNodeFilterArc as $element):
            $this->table[$element->localName][$element->getAttribute('xlink:from')] = [
                'from' => $element->getAttribute('xlink:from'),
                'to' => $element->getAttribute('xlink:to'),
                'type' => $element->getAttribute('xlink:type'),
                'arcrole' => $element->getAttribute('xlink:arcrole'),
                'complement' => $element->getAttribute('complement'),

            ];

        endforeach;



        $dimensionFilters = $xpath->query("//df:explicitDimension", $context);

        // Z axis, only one dimension
        foreach ($dimensionFilters as $element):

            $dimArr = [];
            $dim = trim($element->getElementsByTagName('dimension')->item(0)->nodeValue);


            $dimArr[$dim] = $element->getElementsByTagName('qname')->item(1)->nodeValue;


            $this->table[$element->localName][$element->getAttribute('id')] = [
                'id' => $element->getAttribute('id'),
                'label' => $element->getAttribute('xlink:label'),
                'type' => $element->getAttribute('xlink:type'),
                'linkrole' => $element->getElementsByTagName('linkrole')->item(0)->nodeValue,
                'arcrole' => $element->getElementsByTagName('arcrole')->item(0)->nodeValue,
                'axis' => $element->getElementsByTagName('axis')->item(0)->nodeValue,
                'dimension' => $dimArr,
            ];

        endforeach;
        $this->Xbrl = $this->table;
    }

}
