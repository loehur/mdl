<?php
$mode = (int) ($data['mode'] ?? 0);
$rows = $data['rows'] ?? [];
$grouped = $data['grouped'] ?? [];
$cabangMap = $data['cabangMap'] ?? [];
$userMap = $data['userMap'] ?? [];
$idUser = (int) ($data['idUser'] ?? 0);
$isAdmin = !empty($data['isAdmin']);
$canSelesai = !empty($data['canSelesai']);

$jenisTone = [
  'Perbaikan' => 'blue',
  'Pergantian' => 'yellow',
  'Perawatan' => 'green',
  'Penambahan' => 'red',
];

$fmtDt = function ($dt) {
  if (!$dt || $dt === '0000-00-00 00:00:00') return '—';
  $t = strtotime($dt);
  return $t ? date('d/m/Y H:i', $t) : '—';
};

$namaUser = function ($id) use ($userMap) {
  $id = (int) $id;
  if ($id <= 0) return '—';
  if (isset($userMap[$id]) && is_array($userMap[$id])) {
    return strtoupper((string) ($userMap[$id]['nama_user'] ?? ('#' . $id)));
  }
  return '#' . $id;
};

$kodeCabang = function ($id) use ($cabangMap) {
  $id = (int) $id;
  return $cabangMap[$id] ?? ('#' . $id);
};

