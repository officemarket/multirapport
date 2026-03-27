<?php
/* Copyright (C) 2024-2025 Your Name <your.email@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       /custom/multirapport/core/modules/rapport/pdf_multirapport.class.php
 * \ingroup    multirapport
 * \brief      Class to generate MultiRapport report with Sponge template
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

/**
 * Class to manage MultiRapport PDF generation
 */
class pdf_multirapport extends CommonDocGenerator
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Name of generator
     */
    public $name = 'multirapport';

    /**
     * @var string Description of generator
     */
    public $description = 'MultiRapport Report';

    /**
     * @var int Page width
     */
    public $page_largeur;

    /**
     * @var int Page height
     */
    public $page_hauteur;

    /**
     * @var array Format page
     */
    public $format;

    /**
     * @var int Left margin
     */
    public $marge_gauche = 10;

    /**
     * @var int Right margin
     */
    public $marge_droite = 10;

    /**
     * @var int Top margin
     */
    public $marge_haute = 10;

    /**
     * @var int Bottom margin
     */
    public $marge_basse = 10;

    /**
     * @var int Tab position
     */
    public $tab_top = 45;

    /**
     * @var int Line height
     */
    public $line_height = 5;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $conf, $langs;

        $this->db = $db;
        $this->description = $langs->transnoentities('PDFReportTitle');

        // Page format (A4 by default)
        $formatarray = pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        $this->page_hauteur = $formatarray['height'];
        $this->format = array($this->page_largeur, $this->page_hauteur);

        // Margins
        $this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
        $this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
        $this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
        $this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
    }

    /**
     * Write PDF file
     *
     * @param string    $dir          Directory to save file
     * @param array     $metrics      Array of metrics
     * @param array     $creditInvoices Array of credit invoices
     * @param int       $dateStart    Start timestamp
     * @param int       $dateEnd      End timestamp
     * @param int       $hourStart    Start hour
     * @param int       $hourEnd      End hour
     * @param Translate $outputlangs  Output language
     * @return int                    Return code
     */
    public function write_file($dir, $metrics, $creditInvoices, $dateStart, $dateEnd, $hourStart, $hourEnd, $outputlangs)
    {
        global $conf, $hookmanager, $langs, $user, $mysoc;

        if (!is_object($outputlangs)) {
            $outputlangs = $langs;
        }

        $this->emetteur = $mysoc;

        $outputlangs->load('main');
        $outputlangs->load('bills');
        $outputlangs->load('companies');
        $outputlangs->load('multirapport@multirapport');

        // Create directory if not exists
        if (!is_dir($dir)) {
            $result = dol_mkdir($dir);
            if ($result < 0) {
                $this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
                return -1;
            }
        }

        // Filename
        $file = $dir . '/multirapport_' . dol_print_date($dateStart, '%Y%m%d') . '_' . dol_print_date($dateEnd, '%Y%m%d') . '.pdf';

        // Add pdfgeneration hook
        if (!is_object($hookmanager)) {
            include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
            $hookmanager = new HookManager($this->db);
        }
        $hookmanager->initHooks(array('pdfgeneration'));
        $parameters = array('file' => $file, 'object' => $this, 'outputlangs' => $outputlangs);
        global $action;
        $reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $this, $action);

        // Create PDF instance
        $pdf = pdf_getInstance($this->format);
        $default_font_size = pdf_getPDFFontSize($outputlangs);

        if (class_exists('TCPDF')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }

        $pdf->SetFont(pdf_getPDFFont($outputlangs));
        $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
        $pdf->setAutoPageBreak(true, $this->marge_basse);

        // New page
        $pdf->AddPage();
        $this->_pagehead($pdf, $outputlangs, $dateStart, $dateEnd, $hourStart, $hourEnd);

        // Title
        $pdf->SetFont('', 'B', 16);
        $pdf->SetTextColor(107, 76, 154); // Deep purple
        $pdf->SetXY($this->marge_gauche, 25);
        $pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 10, $outputlangs->transnoentities('PDFReportTitle'), 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        // Period info
        $pdf->SetFont('', 'B', 9);
        $pdf->SetXY($this->marge_gauche, 35);
        $periodText = $outputlangs->transnoentities('ReportPeriod') . ': ';
        $pdf->Cell(40, 5, $periodText, 0, 0, 'L');
        $pdf->SetFont('', '', 9);
        $periodDates = dol_print_date($dateStart, 'day') . ' ' . sprintf('%02d:00', $hourStart);
        $periodDates .= ' - ';
        $periodDates .= dol_print_date($dateEnd, 'day') . ' ' . sprintf('%02d:00', $hourEnd);
        $pdf->Cell(0, 5, $periodDates, 0, 1, 'L');

        // Generated on
        $pdf->SetFont('', 'B', 9);
        $pdf->SetX($this->marge_gauche);
        $pdf->Cell(40, 5, $outputlangs->transnoentities('ReportGeneratedOn') . ': ', 0, 0, 'L');
        $pdf->SetFont('', '', 9);
        $pdf->Cell(0, 5, dol_print_date(dol_now(), 'dayhour'), 0, 1, 'L');

        $pdf->Ln(4);
        $this->tab_top = $pdf->GetY();

        // Financial Metrics Section
        $pdf->SetY($this->tab_top);
        $pdf->SetFont('', 'B', 11);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 7, $outputlangs->transnoentities('FinancialMetrics'), 0, 1, 'L', true);
        $pdf->Ln(1);

        // Metrics table
        $pdf->SetFont('', '', 10);
        $col1Width = 140;
        $col2Width = $this->page_largeur - $this->marge_gauche - $this->marge_droite - $col1Width;

        // Header for metrics
        $pdf->SetFont('', 'B', 10);
        $pdf->SetFillColor(107, 76, 154); // Deep purple
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($col1Width, $this->line_height + 2, $outputlangs->transnoentities('Description'), 1, 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 2, $outputlangs->transnoentities('Amount'), 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', 10);

        // Total Revenue
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell($col1Width, $this->line_height + 1, $outputlangs->transnoentities('TotalRevenue'), 'LRB', 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 1, price($metrics['total_revenue']), 'RB', 1, 'R');

        // Total Paid
        $pdf->Cell($col1Width, $this->line_height + 1, $outputlangs->transnoentities('TotalPaidInvoices'), 'LRB', 0, 'L', false);
        $pdf->Cell($col2Width, $this->line_height + 1, price($metrics['total_paid']), 'RB', 1, 'R');

        // Total Unpaid
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell($col1Width, $this->line_height + 1, $outputlangs->transnoentities('TotalUnpaidInvoices'), 'LRB', 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 1, price($metrics['total_unpaid']), 'RB', 1, 'R');

        // Total Expenses
        $pdf->Cell($col1Width, $this->line_height + 1, $outputlangs->transnoentities('TotalExpenses'), 'LRB', 0, 'L', false);
        $pdf->Cell($col2Width, $this->line_height + 1, price($metrics['total_expenses']), 'RB', 1, 'R');

        // Total Credit Status (not paid yet - highlighted in orange)
        $pdf->SetFillColor(230, 126, 34);  // Orange
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('', 'B', 10);
        $pdf->Cell($col1Width, $this->line_height + 2, $outputlangs->transnoentities('TotalCreditStatusInvoices'), 1, 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 2, price($metrics['total_credit_status']), 1, 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', 10);

        // Total Credit Paid (highlighted in purple)
        $pdf->SetFillColor(107, 76, 154);  // Deep purple
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('', 'B', 10);
        $pdf->Cell($col1Width, $this->line_height + 2, $outputlangs->transnoentities('TotalCreditPaidInvoices'), 1, 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 2, price($metrics['total_credit_paid']), 1, 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', 10);

        $pdf->Ln(3);

        // Credit Paid Invoices List Section
        if (!empty($creditInvoices)) {
            $pdf->SetFont('', 'B', 11);
            $pdf->SetFillColor(240, 230, 255);
            $pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 7, $outputlangs->transnoentities('CreditPaidInvoicesList'), 0, 1, 'L', true);
            $pdf->Ln(1);

            // Table header
            $pdf->SetFont('', 'B', 10);
            $pdf->SetFillColor(107, 76, 154); // Deep purple
            $pdf->SetTextColor(255, 255, 255);
            $colRefWidth = 40;
            $colThirdPartyWidth = 70;
            $colDateWidth = 35;
            $colAmountWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite - $colRefWidth - $colThirdPartyWidth - $colDateWidth;

            $pdf->Cell($colRefWidth, 8, $outputlangs->transnoentities('Ref'), 1, 0, 'C', true);
            $pdf->Cell($colThirdPartyWidth, 8, $outputlangs->transnoentities('ThirdParty'), 1, 0, 'C', true);
            $pdf->Cell($colDateWidth, 8, $outputlangs->transnoentities('Date'), 1, 0, 'C', true);
            $pdf->Cell($colAmountWidth, 8, $outputlangs->transnoentities('AmountTTC'), 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);

            // Table rows
            $pdf->SetFont('', '', 8);
            $fill = false;
            foreach ($creditInvoices as $invoice) {
                // Check if we need a new page
                if ($pdf->GetY() > $this->page_hauteur - $this->marge_basse - 15) {
                    $pdf->AddPage();
                    $this->_pagehead($pdf, $outputlangs, $dateStart, $dateEnd, $hourStart, $hourEnd);
                    $pdf->SetY($this->marge_haute + 25);
                }
                
                $fill = !$fill;
                if ($fill) {
                    $pdf->SetFillColor(250, 250, 250);
                } else {
                    $pdf->SetFillColor(255, 255, 255);
                }

                $pdf->Cell($colRefWidth, 6, $invoice['ref'], 1, 0, 'L', $fill);
                $pdf->Cell($colThirdPartyWidth, 6, dol_trunc($invoice['socname'], 30), 1, 0, 'L', $fill);
                $pdf->Cell($colDateWidth, 6, dol_print_date($invoice['datef'], 'day'), 1, 0, 'C', $fill);
                $pdf->Cell($colAmountWidth, 6, price($invoice['total_ttc']), 1, 1, 'R', $fill);
            }

            // Total row
            $pdf->SetFont('', 'B', 9);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell($colRefWidth + $colThirdPartyWidth + $colDateWidth, 7, $outputlangs->transnoentities('Total'), 1, 0, 'R', true);
            $pdf->Cell($colAmountWidth, 7, price($metrics['total_credit_paid']), 1, 1, 'R', true);
        }

        // Footer - Generated by
        $pdf->SetY(-15);
        $pdf->SetFont('', 'I', 8);
        $pdf->Cell(0, 10, $outputlangs->transnoentities('GeneratedByDolibarr') . ' - ' . $user->getFullName($outputlangs), 0, 0, 'C');

        // Close and output PDF
        $pdf->Close();
        $pdf->Output($file, 'F');

        // Hook afterPDFCreation
        if (!is_object($hookmanager)) {
            include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
            $hookmanager = new HookManager($this->db);
        }
        $hookmanager->initHooks(array('pdfgeneration'));
        $parameters = array('file' => $file, 'object' => $this, 'outputlangs' => $outputlangs);
        $reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);

        dolChmod($file);

        $this->result = array('fullpath' => $file);

        return 1;
    }

    /**
     * Show page head
     *
     * @param TCPDF     $pdf          PDF object
     * @param Translate $outputlangs  Output language
     * @param int       $dateStart    Start timestamp
     * @param int       $dateEnd      End timestamp
     * @param int       $hourStart    Start hour
     * @param int       $hourEnd      End hour
     * @return void
     */
    protected function _pagehead($pdf, $outputlangs, $dateStart, $dateEnd, $hourStart, $hourEnd)
    {
        global $conf;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs));

        // Company logo
        $logo = $conf->mycompany->dir_output . '/logos/' . $this->emetteur->logo;
        if ($this->emetteur->logo && is_readable($logo)) {
            $height = pdf_getHeightForLogo($logo);
            $pdf->Image($logo, $this->marge_gauche, $this->marge_haute, 0, 15);
        }

        // Company info (Right aligned)
        $pdf->SetFont('', 'B', 11);
        $pdf->SetXY($this->page_largeur - $this->marge_droite - 100, $this->marge_haute);
        $pdf->Cell(100, 6, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 1, 'R');
        
        $pdf->SetFont('', '', 8);
        $pdf->SetX($this->page_largeur - $this->marge_droite - 100);
        $address = $outputlangs->convToOutputCharset($this->emetteur->address) . "\n" . $outputlangs->convToOutputCharset($this->emetteur->zip) . ' ' . $outputlangs->convToOutputCharset($this->emetteur->town);
        $pdf->MultiCell(100, 4, $address, 0, 'R');

        // Horizontal line
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line($this->marge_gauche, $this->marge_haute + 20, $this->page_largeur - $this->marge_droite, $this->marge_haute + 20);
    }
}
