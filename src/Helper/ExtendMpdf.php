<?php

namespace AReportDpmXBRL\Helper;

use AReportDpmXBRL\Config\Config;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Pdf;


/**
 * Class ExtendMpdf
 * @category
 * Areport @package AReportDpmXBRL\Config
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class ExtendMpdf extends pdf
{

    /**
     * Gets the implementation of external PDF library that should be used.
     *
     * @param array $config Configuration array
     *
     * @return \Mpdf\Mpdf implementation
     */


    protected function createExternalWriterInstance($config)
    {


        return new \Mpdf\Mpdf($config);
    }


    /**
     * Save Spreadsheet to file.
     *
     * @param string $pFilename Name of the file to save as
     */
    public function save($pFilename): void
    {
        $fileHandle = parent::prepareForSave($pFilename);

        $paperSize = 'A4';

        if (null === $this->getSheetIndex()) {
            $orientation = ($this->spreadsheet->getSheet(0)->getPageSetup()->getOrientation()
                == PageSetup::ORIENTATION_LANDSCAPE) ? 'L' : 'P';
            $printPaperSize = $this->spreadsheet->getSheet(0)->getPageSetup()->getPaperSize();
        } else {
            $orientation = ($this->spreadsheet->getSheet($this->getSheetIndex())->getPageSetup()->getOrientation()
                == PageSetup::ORIENTATION_LANDSCAPE) ? 'L' : 'P';
            $printPaperSize = $this->spreadsheet->getSheet($this->getSheetIndex())->getPageSetup()->getPaperSize();
        }
        $this->setOrientation($orientation);

        //  Override Page Orientation
        if (null !== $this->getOrientation()) {
            $orientation = ($this->getOrientation() == PageSetup::ORIENTATION_DEFAULT)
                ? PageSetup::ORIENTATION_PORTRAIT
                : $this->getOrientation();
        }
        $orientation = strtoupper($orientation);

        //  Override Paper Size
        if (null !== $this->getPaperSize()) {
            $printPaperSize = $this->getPaperSize();
        }

        if (isset(self::$paperSizes[$printPaperSize])) {
            $paperSize = self::$paperSizes[$printPaperSize];
        }

        $config = [
            'margin_top' => 1,
            'margin_left' => 3,
            'margin_right' => 3,
            'mirrorMargins' => true,
            'default_font_size' => 7,
            'default_font' => 'dejavusans',
            'tempDir' => Config::tmpPdfDir(),
            'curlAllowUnsafeSslRequests' => true
        ];

        $pdf = $this->createExternalWriterInstance($config);
        $ortmp = $orientation;
        $pdf->_setPageSize(strtoupper($paperSize), $ortmp);
        $pdf->DefOrientation = $orientation;
//        $pdf->AddPageByArray([
//            'orientation' => $orientation,
//            'margin-left' => $this->inchesToMm($this->spreadsheet->getActiveSheet()->getPageMargins()->getLeft()),
//            'margin-right' => $this->inchesToMm($this->spreadsheet->getActiveSheet()->getPageMargins()->getRight()),
//            'margin-top' => $this->inchesToMm($this->spreadsheet->getActiveSheet()->getPageMargins()->getTop()),
//            'margin-bottom' => $this->inchesToMm($this->spreadsheet->getActiveSheet()->getPageMargins()->getBottom()),
//        ]);

        //  Document info
        $pdf->SetTitle($this->spreadsheet->getProperties()->getTitle());
        $pdf->SetAuthor($this->spreadsheet->getProperties()->getCreator());
        $pdf->SetSubject($this->spreadsheet->getProperties()->getSubject());
        $pdf->SetKeywords($this->spreadsheet->getProperties()->getKeywords());
        $pdf->SetCreator($this->spreadsheet->getProperties()->getCreator());



        $pdf->SetHTMLHeader($this->header());
        $pdf->SetHTMLFooter($this->footer());


        $ortmp = $orientation;
        $pdf->_setPageSize(strtoupper($paperSize), $ortmp);
        $pdf->DefOrientation = $orientation;
       // $pdf->AddPage($orientation);


        $html = $this->generateHTMLAll();
        foreach (\array_chunk(\explode(PHP_EOL, $html), 1000) as $lines) {
            $pdf->WriteHTML(\implode(PHP_EOL, $lines) . $this->generateHTMLFooter());
        }

        //  Write to file
        fwrite($fileHandle, $pdf->Output('', 'S'));

        parent::restoreStateAfterSave();
    }

    /**
     * Convert inches to mm.
     *
     * @param float $inches
     *
     * @return float
     */
    private function inchesToMm($inches)
    {
        return $inches * 25.4;
    }

    private function footer()
    {

        $footer = <<<EOT
                <table autosize="1" style="font-size: 7pt;"  width="100%">
                    <tr>
                     <td>Date: {DATE d-m-Y}</td>
                     <td style="text-align: right;" >The form was created by © Areport based on XBRL specifications</td>
                     <td style="text-align: right;">   Page: {PAGENO}/{nbpg}</td>
                    </tr>
                </table>
EOT;
        return $footer;
    }


    private function header()
    {
        $img=Config::setLogoPath();
        $header = <<<EOT
                <div style="font-weight: bold; font-size: 10pt;">
                <img height="40" width="50" src="{$img}"/>
                </div>
EOT;
        return $header;
    }


}
