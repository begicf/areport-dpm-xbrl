<?php

namespace AReportDpmXBRL\Render\RenderTrait;

use AReportDpmXBRL\Library\Data;
use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\Library\Format;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Class Axis
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
trait RAxis
{
    private $tmp = [];

    private function buildLegacyDefinitionMap(array $dimA): array
    {
        $dom = [];

        foreach ($dimA as $key => $element) {
            $p = explode('_', (string) $element);

            if ($key === 'metric') {
                if ($element != 'false' && isset($p[1])) {
                    $val = explode(':', $p[1]);

                    if (isset($val[1])) {
                        $dom['metric'] = $p[0] . '_' . $val[1];
                    }
                }
            } else {
                if (count($p) == 2) {
                    $val = explode(':', $p[1]);

                    if (isset($val[0], $val[1])) {
                        $keyHelp = $p[0] . '_' . $val[0] . ':' . $p[0] . '_' . $val[1];
                        $keyDim = strtok($key, '_') . '_' . substr($key, strpos($key, ":") + 1);
                        $dom[$keyDim . ':' . $keyHelp] = $keyDim;
                    }
                } elseif (count($p) == 3) {
                    $val = explode(':', $p[2]);

                    if (isset($val[0], $val[1])) {
                        $keyHelp = $p[1] . '_' . $val[0] . ':' . $p[0] . '_' . $val[1];
                        $keyDim = strtok($key, '_') . '_' . substr($key, strpos($key, ":") + 1);
                        $dom[$keyDim . ':' . $keyHelp] = $keyDim;
                    }
                }
            }
        }

        return $dom;
    }

    private function buildNormalizedDefinitionMap(array $dimA): array
    {
        $dom = [];

        foreach ($dimA as $key => $element) {
            if ($key === 'metric') {
                $metricKey = $this->normalizeMetricKey($element);

                if (!empty($metricKey)) {
                    $dom['metric'] = $metricKey;
                }
            } else {
                $keyDim = $this->normalizeDimensionKey($key);
                $memberKey = $this->normalizeDimensionMemberKey($element);

                if (!empty($keyDim) && !empty($memberKey)) {
                    $dom[$keyDim . ':' . $memberKey] = $keyDim;
                }
            }
        }

        return $dom;
    }

    private function findDefinitionMatch(array $dom)
    {
        $metricKey = $dom['metric'] ?? null;

        if (!empty($metricKey)) {
            $requestedMemberKeys = array_values(array_filter(array_keys($dom), static function ($key) {
                return $key !== 'metric';
            }));

            $bestMatch = false;
            $bestScore = null;

            foreach ($this->tmp as $item) {
                if (!isset($item[$metricKey])) {
                    continue;
                }

                $missingKeys = array_values(array_filter($requestedMemberKeys, static function ($key) use ($item) {
                    return !isset($item[$key]);
                }));

                if (!empty($missingKeys)) {
                    continue;
                }

                $itemMemberKeys = $this->extractDefinitionMemberKeys($item);
                $extraMemberCount = count(array_diff($itemMemberKeys, $requestedMemberKeys));
                $score = [$extraMemberCount, count($itemMemberKeys)];

                if ($bestScore === null || $score < $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $item[$metricKey];
                }
            }

            if ($bestMatch !== false) {
                return $bestMatch;
            }
        }

        foreach ($this->tmp as $item) {
            if (isset($dom['metric']) && isset($item[$dom['metric']])):
                $dif = array_diff_key($item, $dom);

                if (count($dif) == (count($item) - count($dom) + 1)):
                    return $item[$dom['metric']];
                endif;
            endif;
        }

        return false;
    }

    private function extractDefinitionMemberKeys(array $item): array
    {
        return array_values(array_filter(array_keys($item), static function ($key) {
            return substr_count((string) $key, ':') >= 2;
        }));
    }

    private function stripDefaultDimensions(array $dimA): array
    {
        foreach ($dimA as $key => $value) {
            if ($key === 'metric') {
                continue;
            }

            if (is_string($value) && strpos($value, ':') !== false) {
                $localName = substr($value, strpos($value, ':') + 1);

                if ($localName !== '' && substr($localName, -2) === 'x0') {
                    unset($dimA[$key]);
                }
            }
        }

        return $dimA;
    }

