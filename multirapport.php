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
$hour_start = (GETPOST('hour_start', 'alpha') !== '' ? GETPOST('hour_start', 'int') : 0);
$hour_end = (GETPOST('hour_end', 'alpha') !== '' ? GETPOST('hour_end', 'int') : 23);

// Handle date_start
$date_start = GETPOST('date_start', 'alpha');
$date_start_year = GETPOST('date_startyear', 'int');
$date_start_month = GETPOST('date_startmonth', 'int');
$date_start_day = GETPOST('date_startday', 'int');

if ($date_start_year && $date_start_month && $date_start_day) {
    $date_start_ts = dol_mktime($hour_start, 0, 0, $date_start_month, $date_start_day, $date_start_year);
} elseif (!empty($date_start)) {
    $date_start_ts = dol_stringtotime($date_start . ' ' . sprintf('%02d', $hour_start) . ':00:00');
} else {
    // Default: yesterday 00h
    $date_start_ts = dol_mktime(0, 0, 0, date('m', dol_now()), date('d', dol_now()) - 1, date('Y', dol_now()));
}

// Handle date_end
$date_end = GETPOST('date_end', 'alpha');
$date_end_year = GETPOST('date_endyear', 'int');
$date_end_month = GETPOST('date_endmonth', 'int');
$date_end_day = GETPOST('date_endday', 'int');

if ($date_end_year && $date_end_month && $date_end_day) {
    $date_end_ts = dol_mktime($hour_end, 59, 59, $date_end_month, $date_end_day, $date_end_year);
} elseif (!empty($date_end)) {
    $date_end_ts = dol_stringtotime($date_end . ' ' . sprintf('%02d', $hour_end) . ':59:59');
} else {
    // Default: today 23h59
    $date_end_ts = dol_mktime(23, 59, 59, date('m', dol_now()), date('d', dol_now()), date('Y', dol_now()));
}

// Access control
if (!isModEnabled('multirapport')) {
    accessforbidden('Module not enabled');
}
if (!$user->hasRight('multirapport', 'read')) {
    accessforbidden();
}
restrictedArea($user, 'multirapport');

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
    $sql .= " WHERE datec >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND datec <= '" . $db->idate($date_end_ts) . "'";
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
    $sql .= " WHERE datec >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND datec <= '" . $db->idate($date_end_ts) . "'";
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
    $sql .= " WHERE datec >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND datec <= '" . $db->idate($date_end_ts) . "'";
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
    $sql .= " WHERE date_create >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND date_create <= '" . $db->idate($date_end_ts) . "'";
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
    $sql .= " WHERE f.datec >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND f.datec <= '" . $db->idate($date_end_ts) . "'";
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
    $sql .= " WHERE f.datec >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND f.datec <= '" . $db->idate($date_end_ts) . "'";
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
    $sql .= " WHERE f.datec >= '" . $db->idate($date_start_ts) . "'";
    $sql .= " AND f.datec <= '" . $db->idate($date_end_ts) . "'";
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

    // 8. Total Margin (if module marge enabled)
    if (isModEnabled('margin')) {
        $sql = "SELECT SUM(f.total_ht - f.buy_price_ht) as margin";
        $sql .= " FROM " . $db->prefix() . "facturedet as f";
        $sql .= " LEFT JOIN " . $db->prefix() . "facture as if ON f.fk_facture = if.rowid";
        $sql .= " WHERE if.datec >= '" . $db->idate($date_start_ts) . "'";
        $sql .= " AND if.datec <= '" . $db->idate($date_end_ts) . "'";
        $sql .= " AND if.fk_statut IN (1, 2)";
        $sql .= " AND if.entity IN (" . getEntity('invoice') . ")";
        
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $metrics['total_margin'] = $obj->margin ? (float) $obj->margin : 0;
            $db->free($resql);
        }
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
llxHeader('', $langs->trans('MultiRapportReport'), '', '', 0, 0, array('/includes/nnnick/chartjs/dist/chart.min.js'), array(), '', '');

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

if (!empty($date_start_ts) && !empty($date_end_ts) && $user->hasRight('multirapport', 'read')) {
    $export_url = 'rapport_export.php?date_start=' . $date_start_ts . '&date_end=' . $date_end_ts;
    print ' <a href="' . $export_url . '" class="button">' . $langs->trans('Export') . ' (CSV)</a>';
}

print '</div>';

print '</form>';

// Results section
print '<br>';

