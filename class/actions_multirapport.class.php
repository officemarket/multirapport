<?php
/* Copyright (C) 2024-2025	Your Name <your.email@example.com>
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
 * \file    htdocs/custom/multirapport/class/actions_multirapport.class.php
 * \ingroup multirapport
 * \brief   Hook actions for MultiRapport module
 */

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

/**
 * Class ActionsMultiRapport
 */
class ActionsMultiRapport
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array<string> Error messages
	 */
	public $errors = array();

	/**
	 * @var array<string> Hook results
	 */
	public $results = array();

	/**
	 * @var string Printed results
	 */
	public $resprints = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Add mass actions to invoice list
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Object
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0=continue, 1=replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$langs->load('multirapport@multirapport');

		// Debug logging
		dol_syslog('MultiRapport::doActions - action='.$action.', context='.$parameters['currentcontext'], LOG_DEBUG);

		// Get massaction directly from POST (Dolibarr clears $massaction if confirmmassaction is not set)
		$massaction = GETPOST('massaction', 'alpha');
		$confirm = GETPOST('confirm', 'alpha');
		
		dol_syslog('MultiRapport::doActions - massaction='.$massaction.', confirm='.$confirm, LOG_DEBUG);

		// Check if we're on invoice list
		if (in_array($parameters['currentcontext'], array('invoicelist', 'facturelist'))) {
			// Get action directly from POST (may be different from $action parameter)
			$direct_action = GETPOST('action', 'alpha');
			
			dol_syslog('MultiRapport::doActions - direct_action='.$direct_action.', POST data: '.print_r($_POST, true), LOG_DEBUG);

			// Show confirmation form for mass action "markascredit"
			if ($massaction == 'markascredit' && $confirm != 'yes' && $direct_action != 'confirm_markascredit') {
				dol_syslog('MultiRapport::doActions - Showing confirmation form', LOG_DEBUG);
				
				// Build hidden inputs for selected invoices and token
				$toselect = GETPOST('toselect', 'array:int');
				
				// Use simple confirmation without formconfirm() to avoid JS issues
				print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="confirm_markascredit">';
				print '<input type="hidden" name="massaction" value="markascredit">';
				print '<input type="hidden" name="confirm" value="yes">';
				foreach ($toselect as $id) {
					print '<input type="hidden" name="toselect[]" value="'.$id.'">';
				}
				
				print '<div class="confirmmessage" style="padding:20px; background:#f8f8f8; border:1px solid #ddd; margin:10px 0;">';
				print '<strong>'.$langs->trans('MarkAsCredit').'</strong><br><br>';
				print $langs->trans('ConfirmMarkAsCreditMass').'<br><br>';
				print '<b>'.$langs->trans('InvoicesSelected').': '.count($toselect).'</b><br><br>';
				print '<input type="submit" class="button" value="'.$langs->trans('Yes').'">';
				print '<a href="'.$_SERVER['PHP_SELF'].'" class="button" style="margin-left:10px;">'.$langs->trans('No').'</a>';
				print '</div>';
				print '</form>';
			}

			// Handle mass action "markascredit" after confirmation
			// Check both $action parameter and direct GETPOST('action')
			if (($action == 'confirm_markascredit' || $direct_action == 'confirm_markascredit') && $confirm === 'yes') {
				dol_syslog('MultiRapport::doActions - Processing confirm_markascredit', LOG_DEBUG);
				$this->processMassActionMarkAsCredit($parameters);
				// Prevent further processing
				$action = '';
				return 1;
			}
		}

		// Handle individual action on invoice card
		if (in_array($parameters['currentcontext'], array('invoicecard', 'facturecard'))) {
			if ($action == 'classifyascredit') {
				if ($confirm === 'yes') {
					// User confirmed: execute the action
					$this->processClassifyAsCredit($object);
					return 1;
				} else {
					// Show a simple PHP confirmation form (avoids the JS register error)
					print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
					print '<input type="hidden" name="token" value="'.newToken().'">';
					print '<input type="hidden" name="action" value="classifyascredit">';
					print '<input type="hidden" name="id" value="'.$object->id.'">';
					print '<input type="hidden" name="confirm" value="yes">';
					print '<div class="confirmmessage" style="padding:20px; background:#fff3cd; border:1px solid #ffc107; border-radius:4px; margin:10px 0;">';
					print '<strong>'.$langs->trans('ClassifyAsCredit').'</strong><br><br>';
					print $langs->trans('ConfirmClassifyAsCredit').'<br><br>';
					print '<input type="submit" class="button" value="'.$langs->trans('Yes').'">';
					print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" class="button button-secondary">'.$langs->trans('No').'</a>';
					print '</div>';
					print '</form>';
					return 1; // Prevent further processing of this action
				}
			}
		}

		return 0;
	}

	/**
	 * Add mass action button to invoice list
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Object
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0=continue
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		if (!isModEnabled('multirapport')) {
			return 0;
		}

		if (!getDolGlobalInt('MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED')) {
			return 0;
		}

		// Check permission
		if (empty($user->rights->invoice->classifycredit) && empty($user->admin)) {
			return 0;
		}

		$langs->load('multirapport@multirapport');

		// Add "Mark as Credit" mass action for invoice list
		if (in_array($parameters['currentcontext'], array('invoicelist', 'facturelist'))) {
			$this->resprints = '<option value="markascredit" data-html="'.dol_escape_htmltag(img_picto('', 'bill', 'class="pictofixedwidth"').$langs->trans('MarkAsCredit')).'">'.dol_escape_htmltag($langs->trans('MarkAsCredit')).'</option>';
		}

		return 0;
	}

	/**
	 * Add action buttons on invoice card
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Object
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0=continue
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		if (!isModEnabled('multirapport')) {
			return 0;
		}

		if (!getDolGlobalInt('MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED')) {
			return 0;
		}

		// Check permission
		if (empty($user->rights->invoice->classifycredit) && empty($user->admin)) {
			return 0;
		}

		$langs->load('multirapport@multirapport');

		// Add "Classify as Credit" button for unpaid invoices
		if (in_array($parameters['currentcontext'], array('invoicecard', 'facturecard'))) {
			if ($object->element == 'facture' && $object->status == Facture::STATUS_VALIDATED && $object->paye == 0) {
				// Check if not already marked as credit (using custom field)
				$iscredit = $this->isInvoiceMarkedAsCredit($object->id);
				if (!$iscredit) {
					// Use a plain <a> link instead of dolGetButtonAction to avoid
					// the JS "Cannot read properties of undefined (reading 'register')" error
					// that occurs when the popup confirmation manager is not yet initialised.
					$url = $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=classifyascredit&token='.newToken();
					print '<a class="butAction" href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($langs->trans('ClassifyAsCredit')).'</a>';
				}
			}
		}

		return 0;
	}

	/**
	 * Handle status label display (LibStatut hook)
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Object
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0=continue, 1=replace standard code
	 */
	public function LibStatut($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs;

		if (!isModEnabled('multirapport')) {
			return 0;
		}

		if (!getDolGlobalInt('MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED')) {
			return 0;
		}

		$langs->load('multirapport@multirapport');

		// Check if this is an invoice with credit status
		if (is_object($object) && $object->element == 'facture') {
			$iscredit = $this->isInvoiceMarkedAsCredit($object->id);
			if ($iscredit) {
				$mode = $parameters['mode'] ?? 0;
				$labelStatus = $langs->transnoentitiesnoconv('BillStatusCredit');
				$labelStatusShort = $langs->transnoentitiesnoconv('ShortBillStatusCredit');
				$statusType = 'status8'; // Custom status type for credit (will use CSS)

				$this->resprints = dolGetStatus($labelStatus, $labelStatusShort, '', $statusType, $mode);
				return 1; // Replace standard code
			}
		}

		return 0;
	}

	/**
	 * Process mass action "Mark as Credit"
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @return void
	 */
	private function processMassActionMarkAsCredit($parameters)
	{
		global $user, $langs;

		$toselect = GETPOST('toselect', 'array:int');
		$nbok = 0;
		$nbko = 0;

		if (!empty($toselect)) {
			$this->db->begin();

			foreach ($toselect as $toselectid) {
				$facture = new Facture($this->db);
				$result = $facture->fetch($toselectid);

				if ($result > 0) {
					// Only unpaid invoices can be marked as credit
					if ($facture->status == Facture::STATUS_VALIDATED && $facture->paye == 0) {
						$result = $this->markInvoiceAsCredit($facture->id, $user);
						if ($result > 0) {
							$nbok++;
						} else {
							$nbko++;
							setEventMessages($facture->ref.' '.$langs->trans('ErrorMarkingAsCredit'), null, 'errors');
						}
					} else {
						$nbko++;
						setEventMessages($facture->ref.' '.$langs->trans('InvoiceMustBeUnpaid'), null, 'errors');
					}
				} else {
					$nbko++;
					setEventMessages($langs->trans('ErrorInvoiceNotFound', $toselectid), null, 'errors');
				}
			}

			if ($nbko == 0) {
				$this->db->commit();
				setEventMessages($langs->trans('InvoicesMarkedAsCredit', $nbok), null, 'mesgs');
			} else {
				$this->db->rollback();
				setEventMessages($langs->trans('SomeInvoicesNotMarkedAsCredit'), null, 'errors');
			}
		}
	}

	/**
	 * Process individual action "Classify as Credit"
	 *
	 * @param Facture $object Invoice object
	 * @return void
	 */
	private function processClassifyAsCredit(&$object)
	{
		global $user, $langs;

		if ($object->status == Facture::STATUS_VALIDATED && $object->paye == 0) {
			$result = $this->markInvoiceAsCredit($object->id, $user);
			if ($result > 0) {
				setEventMessages($langs->trans('InvoiceMarkedAsCredit'), null, 'mesgs');
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
				exit;
			} else {
				setEventMessages($langs->trans('ErrorMarkingAsCredit'), null, 'errors');
			}
		} else {
			setEventMessages($langs->trans('InvoiceMustBeUnpaid'), null, 'errors');
		}
	}

	/**
	 * Mark invoice as credit
	 *
	 * @param int $invoice_id Invoice ID
	 * @param User $user User object
	 * @return int <0 if KO, >0 if OK
	 */
	private function markInvoiceAsCredit($invoice_id, $user)
	{
		global $conf;

		// Use extrafield to store credit status
		$sql = "UPDATE ".$this->db->prefix()."facture";
		$sql .= " SET close_code = 'credit_status', close_note = 'Invoice marked as Credit'";
		$sql .= " WHERE rowid = ".((int) $invoice_id);
		$sql .= " AND fk_statut = ".Facture::STATUS_VALIDATED;
		$sql .= " AND paye = 0";

		dol_syslog(get_class($this)."::markInvoiceAsCredit", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		// Also update extrafield if table exists
		$sql2 = "UPDATE ".$this->db->prefix()."facture_extrafields";
		$sql2 .= " SET multirapport_iscredit = 1";
		$sql2 .= " WHERE fk_object = ".((int) $invoice_id);
		$this->db->query($sql2); // Ignore errors if extrafield doesn't exist

		return 1;
	}

	/**
	 * Check if invoice is marked as credit
	 *
	 * @param int $invoice_id Invoice ID
	 * @return bool True if marked as credit
	 */
	private function isInvoiceMarkedAsCredit($invoice_id)
	{
		$sql = "SELECT close_code FROM ".$this->db->prefix()."facture";
		$sql .= " WHERE rowid = ".((int) $invoice_id);

		dol_syslog(get_class($this)."::isInvoiceMarkedAsCredit", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			if ($obj->close_code == 'credit_status') {
				return true;
			}
		}

		return false;
	}

	/**
	 * Execute hook printFieldListWhere
	 * Modify SQL WHERE clause to handle Credit status filter
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject $object Object
	 * @param string $action Action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0=continue, 1=replace
	 */
	public function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		if (!isModEnabled('multirapport')) {
			return 0;
		}

		if (!getDolGlobalInt('MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED')) {
			return 0;
		}

		// Only for invoice list
		if (in_array($parameters['currentcontext'], array('invoicelist', 'facturelist'))) {
			$search_status = GETPOST('search_status', 'alpha');

			// If searching for Credit status, modify the SQL WHERE
			if ($search_status === 'credit') {
				// Replace the standard status filter with Credit-specific filter
				// We search for invoices with close_code = 'credit_status'
				$this->resprints = " AND f.fk_statut = 1 AND f.close_code = 'credit_status' ";
				return 1; // Replace standard filter
			}
		}

		return 0;
	}
}
