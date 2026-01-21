<div class="row p-2 m-1">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>Data Karyawan
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-sm table-hover table-striped mb-0">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Panggilan</th>
                                <th>WA Number</th>
                                <th>Nama Lengkap</th>
                                <th>Bank/E-Wallet</th>
                                <th>Nama Bank/E-Wallet</th>
                                <th>Nama Pemilik</th>
                                <th>No. Rekening/E-Wallet</th>
                                <th class="text-center" style="width: 60px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 0;
                            foreach ($data['karyawan'] as $row) {
                                $no++;
                                $id = $row['id_user'];
                                $panggilan = $row['nama_user'] ?? '';
                                $wa_number = $row['no_user'] ?? '';
                                $nama_lengkap = $row['nama_lengkap'] ?? '';
                                $bank_code = $row['bank_code'] ?? '';
                                $bank_name = $row['bank_name'] ?? '';
                                $bank_account_name = $row['bank_account_name'] ?? '';
                                $bank_account_number = $row['bank_account_number'] ?? '';
                            ?>
                                <tr>
                                    <td class="text-center"><?= $no ?></td>
                                    <td><?= $panggilan ?: '<span class="text-muted">-</span>' ?></td>
                                    <td><?= $wa_number ?: '<span class="text-muted">-</span>' ?></td>
                                    <td><?= $nama_lengkap ?: '<span class="text-muted">-</span>' ?></td>
                                    <td><?= strtoupper($bank_code) ?: '<span class="text-muted">-</span>' ?></td>
                                    <td><?= $bank_name ?: '<span class="text-muted">-</span>' ?></td>
                                    <td><?= strtoupper($bank_account_name) ?: '<span class="text-muted">-</span>' ?></td>
                                    <td><?= $bank_account_number ?: '<span class="text-muted">-</span>' ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit" 
                                            data-id="<?= $id ?>"
                                            data-panggilan="<?= htmlspecialchars($panggilan) ?>"
                                            data-wa="<?= htmlspecialchars($wa_number) ?>"
                                            data-nama="<?= htmlspecialchars($nama_lengkap) ?>"
                                            data-bank-code="<?= htmlspecialchars($bank_code) ?>"
                                            data-bank-name="<?= htmlspecialchars($bank_name) ?>"
                                            data-bank-account-name="<?= htmlspecialchars($bank_account_name) ?>"
                                            data-bank-account-number="<?= htmlspecialchars($bank_account_number) ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                            
                            <?php if (count($data['karyawan']) == 0) { ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle me-2"></i>Tidak ada data karyawan
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
                    Klik tombol <i class="fas fa-edit"></i> untuk mengedit data karyawan
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Karyawan -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Edit Data Karyawan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_id">
                
                <!-- Info Karyawan -->
                <div class="alert alert-info mb-3">
                    <strong id="info_panggilan"></strong>
                </div>

                <!-- Step Indicator -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between">
                        <div class="step-item active" id="step1-indicator">
                            <div class="step-circle">1</div>
                            <small>Input Data</small>
                        </div>
                        <div class="step-line"></div>
                        <div class="step-item" id="step2-indicator">
                            <div class="step-circle">2</div>
                            <small>Verifikasi WA</small>
                        </div>
                        <div class="step-line"></div>
                        <div class="step-item" id="step3-indicator">
                            <div class="step-circle">3</div>
                            <small>Verifikasi Bank</small>
                        </div>
                        <div class="step-line"></div>
                        <div class="step-item" id="step4-indicator">
                            <div class="step-circle">4</div>
                            <small>Selesai</small>
                        </div>
                    </div>
                </div>

                <!-- Step 1: Input Data -->
                <div id="step1" class="step-content">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nomor WhatsApp</label>
                            <div class="input-group">
                                <span class="input-group-text">+62</span>
                                <input type="text" class="form-control bg-light" id="edit_wa" readonly>
                            </div>
                            <small class="text-muted"><i class="fas fa-lock me-1"></i>Tidak dapat diubah, digunakan untuk verifikasi OTP</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="edit_nama_lengkap" placeholder="Nama Lengkap">
                        </div>
                        <div class="col-12"><hr></div>
                        <div class="col-md-4">
                            <label class="form-label">Kode Bank/E-Wallet <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_bank_code">
                                <option value="">Memuat daftar bank...</option>
                            </select>
                            <small class="text-muted" id="bank_status"></small>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">No Rekening/E-Wallet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_bank_account_number" placeholder="1234567890 atau No HP E-Wallet">
                        </div>
                    </div>
                </div>

                <!-- Step 2: Verifikasi WhatsApp OTP -->
                <div id="step2" class="step-content" style="display: none;">
                    <div class="text-center mb-4">
                        <i class="fab fa-whatsapp text-success" style="font-size: 4rem;"></i>
                        <h5 class="mt-2">Verifikasi Nomor WhatsApp</h5>
                        <p class="text-muted">Kode OTP telah dikirim ke nomor <strong id="otp_wa_display"></strong></p>
                    </div>
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="input-group input-group-lg">
                                <input type="text" class="form-control text-center" id="otp_code" 
                                    placeholder="_ _ _ _ _ _" maxlength="6" style="letter-spacing: 10px; font-weight: bold;">
                            </div>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-link btn-sm" id="btn_resend_otp" disabled>
                                    Kirim ulang OTP (<span id="otp_countdown">60</span>s)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="step3" class="step-content" style="display: none;">
                    <div class="text-center mb-4">
                        <i class="fas fa-university text-primary" style="font-size: 4rem;"></i>
                        <h5 class="mt-2">Verifikasi Rekening Bank/E-Wallet</h5>
                        <p class="text-muted">Memverifikasi data rekening/e-wallet...</p>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div id="bank_verify_loading" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2">Sedang memverifikasi rekening...</p>
                            </div>
                            <div id="bank_verify_result" style="display: none;">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <td width="40%">Bank/E-Wallet</td>
                                        <td><strong id="result_bank_name"></strong></td>
                                    </tr>
                                    <tr>
                                        <td>No. Rekening/E-Wallet</td>
                                        <td><strong id="result_account_number"></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Nama Pemilik</td>
                                        <td><strong id="result_account_holder" class="text-success"></strong></td>
                                    </tr>
                                </table>
                            </div>
                            <div id="bank_verify_error" class="alert alert-danger" style="display: none;"></div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Konfirmasi & Simpan -->
                <div id="step4" class="step-content" style="display: none;">
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        <h5 class="mt-2">Konfirmasi Data</h5>
                        <p class="text-muted">Pastikan semua data sudah benar sebelum menyimpan</p>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td width="40%">WhatsApp</td>
                                    <td><strong id="confirm_wa"></strong> <i class="fas fa-check-circle text-success"></i></td>
                                </tr>
                                <tr>
                                    <td>Nama Lengkap</td>
                                    <td><strong id="confirm_nama"></strong></td>
                                </tr>
                                <tr>
                                    <td>Bank/E-Wallet</td>
                                    <td><strong id="confirm_bank"></strong></td>
                                </tr>
                                <tr>
                                    <td>No. Rekening/E-Wallet</td>
                                    <td><strong id="confirm_rekening"></strong></td>
                                </tr>
                                <tr>
                                    <td>Nama Pemilik Rekening</td>
                                    <td><strong id="confirm_pemilik" class="text-success"></strong> <i class="fas fa-check-circle text-success"></i></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-outline-primary" id="btn_prev" style="display: none;">
                    <i class="fas fa-arrow-left me-1"></i>Kembali
                </button>
                <button type="button" class="btn btn-primary" id="btn_next">
                    Lanjut<i class="fas fa-arrow-right ms-1"></i>
                </button>
                <button type="button" class="btn btn-success" id="btn_save" style="display: none;">
                    <i class="fas fa-save me-1"></i>Simpan Data
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .sticky-top {
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .step-item {
        text-align: center;
        flex: 1;
    }
    .step-circle {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: #e9ecef;
        color: #6c757d;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 5px;
        font-weight: bold;
    }
    .step-item.active .step-circle {
        background: #0d6efd;
        color: white;
    }
    .step-item.completed .step-circle {
        background: #198754;
        color: white;
    }
    .step-line {
        flex: 1;
        height: 2px;
        background: #e9ecef;
        margin-top: 17px;
    }
    .btn-edit {
        padding: 2px 8px;
    }
    
    /* Selectize custom styling */
    .selectize-input {
        min-height: 38px;
        padding: 6px 12px;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        font-size: 1rem;
    }
    .selectize-input.focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    .selectize-dropdown {
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .selectize-input input {
        font-size: 1rem;
    }
    .selectize-control.single .selectize-input:after {
        border-color: #495057 transparent transparent transparent;
    }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/css/selectize.bootstrap5.min.css">

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
<script src="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/js/selectize.min.js"></script>

<script>
$(document).ready(function() {
    var currentStep = 1;
    var maxStep = 4;
    var otpCountdown = null;
    var verifiedData = {};
    var bankList = []; // Store bank list from API
    var banksLoaded = false;
    var bankSelectize = null; // Selectize instance
    
    // Load bank list from API on page load
    loadBankList();
    
    function loadBankList() {
        $('#bank_status').html('<i class="fas fa-spinner fa-spin"></i> Memuat daftar bank...');
        
        $.ajax({
            url: 'https://api.nalju.com/Flip/banks',
            method: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status && res.data) {
                    bankList = res.data;
                    banksLoaded = true;
                    
                    // Prepare options for selectize
                    var options = [];
                    
                    // Check if data is array or object
                    if (Array.isArray(res.data)) {
                        res.data.forEach(function(bank) {
                            var code = bank.bank_code || bank.code;
                            var name = bank.name || bank.bank_name || code.toUpperCase();
                            options.push({ value: code, text: name });
                        });
                    } else {
                        // Object format
                        $.each(res.data, function(code, bank) {
                            var name = (typeof bank === 'object') ? (bank.name || bank.bank_name) : bank;
                            options.push({ value: code, text: name });
                        });
                    }
                    
                    // Initialize selectize
                    initBankSelectize(options);
                    
                    $('#bank_status').html('<i class="fas fa-check text-success"></i> ' + options.length + ' bank tersedia');
                    setTimeout(function() { $('#bank_status').text(''); }, 3000);
                } else {
                    $('#bank_status').html('<span class="text-danger"><i class="fas fa-exclamation-circle"></i> Gagal memuat bank</span>');
                    loadFallbackBanks();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading banks:', {
                    status: status,
                    error: error,
                    statusCode: xhr.status,
                    responseText: xhr.responseText
                });
                
                var errorMsg = 'Error: ' + error;
                if (xhr.status === 0) {
                    errorMsg = 'Tidak dapat terhubung ke server (CORS/Network issue)';
                } else if (xhr.status === 404) {
                    errorMsg = 'Endpoint tidak ditemukan (404)';
                } else if (xhr.status >= 500) {
                    errorMsg = 'Server error (' + xhr.status + ')';
                } else if (xhr.responseText) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        errorMsg = resp.message || errorMsg;
                    } catch(e) {}
                }
                
                $('#bank_status').html('<span class="text-danger"><i class="fas fa-exclamation-circle"></i> ' + errorMsg + '</span>');
                loadFallbackBanks();
            }
        });
    }
    
    function initBankSelectize(options) {
        // Destroy existing selectize if any
        if (bankSelectize) {
            bankSelectize.destroy();
        }
        
        // Initialize selectize with options
        var $select = $('#edit_bank_code').selectize({
            options: options,
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            placeholder: 'Ketik untuk mencari bank...',
            create: false,
            sortField: 'text',
            maxItems: 1,
            allowEmptyOption: true,
            onInitialize: function() {
                // Add empty option
                this.addOption({value: '', text: 'Pilih Bank/E-Wallet'});
            }
        });
        
        bankSelectize = $select[0].selectize;
    }
    
    // Fallback bank list if API fails
    function loadFallbackBanks() {
        var fallbackBanks = [
            {value: 'bca', text: 'Bank BCA'},
            {value: 'bni', text: 'Bank BNI'},
            {value: 'bri', text: 'Bank BRI'},
            {value: 'mandiri', text: 'Bank Mandiri'},
            {value: 'bsi', text: 'Bank Syariah Indonesia'},
            {value: 'cimb', text: 'CIMB Niaga'},
            {value: 'permata', text: 'Bank Permata'},
            {value: 'danamon', text: 'Bank Danamon'},
            {value: 'gopay', text: 'GoPay'},
            {value: 'ovo', text: 'OVO'},
            {value: 'dana', text: 'DANA'},
            {value: 'shopeepay', text: 'ShopeePay'}
        ];
        
        // Initialize selectize with fallback banks
        initBankSelectize(fallbackBanks);
        
        banksLoaded = true;
    }
    
    // Open modal
    $('.btn-edit').click(function() {
        var $btn = $(this);
        currentStep = 1;
        verifiedData = {};
        
        // Populate form
        $('#edit_id').val($btn.data('id'));
        $('#info_panggilan').text($btn.data('panggilan'));
        $('#edit_wa').val($btn.data('wa'));
        $('#edit_nama_lengkap').val($btn.data('nama'));
        $('#edit_bank_account_number').val($btn.data('bank-account-number'));
        
        // Set bank code after banks are loaded
        var savedBankCode = $btn.data('bank-code');
        if (banksLoaded && bankSelectize) {
            bankSelectize.setValue(savedBankCode, true);
        } else {
            // Wait for banks to load then set value
            var checkBanks = setInterval(function() {
                if (banksLoaded && bankSelectize) {
                    bankSelectize.setValue(savedBankCode, true);
                    clearInterval(checkBanks);
                }
            }, 100);
        }
        
        // Reset steps
        updateStepUI();
        
        var modal = new bootstrap.Modal(document.getElementById('modalEdit'));
        modal.show();
    });
    
    // Next button
    $('#btn_next').click(function() {
        if (currentStep === 1) {
            // Validate step 1
            var wa = $('#edit_wa').val().trim();
            var bankCode = $('#edit_bank_code').val();
            var bankAccount = $('#edit_bank_account_number').val().trim();
            
            if (!wa) {
                alert('Nomor WhatsApp wajib diisi');
                return;
            }
            if (!bankCode) {
                alert('Pilih bank terlebih dahulu');
                return;
            }
            if (!bankAccount) {
                alert('Nomor rekening wajib diisi');
                return;
            }
            
            // Send OTP
            sendOTP(wa);
        } else if (currentStep === 2) {
            // Verify OTP
            verifyOTP();
        } else if (currentStep === 3) {
            // Already verified, go to step 4
            currentStep = 4;
            updateStepUI();
        }
    });
    
    // Previous button
    $('#btn_prev').click(function() {
        if (currentStep > 1) {
            currentStep--;
            updateStepUI();
        }
    });
    
    // Save button
    $('#btn_save').click(function() {
        saveData();
    });
    
    function updateStepUI() {
        // Hide all steps
        $('.step-content').hide();
        $('#step' + currentStep).show();
        
        // Update indicators
        for (var i = 1; i <= maxStep; i++) {
            var $ind = $('#step' + i + '-indicator');
            $ind.removeClass('active completed');
            if (i < currentStep) {
                $ind.addClass('completed');
            } else if (i === currentStep) {
                $ind.addClass('active');
            }
        }
        
        // Update buttons
        $('#btn_prev').toggle(currentStep > 1 && currentStep < 4);
        $('#btn_next').toggle(currentStep < 4);
        $('#btn_save').toggle(currentStep === 4);
    }
    
    function sendOTP(wa) {
        $('#btn_next').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Mengirim OTP...');
        
        $.ajax({
            url: '<?= URL::BASE_URL ?>Karyawan/sendOTP',
            method: 'POST',
            data: { wa: wa, id: $('#edit_id').val() },
            dataType: 'json',
            success: function(res) {
                if (res.status) {
                    currentStep = 2;
                    updateStepUI();
                    $('#otp_wa_display').text('+62' + wa);
                    startOTPCountdown();
                } else {
                    // Tampilkan error message lengkap
                    var errorMsg = res.message || 'Gagal mengirim OTP';
                    if (res.api_response) {
                        console.log('API Response:', res.api_response);
                        errorMsg += '\n\nDetail: ' + JSON.stringify(res.api_response, null, 2);
                    }
                    alert(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Terjadi kesalahan saat mengirim OTP';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {}
                alert(errorMsg);
            },
            complete: function() {
                $('#btn_next').prop('disabled', false).html('Lanjut<i class="fas fa-arrow-right ms-1"></i>');
            }
        });
    }
    
    function verifyOTP() {
        var otp = $('#otp_code').val().trim();
        if (!otp || otp.length !== 6) {
            alert('Masukkan 6 digit kode OTP');
            return;
        }
        
        $('#btn_next').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Memverifikasi...');
        
        $.ajax({
            url: '<?= URL::BASE_URL ?>Karyawan/verifyOTP',
            method: 'POST',
            data: { otp: otp, id: $('#edit_id').val() },
            dataType: 'json',
            success: function(res) {
                if (res.status) {
                    verifiedData.wa = $('#edit_wa').val().trim();
                    currentStep = 3;
                    updateStepUI();
                    verifyBank();
                } else {
                    alert(res.message || 'Kode OTP tidak valid');
                }
            },
            error: function() {
                alert('Terjadi kesalahan saat verifikasi OTP');
            },
            complete: function() {
                $('#btn_next').prop('disabled', false).html('Lanjut<i class="fas fa-arrow-right ms-1"></i>');
            }
        });
    }
    
    function verifyBank() {
        var bankCode = $('#edit_bank_code').val();
        var accountNumber = $('#edit_bank_account_number').val().trim();
        
        $('#bank_verify_loading').show();
        $('#bank_verify_result').hide();
        $('#bank_verify_error').hide();
        $('#btn_next').prop('disabled', true);
        
        $.ajax({
            url: '<?= URL::BASE_URL ?>Karyawan/verifyBank',
            method: 'POST',
            data: { 
                bank_code: bankCode, 
                account_number: accountNumber,
                id: $('#edit_id').val()
            },
            dataType: 'json',
            success: function(res) {
                $('#bank_verify_loading').hide();
                
                if (res.status) {
                    verifiedData.bank_code = bankCode;
                    verifiedData.bank_name = res.data.bank_name || $('#edit_bank_code option:selected').text();
                    verifiedData.bank_account_number = accountNumber;
                    verifiedData.bank_account_name = res.data.account_holder;
                    
                    $('#result_bank_name').text(verifiedData.bank_name);
                    $('#result_account_number').text(accountNumber);
                    $('#result_account_holder').text(res.data.account_holder);
                    $('#bank_verify_result').show();
                    $('#btn_next').prop('disabled', false);
                    
                    // Prepare confirmation
                    $('#confirm_wa').text('+62' + verifiedData.wa);
                    $('#confirm_nama').text($('#edit_nama_lengkap').val() || '-');
                    $('#confirm_bank').text(verifiedData.bank_name);
                    $('#confirm_rekening').text(accountNumber);
                    $('#confirm_pemilik').text(res.data.account_holder);
                    
                    verifiedData.nama_lengkap = $('#edit_nama_lengkap').val();
                } else {
                    $('#bank_verify_error').text(res.message || 'Gagal memverifikasi rekening').show();
                }
            },
            error: function() {
                $('#bank_verify_loading').hide();
                $('#bank_verify_error').text('Terjadi kesalahan saat verifikasi rekening').show();
            }
        });
    }
    
    function saveData() {
        $('#btn_save').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...');
        
        $.ajax({
            url: '<?= URL::BASE_URL ?>Karyawan/save',
            method: 'POST',
            data: {
                id: $('#edit_id').val(),
                nama_lengkap: verifiedData.nama_lengkap,
                bank_code: verifiedData.bank_code,
                bank_name: verifiedData.bank_name,
                bank_account_name: verifiedData.bank_account_name,
                bank_account_number: verifiedData.bank_account_number
            },
            dataType: 'json',
            success: function(res) {
                if (res.status) {
                    alert('Data berhasil disimpan!');
                    location.reload();
                } else {
                    alert(res.message || 'Gagal menyimpan data');
                }
            },
            error: function() {
                alert('Terjadi kesalahan saat menyimpan data');
            },
            complete: function() {
                $('#btn_save').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Simpan Data');
            }
        });
    }
    
    function startOTPCountdown() {
        var seconds = 60;
        $('#otp_countdown').text(seconds);
        $('#btn_resend_otp').prop('disabled', true);
        
        if (otpCountdown) clearInterval(otpCountdown);
        
        otpCountdown = setInterval(function() {
            seconds--;
            $('#otp_countdown').text(seconds);
            
            if (seconds <= 0) {
                clearInterval(otpCountdown);
                $('#btn_resend_otp').prop('disabled', false).html('Kirim ulang OTP');
            }
        }, 1000);
    }
    
    // Resend OTP
    $('#btn_resend_otp').click(function() {
        sendOTP($('#edit_wa').val().trim());
    });
    
    // Auto format OTP input
    $('#otp_code').on('input', function() {
        $(this).val($(this).val().replace(/[^0-9]/g, ''));
    });
});
</script>
