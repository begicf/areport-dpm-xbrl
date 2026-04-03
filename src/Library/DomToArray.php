<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AReportDpmXBRL\Library;

use AReportDpmXBRL\Config\Config;

/**
 * Class DomToArray
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class DomToArray
{
    private static function normalizePathFragment($path): string
    {
        return trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, (string) $path), DIRECTORY_SEPARATOR);
    }

    /*
     * @return DomDocument
     */

    public static function invoke($path)
    {
        $dom = new \DOMDocument();
        $dom->load($path);
        return $dom;
    }

    static function getArray($path)
    {

        $dom = self::invoke($path);

        $root = $dom->documentElement;

        $output = self::domnode_to_array($root);
        $output['@root'] = $root->tagName;
        return $output;
    }

    private static function domnode_to_array($node)
    {
        $output = array();
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;
            case \XML_ELEMENT_NODE:
                for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v = self::domnode_to_array($child);
                    if (isset($child->tagName)) {
                        $t = $child->tagName;
                        if (!isset($output[$t])) {
                            $output[$t] = array();
                        }
                        $output[$t][] = $v;
                    } elseif ($v || $v === '0') {
                        $output = (string)$v;
                    }
                }
                if ($node->attributes->length && !is_array($output)) { //Has attributes but isn't an array
                    $output = array('@content' => $output); //Change output into an array.
                }
                if (is_array($output)) {
                    if ($node->attributes->length) {
                        $a = array();
                        foreach ($node->attributes as $attrName => $attrNode) {
                            // echo "<pre>", print_r($attrName), "</pre>";
                            $a[$attrName] = (string)$attrNode->value;
                        }
                        $output['@attributes'] = $a;
                    }
                    foreach ($output as $t => $v) {
                        if (is_array($v) && count($v) == 1 && $t != '@attributes') {
                            $output[$t] = $v[0];
                        }
                    }
                }
                break;
        }
        return $output;
    }

    public static function search_multdim($arr, $field, $value)
    {
        if (!empty($arr)):
            $found = array();
            foreach ($arr as $key => $row):

                if (isset($row[$field]) && $row[$field] === $value):
                    $found[$key] = $row;

                endif;

            endforeach;
            return $found;
        endif;
        return null;
    }

    public static function search_multdim_multival($arr, $value, $role)
    {

        $found_key =
            array_filter($arr, function ($element) use ($role) {
                return isset($element['role']) && $element['role'] == $role;
            });

        if (!empty($arr)):

            switch ($role):

                case $role === 'http://www.eba.europa.eu/xbrl/role/dpm-db-id':


                    $a = self::search_multdim($found_key, 'from', $value);
                    if (is_array($a)):
                        return current($a)['@content'];
                    endif;

                    break;
            endswitch;


        endif;
        return null;


    }


    public static function getPath($path, $string = array(), $return = NULL)
    {
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        $ext = ['xsd'];
        $dir = [];
        $legacyMatches = [];
        $root = rtrim(self::normalizePathFragment($path), DIRECTORY_SEPARATOR);
        $patterns = [];

        foreach ($string as $key => $str) {
            $patterns[$key] = self::normalizePathFragment($str);
        }

        foreach ($rii as $file) :

            if ($file->isDir()) {
                continue;
            }

            $content = self::normalizePathFragment($file->getPathname());
            $tmpPath = pathinfo($content);

            if (!isset($tmpPath['extension']) || !in_array($tmpPath['extension'], $ext, true)) {
                continue;
            }

            $relativePath = ltrim(substr($content, strlen($root)), DIRECTORY_SEPARATOR);
            $relativeDir = self::normalizePathFragment($tmpPath['dirname'] ?? '');

            if ($relativeDir === '.') {
                $relativeDir = '';
            }

            if ($root !== '' && str_starts_with($relativeDir, $root . DIRECTORY_SEPARATOR)) {
                $relativeDir = ltrim(substr($relativeDir, strlen($root)), DIRECTORY_SEPARATOR);
            }

            foreach ($patterns as $key => $pattern):
                if ($pattern === '') {
                    continue;
                }

                $hasExactMatch =
                    $relativeDir === $pattern
                    || $relativePath === $pattern;

                if ($hasExactMatch) {
                    if ($return == NULL):
                        $dir[$key][] = $file->getPathname();
                    else:
                        return $file->getPathname();
                    endif;

                    continue;
                }

                if (strpos($content, $pattern) !== false) {
                    $legacyMatches[$key][] = $file->getPathname();
                }
            endforeach;
        endforeach;

        if ($return == NULL):
            foreach ($patterns as $key => $pattern) {
                if (!isset($dir[$key]) && isset($legacyMatches[$key])) {
                    $dir[$key] = $legacyMatches[$key];
                }
            }

            return $dir;
        endif;

        foreach ($patterns as $key => $pattern) {
            if (isset($legacyMatches[$key][0])) {
                return $legacyMatches[$key][0];
            }
        }

        return null;
    }

    public static function build_url(array $parts)
    {
        $scheme = isset($parts['scheme']) ? ($parts['scheme'] . '://') : '';

        $host = (Config::$owner ?? '');
        $port = isset($parts['port']) ? (':' . $parts['port']) : '';

        $user = ($parts['user'] ?? '');

        $path = ($parts['path'] ?? '');
        $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
        $fragment = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';

        return implode('', [$scheme, $user, $host, $port, $path, $query, $fragment]);
    }

    /**
     *
     * @param type $haystack
     * @param type $needle
     * @return boolean
     */
    public static function strpos_arr($haystack, $needle)
    {
        if (!is_array($needle))
            $needle = array($needle);
        foreach ($needle as $what) {
            if (($pos = strpos($what, $haystack)) !== false)
                return $pos;
        }
        return false;
    }

    /**
     * @param $array
     * @return array|bool
     */
    public static function multidimensional_arr_to_single($array)
    {
        if (!is_array($array)) {
            return FALSE;
        }
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, self::array_flatten($value));
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }


}
