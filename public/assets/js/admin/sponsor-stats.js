/**
 * localisation: public/assets/js/admin/sponsor-stats.js
 * Role: Tri client générique des tableaux "sortable-table" (section Statistiques sponsors).
 *       <th data-sort> = déclencheur ; <td data-sort-value> = valeur brute de comparaison
 *       (fallback sur le texte de la cellule). Clic = bascule asc/desc.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var ICON_IDLE = 'bi bi-caret-up sort-icon text-muted';

    document.querySelectorAll('table.sortable-table').forEach(function (table) {
        table.querySelectorAll('th[data-sort]').forEach(function (th) {
            th.addEventListener('click', function () {
                var tbody = table.tBodies[0];
                if (!tbody) return;

                var colIndex = Array.prototype.indexOf.call(th.parentNode.children, th);
                var dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';

                // Réinitialise l'état des autres colonnes
                table.querySelectorAll('th[data-sort]').forEach(function (other) {
                    delete other.dataset.dir;
                    var ic = other.querySelector('.sort-icon');
                    if (ic) ic.className = ICON_IDLE;
                });
                th.dataset.dir = dir;
                var icon = th.querySelector('.sort-icon');
                if (icon) {
                    icon.className = 'bi sort-icon ' + (dir === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill');
                }

                var factor = dir === 'asc' ? 1 : -1;
                var rows = Array.prototype.slice.call(tbody.rows);
                var dataRows = rows.filter(function (r) { return !r.querySelector('td[colspan]'); });
                var fillerRows = rows.filter(function (r) { return r.querySelector('td[colspan]'); });

                dataRows.sort(function (a, b) {
                    var av = (a.children[colIndex] && a.children[colIndex].dataset.sortValue !== undefined)
                        ? a.children[colIndex].dataset.sortValue
                        : (a.children[colIndex] ? a.children[colIndex].textContent.trim() : '');
                    var bv = (b.children[colIndex] && b.children[colIndex].dataset.sortValue !== undefined)
                        ? b.children[colIndex].dataset.sortValue
                        : (b.children[colIndex] ? b.children[colIndex].textContent.trim() : '');
                    var an = parseFloat(av);
                    var bn = parseFloat(bv);
                    if (av !== '' && bv !== '' && !isNaN(an) && !isNaN(bn)) {
                        return factor * (an - bn);
                    }
                    return factor * String(av).localeCompare(String(bv), 'fr', { numeric: true });
                });

                dataRows.forEach(function (r) { tbody.appendChild(r); });
                fillerRows.forEach(function (r) { tbody.appendChild(r); });
            });
        });
    });
});
