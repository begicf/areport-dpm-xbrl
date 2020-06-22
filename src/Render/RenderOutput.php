<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AReportDpmXBRL\Render;

use AReportDpmXBRL\Domain\Domain;
use AReportDpmXBRL\Helper\ExtendMpdf;
use AReportDpmXBRL\Library\Data;
use AReportDpmXBRL\Library\Directory;
use AReportDpmXBRL\Library\DomToArray;
use AReportDpmXBRL\Library\Format;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Class RenderOutput
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class RenderOutput
{

//put your code here

    private $specification;
    private $roleType = array();
    private $breakdownTreeArc;
    private $row = array();
    private $col = array();
    private $lang;
    private $import;
    private $spreadsheet;
    private $_colOffset = 0; // not working properly
    private $_rowOffset = 3;
    private $_aspectNode = NULL;
    private $_col = 1;
    private $_tableNameId;
    private $_colSpanMax;
    private $_rowSpanMax;
    private $_type = 'xlsx';
    private $_additionalData;
    private $_orientationColumnIndex = 4;

    public function __construct($xbrl, $lang, $type, $additionalData)
    {

        $this->spreadsheet = new Spreadsheet();

        $this->spreadsheet->getProperties()
            ->setCreator("AREPORT")
            ->setTitle("AREPORT XLSX")
            ->setSubject("Ver.1.2.")
            ->setDescription(
                "Based on XBRL specification"
            )
            ->setCategory("XBRL");


        StringHelper::setDecimalSeparator(',');
        StringHelper::setThousandsSeparator('.');


        $this->specification = $xbrl;

        $this->roleType = array_keys($this->specification['def']);

        $this->_type = $type;

        $this->_additionalData = $additionalData;

        $this->setLang($lang);

        $this->axis = new Axis($this->specification, $this->lang);

        $this->tableNameId();


        $this->breakdownTreeArc =
            $this->axis->searchLabel($this->_tableNameId, 'http://xbrl.org/arcrole/PWD/2013-05-17/table-breakdown');

        $this->setAspectNode();
    }

    /**
     *
     */
    public function tableNameId()
    {

        $this->_tableNameId = key($this->specification['rend']['table']);
    }

    /**
     * @return string|null
     */
    public function tableLabelName(): ?string
    {

        return $tableLabelName = $this->specification['rend']['table'][$this->_tableNameId]['label'];
    }

    /**
     * @return string|null
     */
    public function tableName(): ?string
    {
        return $this->axis->searchLabel($this->tableLabelName(), 'http://www.xbrl.org/2008/role/label');
    }

    /**
     * @return string|null
     */
    public function tableVerboseName(): ?string
    {

        return $this->axis->searchLabel($this->tableLabelName(), 'http://www.xbrl.org/2008/role/verboseLabel');

    }

    /**
     *
     * @return type
     */
    public function getXAxis()
    {


        return $this->axis->buildXAxis($this->specification['rend']['definitionNodeSubtreeArc'], current($this->breakdownTreeArc['x'])['to']);
    }

    /**
     *
     * @return type
     */
    public function getYAxis()
    {

        if (isset($this->_aspectNode['y'])):

            return $this->specification['rend']['aspectNode'];

        else:

            return $this->axis->buildYAxis($this->specification['rend']['definitionNodeSubtreeArc'], current($this->breakdownTreeArc['y'])['to']);
        endif;
    }

    /**
     *
     */
    public function getZAxis()
    {

        if (isset($this->breakdownTreeArc['z'])):
            return $this->axis->buildZAxis($this->specification['rend']['definitionNodeSubtreeArc'], current($this->breakdownTreeArc['z'])['to']);
        endif;
    }

    /**
     *
     */
    public function setAspectNode()
    {

        if (isset($this->specification['rend']['aspectNode'])):
            $this->_aspectNode = Data::aspectNode($this->breakdownTreeArc, $this->specification['rend']['aspectNode']);
        endif;
    }

    /**
     *
     * @param type $import
     * @param type $org
     * @param type $user
     * @param type $signers
     * @return $this
     */
    public function renderOutput($import = NULL)
    {

        $this->setImport($import);

        $XAxis = $this->getXAxis();
        $YAxis = $this->getYAxis();
        $ZAxis = $this->getZAxis();

        $this->setSpanMax($XAxis, $YAxis);

        $s = 0;

        $this->spreadsheet->setActiveSheetIndex($s)->setTitle(substr($this->tableName(), 0, 30));


        $this->drawXAxis($XAxis, $s);
        $this->drawZAxis($ZAxis, $s);

        $this->drawYAxis($YAxis, $s);

        //Header
        if ($this->_type != 'pdf'):
            $this->drawHeader($s);
        else:
            $this->spreadsheet->setActiveSheetIndex($s)->mergeCellsByColumnAndRow(1, 1, $this->_col + $this->_colSpanMax, 2);
        endif;

        if (isset($this->_aspectNode['y'])):

            $this->drawOpenContent($s, $XAxis);

        else:

            $this->drawFixContent($s, $XAxis, $YAxis);
        endif;

        return $this;
    }

    public function renderOutputAll($import = NULL)
    {


        $XAxis = $this->getXAxis();
        $YAxis = $this->getYAxis();
        $ZAxis = $this->getZAxis();


        $s = 0;

        $z = $this->getZAxisRaw($ZAxis);

        if (empty($z)):
            $z['000'] = '';
        endif;

        foreach ($z as $key => $row):

            if (is_array($import) && array_key_exists($key, $import)):
                $this->setImport($import[$key]);
            else:
                $this->import = NULL;
            endif;

            $this->setSpanMax($XAxis, $YAxis);

            if ($s != 0):
                $this->spreadsheet->createSheet($s);
            endif;
            //echo $row;
            $this->spreadsheet->setActiveSheetIndex($s)->setTitle($key);

            $this->spreadsheet->setActiveSheetIndex($s);

            $this->drawXAxis($XAxis, $s);
            //$this->drawZAxis($ZAxis, $s);

            $this->drawYAxis($YAxis, $s);

            //Header
            if ($this->_type != 'pdf'):
                $this->drawHeader($s);
            else:
                $this->spreadsheet->setActiveSheetIndex($s)->mergeCellsByColumnAndRow(1, 1, $this->_col + $this->_colSpanMax, 2);
            endif;

            $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow(1, 3, $row)->mergeCellsByColumnAndRow(1, 3, $this->_col + $this->_colSpanMax, 3);

            if (isset($this->_aspectNode['y'])):
                $this->drawOpenContent($s, $XAxis);
            else:

                $this->drawFixContent($s, $XAxis, $YAxis);
            endif;

            $s++;
        endforeach;
        return $this;
    }

    /**
     * @param type $signers
     */
    public function exportFormat()
    {

        switch ($this->_type):
            case 'xlsx':
                $this->outputExcel($this->spreadsheet);
                break;
            case 'pdf':
                $addInformation['tablename'] = $this->tableVerboseName();


                $this->outputPDF($this->spreadsheet, array_merge($this->_additionalData, $addInformation));
                break;
            case 'html':
                $writer = IOFactory::createWriter($this->spreadsheet, 'Html');
                $writer->save('php://output');
                break;
        endswitch;
    }

    public function setSpanMax($xAxis = NULL, $yAxis = NULL)
    {

        if ($xAxis == NULL):
            $xAxis = $this->getXAxis();

        endif;

        if ($yAxis == NULL):
            $yAxis = $this->getYAxis();
        endif;

        //max depth rowspan
        $this->_rowSpanMax = $this->_colSpanMax = max(array_column($xAxis, 'row')) + 1 + $this->_rowOffset;

        //we add two columns for rc code on the y axis
        if (isset($this->_aspectNode['y'])):

            $this->_colSpanMax = count($this->_aspectNode['y']) + $this->_colOffset;

        else:
            $this->_colSpanMax = 2 + $this->_colOffset;
        endif;
    }

    public function drawXAxis($header, $s = 0)
    {
        $this->_col = 1;

        $storPosition = array();

        $headerTitleCol = 1 + $this->_colOffset;
        $headerTitleRow = 1 + $this->_rowOffset;

        $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow($headerTitleCol, $headerTitleRow, $this->tableName());
        $this->spreadsheet->setActiveSheetIndex($s)->getColumnDimensionByColumn($headerTitleCol)->setAutoSize(true);
        $this->spreadsheet->setActiveSheetIndex($s)->mergeCellsByColumnAndRow($headerTitleCol, $headerTitleRow, $this->_colSpanMax, $this->_rowSpanMax)
            ->getStyleByColumnAndRow($headerTitleCol, $headerTitleRow, $this->_colSpanMax, $this->_rowSpanMax + 1)->applyFromArray($this->styleHeader());

        $keys = array_keys($header);

        //X axis - generator
        foreach (array_keys($keys) as $row):
            if (isset($keys[$row - 1])):
                $prev = $header[$keys[$row - 1]];
            endif;
            $this_value = $header[$keys[$row]];


            if (isset($storPosition[$this_value['row']])) :

                //check whether the previous position is higher or lower
                if ($storPosition[$this_value['row']] >= $storPosition[$prev['row']]):
                    $this->_col = $storPosition[$this_value['row']] + 1;
                elseif (isset($this->_col) && $this_value['row'] == 0 && $this_value['abstract'] == 'false'):
                    $this->_col = $this->_col + 1;
                endif;


                //Save the position, if the position has a child elelemt then it is a factor for the number of child elements
                $tmpPos = NULL;
                if (isset($this_value['leaves_element']) && $this_value['abstract'] != 'true'):
                    $tmpPos = $this->_col + $this_value['leaves_element'] - 1;
                elseif (isset($this_value['metric_element'])):
                    $tmpPos = $this->_col + $this_value['metric_element'] - 1;
                else:
                    $tmpPos = $this->_col;
                endif;

                $storPosition[$this_value['row']] = $tmpPos;
            else :


                $tmpPos = NULL;
                //If the position is not set and has child elements, set it to the number of child elements plus the number of columns, otherwise only to the number of columns

                if (isset($this_value['leaves_element']) && $this_value['abstract'] != 'true'):
                    //If the position has childe elements and if the parent element is filled or has a metric value
                    $tmpPos = $this->_col + $this_value['leaves_element'] - 1;
                elseif (isset($this_value['metric_element'])):
                    //If the position possesses childe elements just consider the number metric
                    $tmpPos = $this->_col + $this_value['metric_element'] - 1;
                else:
                    $tmpPos = $this->_col;
                endif;


                $storPosition[$this_value['row']] = $tmpPos;
            endif;


            //Rc-code
            $this->col[$this->_col]['rc-code'] = $rcCode =
                $this->axis->searchLabel($this->specification['rend']['path'] . "#" . $this_value['to'], 'http://www.eurofiling.info/xbrl/role/rc-code');
            $this->col[$this->_col]['id'] = $this_value['to'];
            $this->col[$this->_col]['abstract'] = $this_value['abstract'];

            $this_value['row'] = $this_value['row'] + $this->_rowOffset;
            $lebelName = $this->axis->searchLabel($this_value['to'], 'http://www.xbrl.org/2008/role/label');
            if (isset($this_value['leaves_element']) && $this_value['abstract'] != 'true'):

                $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 1, $lebelName);
                $this->spreadsheet->setActiveSheetIndex($s)->getRowDimension($this_value['row'] + 1)->setRowHeight(70);

                //    $this->spreadsheet->setActiveSheetIndex($s)->mergeCellsByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 1, $this_value['leaves_element'] + $this->_col + 1, $this_value['row'] + 1);
                //    $this->spreadsheet->setActiveSheetIndex($s)->mergeCellsByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + $this->_colSpanMax+1, $this->_col + $this->_colSpanMax, $this->_rowSpanMax);

                $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 1, $this_value['leaves_element'] + $this->_col + 1, $this_value['row'] + 1)->applyFromArray($this->styleX());
                $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 1, $this_value['leaves_element'] + $this->_col + 1, $this_value['row'] + 1)->getAlignment()->setWrapText(true);

                $colMerge =
                    isset($this_value['rollup']) ? $this_value['leaves_element'] - 1 : $this_value['leaves_element'];
                $this->spreadsheet->setActiveSheetIndex($s)->mergeCellsByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 1, $this->_col + $this->_colSpanMax + $colMerge, $this_value['row'] + 1);
                $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 1, $this->_col + $this->_colSpanMax + $colMerge, $this_value['row'] + 1)->applyFromArray($this->styleX());
                $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 2, $this->_col + $this->_colSpanMax, $this->_rowSpanMax)->applyFromArray($this->styleXFix());

                //$this->spreadsheet->getActiveSheet()->getRowDimensions($this_value['row'] + 1)->setRowHeight(10);

                $this->spreadsheet->setActiveSheetIndex($s)->setCellValueExplicitByColumnAndRow($this->_col + $this->_colSpanMax, $this->_rowSpanMax + 1, $rcCode, DataType::TYPE_STRING);
                $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($this->_col + $this->_colSpanMax, $this->_rowSpanMax + 1)->applyFromArray($this->styleRC());

                $this->_col = $this->_col + 1;
            else:
                //
                if (isset($this_value['metric_element']) && $this_value['metric_element'] != 0 && $this_value['metric_element'] < $this_value['leaves_element']):
                    $this_value['leaves_element'] = $this_value['metric_element'];
                endif;

                $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 1, $lebelName);

                $rowspan =
                    (isset($this_value['leaves_element']) || ($this->_rowSpanMax - 1) == $this_value['row']) ? 1 : $this->_rowSpanMax - $this_value['row'];
                $colspan = (isset($this_value['leaves_element']) ? ($this_value['leaves_element'] - 1) : 0);

                $this->spreadsheet->setActiveSheetIndex($s)->mergeCellsByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 1, $colspan + $this->_col + $this->_colSpanMax, $this_value['row'] + $rowspan)
                    ->getStyleByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 1, $this->_col + $this->_colSpanMax, $this_value['row'] + $rowspan)->applyFromArray($this->styleX());

                $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 1, $colspan + $this->_col + $this->_colSpanMax, $this_value['row'] + $rowspan)->applyFromArray($this->styleX());
                $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($this->_col + $this->_colSpanMax, $this_value['row'] + 1, $colspan + $this->_col + $this->_colSpanMax, $this_value['row'] + $rowspan)->getAlignment()->setWrapText(true);

                $this->spreadsheet->getActiveSheet()->getRowDimension($this_value['row'] + $rowspan)->setRowHeight(70);

                $this->spreadsheet->setActiveSheetIndex($s)->setCellValueExplicitByColumnAndRow($this->_col + $this->_colSpanMax, $this->_rowSpanMax + 1, $rcCode, DataType::TYPE_STRING);
                $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($this->_col + $this->_colSpanMax, $this->_rowSpanMax + 1)->applyFromArray($this->styleRC());

            endif;

        endforeach;
    }

    public function getZAxisRaw($ZAxis)
    {


        $z = array();
        // Z Axis print
        if (isset($this->_aspectNode['z'])): // variable Z axis


            $explicitDimension = current($this->specification['rend']['explicitDimension']);

            $domain =
                Domain::getDomain($explicitDimension['linkrole'], Directory::getRootName($this->_additionalData['file_path']));


            foreach ($domain as $row):
                $id = substr($row['href'], strpos($row['href'], "#") + 1);
                $key = substr($id, strpos($id, "_") + 1);
                $z[$key] = $row['@content'];
            endforeach;

            return $z;

        elseif (isset($this->breakdownTreeArc['z'])):

            foreach ($ZAxis as $row):

                $key =
                    $this->axis->searchLabel($this->specification['rend']['path'] . "#" . $row['to'], 'http://www.eurofiling.info/xbrl/role/rc-code');
                $z[$key] = $this->axis->searchLabel($row['to'], 'http://www.xbrl.org/2008/role/label');
            endforeach;
            return $z;

        else:
            return $z;
            //$this->spreadsheet->setActiveSheetIndex($s)->mergeCellsByColumnAndRow(1, 3, 2 + $this->_col, 3);
        endif;
    }

    public function drawZAxis($ZAxis, $s)
    {


        //Z Axis printing
        if (isset($this->_aspectNode['z'])):


            $explicitDimension = current($this->specification['rend']['explicitDimension']);

            $domain =
                Domain::getDomain($explicitDimension['linkrole'], Directory::getRootName($this->_additionalData['file_path']));

            $zContent = null;

            foreach ($domain as $row):
                $id = substr($row['href'], strpos($row['href'], "#") + 1);
                $keyID = substr($id, strpos($id, "_") + 1);
                if ($keyID == $this->import['sheet']):
                    $zContent = $row['@content'];
                endif;
            endforeach;

            $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow(1, 3, $zContent)->mergeCellsByColumnAndRow(1, 3, 2 + $this->_col, 3);

        elseif (isset($this->breakdownTreeArc['z'])):

            foreach ($ZAxis as $row):
                $rcCode =
                    $this->axis->searchLabel($this->specification['rend']['path'] . "#" . $row['to'], 'http://www.eurofiling.info/xbrl/role/rc-code');

                if ($rcCode === $this->import['sheet']):

                    $zContent = $this->axis->searchLabel($row['to'], 'http://www.xbrl.org/2008/role/label');


                endif;
            endforeach;

            $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow(1, 3, $zContent)->mergeCellsByColumnAndRow(1, 3, 2 + $this->_col, 3);

        else:

            $this->spreadsheet->setActiveSheetIndex($s)->mergeCellsByColumnAndRow(1, 3, $this->_col + $this->_colSpanMax, 3);
        endif;
    }

    public function drawYAxis($contents, $s)
    {

        //Y
        $len = count($contents);
        $y = 0;
        if (!isset($this->_aspectNode['y'])):

            $_col = 1 + $this->_colOffset;

            foreach ($contents as $key => $row):

                $labelName = $this->axis->searchLabel($row['to'], 'http://www.xbrl.org/2008/role/label');
                $this->row[$y]['rc-code'] = $rcCode =
                    $this->axis->searchLabel($this->specification['rend']['path'] . "#" . $row['to'], 'http://www.eurofiling.info/xbrl/role/rc-code');
                $this->row[$y]['id'] = $row['to'];
                $this->row[$y]['abstract'] = $row['abstract'];

                //set rc-code
                $countSt = strlen($labelName) + $row['col'] + 5;
                $str = str_pad($labelName, $countSt, "      ", STR_PAD_LEFT);

                $_row = $key + $this->_rowSpanMax + 2;

                $bold = FALSE;

                if ($y != $len - 1):
                    $bold =
                        ($row['col'] < $contents[$y + 1]['col'] && $contents[$y + 1]['abstract'] != 'true') ? TRUE : FALSE;
                endif;

                if ($row['abstract'] == 'true'):
                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow($_col, $_row, $str)->
                    mergeCellsByColumnAndRow($_col, $_row, 2 + $this->_col, $_row)->getStyleByColumnAndRow($_col, $_row, 2 + $this->_col, $_row)->applyFromArray($this->abstractYBold());

                    $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($_col, $_row)->applyFromArray($this->abstractYBold());

                elseif ($bold == TRUE):

                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow(1 + $_col, $_row, $str)->getStyleByColumnAndRow(1 + $_col, $_row)->applyFromArray($this->abstractYBold());
                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow($_col, $_row, $rcCode, DataType::TYPE_STRING)->getStyleByColumnAndRow($_col, $_row)->applyFromArray($this->styleRC());
                else:

                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow(1 + $_col, $_row, $str)->getStyleByColumnAndRow(1 + $_col, $_row)->applyFromArray($this->styleY());
                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow($_col, $_row, $rcCode, DataType::TYPE_STRING)->getStyleByColumnAndRow($_col, $_row)->applyFromArray($this->styleRC());

                endif;

                //$this->spreadsheet->setActiveSheetIndex($s)->getColumnDimensionByColumn($_col)->setAutoSize(true);
                //$this->spreadsheet->setActiveSheetIndex($s)->getColumnDimensionByColumn(1 + $_col)->setAutoSize(true);
                // $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow(2, $key + $this->_rowSpanMax + 2)->applyFromArray($styleY);


                $y++;
            endforeach;
            $this->spreadsheet->setActiveSheetIndex($s)->getColumnDimensionByColumn($_col)->setAutoSize(true);
            $this->spreadsheet->setActiveSheetIndex($s)->getColumnDimensionByColumn(1 + $_col)->setAutoSize(true);
        else:

            foreach ($this->specification['rend']['aspectNode'] as $aspect):

                $from =
                    $this->axis->searchLabel($aspect['id'], 'http://xbrl.org/arcrole/PWD/2013-05-17/breakdown-tree');
                $this->row[$y]['rc-code'] = $rcCode =
                    $this->axis->searchLabel($this->specification['rend']['path'] . "#" . $from, 'http://www.eurofiling.info/xbrl/role/rc-code');
                $this->row[$y]['labelName'] = $this->axis->searchLabel($from, 'http://www.xbrl.org/2008/role/label');

                $this->row[$y]['id'] = $aspect['id'];
                $this->row[$y]['axis'] = 'y';
                $y++;
            endforeach;
            if ($s == 0):
                $this->col = array_merge($this->row, $this->col);
            endif;
        endif;
    }

    private function drawFixContent($s, $XAxis, $YAxis)
    {

        $x = $y = 1;
        foreach ($this->col as $this->_col):
            $y = 1;
            foreach ($this->row as $row):

                $y++;
                $name = 'c' . $this->_col['rc-code'] . 'r' . $row['rc-code'];
                $dim =
                    $this->axis->mergeDimensions(DomToArray::search_multdim($XAxis, 'to', $this->_col['id']), DomToArray::search_multdim($YAxis, 'to', $row['id']));
                $def = $this->axis->checkDef($dim, $name);
                $disabled = ($def && $row['abstract'] != 'true' && $this->_col['abstract'] != 'true') ? '' : 'disabled';

                if ($disabled == 'disabled'):
                    if ($row['abstract'] == 'true'):
                        $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($this->_colSpanMax + $x, $this->_rowSpanMax + $y)->applyFromArray($this->abstractDisable());
                    else:

                        $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($this->_colSpanMax + $x, $this->_rowSpanMax + $y)->applyFromArray($this->styleDisable());
                    endif;
                else:

                    $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($this->_colSpanMax + $x, $this->_rowSpanMax + $y)->applyFromArray($this->styleY());
                    $value = (isset($this->import[$name])) ? $this->import[$name] : "";
                    if (isset($def['type_metric'])):

                        $this->typeMetric($def, $this->_colSpanMax + $x, $this->_rowSpanMax + $y, $value, $s);

                    endif;

                endif;

            endforeach;
            $x++;
        endforeach;

        if ($this->_type != 'pdf'):
            $this->drawFooter($x + 1, $this->_rowSpanMax + $y, $s);
            // Footer does not belong to the XBRL specification
        endif;
    }

    private function drawOpenContent($s, $XAxis)
    {
        //////OPEN TABLE/////////
        $maxRow = $this->axis->getMaxRow($this->import);

        $node = ($this->specification['rend']['aspectNode']);

        $y = 1;
        for (; $y <= $maxRow; $y++):
            $x = 1;

            foreach ($this->col as $this->_col) {

                $name = 'c' . $this->_col['rc-code'] . 'r' . ($y);

                if (isset($node[$this->_col['id']])):


                    $typ = null;

                    $yN = $node[$this->_col['id']];

                    if (isset($this->specification['rend']['aspectNodeFilterArc'][$this->_col['id']])):


                        $explicitDimension =
                            $this->specification['rend']['explicitDimension'][$this->specification['rend']['aspectNodeFilterArc'][$this->_col['id']]['to']];
                        $this->_col['explicitDimension'] =
                            Domain::getDomain($explicitDimension['linkrole'], Directory::getRootName($this->_additionalData['file_path']));

                    else:
                        $IDtyp = Format::getAfterSpecChar($yN['dimensionAspect'], ':');

                        foreach ($this->roleType as $row):
                            $defArr = DomToArray::search_multdim($this->specification['def'][$row], 'name', $IDtyp);

                            if (isset($defArr[0])):

                                $typ['typ'] = $defArr[0]['typ']['key'] . ':' . $defArr[0]['typ']['name'];

                                if (!empty($typ)):
                                    break;
                                endif;

                            endif;
                        endforeach;
                    endif;
                else:
                    $yN = $node;
                endif;


                $dim =
                    $this->axis->mergeDimensions(DomToArray::search_multdim($XAxis, 'to', $this->_col['id']), $yN, $typ);


                $def = $this->axis->checkDef($dim);

                $disabled =
                    ($def && isset($this->_col['abstract']) && $this->_col['abstract'] != 'true') ? '' : 'disabled';
                //$additional['dimensionAspect'] = $node[$this->_aspectNode]['dimensionAspect'];
                //$value = (isset($import[$name]['value'])) ? $import[$name]['value'] : "";

                $dimArr = json_decode($dim, TRUE);
                if (isset($this->_col['labelName']) && !isset($dimArr['metric'])):
                    //open table polja koja se popunjavaju
                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow($x, $this->_rowSpanMax + 1, $this->_col['labelName']);
                    $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($x, $this->_rowSpanMax + 1)->applyFromArray($this->styleX());
                endif;
                $_dim = json_decode($dim, true);


                $value = (isset($this->import[$name])) ? $this->import[$name] : "";

                if (current($_dim) === '*'):

                    if (isset($value['string'])):


                        if (isset($this->_col['explicitDimension'])):

                            $con = Data::getSelectedValue($this->_col['explicitDimension'], $value['string']);
                            $this->spreadsheet->setActiveSheetIndex($s)->setCellValueExplicitByColumnAndRow($x, $this->_rowSpanMax + $y + 1, $con, DataType::TYPE_STRING);

                        else:

                            $this->spreadsheet->setActiveSheetIndex($s)->setCellValueExplicitByColumnAndRow($x, $this->_rowSpanMax + $y + 1, $value['string'], DataType::TYPE_STRING);


                        endif;
                        $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($x, $this->_rowSpanMax + $y + 1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    endif;

                    $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($x, $this->_rowSpanMax + $y + 1)->applyFromArray($this->styleY());
                else:

                    $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($x, $this->_rowSpanMax + $y + 1)->applyFromArray($this->styleY());
                    if (isset($def['type_metric'])):
                        $this->typeMetric($def, $x, $this->_rowSpanMax + $y + 1, $value, $s);

                    endif;

                endif;
                $x++;
            }

        endfor;

        $_rowFooter = $this->_rowSpanMax + $maxRow + 1;
        if ($this->_type != 'pdf'):
            $this->drawFooter(count($this->col), $_rowFooter, $s);

        endif;
    }

    private function drawFooter($x, $y, $s)
    {
        $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow(1, $y + 1, "© Areport")->mergeCellsByColumnAndRow(1, $y + 1, $x, $y + 1);
        $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow(1, $y + 2, 'Date: ' . date('d.m.Y'))->mergeCellsByColumnAndRow(1, $y + 2, $x, $y + 2);

    }

    private function drawHeader($s)
    {

        $offsetMerge = (isset($this->_aspectNode['y'])) ? count($this->col) : 2 + $this->_col;
        $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow(1, 1, $this->tableVerboseName())->mergeCellsByColumnAndRow(1, 1, $offsetMerge, 1);
        $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow(1, 2, 'Period: ' . $this->_additionalData['period'])->mergeCellsByColumnAndRow(1, 2, $offsetMerge, 2);

    }

    private function typeMetric($def, $x, $y, $value, $s)
    {
        if (!empty($value)):
            switch ($def['type_metric']):

                case 'xbrli:monetaryItemType':

                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow($x, $y, $value['integer']);
                    $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($x, $y)->getNumberFormat()->setFormatCode('#,##0');


                    break;

                case 'xbrli:QNameItemType':

                    foreach ($def['presentation'] as $row):

                        if (isset($value['string']) && $this->axis->getHierKey($def['namespace'], $row['href']) === $value['string']):
                            $value = $row['@content'];
                        endif;

                    endforeach;

                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueExplicitByColumnAndRow($x, $y, $value, DataType::TYPE_STRING);
                    $this->spreadsheet->setActiveSheetIndex($s)->getColumnDimensionByColumn($x)->setAutoSize(TRUE);

                    break;
                case 'num:percentItemType':
                    $procent = str_replace('.', ',', $value['string']);
                    $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($x, $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE);
                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow($x, $y, $procent);
                    $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($x, $y)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    break;
                case 'xbrli:booleanItemType':

                    if ($value['string'] === 'true'):
                        $value = 'DA';
                    elseif ($value['string'] === 'false'):
                        $value = 'NE';
                    else:
                        $value = '';
                    endif;
                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow($x, $y, $value);
                    break;
                case 'xbrli:stringItemType':

                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueExplicitByColumnAndRow($x, $y, $value['string'], DataType::TYPE_STRING);
                    $this->spreadsheet->setActiveSheetIndex($s)->getColumnDimensionByColumn($x)->setAutoSize(TRUE);
                    break;
                default :
                    $this->spreadsheet->setActiveSheetIndex($s)->setCellValueByColumnAndRow($x, $y, $value['string']);
                    $this->spreadsheet->setActiveSheetIndex($s)->getStyleByColumnAndRow($x, $y)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    break;
            endswitch;
        endif;
    }

    private function outputHtml($spreadsheet)
    {
        $writer = IOFactory::createWriter($spreadsheet, 'Html');
        $writer->save('php://output');
    }

    private function outputExcel($spreadsheet)
    {

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $this->tableName() . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    private function outputPDF($spreadsheet, $info)
    {

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline;filename="' . $info['tablename'] . '.pdf"');
        header('Cache-Control: max-age=0');


        $spreadsheet->getActiveSheet()->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);

        $indexColumn = Coordinate::columnIndexFromString($this->spreadsheet->getActiveSheet()->getHighestDataColumn());

        if ($indexColumn > $this->_orientationColumnIndex):
            $spreadsheet->getActiveSheet()->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        else:
            $spreadsheet->getActiveSheet()->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        endif;


        $pdf = new ExtendMpdf($spreadsheet);

        $pdf->setInfo($info);

        $pdf->save('php://output');
    }

    private function setLang($lang)
    {
        if (!is_null($lang)):
            $this->lang = $lang;
        endif;
    }

    private function setImport($import)
    {

        if (!is_null($import)):
            $this->import = $import;

        endif;
    }

    private function styleRC()
    {
        $styleRC = array(
            'font' => array(
                'size' => 10,
            ), 'borders' => array(
                'top' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'right' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'left' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'bottom' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
            ),
            'fill' => array(
                'fillType' => Fill::FILL_GRADIENT_LINEAR,
                'rotation' => 90,
                'color' => ['rgb' => 'e0ebff']
            ),
            'alignment' => array(
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ),
        );

        return $styleRC;
    }

    private function styleX()
    {


        $styleX = array(
            'font' => array(
                'size' => 10,
                'bold' => false,
                'align' => 'middle',
            ), 'borders' => array(
                'top' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'right' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'left' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'bottom' => array(
                    'borderStyle' => Border::BORDER_NONE,
                ),
            ),
            'fill' => array(
                'fillType' => Fill::FILL_GRADIENT_LINEAR,
                'rotation' => 90,
                'color' => ['rgb' => 'F0F0F0']
            ),
            'alignment' => array(
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::HORIZONTAL_CENTER,
            ),
        );
        return $styleX;
    }

    private function styleHeader()
    {
        $styleHeader = array(
            'font' => array(
                'size' => 12,
                'bold' => true,
                'align' => 'middle',
            ), 'borders' => array(
                'outline' => array(
                    'borderStyle' => Border::BORDER_THIN,
                )
            ),
            'fill' => array(
                'fillType' => Fill::FILL_GRADIENT_LINEAR,
                'rotation' => 90,
                'color' => ['rgb' => 'F0F0F0']
            ),
            'alignment' => array(
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::HORIZONTAL_CENTER,
            ),
        );


        return $styleHeader;
    }

    private function styleXFix()
    {


        $styleXFix = array(
            'font' => array(
                'size' => 10,
                'bold' => true,
                'align' => 'middle',
            ), 'borders' => array(
                'top' => array(
                    'borderStyle' => Border::BORDER_NONE,
                ),
                'right' => array(
                    'borderStyle' => Border::BORDER_NONE,
                ),
                'left' => array(
                    'borderStyle' => Border::BORDER_NONE,
                ),
                'bottom' => array(
                    'borderStyle' => Border::BORDER_NONE,
                ),
            ),
            'fill' => array(
                'fillType' => Fill::FILL_GRADIENT_LINEAR,
                'rotation' => 90,
                'color' => ['rgb' => 'F0F0F0'],
            ),
            'alignment' => array(
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::HORIZONTAL_CENTER,
            ),
        );
        return $styleXFix;
    }

    private function styleY()
    {

        $styleY = array(
            'font' => array(
                'size' => 10,
            ), 'borders' => array(
                'top' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'right' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'left' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'bottom' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
            ),
        );
        return $styleY;
    }

    private function abstractYBold()
    {

        $abstractYBold = array(
            'font' => array(
                'size' => 10,
                'bold' => true,
            ), 'borders' => array(
                'top' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'right' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'left' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'bottom' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
            ),
        );
        return $abstractYBold;
    }

    private function styleDisable()
    {

        $styleDisable = array(
            'font' => array(
                'size' => 10,
            ), 'borders' => array(
                'top' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'right' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'left' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'bottom' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
            ),
            'fill' => array(
                'fillType' => Fill::FILL_GRADIENT_LINEAR,
                'rotation' => 90,
                'color' => ['rgb' => 'DADADA'],
            ),
        );
        return $styleDisable;
    }

    private function abstractDisable()
    {


        $abstractDisable = array(
            'font' => array(
                'size' => 10,
            ), 'borders' => array(
                'top' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'right' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'left' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
                'bottom' => array(
                    'borderStyle' => Border::BORDER_THIN,
                ),
            ),
            'fill' => array(
                'fillType' => Fill::FILL_GRADIENT_LINEAR,
                'rotation' => 90,
                'color' => ['rgb' => 'FFFFFF'],
            ),
        );
        return $abstractDisable;
    }

}