    private function normalizeMetricKey($value): ?string
    {
        $value = (string) $value;

        if ($value === '' || $value === 'false' || strpos($value, ':') === false) {
            return null;
        }

        [$prefix, $local] = explode(':', $value, 2);
        $root = strtok($prefix, '_');

        if ($root === false || $root === '' || $local === '') {
            return null;
        }

        return $root . '_' . $local;
    }

    private function normalizeDimensionKey($key): ?string
    {
        $key = (string) $key;

        if ($key === '' || strpos($key, ':') === false) {
            return null;
        }

        $root = strtok($key, '_');
        $local = substr($key, strpos($key, ':') + 1);

        if ($root === false || $root === '' || $local === '') {
            return null;
        }

        return $root . '_' . $local;
    }

    private function normalizeDimensionMemberKey($value): ?string
    {
        $value = (string) $value;

        if ($value === '' || $value === 'false' || strpos($value, ':') === false) {
            return null;
        }

        [$prefix, $local] = explode(':', $value, 2);
        $parts = explode('_', $prefix);
        $root = $parts[0] ?? null;
        $domain = $parts[1] ?? null;

        if (empty($root) || empty($domain) || $local === '') {
            return null;
        }

        return $root . '_' . $domain . ':' . $root . '_' . $local;
    }

    public function buildXAxis(array $elements, $parentId = 0, $n = 0, $node = array())
    {
        $branch = array();

        $col = 0;

        foreach ($elements as $element):

            if ($element['from'] == $parentId):

                //ruleNodes
                if (is_array($this->specification['rend']['ruleNode'][$element['from']]['dimension'])):
                    $node =
                        array_replace($node, $this->specification['rend']['ruleNode'][$element['from']]['dimension']);
                endif;

                $children = $this->buildXAxis($elements, $element['to'], $n + 1, $node);
                if ($children):

                    $element['row'] = $n++;

                    //ruleNode
                    if (is_array($this->specification['rend']['ruleNode'][$element['to']]['dimension'])):
                        $element['dimension'] =
                            array_replace($node, $this->specification['rend']['ruleNode'][$element['to']]['dimension']);
                        $element['abstract'] = $this->specification['rend']['ruleNode'][$element['to']]['abstract'];
                    else:
                        $element['abstract'] = $this->specification['rend']['ruleNode'][$element['to']]['abstract'];
                        $element['dimension'] = $node;
                    endif;


                    $element['metric'] = $this->specification['rend']['ruleNode'][$element['to']]['concept'];


                    $count_leaves = count(array_column($children, 'leaves_element'));
                    $element['all_element'] = count($children);

                    //a counter of childe elements containing a metric
                    $tmpC = 0;
                    foreach ($children as $c):

                        if ($c['metric'] != 'false'):
                            ;
                            $tmpC = $tmpC + 1;
                        endif;
                    endforeach;

                    $element['metric_element'] = $tmpC;


                    if (count(array_column($children, 'rollup'))):

                        $count_rollup = array_sum(array_column($children, 'rollup'));

                        if ($count_rollup <= 1):
                            $count_leaves = $count_leaves - 1;
                        else:

                            $count_leaves = 0;

                        endif;
                    endif;


                    if ($element['metric'] != 'false' && $element['abstract'] == 'false'):
                        $element['all_element'] = $element['all_element'] + 1;
                        $element['rollup'] = true;
                    endif;

                    $element['leaves_element'] = $element['all_element'] - $count_leaves;


                    $branch[] = $element;

                    foreach ($children as $c):

                        $branch[] = $c;

                    endforeach;
                    $col = 0;
                    $n--;

                else:
                    //ruleNodes
                    if (is_array($this->specification['rend']['ruleNode'][$element['to']]['dimension'])):
                        $element['dimension'] =
                            array_replace($node, $this->specification['rend']['ruleNode'][$element['to']]['dimension']);
                        $element['abstract'] = $this->specification['rend']['ruleNode'][$element['to']]['abstract'];
                    else:
                        $element['abstract'] = $this->specification['rend']['ruleNode'][$element['to']]['abstract'];
                        $element['dimension'] = $node;
                    endif;
                    $element['metric'] = $this->specification['rend']['ruleNode'][$element['to']]['concept'];

                    $element['row'] = $n;

                    $branch[] = $element;
                endif;
            endif;
        endforeach;
        return $branch;
    }

