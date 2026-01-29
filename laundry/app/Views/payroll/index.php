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
                            <button type="button" class="btn btn-success" id="btnExportCSV" 
                                <?= empty($data['payrolls']) ? 'disabled' : '' ?>>
                                <i class="fas fa-file-csv me-1"></i>Export CSV Flip
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <h5 class="mb-0">
                            Total: <span class="badge bg-success">Rp <?= number_format($data['total_amount'], 0, ',', '.') ?></span>
                        </h5>
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
                                <th class="text-center" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($data['payrolls'])) {
                                $no = 1;
                                foreach ($data['payrolls'] as $p) {
                                    $statusClass = strtolower($p['state']) === 'paid' ? 'success' : 'warning';
                                    $statusText = strtoupper($p['state']);
                            ?>
                                <tr>
                                    <td class="text-center"><?= $no ?></td>
                                    <td><?= htmlspecialchars($p['employee_name']) ?></td>
                                    <td><?= $p['period'] ?></td>
                                    <td class="text-end">Rp <?= number_format($p['amount'], 0, ',', '.') ?></td>
                                    <td><?= strtoupper(htmlspecialchars($p['bank_code'])) ?></td>
                                    <td><?= htmlspecialchars($p['bank_acc_number']) ?></td>
                                    <td><?= strtoupper(htmlspecialchars($p['bank_acc_name'])) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if (strtolower($p['state']) === 'pending') { ?>
                                            <button type="button" class="btn btn-sm btn-success btn-mark-paid" 
                                                data-id="<?= $p['id'] ?>"
                                                data-name="<?= htmlspecialchars($p['employee_name']) ?>"
                                                title="Mark as Paid">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger btn-delete" 
                                                data-id="<?= $p['id'] ?>"
                                                data-name="<?= htmlspecialchars($p['employee_name']) ?>"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php } else { ?>
                                            <span class="text-muted">-</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php
                                    $no++;
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
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
                    <strong>Pending:</strong> Belum dibayar | <strong>Paid:</strong> Sudah dibayar
                </small>
            </div>
        </div>
    </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    const currentPeriod = '<?= $data['period'] ?>';

    // Bulk Add to Payroll
    $('#btnBulkAdd, #btnBulkAddEmpty').click(function() {
        if (!confirm('Tambahkan SEMUA karyawan aktif ke payroll periode ' + currentPeriod + '?')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Processing...');

        $.ajax({
            url: '<?= URL::BASE_URL ?>Payroll/add_bulk',
            method: 'POST',
            data: { date: currentPeriod },
            dataType: 'json',
            success: function(res) {
                if (res.ok) {
                    alert('✅ ' + res.msg);
                    location.reload();
                } else {
                    alert('❌ Error: ' + res.msg);
                }
            },
            error: function(xhr) {
                console.error('Error:', xhr.responseText);
                alert('❌ Terjadi kesalahan saat menambahkan ke payroll');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-users me-1"></i>Add All to Payroll');
            }
        });
    });

    // Export CSV Flip
    $('#btnExportCSV').click(function() {
        window.location.href = '<?= URL::BASE_URL ?>Payroll/export_csv_flip?period=' + currentPeriod;
    });

    // Mark as Paid
    $('.btn-mark-paid').click(function() {
        const payrollId = $(this).data('id');
        const employeeName = $(this).data('name');

        if (!confirm('Tandai payroll untuk ' + employeeName + ' sebagai PAID?')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: '<?= URL::BASE_URL ?>Payroll/mark_as_paid',
            method: 'POST',
            data: { payroll_id: payrollId },
            dataType: 'json',
            success: function(res) {
                if (res.ok) {
                    alert('✅ ' + res.msg);
                    location.reload();
                } else {
                    alert('❌ Error: ' + res.msg);
                }
            },
            error: function() {
                alert('❌ Terjadi kesalahan');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Delete Payroll
    $('.btn-delete').click(function() {
        const payrollId = $(this).data('id');
        const employeeName = $(this).data('name');

        if (!confirm('Hapus payroll untuk ' + employeeName + '?\n\nPeringatan: Data tidak dapat dikembalikan!')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: '<?= URL::BASE_URL ?>Payroll/delete',
            method: 'POST',
            data: { payroll_id: payrollId },
            dataType: 'json',
            success: function(res) {
                if (res.ok) {
                    alert('✅ ' + res.msg);
                    location.reload();
                } else {
                    alert('❌ Error: ' + res.msg);
                }
            },
            error: function() {
                alert('❌ Terjadi kesalahan');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
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
</style>
