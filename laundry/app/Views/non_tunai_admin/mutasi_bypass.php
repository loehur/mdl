<?php $rules = is_array($data['rules'] ?? null) ? $data['rules'] : []; ?>
<div class="container-fluid py-3">
  <div class="card shadow-sm mb-3"><div class="card-body">
    <h5 class="mb-1">Bypass Mutasi BCA</h5>
    <p class="text-muted small mb-3">Mutasi CR dengan keterangan yang mengandung teks ini akan ditandai <strong>Diabaikan</strong> dan tidak dipakai untuk konfirmasi pembayaran.</p>
    <form method="post" action="<?= URL::BASE_URL ?>NonTunaiAdmin/saveMutasiBypass" class="row g-2">
      <div class="col-md-8"><input class="form-control" name="keyword" maxlength="191" required placeholder="Contoh: BUNGA BANK"></div>
      <div class="col-md-auto"><button class="btn btn-primary" type="submit"><i class="fas fa-plus me-1"></i>Tambah teks</button></div>
    </form>
  </div></div>
  <div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Teks pada keterangan</th><th class="text-center">Mutasi diabaikan</th><th class="text-end">Aksi</th></tr></thead>
    <tbody><?php if (!$rules) { ?><tr><td colspan="3" class="text-center text-muted py-4">Belum ada aturan bypass.</td></tr><?php } ?>
    <?php foreach ($rules as $rule) { ?><tr><td><code><?= htmlspecialchars((string) $rule['keyword']) ?></code></td><td class="text-center"><?= (int) ($rule['matched_count'] ?? 0) ?></td><td class="text-end"><form method="post" action="<?= URL::BASE_URL ?>NonTunaiAdmin/deleteMutasiBypass/<?= (int) $rule['id'] ?>" class="d-inline" onsubmit="return confirm('Hapus aturan bypass ini?')"><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr><?php } ?>
    </tbody>
  </table></div></div>
</div>
