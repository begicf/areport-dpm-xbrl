<?php

namespace AReportDpmXBRL\Helper;

use AReportDpmXBRL\Config\Config;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Pdf;

/**
 * Class ExtendMpdf
 * @category
 * Areport @package DpmXbrl\Helper
 * @author Fuad Begic <fuad.begic@gmail.com>
 * Date: 12/06/2020
 */
class ExtendMpdf extends Pdf
{

    /**
     * Gets the implementation of external PDF library that should be used.
     *
     * @param array $config Configuration array
     *
     * @return \Mpdf\Mpdf implementation
     */
    private $info = null;

    protected function createExternalWriterInstance()
    {

        $conf1 = [
            'margin_top' => 15,
            'margin_left' => 3,
            'margin_right' => 3,
            'mirrorMargins' => false,
            'default_font_size' => 7,
            'default_font' => 'dejavusans',
            'tempDir' => Config::tmpPdfDir(),
            'curlAllowUnsafeSslRequests' => true
        ];

        return new Mpdf($conf1);
    }

    /**
     * Save Spreadsheet to file.
     *
     * @param string $pFilename Name of the file to save as
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws PhpSpreadsheetException
     */
    public function setInfo($info)
    {
        $this->info = $info;
    }

    public function save($pFilename): void
    {
        $fileHandle = parent::prepareForSave($pFilename);

//  Default PDF paper size
        $paperSize = 'A4'; //    Letter    (8.5 in. by 11 in.)
//  Check for paper size and page orientation
        if (null === $this->getSheetIndex()) {
            $orientation =
                ($this->spreadsheet->getSheet(0)->getPageSetup()->getOrientation() == PageSetup::ORIENTATION_LANDSCAPE) ? 'L' : 'P';
            $printPaperSize = $this->spreadsheet->getSheet(0)->getPageSetup()->getPaperSize();
        } else {
            $orientation =
                ($this->spreadsheet->getSheet($this->getSheetIndex())->getPageSetup()->getOrientation() == PageSetup::ORIENTATION_LANDSCAPE) ? 'L' : 'P';
            $printPaperSize = $this->spreadsheet->getSheet($this->getSheetIndex())->getPageSetup()->getPaperSize();
        }
        $this->setOrientation($orientation);

//  Override Page Orientation
        if (null !== $this->getOrientation()) {
            $orientation =
                ($this->getOrientation() == PageSetup::ORIENTATION_DEFAULT) ? PageSetup::ORIENTATION_PORTRAIT : $this->getOrientation();
        }
        $orientation = strtoupper($orientation);

//  Override Paper Size
        if (null !== $this->getPaperSize()) {
            $printPaperSize = $this->getPaperSize();
        }

        if (isset(self::$paperSizes[$printPaperSize])) {
            //   $paperSize = self::$paperSizes[$printPaperSize];
        }


        $pdf = $this->createExternalWriterInstance();


        $header = '
<div style="font-weight: bold; font-size: 10pt;">
<img height="20" width="30" src="' . Config::setLogoPath() . '"/>
   Areport
</div>';

        $footer = '
<table autosize="1" style="font-size: 7pt;"  width="100%">

    <tr>
     <td>Date: {DATE d-m-Y}</td>
     <td style="text-align: right;" >@areport</td>
    </tr>
</table>';
        $pdf->SetHTMLHeader($header, '0');
        $pdf->SetHTMLFooter($footer, '0');
        $pdf->SetHTMLHeader($header, 'E');
        $pdf->SetHTMLFooter($footer, 'E');


        $ortmp = $orientation;
        $pdf->_setPageSize(strtoupper($paperSize), $ortmp);
        $pdf->DefOrientation = $orientation;
        $pdf->AddPage($orientation);


        //  Document info
        $pdf->SetTitle($this->spreadsheet->getProperties()->getTitle());
        $pdf->SetAuthor($this->spreadsheet->getProperties()->getCreator());
        $pdf->SetSubject($this->spreadsheet->getProperties()->getSubject());
        $pdf->SetKeywords($this->spreadsheet->getProperties()->getKeywords());
        $pdf->SetCreator($this->spreadsheet->getProperties()->getCreator());


        $pdf->WriteHTML(
            $this->HeaderFBA().
            $this->generateSheetData()
            //  $this->generateHTMLFooter().
           // $this->singers()
        );


        //  Write to file
        fwrite($fileHandle, $pdf->Output('', 'S'));

        parent::restoreStateAfterSave($fileHandle);
    }

    private function HeaderFBA()
    {

        $heaader = <<<EOT
<table width="350px"  autosize="1" style="font-size: 7pt; border:1px solid black; margin-bottom:5px;">
   <tr>
      <td style="word-wrap: break-word;" colspan=4>
         <strong>{$this->info['tablename']}</strong>
      </td>
   </tr>

   <tr>
      <td bgcolor="#e0ebff" width='25%'>
         <strong>Date: </strong>
      </td>
      <td style="border-bottom:1px solid black;" width='35%'>

      </td>

   </tr>


</table>



EOT;

        return $heaader;
    }


    private function drawSigner($signer)
    {

        $html = '<td width="48%" align="center" >
         <table  bgcolor="#e0ebff" width="100%" style="border:1px solid black;" >
            <tr>
               <td align="center">' . $signer . '</td>
            </tr>
         </table>
      </td>';

        return $html;
    }

    private function singers()
    {
        if ($this->info['locked_status'] == 'locked'):


            $html = '<table  autosize="1"  width="50%" style="font-size: 6pt; padding-top:10px;">';


            foreach ($this->info['signatures'] as $sing):

                if (isset($sing['description'])):
                    $html .= '<tr>';
                    $html .= $this->drawSigner($sing['description']);
                    $html .= '<td width="4%"></td>';
                    $html .= '</tr>';
                    $html .= '<tr>';
                    $html .= '<td width="48%" align="center"><strong>Potpis ( Ime i prezime / tel. br. ovlaštenog lica )</strong></td>';
                    $html .= '<td width="4%"></td>';
                    $html .= '</tr>';
                endif;

            endforeach;


            $html .= '</table>';


            return $html;
        endif;
    }

}
