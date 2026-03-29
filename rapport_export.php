<?php
/* Copyright (C) 2026 Office Market Supply */

/**
 * \file    /custom/multirapport/rapport_export.php
 * \ingroup multirapport
 * \brief   Export script for MultiRapport
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

// Access control
if (!isModEnabled('multirapport')) accessforbidden('Module not enabled');
if (!$user->hasRight('multirapport', 'read')) accessforbidden();
restrictedArea($user, 'multirapport');

$date_start_ts = GETPOST('date_start', 'int');
$date_end_ts = GETPOST('date_end', 'int');
$format = GETPOST('format', 'alpha') ?: 'csv';

if (!$date_start_ts || !$date_end_ts) {
    setEventMessages("Dates manquantes", null, 'errors');
    header("Location: multirapport.php");
    exit;
}

$filename = 'export_multirapport_' . date('Ymd', $date_start_ts) . '_' . date('Ymd', $date_end_ts) . '.' . $format;

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Header
fputcsv($output, array(
    $langs->trans('Ref'),
    $langs->trans('ThirdParty'),
    $langs->trans('Date'),
    $langs->trans('AmountHT'),
    $langs->trans('AmountTTC'),
    $langs->trans('Status'),
    $langs->trans('Type')
));

// 1. Paid Invoices
$sql = "SELECT f.ref, s.nom as socname, f.datef, f.total_ht, f.total_ttc, f.fk_statut, f.paye, f.close_code";
$sql .= " FROM " . $db->prefix() . "facture as f";
$sql .= " LEFT JOIN " . $db->prefix() . "societe as s ON f.fk_soc = s.rowid";
$sql .= " WHERE f.datec >= '" . $db->idate($date_start_ts) . "'";
$sql .= " AND f.datec <= '" . $db->idate($date_end_ts) . "'";
$sql .= " AND f.entity IN (" . getEntity('invoice') . ")";

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $status = $langs->trans("Draft");
        if ($obj->fk_statut == 1) $status = $langs->trans("Validated");
        if ($obj->fk_statut == 2) $status = $langs->trans("Closed");
        if ($obj->close_code == 'credit_status') $status = 'Credit';

        fputcsv($output, array(
            $obj->ref,
            $obj->socname,
            dol_print_date($obj->datef, 'day'),
            $obj->total_ht,
            $obj->total_ttc,
            $status,
            'Facture'
        ));
    }
}

fclose($output);
exit;
