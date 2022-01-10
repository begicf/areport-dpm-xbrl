<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AReportDpmXBRL\Library;
/**
 * Class Normalise
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class Normalise
{

    public static function taxPath($path)
    {

        //need improvements
        if (strpos($path, 'public' . DIRECTORY_SEPARATOR . 'tax')):
            $tmp_path = substr($path, strpos($path, 'public' . DIRECTORY_SEPARATOR . 'tax') + 11);
            return self::_normalise($tmp_path);
        else:
            return self::_normalise($path);
        endif;
    }

    public static function _normalise($path)
    {

        // Skip invalid input.
        if (!isset($path)) {
            return FALSE;
        }
        if ($path === '') {
            return '';
        }

        // Attempt to avoid path encoding problems.
        $path = preg_replace("/[^\x20-\x7E]/", '', $path);
        $path = str_replace('\\', '/', $path);

        // Remember path root.
        $prefix = substr($path, 0, 1) === '/' ? '/' : '';

        // Process path components
        $stack = array();
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                // No-op: skip empty part.
            } elseif ($part !== '..') {
                array_push($stack, $part);
            } elseif (!empty($stack)) {
                array_pop($stack);
            } else {
                return FALSE; // Out of the root.
            }
        }

        // Return the "clean" path
        $path = $prefix . implode('/', $stack);
        return $path;
    }


}
