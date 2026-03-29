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
 *    \file       /custom/multirapport/multirapport.php
 *    \ingroup    multirapport
 *    \brief      Report page for MultiRapport module
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/expensereport/class/expensereport.class.php';
require_once __DIR__ . '/class/actions_multirapport.class.php';

// Load translation files required by the page
$langs->loadLangs(array("multirapport@multirapport", "bills", "compta", "trips"));

// Initialize Form object
$form = new Form($db);

// Get parameters
$action = GETPOST('action', 'aZ09');
$date_start = GETPOST('date_start', 'alpha');
$date_end = GETPOST('date_end', 'alpha');
$hour_start = GETPOST('hour_start', 'int');
$hour_end = GETPOST('hour_end', 'int');

// Set default dates if not provided
if (empty($date_start)) {
    $date_start = dol_print_date(dol_time_plus_duree(dol_now(), -1, 'd'), '%Y-%m-%d');
}
if (empty($date_end)) {
    $date_end = dol_print_date(dol_now(), '%Y-%m-%d');
}
if (empty($hour_start)) {
    $hour_start = 0;
}
if (empty($hour_end)) {
    $hour_end = 23;
}

// Convert dates to timestamps for SQL queries
$date_start_ts = dol_stringtotime($date_start . ' ' . sprintf('%02d', $hour_start) . ':00:00');
$date_end_ts = dol_stringtotime($date_end . ' ' . sprintf('%02d', $hour_end) . ':59:59');

// Access control
if (!isModEnabled('multirapport')) {
    accessforbidden('Module not enabled');
}
if (!$user->hasRight('multirapport', 'multirapport', 'read')) {
    accessforbidden();
}

// Initialize a technical object to manage hooks
$hookmanager->initHooks(array('multirapportcard'));

// Initialize metrics array
$metrics = array();
$credit_paid_invoices = array();

// Calculate metrics if we have valid dates
if (!empty($date_start) && !empty($date_end) && $date_start_ts && $date_end_ts) {
    $db->begin();
    
    // 1. Total Revenue (all invoices)
    $sql = "SELECT SUM(total_ttc) as total FROM " . $db->prefix() . "facture";
    $sql .= " WHERE datef >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND datef <= '" . $db->idate($date_end_ts) . "'";
    $sql .= " AND fk_statut IN (" . Facture::STATUS_VALIDATED . ", " . Facture::STATUS_CLOSED . ")";
    $sql .= " AND entity IN (" . getEntity('invoice') . ")";
    
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $metrics['total_revenue'] = $obj->total ? (float) $obj->total : 0;
        $db->free($resql);
    }
    
    // 2. Total Paid Invoices (status closed and paye = 1)
    $sql = "SELECT SUM(total_ttc) as total FROM " . $db->prefix() . "facture";
    $sql .= " WHERE datef >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND datef <= '" . $db->idate($date_end_ts) . "'";
    $sql .= " AND fk_statut = " . Facture::STATUS_CLOSED;
    $sql .= " AND paye = 1";
    $sql .= " AND entity IN (" . getEntity('invoice') . ")";
    
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $metrics['total_paid'] = $obj->total ? (float) $obj->total : 0;
        $db->free($resql);
    }
    
    // 3. Total Unpaid Invoices (status validated)
    $sql = "SELECT SUM(total_ttc) as total FROM " . $db->prefix() . "facture";
    $sql .= " WHERE datef >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND datef <= '" . $db->idate($date_end_ts) . "'";
    $sql .= " AND fk_statut = " . Facture::STATUS_VALIDATED;
    $sql .= " AND paye = 0";
    $sql .= " AND entity IN (" . getEntity('invoice') . ")";
    
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $metrics['total_unpaid'] = $obj->total ? (float) $obj->total : 0;
        $db->free($resql);
    }
    
    // 4. Total Expenses (approved + authorized + settled)
    $sql = "SELECT SUM(total_ttc) as total FROM " . $db->prefix() . "expensereport";
    $sql .= " WHERE date_fin >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND date_fin <= '" . $db->idate($date_end_ts) . "'";
    $sql .= " AND status IN (" . ExpenseReport::STATUS_APPROVED . ", " . ExpenseReport::STATUS_CLOSED . ")";
    $sql .= " AND entity IN (" . getEntity('expensereport') . ")";
    
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $metrics['total_expenses'] = $obj->total ? (float) $obj->total : 0;
        $db->free($resql);
    }
    
    // 5. Total 'Credit' Status Invoices (not paid yet - marked as credit but still unpaid)
    $sql = "SELECT SUM(f.total_ttc) as total";
    $sql .= " FROM " . $db->prefix() . "facture as f";
    $sql .= " WHERE f.datef >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND f.datef <= '" . $db->idate($date_end_ts) . "'";
    $sql .= " AND f.fk_statut = " . Facture::STATUS_VALIDATED;  // Credit invoices are VALIDATED, not closed
    $sql .= " AND f.paye = 0";  // Not paid
    $sql .= " AND f.close_code = 'credit_status'";
    $sql .= " AND f.entity IN (" . getEntity('invoice') . ")";
    
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $metrics['total_credit_status'] = $obj->total ? (float) $obj->total : 0;
        $db->free($resql);
    }
    
    // 6. Total Paid 'Credit' Invoices
    // Invoices that had "Credit" status (close_code = 'credit_status') and are now paid
    $sql = "SELECT SUM(f.total_ttc) as total";
    $sql .= " FROM " . $db->prefix() . "facture as f";
    $sql .= " WHERE f.datef >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND f.datef <= '" . $db->idate($date_end_ts) . "'";
    $sql .= " AND f.fk_statut = " . Facture::STATUS_CLOSED;
    $sql .= " AND f.paye = 1";
    $sql .= " AND f.close_code = 'credit_status'";
    $sql .= " AND f.entity IN (" . getEntity('invoice') . ")";
    
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $metrics['total_credit_paid'] = $obj->total ? (float) $obj->total : 0;
        $db->free($resql);
    }
    
    // 7. List of Paid Credit Invoices (for display under the total)
    $sql = "SELECT f.rowid, f.ref, f.total_ttc, f.datef, s.nom as socname";
    $sql .= " FROM " . $db->prefix() . "facture as f";
    $sql .= " LEFT JOIN " . $db->prefix() . "societe as s ON f.fk_soc = s.rowid";
    $sql .= " WHERE f.datef >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND f.datef <= '" . $db->idate($date_end_ts) . "'";
    $sql .= " AND f.fk_statut = " . Facture::STATUS_CLOSED;
    $sql .= " AND f.paye = 1";
    $sql .= " AND f.close_code = 'credit_status'";
    $sql .= " AND f.entity IN (" . getEntity('invoice') . ")";
    $sql .= " ORDER BY f.datef DESC";
    
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $credit_paid_invoices[] = array(
                'rowid' => $obj->rowid,
                'ref' => $obj->ref,
                'total_ttc' => (float) $obj->total_ttc,
                'datef' => $obj->datef,
                'socname' => $obj->socname
            );
        }
        $db->free($resql);
    }
    
    $db->commit();
}

