<?php
$rows = $data['data_main'] ?? [];
$total = is_array($rows) ? count($rows) : 0;
$canEditPartner = ((int) $this->id_privilege === 100);
$kodeCabangUi = strtoupper((string) ($this->dCabang['kode_cabang'] ?? ''));
$namaCabangUi = (string) ($this->dCabang['nama'] ?? ('MDL ' . $kodeCabangUi));
?>

<div id="plg-root">
<style>
  #plg-root {
    --plg-ink: #0f172a;
    --plg-muted: #1e293b;
    --plg-line: #94a3b8;
    --plg-blue: #2563eb;
    --plg-blue-deep: #1d4ed8;
    --plg-green: #16a34a;
    --plg-green-deep: #15803d;
    --plg-yellow: #f59e0b;
    --plg-yellow-deep: #d97706;
    --plg-red: #dc2626;
    --plg-red-deep: #b91c1c;
    --plg-radius: 0;
    --plg-border: 1px;
    max-width: 1100px;
    width: 100%;
    margin: 8px 0 24px;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    font-size: 13.5px;
    color: var(--plg-ink);
    -webkit-font-smoothing: antialiased;
  }
  #plg-root * { box-sizing: border-box; }
  #plg-root,
  #plg-root .btn,
  #plg-root button,
  #plg-root input,
  #plg-root select,
  #plg-root .plg-chip,
  #plg-root .plg-badge,
  #plg-root .plg-card,
  #plg-root .plg-panel,
  #plg-root .plg-icon {
    border-radius: 0 !important;
  }

  #plg-root .plg-shell {
    min-width: 0;
    background:
      radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.12), transparent 50%),
      radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.12), transparent 45%),
      linear-gradient(180deg, #eef4ff 0%, #f4fff8 55%, #fff8eb 100%);
    border: 1px solid #cbd5e1;
    padding: 14px;
  }
  #plg-root .plg-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: -14px -14px 14px;
    padding: 14px 16px;
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    color: #fff;
  }
  #plg-root .plg-head h2 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
  }
  #plg-root .plg-head small {
    display: block;
    margin-top: 2px;
    font-size: 0.72rem;
    font-weight: 750;
    opacity: 0.95;
  }
  #plg-root .plg-cabang {
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

  #plg-root .plg-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    align-items: start;
  }
  @media (min-width: 960px) {
    #plg-root .plg-layout {
      grid-template-columns: minmax(300px, 380px) minmax(0, 1fr);
    }
  }

  #plg-root .plg-panel {
    border: 1px solid #93c5fd;
    background: linear-gradient(180deg, #eff6ff, #fff);
    padding: 14px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
  }
  #plg-root .plg-panel--yellow {
    border-color: #fcd34d;
    background: linear-gradient(180deg, #fffbeb, #fff);
  }
  #plg-root .plg-panel-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 12px;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: var(--plg-ink);
  }
  #plg-root .plg-icon {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    font-size: 0.85rem;
    color: #fff;
    flex-shrink: 0;
  }
  #plg-root .plg-icon.is-blue { background: var(--plg-blue); }
  #plg-root .plg-icon.is-green { background: var(--plg-green); }
  #plg-root .plg-icon.is-yellow { background: var(--plg-yellow); color: #111; }

  #plg-root .plg-search-wrap {
    position: relative;
    margin-bottom: 12px;
  }
  #plg-root .plg-search-wrap i {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--plg-blue);
    font-size: 0.8rem;
    pointer-events: none;
  }
  #plg-root .plg-input {
    width: 100%;
    border: 1px solid var(--plg-line);
    background: #fff;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--plg-ink);
    outline: none;
  }
  #plg-root .plg-input--search { padding-left: 34px; }
  #plg-root .plg-input:focus {
    border-color: var(--plg-blue);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
  }
  #plg-root .plg-input::placeholder { color: #64748b; font-weight: 700; }

  #plg-root .plg-list-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 10px;
  }
  #plg-root .plg-list-head .plg-panel-title { margin: 0; }
  #plg-root .plg-count {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border: 1px solid #93c5fd;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.03em;
  }

  #plg-root .plg-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
    max-height: min(72vh, 720px);
    overflow-y: auto;
    padding-bottom: 4px;
    align-content: start;
  }
  @media (min-width: 720px) {
    #plg-root .plg-list {
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    }
  }

  #plg-root .plg-card {
    background: linear-gradient(180deg, #eff6ff, #fff);
    border: 1px solid #93c5fd;
    padding: 12px 12px 10px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    display: flex;
    flex-direction: column;
    min-width: 0;
  }
  #plg-root .plg-card:hover {
    border-color: #2563eb;
    box-shadow: 0 12px 26px rgba(37, 99, 235, 0.14);
  }
  #plg-root .plg-card.is-partner {
    border-color: #fcd34d;
    background: linear-gradient(180deg, #fffbeb, #fff);
  }
  #plg-root .plg-card-nama {
    display: block;
    max-width: 100%;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.01em;
    line-height: 1.3;
    color: var(--plg-ink);
    overflow-wrap: anywhere;
  }
  #plg-root .plg-card-meta {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) auto;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--plg-muted);
    line-height: 1.25;
    min-width: 0;
  }
  #plg-root .plg-card-meta .plg-meta-phone {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    min-width: 0;
    overflow: hidden;
  }
  #plg-root .plg-meta-phone__main,
  #plg-root .plg-meta-phone__alt {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  #plg-root .plg-meta-phone__main {
    color: #1d4ed8;
    font-weight: 900;
  }
  #plg-root .plg-meta-phone__alt {
    color: var(--plg-muted);
    font-size: 0.67rem;
    font-weight: 800;
  }
  #plg-root .plg-card-meta .plg-chip,
  #plg-root .plg-card-meta .plg-badge {
    max-width: 100%;
    overflow-wrap: anywhere;
  }
  #plg-root .plg-card-meta > .plg-badge {
    white-space: nowrap;
  }
  #plg-root .plg-card-meta > .plg-chip {
    justify-self: end;
    white-space: nowrap;
  }
  #plg-root .plg-badge {
    display: inline-flex;
    align-items: center;
    padding: 1px 6px;
    border: 1px solid #cbd5e1;
    background: #f1f5f9;
    color: #0f172a;
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.03em;
  }
  #plg-root .plg-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    flex-shrink: 0;
    padding: 2px 7px;
    border: 1px solid #cbd5e1;
    background: #f1f5f9;
    color: #0f172a;
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.02em;
    white-space: nowrap;
  }
  #plg-root .plg-chip--green {
    background: #f0fdf4;
    border-color: #86efac;
    color: #15803d;
  }
  #plg-root .plg-chip--yellow {
    background: #fffbeb;
    border-color: #fcd34d;
    color: #b45309;
  }
  #plg-root .plg-chip--blue {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
  }

  #plg-root .plg-card-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-top: auto;
    padding-top: 10px;
    border-top: 1px solid #dbeafe;
  }
  #plg-root .plg-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #15803d;
    background: linear-gradient(180deg, #16a34a, #15803d);
    color: #fff;
    padding: 6px 10px;
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.02em;
    cursor: pointer;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
  }
  #plg-root .plg-btn:hover:not(:disabled) {
    background: linear-gradient(180deg, #22c55e, #16a34a);
  }
  #plg-root .plg-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    filter: grayscale(0.2);
  }

  #plg-root .plg-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 28px 14px;
    color: var(--plg-ink);
    font-size: 0.88rem;
    font-weight: 800;
    background: linear-gradient(180deg, #eff6ff, #fff);
    border: 1px dashed #93c5fd;
  }
  #plg-root .plg-empty b {
    display: block;
    color: var(--plg-ink);
    font-size: 0.95rem;
    font-weight: 900;
    margin-bottom: 3px;
  }
  #plg-root .plg-empty.is-hidden { display: none; }

  #plg-root .ord-plg-label {
    font-size: 0.78rem;
    color: var(--plg-muted);
  }
  #plg-root .ord-plg-input:focus {
    border-color: var(--plg-blue);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
  }

  #plg-root .plg-modal {
    position: fixed;
    inset: 0;
    z-index: 5200;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    font-family: 'fontku', 'Segoe UI', sans-serif;
  }
  #plg-root .plg-modal.is-hidden { display: none; }
  #plg-root .plg-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.58);
    backdrop-filter: blur(3px);
    cursor: pointer;
  }
  #plg-root .plg-modal__box {
    position: relative;
    z-index: 1;
    width: min(440px, 100%);
    max-height: min(92vh, 900px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid #93c5fd;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
    animation: plgModalIn .18s ease-out;
    overflow: visible;
  }
  @keyframes plgModalIn {
    from { opacity: 0; transform: translateY(10px) scale(0.98); }
    to { opacity: 1; transform: none; }
  }
  #plg-root .plg-modal__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    color: #fff;
    flex-shrink: 0;
  }
  #plg-root .plg-modal__head h3 {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    font-family: inherit;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
  }
  #plg-root .plg-modal__close {
    width: 34px;
    height: 34px;
    border: 0;
    background: rgba(255,255,255,.2);
    color: inherit;
    font-size: 1.15rem;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
  }
  #plg-root .plg-modal__close:hover { background: rgba(255,255,255,.32); }
  #plg-root .plg-modal__body {
    padding: 14px 16px;
    overflow-y: auto;
    flex: 1 1 auto;
    background:
      radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.10), transparent 50%),
      radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.10), transparent 45%),
      linear-gradient(180deg, #eef4ff 0%, #f4fff8 50%, #fff8eb 100%);
    color: var(--plg-ink);
    font-weight: 750;
    font-size: 0.88rem;
  }
  #plg-root .plg-modal__foot {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px;
    background: #fff;
    border-top: 1px solid #e2e8f0;
    flex-shrink: 0;
  }
  #plg-root .plg-label {
    display: block;
    margin: 0 0 5px;
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--plg-muted);
  }
  #plg-root .plg-field { margin-bottom: 12px; }
  #plg-root .plg-field:last-child { margin-bottom: 0; }
  #plg-root .plg-alert {
    margin: 0 0 12px;
    padding: 8px 10px;
    border: 1px solid #fca5a5;
    background: linear-gradient(180deg, #fef2f2, #fff);
    color: #b91c1c;
    font-size: 0.8rem;
    font-weight: 750;
    line-height: 1.35;
  }
  #plg-root .plg-alert--warn {
    border-color: #fcd34d;
    background: linear-gradient(180deg, #fffbeb, #fff);
    color: #92400e;
  }
  #plg-root .plg-alert--ok {
    border-color: #86efac;
    background: linear-gradient(180deg, #f0fdf4, #fff);
    color: #15803d;
  }
  #plg-root .plg-alert.is-hidden { display: none; }
  #plg-root .plg-alert-item { margin-bottom: 6px; }
  #plg-root .plg-alert-item:last-child { margin-bottom: 0; }
  #plg-root .plg-alert-item b { display: block; margin-bottom: 2px; }
  #plg-root .plg-alert-item ul {
    margin: 0;
    padding-left: 16px;
  }
  #plg-root .plg-alert-hp {
    color: #1d4ed8;
    font-weight: 800;
  }
  #plg-root .plg-alert-note {
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px dashed #fcd34d;
  }
  #plg-root .plg-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  @media (max-width: 420px) {
    #plg-root .plg-row { grid-template-columns: 1fr; }
  }
  #plg-root .plg-modal .plg-input {
    width: 100%;
    margin: 0;
    border: 1px solid #94a3b8;
    background: #fff;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 800;
    color: var(--plg-ink);
    outline: none;
  }
  #plg-root .plg-modal .plg-input:focus {
    border-color: var(--plg-blue);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
  }
  #plg-root .plg-modal .plg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 14px;
    border: 1px solid transparent;
    font-size: 0.9rem;
    font-weight: 900;
    font-family: inherit;
    letter-spacing: 0.02em;
    cursor: pointer;
    line-height: 1.2;
  }
  #plg-root .plg-modal .plg-btn:disabled { opacity: 0.55; cursor: not-allowed; }
  #plg-root .plg-modal .plg-btn--ghost {
    background: #e2e8f0;
    color: var(--plg-ink);
    border-color: #cbd5e1;
  }
  #plg-root .plg-modal .plg-btn--ghost:hover:not(:disabled) {
    background: #cbd5e1;
  }
  #plg-root .plg-modal .plg-btn--primary {
    background: linear-gradient(180deg, #16a34a, #15803d);
    color: #fff;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
  }
  #plg-root .plg-modal .plg-btn--primary:hover:not(:disabled) {
    background: linear-gradient(180deg, #22c55e, #16a34a);
  }
  #plg-root .plg-modal .plg-btn--blue {
    background: linear-gradient(180deg, #2563eb, #1d4ed8);
    color: #fff;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
  }
  #plg-root .plg-modal .plg-btn--blue:hover:not(:disabled) {
    background: linear-gradient(180deg, #3b82f6, #2563eb);
  }

  #plg-root .plg-value--nama {
    display: inline-block;
    max-width: 100%;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.01em;
    line-height: 1.3;
    color: var(--plg-ink);
  }
