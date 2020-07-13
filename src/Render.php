<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AReportDpmXBRL;

use AReportDpmXBRL\Config\Config;
use AReportDpmXBRL\Render\RenderExport;
use AReportDpmXBRL\Render\RenderOutput;
use AReportDpmXBRL\Render\RenderPDF;
use AReportDpmXBRL\Render\RenderTable;
use AReportDpmXBRL\Render\RenderTrait\RTrait;
use Exception;


/**
 * Class Render
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class Render
{
    use RTrait;

    //put your code here
//    private $specification;
    private $filename;
//    private $lang = null;
    //private $additionalData = [];

    /**
     * Render constructor.
     * @param null $specification
     * @param null $lang
     * @param null $additionalData
     * @throws Exception
     */
//    public function __construct($specification = null, $lang = null, $additionalData = null)
//    {
//        if (is_null($specification)) {
//            throw new Exception('Taxonomy is not defined!');
//        }
//
//        $this->specification = $specification;
//        $this->lang = $lang;
//        $this->additionalData = $additionalData;
//    }

    /**
     * @return array|mixed
     */
    public function getTableID()
    {
        $tableNameId = key($this->specification['rend']['table']);

        $tableLabelName = $this->specification['rend']['table'][$tableNameId]['label'];

        $tableID =
            $this->searchLabel($this->specification['rend']['path'] . "#" . $tableLabelName, 'http://www.eba.europa.eu/xbrl/role/dpm-db-id');
        return $tableID;
    }

    /**
     * @param $import
     * @return array
     */
    public function renderHtmlForm($import)
    {
        return (new RenderTable($this->specification, $this->lang, $this->additionalData))->renderHtml($import);
    }

    /**
     * @param $type
     * @return RenderOutput
     */
    public function export($type)
    {

        if (!isset($this->additionalData['file_path'])):
            $this->additionalData['file_path'] = Config::publicDir() . $this->filename;
        endif;
        return new RenderOutput($this->specification, $this->lang, $type, $this->additionalData);

    }


}
