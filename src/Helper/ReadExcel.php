<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AReportDpmXBRL\Helper;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;

/**
 * Class ReadExcel
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class ReadExcel
{

    //put your code here

    private $sheetData;
    private $objExcel;

    public function __construct($path, $sheet)
    {

        try {

            //$reader = IOFactory::load($path);
            $inputFileType = 'Xlsx';
            $objReader = IOFactory::createReader($inputFileType);
            StringHelper::setDecimalSeparator(',');
            StringHelper::setThousandsSeparator('.');


            $this->objExcel = $objReader->load($path);

            if (!empty($sheet)):

                $this->sheetData = $this->objExcel->getSheetByName($sheet)->toArray(null, true, true, true);
            else:
                $this->sheetData = $this->objExcel->getActiveSheet()->toArray(null, true, true, true);
            endif;
        } catch (Exception $e) {
            die('Error loading file "' . pathinfo($path, PATHINFO_BASENAME) . '": ' . $e->getMessage());
        }
    }

    private function formatCell($type, $value)
    {
        //dump($type);
        switch ($type):
            case '#,##0':
            case '#,###,':
            case '#,##0':
            case '_-* #,##0.00\ _K_M_-;\-* #,##0.00\ _K_M_-;_-* "-"??\ _K_M_-;_-@_-':
            case '0.00':
            case '0':
                return str_replace('.', '', $value);
            case '0%':
            case '0.00%':
                return str_replace('.', ',', $value);
            default :
                return $value;
        endswitch;
    }

    public function __call($method, $args)
    {

        $el = end($args);

        if (empty($el['typ_table']) || $el['typ_table'] == 'null'):
            $X = array_slice($this->sheetData[$el['rowspan'] + 3], 2, $el['column'] + 1);
            $rcY = count($this->sheetData);

            $value = array();

            for ($Y = $el['rowspan'] + 4; $Y <= $rcY; $Y++):

                foreach ($this->sheetData[$Y] as $key => $row):

                    if (isset($X[$key])):

                        $cell = $key . $Y;

                        $type =
                            $this->objExcel->getActiveSheet()->getCell($cell)->getStyle()->getNumberFormat()->getFormatCode();
                        //dump('c' . $X[$key] . 'r' . $this->sheetData[$Y]['A']);

                        $value['c' . $X[$key] . 'r' . $this->sheetData[$Y]['A']] = $this->formatCell($type, $row);
                    endif;
                endforeach;

            endfor;

        else: // open table


            $XArray = array();
            foreach ($this->sheetData[$el['rowspan'] + 4] as $key => $row):
                if (strpos($row, '(') !== false) :

                    $XArray[$key] = trim(strstr(strstr($row, '('), ')', true), '()');
                else:
                    $XArray[$key] = $row;
                endif;
            endforeach;

            //echo "<pre>", print_r($el), "</pre>";
            $X = array_slice($XArray, 0, $el['column'] + 1);
            $rcY = count($this->sheetData);
            //  echo "<pre>", print_r($X), "</pre>";

            $value = array();
            $i = 1;
            for ($Y = $el['rowspan'] + 5; $Y <= $rcY; $Y++):

                foreach ($this->sheetData[$Y] as $key => $row):

                    if (isset($X[$key])):

                        $type =
                            $this->objExcel->getActiveSheet()->getCell($key . $Y)->getStyle()->getNumberFormat()->getFormatCode();

                        $value['c' . $X[$key] . 'r' . $i] = $this->formatCell($type, $row);
                    endif;
                endforeach;
                $i++;
            endfor;
            $value['row'] = $i;
        endif;


        return $value;
    }

}
