<?php

namespace AReportDpmXBRL\Creat;

use AReportDpmXBRL\Config\Config;
use AReportDpmXBRL\Library\Data;
use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\Set;
use XMLWriter;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Class CreateXBRLFromDB
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class CreateXBRLFromDB extends XMLWriter
{

    //put your code here

    private $context;
    private $date;
    private $organisation;
    private $schemaRef;
    private $fIndicators;
    private $tempContext = [];
    private $id = 1;
    private $Metric = [];
    private $unit = [];
    private $namespace = [];
    private $typedMember;
    private $aspectNode = [];
    private $moduleDir;

    private $defaultMI;

    /**
     * CreateXBRLFromDB constructor.
     * @param $data
     * @param $organisation
     * @param $schemaRef
     * @param $context
     * @param null $fIndicators
     * @param null $moduleDir
     */
    public function __construct($data, $organisation, $schemaRef, $context, $fIndicators = null, $moduleDir = null)
    {

        $this->date = $data;
        $this->schemaRef = $schemaRef;
        $this->context = $context;
        $this->fIndicators = $fIndicators;
        $this->moduleDir = $moduleDir;
        $this->organisation = $organisation;

        $this->defaultMI = Config::$monetaryItem;
    }

    public function writeXbrl()
    {
        $this->openMemory();

        $this->XbrlHeader();
        $this->XbrlXbrli();
        $this->XbrlSchemaRef();
        $this->XbrlUnit();
        $this->XbrlCfinContext();
        $this->XbrlContext();
        $this->XbrlMetric();
        $this->XbrlFind();

        $this->endElement();


        return $this->outputMemory();
    }

    private function XbrlHeader()
    {

        $this->setIndent(true);
        $this->setIndentString(' ');
        $this->startDocument('1.0', 'UTF-8');
        $this->writeComment('AREPORT v.1.0');
    }

    private function traverseArray($array, $target = NULL)
    {

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if ($key === 'namespace'):

                    foreach ($value as $k => $r):
                        $this->namespace[$k] = $r;
                    endforeach;

                elseif ($key === 'typ'):

                    $this->typedMember[$array['name']] = $value;
                elseif ($key === 'aspectNode'):

                    $this->aspectNode[$target] = $value;

                else:
                    $tN = NULL;
                    if (isset($array['targetNamespace'])):
                        $tN = $array['targetNamespace'];
                    endif;
                    $this->traverseArray($value, $tN);
                endif;
            } else {

            }
        }
    }

    private function XbrlXbrli()
    {


        $this->startElementNS(
            'xbrli', 'xbrl', 'http://www.xbrl.org/2003/instance'
        );
        $this->writeAttributeNS(
            'xmlns', 'iso4217', NULL, 'http://www.xbrl.org/2003/iso4217'
        );
        $this->writeAttributeNS(
            'xmlns', 'xbrldi', NULL, 'http://xbrl.org/2006/xbrldi'
        );

        $this->writeAttributeNS(
            'xmlns', 'find', NULL, 'http://www.eurofiling.info/xbrl/ext/filing-indicators'
        );


        $tax = array();
        if (!is_null($this->fIndicators)):
            foreach ($this->fIndicators as $k => $rows):

                $xbrl =
                    new Set(Config::publicDir() . $this->moduleDir . DIRECTORY_SEPARATOR . $rows, Config::$createInstance);

                $tax[$k]['targetNamespace'] = $xbrl->getTargetNamespace();
                foreach ($xbrl->load() as $key => $row):

                    $tax[$k][$key] = $row->Xbrl;


                endforeach;

            endforeach;

            $this->traverseArray($tax);
        endif;


        ksort($this->namespace);

        $unset = ['model', 'table', 'formula', 'df', 'gen', 'label', 'xml', 'xsi', 'nonnum', 'xbrli', 'xs', 'xbrldt'];

        $namespace = array_diff_key($this->namespace, array_flip($unset));

        foreach ($namespace as $key => $row):
            $this->writeAttributeNS(
                'xmlns', $key, NULL, $row
            );
        endforeach;

    }

    private function XbrlSchemaRef()
    {
        $this->startElementNS('link', 'schemaRef', NULL);
        $this->writeAttributeNS(
            'xlink', 'type', NULL, 'simple'
        );
        $this->writeAttributeNS(
            'xlink', 'href', NULL, "http://" . strstr($this->schemaRef, 'www')
        );
        $this->endElement();
    }

    // UNIT
    private function XbrlUnit()
    {

        foreach ($this->context as $row):

            if (!empty($row['metric'])):

                if (!array_key_exists($row['metric'], $this->unit)):

                    //Setuje ID elemente kod Unit-a
                    $this->unit[$row['metric']] = $this->setID($row['metric']);


                endif;

                if (!array_key_exists($row['sheetcode'], $this->unit)):

                    $this->unit[$row['sheetcode']] = $row['sheetcode'];

                endif;


            endif;
        endforeach;


        $this->writeUnit();
    }

    // UNIT set ID
    private function setID($unit)
    {


        switch ($unit):
            case stripos($unit, ':mi') !== false:
                return ['typ' => $this->defaultMI, 'decimals' => -3];
            case stripos($unit, ':pi') !== false:
                return ['typ' => 'upure', 'decimals' => 4, 'format' => 'pi'];
            case stripos($unit, ':ii') !== false:
                return ['typ' => 'upure', 'decimals' => 0, 'format' => 'int'];
            default:
                return 'false';

        endswitch;
    }

    private function writeUnit()
    {

        $unit = array();

        foreach ($this->unit as $key => $row):

            if (isset($row['typ'])):
                $unit[$row['typ']] = $key;
            else:
                $unit[$key] = $row;
            endif;

        endforeach;

        foreach ($unit as $key => $row):
            switch ($key):
                case $this->defaultMI:
                    $this->startElementNS('xbrli', 'unit', NULL);
                    $this->writeAttribute(
                        'id', 'uEUR'
                    );
                    $this->writeElementNs('xbrli', 'measure', NULL, 'iso4217:EUR');
                    $this->endElement();
                    break;
                case 'upure':
                    $this->startElementNS('xbrli', 'unit', NULL);
                    $this->writeAttribute(
                        'id', 'upure'
                    );
                    $this->writeElementNs('xbrli', 'measure', NULL, 'xbrli:pure');
                    $this->endElement();
                    break;
                default:

                    if (!is_numeric($key)):
                        if ($row != 'false'):
                            $this->startElementNS('xbrli', 'unit', NULL);
                            $this->writeAttribute(
                                'id', 'u' . $key
                            );
                            $this->writeElementNs('xbrli', 'measure', NULL, 'iso4217:' . $key);
                            $this->endElement();

                        endif;
                    endif;
                    break;
            endswitch;
        endforeach;
    }

    private function XbrlCfinContext()
    {

        $this->startElementNS('xbrli', 'context', NULL);
        $this->writeAttribute(
            'id', 'cfin'
        );

        //entity
        $this->startElementNS('xbrli', 'entity', NULL);
        $this->startElementNS('xbrli', 'identifier', NULL);
        $this->writeAttribute(
            'scheme', 'http://standards.iso.org/iso/17442'
        );
        $this->writeRaw($this->organisation);
        $this->endElement();
        $this->endElement();

        //period
        $this->startElementNS('xbrli', 'period', NULL);
        $this->writeElementNS('xbrli', 'instant', NULL, date('Y-m-d', strtotime($this->date)));
        $this->endElement();
        $this->endElement();
    }

    private function XbrlContext()
    {
        $i = 1;

        foreach ($this->context as $key => $row):

            if (!empty($row['metric'])):


                $tmpId = array_search($row['context'], $this->tempContext, TRUE);

                if ($tmpId === false):
                    $this->Metric[$i]['contextRef'] = $this->id;
                else:
                    $this->Metric[$i]['contextRef'] = $tmpId + 1;
                endif;


                $this->Metric[$i]['metric'] = $row['metric'];
                $this->Metric[$i]['numeric_value'] = $row['numeric_value'];
                $this->Metric[$i]['string_value'] = $row['string_value'];
                $this->Metric[$i]['sheetcode'] = $row['sheetcode'];

                $arr = json_decode($row['context'], true);


                if (in_array($row['context'], $this->tempContext) == FALSE):

                    $this->tempContext[] = $row['context'];

                    //context
                    $this->startElementNS('xbrli', 'context', NULL);
                    $this->writeAttribute(
                        'id', 'c' . $this->id
                    );

                    //entity
                    $this->startElementNS('xbrli', 'entity', NULL);
                    $this->startElementNS('xbrli', 'identifier', NULL);
                    $this->writeAttribute(
                        'scheme', 'http://standards.iso.org/iso/17442'
                    );
                    $this->writeRaw($this->organisation);
                    $this->endElement();
                    $this->endElement();

                    //period
                    $this->startElementNS('xbrli', 'period', NULL);
                    $this->writeElementNS('xbrli', 'instant', NULL, date('Y-m-d', strtotime($this->date)));
                    $this->endElement();

                    //scenario
                    $this->startElement('xbrli:scenario');


                    foreach ($arr as $k => $scenario):


                        if (!is_array($scenario) && strpos($scenario, ':x0') == false):

                            $this->startElementNS('xbrldi', 'explicitMember', NULL);
                            $this->writeAttribute('dimension', $k);
                            $this->writeRaw($scenario);
                            $this->endElement();

                        elseif (is_array($scenario)):

                            foreach ($scenario as $member => $typ):

                                $this->startElementNS('xbrldi', 'typedMember', NULL);

                                $this->writeAttribute('dimension', $member);

                                $typMember = explode(':', $typ['typ']);

                                $this->startElementNS($typMember[0], $typMember[1], NULL);

                                $this->writeRaw($typ['value']);
                                $this->endElement();
                                $this->endElement();

                            endforeach;

                        endif;

                    endforeach;

                    $this->id++;
                    $this->endElement();
                    $this->endElement();

                endif;

                $i++;
            endif;
        endforeach;
    }

    private function XbrlMetric()
    {

        foreach ($this->Metric as $key => $row):

            if (isset($row['metric'])):


                $this->startElement($row['metric']);

                $this->writeAttribute(
                    'contextRef', 'c' . $row['contextRef']
                );

                if (isset($this->unit[$row['metric']]['typ'])):
                    switch ($this->unit[$row['metric']]['typ']):

                        case $this->defaultMI:

                            if (is_numeric($row['sheetcode'])):
                                $this->writeAttribute('decimals', '-3');
                                $this->writeAttribute('unitRef', 'u' . Config::$monetaryItem);
                                $this->writeRaw($row['numeric_value'] * 1000);
                            else:

                                $this->writeAttribute('decimals', '-3');
                                $this->writeAttribute('unitRef', 'u' . $row['sheetcode']);
                                $this->writeRaw($row['numeric_value'] * 1000);


                            endif;

                            break;
                        case 'upure':
                            if ($this->unit[$row['metric']]['format'] == 'pi'):
                                $this->writeAttribute('decimals', $this->unit[$row['metric']]['decimals']);
                                $this->writeAttribute('unitRef', 'upure');
                                $this->writeRaw($row['numeric_value'] / 10000);
                            else:
                                $this->writeAttribute('decimals', $this->unit[$row['metric']]['decimals']);
                                $this->writeAttribute('unitRef', 'upure');
                                $this->writeRaw((int)$row['numeric_value']);
                            endif;
                            break;
                        default:
                            $this->writeRaw($row['string_value']);
                            break;
                    endswitch;
                else:
                    $this->writeRaw($row['string_value']);
                endif;


                $this->endElement();
            endif;
        endforeach;
    }

    private function XbrlFind()
    {
        $this->startElementNS('find', 'fIndicators', NULL);


        $module =
            new Set(Config::publicDir() . $this->moduleDir . DIRECTORY_SEPARATOR . $this->schemaRef, Config::$moduleSet);


        $mod = array();

        foreach ($module->load() as $key => $row):
            $mod[$key] = $row->Xbrl;

        endforeach;

        $element = $module->schema->getElementsByTagNameNS('http://www.w3.org/2001/XMLSchema', 'element')->item(0);
        $idElement = $element->getAttribute('id');

        $group = $this->filterGroup($mod['pre'], key(Data::searchLabel($mod['pre'], 'href', $idElement)));


        $fillingIndicator = array();
        foreach ($group as $key => $row):

            $find = DomToArray::strpos_arr($row['table'], $this->fIndicators);

            if ($find == TRUE || isset($fillingIndicator[$row['group']])):
                $fillingIndicator[$row['group']] = TRUE;
            else:
                $fillingIndicator[$row['group']] = FALSE;
            endif;


        endforeach;

dump($fillingIndicator);
        foreach ($fillingIndicator as $key => $row):

            $this->startElementNS('find', 'filingIndicator', NULL);
            $this->writeAttribute('contextRef', 'cfin');
            if ($row === FALSE):
                $this->writeAttributeNS('find', 'filed', NULL, "false");
            endif;
            $this->writeRaw($key);

            $this->endElement();
        endforeach;


        $this->endElement();
    }

    private function filterGroup(array $elements, $parentId = '')
    {
        $group = $this->buildGroup($elements, $parentId);
        $filter = array();
        $i = 0;
        foreach ($group as $row):
            if (isset($row['children'])):

                foreach ($row['children'] as $el):

                    $filter[$i]['group'] = substr($row['href'], strrpos($row['href'], '_tg') + 3);

                    $filter[$i]['table'] = strtolower(substr($el['href'], strrpos($el['href'], '_t') + 2));
                    $i++;
                endforeach;

            endif;

        endforeach;

        foreach ($this->fIndicators as $row):


        endforeach;
        
        return $filter;
    }

    private function buildGroup(array $elements, $parentId = '')
    {
        $branch = array();

        foreach ($elements as $element) {
            if (isset($element['from']) && $element['from'] == $parentId) {
                $children = $this->buildGroup($elements, $element['to']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }

}
