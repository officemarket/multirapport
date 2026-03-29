<?php
/* Copyright (C) 2026 Office Market Supply */

/**
 * \file    /custom/multirapport/core/boxes/multirapport_widget.php
 * \ingroup multirapport
 * \brief   Module to show a box on Home page
 */

include_once DOL_DOCUMENT_ROOT . '/core/boxes/modules_boxes.php';

/**
 * Class to manage the box for MultiRapport
 */
class multirapport_widget extends ModeleBoxes
{
    public $boxlabel = "MultiRapportMetrics";
    public $boximg = "fa-file-invoice-dollar";
    public $family = "financial";
    public $enabled = 1;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     * @param string $param Parameters
     */
    public function __construct($db, $param = '')
    {
        global $langs;
        $langs->load("multirapport@multirapport");

        $this->db = $db;
        $this->boxlabel = $langs->trans("MultiRapportMetrics");
    }

    /**
     * Load data into info_box_contents array to show array later.
     *
     * @param int $max Maximum number of records to load
     * @return void
     */
    public function loadBox($max = 5)
    {
        global $conf, $user, $langs, $db;

        $this->info_box_head = array('text' => $langs->trans("MultiRapportMetrics") . ' (' . $langs->trans("CurrentMonth") . ')');

        if ($user->hasRight('multirapport', 'read')) {
            require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

            $now = dol_now();
            $date_start = dol_get_first_day(date('Y', $now), date('m', $now));
            $date_end = dol_get_last_day(date('Y', $now), date('m', $now));

            // Metrics calculation (Simplified for widget)
            $metrics = array();

            // Total Revenue
            $sql = "SELECT SUM(total_ttc) as total FROM " . $db->prefix() . "facture";
            $sql .= " WHERE datec >= '" . $db->idate($date_start) . "' AND datec <= '" . $db->idate($date_end) . "'";
            $sql .= " AND fk_statut IN (1, 2) AND entity IN (" . getEntity('invoice') . ")";
            $resql = $db->query($sql);
            if ($resql) {
                $obj = $db->fetch_object($resql);
                $metrics['revenue'] = $obj->total ?: 0;
            }

            // Credit Status
            $sql = "SELECT SUM(total_ttc) as total FROM " . $db->prefix() . "facture";
            $sql .= " WHERE datec >= '" . $db->idate($date_start) . "' AND datec <= '" . $db->idate($date_end) . "'";
            $sql .= " AND close_code = 'credit_status' AND entity IN (" . getEntity('invoice') . ")";
            $resql = $db->query($sql);
            if ($resql) {
                $obj = $db->fetch_object($resql);
                $metrics['credit'] = $obj->total ?: 0;
            }

            $this->info_box_contents[0][0] = array(
                'td' => 'class="left"',
                'text' => $langs->trans("TotalRevenue")
            );
            $this->info_box_contents[0][1] = array(
                'td' => 'class="right"',
                'text' => price($metrics['revenue'])
            );

            $this->info_box_contents[1][0] = array(
                'td' => 'class="left"',
                'text' => $langs->trans("TotalCreditStatusInvoices")
            );
            $this->info_box_contents[1][1] = array(
                'td' => 'class="right"',
                'text' => '<span style="color:#cc6600; font-weight:bold;">' . price($metrics['credit']) . '</span>'
            );
            
            $this->info_box_contents[2][0] = array(
                'td' => 'class="center" colspan="2"',
                'text' => '<a href="' . dol_buildpath('/custom/multirapport/multirapport.php', 1) . '">' . $langs->trans("FullReport") . '</a>'
            );
        } else {
            $this->info_box_contents[0][0] = array(
                'td' => 'class="left"',
                'text' => $langs->trans("ReadPermissionNotAllowed")
            );
        }
    }

    /**
     * Method to show box
     *
     * @param array $head Header array
     * @param array $contents Contents array
     * @param int $nooutput No output
     * @return string
     */
    public function showBox($head = null, $contents = null, $nooutput = 0)
    {
        return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }
}
