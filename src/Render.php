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

    private $filename;

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
    public function renderHtmlForm($import, $ZSelect = null)
    {
        return (new RenderTable($this->specification, $this->lang, $this->additionalData))->renderHtml($import, $ZSelect);
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
