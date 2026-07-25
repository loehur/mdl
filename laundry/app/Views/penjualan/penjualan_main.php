<style>
  #ord-root {
    --ord-ink: #0f172a;
    --ord-muted: #1e293b;
    --ord-line: #cbd5e1;
    --ord-card: #ffffff;
    --ord-blue: #2563eb;
    --ord-blue-deep: #1d4ed8;
    --ord-green: #16a34a;
    --ord-green-deep: #15803d;
    --ord-yellow: #f59e0b;
    --ord-yellow-deep: #d97706;
    --ord-red: #dc2626;
    --ord-red-deep: #b91c1c;
    --ord-radius: 0;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    font-size: 13.5px;
    color: var(--ord-ink);
    padding: 14px 14px 22px;
    background: transparent;
    min-height: 100%;
  }
  #ord-root * { box-sizing: border-box; }
  #ord-root .btn,
  #ord-root button,
  #ord-root input,
  #ord-root select,
  #ord-root textarea,
  #ord-root .form-control,
  #ord-root .selectize-input,
  #ord-root .selectize-dropdown,
  #ord-root .badge,
  #ord-root .modal-content,
  .ord-plg-modal__panel,
  .ord-order-modal__panel,
  .ord-item-modal__panel {
    border-radius: 0 !important;
  }

  #ord-root .ord-card {
    background: var(--ord-card);
    border: 1px solid #e2e8f0;
    border-radius: var(--ord-radius);
    padding: 14px;
    margin-bottom: 12px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
  }
  #ord-root .ord-card--blue { border-color: #93c5fd; background: linear-gradient(180deg, #eff6ff, #fff); }
  #ord-root .ord-card--green { border-color: #86efac; background: linear-gradient(180deg, #f0fdf4, #fff); }
  #ord-root .ord-card--yellow { border-color: #fcd34d; background: linear-gradient(180deg, #fffbeb, #fff); }
  #ord-root .ord-card--red { border-color: #fca5a5; background: linear-gradient(180deg, #fef2f2, #fff); }

  #ord-root .ord-card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 12px;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: var(--ord-ink);
  }
  #ord-root .ord-card-title i {
    width: 30px;
    height: 30px;
    border-radius: 0;
    display: grid;
    place-items: center;
    font-size: 0.85rem;
    color: #fff;
  }
  #ord-root .ord-card-title i.is-blue { background: var(--ord-blue); }
  #ord-root .ord-card-title i.is-green { background: var(--ord-green); }
  #ord-root .ord-card-title i.is-yellow { background: var(--ord-yellow); color: #111; }
  #ord-root .ord-card-title i.is-red { background: var(--ord-red); }

  #ord-root .ord-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  #ord-root .ord-field label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--ord-muted);
  }
  #ord-root .ord-link {
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0;
    text-transform: none;
    color: var(--ord-yellow-deep);
    cursor: pointer;
  }
  #ord-root .ord-link:hover { color: var(--ord-red-deep); }

  #ord-root .selectize-input {
    border: 1px solid #94a3b8 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    min-height: 42px;
    padding: 8px 12px !important;
    font-size: 0.92rem !important;
    font-weight: 800 !important;
    color: var(--ord-ink) !important;
    background: #fff !important;
  }
  #ord-root .selectize-input.focus {
    border-color: var(--ord-blue) !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22) !important;
  }
  #ord-root .selectize-dropdown {
    border: 1px solid #94a3b8 !important;
    border-radius: 0 !important;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.16) !important;
  }
  #ord-root .selectize-dropdown .option {
    font-weight: 700;
    color: var(--ord-ink);
  }

  #ord-root .ord-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    border: 0;
    border-radius: 0;
    padding: 12px 14px;
    font-family: inherit;
    font-size: 0.95rem;
    font-weight: 900;
    cursor: pointer;
    transition: transform .12s ease, filter .12s ease;
  }
  #ord-root .ord-btn:active { transform: scale(0.98); }
  #ord-root .ord-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    filter: grayscale(0.2);
  }
  #ord-root .ord-btn--primary {
    margin-top: 12px;
    background: linear-gradient(135deg, var(--ord-green-deep), var(--ord-green));
    color: #fff;
    box-shadow: 0 10px 22px rgba(22, 163, 74, 0.32);
  }
  #ord-root .ord-btn--primary:hover:not(:disabled) {
    filter: brightness(1.05);
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
    gap: 7px;
    min-height: 86px;
    padding: 10px 6px;
    border: 1px solid #cbd5e1;
    border-radius: 0;
    background: #fff;
    color: var(--ord-ink);
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 900;
    cursor: pointer;
    transition: border-color .15s ease, background .15s ease, transform .12s ease, box-shadow .15s ease;
  }
  #ord-root .ord-svc-btn i {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border-radius: 0;
    font-size: 1rem;
    color: #fff;
  }
  #ord-root .ord-svc-btn[data-id_penjualan='1'] {
    border-color: #4ade80;
    background: linear-gradient(180deg, #dcfce7, #fff);
  }
  #ord-root .ord-svc-btn[data-id_penjualan='1'] i { background: var(--ord-green); }
  #ord-root .ord-svc-btn[data-id_penjualan='2'] {
    border-color: #60a5fa;
    background: linear-gradient(180deg, #dbeafe, #fff);
  }
  #ord-root .ord-svc-btn[data-id_penjualan='2'] i { background: var(--ord-blue); }
  #ord-root .ord-svc-btn[data-id_penjualan='3'] {
    border-color: #fbbf24;
    background: linear-gradient(180deg, #fef3c7, #fff);
  }
  #ord-root .ord-svc-btn[data-id_penjualan='3'] i { background: var(--ord-yellow); color: #111; }
  #ord-root .ord-svc-btn[data-id_penjualan='4'] {
    border-color: #f87171;
    background: linear-gradient(180deg, #fee2e2, #fff);
  }
  #ord-root .ord-svc-btn[data-id_penjualan='4'] i { background: var(--ord-red); }
  #ord-root .ord-svc-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 18px rgba(15, 23, 42, 0.12);
  }
  #ord-root .ord-svc-btn:active { transform: scale(0.97); }

  #ord-root #sering:empty::before {
    content: 'Pilih pelanggan untuk melihat layanan favorit.';
    display: block;
    color: var(--ord-muted);
    font-size: 0.84rem;
    font-weight: 700;
  }
  #ord-root #sering .ord-sering-item,
  #ord-root #sering > div {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 9px 0;
    border-bottom: 1px dashed #cbd5e1;
    font-size: 0.84rem;
    font-weight: 750;
    color: var(--ord-ink);
    white-space: normal !important;
  }
  #ord-root #sering > div:last-child { border-bottom: 0; }
  #ord-root #sering a.border,
  #ord-root #sering .pilih-sering a {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border: 0 !important;
    border-radius: 0 !important;
    background: var(--ord-blue);
    color: #fff !important;
    font-size: 0.72rem;
    font-weight: 900;
    text-decoration: none;
  }
  #ord-root #sering .pilih-sering a:hover {
    background: var(--ord-blue-deep);
  }

  #ord-root #saldoMember:empty { display: none; }
  #ord-root #saldoMember { margin-bottom: 10px; }

  #ord-root .ord-cart-wrap {
    border-radius: var(--ord-radius);
    border: 1px solid #fbbf24;
    background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);
    padding: 12px;
    box-shadow: 0 10px 24px rgba(217, 119, 6, 0.12);
  }
  #ord-root .ord-cart-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
  }
  #ord-root .ord-cart-head strong {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: var(--ord-ink);
  }
  #ord-root .ord-cart-head strong i {
    width: 30px;
    height: 30px;
    border-radius: 0;
    display: grid;
    place-items: center;
    background: var(--ord-yellow);
    color: #111;
    font-size: 0.85rem;
  }
  #ord-root #cart {
    position: relative;
    max-height: 220px;
    overflow-y: auto;
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
  #ord-root #cart::-webkit-scrollbar { display: none; }
  #ord-root #cart.is-loading {
    pointer-events: none;
    min-height: 96px;
  }
  #ord-root #cart.is-loading::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 2;
    background: rgba(255, 247, 237, 0.82);
  }
  #ord-root .ord-cart-loading {
    position: absolute;
    z-index: 3;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border: 1px solid #f59e0b;
    background: #fff;
    color: #0f172a;
    font-size: 0.84rem;
    font-weight: 900;
    white-space: nowrap;
    box-shadow: 0 8px 18px rgba(217, 119, 6, 0.18);
  }
  #ord-root .ord-cart-loading i {
    color: #d97706;
  }

  #ord-root .ord-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0 12px;
    align-items: start;
  }
  #ord-root .ord-col { min-width: 0; }

  @media (min-width: 640px) {
    #ord-root .ord-layout { grid-template-columns: 1fr 1fr; }
    #ord-root #cart { max-height: min(55vh, 420px); }
    #ord-root .ord-cart-wrap { position: sticky; top: 8px; }
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
        <div class="ord-card ord-card--blue">
          <p class="ord-card-title"><i class="fas fa-user-edit is-blue"></i> Data Order</p>
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
        <div class="ord-card ord-card--green">
          <p class="ord-card-title"><i class="fas fa-plus-square is-green"></i> Tambah Item</p>
          <form id="main">
            <div class="ord-svc">
              <button type="button" data-id_penjualan="1" class="ord-svc-btn orderPenjualanForm">
                <i class="fas fa-weight"></i>
                Kiloan
              </button>
              <button type="button" data-id_penjualan="2" class="ord-svc-btn orderPenjualanForm">
                <i class="fas fa-tshirt"></i>
                Satuan
              </button>
              <button type="button" data-id_penjualan="3" class="ord-svc-btn orderPenjualanForm">
                <i class="fas fa-ruler-combined"></i>
                Bidang
              </button>
              <button type="button" data-id_penjualan="4" class="ord-svc-btn orderPenjualanForm">
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
      <div class="ord-card ord-card--red">
        <p class="ord-card-title"><i class="fas fa-fire is-red"></i> Layanan Paling Sering</p>
        <div id="sering"></div>
      </div>

      <div class="ord-cart-wrap">
        <div class="ord-cart-head">
          <strong><i class="fas fa-shopping-basket"></i> Keranjang</strong>
        </div>
        <div id="cart"></div>
      </div>
    </div>
  </div>
</div>

<div class="ord-order-modal" id="ordOrderModal" aria-hidden="true">
  <div class="ord-order-modal__backdrop" data-ord-order-close></div>
  <div class="ord-order-modal__panel" role="dialog" aria-modal="true">
    <div class="orderPenjualanForm"></div>
  </div>
</div>

<style>
  .ord-order-modal {
    position: fixed;
    inset: 0;
    z-index: 5050;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
  }
  .ord-order-modal.is-open { display: flex; }
  .ord-order-modal__backdrop {
    position: absolute;
    inset: 0;
    z-index: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(3px);
  }
  .ord-order-modal__panel {
    position: relative;
    z-index: 1;
    width: min(520px, 100%);
    max-height: min(90vh, 720px);
    background: #fff;
    border-radius: 0;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
    overflow: hidden;
    animation: ordOrderIn .18s ease-out;
    pointer-events: auto;
  }
  @keyframes ordOrderIn {
    from { opacity: 0; transform: translateY(10px) scale(0.98); }
    to { opacity: 1; transform: none; }
  }
</style>

<div class="ord-item-modal" id="ordItemModal" aria-hidden="true">
  <div class="ord-item-modal__backdrop" data-ord-item-close></div>
  <div class="ord-item-modal__panel" role="dialog" aria-modal="true" aria-labelledby="ordItemTitle">
    <div class="addItemForm"></div>
  </div>
</div>

<style>
  .ord-item-modal {
    position: fixed;
    inset: 0;
    z-index: 5100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
  }
  .ord-item-modal.is-open { display: flex; }
  .ord-item-modal__backdrop {
    position: absolute;
    inset: 0;
    z-index: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(3px);
  }
  .ord-item-modal__panel {
    position: relative;
    z-index: 1;
    width: min(420px, 100%);
    max-height: min(90vh, 640px);
    background: #fff;
    border-radius: 0;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
    overflow: visible;
    animation: ordItemIn .18s ease-out;
    pointer-events: auto;
  }
  @keyframes ordItemIn {
    from { opacity: 0; transform: translateY(10px) scale(0.98); }
    to { opacity: 1; transform: none; }
  }
</style>

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
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(3px);
  }
  .ord-plg-modal__panel {
    position: relative;
    z-index: 1;
    width: min(400px, 100%);
    background: #fff;
    border-radius: 0;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
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
    background: linear-gradient(105deg, #d97706, #f59e0b 55%, #dc2626);
    color: #fff;
  }
  .ord-plg-modal__head h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 900;
    letter-spacing: -0.02em;
    font-family: 'fontku', sans-serif;
  }
  .ord-plg-modal__head small {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    font-weight: 750;
    opacity: 0.95;
  }
  .ord-plg-modal__close {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 0;
    background: rgba(255,255,255,.2);
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .ord-plg-modal__close:hover { background: rgba(255,255,255,.32); }
  .ord-plg-modal__body {
    padding: 14px 16px 16px;
    background: linear-gradient(180deg, #fffbeb, #fff);
  }
  .ord-plg-label {
    display: block;
    margin: 0 0 5px;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #0f172a;
  }
  .ord-plg-input {
    width: 100%;
    margin-bottom: 10px;
    padding: 10px 12px;
    border: 1px solid #94a3b8;
    border-radius: 0;
    background: #fff;
    font-family: 'fontku', sans-serif;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    outline: none;
  }
  .ord-plg-input:focus {
    border-color: #f59e0b;
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25);
  }
  .ord-plg-msg {
    margin: 0 0 10px;
    padding: 8px 10px;
    border-radius: 0;
    font-size: 12px;
    font-weight: 800;
    background: #fee2e2;
    border: 1px solid #f87171;
    color: #b91c1c;
  }
  .ord-plg-msg.is-hidden { display: none; }
  .ord-plg-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    border: 0;
    border-radius: 0;
    padding: 12px 14px;
    background: linear-gradient(135deg, #15803d, #16a34a);
    color: #fff;
    font-family: 'fontku', sans-serif;
    font-size: 14px;
    font-weight: 900;
    cursor: pointer;
    box-shadow: 0 8px 18px rgba(22, 163, 74, 0.3);
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

    window.setOrdCartLoading = function (on) {
      var $cart = $("#cart");
      if (!$cart.length) return;
      $cart.toggleClass("is-loading", !!on);
      if (on) {
        if (!$cart.children(".ord-cart-loading").length) {
          $cart.append(
            '<div class="ord-cart-loading"><i class="fas fa-spinner fa-spin"></i> Memuat keranjang…</div>'
          );
        }
      } else {
        $cart.children(".ord-cart-loading").remove();
      }
    };
    window.reloadOrdCart = function () {
      window.setOrdCartLoading(true);
      $("#cart").load("<?= URL::BASE_URL ?>Penjualan/cart", function () {
        window.setOrdCartLoading(false);
      });
    };

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
      var id_penjualan = $(this).attr('data-id_penjualan');
      if (typeof window.openOrdItemModal === "function") {
        window.openOrdItemModal();
        $('div.addItemForm').html('<div style="padding:28px;text-align:center;color:#1e293b;font-weight:800"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>');
      }
      $('div.addItemForm').load('<?= URL::BASE_URL ?>Penjualan/addItemForm/' + id_penjualan);
    });

    $("span.addPelanggan, .addPelanggan").on("click", function(e) {
      e.preventDefault();
      window.openOrdPlgModal();
    });

    $(document).off("click.ordPlgClose", "[data-ord-plg-close]").on("click.ordPlgClose", "[data-ord-plg-close]", function () {
      window.closeOrdPlgModal();
    });
    $(document).off("keydown.ordPlgEsc").on("keydown.ordPlgEsc", function (e) {
      if (e.key === "Escape" && $("#ordPlgModal").hasClass("is-open")) {
        window.closeOrdPlgModal();
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
          window.closeOrdPlgModal();
          $btn.prop("disabled", false);
        },
        error: function () {
          $msg.removeClass("is-hidden").text("Gagal menambah pelanggan");
          $btn.prop("disabled", false);
        }
      });
    });

    window.openOrdOrderModal = function () {
      // Tetap di DOM offcanvas agar focus trap Bootstrap tidak mengunci input
      $("#ordOrderModal").addClass("is-open").attr("aria-hidden", "false");
    };
    window.closeOrdOrderModal = function () {
      $("#ordOrderModal").removeClass("is-open").attr("aria-hidden", "true");
    };

    window.openOrdPlgModal = function () {
      $("#ordPlgMsg").addClass("is-hidden").text("");
      $("#ordPlgForm")[0].reset();
      $("#ordPlgModal").addClass("is-open").attr("aria-hidden", "false");
      setTimeout(function () { $("#ordPlgHp").focus(); }, 50);
    };
    window.closeOrdPlgModal = function () {
      $("#ordPlgModal").removeClass("is-open").attr("aria-hidden", "true");
    };

    window.openOrdItemModal = function () {
      // Lepas focus trap offcanvas agar ketikan di Selectize tidak digagalkan
      document.querySelectorAll(".offcanvas.show").forEach(function (el) {
        if (typeof bootstrap === "undefined" || !bootstrap.Offcanvas) return;
        var inst = bootstrap.Offcanvas.getInstance(el);
        if (inst && inst._focustrap && typeof inst._focustrap.deactivate === "function") {
          inst._focustrap.deactivate();
          el.setAttribute("data-ord-item-trap", "1");
        }
      });
      $("#ordItemModal").addClass("is-open").attr("aria-hidden", "false");
    };
    window.closeOrdItemModal = function () {
      var $sel = $("#ord_item_select");
      if ($sel.length && $sel[0].selectize) {
        try { $sel[0].selectize.destroy(); } catch (err) {}
      }
      $("#ordItemModal").removeClass("is-open").attr("aria-hidden", "true");
      $("div.addItemForm").empty();
      // Aktifkan kembali focus trap offcanvas
      document.querySelectorAll('.offcanvas.show[data-ord-item-trap="1"]').forEach(function (el) {
        el.removeAttribute("data-ord-item-trap");
        if (typeof bootstrap === "undefined" || !bootstrap.Offcanvas) return;
        var inst = bootstrap.Offcanvas.getInstance(el);
        if (inst && inst._focustrap && typeof inst._focustrap.activate === "function") {
          inst._focustrap.activate();
        }
      });
    };

    $(document).off("click.ordItemCloseShell", "#ordItemModal [data-ord-item-close]").on("click.ordItemCloseShell", "#ordItemModal [data-ord-item-close]", function (e) {
      e.preventDefault();
      window.closeOrdItemModal();
    });
    $(document).off("keydown.ordItemEsc").on("keydown.ordItemEsc", function (e) {
      if (e.key === "Escape" && $("#ordItemModal").hasClass("is-open")) {
        window.closeOrdItemModal();
      }
    });

    $(document).off("click.ordOrderClose", "[data-ord-order-close]").on("click.ordOrderClose", "[data-ord-order-close]", function () {
      window.closeOrdOrderModal();
    });
    $(document).off("keydown.ordOrderEsc").on("keydown.ordOrderEsc", function (e) {
      if (e.key === "Escape" && $("#ordOrderModal").hasClass("is-open")) {
        window.closeOrdOrderModal();
      }
    });

    $("button.orderPenjualanForm").on("click", function(e) {
      var id_penjualan = $(this).attr('data-id_penjualan');
      var id_harga = 0;
      var saldo = 0;
      window.openOrdOrderModal();
      $('div.orderPenjualanForm').html('<div style="padding:28px;text-align:center;color:#5a6a7c"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>');
      $('div.orderPenjualanForm').load('<?= URL::BASE_URL ?>Penjualan/orderPenjualanForm/' + id_penjualan + '/' + id_harga + '/' + saldo);
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
