<div class="content mt-3">
    <div class="container-fluid">
        <div class="row g-4">
            <!-- Card Set Harga -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h6 class="text-uppercase text-muted mb-3" style="letter-spacing: 1px; font-size: 12px;">Set Harga</h6>
                        <div class="d-flex gap-2">
                            <select class="form-select def_price" id="defPrice" data-mode="def_price">
                                <option value="0" <?= ($this->mdl_setting['def_price'] == 0) ? "selected" : "" ?>>Set A</option>
                                <option value="1" <?= ($this->mdl_setting['def_price'] == 1) ? "selected" : "" ?>>Set B</option>
                            </select>
                            <button type="button" class="btn btn-dark px-4" id="btnSavePrice">Save</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Card Salin Gaji -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h6 class="text-uppercase text-muted mb-3" style="letter-spacing: 1px; font-size: 12px;">Salin Pengaturan Gaji</h6>
                        <form id="formSalinGaji" action="<?= URL::BASE_URL ?>Setting/salin_gaji" method="POST">
                            <div class="mb-3">
                                <label class="form-label small text-muted">Dari</label>
                                <select name="sumber" class="form-select searchable" required>
                                    <option value="" selected disabled>-- Pilih Sumber --</option>
                                    <?php foreach ($this->user as $a) { ?>
                                        <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . " - " . strtoupper($a['nama_user']) ?></option>
                                    <?php } ?>
                                    <?php if (count($this->userCabang) > 0) { ?>
                                        <?php foreach ($this->userCabang as $a) { ?>
                                            <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . " - " . strtoupper($a['nama_user']) ?></option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">Ke</label>
                                <select name="target" class="form-select searchable" required>
                                    <option value="" selected disabled>-- Pilih Target --</option>
                                    <?php foreach ($this->user as $a) { ?>
                                        <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . " - " . strtoupper($a['nama_user']) ?></option>
                                    <?php } ?>
                                    <?php if (count($this->userCabang) > 0) { ?>
                                        <?php foreach ($this->userCabang as $a) { ?>
                                            <option value="<?= $a['id_user'] ?>"><?= $a['id_user'] . " - " . strtoupper($a['nama_user']) ?></option>
                                        <?php } ?>
                                    <?php } ?>
                                    <option value="0">00 - ALL</option>
                                </select>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">*Tidak termasuk tunjangan</small>
                                <button type="submit" class="btn btn-dark btn-sm px-4">Salin</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= URL::EX_ASSETS ?>plugins/select2/select2.min.js"></script>

<style>
    .select2-container--default .select2-selection--single {
        height: 38px;
        padding: 5px 10px;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        background-color: #fff;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 26px;
        color: #212529;
        padding-left: 0;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #6c757d;
    }
    .select2-dropdown {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 6px 10px;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #212529;
    }
    .select2-container {
        width: 100% !important;
    }
</style>

<script>
    $(document).ready(function() {
        $('select.searchable').select2({
            placeholder: '-- Pilih --',
            allowClear: false
        });
    });

    // Validasi input realtime 0-10 (Delegated & Aggressive)
    $(document).on('input change keyup blur', "#marginTop, #feedLines", function() {
        var el = $(this);
        var val = parseInt(el.val());
        
        if (isNaN(val)) return; // Biarkan kosong/sedang mengetik
        if (val < 0) {
            el.val(0);
        }
    });

    // Save Set Harga
    $("#btnSavePrice").on('click', function() {
        var btn = $(this);
        var select = $("#defPrice");
        btn.prop('disabled', true).text('...');
        
        $.ajax({
            url: '<?= URL::BASE_URL ?>Setting/updateCell',
            data: { 'value': select.val(), 'mode': select.attr('data-mode') },
            type: 'POST',
            success: function() {
                btn.prop('disabled', false).text('Saved');
                setTimeout(function() { btn.text('Save'); }, 1500);
            },
            error: function() {
                btn.prop('disabled', false).text('Save');
                alert('Gagal menyimpan');
            }
        });
    });

    // Salin Gaji
    $("#formSalinGaji").on("submit", function(e) {
        e.preventDefault();
        var btn = $(this).find('button[type=submit]');
        btn.prop('disabled', true).text('...');
        
        $.ajax({
            url: $(this).attr('action'),
            data: $(this).serialize(),
            type: $(this).attr("method"),
            success: function(res) {
                btn.prop('disabled', false).text('Salin');
                if (res.length == 0) {
                    alert("Berhasil");
                } else {
                    alert(res);
                }
            }
        });
    });
</script>