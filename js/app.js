/* ============================================================
   KRYSTAL ATTENDANCE SYSTEM - Frontend Logic (PHP Backend)
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    var datePickers = document.querySelectorAll('input[type="date"]');
    if (datePickers.length > 0) {
        var today = new Date().toISOString().split('T')[0];
        datePickers.forEach(function(p) { if (!p.value) p.value = today; });
    }

    var page = detectPage();
    if (page === 'login') { initLogin(); }
    else if (page === 'dashboard') { initDashboard(); }
    else if (page === 'add-attendance') { initAddAttendance(); }
    else if (page === 'preview') { initPreview(); }
});

/* ============================================================
   HELPERS
   ============================================================ */
function detectPage() {
    if (document.getElementById('login-form')) return 'login';
    if (document.getElementById('staff-name')) return 'add-attendance';
    if (document.querySelector('.preview-stats')) return 'preview';
    var h = document.querySelector('.card-header h3');
    if (h && h.textContent.includes('Saved Attendance Records')) return 'dashboard';
    return 'unknown';
}

function formatDate(dateStr) {
    var d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' });
}

function downloadFile(content, filename, mimeType) {
    var blob = new Blob([content], { type: mimeType });
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.setAttribute('hidden', ''); a.setAttribute('href', url); a.setAttribute('download', filename);
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function generateCsv(employees, date) {
    var csv = 'Serial Number,Name,ID,Post,Status\n';
    employees.forEach(function(s, i) {
        var post = s.post.charAt(0).toUpperCase() + s.post.slice(1);
        var statusStr = s.status ? s.status : 'present';
        if (statusStr === 'halfday') statusStr = 'Half Day';
        else if (statusStr === 'weekoff') statusStr = 'Week Off';
        else statusStr = statusStr.charAt(0).toUpperCase() + statusStr.slice(1);
        csv += (i+1) + ',"' + s.name + '",' + s.id + ',' + post + ',' + statusStr + '\n';
    });
    return csv;
}

function getPostIcon(post) {
    var p = post.toLowerCase();
    if (p === 'guard') return 'fa-user-shield';
    if (p === 'supervisor') return 'fa-clipboard-user';
    if (p === 'bouncer') return 'fa-user-ninja';
    if (p === 'incharge') return 'fa-user-tie';
    if (p === 'driver') return 'fa-car';
    return 'fa-user';
}

function getStatusBadge(status) {
    if (status === 'absent') return '<span class="badge badge-danger">Absent</span>';
    if (status === 'halfday') return '<span class="badge badge-warning" style="background:#f59e0b;color:white;">Half Day</span>';
    if (status === 'leave') return '<span class="badge badge-info" style="background:#0dcaf0;color:white;">Leave</span>';
    if (status === 'weekoff') return '<span class="badge badge-secondary" style="background:#6c757d;color:white;">Week Off</span>';
    return '<span class="badge badge-success">Present</span>';
}

function getShiftLabel(shift) {
    if (shift === 'morning') return 'Morning Shift';
    if (shift === 'afternoon') return 'Afternoon Shift';
    if (shift === 'night') return 'Night Shift';
    if (shift === 'all') return 'Main Admin';
    return shift;
}

function getLocationLabel(location) {
    if (location === 'landside') return 'Landside';
    if (location === 'asset') return 'Asset';
    if (location === 'cargo') return 'Cargo';
    if (location === 'all') return 'All Locations';
    return location;
}

/* ---- CSRF-aware API call ---- */
function apiCall(url, method, body) {
    var headers = { 'Content-Type': 'application/json' };
    // Include CSRF token for mutation requests
    var csrfToken = (typeof CSRF_TOKEN !== 'undefined') ? CSRF_TOKEN : '';
    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }
    var opts = { method: method, headers: headers };
    if (body) opts.body = JSON.stringify(body);
    return fetch(url, opts)
        .then(function(r) {
            if (r.status === 401) {
                document.querySelectorAll('button:disabled').forEach(function(btn) { btn.disabled = false; });
                window.location.href = 'index.php?expired=1';
                return new Promise(function() {});
            }
            var ct = r.headers.get('Content-Type') || '';
            if (!ct.includes('application/json')) {
                // Server returned non-JSON (PHP error page, redirect, etc.)
                return { success: false, error: 'Unexpected server response. Please try again.' };
            }
            return r.json().catch(function() {
                return { success: false, error: 'Invalid response from server.' };
            });
        })
        .catch(function() {
            return { success: false, error: 'Connection error. Please check your network.' };
        });
}

