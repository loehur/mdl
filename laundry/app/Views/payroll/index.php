<div class="row p-2 m-1">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill-wave me-2"></i>Payroll Management
                </h5>
                <div class="d-flex gap-2 align-items-center">
                    <!-- Period Selector -->
                    <form method="GET" action="<?= URL::BASE_URL ?>Payroll" class="d-flex gap-2">
                        <input type="month" name="period" class="form-control form-control-sm" 
                            value="<?= $data['period'] ?>" 
                            onchange="this.form.submit()">
                    </form>
                </div>
            </div>
            <div class="card-body">
                <!-- Action Buttons -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-primary" id="btnBulkAdd">
                                <i class="fas fa-users me-1"></i>Add All to Payroll
                            </button>
                            <button type="button" class="btn btn-warning" id="btnApproveAll">
                                <i class="fas fa-check-circle me-1"></i>Approve All
                            </button>
                            <button type="button" class="btn btn-success" id="btnExportCSV" 
                                <?= empty($data['payrolls']) ? 'disabled' : '' ?>>
                                <i class="fas fa-file-csv me-1"></i>Export CSV Flip
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex flex-column align-items-end gap-1">
                            <div class="small text-muted">Total: <span class="badge bg-success">Rp <?= number_format($data['total_amount'] ?? 0, 0, ',', '.') ?></span></div>
                            <div class="small">Cash: <span class="badge bg-secondary">Rp <?= number_format($data['total_cash'] ?? 0, 0, ',', '.') ?></span></div>
                            <div class="small">Transfer: <span class="badge bg-primary">Rp <?= number_format($data['total_transfer'] ?? 0, 0, ',', '.') ?></span></div>
                        </div>
                    </div>
                </div>

                <!-- Payroll Table -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Nama Karyawan</th>
                                <th>Periode</th>
                                <th class="text-end">Jumlah</th>
                                <th>Bank</th>
                                <th>No. Rekening</th>
                                <th>Nama Pemilik</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($data['payrolls'])) {
                                $no = 1;
                                foreach ($data['payrolls'] as $p) {
                                    $state = strtolower($p['state']);
                                    // draft=secondary, approved=success
                                    $statusClass = $state === 'approved' ? 'success' : 'secondary';
                                    $statusText = strtoupper($p['state']);
                            ?>
                                <tr>
                                    <td class="text-center"><?= $no ?></td>
                                    <td><?= htmlspecialchars($p['employee_name']) ?></td>
                                    <td><?= $p['period'] ?></td>
                                    <td class="text-end">Rp <?= number_format($p['amount'], 0, ',', '.') ?></td>
                                    <td><?= !empty($p['bank_code']) ? strtoupper(htmlspecialchars($p['bank_code'])) : '<span class="badge bg-secondary">CASH</span>' ?></td>
                                    <td><?= !empty($p['bank_acc_number']) ? htmlspecialchars($p['bank_acc_number']) : '<span class="text-muted">-</span>' ?></td>
                                    <td><?= !empty($p['bank_acc_name']) ? strtoupper(htmlspecialchars($p['bank_acc_name'])) : '<span class="text-muted">-</span>' ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                                    </td>
                                </tr>
                            <?php
                                    $no++;
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle me-2"></i>Tidak ada data payroll untuk periode <?= $data['period'] ?>
                                        <br><br>
                                        <button type="button" class="btn btn-sm btn-primary" id="btnBulkAddEmpty">
                                            <i class="fas fa-plus me-1"></i>Add All to Payroll
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>Draft:</strong> Baru dibuat, masih bisa diedit | <strong>Approved:</strong> Sudah di-approve, siap export
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold">Konfirmasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p id="confirmMessage" class="mb-0 fs-5 text-muted"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary px-4" id="confirmBtn">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<!-- Secure Approve All Modal -->
<div class="modal fade" id="secureApproveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Ekstra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="mb-3 text-muted fs-6">
                    Anda akan meng-approve <strong>SEMUA</strong> payroll draft periode <span class="fw-bold text-dark current-period-text"></span>. Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="form-group mb-0">
                    <label class="small text-uppercase fw-bold text-muted mb-1">Ketik "approve" untuk melanjutkan:</label>
                    <input type="text" id="approveAllSecret" class="form-control form-control-lg text-center" placeholder="approve" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-warning px-4 fw-bold" id="confirmApproveAllBtn" disabled>Approve Sekarang</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 2000;">
    <div id="statusToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex p-1">
            <div class="toast-body fs-6 fw-medium"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    const currentPeriod = '<?= $data['period'] ?>';
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    const secureModal = new bootstrap.Modal(document.getElementById('secureApproveModal'));
    const statusToast = new bootstrap.Toast(document.getElementById('statusToast'));

    function showToast(msg, type = 'success') {
        const bg = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-info');
        $('#statusToast').removeClass('bg-success bg-danger bg-info').addClass(bg);
        $('#statusToast .toast-body').html(msg);
        statusToast.show();
    }

    function askConfirm(msg, callback) {
        $('#confirmMessage').text(msg);
        $('#confirmBtn').off('click').on('click', function() {
            confirmModal.hide();
            callback();
        });
        confirmModal.show();
    }

    // Bulk Add to Payroll
    $('#btnBulkAdd, #btnBulkAddEmpty').click(function() {
        const $btn = $(this);
        askConfirm('Tambahkan SEMUA karyawan aktif ke payroll periode ' + currentPeriod + '?', function() {
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Processing...');
            $.ajax({
                url: '<?= URL::BASE_URL ?>Payroll/add_bulk',
                method: 'POST',
                data: { date: currentPeriod },
                dataType: 'json',
                success: function(res) {
                    if (res.ok) {
                        showToast('✅ ' + res.msg);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('❌ Error: ' + res.msg, 'error');
                        $btn.prop('disabled', false).html('<i class="fas fa-users me-1"></i>Add All to Payroll');
                    }
                },
                error: function() {
                    showToast('❌ Terjadi kesalahan server', 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-users me-1"></i>Add All to Payroll');
                }
            });
        });
    });

    // Approve All Flow
    $('#btnApproveAll').click(function() {
        $('.current-period-text').text(currentPeriod);
        $('#approveAllSecret').val('');
        $('#confirmApproveAllBtn').prop('disabled', true);
        secureModal.show();
    });

    $('#approveAllSecret').on('input', function() {
        $('#confirmApproveAllBtn').prop('disabled', $(this).val().toLowerCase() !== 'approve');
    });

    $('#confirmApproveAllBtn').click(function() {
        const $btnTrigger = $('#btnApproveAll');
        const originalHtml = $btnTrigger.html();
        
        secureModal.hide();
        $btnTrigger.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Approving...');

        $.ajax({
            url: '<?= URL::BASE_URL ?>Payroll/approve_all',
            method: 'POST',
            data: { period: currentPeriod },
            dataType: 'json',
            success: function(res) {
                if (res.ok) {
                    showToast('✅ ' + res.msg);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('❌ Error: ' + res.msg, 'error');
                    $btnTrigger.prop('disabled', false).html(originalHtml);
                }
            },
            error: function() {
                showToast('❌ Terjadi kesalahan server', 'error');
                $btnTrigger.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Export CSV Flip
    $('#btnExportCSV').click(function() {
        window.location.href = '<?= URL::BASE_URL ?>Payroll/export_csv_flip?period=' + currentPeriod;
    });
});
</script>

<style>
.btn-group .btn {
    margin-right: 0;
}
.table td, .table th {
    vertical-align: middle;
}
.modal-content {
    border: none;
    border-radius: 12px;
}
.toast {
    border-radius: 10px;
}
</style>
