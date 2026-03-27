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
 * MultiRapport module CSS
 * Contains styles for the Credit status on invoices
 */

/* Credit Status Badge - visually distinct purple/magenta style */
.badge.status8,
span.badge.status8,
.badge.bg-success-dk {
    background-color: #9c27b0 !important; /* Purple color for Credit status */
    color: #fff !important;
    border-color: #7b1fa2 !important;
}

/* Hover effect for Credit status */
.badge.status8:hover,
span.badge.status8:hover {
    background-color: #7b1fa2 !important;
}

/* Credit status in list view */
.tdoverflowmax125 span.badge.status8,
.tdoverflowmax150 span.badge.status8 {
    display: inline-block;
    padding: 3px 8px;
    font-size: 0.85em;
    border-radius: 3px;
}

/* Make Credit status visually distinct in the status column */
.facturecredit .badge.status8 {
    background: linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%);
    box-shadow: 0 1px 3px rgba(156, 39, 176, 0.3);
}

/* Credit status tooltip styling */
.badge.status8[title]:hover::after {
    content: attr(title);
    position: absolute;
    background: #333;
    color: #fff;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
}

/* Kanban view Credit status */
.info-box-status .badge.status8 {
    font-size: 0.9em;
    padding: 4px 10px;
}

/* Credit status filter in list view */
.selectstatus8 {
    background-color: rgba(156, 39, 176, 0.1);
    border-color: #9c27b0;
}
