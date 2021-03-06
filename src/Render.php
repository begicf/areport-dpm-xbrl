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
use AReportDpmXBRL\Render\RenderHtmlTable;
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

    private $filename;

    /**
     * @param $import array [
     * $params=[
     *  'sheets'=> (string) Required ,
     *  'file'=> =(array) data,
     *  'ext'=>('DB', 'XBRL') Source of data Required,
     *  ]
     * ]
     *
     * @return array [
     *  'table' => (string) Table HTMl Form,
     *  'sheets' => (string)  Sheets HTML Form
     *  'tableName' => (string)  Table Name
     *  'aspectNode' => (bool)  Aspect Axis
     *  'tableID'=> (string)  Table ID
     *  'groups' => (string)  Table group
     * ]
     */
    public function renderHtmlForm($import, $ZSelect = null): array
    {
        return (new RenderHtmlTable($this->specification, $this->lang, $this->additionalData))->renderHtml($import, $ZSelect);
    }

    /**
     * @param string $type pdf | xlsx
     * @return RenderOutput
     * @throws Exception
     */
    public function export(string $type = 'xlsx')
    {

        if (!in_array($type,['html','xlsx','pdf'])):
            throw new Exception("Export extensions must be xlsx, pdf or html");
        endif;


        if (!isset($this->additionalData['file_path'])):
            $this->additionalData['file_path'] = Config::publicDir() . $this->filename;
        endif;
        return new RenderOutput($this->specification, $this->lang, $type, $this->additionalData);

    }


}
