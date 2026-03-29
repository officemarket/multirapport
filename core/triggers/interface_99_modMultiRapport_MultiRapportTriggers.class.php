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
 * \file    htdocs/custom/multirapport/core/triggers/interface_99_modMultiRapport_MultiRapportTriggers.class.php
 * \ingroup multirapport
 * \brief   MultiRapport trigger to remove credit status when invoice is paid
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class of triggers for MultiRapport module
 */
class InterfaceMultiRapportTriggers extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "multirapport";
		$this->description = "MultiRapport module triggers: handle credit status removal on payment";
		$this->version = self::VERSIONS['prod']; // Possible values: VERSIONS['prod'], VERSIONS['exp'], VERSIONS['dev']
		$this->picto = 'bill';
	}

	/**
	 * Trigger run function
	 *
	 * @param string $action Code of the event that triggered the trigger
	 * @param CommonObject $object Object that triggered the trigger
	 * @param User $user User who triggered the trigger
	 * @param Translate $langs Language object
	 * @param Conf $conf Dolibarr configuration object
	 * @return int                     Return integer <0 if KO, 0 if nothing done, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('multirapport')) {
			return 0;
		}

		if (!getDolGlobalInt('MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED')) {
			return 0;
		}

		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

		dol_syslog("Trigger '".$this->name."' for action '".$action."' launched by ".__FILE__, LOG_DEBUG);

		// When an invoice is paid, remove the credit status
		if ($action == 'BILL_PAYED') {
			if ($object->element == 'facture') {
				// Check if invoice was marked as credit
				$sql = "SELECT close_code FROM ".$this->db->prefix()."facture";
				$sql .= " WHERE rowid = ".((int) $object->id);

				$resql = $this->db->query($sql);
				if ($resql && $this->db->num_rows($resql) > 0) {
					$obj = $this->db->fetch_object($resql);
					if ($obj->close_code == 'credit_status') {
						// Remove credit status since invoice is now paid
						$sql2 = "UPDATE ".$this->db->prefix()."facture";
						$sql2 .= " SET close_code = NULL, close_note = NULL";
						$sql2 .= " WHERE rowid = ".((int) $object->id);
						$this->db->query($sql2);

						dol_syslog("Credit status removed from invoice " . $object->id . " because it was paid", LOG_DEBUG);
					}
				}
			}
		}

		// When payment is deleted and invoice becomes unpaid again, check if we need to restore credit status
		if ($action == 'BILL_DELETE') {
			// Nothing special to do here - the invoice will be deleted
		}

		return 0;
	}
}
