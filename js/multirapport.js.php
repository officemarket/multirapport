<?php
/* Copyright (C) 2024-2025 Your Name <your.email@example.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__.'/../../../main.inc.php';

header('Content-Type: application/javascript; charset=UTF-8');

// Only proceed if module is enabled
if (!isModEnabled('multirapport')) {
    exit;
}

if (!getDolGlobalInt('MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED')) {
    exit;
}

$langs->load('multirapport@multirapport');
?>
/**
 * MultiRapport module JavaScript
 * Adds Credit status option to invoice list filters
 */

(function() {
    'use strict';

    function addCreditOption() {
        if (typeof jQuery === 'undefined') {
            setTimeout(addCreditOption, 100);
            return;
        }

        jQuery(function($) {
            var statusSelect = $('select[name="search_status"]');
            if (statusSelect.length === 0) {
                return;
            }

            // Check if Credit option already exists
            if (statusSelect.find('option[value="credit"]').length > 0) {
                return;
            }

            // Add Credit option after "Unpaid" (value 1)
            var creditOption = $('<option>', {
                value: 'credit',
                text: '<?php echo $langs->trans('BillShortStatusCredit'); ?>'
            });

            var unpaidOption = statusSelect.find('option[value="1"]');
            if (unpaidOption.length > 0) {
                unpaidOption.after(creditOption);
            } else {
                statusSelect.append(creditOption);
            }

            // Select Credit if URL has search_status=credit
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('search_status') === 'credit') {
                statusSelect.val('credit');
            }
        });
    }

    // Try immediately and also on DOM ready
    addCreditOption();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addCreditOption);
    }
})();
