<?php

namespace AReportDpmXBRL\Creat;

use AReportDpmXBRL\Config\Config;
use AReportDpmXBRL\Library\FactValue;
use AReportDpmXBRL\Library\MetricTypes;
use AReportDpmXBRL\Library\ModuleInfo;
use AReportDpmXBRL\Library\Data;
use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\Library\Format;
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
    private $fIndicators = [];
    private $tempContext = [];
    private $writtenFacts = [];
    private $id = 1;
    private $Metric = [];
    private $unit = [];
    private $namespace = [];
    private $typedMember;
    private $aspectNode = [];
    private $moduleDir;

    private $defaultMI;

    /** Filing options; see setOptions(). */
    private $options = [
        'baseCurrency' => null,      // ISO 4217, defaults to Config::$monetaryItem
        'monetaryScale' => 1000,     // form amounts are in thousands
        'decimalsMonetary' => -3,
        'decimalsPercentage' => 4,
        'decimalsInteger' => 0,
        'decimalsDecimal' => 2,
        'filingIndicators' => [],    // code => bool; false is written as find:filed="false"
    ];

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

    /** Override filing options (currency, scale, decimals, filing indicators). */
    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, array_intersect_key($options, $this->options));

        return $this;
    }

    public function writeXbrl()
    {
        $this->defaultMI = strtoupper((string) ($this->options['baseCurrency'] ?: Config::$monetaryItem));
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


        // schemaRef always needs these, even for an instance without table data
        // (they used to come only from the tables' schemas → malformed XML).
        $this->namespace += ['link' => 'http://www.xbrl.org/2003/linkbase', 'xlink' => 'http://www.w3.org/1999/xlink'];

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
            'xlink', 'href', NULL, $this->toOfficialUrl($this->schemaRef)
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


        // The metric's XBRL type from the taxonomy dictionary (DPM 2.0 names like
        // eba_met:qABJ carry no type), falling back to DPM 1.0 name prefixes (mi/pi/ii).
        switch (MetricTypes::kind($this->moduleDir, $unit)):
            case 'monetary':
                return ['typ' => $this->defaultMI];
            case 'percentage':
                return ['typ' => 'upure', 'format' => 'pi'];
            case 'integer':
                return ['typ' => 'upure', 'format' => 'int'];
            case 'decimal':
                return ['typ' => 'upure', 'format' => 'dec'];
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
                        'id', 'u' . $this->defaultMI
                    );
                    $this->writeElementNs('xbrli', 'measure', NULL, 'iso4217:' . $this->defaultMI);
                    $this->endElement();
                    break;
                case 'upure':
                    $this->startElementNS('xbrli', 'unit', NULL);
                    $this->writeAttribute(
                        'id', 'uPURE'
                    );
                    $this->writeElementNs('xbrli', 'measure', NULL, 'xbrli:pure');
                    $this->endElement();
                    break;
                default:

                    if (preg_match('/^[A-Z]{3}$/', (string) $key)):
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
            'id', 'cfi'
        );

        //entity
        $this->startElementNS('xbrli', 'entity', NULL);
        $this->startElementNS('xbrli', 'identifier', NULL);
        $this->writeAttribute(
            'scheme', $this->entityIdentifierScheme()
        );
        $this->writeRaw($this->normalizeEntityIdentifierValue($this->organisation));
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

                // A context is identified by the members it writes (sorted), not by the
                // raw JSON: the same datapoint saved from two tables used to get two
                // s-equal contexts, i.e. a duplicate fact (EBA filing rule 2.16) that
                // also multiplied formula evaluations (2^n messages in Arelle).
                $arr = json_decode($row['context'], true) ?: [];
                $members = $this->collectScenarioMembers($arr);
                usort($members, fn ($a, $b) => strcmp((string) $a['dimension'], (string) $b['dimension']));
                $ctxKey = json_encode($members);
                $currency = preg_match('/^[A-Z]{3}$/', (string) $row['sheetcode']) ? $row['sheetcode'] : '';
                $factKey = $row['metric'].'|'.$ctxKey.'|'.$currency;
                if (isset($this->writtenFacts[$factKey])):
                    continue;
                endif;
                $this->writtenFacts[$factKey] = true;

                $tmpId = array_search($ctxKey, $this->tempContext, TRUE);

                if ($tmpId === false):
                    $this->Metric[$i]['contextRef'] = $this->id;
                else:
                    $this->Metric[$i]['contextRef'] = $tmpId + 1;
                endif;


                $this->Metric[$i]['metric'] = $row['metric'];
                $this->Metric[$i]['numeric_value'] = $row['numeric_value'];
                $this->Metric[$i]['string_value'] = $row['string_value'];
                $this->Metric[$i]['sheetcode'] = $row['sheetcode'];

                if (in_array($ctxKey, $this->tempContext, TRUE) == FALSE):

                    $this->tempContext[] = $ctxKey;

                    //context
                    $this->startElementNS('xbrli', 'context', NULL);
                    $this->writeAttribute(
                        'id', 'c' . $this->id
                    );

                    //entity
                    $this->startElementNS('xbrli', 'entity', NULL);
                    $this->startElementNS('xbrli', 'identifier', NULL);
                    $this->writeAttribute(
                        'scheme', $this->entityIdentifierScheme()
                    );
                    $this->writeRaw($this->normalizeEntityIdentifierValue($this->organisation));
                    $this->endElement();
                    $this->endElement();

                    //period
                    $this->startElementNS('xbrli', 'period', NULL);
                    $this->writeElementNS('xbrli', 'instant', NULL, date('Y-m-d', strtotime($this->date)));
                    $this->endElement();

                    $scenarioMembers = $members;

                    if (!empty($scenarioMembers)) {
                        $this->startElement('xbrli:scenario');

                        foreach ($scenarioMembers as $member):
                            if ($member['type'] === 'explicit') {
                                $this->startElementNS('xbrldi', 'explicitMember', NULL);
                                $this->writeAttribute('dimension', $member['dimension']);
                                $this->writeRaw($member['value']);
                                $this->endElement();
                                continue;
                            }

                            $this->startElementNS('xbrldi', 'typedMember', NULL);
                            $this->writeAttribute('dimension', $member['dimension']);

                            $typMember = explode(':', $member['typed']['typ']);

                            $this->startElementNS($typMember[0], $typMember[1], NULL);
                            $this->writeRaw($member['typed']['value']);
                            $this->endElement();
                            $this->endElement();
                        endforeach;

                        $this->endElement();
                    }

                    $this->id++;
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
                            // A currency sheet (z-axis code like "USD") sets the unit; any other sheet uses the base currency.
                            $currency = preg_match('/^[A-Z]{3}$/', (string) $row['sheetcode']) ? $row['sheetcode'] : $this->defaultMI;
                            $this->writeAttribute('decimals', (string) $this->options['decimalsMonetary']);
                            $this->writeAttribute('unitRef', 'u' . $currency);
                            $this->writeRaw(FactValue::forKind('monetary', $row['numeric_value'], (int) $this->options['monetaryScale']));
                            break;
                        case 'upure':
                            $format = $this->unit[$row['metric']]['format'];
                            $decimals = ['pi' => 'decimalsPercentage', 'int' => 'decimalsInteger', 'dec' => 'decimalsDecimal'][$format];
                            $this->writeAttribute('decimals', (string) $this->options[$decimals]);
                            $this->writeAttribute('unitRef', 'uPURE');
                            $this->writeRaw(FactValue::forKind(['pi' => 'percentage', 'int' => 'integer', 'dec' => 'decimal'][$format], $row['numeric_value']));
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

        $fillingIndicator = [];

        foreach ((array) $this->fIndicators as $path):
            $code = $this->normalizeFilingIndicatorCode($path);

            if ($code === '') {
                continue;
            }

            $fillingIndicator[$code] = TRUE;
        endforeach;

        // Filing Rules: every template of the module carries an indicator — negative
        // (find:filed="false") unless it is reported. Needs the module JSON (DPM 2.0 modules).
        $info = ModuleInfo::load((string) $this->moduleDir, (string) $this->schemaRef);
        foreach (array_keys($info['filing_indicators'] ?? []) as $code):
            $fillingIndicator += [$code => false];
        endforeach;

        // Explicit choices win: false is written as find:filed="false", true is reported even without data.
        foreach ((array) $this->options['filingIndicators'] as $code => $filed):
            $fillingIndicator[str_replace('-', '.', strtoupper((string) $code))] = (bool) $filed;
        endforeach;

        ksort($fillingIndicator, SORT_NATURAL);

        foreach ($fillingIndicator as $key => $row):

            $this->startElementNS('find', 'filingIndicator', NULL);
            if ($row === false):
                // A global attribute of the filing-indicators schema: it must be qualified.
                $this->writeAttribute('find:filed', 'false');
            endif;
            $this->writeAttribute('contextRef', 'cfi');
            $this->writeRaw($key);
            $this->endElement();
        endforeach;


        $this->endElement();
    }

    /**
     * @param array $elements
     * @param string $parentId
     * @return array
     */
    private function filterGroup(array $elements, $parentId = ''): array
    {
        $group = $this->buildGroup($elements, $parentId);

        $filter = [];
        $i = 0;
        foreach ($group as $row):

            if (isset($row['children'])):

                foreach ($row['children'] as $el):

                    $filter[$i]['group'] = Format::getAfterSpecChar($row['href'], '_tg', 3);

                    $filter[$i]['table'] = strtolower(Format::getAfterSpecChar($el['href'], '_t', 3));
                    $i++;
                endforeach;

            endif;

        endforeach;

        return $filter;
    }

    /**
     * @param array $elements
     * @param string $parentId
     * @return array
     */
    private function buildGroup(array $elements, $parentId = ''): array
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

    private function collectScenarioMembers(array $context): array
    {
        $members = [];

        foreach ($context as $dimension => $scenario) {
            if (is_array($scenario)) {
                foreach ($scenario as $typedDimension => $typedMember) {
                    if (!is_array($typedMember) || empty($typedMember['typ'])) {
                        continue;
                    }

                    $members[] = [
                        'type' => 'typed',
                        'dimension' => $typedDimension,
                        'typed' => $typedMember,
                    ];
                }

                continue;
            }

            if (!is_string($scenario) || $this->isDefaultDimensionMember($scenario)) {
                continue;
            }

            $members[] = [
                'type' => 'explicit',
                'dimension' => $dimension,
                'value' => $scenario,
            ];
        }

        return $members;
    }

    private function isDefaultDimensionMember(string $value): bool
    {
        $member = strstr($value, ':');

        if ($member === false) {
            return false;
        }

        $member = ltrim($member, ':');

        return in_array($member, ['x0', 'qx0'], true);
    }

    private function toOfficialUrl(string $path): string
    {
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }

        $normalized = str_replace('\\', '/', $path);
        $position = strpos($normalized, 'www.');

        if ($position !== false) {
            return 'http://' . substr($normalized, $position);
        }

        return $normalized;
    }

    private function entityIdentifierScheme(): string
    {
        return 'https://eurofiling.info/eu/rs';
    }

    private function normalizeEntityIdentifierValue(string $value): string
    {
        if (strpos($value, ':') === false) {
            return $value;
        }

        return substr($value, strpos($value, ':') + 1);
    }

    private function normalizeFilingIndicatorCode(string $path): string
    {
        $code = strtoupper(pathinfo(basename($path), PATHINFO_FILENAME));
        $code = preg_replace('/\.([A-Z])$/', '', $code);

        return $code ?? '';
    }

}
