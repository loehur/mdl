<style>
  #ord-root {
    --ord-ink: #1e3a5f;
    --ord-ink-soft: #2a4a73;
    --ord-muted: #5a6a7c;
    --ord-line: #D5DEEA;
    --ord-card: #FFFFFF;
    --ord-foam: #F4F7FB;
    --ord-accent: #3f74d4;
    --ord-accent-deep: #2f61bc;
    --ord-accent-soft: #d9e6fa;
    --ord-radius: 16px;
    font-family: 'fontku', 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
    font-size: 13px;
    color: var(--ord-ink);
    padding: 12px 12px 20px;
    background:
      radial-gradient(120% 80% at 100% -10%, rgba(63, 116, 212, 0.12), transparent 55%),
      var(--ord-foam);
    min-height: 100%;
  }
  #ord-root * { box-sizing: border-box; }

  #ord-root .ord-card {
    background: var(--ord-card);
    border: 1px solid var(--ord-line);
    border-radius: var(--ord-radius);
    padding: 12px;
    margin-bottom: 10px;
    box-shadow: 0 8px 22px rgba(36, 48, 65, 0.05);
  }
  #ord-root .ord-card-title {
    margin: 0 0 10px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--ord-muted);
  }

  #ord-root .ord-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  #ord-root .ord-field label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 5px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--ord-muted);
  }
  #ord-root .ord-link {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;
    color: var(--ord-accent);
    cursor: pointer;
  }
  #ord-root .ord-link:hover { color: var(--ord-accent-deep); }

  /* Selectize soft override inside order */
  #ord-root .selectize-input {
    border: 1px solid var(--ord-line) !important;
    border-radius: 10px !important;
    box-shadow: none !important;
    min-height: 38px;
    padding: 7px 10px !important;
    font-size: 0.88rem !important;
    font-weight: 650;
  }
  #ord-root .selectize-input.focus {
    border-color: var(--ord-accent) !important;
    box-shadow: 0 0 0 3px rgba(63, 116, 212, 0.18) !important;
  }
  #ord-root .selectize-dropdown {
    border-color: var(--ord-line) !important;
    border-radius: 10px !important;
    box-shadow: 0 10px 24px rgba(36, 48, 65, 0.12) !important;
  }

  #ord-root .ord-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    border: 0;
    border-radius: 11px;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 800;
    cursor: pointer;
    transition: transform .12s ease, opacity .12s ease, background .15s ease;
  }
  #ord-root .ord-btn:active { transform: scale(0.98); }
  #ord-root .ord-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }
  #ord-root .ord-btn--primary {
    margin-top: 10px;
    background: linear-gradient(145deg, var(--ord-accent-deep) 0%, var(--ord-accent) 100%);
    color: #fff;
    box-shadow: 0 8px 18px rgba(47, 97, 188, 0.28);
  }
  #ord-root .ord-btn--primary:hover:not(:disabled) {
    background: linear-gradient(145deg, #274f9e 0%, var(--ord-accent-deep) 100%);
  }

  #ord-root .ord-svc {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
  }
  #ord-root .ord-svc-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 72px;
    padding: 10px 6px;
    border: 1px solid var(--ord-line);
    border-radius: 14px;
    background: #fff;
    color: var(--ord-ink);
    font-family: inherit;
    font-size: 0.72rem;
    font-weight: 750;
    cursor: pointer;
    transition: border-color .15s ease, background .15s ease, transform .12s ease, box-shadow .15s ease;
  }
  #ord-root .ord-svc-btn i {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    font-size: 0.95rem;
  }
  #ord-root .ord-svc-btn[data-id_penjualan='1'] i { background: rgba(42, 157, 143, 0.14); color: #1A7A6E; }
  #ord-root .ord-svc-btn[data-id_penjualan='2'] i { background: rgba(63, 116, 212, 0.14); color: var(--ord-accent-deep); }
  #ord-root .ord-svc-btn[data-id_penjualan='3'] i { background: rgba(36, 48, 65, 0.1); color: #243041; }
  #ord-root .ord-svc-btn[data-id_penjualan='4'] i { background: rgba(231, 111, 81, 0.14); color: #C24A30; }
  #ord-root .ord-svc-btn:hover {
    border-color: rgba(63, 116, 212, 0.45);
    background: var(--ord-accent-soft);
    box-shadow: 0 6px 16px rgba(47, 97, 188, 0.12);
  }
  #ord-root .ord-svc-btn:active { transform: scale(0.97); }

  #ord-root #sering:empty::before {
    content: 'Pilih pelanggan untuk melihat layanan favorit.';
    display: block;
    color: var(--ord-muted);
    font-size: 0.8rem;
  }
  #ord-root #sering .ord-sering-item,
  #ord-root #sering > div {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px dashed var(--ord-line);
    font-size: 0.8rem;
    white-space: normal !important;
  }
  #ord-root #sering > div:last-child { border-bottom: 0; }
  #ord-root #sering a.border,
  #ord-root #sering #pilih_sering a {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border: 0 !important;
    border-radius: 999px !important;
    background: var(--ord-accent-soft);
    color: var(--ord-accent-deep);
    font-size: 0.7rem;
    font-weight: 800;
    text-decoration: none;
  }

  #ord-root #saldoMember:empty { display: none; }
  #ord-root #saldoMember { margin-bottom: 10px; }

  #ord-root .ord-cart-wrap {
    border-radius: var(--ord-radius);
    border: 1px solid rgba(63, 116, 212, 0.25);
    background: linear-gradient(180deg, #edf3fc 0%, #f7faff 100%);
    padding: 10px;
  }
  #ord-root .ord-cart-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
  }
  #ord-root .ord-cart-head strong {
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: -0.01em;
  }
  #ord-root #cart {
    max-height: 220px;
    overflow-y: auto;
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
  #ord-root #cart::-webkit-scrollbar { display: none; }

  #ord-root .ord-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0 12px;
    align-items: start;
  }
  #ord-root .ord-col {
    min-width: 0;
  }

  @media (min-width: 640px) {
    #ord-root .ord-layout {
      grid-template-columns: 1fr 1fr;
    }
    #ord-root #cart {
      max-height: min(55vh, 420px);
    }
    #ord-root .ord-cart-wrap {
      position: sticky;
      top: 8px;
    }
  }

  @media (max-width: 639px) {
    #ord-root .ord-grid-2 { grid-template-columns: 1fr; }
    #ord-root .ord-svc { grid-template-columns: repeat(2, 1fr); }
  }
