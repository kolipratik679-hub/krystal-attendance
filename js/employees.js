/* ============================================================
   EMPLOYEE MASTER MANAGEMENT — Frontend Logic (Phase 4A)
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    var searchInput    = document.getElementById('emp-search');
    var statusFilter   = document.getElementById('emp-status-filter');
    var postFilter     = document.getElementById('emp-post-filter');
    var addBtn         = document.getElementById('emp-add-btn');
    var formCard       = document.getElementById('emp-form-card');
    var formTitle      = document.getElementById('emp-form-title');
    var formCloseBtn   = document.getElementById('emp-form-close');
    var form           = document.getElementById('emp-form');
    var editIdInput    = document.getElementById('emp-edit-id');
    var idInput        = document.getElementById('emp-id-input');
    var nameInput      = document.getElementById('emp-name-input');
    var postInput      = document.getElementById('emp-post-input');
    var statusInput    = document.getElementById('emp-status-input');
    var notesInput     = document.getElementById('emp-notes-input');
    var submitBtn      = document.getElementById('emp-submit-btn');
    var tbody          = document.getElementById('emp-tbody');
    var countBadge     = document.getElementById('emp-count-badge');
    var exportCsvBtn   = document.getElementById('emp-export-csv');
    var exportPdfBtn   = document.getElementById('emp-export-pdf');

    var employeesList = [];
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

    function loadEmployees() {
        var search = searchInput.value.trim();
        var status = statusFilter.value;
        var post   = postFilter.value;
        var params = [];
        if (search) params.push('search=' + encodeURIComponent(search));
        if (status) params.push('status=' + encodeURIComponent(status));
        if (post)   params.push('post=' + encodeURIComponent(post));
        var url = 'api/employees.php' + (params.length > 0 ? '?' + params.join('&') : '');

        apiCall(url, 'GET')
        .then(function(data) {
            if (data.success) {
                employeesList = data.employees || [];
                renderTable();
            } else {
                alert(data.error || 'Failed to load employees.');
            }
        });
    }

    function getPostIcon(post) {
        var p = (post || '').toLowerCase();
        if (p === 'guard')      return 'fa-user-shield';
        if (p === 'supervisor') return 'fa-clipboard-user';
        if (p === 'bouncer')    return 'fa-user-ninja';
        if (p === 'incharge')   return 'fa-user-tie';
        if (p === 'driver')     return 'fa-car';
        return 'fa-user';
    }

    function renderTable() {
        tbody.innerHTML = '';
        countBadge.textContent = employeesList.length;

        if (employeesList.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem;">No employees found.</td></tr>';
            return;
        }

        employeesList.forEach(function(emp, idx) {
            var icon = getPostIcon(emp.post);
            var displayPost = emp.post.charAt(0).toUpperCase() + emp.post.slice(1);
            var statusClass = emp.status === 'active' ? 'badge-success' : 'badge-danger';
            var statusLabel = emp.status === 'active' ? 'Active' : 'Inactive';
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + (idx + 1) + '</td>' +
                '<td><strong>' + escHtml(emp.employee_id) + '</strong></td>' +
                '<td><i class="fa-solid ' + icon + '" style="color:var(--text-muted);margin-right:8px;"></i>' + escHtml(emp.name) + '</td>' +
                '<td>' + displayPost + '</td>' +
                '<td><span class="badge ' + statusClass + '">' + statusLabel + '</span></td>' +
                '<td style="color:var(--text-muted);font-size:0.85rem;">' + escHtml(emp.notes || '—') + '</td>' +
                '<td style="text-align:right;"><div class="flex justify-end gap-2">' +
                    '<button type="button" class="btn btn-outline btn-icon emp-edit-btn" data-idx="' + idx + '" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
                    '<button type="button" class="btn btn-danger btn-icon emp-del-btn" data-idx="' + idx + '" title="Deactivate"><i class="fa-regular fa-trash-can"></i></button>' +
                '</div></td>';
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('.emp-edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { editEmployee(parseInt(btn.getAttribute('data-idx'))); });
        });
        tbody.querySelectorAll('.emp-del-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { deleteEmployee(parseInt(btn.getAttribute('data-idx'))); });
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
        formTitle.textContent = 'Add Employee';
        submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> Add Employee';
        formCard.style.display = 'block';
        idInput.focus();
    });

    formCloseBtn.addEventListener('click', function() {
        formCard.style.display = 'none';
        resetForm();
    });

    function resetForm() {
        editIdInput.value = '0';
        idInput.value = '';
        nameInput.value = '';
        postInput.value = '';
        statusInput.value = 'active';
        notesInput.value = '';
    }

    function editEmployee(idx) {
        var emp = employeesList[idx];
        if (!emp) return;
        editIdInput.value = emp.id;
        idInput.value = emp.employee_id;
        nameInput.value = emp.name;
        postInput.value = emp.post;
        statusInput.value = emp.status;
        notesInput.value = emp.notes || '';
        formTitle.textContent = 'Edit Employee';
        submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Update Employee';
        formCard.style.display = 'block';
        nameInput.focus();
    }

    function deleteEmployee(idx) {
        var emp = employeesList[idx];
        if (!emp) return;
        if (!confirm('Deactivate employee "' + emp.name + '" (ID: ' + emp.employee_id + ')?\n\nThis will set their status to Inactive. They will no longer appear in attendance autocomplete.')) return;

        apiCall('api/employees.php', 'DELETE', { id: emp.id })
        .then(function(data) {
            if (data.success) {
                loadEmployees();
            } else {
                alert(data.error || 'Failed to deactivate employee.');
            }
        });
    }

    // Form submit
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var editId = parseInt(editIdInput.value) || 0;
        var empId  = idInput.value.trim();
        var name   = nameInput.value.trim();
        var post   = postInput.value;
        var status = statusInput.value;
        var notes  = notesInput.value.trim();

        if (!empId || !/^\d+$/.test(empId)) { alert('Employee ID must be a numeric value.'); return; }
        if (empId.length > 20) { alert('Employee ID is too long (max 20 digits).'); return; }
        if (!name) { alert('Please enter employee name.'); return; }
        if (name.length > 150) { alert('Name is too long (max 150 characters).'); return; }
        if (!post) { alert('Please select a post.'); return; }

        submitBtn.disabled = true;
        var payload = { employee_id: empId, name: name, post: post, status: status, notes: notes };

        if (editId > 0) {
            payload.id = editId;
            apiCall('api/employees.php', 'PUT', payload)
            .then(function(data) {
                submitBtn.disabled = false;
                if (data.success) {
                    formCard.style.display = 'none';
                    resetForm();
                    loadEmployees();
                } else {
                    alert(data.error || 'Update failed.');
                }
            });
        } else {
            apiCall('api/employees.php', 'POST', payload)
            .then(function(data) {
                submitBtn.disabled = false;
                if (data.success) {
                    formCard.style.display = 'none';
                    resetForm();
                    loadEmployees();
                } else {
                    alert(data.error || 'Failed to add employee.');
                }
            });
        }
    });

    // Filters
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadEmployees, 300);
    });
    statusFilter.addEventListener('change', loadEmployees);
    postFilter.addEventListener('change', loadEmployees);

    // CSV Export
    exportCsvBtn.addEventListener('click', function() {
        if (employeesList.length === 0) { alert('No data to export.'); return; }
        var csv = 'Employee ID,Name,Post,Status,Notes\n';
        employeesList.forEach(function(emp) {
            csv += emp.employee_id + ',"' + emp.name + '",' +
                   (emp.post.charAt(0).toUpperCase() + emp.post.slice(1)) + ',' +
                   (emp.status.charAt(0).toUpperCase() + emp.status.slice(1)) + ',"' +
                   (emp.notes || '') + '"\n';
        });
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = 'employees_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    });

    // PDF Export (print)
    exportPdfBtn.addEventListener('click', function() {
        if (employeesList.length === 0) { alert('No data to export.'); return; }
        var w = window.open('', '_blank');
        var html = '<!DOCTYPE html><html><head><title>Employee List</title>' +
            '<style>body{font-family:Inter,sans-serif;padding:2rem;}table{width:100%;border-collapse:collapse;margin-top:1rem;}' +
            'th,td{padding:0.5rem 1rem;text-align:left;border-bottom:1px solid #e5e7eb;}th{background:#f8fafc;font-size:0.75rem;text-transform:uppercase;color:#6b7280;}' +
            'h2{color:#1f2937;}.badge{padding:2px 8px;border-radius:9999px;font-size:0.75rem;}.active{background:#dcfce7;color:#166534;}.inactive{background:#fee2e2;color:#991b1b;}</style>' +
            '</head><body><h2>Krystal Attendance — Employee Master List</h2>' +
            '<p style="color:#6b7280;">Generated: ' + new Date().toLocaleString() + ' | Total: ' + employeesList.length + '</p>' +
            '<table><thead><tr><th>#</th><th>Emp ID</th><th>Name</th><th>Post</th><th>Status</th><th>Notes</th></tr></thead><tbody>';
        employeesList.forEach(function(emp, i) {
            var sc = emp.status === 'active' ? 'active' : 'inactive';
            html += '<tr><td>' + (i+1) + '</td><td>' + emp.employee_id + '</td><td>' + escHtml(emp.name) + '</td>' +
                    '<td>' + (emp.post.charAt(0).toUpperCase() + emp.post.slice(1)) + '</td>' +
                    '<td><span class="badge ' + sc + '">' + (emp.status.charAt(0).toUpperCase() + emp.status.slice(1)) + '</span></td>' +
                    '<td>' + escHtml(emp.notes || '—') + '</td></tr>';
        });
        html += '</tbody></table></body></html>';
        w.document.write(html);
        w.document.close();
        w.addEventListener('load', function() { setTimeout(function() { w.print(); }, 400); });
    });

    // Initial load
    loadEmployees();
});