if (!empty($metrics)) {
    // Modern Cards for key metrics
    print '<div class="fichecenter">';
    print '<div class="div-table-responsive-no-min">';
    print '<div class="box-flex-container">';
    
    // Revenue Card
    print '<div class="box-flex-item" style="flex: 1 1 200px; padding: 10px;">';
    print '<div class="box" style="background: #fff; border-top: 3px solid #6b4c9a; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
    print '<div class="opacitymedium">' . $langs->trans('TotalRevenue') . '</div>';
    print '<div style="font-size: 1.8em; font-weight: bold; color: #6b4c9a;">' . price($metrics['total_revenue'] ?? 0) . '</div>';
    print '</div>';
    print '</div>';
    
    // Paid Card
    print '<div class="box-flex-item" style="flex: 1 1 200px; padding: 10px;">';
    print '<div class="box" style="background: #fff; border-top: 3px solid #28a745; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
    print '<div class="opacitymedium">' . $langs->trans('TotalPaidInvoices') . '</div>';
    print '<div style="font-size: 1.8em; font-weight: bold; color: #28a745;">' . price($metrics['total_paid'] ?? 0) . '</div>';
    print '</div>';
    print '</div>';
    
    // Unpaid Card
    print '<div class="box-flex-item" style="flex: 1 1 200px; padding: 10px;">';
    print '<div class="box" style="background: #fff; border-top: 3px solid #dc3545; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
    print '<div class="opacitymedium">' . $langs->trans('TotalUnpaidInvoices') . '</div>';
    print '<div style="font-size: 1.8em; font-weight: bold; color: #dc3545;">' . price($metrics['total_unpaid'] ?? 0) . '</div>';
    print '</div>';
    print '</div>';
    
    // Expenses Card
    print '<div class="box-flex-item" style="flex: 1 1 200px; padding: 10px;">';
    print '<div class="box" style="background: #fff; border-top: 3px solid #ffc107; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
    print '<div class="opacitymedium">' . $langs->trans('TotalExpenses') . '</div>';
    print '<div style="font-size: 1.8em; font-weight: bold; color: #ffc107;">' . price($metrics['total_expenses'] ?? 0) . '</div>';
    print '</div>';
    print '</div>';
    
    print '</div>';
    print '</div>';
    print '</div>';

    // Charts Container
    print '<div class="fichecenter" style="display: flex; flex-wrap: wrap;">';
    
    // Left side: Chart
    print '<div style="flex: 1 1 50%; padding: 10px;">';
    print '<canvas id="revenueChart" style="max-height: 350px; background: #fff; border-radius: 4px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"></canvas>';
    print '</div>';
    
    // Right side: Breakdown Table
    print '<div style="flex: 1 1 40%; padding: 10px;">';
    print '<div style="background: #fff; border-radius: 4px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td colspan="2">' . $langs->trans('FinancialMetrics') . '</td></tr>';
    print '<tr class="oddeven"><td>' . $langs->trans('TotalRevenue') . '</td><td class="right">' . price($metrics['total_revenue']) . '</td></tr>';
    print '<tr class="oddeven"><td>' . $langs->trans('TotalExpenses') . '</td><td class="right">' . price($metrics['total_expenses']) . '</td></tr>';
    if (isset($metrics['total_margin'])) {
        print '<tr class="oddeven"><td>' . $langs->trans('TotalMargin') . '</td><td class="right" style="color:#28a745;">' . price($metrics['total_margin']) . '</td></tr>';
    }
    print '<tr class="liste_total"><td><strong>Net profit</strong></td><td class="right"><strong>' . price($metrics['total_revenue'] - $metrics['total_expenses']) . '</strong></td></tr>';
    print '</table>';
    
    print '<br>';
    
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td colspan="2">' . $langs->trans('CreditStatusDetails') . '</td></tr>';
    print '<tr class="oddeven"><td>' . $langs->trans('TotalCreditStatusInvoices') . '</td><td class="right" style="color:#cc6600;">' . price($metrics['total_credit_status'] ?? 0) . '</td></tr>';
    print '<tr class="oddeven"><td>' . $langs->trans('TotalCreditPaidInvoices') . '</td><td class="right" style="color:#6b4c9a;">' . price($metrics['total_credit_paid'] ?? 0) . '</td></tr>';
    print '</table>';
    print '</div>';
    print '</div>';
    
    print '</div>';

    // Chart JS script
    print '<script type="text/javascript">
        $(document).ready(function() {
            var ctx = document.getElementById("revenueChart").getContext("2d");
            new Chart(ctx, {
                type: "bar",
                data: {
                    labels: ["' . $langs->trans('TotalRevenue') . '", "' . $langs->trans('TotalExpenses') . '", "Net"],
                    datasets: [{
                        label: "' . $langs->trans('Amount') . '",
                        data: [' . ($metrics['total_revenue'] ?? 0) . ', ' . ($metrics['total_expenses'] ?? 0) . ', ' . (($metrics['total_revenue'] ?? 0) - ($metrics['total_expenses'] ?? 0)) . '],
                        backgroundColor: ["#6b4c9a", "#ffc107", "#28a745"],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true }
                    },
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: "' . $langs->trans('RevenueVsExpenses') . '" }
                    }
                }
            });
        });
    </script>';
}

// Right column - Credit Paid Invoices List
print '<br>';
print '<div class="fichecenter">';
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

// End of page
llxFooter();
$db->close();
