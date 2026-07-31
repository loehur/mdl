<?php
$hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulanIndo = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tanggalAbsen = $hariIndo[(int) date('w')] . ', ' . date('j') . ' ' . $bulanIndo[(int) date('n')] . ' ' . date('Y');
$kodeCabang = strtoupper((string) ($this->dCabang['kode_cabang'] ?? ''));
$feeJagaMalam = (int) ($data['fee_jaga_malam'] ?? 14000);
$feeCuci = (int) ($data['fee_cuci'] ?? 65000);
?>
<div id="absen-root">
  <style>
    #absen-root {
      --abs-ink: #0f172a;
      --abs-muted: #1e293b;
      --abs-line: #94a3b8;
      --abs-blue: #2563eb;
      --abs-blue-deep: #1d4ed8;
      --abs-green: #16a34a;
      --abs-green-deep: #15803d;
      --abs-yellow: #f59e0b;
      --abs-yellow-deep: #d97706;
      --abs-red: #dc2626;
      --abs-radius: 0;
      --abs-border: 1px;
      max-width: 1100px;
      width: 100%;
      margin: 8px 0 24px;
      font-family: 'fontku', 'Segoe UI', sans-serif;
    }
    #absen-root .absen-layout {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
      align-items: start;
    }
    #absen-root .absen-shell {
      min-width: 0;
      background:
        radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.12), transparent 50%),
        radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.12), transparent 45%),
        linear-gradient(180deg, #eef4ff 0%, #f4fff8 55%, #fff8eb 100%);
      border: 1px solid #cbd5e1;
      padding: 14px;
    }
    #absen-root #load.absen-lists {
      display: grid;
      grid-template-columns: 1fr;
      gap: 10px;
      min-width: 0;
      margin-top: 0;
    }
    /* Layar lebar: Form | Daftar (Hari Ini & Kemarin tetap 1 kolom, bertumpuk) */
    @media (min-width: 960px) {
      #absen-root .absen-layout {
        grid-template-columns: minmax(340px, 420px) minmax(0, 1fr);
      }
    }
    #absen-root,
    #absen-root .btn,
    #absen-root button,
    #absen-root input,
    #absen-root select,
    #absen-root .selectize-input,
    #absen-root .selectize-dropdown,
    #absen-root .absen-chip,
    #absen-root .absen-opt__face,
    #absen-root .absen-modal__panel {
      border-radius: 0 !important;
    }
    #absen-root .absen-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin: -14px -14px 14px;
      padding: 14px 16px;
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
      color: #fff;
    }
    #absen-root .absen-head h2 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0,0,0,.18);
    }
    #absen-root .absen-head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 750;
      opacity: 0.95;
    }
    #absen-root .absen-cabang {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 52px;
      padding: 8px 10px;
      background: rgba(255,255,255,.2);
      color: #fff;
      font-weight: 900;
      font-size: 0.95rem;
      letter-spacing: 0.06em;
    }
    #absen-root .absen-datetime {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 12px 14px;
      margin-bottom: 14px;
      border: 1px solid #93c5fd;
      background: linear-gradient(180deg, #eff6ff, #fff);
      color: var(--abs-ink);
      font-size: 0.88rem;
      font-weight: 800;
    }
    #absen-root .absen-tanggal,
    #absen-root .absen-jam {
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 0;
    }
    #absen-root .absen-jam {
      color: var(--abs-red);
      font-variant-numeric: tabular-nums;
      flex-shrink: 0;
    }
    #absen-root .absen-tanggal i { color: var(--abs-blue); }
    #absen-root .absen-jam i { color: var(--abs-red); }
    #absen-root .absen-field { margin-bottom: 14px; }
    #absen-root .absen-field:last-child { margin-bottom: 0; }
    #absen-root .absen-label {
      display: block;
      margin: 0 0 6px;
      font-size: 0.78rem;
      font-weight: 900;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--abs-muted);
    }
    #absen-root .absen-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    /* Selectize: satu border saja */
    #absen-root select.tize,
    #absen-root select.selectized {
      border: 0 !important;
      box-shadow: none !important;
      background: transparent !important;
      padding: 0 !important;
    }
    #absen-root .selectize-control,
    #absen-root .selectize-control.single {
      border: 0 !important;
      box-shadow: none !important;
      background: transparent !important;
      margin: 0;
    }
    #absen-root .selectize-control.single .selectize-input {
      border: 1px solid var(--abs-line) !important;
      border-radius: 0 !important;
      min-height: 42px;
      padding: 10px 12px !important;
      box-shadow: none !important;
      background: #fff !important;
      font-weight: 800;
      color: var(--abs-ink);
    }
    #absen-root .selectize-control.single .selectize-input.focus {
      border-color: var(--abs-blue) !important;
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22) !important;
    }
    #absen-root .selectize-control.single .selectize-input:after {
      border: 0 !important;
    }
    #absen-root .selectize-dropdown {
      border: 1px solid var(--abs-line) !important;
      border-radius: 0 !important;
      z-index: 30 !important;
    }
    #absen-root .absen-opt-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }
    #absen-root .absen-opt {
      position: relative;
      margin: 0;
      cursor: pointer;
    }
    #absen-root .absen-opt input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }
    #absen-root .absen-opt__face {
      display: flex;
      align-items: center;
      gap: 8px;
      min-height: 48px;
      padding: 10px 12px;
      border: 1px solid #cbd5e1;
      background: #f8fafc;
      color: var(--abs-ink);
      font-weight: 800;
      font-size: 0.84rem;
      opacity: 0.72;
      transition: border-color .12s ease, background .12s ease, opacity .12s ease, box-shadow .12s ease;
    }
    #absen-root .absen-opt__face::after {
      content: "";
      width: 14px;
      height: 14px;
      margin-left: auto;
      border: 1px solid #cbd5e1;
      background: #fff;
      flex-shrink: 0;
    }
    #absen-root .absen-opt__icon {
      width: 28px;
      height: 28px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #cbd5e1;
      color: #475569;
      flex-shrink: 0;
    }
    #absen-root .absen-opt__text {
      display: flex;
      flex-direction: column;
      gap: 2px;
      min-width: 0;
      flex: 1;
    }
    #absen-root .absen-opt__title {
      font-weight: 800;
      font-size: 0.84rem;
      line-height: 1.15;
    }
    #absen-root .absen-opt__fee {
      font-size: 0.72rem;
      font-weight: 800;
      color: #1d4ed8;
      font-variant-numeric: tabular-nums;
      letter-spacing: 0.01em;
    }
    #absen-root .absen-opt input:checked + .absen-opt__face {
      opacity: 1;
      border-width: 2px;
    }
    #absen-root .absen-opt[data-tone="green"] input:checked + .absen-opt__face {
      border-color: var(--abs-green);
      background: linear-gradient(180deg, #f0fdf4, #fff);
      box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.18);
    }
    #absen-root .absen-opt[data-tone="green"] input:checked + .absen-opt__face .absen-opt__icon {
      background: var(--abs-green);
      color: #fff;
    }
    #absen-root .absen-opt[data-tone="green"] input:checked + .absen-opt__face::after {
      border: 0;
      background: var(--abs-green);
      box-shadow: inset 0 0 0 2px #fff;
    }
    #absen-root .absen-opt[data-tone="blue"] input:checked + .absen-opt__face {
      border-color: var(--abs-blue);
      background: linear-gradient(180deg, #eff6ff, #fff);
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.18);
    }
    #absen-root .absen-opt[data-tone="blue"] input:checked + .absen-opt__face .absen-opt__icon {
      background: var(--abs-blue);
      color: #fff;
    }
    #absen-root .absen-opt[data-tone="blue"] input:checked + .absen-opt__face::after {
      border: 0;
      background: var(--abs-blue);
      box-shadow: inset 0 0 0 2px #fff;
    }
    #absen-root .absen-opt[data-tone="yellow"] input:checked + .absen-opt__face {
      border-color: var(--abs-yellow-deep);
      background: linear-gradient(180deg, #fffbeb, #fff);
      box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
    }
    #absen-root .absen-opt[data-tone="yellow"] input:checked + .absen-opt__face .absen-opt__icon {
      background: var(--abs-yellow);
      color: #111;
    }
    #absen-root .absen-opt[data-tone="yellow"] input:checked + .absen-opt__face::after {
      border: 0;
      background: var(--abs-yellow-deep);
      box-shadow: inset 0 0 0 2px #fff;
    }
    #absen-root .absen-opt[data-tone="red"] input:checked + .absen-opt__face {
      border-color: var(--abs-red);
      background: linear-gradient(180deg, #fef2f2, #fff);
      box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.18);
    }
    #absen-root .absen-opt[data-tone="red"] input:checked + .absen-opt__face .absen-opt__icon {
      background: var(--abs-red);
      color: #fff;
    }
    #absen-root .absen-opt[data-tone="red"] input:checked + .absen-opt__face::after {
      border: 0;
      background: var(--abs-red);
      box-shadow: inset 0 0 0 2px #fff;
    }
    #absen-root .absen-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 12px 14px;
      border: 1px solid transparent;
      background: linear-gradient(180deg, var(--abs-green), var(--abs-green-deep));
      color: #fff;
      font-size: 0.95rem;
      font-weight: 900;
      cursor: pointer;
    }
    #absen-root .absen-btn:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }
    #absen-root .absen-btn .kode-cabang {
      font-weight: 900;
      letter-spacing: 0.06em;
    }
    #absen-root .absen-alert {
      margin: 0 0 12px;
      padding: 10px 12px;
      border: 1px solid #fca5a5;
      background: linear-gradient(180deg, #fef2f2, #fff);
      color: #b91c1c;
      font-size: 0.84rem;
      font-weight: 750;
    }
    #absen-root .absen-alert.is-ok {
      border-color: #86efac;
      background: linear-gradient(180deg, #f0fdf4, #fff);
      color: #15803d;
    }
    /* Confirm modal */
    #absen-root .absen-modal {
      position: fixed;
      inset: 0;
      z-index: 5200;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    #absen-root .absen-modal.is-open { display: flex; }
    #absen-root .absen-modal__backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.58);
      backdrop-filter: blur(3px);
      cursor: pointer;
    }
    #absen-root .absen-modal__panel {
      position: relative;
      z-index: 1;
      width: min(400px, 100%);
      background: #fff;
      box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
      overflow: hidden;
      animation: absenModalIn .18s ease-out;
    }
    @keyframes absenModalIn {
      from { opacity: 0; transform: translateY(10px) scale(0.98); }
      to { opacity: 1; transform: none; }
    }
    #absen-root .absen-modal__head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 14px 16px;
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
      color: #fff;
    }
    #absen-root .absen-modal__head h3 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
    }
    #absen-root .absen-modal__close {
      width: 34px;
      height: 34px;
      border: 0;
      background: rgba(255,255,255,.2);
      color: #fff;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    #absen-root .absen-modal__body {
      padding: 14px 16px;
      background: linear-gradient(180deg, #eff6ff, #fff);
      color: var(--abs-ink);
      font-weight: 750;
      font-size: 0.88rem;
    }
    #absen-root .absen-modal__body p {
      margin: 0 0 12px;
      color: #475569;
      font-weight: 700;
      font-size: 0.82rem;
    }
    #absen-root .absen-summary {
      border: 1px solid #cbd5e1;
      background: #fff;
    }
    #absen-root .absen-summary div {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 10px 12px;
      border-bottom: 1px solid #e2e8f0;
      font-size: 0.84rem;
    }
    #absen-root .absen-summary div:last-child { border-bottom: 0; }
    #absen-root .absen-summary span { color: #64748b; font-weight: 750; }
    #absen-root .absen-summary strong { color: var(--abs-ink); font-weight: 900; text-align: right; }
    #absen-root .absen-chip {
      display: inline-block;
      padding: 3px 8px;
      font-size: 0.72rem;
      font-weight: 900;
      letter-spacing: 0.04em;
    }
    #absen-root .absen-chip--green {
      background: #16a34a;
      color: #fff;
    }
    #absen-root .absen-chip--yellow {
      background: #f59e0b;
      color: #111;
    }
    #absen-root .absen-modal__foot {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      padding: 12px 16px;
      background: #fff;
      border-top: 1px solid #e2e8f0;
    }
    #absen-root .absen-btn-ghost {
      padding: 10px 14px;
      border: 1px solid #cbd5e1;
      background: #e2e8f0;
      color: var(--abs-ink);
      font-weight: 900;
      cursor: pointer;
    }
    #absen-root .absen-btn-primary {
      padding: 10px 14px;
      border: 1px solid transparent;
      background: linear-gradient(180deg, var(--abs-green), var(--abs-green-deep));
      color: #fff;
      font-weight: 900;
      cursor: pointer;
    }
    #absen-root .absen-btn-danger {
      padding: 10px 14px;
      border: 1px solid transparent;
      background: linear-gradient(180deg, #dc2626, #b91c1c);
      color: #fff;
      font-weight: 900;
      cursor: pointer;
    }
    #absen-root .absen-btn-danger:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }
    #absen-root .absen-modal__head--red {
      background: linear-gradient(105deg, #b91c1c 0%, #dc2626 100%);
    }
    #absen-root .absen-modal__warn {
      margin: 0 0 12px;
      padding: 10px 12px;
      border: 1px solid #fca5a5;
      background: linear-gradient(180deg, #fef2f2, #fff);
      color: #b91c1c;
      font-size: 0.82rem;
      font-weight: 750;
    }
    @media (max-width: 520px) {
      #absen-root .absen-row,
      #absen-root .absen-opt-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <div class="absen-layout">
  <div class="absen-shell">
    <div class="absen-head">
      <div>
        <h2>Absen Harian</h2>
        <small>Catat kehadiran karyawan outlet</small>
      </div>
      <span class="absen-cabang"><?= htmlspecialchars($kodeCabang) ?></span>
    </div>

    <form id="absenForm" method="POST" action="<?= URL::BASE_URL ?>Absen/absen" autocomplete="off">
      <div class="absen-datetime">
        <div class="absen-tanggal">
          <i class="far fa-calendar-alt"></i>
          <span><?= htmlspecialchars($tanggalAbsen) ?></span>
        </div>
        <div class="absen-jam">
          <i class="far fa-clock"></i>
          <span id="absen-jam"><?= date('H:i:s') ?></span>
        </div>
      </div>

      <div id="info"></div>

      <div class="absen-field">
        <label class="absen-label" for="absenKaryawan">Karyawan</label>
        <select name="karyawan" id="absenKaryawan" class="tize" style="width: 100%;" required>
          <option value="" selected disabled></option>
          <optgroup label="MDL <?= htmlspecialchars($kodeCabang) ?>">
            <?php foreach ($this->user as $a) { ?>
              <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
            <?php } ?>
          </optgroup>
          <?php if (count($this->userCabang) > 0) { ?>
            <optgroup label="Cabang Lain">
              <?php foreach ($this->userCabang as $a) { ?>
                <option value="<?= (int) $a['id_user'] ?>"><?= (int) $a['id_user'] . "-" . strtoupper($a['nama_user']) ?></option>
              <?php } ?>
            </optgroup>
          <?php } ?>
        </select>
      </div>

      <div class="absen-field">
        <span class="absen-label">Tugas</span>
        <div class="absen-opt-grid">
          <label class="absen-opt" data-tone="green">
            <input type="radio" name="jenis" value="0" required>
            <span class="absen-opt__face">
              <span class="absen-opt__icon"><i class="fas fa-tshirt"></i></span>
              <span class="absen-opt__text">
                <span class="absen-opt__title">Cuci</span>
                <span class="absen-opt__fee">Rp<?= number_format($feeCuci) ?> / cuci</span>
              </span>
            </span>
          </label>
          <label class="absen-opt" data-tone="blue">
            <input type="radio" name="jenis" value="1">
            <span class="absen-opt__face">
              <span class="absen-opt__icon"><i class="fas fa-moon"></i></span>
              <span class="absen-opt__text">
                <span class="absen-opt__title">Jaga Malam</span>
                <span class="absen-opt__fee">Rp<?= number_format($feeJagaMalam) ?> / malam</span>
              </span>
            </span>
          </label>
          <label class="absen-opt" data-tone="yellow">
            <input type="radio" name="jenis" value="2">
            <span class="absen-opt__face"><span class="absen-opt__icon"><i class="fas fa-motorcycle"></i></span>Delivery</span>
          </label>
          <label class="absen-opt" data-tone="red">
            <input type="radio" name="jenis" value="3">
            <span class="absen-opt__face"><span class="absen-opt__icon"><i class="fas fa-tools"></i></span>Maintenance</span>
          </label>
        </div>
      </div>

      <div class="absen-field">
        <span class="absen-label">Tanggal</span>
        <div class="absen-opt-grid">
          <label class="absen-opt" data-tone="green">
            <input type="radio" name="tgl" value="0" checked required>
            <span class="absen-opt__face"><span class="absen-opt__icon"><i class="fas fa-calendar-day"></i></span>Hari ini</span>
          </label>
          <label class="absen-opt" data-tone="yellow">
            <input type="radio" name="tgl" value="1">
            <span class="absen-opt__face"><span class="absen-opt__icon"><i class="fas fa-calendar-minus"></i></span>Kemarin</span>
          </label>
        </div>
      </div>

      <div class="absen-field" style="margin-top:16px;">
        <button type="submit" class="absen-btn" id="btnSubmitAbsen">
          <i class="fas fa-user-check"></i>
          Absen — <span class="kode-cabang"><?= htmlspecialchars($kodeCabang) ?></span>
        </button>
      </div>
    </form>
  </div>

  <div id="load" class="absen-lists"></div>
  </div>

  <div class="absen-modal" id="modalKonfirmasiAbsen" aria-hidden="true">
    <div class="absen-modal__backdrop" data-absen-close></div>
    <div class="absen-modal__panel" role="dialog" aria-modal="true" aria-labelledby="modalKonfirmasiAbsenLabel">
      <div class="absen-modal__head">
        <h3 id="modalKonfirmasiAbsenLabel">Konfirmasi Absen</h3>
        <button type="button" class="absen-modal__close" data-absen-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="absen-modal__body">
        <p>Pastikan data berikut sudah benar sebelum absen:</p>
        <div class="absen-summary">
          <div><span>Nama Karyawan</span><strong id="konfirm_nama">-</strong></div>
          <div><span>Tugas</span><strong id="konfirm_tugas">-</strong></div>
          <div>
            <span>Tanggal Absen</span>
            <strong>
              <span class="absen-chip absen-chip--green" id="konfirm_tgl_badge">-</span>
              <span id="konfirm_tgl_date">-</span>
            </strong>
          </div>
        </div>
      </div>
      <div class="absen-modal__foot">
        <button type="button" class="absen-btn-ghost" data-absen-close>Batal</button>
        <button type="button" class="absen-btn-primary" id="btnKonfirmasiAbsen"><i class="fas fa-check"></i> Ya, Absen</button>
      </div>
    </div>
  </div>

  <div class="absen-modal" id="modalHapusAbsen" aria-hidden="true" data-op-static="1">
    <div class="absen-modal__backdrop" data-absen-hapus-close></div>
    <div class="absen-modal__panel" role="dialog" aria-modal="true" aria-labelledby="modalHapusAbsenLabel">
      <div class="absen-modal__head absen-modal__head--red">
        <h3 id="modalHapusAbsenLabel"><i class="fas fa-trash-alt"></i> Hapus Absen?</h3>
        <button type="button" class="absen-modal__close" data-absen-hapus-close aria-label="Tutup"><i class="fas fa-times"></i></button>
      </div>
      <div class="absen-modal__body">
        <div class="absen-modal__warn">
          <i class="fas fa-exclamation-triangle"></i>
          Data absen akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.
        </div>
        <div class="absen-summary">
          <div><span>ID</span><strong id="hapus_absen_id">-</strong></div>
          <div><span>Nama Karyawan</span><strong id="hapus_absen_nama">-</strong></div>
          <div><span>Tugas</span><strong id="hapus_absen_tugas">-</strong></div>
          <div><span>Tanggal / Jam</span><strong id="hapus_absen_waktu">-</strong></div>
        </div>
      </div>
      <div class="absen-modal__foot">
        <button type="button" class="absen-btn-ghost" data-absen-hapus-close>Batal</button>
        <button type="button" class="absen-btn-danger" id="btnKonfirmasiHapusAbsen">
          <i class="fas fa-trash-alt"></i> Hapus
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script>
(function () {
  var tglAbsenInfo = {
    '0': { label: 'HARI INI', date: '<?= date('Y-m-d') ?>', chip: 'absen-chip--green' },
    '1': { label: 'KEMARIN', date: '<?= date('Y-m-d', strtotime('-1 day')) ?>', chip: 'absen-chip--yellow' }
  };
  var tugasLabel = { '0': 'Cuci', '1': 'Jaga Malam', '2': 'Delivery', '3': 'Maintenance' };
  var $modal = $('#modalKonfirmasiAbsen');
  var $modalHapus = $('#modalHapusAbsen');
  var hapusAbsenId = 0;

  function syncBodyLock() {
    if ($('.absen-modal.is-open').length) {
      document.body.classList.add('op-modal-open');
    } else {
      document.body.classList.remove('op-modal-open');
    }
  }
  function openConfirm() {
    $modal.addClass('is-open').attr('aria-hidden', 'false');
    syncBodyLock();
  }
  function closeConfirm() {
    $modal.removeClass('is-open').attr('aria-hidden', 'true');
    syncBodyLock();
  }
  function openHapus() {
    $modalHapus.addClass('is-open').attr('aria-hidden', 'false');
    syncBodyLock();
  }
  function closeHapus() {
    $modalHapus.removeClass('is-open').attr('aria-hidden', 'true');
    hapusAbsenId = 0;
    syncBodyLock();
  }

  $(document).ready(function () {
    $('#absen-root select.tize').selectize();
    $('div#load').load('<?= URL::BASE_URL ?>Absen/load');
  });

  function showInfo(msg, ok) {
    var cls = ok ? 'absen-alert is-ok' : 'absen-alert';
    $('#info').html('<div class="' + cls + '">' + msg + '</div>').show();
  }

  function submitAbsen() {
    var $form = $('#absenForm');
    var $btn = $('#btnSubmitAbsen');
    var $confirm = $('#btnKonfirmasiAbsen');
    var oldBtn = $btn.html();
    var oldConfirm = $confirm.html();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    $confirm.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $('.loaderDiv').fadeIn('fast');

    $.ajax({
      url: $form.attr('action'),
      data: $form.serialize(),
      type: $form.attr('method'),
      success: function (res) {
        try {
          var data = JSON.parse(res);
          if (data.code == 0) {
            showInfo(data.msg, false);
          } else if (data.code == 1) {
            showInfo(data.msg, true);
            $('div#load').load('<?= URL::BASE_URL ?>Absen/load');
          }
        } catch (e) {
          showInfo(res, false);
        }
      },
      error: function () {
        showInfo('Gagal mengirim absen. Coba lagi.', false);
      },
      complete: function () {
        $('.loaderDiv').fadeOut('slow');
        $btn.prop('disabled', false).html(oldBtn);
        $confirm.prop('disabled', false).html(oldConfirm);
      }
    });
  }

  $('#absenForm').on('submit', function (e) {
    e.preventDefault();
    var karyawan = $('select[name=karyawan]').val();
    var jenis = $('input[name=jenis]:checked').val();
    var tgl = $('input[name=tgl]:checked').val();

    if (!karyawan || jenis === undefined || jenis === null || tgl === undefined || tgl === null || tgl === '') {
      showInfo('Lengkapi Karyawan, Tugas, dan Tanggal terlebih dahulu', false);
      return;
    }

    var namaKaryawan = '';
    var sel = document.querySelector('#absenKaryawan');
    if (sel && sel.selectize) {
      var item = sel.selectize.options[karyawan];
      namaKaryawan = item ? item.text : karyawan;
    } else {
      namaKaryawan = $('select[name=karyawan] option[value="' + karyawan + '"]').text().trim();
    }

    var infoTgl = tglAbsenInfo[tgl];
    $('#konfirm_nama').text(namaKaryawan);
    $('#konfirm_tugas').text(tugasLabel[jenis] || '-');
    $('#konfirm_tgl_badge').attr('class', 'absen-chip ' + infoTgl.chip).text(infoTgl.label);
    $('#konfirm_tgl_date').text('(' + infoTgl.date + ')');
    openConfirm();
  });

  $(document).on('click', '#absen-root [data-absen-close]', function (e) {
    e.preventDefault();
    closeConfirm();
  });

  $(document).on('click', '#absen-root [data-absen-hapus-close]', function (e) {
    e.preventDefault();
    closeHapus();
  });

  $('#btnKonfirmasiAbsen').on('click', function () {
    closeConfirm();
    submitAbsen();
  });

  $(document).on('click', '#absen-root .btnHapusAbsen', function (e) {
    e.preventDefault();
    var $btn = $(this);
    hapusAbsenId = parseInt($btn.attr('data-id'), 10) || 0;
    if (!hapusAbsenId) return;
    $('#hapus_absen_id').text('#' + hapusAbsenId);
    $('#hapus_absen_nama').text($btn.attr('data-nama') || '-');
    $('#hapus_absen_tugas').text($btn.attr('data-tugas') || '-');
    $('#hapus_absen_waktu').text(($btn.attr('data-tanggal') || '-') + ' / ' + ($btn.attr('data-jam') || '-'));
    openHapus();
  });

  $('#btnKonfirmasiHapusAbsen').on('click', function () {
    if (!hapusAbsenId) return;
    var $btn = $(this);
    var oldHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menghapus...');
    $('.loaderDiv').fadeIn('fast');

    $.ajax({
      url: '<?= URL::BASE_URL ?>Absen/hapus',
      type: 'POST',
      dataType: 'json',
      data: { id: hapusAbsenId },
      success: function (res) {
        closeHapus();
        if (res && res.code == 1) {
          showInfo(res.msg || 'Absen dihapus', true);
          $('div#load').load('<?= URL::BASE_URL ?>Absen/load');
        } else {
          showInfo((res && res.msg) || 'Gagal menghapus absen', false);
        }
      },
      error: function () {
        showInfo('Gagal menghapus absen. Coba lagi.', false);
      },
      complete: function () {
        $('.loaderDiv').fadeOut('slow');
        $btn.prop('disabled', false).html(oldHtml);
      }
    });
  });

  $(document).on('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if ($modalHapus.hasClass('is-open')) {
      closeHapus();
      return;
    }
    if ($modal.hasClass('is-open')) {
      closeConfirm();
    }
  });

  (function () {
    var serverOffset = <?= (int) time() ?> - Math.floor(Date.now() / 1000);
    function pad(n) { return n < 10 ? '0' + n : n; }
    function tickJam() {
      var d = new Date((Math.floor(Date.now() / 1000) + serverOffset) * 1000);
      $('#absen-jam').text(pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()));
    }
    tickJam();
    setInterval(tickJam, 1000);
  })();
})();
</script>
