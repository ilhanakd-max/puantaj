document.addEventListener('DOMContentLoaded', () => {
    const API_URL = 'backend.php';
    const appContent = document.getElementById('app-content');
    const pageTitle = document.getElementById('page-title');
    const menuToggle = document.getElementById('menu-toggle');
    const wrapper = document.getElementById('wrapper');
    const sidebarLinks = document.querySelectorAll('#sidebar-wrapper .list-group-item');

    let employeeModal;
    let timesheetModal;

    // --- INITIALIZATION ---
    function init() {
        employeeModal = new bootstrap.Modal(document.getElementById('employeeModal'));
        timesheetModal = new bootstrap.Modal(document.getElementById('timesheetModal'));

        setupEventListeners();

        sidebarLinks.forEach(l => l.classList.remove('active'));
        document.querySelector('a[data-page="dashboard"]').classList.add('active');

        loadPage('dashboard');
    }

    // --- EVENT LISTENERS ---
    function setupEventListeners() {
        menuToggle.addEventListener('click', () => {
            wrapper.classList.toggle('toggled');
        });

        sidebarLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = e.target.closest('a').dataset.page;
                loadPage(page);

                sidebarLinks.forEach(l => l.classList.remove('active'));
                e.target.closest('a').classList.add('active');

                 if (window.innerWidth < 768 && wrapper.classList.contains("toggled")) {
                    wrapper.classList.remove("toggled");
                }
            });
        });

        document.getElementById('saveEmployee').addEventListener('click', saveEmployee);
        document.getElementById('saveTimesheet').addEventListener('click', saveTimesheet);

        appContent.addEventListener('click', handleAppContentClick);
    }

    function handleAppContentClick(e) {
        const target = e.target.closest('button[data-action]');
        if (!target) return;

        const action = target.dataset.action;

        switch (action) {
            case 'edit-employee': {
                const employeeId = target.dataset.employeeId;
                openEmployeeModal(employeeId);
                break;
            }
            case 'delete-employee': {
                const employeeId = target.dataset.employeeId;
                deleteEmployee(employeeId);
                break;
            }
            case 'edit-timesheet': {
                const timesheetId = target.dataset.timesheetId;
                const employeeId = target.dataset.employeeId;
                openTimesheetModal(timesheetId, employeeId);
                break;
            }
            case 'delete-timesheet': {
                const timesheetId = target.dataset.timesheetId;
                const employeeId = target.dataset.employeeId;
                deleteTimesheet(timesheetId, employeeId);
                break;
            }
        }
    }

    // --- API HELPER ---
    async function apiRequest(action, method = 'GET', body = null) {
        const url = `${API_URL}?action=${action}`;
        const options = {
            method,
            headers: {}
        };

        if (method === 'POST') {
            if (body instanceof FormData) {
                 options.body = body;
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(body);
            }
        }

        try {
            const response = await fetch(method === 'POST' ? `${API_URL}` : url, options);
            if (action === 'export_json') {
                return response.blob();
            }
            return response.json();
        } catch (error) {
            console.error('API Request Error:', error);
            return { success: false, message: 'Sunucuyla iletişim kurulamadı.' };
        }
    }

    // --- PAGE LOADING ---
    async function loadPage(page) {
        pageTitle.textContent = page.charAt(0).toUpperCase() + page.slice(1);
        appContent.innerHTML = '<h2>Yükleniyor...</h2>';

        switch (page) {
            case 'dashboard':
                loadDashboard();
                break;
            case 'employees':
                loadEmployeesPage();
                break;
            case 'timesheets':
                loadTimesheetsPage();
                break;
            case 'reports':
                loadReportsPage();
                break;
            case 'backup':
                loadBackupPage();
                break;
             case 'settings':
                loadSettingsPage();
                break;
            default:
                appContent.innerHTML = '<h2>Sayfa bulunamadı</h2>';
        }
    }

    // --- DASHBOARD ---
    async function loadDashboard() {
        const employees = await apiRequest('get_employees');
        appContent.innerHTML = `
            <h3>Dashboard</h3>
            <p>Toplam ${employees.length} personel bulunmaktadır.</p>
        `;
    }

     // --- SETTINGS ---
    function loadSettingsPage() {
        appContent.innerHTML = `
            <h3>Ayarlar</h3>
            <p>Uygulama ayarları burada yer alacak.</p>
        `;
    }

    // --- EMPLOYEE MANAGEMENT ---
    async function loadEmployeesPage() {
        appContent.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Personel Yönetimi</h3>
                <button class="btn btn-primary" id="addEmployeeBtn"><i class="bi bi-plus-lg"></i> Personel Ekle</button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Ad Soyad</th>
                            <th>TC Kimlik No</th>
                            <th>Telefon</th>
                            <th>Departman</th>
                            <th>İşe Başlama</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody id="employeeList">
                    </tbody>
                </table>
            </div>
        `;
        document.getElementById('addEmployeeBtn').addEventListener('click', openEmployeeModal);
        renderEmployeeList();
    }

    async function renderEmployeeList() {
        const employees = await apiRequest('get_employees');
        const employeeList = document.getElementById('employeeList');
        employeeList.innerHTML = '';
        employees.forEach(emp => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${emp.adSoyad}</td>
                <td>${emp.tcKimlikNo}</td>
                <td>${emp.telefon}</td>
                <td>${emp.departman}</td>
                <td>${new Date(emp.iseBaslamaTarihi).toLocaleDateString()}</td>
                <td>
                    <button class="btn btn-sm btn-info" data-action="edit-employee" data-employee-id="${emp.id}"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-danger" data-action="delete-employee" data-employee-id="${emp.id}"><i class="bi bi-trash"></i></button>
                </td>
            `;
            employeeList.appendChild(row);
        });
    }

    async function openEmployeeModal(employeeId = null) {
        const form = document.getElementById('employeeForm');
        form.reset();
        document.getElementById('employeeId').value = '';

        if (employeeId) {
            const employees = await apiRequest('get_employees');
            const employee = employees.find(e => e.id === employeeId);
            if (employee) {
                document.getElementById('employeeId').value = employee.id;
                document.getElementById('adSoyad').value = employee.adSoyad;
                document.getElementById('tcKimlikNo').value = employee.tcKimlikNo;
                document.getElementById('telefon').value = employee.telefon;
                document.getElementById('departman').value = employee.departman;
                document.getElementById('gorev').value = employee.gorev;
                document.getElementById('iseBaslamaTarihi').value = employee.iseBaslamaTarihi;
            }
        }
        employeeModal.show();
    }

    async function saveEmployee() {
        const employeeId = document.getElementById('employeeId').value;
        const employeeData = {
            id: employeeId || null,
            adSoyad: document.getElementById('adSoyad').value,
            tcKimlikNo: document.getElementById('tcKimlikNo').value,
            telefon: document.getElementById('telefon').value,
            departman: document.getElementById('departman').value,
            gorev: document.getElementById('gorev').value,
            iseBaslamaTarihi: document.getElementById('iseBaslamaTarihi').value,
        };

        const action = employeeId ? 'edit_employee' : 'add_employee';
        const response = await apiRequest(action, 'POST', { action, employee: employeeData });

        if (response.success) {
            employeeModal.hide();
            loadEmployeesPage();
        } else {
            alert('Hata: ' + response.message);
        }
    }

    async function deleteEmployee(employeeId) {
        if (confirm('Bu personeli silmek istediğinizden emin misiniz?')) {
            const response = await apiRequest('delete_employee', 'POST', { action: 'delete_employee', employee_id: employeeId });
            if (response.success) {
                loadEmployeesPage();
            } else {
                alert('Hata: ' + response.message);
            }
        }
    }

    // --- TIMESHEET MANAGEMENT ---
    async function loadTimesheetsPage() {
         const employees = await apiRequest('get_employees');
         let options = employees.map(e => `<option value="${e.id}">${e.adSoyad}</option>`).join('');

        appContent.innerHTML = `
            <h3>Puantaj Yönetimi</h3>
            <div class="mb-3">
                <label for="employeeSelect" class="form-label">Personel Seçin</label>
                <select id="employeeSelect" class="form-select">
                    <option value="">-- Personel Seçin --</option>
                    ${options}
                </select>
            </div>
             <div id="timesheet-content" class="d-none">
                 <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 id="selected-employee-name"></h4>
                    <button class="btn btn-primary" id="addTimesheetBtn"><i class="bi bi-plus-lg"></i> Puantaj Ekle</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Çalışma Saati</th>
                                <th>Mesai Türü</th>
                                <th>İzin Türü</th>
                                <th>Açıklama</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="timesheetList">
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        const employeeSelect = document.getElementById('employeeSelect');
        employeeSelect.addEventListener('change', (e) => {
            const employeeId = e.target.value;
            if (employeeId) {
                document.getElementById('timesheet-content').classList.remove('d-none');
                const selectedEmployee = employees.find(emp => emp.id === employeeId);
                document.getElementById('selected-employee-name').textContent = selectedEmployee.adSoyad;
                renderTimesheetList(employeeId);
            } else {
                 document.getElementById('timesheet-content').classList.add('d-none');
            }
        });

        document.getElementById('addTimesheetBtn').addEventListener('click', () => {
             const employeeId = document.getElementById('employeeSelect').value;
             openTimesheetModal(null, employeeId);
        });
    }

    async function renderTimesheetList(employeeId) {
        const timesheets = await apiRequest(`get_timesheets_by_employee&employee_id=${employeeId}`);
        const timesheetList = document.getElementById('timesheetList');
        timesheetList.innerHTML = '';
        timesheets.sort((a, b) => new Date(b.date) - new Date(a.date));

        timesheets.forEach(ts => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${new Date(ts.date).toLocaleDateString()}</td>
                <td>${ts.workingHours}</td>
                <td>${ts.shiftType}</td>
                <td>${ts.leaveType || '-'}</td>
                <td>${ts.description || '-'}</td>
                <td>
                    <button class="btn btn-sm btn-info" data-action="edit-timesheet" data-timesheet-id="${ts.id}" data-employee-id="${employeeId}"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-danger" data-action="delete-timesheet" data-timesheet-id="${ts.id}" data-employee-id="${employeeId}"><i class="bi bi-trash"></i></button>
                </td>
            `;
            timesheetList.appendChild(row);
        });
    }

    async function openTimesheetModal(timesheetId = null, employeeId) {
        const form = document.getElementById('timesheetForm');
        form.reset();
        document.getElementById('timesheetId').value = '';
        document.getElementById('timesheetEmployeeId').value = employeeId;
        document.getElementById('timesheetDate').valueAsDate = new Date();


        if (timesheetId) {
            const timesheets = await apiRequest(`get_timesheets_by_employee&employee_id=${employeeId}`);
            const timesheet = timesheets.find(t => t.id === timesheetId);
            if (timesheet) {
                document.getElementById('timesheetId').value = timesheet.id;
                document.getElementById('timesheetDate').value = timesheet.date;
                document.getElementById('workingHours').value = timesheet.workingHours;
                document.getElementById('shiftType').value = timesheet.shiftType;
                document.getElementById('leaveType').value = timesheet.leaveType;
                document.getElementById('description').value = timesheet.description;
            }
        }
        timesheetModal.show();
    }

    async function saveTimesheet() {
        const timesheetId = document.getElementById('timesheetId').value;
        const employeeId = document.getElementById('timesheetEmployeeId').value;
        const timesheetData = {
            id: timesheetId || null,
            employee_id: employeeId,
            date: document.getElementById('timesheetDate').value,
            workingHours: parseFloat(document.getElementById('workingHours').value),
            shiftType: document.getElementById('shiftType').value,
            leaveType: document.getElementById('leaveType').value,
            description: document.getElementById('description').value,
        };

        const action = timesheetId ? 'edit_timesheet' : 'add_timesheet';
        const response = await apiRequest(action, 'POST', { action, timesheet: timesheetData });

        if (response.success) {
            timesheetModal.hide();
            renderTimesheetList(employeeId);
        } else {
            alert('Hata: ' + response.message);
        }
    }

    async function deleteTimesheet(timesheetId, employeeId) {
        if (confirm('Bu puantaj kaydını silmek istediğinizden emin misiniz?')) {
            const response = await apiRequest('delete_timesheet', 'POST', { action: 'delete_timesheet', timesheet_id: timesheetId });
            if (response.success) {
                renderTimesheetList(employeeId);
            } else {
                alert('Hata: ' + response.message);
            }
        }
    }

    // --- REPORTS ---
    async function loadReportsPage() {
        const employees = await apiRequest('get_employees');
        let options = employees.map(e => `<option value="${e.id}">${e.adSoyad}</option>`).join('');

        appContent.innerHTML = `
            <h3>Raporlar</h3>
            <div class="row">
                <div class="col-md-4">
                    <label for="reportEmployeeSelect" class="form-label">Personel</label>
                    <select id="reportEmployeeSelect" class="form-select">
                        <option value="">-- Personel Seçin --</option>
                        ${options}
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="reportMonth" class="form-label">Ay</label>
                    <input type="month" id="reportMonth" class="form-control" value="${new Date().toISOString().slice(0, 7)}">
                </div>
                 <div class="col-md-2 d-flex align-items-end">
                    <button id="generateReportBtn" class="btn btn-primary">Rapor Oluştur</button>
                </div>
            </div>
            <hr>
            <div id="report-output"></div>
        `;

        document.getElementById('generateReportBtn').addEventListener('click', generateReport);
    }

    async function generateReport() {
        const employeeId = document.getElementById('reportEmployeeSelect').value;
        const month = document.getElementById('reportMonth').value;
        const output = document.getElementById('report-output');

        if (!employeeId || !month) {
            output.innerHTML = '<p class="text-danger">Lütfen personel ve ay seçin.</p>';
            return;
        }

        const employees = await apiRequest('get_employees');
        const employee = employees.find(e => e.id === employeeId);
        let timesheets = await apiRequest(`get_timesheets_by_employee&employee_id=${employeeId}`);

        const [year, monthNum] = month.split('-');
        timesheets = timesheets.filter(ts => ts.date.startsWith(month));

        if (timesheets.length === 0) {
            output.innerHTML = '<p>Seçilen ay için kayıt bulunamadı.</p>';
            return;
        }

        // Calculate stats
        let totalHours = 0;
        const shiftCounts = {};
        const leaveCounts = {};
        timesheets.forEach(ts => {
            totalHours += ts.workingHours;
            shiftCounts[ts.shiftType] = (shiftCounts[ts.shiftType] || 0) + 1;
            if (ts.leaveType) {
                leaveCounts[ts.leaveType] = (leaveCounts[ts.leaveType] || 0) + 1;
            }
        });

        let reportHTML = `
            <div id="report-content">
                <h4>${employee.adSoyad} - ${month} Puantaj Raporu</h4>
                <p><strong>Toplam Çalışma Saati:</strong> ${totalHours}</p>
                <h5>Mesai Türleri</h5>
                <ul>
                    ${Object.entries(shiftCounts).map(([type, count]) => `<li>${type}: ${count} gün</li>`).join('')}
                </ul>
                 <h5>İzinler</h5>
                <ul>
                    ${Object.entries(leaveCounts).map(([type, count]) => `<li>${type}: ${count} gün</li>`).join('')}
                </ul>
                <table class="table table-sm mt-3">
                    <thead><tr><th>Tarih</th><th>Saat</th><th>Mesai</th><th>İzin</th><th>Açıklama</th></tr></thead>
                    <tbody>
                        ${timesheets.map(ts => `
                            <tr>
                                <td>${new Date(ts.date).toLocaleDateString()}</td>
                                <td>${ts.workingHours}</td>
                                <td>${ts.shiftType}</td>
                                <td>${ts.leaveType || '-'}</td>
                                <td>${ts.description || '-'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button id="exportPdfBtn" class="btn btn-danger"><i class="bi bi-file-pdf"></i> PDF İndir</button>
                <button id="exportJsonBtn" class="btn btn-secondary"><i class="bi bi-file-code"></i> JSON İndir</button>
                <button id="exportTxtBtn" class="btn btn-info text-white"><i class="bi bi-file-text"></i> TXT İndir</button>
            </div>
        `;

        output.innerHTML = reportHTML;

        document.getElementById('exportPdfBtn').addEventListener('click', () => exportReportAsPDF(employee.adSoyad, month));
        document.getElementById('exportJsonBtn').addEventListener('click', () => exportReportAsJSON(timesheets, employee.adSoyad, month));
        document.getElementById('exportTxtBtn').addEventListener('click', () => exportReportAsTXT(timesheets, employee.adSoyad, month));

    }

    function exportReportAsPDF(employeeName, month) {
        const { jsPDF } = window.jspdf;
        const reportContent = document.getElementById('report-content');

        html2canvas(reportContent).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF();
            const imgProps= pdf.getImageProperties(imgData);
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
            pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
            pdf.save(`${employeeName}_${month}_rapor.pdf`);
        });
    }

    function exportReportAsJSON(data, employeeName, month) {
        const jsonString = JSON.stringify(data, null, 2);
        const blob = new Blob([jsonString], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${employeeName}_${month}_rapor.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function exportReportAsTXT(data, employeeName, month) {
        let txtContent = `${employeeName} - ${month} Raporu\n\n`;
        data.forEach(ts => {
            txtContent += `Tarih: ${ts.date}\n`;
            txtContent += `Çalışma Saati: ${ts.workingHours}\n`;
            txtContent += `Mesai Türü: ${ts.shiftType}\n`;
            txtContent += `İzin Türü: ${ts.leaveType || '-'}\n`;
            txtContent += `Açıklama: ${ts.description || '-'}\n`;
            txtContent += '------------------------\n';
        });

        const blob = new Blob([txtContent], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${employeeName}_${month}_rapor.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // --- BACKUP & RESTORE ---
    function loadBackupPage() {
        appContent.innerHTML = `
            <h3>Yedekleme ve Geri Yükleme</h3>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Verileri Dışa Aktar</h5>
                    <p class="card-text">Tüm personel ve puantaj verilerini bir JSON dosyası olarak indirin.</p>
                    <button id="exportDbBtn" class="btn btn-success">JSON İndir</button>
                </div>
            </div>
             <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Verileri İçe Aktar</h5>
                    <p class="card-text">Daha önce indirdiğiniz bir JSON dosyasını yükleyerek verileri geri yükleyin. <strong>Mevcut tüm veriler silinecektir!</strong></p>
                    <input type="file" id="importFile" class="form-control mb-2" accept=".json">
                    <button id="importDbBtn" class="btn btn-warning">Yükle ve Geri Yükle</button>
                </div>
            </div>
        `;

        document.getElementById('exportDbBtn').addEventListener('click', exportDatabase);
        document.getElementById('importDbBtn').addEventListener('click', importDatabase);
    }

    async function exportDatabase() {
        const blob = await apiRequest('export_json', 'GET');
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'puantaj_yedek.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    async function importDatabase() {
        const fileInput = document.getElementById('importFile');
        if (fileInput.files.length === 0) {
            alert('Lütfen bir dosya seçin.');
            return;
        }

        if (!confirm('Emin misiniz? Bu işlem mevcut tüm verileri silecektir!')) {
            return;
        }

        const formData = new FormData();
        formData.append('json_file', fileInput.files[0]);
        formData.append('action', 'import_json');

        const response = await apiRequest('import_json', 'POST', formData);

        if (response.success) {
            alert('Veriler başarıyla geri yüklendi.');
            loadPage('dashboard');
        } else {
            alert('Hata: ' + response.message);
        }
    }


    // --- START THE APP ---
    init();
});
