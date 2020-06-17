<?php namespace AReportDpmXBRL\HTMLCommon;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


class Error {

    public static function isError($data, $code = null) {
        if (!is_object($data)) {
            return false;
        }
        if (!is_a($data, 'Error')) {
            return false;
        }
    }

    public static function raiseError($error) {
        echo $error;
    }

}