// PDF Generation
if ($action == 'generatepdf') {
    require_once __DIR__ . '/core/modules/rapport/pdf_multirapport.class.php';
    
    $pdfgenerator = new pdf_multirapport($db);
    $dir = $conf->multirapport->dir_output . '/rapports';
    
    $result = $pdfgenerator->write_file(
        $dir,
        $metrics,
        $credit_paid_invoices,
        $date_start_ts,
        $date_end_ts,
        $hour_start,
        $hour_end,
        $langs
    );
    
    if ($result > 0) {
        setEventMessage($langs->trans('PDFGeneratedSuccessfully') . ': ' . $pdfgenerator->result['fullpath'], 'mesgs');
        // Redirect to download the file
        header('Location: ' . DOL_URL_ROOT . '/document.php?modulepart=multirapport&file=rapports/' . basename($pdfgenerator->result['fullpath']) . '&entity=' . $conf->entity);
        exit;
    } else {
        setEventMessage($pdfgenerator->error, 'errors');
    }
}

// Header
llxHeader();

// Print page title
print load_fiche_titre($langs->trans('MultiRapportReport'), '', 'multirapport.png@multirapport');

// Filter form
print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" name="search_form">';
print '<input type="hidden" name="token" value="' . newToken() . '">';

print '<div class="tabBar tabBarWithBottom">';
print '<table class="border centpercent">';

// Date range
print '<tr><td class="titlefield">' . $langs->trans('DateStart') . '</td>';
print '<td>';
print $form->selectDate($date_start_ts, 'date_start', 0, 0, 1, 'search_form', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('DateStart'));
print '</td></tr>';

print '<tr><td class="titlefield">' . $langs->trans('DateEnd') . '</td>';
print '<td>';
print $form->selectDate($date_end_ts, 'date_end', 0, 0, 1, 'search_form', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('DateEnd'));
print '</td></tr>';