    public function buildYAxis(array $elements, $parentId = 0, $n = 0, $node = array())
    {
        $branch = array();

        foreach ($elements as $element) :

            if (isset($element['from']) && $element['from'] == $parentId) :

                //ruleNodes
                if (is_array($this->specification['rend']['ruleNode'][$element['from']]['dimension'])):
                    $node =
                        array_replace($node, $this->specification['rend']['ruleNode'][$element['from']]['dimension']);
                endif;

                $children = $this->buildYAxis($elements, $element['to'], $n + 1, $node);

                if ($children):
                    $element['col'] = $n++;

                    //ruleNodes
                    if (is_array($this->specification['rend']['ruleNode'][$element['to']]['dimension'])):
                        $element['dimension'] =
                            array_replace($node, $this->specification['rend']['ruleNode'][$element['to']]['dimension']);
                        $element['abstract'] = $this->specification['rend']['ruleNode'][$element['to']]['abstract'];
                    else:
                        $element['abstract'] = $this->specification['rend']['ruleNode'][$element['to']]['abstract'];
                        $element['dimension'] = $node;
                    endif;

                    $element['metric'] = $this->specification['rend']['ruleNode'][$element['to']]['concept'];


                    $branch[] = $element;

                    foreach ($children as $c):

                        $branch[] = $c;

                    endforeach;
                    $n--;

                else:

                    //ruleNodes
                    if (is_array($this->specification['rend']['ruleNode'][$element['to']]['dimension'])):
                        $element['dimension'] =
                            array_replace($node, $this->specification['rend']['ruleNode'][$element['to']]['dimension']);
                        $element['abstract'] = $this->specification['rend']['ruleNode'][$element['to']]['abstract'];
                    else:
                        $element['abstract'] = $this->specification['rend']['ruleNode'][$element['to']]['abstract'];
                        $element['dimension'] = $node;
                    endif;


                    $element['metric'] = $this->specification['rend']['ruleNode'][$element['to']]['concept'];
                    $element['col'] = $n;

                    $branch[] = $element;
                endif;
            endif;
        endforeach;
        return $branch;
    }

    public function buildZAxis(array $elements, $parentId = 0, $node = array())
    {
        $branch = array();

        foreach ($elements as $element) :

            if ($element['from'] == $parentId) :

                //ruleNodes
                if (is_array($this->specification['rend']['ruleNode'][$element['from']]['dimension'])):
                    $node =
                        array_replace($node, $this->specification['rend']['ruleNode'][$element['from']]['dimension']);
                endif;

                $children = $this->buildZAxis($elements, $element['to'], $node);

                if ($children):


                    //ruleNodes
                    if (is_array($this->specification['rend']['ruleNode'][$element['to']]['dimension'])):
                        $element['dimension'] =
                            array_replace($node, $this->specification['rend']['ruleNode'][$element['to']]['dimension']);
                        $element['abstract'] = $this->specification['rend']['ruleNode'][$element['to']]['abstract'];
                    else:
                        $element['dimension'] = $node;
                    endif;
                    $element['metric'] = $this->specification['rend']['ruleNode'][$element['to']]['concept'];


                    $branch[] = $element;

                    foreach ($children as $c):

                        $branch[] = $c;

                    endforeach;

                else:

                    //ruleNodes
                    if (is_array($this->specification['rend']['ruleNode'][$element['to']]['dimension'])):
                        $element['dimension'] =
                            array_replace($node, $this->specification['rend']['ruleNode'][$element['to']]['dimension']);
                        $element['abstract'] = $this->specification['rend']['ruleNode'][$element['to']]['abstract'];
                    else:
                        $element['dimension'] = $node;
                    endif;


                    $element['metric'] = $this->specification['rend']['ruleNode'][$element['to']]['concept'];


                    $branch[] = $element;
                endif;
            endif;
        endforeach;
        return $branch;
    }

