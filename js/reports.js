/**
 * Phase 4B — Krystal Attendance System
 * Reports Module Frontend Logic
 */

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const searchInput = document.getElementById('report-emp-search');
    const dropdownEl = document.getElementById('report-emp-dropdown');
    const empIdHidden = document.getElementById('report-emp-id');
    const empBadge = document.getElementById('report-emp-badge');
    const empBadgeText = document.getElementById('report-emp-badge-text');
    const empClearBtn = document.getElementById('report-emp-clear');
    const clearFiltersBtn = document.getElementById('report-clear-btn');
    
    const startDateInput = document.getElementById('report-start-date');
    const endDateInput = document.getElementById('report-end-date');
    const locationSelect = document.getElementById('report-location');
    const shiftSelect = document.getElementById('report-shift');
    const statusSelect = document.getElementById('report-status');
    
    const form = document.getElementById('report-form');
    const tbody = document.getElementById('report-tbody');
    const countBadge = document.getElementById('report-count-badge');
    
    const exportCsvBtn = document.getElementById('report-export-csv');
    const exportPdfBtn = document.getElementById('report-export-pdf');

    let currentData = [];
    let acDebounceTimer = null;

    // ----- Autocomplete Logic -----
    searchInput.addEventListener('input', function() {
        clearTimeout(acDebounceTimer);
        var val = searchInput.value.trim();
        if (val.length < 1) {
            dropdownEl.classList.remove('active');
            dropdownEl.innerHTML = '';
            return;
        }
        acDebounceTimer = setTimeout(function() {
            fetch('api/employees.php?search=' + encodeURIComponent(val) + '&limit=10')
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.employees || data.employees.length === 0) {
                    dropdownEl.innerHTML = '<div class="autocomplete-no-results">No employees found.</div>';
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
                    });
                    dropdownEl.appendChild(item);
                });
                dropdownEl.classList.add('active');
            }).catch(err => console.error(err));
        }, 250);
    });

    searchInput.addEventListener('blur', function() {
        setTimeout(function() { dropdownEl.classList.remove('active'); }, 200);
    });

    searchInput.addEventListener('focus', function() {
        if (dropdownEl.children.length > 0 && searchInput.value.trim().length >= 1) {
            dropdownEl.classList.add('active');
        }
    });

    function selectEmployee(emp) {
        dropdownEl.classList.remove('active');
        searchInput.value = '';
        empIdHidden.value = emp.employee_id;
        empBadgeText.textContent = emp.name + ' (' + emp.employee_id + ')';
        empBadge.style.display = 'inline-block';
        searchInput.placeholder = 'Employee selected...';
        searchInput.disabled = true;
    }

    empClearBtn.addEventListener('click', function() {
        empIdHidden.value = '';
        empBadge.style.display = 'none';
        searchInput.placeholder = 'Name or ID...';
        searchInput.disabled = false;
        searchInput.focus();
    });

    clearFiltersBtn.addEventListener('click', function() {
        empClearBtn.click();
        startDateInput.value = '';
        endDateInput.value = '';
        locationSelect.value = 'all';
        shiftSelect.value = 'all';
        statusSelect.value = 'all';
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">Set filters and click Search to view history.</td></tr>';
        countBadge.textContent = '0';
        currentData = [];
    });

    // ----- Fetch and Render Data -----
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        fetchReports();
    });

    function fetchReports() {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</td></tr>';
        
        let url = new URL(window.location.origin + '/krystal/api/reports.php');
        let params = {
            emp_id: empIdHidden.value,
            start_date: startDateInput.value,
            end_date: endDateInput.value,
            location: locationSelect.value,
            shift: shiftSelect.value,
            status: statusSelect.value
        };
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

        fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentData = data.records;
                renderTable(currentData);
            } else {
                alert(data.message || 'Error fetching reports.');
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:red;padding:2rem;">Error fetching data.</td></tr>';
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:red;padding:2rem;">Network error.</td></tr>';
        });
    }

    function renderTable(records) {
        countBadge.textContent = records.length;
        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">No records found for the selected filters.</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        records.forEach((r, idx) => {
            let tr = document.createElement('tr');
            
            // Format status badge
            let statusBadge = '';
            if (r.status === 'present') statusBadge = '<span class="badge badge-success">Present</span>';
            else if (r.status === 'absent') statusBadge = '<span class="badge badge-danger">Absent</span>';
            else if (r.status === 'halfday') statusBadge = '<span class="badge badge-warning">Half Day</span>';
            else if (r.status === 'leave') statusBadge = '<span class="badge badge-info">Leave</span>';
            else statusBadge = '<span class="badge">' + capitalize(r.status) + '</span>';

            tr.innerHTML = `
                <td>${idx + 1}</td>
                <td>${escHtmlInline(r.date)}</td>
                <td>${capitalize(r.location)}</td>
                <td>${capitalize(r.shift)}</td>
                <td>${escHtmlInline(r.employee_id)}</td>
                <td>${escHtmlInline(r.name)}</td>
                <td>${capitalize(r.post)}</td>
                <td>${statusBadge}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // ----- Exports -----
    exportCsvBtn.addEventListener('click', function() {
        if (currentData.length === 0) {
            alert('No data to export.');
            return;
        }
        let csv = 'Date,Location,Shift,Emp ID,Name,Post,Status\n';
        currentData.forEach(r => {
            let row = [
                r.date,
                capitalize(r.location),
                capitalize(r.shift),
                r.employee_id,
                `"${r.name.replace(/"/g, '""')}"`,
                capitalize(r.post),
                capitalize(r.status)
            ];
            csv += row.join(',') + '\n';
        });

        let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.setAttribute('download', 'attendance_report.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });

    exportPdfBtn.addEventListener('click', function() {
        if (currentData.length === 0) {
            alert('No data to print/export.');
            return;
        }
        
        let printWin = window.open('', '_blank');
        
        let html = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Attendance Report</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #ccc; padding-bottom: 10px; }
                h1 { margin: 0; color: #0f172a; font-size: 24px; }
                .meta { color: #64748b; font-size: 14px; margin-top: 5px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
                th, td { border: 1px solid #e2e8f0; padding: 8px 12px; text-align: left; }
                th { background-color: #f8fafc; font-weight: bold; }
                @media print {
                    @page { margin: 1cm; }
                    body { -webkit-print-color-adjust: exact; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>KRYSTAL ATTENDANCE REPORT</h1>
                <div class="meta">Generated on: ${new Date().toLocaleString()} | Total Records: ${currentData.length}</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Shift</th>
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Post</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        currentData.forEach(r => {
            html += `
            <tr>
                <td>${escHtmlInline(r.date)}</td>
                <td>${capitalize(r.location)}</td>
                <td>${capitalize(r.shift)}</td>
                <td>${escHtmlInline(r.employee_id)}</td>
                <td>${escHtmlInline(r.name)}</td>
                <td>${capitalize(r.post)}</td>
                <td>${capitalize(r.status)}</td>
            </tr>`;
        });
        
        html += `
                </tbody>
            </table>
            <script>
                window.onload = function() { window.print(); window.close(); }
            </script>
        </body>
        </html>`;
        
        printWin.document.open();
        printWin.document.write(html);
        printWin.document.close();
    });

    // Helpers
    function escHtmlInline(str) {
        var d = document.createElement('span');
        d.textContent = str;
        return d.innerHTML;
    }
    function capitalize(s) {
        return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
    }

    // Default load: Last 7 days
    let today = new Date();
    let lastWeek = new Date(today);
    lastWeek.setDate(today.getDate() - 7);
    
    // Format YYYY-MM-DD
    endDateInput.value = today.toISOString().split('T')[0];
    startDateInput.value = lastWeek.toISOString().split('T')[0];

    // Trigger initial search
    fetchReports();
});