/* ---- Validation constants (mirror backend) ---- */
var VALID_POSTS = ['incharge', 'supervisor', 'bouncer', 'guard', 'driver'];
var VALID_STATUSES = ['present', 'absent', 'halfday', 'leave', 'weekoff'];
var VALID_SHIFTS = ['morning', 'afternoon', 'night'];
var VALID_LOCATIONS = ['landside', 'asset', 'cargo'];
var MAX_NAME_LENGTH = 150;
var MAX_ID_LENGTH = 50;

/* ============================================================
   0. LOGIN PAGE
   ============================================================ */
function initLogin() {
    var form = document.getElementById('login-form');
    var errorBox = document.getElementById('login-error');
    var errorText = document.getElementById('login-error-text');
    var usernameInput = document.getElementById('username');
    if (usernameInput) usernameInput.focus();

    function showError(msg) { errorText.textContent = msg; errorBox.style.display = 'block'; }
    function hideError() { errorBox.style.display = 'none'; }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        hideError();
        var u = document.getElementById('username').value.trim();
        var p = document.getElementById('password').value.trim();
        if (!u || !p) { showError('Please enter both username and password.'); return; }
        if (u.length > 100) { showError('Username is too long.'); return; }
        if (p.length > 255) { showError('Password is too long.'); return; }

        apiCall('api/login.php', 'POST', { username: u, password: p })
        .then(function(data) {
            if (data.success) {
                // Update CSRF token from login response
                if (data.csrf_token) { CSRF_TOKEN = data.csrf_token; }
                window.location.href = 'dashboard.php';
            } else {
                showError(data.error || 'Invalid credentials.');
            }
        })
        .catch(function() { showError('Connection error. Please try again.'); });
    });
}

/* ============================================================
   1. DASHBOARD PAGE
   ============================================================ */
function initDashboard() {
    var tbody = document.getElementById('dashboard-tbody');
    var dateFilter = document.getElementById('date-filter');
    var shiftFilter = document.getElementById('shift-filter');
    var locationFilter = document.getElementById('location-filter');
    var user = (typeof SESSION_USER !== 'undefined') ? SESSION_USER : { role: 'shift', shift: 'morning', location: 'landside' };

    function loadRecords() {
        var params = [];
        if (dateFilter && dateFilter.value) params.push('date=' + dateFilter.value);
        if (shiftFilter && shiftFilter.value) params.push('shift=' + shiftFilter.value);
        if (locationFilter && locationFilter.value) params.push('location=' + locationFilter.value);
        var qs = params.length > 0 ? '?' + params.join('&') : '';

        fetch('api/attendance.php' + qs)
        .then(function(r) {
            var ct = r.headers.get('Content-Type') || '';
            if (!ct.includes('application/json')) {
                return { success: false, error: 'Invalid server response.' };
            }
            return r.json().catch(function() {
                return { success: false, error: 'Invalid server response.' };
            });
        })
        .then(function(data) {
            if (!data.success) { tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;color:var(--text-muted);padding:2rem;">Error loading records.</td></tr>'; return; }
            renderRecords(data.records);
        })
        .catch(function() { tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;color:var(--text-muted);padding:2rem;">Connection error.</td></tr>'; });
    }

    function renderRecords(records) {
        tbody.innerHTML = '';
        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;color:var(--text-muted);padding:2rem;">No attendance records found.</td></tr>';
            return;
        }
        records.forEach(function(rec) {
            var tr = document.createElement('tr');
            var locLabel = rec.location ? getLocationLabel(rec.location) : '';
            tr.innerHTML =
                '<td><strong>' + formatDate(rec.date) + '</strong>' +
                '<div style="font-size:0.85rem;color:var(--text-muted);"><i class="fa-solid fa-users"></i> ' + rec.employees.length + ' Staff &middot; ' + locLabel + ' &middot; ' + getShiftLabel(rec.shift) + '</div></td>' +
                '<td style="text-align:right;"><div class="flex justify-end gap-2">' +
                    '<button class="btn btn-outline btn-icon dash-edit" data-id="' + rec.id + '" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
                    '<button class="btn btn-outline btn-icon dash-csv" data-idx="' + rec.id + '" title="CSV"><i class="fa-solid fa-file-csv"></i></button>' +
                    '<button class="btn btn-danger btn-icon dash-del" data-id="' + rec.id + '" title="Delete"><i class="fa-regular fa-trash-can"></i></button>' +
                '</div></td>';
            tbody.appendChild(tr);

            // Store employees on the button for CSV
            var csvBtn = tr.querySelector('.dash-csv');
            csvBtn._employees = rec.employees;
            csvBtn._date = rec.date;
        });

        // Edit
        tbody.querySelectorAll('.dash-edit').forEach(function(btn) {
            btn.addEventListener('click', function() {
                window.location.href = 'add-attendance.php?edit=' + btn.getAttribute('data-id');
            });
        });
        // CSV
        tbody.querySelectorAll('.dash-csv').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var csv = generateCsv(btn._employees, btn._date);
                downloadFile(csv, 'attendance_' + btn._date + '.csv', 'text/csv');
            });
        });
        // Delete
        tbody.querySelectorAll('.dash-del').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this record?')) return;
                apiCall('api/attendance.php', 'DELETE', { id: parseInt(btn.getAttribute('data-id')) })
                .then(function(data) {
                    if (data.success) loadRecords();
                    else alert(data.error || 'Delete failed.');
                });
            });
        });
    }

    if (dateFilter) { dateFilter.value = ''; dateFilter.addEventListener('change', loadRecords); }
    if (shiftFilter) { shiftFilter.addEventListener('change', loadRecords); }
    if (locationFilter) { locationFilter.addEventListener('change', loadRecords); }
    loadRecords();
}