    public function searchLabel($value, $role)
    {

        switch ($role):
            case 'http://www.xbrl.org/2008/role/label':

                $found =
                    Data::searchLabel($this->specification[$this->lang], 'href', Format::getAfterSpecChar($value, '_'));


                return $this->pickPreferredLabel($found, $role);

                break;

            case 'http://www.xbrl.org/2008/role/verboseLabel':

                $found =
                    Data::searchLabel($this->specification[$this->lang], 'href', Format::getAfterSpecChar($value, '_'));
                foreach ($found as $value):

                    if ($value['role'] == $role):

                        return $value['@content'];
                    endif;
                endforeach;

                break;

            case 'http://www.eurofiling.info/xbrl/role/rc-code':

                $found = DomToArray::search_multdim($this->specification['lab-codes'], 'href', $value);

                foreach ($found as $value):

                    if ($value['role'] == $role):

                        return $value['@content'];
                    endif;
                endforeach;

                break;


            case 'http://www.eurofiling.info/xbrl/role/filing-indicator-code':

                $found = DomToArray::search_multdim($this->specification['lab-codes'], 'href', $value);

                foreach ($found as $value):

                    if ($value['role'] == $role):

                        return $value['@content'];
                    endif;
                endforeach;

                break;

            case 'http://www.eba.europa.eu/xbrl/role/dpm-db-id':

                $found = DomToArray::search_multdim($this->specification['lab-codes'], 'href', $value);

                foreach ($found as $value):

                    if ($value['role'] == $role):

                        return $value['@content'];
                    endif;
                endforeach;

                break;


            case 'http://xbrl.org/arcrole/PWD/2013-05-17/table-breakdown':

                $found =
                    array_replace_recursive($this->specification['rend']['tableBreakdownArc'], $this->specification['rend']['breakdownTreeArc']);

                $arr = array();
                foreach ($found as $key => $value) {
                    if ($value['axis']):
                        $arr[$value['axis']][$value['to']] = $value;
                    endif;
                }

                return $arr;
                break;

            case 'http://xbrl.org/arcrole/PWD/2013-05-17/breakdown-tree':


                $found = DomToArray::search_multdim($this->specification['rend']['breakdownTreeArc'], 'to', $value);

                foreach ($found as $value):

                    if ($value['arcrole'] == 'http://xbrl.org/arcrole/PWD/2013-05-17/breakdown-tree'):
                        return $value['from'];

                    elseif ($value['arcrole'] == 'http://xbrl.org/arcrole/2014/breakdown-tree'):

                        return $value['from'];
                    endif;
                endforeach;

                break;
        endswitch;
    }

    private function pickPreferredLabel($found, $role)
    {
        if (empty($found) || !is_array($found)):
            return null;
        endif;

        $genericLabels = ['rows', 'columns', 'sheets'];
        $fallback = null;

        foreach ($found as $value):

            if (($value['role'] ?? null) != $role):
                continue;
            endif;

            $content = trim($value['@content'] ?? '');

            if ($content === ''):
                continue;
            endif;

            if (is_null($fallback)):
                $fallback = $content;
            endif;

            if (!in_array(strtolower($content), $genericLabels, true)):
                return $content;
            endif;
        endforeach;

        return $fallback;
    }

    /**
     * @return array
     */
    private function getAllDimensions()
    {
        $ruleNode = $this->specification['rend']['ruleNode'];

        $dim = array();
        foreach ($ruleNode as $key => $row):

            if (isset($this->breakdownTreeArc['z']['to']) && $key == $this->breakdownTreeArc['z']['to'])
                break;
            if (isset($row['dimension']) && is_array($row['dimension'])):
                foreach ($row['dimension'] as $key => $r):
                    if (!in_array($key, $dim) and $row['abstract'] == 'false'):
                        $dim[$key] = strstr($r, ':', true);
                    endif;
                endforeach;
            endif;
        endforeach;
        return $dim;
    }