</style>

  <div class="plg-shell">
    <div class="plg-head">
      <div>
        <h2>Data Pelanggan</h2>
        <small><?= htmlspecialchars($namaCabangUi, ENT_QUOTES, 'UTF-8') ?></small>
      </div>
      <span class="plg-cabang"><?= htmlspecialchars($kodeCabangUi !== '' ? $kodeCabangUi : 'MDL', ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="plg-layout">
      <div class="plg-aside">
        <div class="plg-panel plg-panel--yellow">
          <div class="plg-panel-title">
            <i class="plg-icon is-yellow fas fa-user-plus"></i>
            Tambah Pelanggan
          </div>
          <div class="plg-search-wrap">
            <i class="fas fa-search"></i>
            <input
              type="text"
              id="plg-filter"
              class="plg-input plg-input--search"
              autocomplete="off"
              placeholder="Cari nama, HP, atau ID…"
            >
          </div>
          <?php $this->view('pelanggan/form_tambah', ['plg_add_mode' => 'list']); ?>
        </div>
      </div>

      <div class="plg-main">
        <div class="plg-list-head">
          <div class="plg-panel-title">
            <i class="plg-icon is-blue fas fa-address-book"></i>
            Daftar
          </div>
          <span class="plg-count" id="plg-count"><?= (int) $total ?></span>
        </div>

        <div class="plg-list" id="plg-list">
          <?php if ($total === 0) { ?>
            <div class="plg-empty" id="plg-empty-all">
              <b>Belum ada pelanggan</b>
              Cek nomor HP di form kiri, lalu simpan.
            </div>
          <?php } else { ?>
            <?php foreach ($rows as $a) {
              $id = (int) $a['id_pelanggan'];
              $f1 = $a['nama_pelanggan'];
              $f2 = $a['nomor_pelanggan'];
              $f5 = $a['disc'];
              $f6 = $a['nomor_pelanggan_2'] ?? '';

              if ($f1 === '' || $f1 === null) {
                $f1 = '[ ]';
              }
              if ($f2 === '' || $f2 === null) {
                $f2 = '08';
              }

              $f1Show = strtoupper($f1);
              $f1Attr = htmlspecialchars($f1, ENT_QUOTES, 'UTF-8');
              $f1Html = htmlspecialchars($f1Show, ENT_QUOTES, 'UTF-8');
              $f2Attr = htmlspecialchars($f2, ENT_QUOTES, 'UTF-8');
              $f2Html = htmlspecialchars($f2, ENT_QUOTES, 'UTF-8');
              $f5Attr = htmlspecialchars((string) $f5, ENT_QUOTES, 'UTF-8');
              $f6Attr = htmlspecialchars($f6, ENT_QUOTES, 'UTF-8');
              $searchBlob = strtolower($id . ' ' . $f1Show . ' ' . $f2 . ' ' . $f6 . ' ' . $f5);
              $isPartner = ((float) $f5 > 0);
              $cardClass = $isPartner ? 'plg-card is-partner' : 'plg-card';
              $chipClass = $isPartner ? 'plg-chip plg-chip--yellow' : 'plg-chip';
              $hpDigits = preg_replace('/\D/', '', (string) $f2);
              $canChat = strlen($hpDigits) >= 8;
            ?>
              <article class="<?= $cardClass ?>" data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
                <strong class="plg-card-nama">
                  <span class="plg-value plg-value--nama"><?= $f1Html ?></span>
                </strong>
                <div class="plg-card-meta">
                  <span class="plg-badge">#<?= $id ?></span>
                  <span class="plg-meta-phone">
                    <span class="plg-meta-phone__main" title="Nomor HP"><?= $f2Html ?></span>
                    <?php if ($f6 !== '') { ?>
                      <span class="plg-meta-phone__alt" title="Nomor alternatif">Alt: <?= htmlspecialchars($f6, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php } ?>
                  </span>
                  <span class="<?= $chipClass ?>">
                    <?php if ($canEditPartner) { ?>
                      Partner <?= $f5Attr ?>%
                    <?php } else { ?>
                      <?= $f5Attr ?>%
                    <?php } ?>
                  </span>
                </div>
                <div class="plg-card-actions">
                  <button type="button" class="plg-btn plg-edit-btn" data-id="<?= $id ?>"
                    data-nama="<?= $f1Attr ?>" data-nomor="<?= $f2Attr ?>"
                    data-nomor2="<?= $f6Attr ?>"
                    data-disc="<?= $f5Attr ?>"
                    title="Edit data pelanggan">
                    <i class="fas fa-pen"></i> Edit
                  </button>
                  <button type="button"
                    class="plg-btn plg-chat-btn"
                    data-hp="<?= $f2Attr ?>"
                    data-nama="<?= $f1Attr ?>"
                    title="Riwayat Chat"
                    aria-label="Riwayat Chat"
                    <?= $canChat ? '' : 'disabled' ?>>
                    <i class="fas fa-comments"></i> Chat
                  </button>
                </div>
              </article>
            <?php } ?>

            <div id="plg-empty-filter" class="plg-empty is-hidden">
              <b>Tidak ada hasil</b>
              Coba kata kunci lain.
            </div>
          <?php } ?>
        </div>
      </div>
    </div>

    <div id="plg-edit-modal" class="plg-modal is-hidden" aria-hidden="true">
      <div class="plg-modal__backdrop" data-plg-close></div>
      <div class="plg-modal__box" role="dialog" aria-modal="true" aria-labelledby="plg-edit-title">
        <div class="plg-modal__head">
          <h3 id="plg-edit-title"><i class="fas fa-pen"></i> Edit Pelanggan</h3>
          <button type="button" class="plg-modal__close" data-plg-close aria-label="Tutup">&times;</button>
        </div>
        <form id="plg-edit-form" autocomplete="off">
          <input type="hidden" name="id" id="plg-edit-id">
          <div class="plg-modal__body">
            <div class="plg-alert is-hidden" id="plg-edit-alert" role="alert"></div>
            <div class="plg-field">
              <label class="plg-label" for="plg-edit-nama">Nama/Panggilan</label>
              <input type="text" id="plg-edit-nama" name="nama_pelanggan" class="plg-input" required
            style="text-transform: uppercase;">
            </div>
            <div class="plg-row">
              <div class="plg-field">
                <label class="plg-label" for="plg-edit-nomor">Nomor HP</label>
                <input type="text" id="plg-edit-nomor" name="nomor_pelanggan" class="plg-input" required inputmode="tel">
              </div>
              <div class="plg-field">
                <label class="plg-label" for="plg-edit-nomor2">HP Alternatif</label>
                <input type="text" id="plg-edit-nomor2" name="nomor_pelanggan_2" class="plg-input" inputmode="tel">
              </div>
            </div>
            <?php if ($canEditPartner) { ?>
              <div class="plg-field">
                <label class="plg-label" for="plg-edit-disc">Diskon Partner (%)</label>
                <input type="number" id="plg-edit-disc" name="disc" class="plg-input" min="0" max="100" step="1">
              </div>
            <?php } ?>
          </div>
          <div class="plg-modal__foot">
            <button type="button" class="plg-btn plg-btn--ghost" data-plg-close>Batal</button>
            <button type="button" class="plg-btn plg-btn--blue" id="plg-edit-cek"><i class="fas fa-search"></i> Cek Dulu</button>
            <button type="submit" class="plg-btn plg-btn--primary" id="plg-edit-save" disabled><i class="fas fa-save"></i> Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function ($) {
  var $root = $('#plg-root');
  if (!$root.length) return;

  function toast(msg, type) {
    type = type || 'info';
    if (!window.MdlToast) return;
    if (type === 'ok' || type === 'success') MdlToast.ok(msg);
    else if (type === 'error' || type === 'danger') MdlToast.error(msg);
    else if (type === 'warn' || type === 'warning') MdlToast.warn(msg);
    else MdlToast.info(msg);
  }

  window.onPelangganPicked = function () {
    toast('Pelanggan disimpan', 'ok');
    location.reload(true);
  };

  function applyFilter() {
    var q = ($('#plg-filter').val() || '').toLowerCase().trim();
    var hp = ($('#ordPlgHp').val() || '').toLowerCase();
    var hpTail = hp.length >= 8 ? hp.substring(hp.length - 8) : hp;
    var nama = ($('#ordPlgNama').val() || '').toLowerCase().trim();
    var visible = 0;

    $root.find('.plg-card').each(function () {
      var blob = ($(this).attr('data-search') || '').toLowerCase();
      var ok = true;
      if (q && blob.indexOf(q) === -1) ok = false;
      if (ok && nama && blob.indexOf(nama) === -1) ok = false;
      if (ok && hpTail && blob.indexOf(hpTail) === -1) ok = false;
      $(this).toggle(ok);
      if (ok) visible++;
    });

    var $count = $('#plg-count');
    if ($count.length) $count.text(visible);

    var $emptyFilter = $('#plg-empty-filter');
    if ($emptyFilter.length) {
      $emptyFilter.toggleClass('is-hidden', visible > 0 || $root.find('.plg-card').length === 0);
    }
  }

  var $modal = $('#plg-edit-modal');

  function openEditModal() {
    $modal.removeClass('is-hidden');
    hideEditAlert();
    setSaveEnabled(false);
    $('#plg-edit-nama').trigger('focus');
  }

  function closeEditModal() {
    $modal.addClass('is-hidden');
  }

  $root.on('click', '.plg-edit-btn', function () {
    var $btn = $(this);
    var $card = $btn.closest('.plg-card');
    var nomor = $btn.attr('data-nomor') || $card.find('.plg-meta-phone__main').text().trim();
    var nomor2 = $btn.attr('data-nomor2') || $card.find('.plg-meta-phone__alt').text().replace(/^Alt:\s*/i, '').trim();
    $('#plg-edit-id').val($btn.attr('data-id'));
    $('#plg-edit-nama').val($btn.attr('data-nama') || $card.find('.plg-card-nama').text().trim());
    $('#plg-edit-nomor').val(nomor);
    $('#plg-edit-nomor2').val(nomor2);
    var $disc = $('#plg-edit-disc');
    if ($disc.length) $disc.val($btn.attr('data-disc') || '0');
    openEditModal();
  });

  $modal.on('click', '[data-plg-close]', function (e) {
    e.preventDefault();
    closeEditModal();
  });
  $modal.on('click', '.plg-modal__backdrop', function () {
    closeEditModal();
  });
  $(document).on('keydown.plgEditModal', function (e) {
    if (e.key === 'Escape' && !$modal.hasClass('is-hidden')) closeEditModal();
  });

  var $alert = $('#plg-edit-alert');
  var $saveBtn = $('#plg-edit-save');
  var $cekBtn = $('#plg-edit-cek');
  var cekBtnHtml = '';

  function setCekLoading(on) {
    if (!on) {
      $cekBtn.prop('disabled', false).html(cekBtnHtml);
      return;
    }
    cekBtnHtml = $cekBtn.html();
    $cekBtn.prop('disabled', true)
      .html('<i class="fas fa-spinner fa-spin"></i> Mengecek…');
  }

  function setEditAlert(html, type) {
    if (!$alert.length) return;
    $alert
      .removeClass('plg-alert--warn plg-alert--ok')
      .attr('class', 'plg-alert' + (type ? ' plg-alert--' + type : ''))
      .html(html || '')
      .removeClass('is-hidden');
  }
  function hideEditAlert() {
    if ($alert.length) $alert.addClass('is-hidden').html('');
  }

  function setSaveEnabled(on) {
    $saveBtn.prop('disabled', !on);
  }

  function cekEditNomor() {
    hideEditAlert();
    var nomor = String($('#plg-edit-nomor').val() || '').replace(/\D/g, '');
    var nomor2 = String($('#plg-edit-nomor2').val() || '').replace(/\D/g, '');
    if (!nomor || nomor.length < 8) {
      setEditAlert('Isi Nomor HP utama dengan lengkap (min. 8 digit) dulu.', 'warn');
      $('#plg-edit-nomor').trigger('focus');
      return;
    }
    if (nomor2 && nomor === nomor2) {
      setEditAlert('Nomor HP Alternatif sama dengan Nomor HP utama — gunakan nomor berbeda.', 'warn');
      $('#plg-edit-nomor2').trigger('focus');
      return;
    }

    setCekLoading(true);
    $.ajax({
      url: '<?= URL::BASE_URL ?>Pelanggan/cekEdit',
      type: 'POST',
      dataType: 'json',
      data: {
        id: $('#plg-edit-id').val(),
        nomor_pelanggan: nomor,
        nomor_pelanggan_2: nomor2
      },
      success: function (res) {
        setCekLoading(false);
        if (!res || !res.ok) {
          setEditAlert((res && res.msg) || 'Gagal cek nomor', 'warn');
          return;
        }
        renderCekEdit(res);
      },
      error: function () {
        setCekLoading(false);
        setEditAlert('Gagal cek nomor — coba lagi.', 'warn');
      }
    });
  }

  function renderCekEdit(res) {
    // Nama harus unik di cabang — jika duplikat, tolak dan jangan nyalakan Simpan.
    if (res && res.nama_dup) {
      var d = res.nama_dup;
      var msg = 'Nama <b>' + $('<div>').text(d.nama || '').html() + '</b> sudah digunakan pelanggan lain di cabang ini '
        + '(#' + d.id + ' — ' + $('<div>').text(d.nomor || '').html() + '). Ganti dengan nama lain.';
      setEditAlert(msg, 'warn');
      setSaveEnabled(false);
      return;
    }

    var hasil = (res && res.hasil) || [];
    if (!hasil.length) {
      setEditAlert('Edit OK — nama unik dan nomor tidak dipakai pelanggan lain di cabang ini.', 'ok');
      setSaveEnabled(true);
      return;
    }

    var html = '';
    var adaBentrok = false;
    hasil.forEach(function (h) {
      if (!h || !h.bentrok) return;
      adaBentrok = true;
      var label = h.label || 'Nomor';
      html += '<div class="plg-alert-item"><b>' + label + ' ' + $('<div>').text(h.nomor || '').html() + ' sudah dipakai pelanggan lain di cabang ini:</b><ul>';
      (h.items || []).forEach(function (it) {
        html += '<li>#' + it.id + ' — ' + $('<div>').text(it.nama || '').html()
          + ' <span class="plg-alert-hp">' + $('<div>').text(it.nomor || '').html() + '</span>'
          + (it.nomor2 ? ' <span class="plg-alert-hp">' + $('<div>').text(it.nomor2 || '').html() + '</span>' : '')
          + '</li>';
      });
      html += '</ul></div>';
    });

    if (adaBentrok) {
      html += '<div class="plg-alert-note">Kamu tetap bisa simpan dengan nomor yang sama. Pastikan ini memang disengaja.</div>';
      setEditAlert(html, 'warn');
    } else {
      setEditAlert('Edit OK — nomor tidak dipakai pelanggan lain di cabang ini.', 'ok');
    }
    setSaveEnabled(true);
  }

  $cekBtn.on('click', function (e) {
    e.preventDefault();
    cekEditNomor();
  });

  $('#plg-edit-form').on('submit', function (e) {
    e.preventDefault();
    hideEditAlert();

    var nomor = String($('#plg-edit-nomor').val() || '').replace(/\D/g, '');
    var nomor2 = String($('#plg-edit-nomor2').val() || '').replace(/\D/g, '');
    if (nomor2 && nomor === nomor2) {
      setEditAlert('Nomor HP Alternatif sama dengan Nomor HP utama — gunakan nomor berbeda.', 'warn');
      $('#plg-edit-nomor2').trigger('focus');
      return;
    }

    $saveBtn.prop('disabled', true);
    $.ajax({
      url: '<?= URL::BASE_URL ?>Pelanggan/update',
      type: 'POST',
      dataType: 'html',
      data: $(this).serialize(),
      success: function (res) {
        var raw = String(res || '').trim();
        if (raw !== '' && raw !== '0') {
          if (raw.indexOf('nama') !== -1 && raw.indexOf('sudah digunakan') !== -1) {
            // Nama duplikat — tetap tolak, Simpan jangan dinyalakan.
            setEditAlert(raw, 'warn');
            setSaveEnabled(false);
          } else {
            $saveBtn.prop('disabled', false);
            if (raw.indexOf('sama dengan') !== -1) {
              setEditAlert(raw, 'warn');
            } else {
              toast(raw, 'error');
            }
          }
          return;
        }
        closeEditModal();
        toast('Tersimpan', 'ok');
        location.reload(true);
      },
      error: function () {
        toast('Gagal menyimpan', 'error');
        $saveBtn.prop('disabled', false);
      }
    });
  });

  $('#plg-filter, #ordPlgNama, #ordPlgHp').on('keyup input', applyFilter);

  $root.on('click', '.plg-chat-btn', function (ev) {
    ev.preventDefault();
    ev.stopPropagation();
    var $btn = $(this);
    if ($btn.prop('disabled')) return;
    var hp = String($btn.attr('data-hp') || '').trim();
    var nama = String($btn.attr('data-nama') || 'Pelanggan').trim();
    if (!hp || hp.replace(/\D/g, '').length < 8) {
      toast('Nomor pelanggan tidak tersedia', 'warn');
      return;
    }
    if (window.MdlChatHistory && typeof MdlChatHistory.open === 'function') {
      MdlChatHistory.open(hp, nama, { showCloseCase: false });
    } else {
      toast('Modal chat belum siap', 'error');
    }
  });
})(jQuery);
</script>