/* ============================================================
   2. ADD ATTENDANCE PAGE
   ============================================================ */
function initAddAttendance() {
    var form = document.getElementById('staff-form');
    var nameInput = document.getElementById('staff-name');
    var idInput = document.getElementById('staff-id');
    var postSelect = document.getElementById('staff-post');
    var statusSelect = document.getElementById('staff-status');
    var submitBtn = document.getElementById('submit-btn');
    var tbody = document.getElementById('attendance-tbody');
    var searchInput = document.getElementById('search-input');
    var statusFilterSelect = document.getElementById('status-filter');
    var dateInput = document.getElementById('attendance-date');
    var masterValidated = document.getElementById('staff-master-validated');
    var nameDropdown = document.getElementById('name-dropdown');
    var idDropdown = document.getElementById('id-dropdown');

    var previewBtn = document.getElementById('preview-btn');
    var finalSaveBtn = document.getElementById('final-save-btn');
    var exportCsvBtn = document.getElementById('export-csv-btn');
    var downloadPdfBtn = document.getElementById('download-pdf-btn');

    var user = (typeof SESSION_USER !== 'undefined') ? SESSION_USER : { role: 'shift', shift: 'morning', location: 'landside' };
    var activeShift = (typeof ACTIVE_SHIFT !== 'undefined') ? ACTIVE_SHIFT : user.shift;
    var activeLocation = (typeof ACTIVE_LOCATION !== 'undefined') ? ACTIVE_LOCATION : user.location;
    var editRecord = (typeof EDIT_DATA !== 'undefined') ? EDIT_DATA : null;

    // Location-aware localStorage key to prevent cross-location contamination
    function getStorageKey(loc, shf) {
        var l = (loc && loc !== 'all') ? loc : 'landside';
        var s = (shf && shf !== 'all') ? shf : 'morning';
        return 'previewData_' + l + '_' + s;
    }
    var storageKey = getStorageKey(activeLocation, activeShift);

    // Check for "new" flag to reset cache
    if (window.location.search.indexOf('new=1') > -1) {
        localStorage.removeItem(storageKey);
    }

    var attendanceData = [];
    var editIndex = -1;
    var editRecordId = 0;
    var acDebounceTimer = null;

    // ---- Autocomplete Engine (Phase 4A) ----
    function setupAutocomplete(inputEl, dropdownEl, searchField) {
        inputEl.addEventListener('input', function() {
            clearTimeout(acDebounceTimer);
            var val = inputEl.value.trim();
            if (val.length < 1) {
                dropdownEl.classList.remove('active');
                dropdownEl.innerHTML = '';
                // Reset validation when user clears/changes
                if (masterValidated) masterValidated.value = '0';
                return;
            }
            // Reset validation flag on manual typing
            if (masterValidated) masterValidated.value = '0';
            acDebounceTimer = setTimeout(function() {
                apiCall('api/employees.php?search=' + encodeURIComponent(val) + '&status=active&limit=10', 'GET')
                .then(function(data) {
                    if (!data.success || !data.employees || data.employees.length === 0) {
                        dropdownEl.innerHTML = '<div class="autocomplete-no-results">This employee is not added in company records.</div>';
                        dropdownEl.classList.add('active');
                        return;
                    }
                    dropdownEl.innerHTML = '';
                    data.employees.forEach(function(emp) {
                        var item = document.createElement('div');
                        item.className = 'autocomplete-item';
                        item.innerHTML = '<span class="emp-name">' + escHtmlInline(emp.name) + '</span>' +
                            '<span class="emp-meta">ID: ' + escHtmlInline(emp.employee_id) + ' · ' + capitalize(emp.post) + '</span>';
                        item.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            selectEmployee(emp);
                            dropdownEl.classList.remove('active');
                        });
                        dropdownEl.appendChild(item);
                    });
                    dropdownEl.classList.add('active');
                });
            }, 250);
        });

        inputEl.addEventListener('blur', function() {
            setTimeout(function() { dropdownEl.classList.remove('active'); }, 200);
        });

        inputEl.addEventListener('focus', function() {
            if (dropdownEl.children.length > 0 && inputEl.value.trim().length >= 1) {
                dropdownEl.classList.add('active');
            }
        });
    }

    function selectEmployee(emp) {
        nameInput.value = emp.name;
        idInput.value = emp.employee_id;
        postSelect.value = emp.post.toLowerCase();
        if (masterValidated) masterValidated.value = '1';
        // Close both dropdowns
        if (nameDropdown) nameDropdown.classList.remove('active');
        if (idDropdown) idDropdown.classList.remove('active');
    }

    function escHtmlInline(str) {
        var d = document.createElement('span');
        d.textContent = str;
        return d.innerHTML;
    }

    function capitalize(s) {
        return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
    }

    // Wire up both autocomplete fields
    if (nameDropdown) setupAutocomplete(nameInput, nameDropdown, 'name');
    if (idDropdown) setupAutocomplete(idInput, idDropdown, 'id');
    // ---- End Autocomplete ----

    // Load edit data from server if editing
    if (editRecord) {
        attendanceData = editRecord.employees.slice();
        editRecordId = editRecord.id;
        if (dateInput) dateInput.value = editRecord.date;
        // Use location from the record being edited
        if (editRecord.location) activeLocation = editRecord.location;
        // Recalculate storage key after updating activeLocation
        storageKey = getStorageKey(activeLocation, activeShift);
    }

    // Load from persistence if available (Preview -> Back / Edit Attendance)
    var stored = localStorage.getItem(storageKey);
    if (stored) {
        try {
            var p = JSON.parse(stored);
            // If we're editing an existing record, only use cache if it matches the record ID
            // Otherwise if we're not editing (new record), use the cache
            if ((!editRecordId && !p.editId) || (editRecordId && p.editId == editRecordId)) {
                if (p.employees) attendanceData = p.employees;
                if (dateInput && p.date) dateInput.value = p.date;
            }
        } catch(e) {
            console.error('Error parsing stored data', e);
        }
    }

    function renderTable() {
        var searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
        var statusFilter = statusFilterSelect ? statusFilterSelect.value : '';
        tbody.innerHTML = '';
        var serial = 1;
        attendanceData.forEach(function(staff, index) {
            var matchSearch = staff.name.toLowerCase().includes(searchTerm) || staff.id.toString().toLowerCase().includes(searchTerm);
            var matchStatus = (statusFilter === '' || staff.status === statusFilter);
            if (!matchSearch || !matchStatus) return;
            var icon = getPostIcon(staff.post);
            var displayPost = staff.post.charAt(0).toUpperCase() + staff.post.slice(1);
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + (serial++) + '</td>' +
                '<td><i class="fa-solid ' + icon + '" style="color:var(--text-muted);margin-right:8px;"></i> ' + staff.name + '</td>' +
                '<td>' + staff.id + '</td>' +
                '<td>' + displayPost + '</td>' +
                '<td>' + getStatusBadge(staff.status) + '</td>' +
                '<td style="text-align:right;"><div class="flex justify-end gap-2">' +
                    '<button type="button" class="btn btn-outline btn-icon edit-btn" data-index="' + index + '" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
                    '<button type="button" class="btn btn-danger btn-icon delete-btn" data-index="' + index + '" title="Delete"><i class="fa-regular fa-trash-can"></i></button>' +
                '</div></td>';
            tbody.appendChild(tr);
        });
        tbody.querySelectorAll('.edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { editStaff(parseInt(btn.getAttribute('data-index'))); });
        });
        tbody.querySelectorAll('.delete-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { deleteStaff(parseInt(btn.getAttribute('data-index'))); });
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var name = nameInput.value.trim();
        var id = idInput.value.trim();
        var post = postSelect.value;
        var status = statusSelect.value;

        // Frontend validation (mirrors backend)
        if (!name) { alert('Please enter a name.'); return; }
        if (name.length > MAX_NAME_LENGTH) { alert('Name is too long (max ' + MAX_NAME_LENGTH + ' characters).'); return; }
        if (!id || isNaN(id)) { alert('Please enter a valid numeric ID.'); return; }
        if (id.length > MAX_ID_LENGTH) { alert('ID is too long (max ' + MAX_ID_LENGTH + ' characters).'); return; }
        if (!post || VALID_POSTS.indexOf(post.toLowerCase()) === -1) { alert('Please select a valid post.'); return; }
        if (!status || VALID_STATUSES.indexOf(status.toLowerCase()) === -1) { alert('Please select a valid status.'); return; }

        // Phase 4A: Master validation check
        if (masterValidated && masterValidated.value !== '1') {
            alert('This employee is not added in company records.\n\nPlease select an employee from the autocomplete suggestions.');
            nameInput.focus();
            return;
        }

        var isDuplicate = attendanceData.some(function(staff, index) {
            return staff.id === id && index !== editIndex;
        });

        if (isDuplicate) {
            alert('Error: Staff ID ' + id + ' is already added in this session.');
            return;
        }

        if (editIndex > -1) {
            attendanceData[editIndex] = { name: name, id: id, post: post, status: status };
            editIndex = -1;
            submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> Add';
        } else {
            attendanceData.push({ name: name, id: id, post: post, status: status });
        }
        nameInput.value = ''; idInput.value = ''; postSelect.value = ''; statusSelect.value = 'present';
        if (masterValidated) masterValidated.value = '0';
        renderTable();
    });


    function editStaff(index) {
        var s = attendanceData[index];
        nameInput.value = s.name; idInput.value = s.id;
        postSelect.value = s.post.toLowerCase(); statusSelect.value = s.status || 'present';
        editIndex = index;
        if (masterValidated) masterValidated.value = '1';  // Trust existing data
        submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Update';
        nameInput.focus();
    }

    function deleteStaff(index) {
        if (!confirm('Delete this employee?')) return;
        attendanceData.splice(index, 1);
        if (editIndex === index) {
            editIndex = -1; nameInput.value = ''; idInput.value = '';
            postSelect.value = ''; statusSelect.value = 'present';
            if (masterValidated) masterValidated.value = '0';
            submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> Add';
        } else if (editIndex > index) { editIndex--; }
        renderTable();
    }

    if (searchInput) searchInput.addEventListener('input', renderTable);
    if (statusFilterSelect) statusFilterSelect.addEventListener('change', renderTable);

    // Helper: build preview/edit URL with shift + location params for admin
    function buildAdminParams(shift, location, editId) {
        var params = [];
        if (editId) params.push('edit=' + editId);
        if (user.role === 'admin') {
            params.push('shift=' + shift);
            params.push('location=' + location);
        }
        return params.length > 0 ? '?' + params.join('&') : '';
    }

    // Preview - still uses localStorage for cross-page data passing
    if (previewBtn) {
        previewBtn.addEventListener('click', function() {
            if (attendanceData.length === 0) { alert('No attendance data to preview.'); return; }
            var shift = activeShift === 'all' ? 'morning' : activeShift;
            var location = activeLocation === 'all' ? 'landside' : activeLocation;
            localStorage.setItem(getStorageKey(location, shift), JSON.stringify({
                date: dateInput ? dateInput.value : new Date().toISOString().split('T')[0],
                shift: shift,
                location: location,
                employees: attendanceData,
                editId: editRecordId
            }));
            window.location.href = 'preview.php' + buildAdminParams(shift, location, editRecordId);
        });
    }

    // Final Save - saves to DB via API
    if (finalSaveBtn) {
        finalSaveBtn.addEventListener('click', function() {
            if (attendanceData.length === 0) { alert('No data to save.'); return; }
            var date = dateInput ? dateInput.value : new Date().toISOString().split('T')[0];
            var shift = activeShift === 'all' ? 'morning' : activeShift;
            var location = activeLocation === 'all' ? 'landside' : activeLocation;

            // Frontend date validation
            if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) { alert('Please select a valid date.'); return; }
            // Frontend shift validation
            if (VALID_SHIFTS.indexOf(shift) === -1) { alert('Invalid shift selected.'); return; }
            // Frontend location validation
            if (VALID_LOCATIONS.indexOf(location) === -1) { alert('Invalid location selected.'); return; }

            var payload = { date: date, shift: shift, location: location, employees: attendanceData };
            if (editRecordId > 0) payload.editId = editRecordId;

            finalSaveBtn.disabled = true;
            finalSaveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            apiCall('api/attendance.php', 'POST', payload)
            .then(function(data) {
                if (data.success) {
                    localStorage.removeItem(storageKey);
                    window.location.href = 'dashboard.php';
                } else {
                    alert(data.error || 'Save failed.');
                    finalSaveBtn.disabled = false;
                    finalSaveBtn.innerHTML = '<i class="fa-solid fa-check-double"></i> Final Save';
                }
            })
            .catch(function() {
                alert('Connection error.');
                finalSaveBtn.disabled = false;
                finalSaveBtn.innerHTML = '<i class="fa-solid fa-check-double"></i> Final Save';
            });
        });
    }

    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', function() {
            if (attendanceData.length === 0) { alert('No data to export.'); return; }
            var date = dateInput ? dateInput.value : new Date().toISOString().split('T')[0];
            downloadFile(generateCsv(attendanceData, date), 'attendance_' + date + '.csv', 'text/csv');
        });
    }

    if (downloadPdfBtn) {
        downloadPdfBtn.addEventListener('click', function() {
            if (attendanceData.length === 0) { alert('No data to download.'); return; }
            var shift = user.shift === 'all' ? 'morning' : user.shift;
            var location = activeLocation === 'all' ? 'landside' : activeLocation;
            localStorage.setItem(getStorageKey(location, shift), JSON.stringify({
                date: dateInput ? dateInput.value : new Date().toISOString().split('T')[0],
                shift: shift,
                location: location,
                employees: attendanceData,
                editId: editRecordId
            }));
            var url = 'preview.php' + buildAdminParams(shift, location, editRecordId);

            var w = window.open(url, '_blank');
            if (w) { w.addEventListener('load', function() { setTimeout(function() { w.print(); }, 600); }); }
        });
    }

    renderTable();
}