    /**
     * Check the link to the tax definition or whether the fields are used or not
     * @param $dim
     * @return bool
     */
    public function checkDef($dim)
    {

        $this->specification['def'];

        $dimA = json_decode($dim, true);
        if (!is_array($dimA)) {
            return false;
        }

        if (empty($this->tmp)):
            foreach ($this->specification['def'] as $key => &$val):

                if (count($val) > 2):
                    foreach ($val as $keyVal => $row):
                        $this->tmp[$key][$keyVal] = $row;
                    endforeach;
                endif;

            endforeach;
        endif;

        $candidates = [$dimA];
        $reduced = $this->stripDefaultDimensions($dimA);

        if ($reduced !== $dimA) {
            $candidates[] = $reduced;
        }

        foreach ($candidates as $candidate) {
            $legacy = $this->findDefinitionMatch($this->buildLegacyDefinitionMap($candidate));
            if ($legacy !== false) {
                return $legacy;
            }

            $normalized = $this->findDefinitionMatch($this->buildNormalizedDefinitionMap($candidate));
            if ($normalized !== false) {
                return $normalized;
            }
        }

        return false;
    }

    /**
     * @param $domain
     * @param $value
     * @return string
     */
    public function getHierKey($domain, $value)
    {


        $_searckKey = Format::getAfterSpecChar(Format::getBeforeSpecChar($value, '_'), '#');

        foreach ($domain as $key => $row):


            if (strpos($key, $_searckKey) !== false):
                $_val = Format::getAfterSpecChar($value, '_');
                return $key . ':' . $_val;

            endif;

        endforeach;

    }

    /**
     * @param $x
     * @param $y
     * @param null $typ
     * @param null $z
     * @return false|string
     */
    public function mergeDimensions($x, $y, $typ = null, $z = null)
    {

        $allDim = $this->getAllDimensions();

        $metric = array();

        if (!empty($x)):
            $x = call_user_func_array('array_merge', array_values($x));
        endif;

        if (!empty($y) && !array_key_exists('dimensionAspect', $y)):
            $y = call_user_func_array('array_merge', array_values($y));
        endif;


        if (isset($x['metric']) && $x['metric'] != 'false'):
            $metric = ['metric' => $x['metric']];
        elseif (isset($y['metric']) && $y['metric'] != 'false'):
            $metric = ['metric' => $y['metric']];
        elseif (isset($z['metric']) && $z['metric'] != 'false'):
            $metric = ['metric' => $z['metric']];
        endif;


        if (isset($y['dimensionAspect']) && isset($x['dimension']) && isset($z['dimension'])):
            $merge = array_merge($metric, (array)$x['dimension'], (array)$z['dimension']);
        elseif (isset($y['dimensionAspect']) && isset($x['dimension']) && (is_null($z) || empty($z['dimension']))):
            $merge = array_merge($metric, (array)$x['dimension']);
        elseif (isset($x['dimension']) && isset($y['dimension']) && (is_null($z) || empty($z['dimension']))):
            $merge = array_merge($metric, (array)$x['dimension'], (array)$y['dimension']);
        elseif (isset($x['dimension']) && isset($y['dimension']) && isset($z['dimension'])):
            $merge = array_merge($metric, (array)$x['dimension'], (array)$y['dimension'], (array)$z['dimension']);
        elseif (isset($y['dimensionAspect'])):
            if (is_array($typ)):
                return json_encode(array_merge(array($y['dimensionAspect'] => "*"), $typ));
            else:
                return json_encode(array($y['dimensionAspect'] => "*"));
            endif;

        elseif (isset($x['dimension'])):
            $merge = array_merge($metric, (array)$x['dimension']);
        else:
            $merge = $metric;
        endif;

        foreach ($allDim as $key => $row):
            if (!key_exists($key, $merge)):
                $merge[$key] = $row . ':x0';
            endif;
        endforeach;

        return json_encode($merge);
    }

    /**
     * @param $row
     * @return int|mixed
     */
    public function getMaxRow($row)
    {
        $var = array(1);
        if (isset($row) && !empty($row)):
            foreach ($row as $key => $row):
                $num = substr($key, strpos($key, "r") + 1);
                if (is_numeric($num)):
                    $var[] = $num;
                endif;
            endforeach;
            return max($var);
        else:
            return 1;
        endif;
    }

    /**
     * @param $ZAxis
     * @param $ZSelect
     * @return mixed|null
     */

    private function getCurrentZAxis($ZAxis, $ZSelect)
    {

        if (is_null($ZAxis)):
            return null;
        endif;

        if (!is_null($ZSelect)):

            return current(DomToArray::search_multdim($ZAxis, 'order', (json_decode($ZSelect))->order));

        else:

            return current($ZAxis);

        endif;

    }

}
