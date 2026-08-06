<?php
$mode = (int) ($data['mode'] ?? 0);
$rows = $data['rows'] ?? [];
$grouped = $data['grouped'] ?? [];
$cabangMap = $data['cabangMap'] ?? [];

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

$kodeCabang = function ($id) use ($cabangMap) {
  $id = (int) $id;
  return $cabangMap[$id] ?? ('#' . $id);
};

$renderCard = function ($row) use ($mode, $jenisTone, $fmtDt, $kodeCabang) {
  $id = (int) ($row['id_tiket'] ?? 0);
  $judul = (string) ($row['judul'] ?? '');
  $jenis = (string) ($row['jenis'] ?? '');
  $ket = (string) ($row['keterangan'] ?? '');
  $karyawan = (string) ($row['karyawan'] ?? '');
  $karyawanSelesai = (string) ($row['karyawan_selesai'] ?? '');
  $idKaryawan = (int) ($row['id_karyawan'] ?? 0);
  $tone = $jenisTone[$jenis] ?? 'blue';
  $isProses = $mode === 0;
  ?>
  <div class="tk-card tk-card--<?= $isProses ? 'proses' : 'selesai' ?>">
    <div class="tk-card__head">
      <div class="tk-card__meta">
        <span class="tk-badge"><?= htmlspecialchars($kodeCabang($row['id_cabang'] ?? 0)) ?></span>
        <span class="tk-chip tk-chip--<?= htmlspecialchars($tone) ?>"><?= htmlspecialchars($jenis) ?></span>
        <span class="tk-card__time"><?= htmlspecialchars($isProses ? $fmtDt($row['insertTime'] ?? '') : $fmtDt($row['selesaiTime'] ?? '')) ?></span>
      </div>
    </div>
    <div class="tk-card__body">
      <div class="tk-card__title"><?= htmlspecialchars($judul) ?></div>
      <?php if ($ket !== '') { ?>
        <div class="tk-card__ket"><?= nl2br(htmlspecialchars($ket)) ?></div>
      <?php } ?>
      <div class="tk-card__people">
        <span><em>Pembuat</em> <?= htmlspecialchars($karyawan !== '' ? $karyawan : '—') ?></span>
        <?php if (!$isProses) { ?>
          <span><em>Penyelesai</em> <?= htmlspecialchars($karyawanSelesai !== '' ? $karyawanSelesai : '—') ?></span>
        <?php } ?>
      </div>
      <?php if (!$isProses && !empty($row['catatan_selesai'])) { ?>
        <div class="tk-card__catatan">
          <em>Catatan</em>
          <p><?= nl2br(htmlspecialchars((string) $row['catatan_selesai'])) ?></p>
        </div>
      <?php } ?>
    </div>
    <?php if ($isProses) { ?>
      <div class="tk-card__actions">
        <button type="button"
          class="tk-btn tk-btn--blue tk-btn--sm btnEditTiket"
          data-id="<?= $id ?>"
          data-id-karyawan="<?= $idKaryawan ?>"
          data-pembuat="<?= htmlspecialchars(base64_encode($karyawan), ENT_QUOTES) ?>"
          data-judul="<?= htmlspecialchars(base64_encode($judul), ENT_QUOTES) ?>"
          data-jenis="<?= htmlspecialchars($jenis, ENT_QUOTES) ?>"
          data-keterangan="<?= htmlspecialchars(base64_encode($ket), ENT_QUOTES) ?>">
          <i class="fas fa-edit"></i> Edit
        </button>
        <button type="button"
          class="tk-btn tk-btn--primary tk-btn--sm btnSelesaiTiket"
          data-id="<?= $id ?>"
          data-judul="<?= htmlspecialchars(base64_encode($judul), ENT_QUOTES) ?>">
          <i class="fas fa-check"></i> Selesai
        </button>
        <button type="button"
          class="tk-btn tk-btn--danger tk-btn--sm btnHapusTiket"
          data-id="<?= $id ?>"
          data-id-karyawan="<?= $idKaryawan ?>"
          data-pembuat="<?= htmlspecialchars(base64_encode($karyawan), ENT_QUOTES) ?>"
          data-judul="<?= htmlspecialchars(base64_encode($judul), ENT_QUOTES) ?>">
          <i class="fas fa-trash"></i> Hapus
        </button>
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
  }
  #tiket-root .tk-card--proses {
    border-color: #93c5fd;
    background: #f8fbff;
  }
  #tiket-root .tk-card--selesai {
    border-color: #86efac;
    background: #f7fdf9;
  }
  #tiket-root .tk-card__head {
    padding: 6px 8px;
    border-bottom: 1px solid #e2e8f0;
  }
  #tiket-root .tk-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 6px;
    align-items: center;
  }
  #tiket-root .tk-badge {
    display: inline-block;
    padding: 1px 6px;
    background: #0f172a;
    color: #fff;
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.03em;
  }
  #tiket-root .tk-chip {
    display: inline-block;
    padding: 1px 6px;
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.02em;
  }
  #tiket-root .tk-chip--blue { background: #2563eb; color: #fff; }
  #tiket-root .tk-chip--green { background: #16a34a; color: #fff; }
  #tiket-root .tk-chip--yellow { background: #f59e0b; color: #111; }
  #tiket-root .tk-chip--red { background: #dc2626; color: #fff; }
  #tiket-root .tk-card__time {
    margin-left: auto;
    font-size: 0.7rem;
    font-weight: 750;
    color: #64748b;
    white-space: nowrap;
  }
  #tiket-root .tk-card__body { padding: 8px; }
  #tiket-root .tk-card__title {
    font-size: 0.88rem;
    font-weight: 900;
    color: #0f172a;
    margin: 0 0 2px;
    letter-spacing: -0.02em;
    line-height: 1.25;
  }
  #tiket-root .tk-card__ket {
    font-size: 0.78rem;
    font-weight: 650;
    color: #334155;
    margin: 0 0 4px;
    line-height: 1.35;
    white-space: pre-wrap;
  }
  #tiket-root .tk-card__people {
    display: flex;
    flex-wrap: wrap;
    gap: 2px 12px;
    font-size: 0.76rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
  }
  #tiket-root .tk-card__people em {
    font-style: normal;
    font-weight: 900;
    font-size: 0.64rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin-right: 4px;
  }
  #tiket-root .tk-card__catatan {
    margin-top: 6px;
    border-left: 2px solid #16a34a;
    padding: 2px 0 2px 8px;
  }
  #tiket-root .tk-card__catatan em {
    display: block;
    font-style: normal;
    font-size: 0.64rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #15803d;
    margin-bottom: 1px;
  }
  #tiket-root .tk-card__catatan p {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 650;
    color: #0f172a;
    line-height: 1.35;
  }
  #tiket-root .tk-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 0 8px 8px;
  }
  #tiket-root .tk-month {
    margin: 0 0 8px;
  }
  #tiket-root .tk-month__head {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 8px;
    background: #15803d;
    color: #fff;
    font-size: 0.8rem;
    font-weight: 900;
    letter-spacing: -0.02em;
  }
  #tiket-root .tk-month__head .tk-month__count {
    margin-left: auto;
    background: rgba(255,255,255,.22);
    padding: 1px 6px;
    font-size: 0.68rem;
  }
  #tiket-root .tk-month__list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 6px;
    margin-top: 6px;
  }
  #tiket-root .tk-empty {
    border: 1px solid #cbd5e1;
    background: #fff;
    padding: 18px 12px;
    text-align: center;
    color: #64748b;
    font-weight: 750;
    font-size: 0.82rem;
  }
  #tiket-root .tk-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 6px;
  }
  @media (min-width: 720px) {
    #tiket-root .tk-grid,
    #tiket-root .tk-month__list {
      grid-template-columns: 1fr 1fr;
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