/* ============================================================
   3. PREVIEW PAGE
   ============================================================ */
function initPreview() {
    // Read from location-aware key (passed via localStorage payload's own location+shift)
    // Try to find the right key from URL params or fallback to scanning
    var previewStorageKey = 'previewData';
    var urlParams = new URLSearchParams(window.location.search);
    var urlLoc = urlParams.get('location') || '';
    var urlShift = urlParams.get('shift') || '';
    if (urlLoc && urlShift) {
        previewStorageKey = 'previewData_' + urlLoc + '_' + urlShift;
    } else {
        // Fallback: try to find any previewData_ key
        for (var i = 0; i < localStorage.length; i++) {
            var k = localStorage.key(i);
            if (k && k.indexOf('previewData_') === 0) {
                previewStorageKey = k;
                break;
            }
        }
    }
    var dataStr = localStorage.getItem(previewStorageKey);
    var payload = null;
    if (dataStr) {
        try {
            payload = JSON.parse(dataStr);
        } catch(e) {
            payload = null;
        }
    }
    var employees     = payload ? (payload.employees || []) : [];
    var previewDate   = payload ? (payload.date  || '') : '';
    var previewShift  = payload ? (payload.shift || '') : '';
    var previewLocation = payload ? (payload.location || '') : '';

    var dateBadge = document.querySelector('.header-content .badge');
    if (dateBadge && previewDate) dateBadge.textContent = formatDate(previewDate);

    if (previewShift || previewLocation) {
        var combinedLabel = '';
        if (previewLocation) combinedLabel += getLocationLabel(previewLocation);
        if (previewLocation && previewShift) combinedLabel += ' — ';
        if (previewShift) combinedLabel += getShiftLabel(previewShift);
        document.querySelectorAll('.shift-badge').forEach(function(b) { b.textContent = combinedLabel; });
    }

    var printHeader = document.querySelector('.print-header p');
    if (printHeader && previewDate) {
        var locStr = previewLocation ? getLocationLabel(previewLocation) + ' - ' : '';
        printHeader.textContent = locStr + 'Daily Attendance Report - ' + getShiftLabel(previewShift || 'morning') + ' (' + formatDate(previewDate) + ')';
    }

    var grouped = { incharge: [], supervisor: [], bouncer: [], guard: [], driver: [] };
    employees.forEach(function(s) { var p = s.post.toLowerCase(); if (grouped[p]) grouped[p].push(s); });

    var totalStaff = 0, totalPresent = 0, totalAbsent = 0;

    function renderSection(key) {
        var sections = Array.from(document.querySelectorAll('section.card'));
        var section = sections.find(function(sec) {
            var h3 = sec.querySelector('h3');
            return h3 && h3.textContent.toLowerCase().includes(key);
        });
        if (!section) return;
        var group = grouped[key] || [];
        var pres = 0, abs = 0;
        group.forEach(function(s) { if (s.status === 'absent' || s.status === 'leave' || s.status === 'weekoff') abs++; else pres++; });
        totalStaff += group.length; totalPresent += pres; totalAbsent += abs;

        var sv = section.querySelectorAll('.stat-value');
        if (sv.length >= 3) { sv[0].textContent = group.length; sv[1].textContent = pres; sv[2].textContent = abs; }

        var tb = section.querySelector('tbody');
        if (tb) {
            tb.innerHTML = '';
            if (group.length === 0) {
                tb.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted);">No data available</td></tr>';
            } else {
                group.forEach(function(s) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td>' + s.id + '</td><td>' + s.name + '</td><td style="text-align:right;">' + getStatusBadge(s.status) + '</td>';
                    tb.appendChild(tr);
                });
            }
        }
    }

    ['incharge','supervisor','bouncer','guard','driver'].forEach(renderSection);

    var sc = document.querySelector('.summary-card');
    if (sc) {
        var sv = sc.querySelectorAll('.stat-value');
        if (sv.length >= 3) { sv[0].textContent = totalStaff; sv[1].textContent = totalPresent; sv[2].textContent = totalAbsent; }
    }

    // CSV button
    var abr = document.querySelector('.action-bar.print-hide .action-bar-right');
    if (abr && employees.length > 0) {
        var csvBtn = document.createElement('button');
        csvBtn.className = 'btn btn-outline';
        csvBtn.innerHTML = '<i class="fa-solid fa-file-csv"></i> Export CSV';
        var pb = abr.querySelector('button');
        if (pb) abr.insertBefore(csvBtn, pb); else abr.appendChild(csvBtn);
        csvBtn.addEventListener('click', function() {
            downloadFile(generateCsv(employees, previewDate), 'attendance_' + previewDate + '.csv', 'text/csv');
        });
    }
}
