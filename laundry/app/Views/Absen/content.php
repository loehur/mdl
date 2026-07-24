<?php
if (!function_exists('absen_nama_user')) {
  function absen_nama_user($userMerge, $idKaryawan)
  {
    foreach ($userMerge as $c) {
      if ((int) $c['id_user'] === (int) $idKaryawan) {
        return strtoupper((string) $c['nama_user']);
      }
    }
    return '-';
  }
}

if (!function_exists('absen_jenis_label')) {
  function absen_jenis_label($jenis)
  {
    $map = [
      0 => ['Cuci', 'green', 'fa-tshirt'],
      1 => ['Jaga Malam', 'blue', 'fa-moon'],
      2 => ['Delivery', 'yellow', 'fa-motorcycle'],
      3 => ['Maintenance', 'red', 'fa-tools'],
    ];
    $j = (int) $jenis;
    return $map[$j] ?? ['NaN - ' . $j, 'muted', 'fa-question'];
  }
}
?>
<style>
  #absen-root .absen-list {
    border: 1px solid #cbd5e1;
    background: #fff;
    margin-bottom: 10px;
  }
  #absen-root .absen-list--today {
    border-color: #86efac;
    background: linear-gradient(180deg, #f0fdf4, #fff);
  }
  #absen-root .absen-list--yesterday {
    border-color: #fcd34d;
    background: linear-gradient(180deg, #fffbeb, #fff);
  }
  #absen-root .absen-list__head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #1e293b;
  }
  #absen-root .absen-list__head i {
    width: 26px;
    height: 26px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    border-radius: 0;
  }
  #absen-root .absen-list--today .absen-list__head i { background: #16a34a; }
  #absen-root .absen-list--yesterday .absen-list__head i { background: #f59e0b; color: #111; }
  #absen-root .absen-list__count {
    margin-left: auto;
    font-weight: 900;
    color: #0f172a;
  }
  #absen-root .absen-list__empty {
    padding: 14px 12px;
    color: #64748b;
    font-weight: 700;
    font-size: 0.84rem;
  }
  #absen-root .absen-list__row {
    display: grid;
    grid-template-columns: 52px 1.1fr 1.2fr 72px;
    gap: 8px;
    align-items: center;
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    color: #0f172a;
    font-size: 0.84rem;
    font-weight: 750;
  }
  #absen-root .absen-list__row.is-admin {
    grid-template-columns: 52px 1.1fr 1fr 64px 36px;
  }
  #absen-root .absen-list__row:last-child { border-bottom: 0; }
  #absen-root .absen-list__id {
    color: #64748b;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
  }
  #absen-root .absen-list__task {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 900;
  }
  #absen-root .absen-list__task i {
    width: 22px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
  }
  #absen-root .absen-list__task.is-green i { background: #16a34a; }
  #absen-root .absen-list__task.is-blue i { background: #2563eb; }
  #absen-root .absen-list__task.is-yellow i { background: #f59e0b; color: #111; }
  #absen-root .absen-list__task.is-red i { background: #dc2626; }
  #absen-root .absen-list__task.is-muted i { background: #94a3b8; }
  #absen-root .absen-list__name {
    font-weight: 900;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  #absen-root .absen-list__time {
    text-align: right;
    font-variant-numeric: tabular-nums;
    font-weight: 800;
    color: #1e293b;
  }
  #absen-root .absen-list__del {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1px solid #fca5a5;
    background: linear-gradient(180deg, #fef2f2, #fff);
    color: #dc2626;
    cursor: pointer;
    padding: 0;
  }
  #absen-root .absen-list__del:hover {
    background: #dc2626;
    color: #fff;
    border-color: #dc2626;
  }
  @media (max-width: 420px) {
    #absen-root .absen-list__row {
      grid-template-columns: 44px 1fr 64px;
      grid-template-areas:
        "id task time"
        "id name time";
    }
    #absen-root .absen-list__row.is-admin {
      grid-template-columns: 44px 1fr 36px 56px;
      grid-template-areas:
        "id task del time"
        "id name del time";
    }
    #absen-root .absen-list__id { grid-area: id; }
    #absen-root .absen-list__task { grid-area: task; }
    #absen-root .absen-list__name { grid-area: name; }
    #absen-root .absen-list__time { grid-area: time; align-self: center; }
    #absen-root .absen-list__del { grid-area: del; align-self: center; }
  }
</style>

<?php
$isAdmin = !empty($data['isAdmin']);
$lists = [
  ['key' => 'hari_ini', 'title' => 'Hari Ini', 'class' => 'absen-list--today', 'icon' => 'fa-calendar-day'],
  ['key' => 'kemarin', 'title' => 'Kemarin', 'class' => 'absen-list--yesterday', 'icon' => 'fa-calendar-minus'],
];
foreach ($lists as $list) {
  $rows = $data[$list['key']] ?? [];
  $count = is_array($rows) ? count($rows) : 0;
?>
<div class="absen-list <?= $list['class'] ?>">
  <div class="absen-list__head">
    <i class="fas <?= $list['icon'] ?>"></i>
    <?= htmlspecialchars($list['title']) ?>
    <span class="absen-list__count"><?= (int) $count ?></span>
  </div>
  <?php if ($count === 0) { ?>
    <div class="absen-list__empty">Belum ada absen.</div>
  <?php } else {
    foreach ($rows as $d) {
      $nama = absen_nama_user($this->userMerge, $d['id_karyawan']);
      $meta = absen_jenis_label($d['jenis']);
  ?>
    <div class="absen-list__row<?= $isAdmin ? ' is-admin' : '' ?>">
      <div class="absen-list__id">#<?= (int) $d['id'] ?></div>
      <div class="absen-list__task is-<?= htmlspecialchars($meta[1]) ?>">
        <i class="fas <?= htmlspecialchars($meta[2]) ?>"></i>
        <?= htmlspecialchars($meta[0]) ?>
      </div>
      <div class="absen-list__name"><?= htmlspecialchars($nama) ?></div>
      <div class="absen-list__time"><i class="far fa-clock"></i> <?= htmlspecialchars((string) $d['jam']) ?></div>
      <?php if ($isAdmin) { ?>
        <button type="button"
          class="absen-list__del btnHapusAbsen"
          title="Hapus absen"
          data-id="<?= (int) $d['id'] ?>"
          data-nama="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>"
          data-tugas="<?= htmlspecialchars($meta[0], ENT_QUOTES, 'UTF-8') ?>"
          data-jam="<?= htmlspecialchars((string) $d['jam'], ENT_QUOTES, 'UTF-8') ?>"
          data-tanggal="<?= htmlspecialchars((string) $d['tanggal'], ENT_QUOTES, 'UTF-8') ?>">
          <i class="fas fa-trash-alt"></i>
        </button>
      <?php } ?>
    </div>
  <?php }
  } ?>
</div>
<?php } ?>
