<?php
/**
 *  PDFMerger created by Jarrod Nettles December 2009
 *  jarrod@squarecrow.com
 *
 *  v1.0
 *
 * Class for easily merging PDFs (or specific pages of PDFs) together into one. Output to a file, browser, download, or return as a string.
 * Unfortunately, this class does not preserve many of the enhancements your original PDF might contain. It treats
 * your PDF page as an image and then concatenates them all together.
 *
 * Note that your PDFs are merged in the order that you provide them using the addPDF function, same as the pages.
 * If you put pages 12-14 before 1-5 then 12-15 will be placed first in the output.
 *
 *
 * Uses FPDI 1.3.1 from Setasign
 * Uses FPDF 1.6 by Olivier Plathey with FPDF_TPL extension 1.1.3 by Setasign
 *
 * Both of these packages are free and open source software, bundled with this class for ease of use.
 * They are not modified in any way. PDFMerger has all the limitations of the FPDI package - essentially, it cannot import dynamic content
 * such as form fields, links or page annotations (anything not a part of the page content stream).
 *
 */
namespace Clegginabox\PDFMerger;

use Exception;
use setasign\Fpdi\Fpdi;

class PDFMerger
{
    private array $_files = [];

    /**
     * Add a PDF for inclusion in the merge with a valid file path. Pages should be formatted: 1,3,6, 12-16.
     * @param string $filepath
     * @param string|int $pages
     * @param string|null $orientation
     * @return PDFMerger
     * @throws Exception
     */
    public function addPDF($filepath, $pages = 'all', $orientation = null)
    {
        if (file_exists($filepath)) {
            if (strtolower($pages) != 'all') {
                $pages = $this->_rewritePages($pages);
            }

            $this->_files[] = array($filepath, $pages, $orientation);
        } else {
            throw new Exception("Could not locate PDF on '$filepath'");
        }

        return $this;
    }

    /**
     * Merges your provided PDFs and outputs to specified location.
     * @param string $outMode
     * @param string $outPath
     * @param string $orientation
     * @return PDFMerger|bool|string
     */
    public function merge(?string $outMode = 'browser', ?string $outPath = 'newfile.pdf', ?string $orientation = 'A')
    {
        if (!isset($this->_files) || !is_array($this->_files)) {
            throw new Exception("No PDFs to merge.");
        }

        $fpdi = new Fpdi();

        // merger operations
        foreach ($this->_files as $file) {
            $filename  = $file[0];
            $filePages = $file[1];
            $fileOrientation = (!is_null($file[2])) ? $file[2] : $orientation;

            $count = $fpdi->setSourceFile($filename);

            //add the pages
            if ($filePages == 'all') {
                for ($i=1; $i<=$count; $i++) {
                    $template   = $fpdi->importPage($i);
                    $this->_addTemplatePage($fpdi, $template, $fileOrientation);
                }
            } else {
                foreach ($filePages as $page) {
                    if (!$template = $fpdi->importPage($page)) {
                        throw new Exception("Could not load page '$page' in PDF '$filename'. Check that the page exists.");
                    }
                    $this->_addTemplatePage($fpdi, $template, $fileOrientation);
                }
            }
        }

        //output operations
        $mode = $this->_switchMode($outMode);

        if ($mode == 'S') {
            return $fpdi->Output($outPath, 'S');
        } else {
            if ($fpdi->Output($outPath, $mode) == '') {
                return true;
            } else {
                throw new Exception("Error outputting PDF to '$outMode'.");
            }
        }
    }

    /**
     * FPDI uses single characters for specifying the output location. Change our more descriptive string into proper format.
     * @param string $mode
     * @return string
     */
    private function _switchMode($mode)
    {
        switch(strtolower($mode ?? ''))
        {
            case 'download':
                return 'D';
            case 'file':
                return 'F';
            case 'string':
                return 'S';
            case 'browser':
            default:
                return 'I';
        }
    }

    /**
     * Takes our provided pages in the form of 1,3,4,16-50 and creates an array of all pages
     * @param string $pages
     * @return array
     * @throws Exception
     */
    private function _rewritePages($pages)
    {
        $pages = str_replace(' ', '', $pages ?? '');
        $part = explode(',', $pages ?? '');

        //parse hyphens
        $newPages = [];
        foreach ($part as $i) {
            $ind = explode('-', $i);

            if (count($ind) == 2) {
                $x = $ind[0]; //start page
                $y = $ind[1]; //end page

                if ($x > $y) {
                    throw new Exception("Starting page, '$x' is greater than ending page '$y'.");
                }

                //add middle pages
                while ($x <= $y) {
                    $newPages[] = (int) $x;
                    $x++;
                }
            } else {
                $newPages[] = (int) $ind[0];
            }
        }

        return $newPages;
    }

    /**
     * Helper method to add a template page to the FPDI object with correct orientation and size.
     * @param Fpdi $fpdi
     * @param int $template
     * @param string $fileOrientation
     * @return void
     */
    private function _addTemplatePage($fpdi, $template, $fileOrientation)
    {
        $size = $fpdi->getTemplateSize($template);
        $pageOrientation = ($size['width'] > $size['height']) ? 'L' : 'P';  // Determine orientation for this specific page

        if($fileOrientation !== 'A') {  // If an orientation was provided for the whole document, use it
            $pageOrientation = $fileOrientation;
        }
        $fpdi->AddPage($pageOrientation, array($size['width'], $size['height']));
        $fpdi->useTemplate($template);
    }
}
