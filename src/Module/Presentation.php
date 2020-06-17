<?php

namespace AReportDpmXBRL\Module;

use DOMXPath;
use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\XbrlInterface;

/**
 * Class Presentation
 * @category
 * Areport @package DpmXbrl\Module
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class Presentation implements XbrlInterface
{

    public $Xbrl;
    private $gen;
    private $namespace;

    public function __construct($path)
    {
        $dom = new DomToArray();
        $this->arrayPath = $dom->invoke($path);

        $context = $this->arrayPath->documentElement;
        $xpath = new DOMXPath($this->arrayPath);


        foreach ($xpath->query('namespace::*', $context) as $node) {

            $xpath->registerNamespace($node->prefix, $node->nodeValue);
            $this->namespace[$node->prefix] = $node->nodeValue;
        }


        foreach ($this->namespace as $prefix => $value):

            switch ($prefix):
                case('gen'):
                    $xpath->registerNamespace($prefix, $value);
                    $arc = $xpath->query("//gen:arc ", $context);

                    foreach ($arc as $element):

                        $this->gen[$element->getAttribute('xlink:to')]['type'] = $element->getAttribute('xlink:type');

                        $this->gen[$element->getAttribute('xlink:to')]['from'] = $element->getAttribute('xlink:from');
                        $this->gen[$element->getAttribute('xlink:to')]['to'] = $element->getAttribute('xlink:to');
                        $this->gen[$element->getAttribute('xlink:to')]['arcrole'] =
                            $element->getAttribute('xlink:arcrole');
                        $this->gen[$element->getAttribute('xlink:to')]['order'] = $element->getAttribute('order');

                    endforeach;
                    break;

                case('link'):

                    $xpath->registerNamespace($prefix, $value);
                    $presentationLink = $xpath->query("//link:presentationLink ", $context);

                    if ($presentationLink->length):

                        foreach ($presentationLink as $element):

                            $role = $element->getAttribute('xlink:role');

                            foreach ($element->childNodes as $el):

                                if ($el->nodeType == 1):

                                    switch ($el->localName):

                                        case('loc'):
                                            $this->link[$role][$el->getAttribute('xlink:label')]['href'] =
                                                $el->getAttribute('xlink:href');
                                            $this->link[$role][$el->getAttribute('xlink:label')]['label'] =
                                                $el->getAttribute('xlink:label');

                                            break;
                                        case('presentationArc'):
                                            $this->link[$role][$el->getAttribute('xlink:to')]['from'] =
                                                $el->getAttribute('xlink:from');
                                            $this->link[$role][$el->getAttribute('xlink:to')]['to'] =
                                                $el->getAttribute('xlink:to');
                                            $this->link[$role][$el->getAttribute('xlink:to')]['order'] =
                                                $el->getAttribute('order');
                                            $this->link[$role][$el->getAttribute('xlink:to')]['arcrole'] =
                                                $el->getAttribute('xlink:arcrole');
                                            break;

                                    endswitch;


                                endif;

                            endforeach;

                        endforeach;

                    else:

                        $link = $xpath->query("//link:loc ", $context);

                        foreach ($link as $element):

                            $this->link[$element->getAttribute('xlink:label')]['type'] = $element->getAttribute('xlink:type');
                            $this->link[$element->getAttribute('xlink:label')]['href'] = $element->getAttribute('xlink:href');
                            $this->link[$element->getAttribute('xlink:label')]['label'] = $element->getAttribute('xlink:label');

                        endforeach;

                    endif;

                    break;


            endswitch;

        endforeach;

        if (is_array($this->gen) && is_array($this->link)):
            $this->Xbrl = array_merge_recursive($this->gen, $this->link);
        else:
            $this->Xbrl = $this->link;
        endif;

        $this->Xbrl['path'] = $path;
    }

}
