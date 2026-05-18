/* ============================================================
   DEPLOYMENT LOCATION MASTER MANAGEMENT — Frontend Logic (Phase 5A)
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    var searchInput    = document.getElementById('deploc-search');
    var statusFilter   = document.getElementById('deploc-status-filter');
    var addBtn         = document.getElementById('deploc-add-btn');
    var formCard       = document.getElementById('deploc-form-card');
    var formTitle      = document.getElementById('deploc-form-title');
    var formCloseBtn   = document.getElementById('deploc-form-close');
    var form           = document.getElementById('deploc-form');
    var editIdInput    = document.getElementById('deploc-edit-id');
    var nameInput      = document.getElementById('deploc-name-input');
    var statusInput    = document.getElementById('deploc-status-input');
    var notesInput     = document.getElementById('deploc-notes-input');
    var submitBtn      = document.getElementById('deploc-submit-btn');
    var tbody          = document.getElementById('deploc-tbody');
    var countBadge     = document.getElementById('deploc-count-badge');
    var exportCsvBtn   = document.getElementById('deploc-export-csv');
    var exportPdfBtn   = document.getElementById('deploc-export-pdf');

    var locationsList = [];
    var debounceTimer = null;

    function apiCall(url, method, body) {
        var headers = { 'Content-Type': 'application/json' };
        if (typeof CSRF_TOKEN !== 'undefined' && CSRF_TOKEN) {
            headers['X-CSRF-TOKEN'] = CSRF_TOKEN;
        }
        var opts = { method: method, headers: headers };
        if (body) opts.body = JSON.stringify(body);
        return fetch(url, opts)
            .then(function(r) {
                if (r.status === 401) {
                    window.location.href = 'index.php?expired=1';
                    return new Promise(function() {});
                }
                return r.json().catch(function() {
                    return { success: false, error: 'Invalid server response.' };
                });
            })
            .catch(function() {
                return { success: false, error: 'Connection error.' };
            });
    }

    function loadLocations() {
        var search = searchInput.value.trim();
        var status = statusFilter.value;
        var params = [];
        if (search) params.push('search=' + encodeURIComponent(search));
        if (status) params.push('status=' + encodeURIComponent(status));
        var url = 'api/deployment-locations.php' + (params.length > 0 ? '?' + params.join('&') : '');

        apiCall(url, 'GET')
        .then(function(data) {
            if (data.success) {
                locationsList = data.locations || [];
                renderTable();
            } else {
                alert(data.error || 'Failed to load deployment locations.');
            }
        });
    }

    function renderTable() {
        tbody.innerHTML = '';
        countBadge.textContent = locationsList.length;

        if (locationsList.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:2rem;">No deployment locations found.</td></tr>';
            return;
        }

        locationsList.forEach(function(loc, idx) {
            var statusClass = loc.status === 'active' ? 'badge-success' : 'badge-danger';
            var statusLabel = loc.status === 'active' ? 'Active' : 'Inactive';
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + (idx + 1) + '</td>' +
                '<td><i class="fa-solid fa-map-pin" style="color:var(--text-muted);margin-right:8px;"></i>' + escHtml(loc.name) + '</td>' +
                '<td><span class="badge ' + statusClass + '">' + statusLabel + '</span></td>' +
                '<td style="color:var(--text-muted);font-size:0.85rem;">' + escHtml(loc.notes || '—') + '</td>' +
                '<td style="text-align:right;"><div class="flex justify-end gap-2">' +
                    '<button type="button" class="btn btn-outline btn-icon deploc-edit-btn" data-idx="' + idx + '" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
                    '<button type="button" class="btn btn-danger btn-icon deploc-del-btn" data-idx="' + idx + '" title="Deactivate"><i class="fa-regular fa-trash-can"></i></button>' +
                '</div></td>';
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('.deploc-edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { editLocation(parseInt(btn.getAttribute('data-idx'))); });
        });
        tbody.querySelectorAll('.deploc-del-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { deleteLocation(parseInt(btn.getAttribute('data-idx'))); });
        });
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // Show/hide form
    addBtn.addEventListener('click', function() {
        resetForm();
        formTitle.textContent = 'Add Deployment Location';
        submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> Add Location';
        formCard.style.display = 'block';
        nameInput.focus();
    });

    formCloseBtn.addEventListener('click', function() {
        formCard.style.display = 'none';
        resetForm();
    });

    function resetForm() {
        editIdInput.value = '0';
        nameInput.value = '';
        statusInput.value = 'active';
        notesInput.value = '';
    }

    function editLocation(idx) {
        var loc = locationsList[idx];
        if (!loc) return;
        editIdInput.value = loc.id;
        nameInput.value = loc.name;
        statusInput.value = loc.status;
        notesInput.value = loc.notes || '';
        formTitle.textContent = 'Edit Deployment Location';
        submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Update Location';
        formCard.style.display = 'block';
        nameInput.focus();
    }

    function deleteLocation(idx) {
        var loc = locationsList[idx];
        if (!loc) return;
        if (!confirm('Deactivate deployment location "' + loc.name + '"?\n\nIt will no longer appear in attendance autocomplete.')) return;

        apiCall('api/deployment-locations.php', 'DELETE', { id: loc.id })
        .then(function(data) {
            if (data.success) {
                loadLocations();
            } else {
                alert(data.error || 'Failed to deactivate deployment location.');
            }
        });
    }

    // Form submit
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var editId = parseInt(editIdInput.value) || 0;
        var name   = nameInput.value.trim();
        var status = statusInput.value;
        var notes  = notesInput.value.trim();

        if (!name) { alert('Please enter a location name.'); return; }
        if (name.length > 100) { alert('Location name is too long (max 100 characters).'); return; }

        submitBtn.disabled = true;
        var payload = { name: name, status: status, notes: notes };

        if (editId > 0) {
            payload.id = editId;
            apiCall('api/deployment-locations.php', 'PUT', payload)
            .then(function(data) {
                submitBtn.disabled = false;
                if (data.success) {
                    formCard.style.display = 'none';
                    resetForm();
                    loadLocations();
                } else {
                    alert(data.error || 'Update failed.');
                }
            });
        } else {
            apiCall('api/deployment-locations.php', 'POST', payload)
            .then(function(data) {
                submitBtn.disabled = false;
                if (data.success) {
                    formCard.style.display = 'none';
                    resetForm();
                    loadLocations();
                } else {
                    alert(data.error || 'Failed to add deployment location.');
                }
            });
        }
    });

    // Filters
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadLocations, 300);
    });
    statusFilter.addEventListener('change', loadLocations);

    // CSV Export
    exportCsvBtn.addEventListener('click', function() {
        if (locationsList.length === 0) { alert('No data to export.'); return; }
        var csv = 'Location Name,Status,Notes\n';
        locationsList.forEach(function(loc) {
            csv += '"' + loc.name.replace(/"/g, '""') + '",' +
                   (loc.status.charAt(0).toUpperCase() + loc.status.slice(1)) + ',"' +
                   (loc.notes || '').replace(/"/g, '""') + '"\n';
        });
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = 'deployment_locations_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    });

    // PDF Export (print)
    exportPdfBtn.addEventListener('click', function() {
        if (locationsList.length === 0) { alert('No data to export.'); return; }
        var w = window.open('', '_blank');
        var html = '<!DOCTYPE html><html><head><title>Deployment Locations</title>' +
            '<style>body{font-family:Inter,sans-serif;padding:2rem;}table{width:100%;border-collapse:collapse;margin-top:1rem;}' +
            'th,td{padding:0.5rem 1rem;text-align:left;border-bottom:1px solid #e5e7eb;}th{background:#f8fafc;font-size:0.75rem;text-transform:uppercase;color:#6b7280;}' +
            'h2{color:#1f2937;}.badge{padding:2px 8px;border-radius:9999px;font-size:0.75rem;}.active{background:#dcfce7;color:#166534;}.inactive{background:#fee2e2;color:#991b1b;}</style>' +
            '</head><body><h2>Krystal Attendance — Deployment Locations</h2>' +
            '<p style="color:#6b7280;">Generated: ' + new Date().toLocaleString() + ' | Total: ' + locationsList.length + '</p>' +
            '<table><thead><tr><th>#</th><th>Location Name</th><th>Status</th><th>Notes</th></tr></thead><tbody>';
        locationsList.forEach(function(loc, i) {
            var sc = loc.status === 'active' ? 'active' : 'inactive';
            html += '<tr><td>' + (i+1) + '</td><td>' + escHtml(loc.name) + '</td>' +
                    '<td><span class="badge ' + sc + '">' + (loc.status.charAt(0).toUpperCase() + loc.status.slice(1)) + '</span></td>' +
                    '<td>' + escHtml(loc.notes || '—') + '</td></tr>';
        });
        html += '</tbody></table></body></html>';
        w.document.write(html);
        w.document.close();
        w.addEventListener('load', function() { setTimeout(function() { w.print(); }, 400); });
    });

    // Initial load
    loadLocations();
});
