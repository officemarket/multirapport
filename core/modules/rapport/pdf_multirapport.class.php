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
     * @var int Y position for body content after header (set by _pagehead)
     */
    protected $content_start_y = 45;

    /**
     * @var int Line height
     */
    public $line_height = 5;

    /** @var array RGB brand color (aligned with module UI #6b4c9a) */
    protected $color_brand = array(107, 76, 154);

    /** @var array RGB accent orange (credit status) */
    protected $color_accent_orange = array(230, 126, 34);

    /** @var array RGB titre / texte principal (modèle Sponge : bleu nuit) */
    protected $color_title_navy = array(0, 0, 60);

    /** @var array RGB en-têtes de tableau type Sponge */
    protected $color_header_fill = array(224, 224, 224);

    /** @var array RGB lignes de grille PDF Dolibarr */
    protected $color_draw = array(128, 128, 128);

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

        if (class_exists('TCPDF')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }

        if (method_exists($pdf, 'AliasNbPages')) {
            $pdf->AliasNbPages(); // @phan-suppress-current-line PhanUndeclaredMethod
        }

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $pdf->SetFont(pdf_getPDFFont($outputlangs));
        $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
        // Espace pour pied de page (ligne + 2 lignes de texte + pagination)
        $pdf->setAutoPageBreak(true, max($this->marge_basse, 22));

        // Première page (fond PDF optionnel comme Sponge + entête société)
        $this->_startNewPage($pdf, $outputlangs, $dateStart, $dateEnd, $hourStart, $hourEnd);

        $usableW = $this->page_largeur - $this->marge_gauche - $this->marge_droite;

        // Titre du rapport (style Sponge : gras + bleu nuit)
        $pdf->SetXY($this->marge_gauche, $this->content_start_y);
        $pdf->SetFont('', 'B', $default_font_size + 5);
        $pdf->SetTextColor($this->color_title_navy[0], $this->color_title_navy[1], $this->color_title_navy[2]);
        $pdf->Cell($usableW, 9, $outputlangs->convToOutputCharset($outputlangs->transnoentities('PDFReportTitle')), 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        // Period info
        $pdf->SetFont('', 'B', 9);
        $pdf->SetX($this->marge_gauche);
        $periodText = $outputlangs->transnoentities('ReportPeriod') . ' :';
        $pdf->Cell(42, 5, $outputlangs->convToOutputCharset($periodText), 0, 0, 'L');
        $pdf->SetFont('', '', 9);
        $periodDates = dol_print_date($dateStart, 'day') . ' ' . sprintf('%02d:00', $hourStart);
        $periodDates .= ' — ';
        $periodDates .= dol_print_date($dateEnd, 'day') . ' ' . sprintf('%02d:00', $hourEnd);
        $pdf->Cell(0, 5, $outputlangs->convToOutputCharset($periodDates), 0, 1, 'L');

        // Generated on
        $pdf->SetFont('', 'B', 9);
        $pdf->SetX($this->marge_gauche);
        $pdf->Cell(42, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('ReportGeneratedOn') . ' :'), 0, 0, 'L');
        $pdf->SetFont('', '', 9);
        $pdf->Cell(0, 5, $outputlangs->convToOutputCharset(dol_print_date(dol_now(), 'dayhour')), 0, 1, 'L');

        $pdf->Ln(3);
        $this->tab_top = $pdf->GetY();

        // Financial Metrics Section (bandeau type Sponge)
        $pdf->SetY($this->tab_top);
        $pdf->SetFont('', 'B', 11);
        $pdf->SetFillColor($this->color_header_fill[0], $this->color_header_fill[1], $this->color_header_fill[2]);
        $pdf->SetTextColor($this->color_title_navy[0], $this->color_title_navy[1], $this->color_title_navy[2]);
        $pdf->Cell($usableW, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities('FinancialMetrics')), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(1);

        // Metrics table — proportional column widths
        $pdf->SetFont('', '', 10);
        $col1Width = $usableW * 0.62;
        $col2Width = $usableW - $col1Width;

        // Header for metrics (identique aux PDF facture Sponge : fond gris + texte bleu nuit)
        $pdf->SetFont('', 'B', 10);
        $pdf->SetDrawColor($this->color_draw[0], $this->color_draw[1], $this->color_draw[2]);
        $pdf->SetFillColor($this->color_header_fill[0], $this->color_header_fill[1], $this->color_header_fill[2]);
        $pdf->SetTextColor($this->color_title_navy[0], $this->color_title_navy[1], $this->color_title_navy[2]);
        $pdf->Cell($col1Width, $this->line_height + 2, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Description')), 1, 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 2, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Amount')), 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', 10);

        $totalRevenue = isset($metrics['total_revenue']) ? (float) $metrics['total_revenue'] : 0;
        $totalExpenses = isset($metrics['total_expenses']) ? (float) $metrics['total_expenses'] : 0;
        $netProfit = $totalRevenue - $totalExpenses;

        $pdf->SetDrawColor($this->color_draw[0], $this->color_draw[1], $this->color_draw[2]);

        // Total Revenue
        $pdf->SetFillColor(248, 248, 248);
        $pdf->Cell($col1Width, $this->line_height + 1, $outputlangs->convToOutputCharset($outputlangs->transnoentities('TotalRevenue')), 'LRB', 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 1, price($totalRevenue), 'RB', 1, 'R');

        // Total Paid
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell($col1Width, $this->line_height + 1, $outputlangs->convToOutputCharset($outputlangs->transnoentities('TotalPaidInvoices')), 'LRB', 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 1, price($metrics['total_paid'] ?? 0), 'RB', 1, 'R');

        // Total Unpaid
        $pdf->SetFillColor(248, 248, 248);
        $pdf->Cell($col1Width, $this->line_height + 1, $outputlangs->convToOutputCharset($outputlangs->transnoentities('TotalUnpaidInvoices')), 'LRB', 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 1, price($metrics['total_unpaid'] ?? 0), 'RB', 1, 'R');

        // Total Expenses
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell($col1Width, $this->line_height + 1, $outputlangs->convToOutputCharset($outputlangs->transnoentities('TotalExpenses')), 'LRB', 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 1, price($totalExpenses), 'RB', 1, 'R');

        // Total Margin (module marge)
        if (isModEnabled('margin') && isset($metrics['total_margin'])) {
            $pdf->SetFillColor(248, 248, 248);
            $pdf->Cell($col1Width, $this->line_height + 1, $outputlangs->convToOutputCharset($outputlangs->transnoentities('TotalMargin')), 'LRB', 0, 'L', true);
            $pdf->SetTextColor(40, 120, 70);
            $pdf->Cell($col2Width, $this->line_height + 1, price($metrics['total_margin']), 'RB', 1, 'R', true);
            $pdf->SetTextColor(0, 0, 0);
        }

        // Net profit / result (same as web breakdown)
        $pdf->SetFont('', 'B', 10);
        $pdf->SetFillColor(240, 248, 240);
        $pdf->SetTextColor($this->color_title_navy[0], $this->color_title_navy[1], $this->color_title_navy[2]);
        $pdf->Cell($col1Width, $this->line_height + 2, $outputlangs->convToOutputCharset($outputlangs->transnoentities('NetProfit')), 'LRB', 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 2, price($netProfit), 'RB', 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', 10);

        // Total Credit Status (mise en évidence métier : orange)
        $pdf->SetFillColor($this->color_accent_orange[0], $this->color_accent_orange[1], $this->color_accent_orange[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('', 'B', 10);
        $pdf->Cell($col1Width, $this->line_height + 2, $outputlangs->convToOutputCharset($outputlangs->transnoentities('TotalCreditStatusInvoices')), 1, 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 2, price($metrics['total_credit_status'] ?? 0), 1, 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', 10);

        // Total Credit Paid (accent identité module)
        $pdf->SetFillColor($this->color_brand[0], $this->color_brand[1], $this->color_brand[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('', 'B', 10);
        $pdf->Cell($col1Width, $this->line_height + 2, $outputlangs->convToOutputCharset($outputlangs->transnoentities('TotalCreditPaidInvoices')), 1, 0, 'L', true);
        $pdf->Cell($col2Width, $this->line_height + 2, price($metrics['total_credit_paid'] ?? 0), 1, 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', 10);

        $pdf->Ln(3);

        // Credit Paid Invoices List Section
        if (!empty($creditInvoices)) {
            $pdf->SetFont('', 'B', 11);
            $pdf->SetFillColor($this->color_header_fill[0], $this->color_header_fill[1], $this->color_header_fill[2]);
            $pdf->SetTextColor($this->color_title_navy[0], $this->color_title_navy[1], $this->color_title_navy[2]);
            $pdf->Cell($usableW, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities('CreditPaidInvoicesList')), 0, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(1);

            // Table header — widths from usable width (style Sponge)
            $pdf->SetFont('', 'B', 9);
            $pdf->SetDrawColor($this->color_draw[0], $this->color_draw[1], $this->color_draw[2]);
            $pdf->SetFillColor($this->color_header_fill[0], $this->color_header_fill[1], $this->color_header_fill[2]);
            $pdf->SetTextColor($this->color_title_navy[0], $this->color_title_navy[1], $this->color_title_navy[2]);
            $colRefWidth = round($usableW * 0.18);
            $colThirdPartyWidth = round($usableW * 0.38);
            $colDateWidth = round($usableW * 0.18);
            $colAmountWidth = $usableW - $colRefWidth - $colThirdPartyWidth - $colDateWidth;

            $pdf->Cell($colRefWidth, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Ref')), 1, 0, 'C', true);
            $pdf->Cell($colThirdPartyWidth, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities('ThirdParty')), 1, 0, 'C', true);
            $pdf->Cell($colDateWidth, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Date')), 1, 0, 'C', true);
            $pdf->Cell($colAmountWidth, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AmountTTC')), 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);

            // Table rows
            $pdf->SetFont('', '', 8);
            $fill = false;
            $rowH = 6;
            $footerReserve = 22;
            foreach ($creditInvoices as $invoice) {
                if ($pdf->GetY() > $this->page_hauteur - $this->marge_basse - $footerReserve - $rowH) {
                    $this->_startNewPage($pdf, $outputlangs, $dateStart, $dateEnd, $hourStart, $hourEnd);
                    $pdf->SetXY($this->marge_gauche, $this->content_start_y);
                    $pdf->SetFont('', 'I', 9);
                    $pdf->SetTextColor($this->color_title_navy[0], $this->color_title_navy[1], $this->color_title_navy[2]);
                    $pdf->Cell($usableW, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('CreditPaidInvoicesList') . ' — ' . $outputlangs->transnoentities('PDFCreditListContinuation')), 0, 1, 'L');
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('', 'B', 9);
                    $pdf->SetDrawColor($this->color_draw[0], $this->color_draw[1], $this->color_draw[2]);
                    $pdf->SetFillColor($this->color_header_fill[0], $this->color_header_fill[1], $this->color_header_fill[2]);
                    $pdf->SetTextColor($this->color_title_navy[0], $this->color_title_navy[1], $this->color_title_navy[2]);
                    $pdf->Cell($colRefWidth, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Ref')), 1, 0, 'C', true);
                    $pdf->Cell($colThirdPartyWidth, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities('ThirdParty')), 1, 0, 'C', true);
                    $pdf->Cell($colDateWidth, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Date')), 1, 0, 'C', true);
                    $pdf->Cell($colAmountWidth, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AmountTTC')), 1, 1, 'C', true);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('', '', 8);
                }

                $fill = !$fill;
                $pdf->SetFillColor($fill ? 248 : 255, $fill ? 248 : 255, $fill ? 248 : 255);

                $refStr = $outputlangs->convToOutputCharset($invoice['ref']);
                $socStr = $outputlangs->convToOutputCharset(dol_trunc($invoice['socname'], 42));
                $pdf->Cell($colRefWidth, $rowH, $refStr, 1, 0, 'L', $fill);
                $pdf->Cell($colThirdPartyWidth, $rowH, $socStr, 1, 0, 'L', $fill);
                $pdf->Cell($colDateWidth, $rowH, dol_print_date($invoice['datef'], 'day'), 1, 0, 'C', $fill);
                $pdf->Cell($colAmountWidth, $rowH, price($invoice['total_ttc']), 1, 1, 'R', $fill);
            }

            // Total row
            if ($pdf->GetY() > $this->page_hauteur - $this->marge_basse - $footerReserve - 8) {
                $this->_startNewPage($pdf, $outputlangs, $dateStart, $dateEnd, $hourStart, $hourEnd);
                $pdf->SetY($this->content_start_y);
            }
            $pdf->SetFont('', 'B', 9);
            $pdf->SetDrawColor($this->color_draw[0], $this->color_draw[1], $this->color_draw[2]);
            $pdf->SetFillColor($this->color_header_fill[0], $this->color_header_fill[1], $this->color_header_fill[2]);
            $pdf->SetTextColor($this->color_title_navy[0], $this->color_title_navy[1], $this->color_title_navy[2]);
            $pdf->Cell($colRefWidth + $colThirdPartyWidth + $colDateWidth, 7, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Total')), 1, 0, 'R', true);
            $pdf->Cell($colAmountWidth, 7, price($metrics['total_credit_paid'] ?? 0), 1, 1, 'R', true);
            $pdf->SetTextColor(0, 0, 0);
        }

        // Footer on every page (generated by + page numbers)
        $this->_drawPdfFooters($pdf, $outputlangs, $user);

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
     * Nouvelle page : fond PDF global (comme Sponge) + entête société
     *
     * @param TCPDF     $pdf          PDF object
     * @param Translate $outputlangs  Output language
     * @param int       $dateStart    Start timestamp
     * @param int       $dateEnd      End timestamp
     * @param int       $hourStart    Start hour
     * @param int       $hourEnd      End hour
     * @return void
     */
    protected function _startNewPage($pdf, $outputlangs, $dateStart, $dateEnd, $hourStart, $hourEnd)
    {
        $pdf->AddPage();
        pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
        $this->_pagehead($pdf, $outputlangs, $dateStart, $dateEnd, $hourStart, $hourEnd);
    }

    /**
     * Show page head (logique proche du modèle Sponge : logo thumbs / grand logo, bloc société à droite)
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

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', $default_font_size);

        $headerTop = $this->marge_haute;
        $logoMaxH = 18;

        $pdf->SetXY($this->marge_gauche, $headerTop);

        // Logo (même logique que pdf_sponge : répertoire entité + vignette ou grand logo)
        if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO') && !empty($this->emetteur->logo)) {
            $logodir = $conf->mycompany->dir_output;
            $entity = isset($conf->entity) ? ((int) $conf->entity) : 1;
            if (!empty($conf->mycompany->multidir_output[$entity])) {
                $logodir = $conf->mycompany->multidir_output[$entity];
            }
            if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO')) {
                $logo = $logodir . '/logos/thumbs/' . $this->emetteur->logo_small;
            } else {
                $logo = $logodir . '/logos/' . $this->emetteur->logo;
            }
            if (!is_readable($logo) && !getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO')) {
                $logo = $logodir . '/logos/' . $this->emetteur->logo;
            }
            if (is_readable($logo)) {
                $height = pdf_getHeightForLogo($logo);
                if ($height > $logoMaxH) {
                    $height = $logoMaxH;
                }
                $pdf->Image($logo, $this->marge_gauche, $headerTop, 0, $height);
            }
        }

        // Company info (right aligned)
        $blockW = min(105, $this->page_largeur - $this->marge_gauche - $this->marge_droite - 45);
        $pdf->SetFont('', 'B', 11);
        $pdf->SetTextColor($this->color_title_navy[0], $this->color_title_navy[1], $this->color_title_navy[2]);
        $pdf->SetXY($this->page_largeur - $this->marge_droite - $blockW, $headerTop);
        $pdf->MultiCell($blockW, 5, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'R');

        $pdf->SetFont('', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetX($this->page_largeur - $this->marge_droite - $blockW);
        $address = $outputlangs->convToOutputCharset($this->emetteur->address) . "\n" . $outputlangs->convToOutputCharset($this->emetteur->zip) . ' ' . $outputlangs->convToOutputCharset($this->emetteur->town);
        $pdf->MultiCell($blockW, 3.8, $address, 0, 'R');

        $lineY = max($headerTop + $logoMaxH + 2, (float) $pdf->GetY() + 2);
        $pdf->SetDrawColor($this->color_draw[0], $this->color_draw[1], $this->color_draw[2]);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($this->marge_gauche, $lineY, $this->page_largeur - $this->marge_droite, $lineY);

        $this->content_start_y = (int) $lineY + 5;
    }

    /**
     * Pied de page sur chaque page : style proche pdf.lib (ligne grise, pagination à droite)
     *
     * @param TCPDF     $pdf         PDF
     * @param Translate $outputlangs Lang
     * @param User      $user        User
     * @return void
     */
    protected function _drawPdfFooters($pdf, $outputlangs, $user)
    {
        global $conf;

        $nb = $pdf->getNumPages();
        $companyName = !empty($this->emetteur->name) ? $this->emetteur->name : '—';
        $lineCompany = $outputlangs->convToOutputCharset($outputlangs->trans('PDFGeneratedByCompany', $companyName));
        $lineUser = $outputlangs->convToOutputCharset($outputlangs->trans('PDFReportPreparedBy', $user->getFullName($outputlangs)));

        $footerRgb = array(80, 80, 85);
        if (getDolGlobalString('PDF_FOOTER_TEXT_COLOR')) {
            $tr = 0;
            $tg = 0;
            $tb = 0;
            if (sscanf(getDolGlobalString('PDF_FOOTER_TEXT_COLOR'), '%d, %d, %d', $tr, $tg, $tb) === 3) {
                $footerRgb = array((int) $tr, (int) $tg, (int) $tb);
            }
        }

        $fontRenderCorrection = 0;
        if (in_array(pdf_getPDFFont($outputlangs), array('freemono', 'DejaVuSans'))) {
            $fontRenderCorrection = 10;
        }
        $pageNumW = 18 + $fontRenderCorrection;

        for ($p = 1; $p <= $nb; $p++) {
            $pdf->setPage($p);
            $dims = $pdf->getPageDimensions();
            $lm = $dims['lm'];
            $rm = $dims['rm'];
            $wk = $dims['wk'];
            $wFooter = $wk - $lm - $rm;

            $pdf->SetY(-18);
            $pdf->SetDrawColor(224, 224, 224);
            $yLine = $pdf->GetY() - 0.5;
            $pdf->Line($lm, $yLine, $wk - $rm, $yLine);

            $pagination = $pdf->PageNo() . ' / ' . $pdf->getAliasNbPages();

            $pdf->SetFont('', 'B', 7);
            $pdf->SetTextColor($footerRgb[0], $footerRgb[1], $footerRgb[2]);
            $pdf->SetXY($lm, -17);
            $pdf->Cell($wFooter - $pageNumW, 3, $lineCompany, 0, 0, 'C');
            $pdf->SetFont('', '', 7);
            $pdf->Cell($pageNumW, 3, $pagination, 0, 1, 'R');

            $pdf->SetFont('', 'I', 6);
            $pdf->SetX($lm);
            $pdf->Cell($wFooter, 2, $lineUser, 0, 1, 'C');

            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->setPage($nb);
    }
}