</style>

<div id="ord-root">
  <div class="ord-layout">
    <div class="ord-col ord-col-main">
      <form class="orderProses" action="<?= URL::BASE_URL ?>Penjualan/proses" method="POST">
        <div class="ord-card">
          <p class="ord-card-title">Data order</p>
          <div class="ord-grid-2">
            <div class="ord-field">
              <label>
                Pelanggan
                <span class="ord-link addPelanggan" id="btnTambahPelangganOrder" role="button" tabindex="0">
                  <i class="fas fa-plus"></i> Tambah
                </span>
              </label>
              <select id="pelanggan_submit" name="f1" class="proses form-control tize" style="width: 100%;" required>
                <option value="" selected disabled></option>
                <?php foreach ($this->pelanggan as $a) { ?>
                  <option id=" <?= $a['id_pelanggan'] ?>" value="<?= $a['id_pelanggan'] ?>"><?= strtoupper($a['nama_pelanggan']) . ", " . $a['nomor_pelanggan']  ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="ord-field">
              <label>Karyawan</label>
              <select name="f2" class="form-control tize karyawan" style="width: 100%;" required>
                <option value="" selected disabled></option>
                <optgroup label="<?= $this->dCabang['nama'] ?> [<?= $this->dCabang['kode_cabang'] ?>]">
                  <?php foreach ($this->user as $a) { ?>
                    <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                  <?php } ?>
                </optgroup>
                <?php if (count($this->userCabang) > 0) { ?>
                  <optgroup label="----- Cabang Lain -----">
                    <?php foreach ($this->userCabang as $a) { ?>
                      <option id="<?= $a['id_user'] ?>" value="<?= $a['id_user'] ?>"><?= $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
                    <?php } ?>
                  </optgroup>
                <?php } ?>
              </select>
            </div>
          </div>
          <button id="proses" type="submit" class="ord-btn ord-btn--primary" disabled>
            <i class="fas fa-check-circle"></i>
            Proses Order
          </button>
        </div>
      </form>

      <div id="waitReady" class="invisible">
        <div class="ord-card">
          <p class="ord-card-title">Tambah item</p>
          <form id="main">
            <div class="ord-svc">
              <button type="button" data-id_penjualan="1" class="ord-svc-btn orderPenjualanForm" data-bs-target="#modalPenjualan">
                <i class="fas fa-weight"></i>
                Kiloan
              </button>
              <button type="button" data-id_penjualan="2" class="ord-svc-btn orderPenjualanForm" data-bs-target="#modalPenjualan">
                <i class="fas fa-tshirt"></i>
                Satuan
              </button>
              <button type="button" data-id_penjualan="3" class="ord-svc-btn orderPenjualanForm" data-bs-target="#modalPenjualan">
                <i class="fas fa-ruler-combined"></i>
                Bidang
              </button>
              <button type="button" data-id_penjualan="4" class="ord-svc-btn orderPenjualanForm" data-bs-target="#modalPenjualan">
                <i class="fas fa-cube"></i>
                Volume
              </button>
            </div>
          </form>
        </div>
      </div>

      <div id="saldoMember"></div>
    </div>

    <div class="ord-col ord-col-side">
      <div class="ord-card">
        <p class="ord-card-title">Layanan paling sering</p>
        <div id="sering"></div>
      </div>

      <div class="ord-cart-wrap">
        <div class="ord-cart-head">
          <strong><i class="fas fa-shopping-basket me-1"></i> Keranjang</strong>
        </div>
        <div id="cart"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="modalPenjualan">
  <div class="modal-dialog" role="document">
    <div class="modal-content orderPenjualanForm">
    </div>
  </div>
</div>

<div class="modal" id="exampleModal2">
  <div class="modal-dialog modal-sm">
    <div class="modal-content addItemForm">
    </div>
  </div>
</div>

<!-- Custom modal: Tambah Pelanggan (tanpa Bootstrap) -->
<div class="ord-plg-modal" id="ordPlgModal" aria-hidden="true">
  <div class="ord-plg-modal__backdrop" data-ord-plg-close></div>
  <div class="ord-plg-modal__panel" role="dialog" aria-modal="true" aria-labelledby="ordPlgTitle">
    <div class="ord-plg-modal__head">
      <div>
        <h3 id="ordPlgTitle">Tambah Pelanggan</h3>
        <small>Isi nama dan nomor HP</small>
      </div>
      <button type="button" class="ord-plg-modal__close" data-ord-plg-close aria-label="Tutup">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form id="ordPlgForm" class="ord-plg-modal__body" autocomplete="off">
      <label class="ord-plg-label" for="ordPlgHp">Nomor HP</label>
      <input type="text" id="ordPlgHp" name="f2" class="ord-plg-input" required placeholder="08…" inputmode="tel">

      <label class="ord-plg-label" for="ordPlgNama">Nama pelanggan</label>
      <input type="text" id="ordPlgNama" name="f1" class="ord-plg-input" required placeholder="Nama lengkap">

      <p class="ord-plg-msg is-hidden" id="ordPlgMsg"></p>

      <button type="submit" class="ord-plg-submit" id="ordPlgSubmit">
        <i class="fas fa-plus"></i> Simpan pelanggan
      </button>
    </form>
  </div>
</div>

<style>
  .ord-plg-modal {
    position: fixed;
    inset: 0;
    z-index: 5000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
  }
  .ord-plg-modal.is-open { display: flex; }
  .ord-plg-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(3px);
  }
  .ord-plg-modal__panel {
    position: relative;
    z-index: 1;
    width: min(400px, 100%);
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.28);
    overflow: hidden;
    animation: ordPlgIn .18s ease-out;
  }
  @keyframes ordPlgIn {
    from { opacity: 0; transform: translateY(10px) scale(0.98); }
    to { opacity: 1; transform: none; }
  }
  .ord-plg-modal__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: linear-gradient(135deg, #2f61bc, #3f74d4);
    color: #fff;
  }
  .ord-plg-modal__head h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: 0.02em;
    font-family: 'fontku', sans-serif;
  }
  .ord-plg-modal__head small {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    font-weight: 600;
    opacity: 0.85;
  }
  .ord-plg-modal__close {
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 8px;
    background: rgba(255,255,255,.15);
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .ord-plg-modal__close:hover { background: rgba(255,255,255,.28); }
  .ord-plg-modal__body {
    padding: 14px 16px 16px;
    background: #f4f7fb;
  }
  .ord-plg-label {
    display: block;
    margin: 0 0 5px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #5a6a7c;
  }
  .ord-plg-input {
    width: 100%;
    margin-bottom: 10px;
    padding: 10px 12px;
    border: 1px solid #D5DEEA;
    border-radius: 10px;
    background: #fff;
    font-family: 'fontku', sans-serif;
    font-size: 14px;
    font-weight: 650;
    color: #1e3a5f;
    outline: none;
  }
  .ord-plg-input:focus {
    border-color: #3f74d4;
    box-shadow: 0 0 0 3px rgba(63, 116, 212, 0.2);
  }
  .ord-plg-msg {
    margin: 0 0 10px;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 650;
    background: rgba(231, 111, 81, 0.12);
    color: #C24A30;
  }
  .ord-plg-msg.is-hidden { display: none; }
  .ord-plg-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    border: 0;
    border-radius: 11px;
    padding: 11px 14px;
    background: linear-gradient(145deg, #2f61bc, #3f74d4);
    color: #fff;
    font-family: 'fontku', sans-serif;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 8px 18px rgba(47, 97, 188, 0.28);
  }
  .ord-plg-submit:disabled { opacity: 0.6; cursor: not-allowed; }
</style>

<div class="modal fade" id="modalDiskonHarga" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title m-0">Atur Diskon</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formDiskonHarga">
        <div class="modal-body">
          <input type="hidden" id="diskon_id_penjualan" name="id" value="">
          <input type="hidden" id="diskon_harga_asli" value="">
          <div class="mb-2">
            <label class="form-label form-label-sm mb-1">Harga Asli / item-kg-unit</label>
            <input type="text" id="diskon_harga_asli_view" class="form-control form-control-sm" readonly>
          </div>
          <div>
            <label class="form-label form-label-sm mb-1">Harga Setelah Diskon / item-kg-unit</label>
            <input type="number" min="0" step="0.01" id="diskon_harga_input" name="harga_diskon" class="form-control form-control-sm" required>
          </div>
          <small class="text-muted">Harga asli tidak diubah, sistem hanya mengisi nilai diskon.</small>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script>
  $("form.orderProses").on("submit", function(e) {
    var pelanggan_submit = $('select#pelanggan_submit').val();
    e.preventDefault();
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr("method"),
      success: function(result) {
        window.location.href = "<?= URL::BASE_URL ?>Operasi/i/0/" + pelanggan_submit + "/0";
      },
    });
  });

  $(document).ready(function() {
    $(".orderProses .tize").selectize();
    $("div#waitReady").removeClass("invisible");
    $('div#cart').load('<?= URL::BASE_URL ?>Penjualan/cart');

    $(".removeRow").on("click", function(e) {
      e.preventDefault();
      var id_value = $(this).attr('data-id_value');
      $.ajax({
        url: "<?= URL::BASE_URL ?>Penjualan/RemoveRow",
        data: {
          'id': id_value,
        },
        type: 'POST',
        success: function(response) {
          $('tr.tr' + id_value).remove();
          location.reload(true);
        },
      });
    });

    $(".addItem").on("click", function(e) {
      e.preventDefault();
      var id_group = $(this).attr('data-id_group');
      var id_penjualan = "'" + $(this).attr('data-id_penjualan') + "'";
      var data = id_group + "|" + id_penjualan;
      $('div.addItemForm').load('<?= URL::BASE_URL ?>Penjualan/addItemForm/' + data);
    });

    $("span.addPelanggan, .addPelanggan").on("click", function(e) {
      e.preventDefault();
      openOrdPlgModal();
    });

    function openOrdPlgModal() {
      var $m = $("#ordPlgModal");
      if ($m.parent()[0] !== document.body) {
        $m.appendTo(document.body);
      }
      $("#ordPlgMsg").addClass("is-hidden").text("");
      $("#ordPlgForm")[0].reset();
      $m.addClass("is-open").attr("aria-hidden", "false");
      setTimeout(function () { $("#ordPlgHp").focus(); }, 50);
    }
    function closeOrdPlgModal() {
      $("#ordPlgModal").removeClass("is-open").attr("aria-hidden", "true");
    }

    $(document).off("click.ordPlgClose", "[data-ord-plg-close]").on("click.ordPlgClose", "[data-ord-plg-close]", function () {
      closeOrdPlgModal();
    });
    $(document).off("keydown.ordPlgEsc").on("keydown.ordPlgEsc", function (e) {
      if (e.key === "Escape" && $("#ordPlgModal").hasClass("is-open")) {
        closeOrdPlgModal();
      }
    });

    $(document).off("submit.ordPlg", "#ordPlgForm").on("submit.ordPlg", "#ordPlgForm", function (e) {
      e.preventDefault();
      var $btn = $("#ordPlgSubmit");
      var $msg = $("#ordPlgMsg");
      $msg.addClass("is-hidden").text("");
      $btn.prop("disabled", true);

      $.ajax({
        url: "<?= URL::BASE_URL ?>Penjualan/tambahPelanggan",
        type: "POST",
        dataType: "json",
        data: {
          f1: $("#ordPlgNama").val(),
          f2: $("#ordPlgHp").val()
        },
        success: function (res) {
          if (!res || !res.ok) {
            $msg.removeClass("is-hidden").text((res && res.msg) ? res.msg : "Gagal menambah pelanggan");
            $btn.prop("disabled", false);
            return;
          }

          var label = res.nama + ", " + res.hp;
          var $sel = $("select#pelanggan_submit");
          var selectize = $sel[0] && $sel[0].selectize ? $sel[0].selectize : null;
          if (selectize) {
            selectize.addOption({ value: String(res.id), text: label });
            selectize.addItem(String(res.id), true);
          } else {
            $sel.append($("<option>", { value: res.id, text: label, selected: true }));
            $sel.trigger("change");
          }

          $("#saldoMember").load("<?= URL::BASE_URL ?>Member/cekRekap/" + res.id);
          $("#sering").load("<?= URL::BASE_URL ?>Penjualan/sering/" + res.id);
          closeOrdPlgModal();
          $btn.prop("disabled", false);
        },
        error: function () {
          $msg.removeClass("is-hidden").text("Gagal menambah pelanggan");
          $btn.prop("disabled", false);
        }
      });
    });

    $("button.orderPenjualanForm").on("click", function(e) {
      var id_penjualan = $(this).attr('data-id_penjualan');
      var id_harga = 0;
      var saldo = 0;
      $('div.orderPenjualanForm').load('<?= URL::BASE_URL ?>Penjualan/orderPenjualanForm/' + id_penjualan + '/' + id_harga + '/' + saldo);

      var target = $(this).attr('data-bs-target');
      if (target) {
        var modalEl = document.querySelector(target);
        if (modalEl) {
          var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
          modal.show();
        }
      }
    });

    $("a.removeItem").on('click', function(e) {
      e.preventDefault();
      var idNya = $(this).attr('id');
      var keyNya = $(this).attr('data-key');

      $.ajax({
        url: '<?= URL::BASE_URL ?>Penjualan/removeItem',
        data: {
          'id': idNya,
          'key': keyNya
        },
        type: 'POST',
        success: function() {
          $("#item" + idNya + "" + keyNya).remove();
          location.reload(true);
        },
      });
    });
  });

  $('select.proses').on('change', function() {
    var id_pelanggan = this.value;
    if (id_pelanggan == "") {
      $("#saldoMember").html("");
      $("#sering").html("");
      return;
    }
    $("#saldoMember").load('<?= URL::BASE_URL ?>Member/cekRekap/' + id_pelanggan)
    $("#sering").load('<?= URL::BASE_URL ?>Penjualan/sering/' + id_pelanggan)
  });
</script>
