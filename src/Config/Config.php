<?php

namespace AReportDpmXBRL\Config;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Class Config
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class Config
{

    public static $monetaryItem = 'EUR';

    public static $lang = [
        '0' => 'en',
        '1' => 'bs-Latn-BA',
        '2' => 'ba',

    ];

    public static $confSet = [
        'lab-codes' => 'lab-codes',
        'rend' => 'rend',
        'def' => 'def',
        'pre' => 'pre',
        'tab' => 'tab'
    ];

    public static $moduleSet = [
        'pre' => 'pre',
        'rend' => 'rend',
        'lab-codes' => 'lab-codes'
    ];
    public static $createInstance = [
        'rend' => 'rend',
        'def' => 'def',
    ];
    public static $owner = 'www.eba.europa.eu';


    public static function publicDir()
    {
        return storage_path('app/public/tax/');
    }


    public static $prefixOwner = 'fba';

    public static function setLogoPath()
    {

        return public_path() . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.svg';
    }

    public static function owners()
    {
        return [
            'fba' => [
                'namespace' => 'http://www.fba.ba',
                'officialLocation' => 'http://www.fba.ba/xbrl',
                'prefix' => 'fba',
                'copyright' => '(C) FBA'
            ],
            'eba' => [
                'namespace' => 'http://www.eba.europa.eu/xbrl/crr',
                'officialLocation' => 'http://www.eba.europa.eu/eu/fr/xbrl/crr',
                'prefix' => 'eba',
                'copyright' => '(C) EBA'
            ],
        ];
    }

    public static function tmpPdfDir()
    {

        return storage_path() . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;

    }


}