$renderCard = function ($row) use ($mode, $idUser, $isAdmin, $canSelesai, $jenisTone, $fmtDt, $namaUser, $kodeCabang) {
  $id = (int) ($row['id_tiket'] ?? 0);
  $judul = (string) ($row['judul'] ?? '');
  $jenis = (string) ($row['jenis'] ?? '');
  $ket = (string) ($row['keterangan'] ?? '');
  $karyawan = (string) ($row['karyawan'] ?? '');
  $tone = $jenisTone[$jenis] ?? 'blue';
  $isOwner = ((int) ($row['id_user'] ?? 0) === $idUser);
  $isProses = $mode === 0;
  ?>
  <div class="tk-card tk-card--<?= $isProses ? 'proses' : 'selesai' ?>">
    <div class="tk-card__head">
      <div class="tk-card__meta">
        <span class="tk-badge"><?= htmlspecialchars($kodeCabang($row['id_cabang'] ?? 0)) ?></span>
        <span class="tk-chip tk-chip--<?= htmlspecialchars($tone) ?>"><?= htmlspecialchars($jenis) ?></span>
      </div>
      <span class="tk-card__time"><?= htmlspecialchars($isProses ? $fmtDt($row['insertTime'] ?? '') : $fmtDt($row['selesaiTime'] ?? '')) ?></span>
    </div>
    <div class="tk-card__body">
      <div class="tk-card__title"><?= htmlspecialchars($judul) ?></div>
      <?php if ($ket !== '') { ?>
        <div class="tk-card__ket"><?= nl2br(htmlspecialchars($ket)) ?></div>
      <?php } ?>
      <div class="tk-card__grid">
        <div><span>Karyawan</span><strong><?= htmlspecialchars($karyawan) ?></strong></div>
        <div><span>Pembuat</span><strong><?= htmlspecialchars($namaUser($row['id_user'] ?? 0)) ?></strong></div>
        <?php if (!$isProses) { ?>
          <div><span>Karyawan Selesai</span><strong><?= htmlspecialchars((string) ($row['karyawan_selesai'] ?? '—')) ?></strong></div>
          <div><span>Ditutup oleh</span><strong><?= htmlspecialchars($namaUser($row['id_user_selesai'] ?? 0)) ?></strong></div>
        <?php } ?>
      </div>
      <?php if (!$isProses && !empty($row['catatan_selesai'])) { ?>
        <div class="tk-card__catatan">
          <span>Catatan Selesai</span>
          <p><?= nl2br(htmlspecialchars((string) $row['catatan_selesai'])) ?></p>
        </div>
      <?php } ?>
    </div>
    <?php if ($isProses && ($isOwner || $isAdmin || $canSelesai)) { ?>
      <div class="tk-card__actions">
        <?php if ($isOwner) { ?>
          <button type="button"
            class="tk-btn tk-btn--blue tk-btn--sm btnEditTiket"
            data-id="<?= $id ?>"
            data-judul="<?= htmlspecialchars(base64_encode($judul), ENT_QUOTES) ?>"
            data-jenis="<?= htmlspecialchars($jenis, ENT_QUOTES) ?>"
            data-keterangan="<?= htmlspecialchars(base64_encode($ket), ENT_QUOTES) ?>"
            data-karyawan="<?= htmlspecialchars(base64_encode($karyawan), ENT_QUOTES) ?>">
            <i class="fas fa-edit"></i> Edit
          </button>
        <?php } ?>
        <?php if ($canSelesai) { ?>
          <button type="button"
            class="tk-btn tk-btn--primary tk-btn--sm btnSelesaiTiket"
            data-id="<?= $id ?>"
            data-judul="<?= htmlspecialchars(base64_encode($judul), ENT_QUOTES) ?>">
            <i class="fas fa-check"></i> Selesai
          </button>
        <?php } ?>
        <?php if ($isAdmin) { ?>
          <button type="button"
            class="tk-btn tk-btn--danger tk-btn--sm btnHapusTiket"
            data-id="<?= $id ?>"
            data-judul="<?= htmlspecialchars(base64_encode($judul), ENT_QUOTES) ?>">
            <i class="fas fa-trash"></i> Hapus
          </button>
        <?php } ?>
      </div>
    <?php } ?>
  </div>
  <?php
};
?>
<style>
  #tiket-root .tk-card {
    border: 1px solid #cbd5e1;
    background: #fff;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
  }
  #tiket-root .tk-card--proses {
    border-color: #93c5fd;
    background: linear-gradient(180deg, #eff6ff, #fff);
  }
  #tiket-root .tk-card--selesai {
    border-color: #86efac;
    background: linear-gradient(180deg, #f0fdf4, #fff);
  }
  #tiket-root .tk-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
  }
  #tiket-root .tk-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
  }
  #tiket-root .tk-badge {
    display: inline-block;
    padding: 3px 8px;
    background: #0f172a;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.04em;
  }
  #tiket-root .tk-chip {
    display: inline-block;
    padding: 3px 8px;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.03em;
  }
  #tiket-root .tk-chip--blue { background: #2563eb; color: #fff; }
  #tiket-root .tk-chip--green { background: #16a34a; color: #fff; }
  #tiket-root .tk-chip--yellow { background: #f59e0b; color: #111; }
  #tiket-root .tk-chip--red { background: #dc2626; color: #fff; }
  #tiket-root .tk-card__time {
    font-size: 0.76rem;
    font-weight: 750;
    color: #475569;
    white-space: nowrap;
  }
  #tiket-root .tk-card__body { padding: 12px; }
  #tiket-root .tk-card__title {
    font-size: 0.95rem;
    font-weight: 900;
    color: #0f172a;
    margin-bottom: 6px;
    letter-spacing: -0.02em;
  }
  #tiket-root .tk-card__ket {
    font-size: 0.84rem;
    font-weight: 750;
    color: #1e293b;
    margin-bottom: 10px;
    white-space: pre-wrap;
  }
  #tiket-root .tk-card__grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  #tiket-root .tk-card__grid div {
    border: 1px solid #e2e8f0;
    background: #fff;
    padding: 8px 10px;
  }
  #tiket-root .tk-card__grid span {
    display: block;
    font-size: 0.68rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin-bottom: 2px;
  }
  #tiket-root .tk-card__grid strong {
    font-size: 0.84rem;
    font-weight: 900;
    color: #0f172a;
  }
  #tiket-root .tk-card__catatan {
    margin-top: 10px;
    border: 1px solid #86efac;
    background: linear-gradient(180deg, #f0fdf4, #fff);
    padding: 10px 12px;
  }
  #tiket-root .tk-card__catatan span {
    display: block;
    font-size: 0.68rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #15803d;
    margin-bottom: 4px;
  }
  #tiket-root .tk-card__catatan p {
    margin: 0;
    font-size: 0.84rem;
    font-weight: 750;
    color: #0f172a;
  }
  #tiket-root .tk-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0 12px 12px;
  }
  #tiket-root .tk-month {
    margin: 4px 0 8px;
  }
  #tiket-root .tk-month__head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    background: linear-gradient(105deg, #15803d 0%, #16a34a 100%);
    color: #fff;
    font-size: 0.88rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
  }
  #tiket-root .tk-month__head .tk-month__count {
    margin-left: auto;
    background: rgba(255,255,255,.22);
    padding: 2px 8px;
    font-size: 0.72rem;
  }
  #tiket-root .tk-month__list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    margin-top: 8px;
  }
  #tiket-root .tk-empty {
    border: 1px solid #cbd5e1;
    background: #fff;
    padding: 28px 16px;
    text-align: center;
    color: #64748b;
    font-weight: 750;
    font-size: 0.88rem;
  }
  #tiket-root .tk-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
  }
  @media (min-width: 720px) {
    #tiket-root .tk-grid,
    #tiket-root .tk-month__list {
      grid-template-columns: 1fr 1fr;
    }
  }
  @media (max-width: 520px) {
    #tiket-root .tk-card__grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<?php if ($mode === 1) { ?>
  <?php if (empty($grouped)) { ?>
    <div class="tk-empty">Belum ada tiket selesai.</div>
  <?php } else { ?>
    <?php foreach ($grouped as $ym => $group) { ?>
      <div class="tk-month">
        <div class="tk-month__head">
          <i class="far fa-calendar-alt"></i>
          <span><?= htmlspecialchars($group['label'] ?? $ym) ?></span>
          <span class="tk-month__count"><?= count($group['items'] ?? []) ?></span>
        </div>
        <div class="tk-month__list">
          <?php foreach (($group['items'] ?? []) as $row) {
            $renderCard($row);
          } ?>
        </div>
      </div>
    <?php } ?>
  <?php } ?>
<?php } else { ?>
  <?php if (empty($rows)) { ?>
    <div class="tk-empty">Tidak ada tiket proses. Klik Tambah untuk membuat tiket baru.</div>
  <?php } else { ?>
    <div class="tk-grid">
      <?php foreach ($rows as $row) {
        $renderCard($row);
      } ?>
    </div>
  <?php } ?>
<?php } ?>
