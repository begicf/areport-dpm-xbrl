<?php


namespace AReportDpmXBRL\Render\RenderTrait;

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

trait RExcel
{

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
