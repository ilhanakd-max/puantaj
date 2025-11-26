<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puantaj Web Uygulaması</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading text-white">Puantaj Takip</div>
            <div class="list-group list-group-flush">
                <a href="#" class="list-group-item list-group-item-action bg-dark text-white" data-page="dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="#" class="list-group-item list-group-item-action bg-dark text-white" data-page="employees"><i class="bi bi-people"></i> Personeller</a>
                <a href="#" class="list-group-item list-group-item-action bg-dark text-white" data-page="timesheets"><i class="bi bi-calendar-check"></i> Puantaj</a>
                <a href="#" class="list-group-item list-group-item-action bg-dark text-white" data-page="reports"><i class="bi bi-file-earmark-bar-graph"></i> Raporlar</a>
                <a href="#" class="list-group-item list-group-item-action bg-dark text-white" data-page="backup"><i class="bi bi-database-down"></i> Yedekleme</a>
                <a href="#" class="list-group-item list-group-item-action bg-dark text-white" data-page="settings"><i class="bi bi-gear"></i> Ayarlar</a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-primary" id="menu-toggle"><i class="bi bi-list"></i></button>
                    <h5 class="ms-3 mb-0" id="page-title">Dashboard</h5>
                </div>
            </nav>

            <div class="container-fluid p-4" id="app-content">
                <!-- Content will be loaded here by JavaScript -->
            </div>
        </div>
        <!-- /#page-content-wrapper -->
    </div>
    <!-- /#wrapper -->

    <!-- Modals -->
    <!-- Employee Modal -->
    <div class="modal fade" id="employeeModal" tabindex="-1" aria-labelledby="employeeModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="employeeModalLabel">Personel Ekle/Düzenle</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="employeeForm">
              <input type="hidden" id="employeeId">
              <div class="mb-3">
                <label for="adSoyad" class="form-label">Ad Soyad</label>
                <input type="text" class="form-control" id="adSoyad" required>
              </div>
              <div class="mb-3">
                <label for="tcKimlikNo" class="form-label">TC Kimlik No</label>
                <input type="text" class="form-control" id="tcKimlikNo">
              </div>
              <div class="mb-3">
                <label for="telefon" class="form-label">Telefon</label>
                <input type="text" class="form-control" id="telefon">
              </div>
              <div class="mb-3">
                <label for="departman" class="form-label">Departman</label>
                <input type="text" class="form-control" id="departman">
              </div>
              <div class="mb-3">
                <label for="gorev" class="form-label">Görev</label>
                <input type="text" class="form-control" id="gorev">
              </div>
              <div class="mb-3">
                <label for="iseBaslamaTarihi" class="form-label">İşe Başlama Tarihi</label>
                <input type="date" class="form-control" id="iseBaslamaTarihi" required>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            <button type="button" class="btn btn-primary" id="saveEmployee">Kaydet</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Timesheet Modal -->
    <div class="modal fade" id="timesheetModal" tabindex="-1" aria-labelledby="timesheetModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="timesheetModalLabel">Puantaj Ekle/Düzenle</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="timesheetForm">
              <input type="hidden" id="timesheetId">
              <input type="hidden" id="timesheetEmployeeId">
               <div class="mb-3">
                  <label for="timesheetDate" class="form-label">Tarih</label>
                  <input type="date" class="form-control" id="timesheetDate" required>
              </div>
              <div class="mb-3">
                  <label for="workingHours" class="form-label">Çalışma Saati</label>
                  <input type="number" class="form-control" id="workingHours" step="0.5" value="8">
              </div>
              <div class="mb-3">
                  <label for="shiftType" class="form-label">Mesai Türü</label>
                  <select class="form-select" id="shiftType">
                      <option value="Gündüz">Gündüz</option>
                      <option value="Gece">Gece</option>
                      <option value="Resmî Tatil">Resmî Tatil</option>
                      <option value="Özel">Özel</option>
                  </select>
              </div>
              <div class="mb-3">
                  <label for="leaveType" class="form-label">İzin Türü (Varsa)</label>
                  <select class="form-select" id="leaveType">
                      <option value="">Yok</option>
                      <option value="Ücretli">Ücretli İzin</option>
                      <option value="Ücretsiz">Ücretsiz İzin</option>
                      <option value="Rapor">Rapor</option>
                  </select>
              </div>
              <div class="mb-3">
                  <label for="description" class="form-label">Açıklama/Not</label>
                  <textarea class="form-control" id="description" rows="3"></textarea>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            <button type="button" class="btn btn-primary" id="saveTimesheet">Kaydet</button>
          </div>
        </div>
      </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>
