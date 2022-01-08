<?php

namespace AReportDpmXBRL\Link;

use DOMXPath;
use AReportDpmXBRL\Dimension\Dimension;
use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\Metric\Metric;
use AReportDpmXBRL\XbrlInterface;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Class DefinitionLink
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class DefinitionLink implements XbrlInterface
{

    //Sets the elements to the next sequence

    private $def;
    private $hyp;
    private $dom;
    private $path;
    public $Xbrl;

    public function __construct($path)
    {

        $this->dom = new DomToArray();
        $this->arrayPath = $this->dom->invoke($path);

        $this->path = $path;

        $context = $this->arrayPath->documentElement;
        $xpath = new DOMXPath($this->arrayPath);

        foreach ($xpath->query('//link:definitionLink', $context) as $element) {

            $def = array();
            foreach ($element->childNodes as $node):


                if ($node instanceof \DOMElement):
                    if ($node->tagName == 'link:loc'):
                        $def[$node->tagName][] = [
                            'type' => $node->getAttribute('xlink:type'),
                            'href' => $node->getAttribute('xlink:href'),
                            'label' => $node->getAttribute('xlink:label')];

                    elseif ($node->tagName == 'link:definitionArc'):

                        $def[$node->tagName][] = [
                            'type' => $node->getAttribute('xlink:type'),
                            'arcrole' => $node->getAttribute('xlink:arcrole'),
                            'from' => $node->getAttribute('xlink:from'),
                            'to' => $node->getAttribute('xlink:to'),
                            'usable' => $node->getAttribute('xbrldt:usable'),
                            'contextElement' => $node->getAttribute('xbrldt:contextElement'),
                            'closed' => $node->getAttribute('xbrldt:closed'),
                            'targetRole' => $node->getAttribute('xbrldt:targetRole'),
                            'order' => $node->getAttribute('order'),];
                    endif;

                endif;

            endforeach;

            $this->def[$element->getAttribute('xlink:role')] = $def;
        };
        foreach ($this->def as $key => $hyp):


            $scenario = $this->dom->search_multdim($hyp['link:definitionArc'], 'contextElement', 'scenario');


            $loc = $this->reduceLocLable($hyp['link:loc']);

            if ($scenario):
                $metric = call_user_func_array('array_merge', array_values($scenario));

                $this->hyp[$key] = $this->buildHyp($hyp['link:definitionArc'], $metric['from'], $loc);
            else:

                $this->hyp[$key] = $hyp;
            endif;

        endforeach;

        $this->Xbrl = $this->hyp;

    }

    private function buildHyp(array $elements, $from = 0, $loc, $prev = '')
    {
        $branch = array();

        foreach ($elements as $element) {
            if ($element['from'] == $from) {
                $children = $this->buildHyp($elements, $element['to'], $loc, $loc[$element['from']]['key']);

                if ($children):

                    if ($element['contextElement'] == 'scenario'):
                        $metric=array();
                        $pathMetric =
                            dirname($this->path) . DIRECTORY_SEPARATOR . strtok($loc[$element['from']]['href'], "#");


                        $xpath =
                            substr($loc[$element['from']]['href'], strpos($loc[$element['from']]['href'], "#") + 1);
                        $metric = Metric::getMetric($pathMetric, $xpath);

                        $branch[$loc[$element['from']]['key']] =
                            array_merge($element, $metric, array('href' => $loc[$element['from']]['href']));


                    elseif ($element['arcrole'] == 'http://xbrl.org/int/dim/arcrole/hypercube-dimension' && empty($element['targetRole'])):
                        $branch[$loc[$element['from']]['key'] . ':' . $loc[$element['to']]['key']] = $element;

                    else:
                        $branch[$loc[$element['from']]['key']] = $element;
                    endif;

                    foreach ($children as $c):

                        if ($c['arcrole'] == 'http://xbrl.org/int/dim/arcrole/dimension-domain'):
                            $branch[$loc[$c['from']]['key']] = $c;


                        elseif ($c['arcrole'] == 'http://xbrl.org/int/dim/arcrole/hypercube-dimension'):
                            $branch[$loc[$c['from']]['key'] . ':' . $loc[$c['to']]['key']] = $c;

                            if (isset($c['targetRole']) && !empty($c['targetRole'])):
                                $target =
                                    $this->buildHyp($this->def[$c['targetRole']]['link:definitionArc'], $c['to'], $this->reduceLocLable($this->def[$c['targetRole']]['link:loc']), $loc[$c['from']]['key']);

                                $branch = $branch + $target;

                                $branch[$loc[$c['from']]['key'] . ':' . $loc[$c['to']]['key']] = $c;

                            endif;

                        else:
                            $dimKey = (isset($c['parent'])) ? $c['parent'] . ':' : null;
                            $branch[$dimKey . $loc[$c['from']]['key'] . ':' . $loc[$c['to']]['key']] = $c;

                        endif;
                    endforeach;


                else:

                    //if href cointains met.xsd that means that is metric.value
                    if (strpos($loc[$element['to']]['href'], 'met.xsd') !== false):

                        $pathMetric =
                            dirname($this->path) . DIRECTORY_SEPARATOR . strtok($loc[$element['to']]['href'], "#");

                        $xpath = substr($loc[$element['to']]['href'], strpos($loc[$element['to']]['href'], "#") + 1);

                        $metric = Metric::getMetric($pathMetric, $xpath);

                        $branch[$loc[$element['to']]['key']] =
                            array_merge_recursive($element, $metric, array('href' => $loc[$element['to']]['href']));

                    elseif (strpos($loc[$element['to']]['href'], 'dim.xsd') !== false):

                        $pathDim =
                            dirname($this->path) . DIRECTORY_SEPARATOR . strtok($loc[$element['to']]['href'], "#");

                        $xpath = substr($loc[$element['to']]['href'], strpos($loc[$element['to']]['href'], "#") + 1);

                        $dim = Dimension::getDimension($pathDim, $xpath);

                        $branch[$loc[$element['to']]['key']] =
                            array_merge_recursive($element, $dim, array('href' => $loc[$element['to']]['href']));
                    elseif ($element['arcrole'] == 'http://xbrl.org/int/dim/arcrole/hypercube-dimension' && empty($element['targetRole'])):
                        $branch[$loc[$element['from']]['key'] . ':' . $loc[$element['to']]['key']] = $element;
                    else:


                        $branch[$loc[$element['to']]['key']] = $element;

                        $branch[$loc[$element['to']]['key']]['parent'] = $prev;

                    endif;

                endif;
            }
        }

        return $branch;
    }


    /**
     * associates local link names with global ones
     * @param $elements
     * @return array
     */
    private function reduceLocLable($elements)
    {

        $tmp = array();

        foreach ($elements as $row):
            $content = substr($row['href'], strpos($row['href'], "#") + 1);
            if (!empty($content)):
                $tmp[$row['label']] = ['key' => $content, 'href' => $row['href']];
            endif;

        endforeach;

        return $tmp;
    }

}
