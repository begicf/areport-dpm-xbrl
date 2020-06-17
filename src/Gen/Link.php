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
 * Class Link
 * @category
 * Areport @package DpmXbrl\Gen
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class Link implements XbrlInterface
{

    public $Xbrl;
    private $label, $gen, $link;
    private $namespace;
    private $dom;

    public function __construct($path)
    {


        $this->dom = DomToArray::invoke($path);


        $context = $this->dom->documentElement;
        $xpath = new DOMXPath($this->dom);

        foreach ($xpath->query('namespace::*', $context) as $node) {

            $xpath->registerNamespace($node->prefix, $node->nodeValue);
            $this->namespace[$node->prefix] = $node->nodeValue;
        }


        foreach ($this->namespace as $prefix => $value):
            switch ($prefix):
                case 'label':
                    $xpath->registerNamespace($prefix, $value);
                    $label = $xpath->query("//label:label", $context);

                    foreach ($label as $element):


                        $this->label[$element->getAttribute('xlink:label')]['lang'] =
                            $element->getAttribute('xml:lang');
                        $this->label[$element->getAttribute('xlink:label')]['@content'] = $element->nodeValue;
                        $this->label[$element->getAttribute('xlink:label')]['role'] =
                            $element->getAttribute('xlink:role');


                    endforeach;

                    break;
                case 'gen':

                    $xpath->registerNamespace($prefix, $value);
                    $arc = $xpath->query("//gen:arc ", $context);

                    foreach ($arc as $element):
                        $this->gen[$element->getAttribute('xlink:to')]['to'] = $element->getAttribute('xlink:to');
                        $this->gen[$element->getAttribute('xlink:to')]['from'] = $element->getAttribute('xlink:from');
                        $this->gen[$element->getAttribute('xlink:to')]['arcrole'] =
                            $element->getAttribute('xlink:arcrole');
                        $this->gen[$element->getAttribute('xlink:to')]['type'] = $element->getAttribute('xlink:type');
                    endforeach;


                    break;
                case 'link':
                    $xpath->registerNamespace($prefix, $value);
                    $link = $xpath->query("//*[namespace-uri()='$value'] ");


                    foreach ($link as $element):

                        if ($element->tagName == 'link:loc'):
                            $this->link[$element->getAttribute('xlink:label')]['href'] =
                                $element->getAttribute('xlink:href');
                            $this->link[$element->getAttribute('xlink:label')]['type'] =
                                $element->getAttribute('xlink:type');
                        elseif ($element->tagName == 'link:label'):

                            $this->link[$element->getAttribute('xlink:label')]['@content'] = $element->nodeValue;
                            $this->link[$element->getAttribute('xlink:label')]['lang'] =
                                $element->getAttribute('xml:lang');
                        elseif ($element->tagName == 'link:labelArc'):
                            $this->link[$element->getAttribute('xlink:to')]['to'] = $element->getAttribute('xlink:to');
                            $this->link[$element->getAttribute('xlink:to')]['from'] =
                                $element->getAttribute('xlink:from');
                        endif;

                    endforeach;

                    break;

            endswitch;
        endforeach;

        if (array_key_exists('gen', $this->namespace)):

            if (!empty($this->gen) && !empty($this->label)):
                $this->Xbrl = array_merge_recursive($this->gen, $this->label);
            elseif (!empty($this->gen) && is_array($this->link)):
                $this->Xbrl = array_merge_recursive($this->gen, $this->link);
            endif;


            if (is_array($this->link)):
                foreach ($this->link as $key => $row):

                    $found = DomToArray::search_multdim($this->Xbrl, 'from', $key);

                    if ($found):
                        foreach ($found as $k => $fo):
                            $this->Xbrl[$k]['href'] = $row['href'];
                        endforeach;

                    endif;

                endforeach;
            endif;
        else:

            $this->Xbrl = $this->link;
            foreach ($this->link as $key => $row):
                $found = DomToArray::search_multdim($this->link, 'from', $key);

                if ($found):
                    $this->Xbrl[key($found)]['href'] = $row['href'];
                    unset($this->Xbrl[$key]);
                endif;

            endforeach;


        endif;
    }

}