// Hour range
print '<tr><td class="titlefield">' . $langs->trans('HourStart') . '</td>';
print '<td>';
print '<select name="hour_start" class="flat">';
for ($i = 0; $i < 24; $i++) {
    $selected = ($hour_start == $i) ? ' selected' : '';
    print '<option value="' . $i . '"' . $selected . '>' . sprintf('%02d', $i) . ':00</option>';
}
print '</select>';
print '</td></tr>';

print '<tr><td class="titlefield">' . $langs->trans('HourEnd') . '</td>';
print '<td>';
print '<select name="hour_end" class="flat">';
for ($i = 0; $i < 24; $i++) {
    $selected = ($hour_end == $i) ? ' selected' : '';
    print '<option value="' . $i . '"' . $selected . '>' . sprintf('%02d', $i) . ':00</option>';
}
print '</select>';
print '</td></tr>';

print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button" value="' . $langs->trans('Refresh') . '">';
print '<input type="submit" class="button" name="action" value="generatepdf">';
print '</div>';

print '</form>';

// Results section
print '<br>';
print '<div class="fichecenter">';

// Metrics cards
print '<div class="fichethirdleft">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">' . $langs->trans('FinancialMetrics') . '</td>';
print '</tr>';

// Total Revenue
print '<tr class="oddeven">';
print '<td>' . $langs->trans('TotalRevenue') . '</td>';
print '<td class="right amount" style="font-weight:bold;">' . price($metrics['total_revenue'] ?? 0) . '</td>';
print '</tr>';

// Total Paid
print '<tr class="oddeven">';
print '<td>' . $langs->trans('TotalPaidInvoices') . '</td>';
print '<td class="right amount">' . price($metrics['total_paid'] ?? 0) . '</td>';
print '</tr>';

// Total Unpaid
print '<tr class="oddeven">';
print '<td>' . $langs->trans('TotalUnpaidInvoices') . '</td>';
print '<td class="right amount">' . price($metrics['total_unpaid'] ?? 0) . '</td>';
print '</tr>';

// Total Expenses
print '<tr class="oddeven">';
print '<td>' . $langs->trans('TotalExpenses') . '</td>';
print '<td class="right amount">' . price($metrics['total_expenses'] ?? 0) . '</td>';
print '</tr>';

// Total Credit Status (not paid yet)
print '<tr class="oddeven" style="background-color:#ffe6cc;">';
print '<td><strong>' . $langs->trans('TotalCreditStatusInvoices') . '</strong></td>';
print '<td class="right amount" style="font-weight:bold; color:#cc6600;">' . price($metrics['total_credit_status'] ?? 0) . '</td>';
print '</tr>';

// Total Credit Paid
print '<tr class="oddeven" style="background-color:#f0e6ff;">';
print '<td><strong>' . $langs->trans('TotalCreditPaidInvoices') . '</strong></td>';
print '<td class="right amount" style="font-weight:bold; color:#6b4c9a;">' . price($metrics['total_credit_paid'] ?? 0) . '</td>';
print '</tr>';

print '</table>';
print '</div>';
print '</div>';

// Right column - Credit Paid Invoices List
print '<div class="fichetwothirdright">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('CreditPaidInvoicesList') . '</td>';
print '<td class="center">' . $langs->trans('ThirdParty') . '</td>';
print '<td class="center">' . $langs->trans('Date') . '</td>';
print '<td class="right">' . $langs->trans('AmountTTC') . '</td>';
print '</tr>';

if (!empty($credit_paid_invoices)) {
    foreach ($credit_paid_invoices as $invoice) {
        print '<tr class="oddeven">';
        print '<td>';
        $facture_static = new Facture($db);
        $facture_static->id = $invoice['rowid'];
        $facture_static->ref = $invoice['ref'];
        print $facture_static->getNomUrl(1);
        print '</td>';
        print '<td class="center">' . dol_escape_htmltag($invoice['socname']) . '</td>';
        print '<td class="center">' . dol_print_date($invoice['datef'], 'day') . '</td>';
        print '<td class="right amount">' . price($invoice['total_ttc']) . '</td>';
        print '</tr>';
    }
} else {
    print '<tr class="oddeven"><td colspan="4" class="opacitymedium center">' . $langs->trans('None') . '</td></tr>';
}

// Total row
if (!empty($credit_paid_invoices)) {
    print '<tr class="liste_total">';
    print '<td colspan="3" class="right">' . $langs->trans('Total') . '</td>';
    print '<td class="right amount">' . price($metrics['total_credit_paid'] ?? 0) . '</td>';
    print '</tr>';
}

print '</table>';
print '</div>';
print '</div>';

print '</div>';

// End of page
llxFooter();
$db->close();
