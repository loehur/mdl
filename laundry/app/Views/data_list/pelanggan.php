<?php
$page = $data['z']['page'];
$rows = $data['data_main'] ?? [];
$total = is_array($rows) ? count($rows) : 0;
$canEditPartner = ((int) $this->id_privilege === 100);
?>

<div id="plg-root" class="font-sans text-mdl-ink px-1 pb-8">
  <!-- Header -->
  <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
      <p class="m-0 text-[11px] font-bold uppercase tracking-[0.14em] text-mdl-soft">Master data</p>
      <h1 class="m-0 mt-1 text-2xl font-extrabold tracking-tight text-mdl-ink">Data Pelanggan</h1>
      <p class="m-0 mt-1 text-sm text-mdl-soft">Kelola nama, nomor HP, dan partner. Double-click untuk edit.</p>
    </div>
    <div class="inline-flex items-center gap-2 rounded-full bg-mdl-accent-soft px-3 py-1.5 text-sm font-bold text-mdl-accent-deep">
      <i class="fas fa-address-book"></i>
      <span id="plg-count"><?= (int) $total ?></span>
      <span class="font-semibold text-mdl-soft">pelanggan</span>
    </div>
  </div>

  <!-- Toolbar: search + add -->
  <div class="mb-3 overflow-hidden rounded-2xl border border-mdl-line/70 bg-gradient-to-br from-white via-mdl-surface to-mdl-accent-soft/40 shadow-[0_8px_24px_rgba(36,48,65,0.08)]">
    <div class="border-b border-mdl-line/50 px-4 py-3">
      <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-mdl-soft" for="plg-filter">Cari</label>
      <div class="relative">
        <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-mdl-soft"></i>
        <input
          type="text"
          id="plg-filter"
          autocomplete="off"
          placeholder="Cari nama, nomor HP, atau ID…"
          class="w-full rounded-xl border border-mdl-line bg-white py-2.5 pl-10 pr-3 text-sm font-semibold text-mdl-ink outline-none transition focus:border-mdl-accent focus:ring-2 focus:ring-mdl-accent/25"
        >
      </div>
    </div>

    <form id="plg-form" action="<?= URL::BASE_URL; ?>Data_List/insert/<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>" method="POST" class="grid gap-3 px-4 py-3 sm:grid-cols-[1fr_1.2fr_auto]">
      <div>
        <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-mdl-soft" for="plg-hp">Nomor HP</label>
        <input
          type="text"
          id="plg-hp"
          name="f2"
          required
          autocomplete="off"
          placeholder="08…"
          class="w-full rounded-xl border border-mdl-line bg-white px-3 py-2.5 text-sm font-semibold text-mdl-ink outline-none transition focus:border-mdl-accent focus:ring-2 focus:ring-mdl-accent/25"
        >
      </div>
      <div>
        <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-mdl-soft" for="plg-nama">Nama pelanggan</label>
        <input
          type="text"
          id="plg-nama"
          name="f1"
          required
          autocomplete="off"
          placeholder="Nama lengkap"
          class="w-full rounded-xl border border-mdl-line bg-white px-3 py-2.5 text-sm font-semibold text-mdl-ink outline-none transition focus:border-mdl-accent focus:ring-2 focus:ring-mdl-accent/25"
        >
      </div>
      <div class="flex items-end">
        <button
          type="submit"
          id="plg-submit"
          class="inline-flex h-[42px] w-full items-center justify-center gap-2 rounded-xl bg-mdl-accent px-5 text-sm font-extrabold text-white shadow-[0_6px_16px_rgba(63,116,212,0.35)] transition hover:bg-mdl-accent-deep active:scale-[0.98] sm:min-w-[120px]"
        >
          <i class="fas fa-plus"></i>
          Tambah
        </button>
      </div>
    </form>
  </div>

  <!-- List -->
  <div class="overflow-hidden rounded-2xl border border-mdl-line/70 bg-white shadow-[0_8px_24px_rgba(36,48,65,0.06)]">
    <div class="flex items-center justify-between border-b border-mdl-line/50 bg-mdl-surface/80 px-4 py-2.5">
      <span class="text-[11px] font-bold uppercase tracking-wider text-mdl-soft">Daftar</span>
      <span class="text-[11px] font-semibold text-mdl-soft">Double-click nama / HP<?= $canEditPartner ? ' / partner' : '' ?> untuk edit</span>
    </div>

    <div id="plg-list" class="max-h-[min(70vh,640px)] overflow-y-auto">
      <?php if ($total === 0) { ?>
        <div class="px-4 py-14 text-center" id="plg-empty-all">
          <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-mdl-accent-soft text-mdl-accent">
            <i class="fas fa-user-plus text-lg"></i>
          </div>
          <p class="m-0 text-base font-extrabold text-mdl-ink">Belum ada pelanggan</p>
          <p class="m-0 mt-1 text-sm text-mdl-soft">Tambah pelanggan baru lewat form di atas.</p>
        </div>
      <?php } else { ?>
        <?php foreach ($rows as $a) {
          $id = (int) $a['id_pelanggan'];
          $f1 = $a['nama_pelanggan'];
          $f2 = $a['nomor_pelanggan'];
          $f5 = $a['disc'];

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
          $searchBlob = strtolower($id . ' ' . $f1Show . ' ' . $f2 . ' ' . $f5);
        ?>
          <div
            class="plg-row group flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-mdl-line/40 px-4 py-3 transition hover:bg-mdl-accent-soft/35 last:border-b-0"
            data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>"
          >
            <div class="flex min-w-[3.5rem] flex-col">
              <span class="text-[10px] font-bold uppercase tracking-wider text-mdl-soft">ID</span>
              <span class="text-sm font-extrabold text-mdl-soft">#<?= $id ?></span>
            </div>

            <div class="min-w-[12rem] flex-1">
              <span class="text-[10px] font-bold uppercase tracking-wider text-mdl-soft">Nama</span>
              <div>
                <span
                  class="plg-edit cursor-text rounded px-0.5 text-base font-extrabold tracking-tight text-mdl-ink transition group-hover:text-mdl-accent-deep"
                  data-mode="1"
                  data-id_value="<?= $id ?>"
                  data-value="<?= $f1Attr ?>"
                  title="Double-click untuk edit"
                ><?= $f1Html ?></span>
              </div>
            </div>

            <div class="min-w-[9rem]">
              <span class="text-[10px] font-bold uppercase tracking-wider text-mdl-soft">HP</span>
              <div>
                <span
                  class="plg-edit cursor-text rounded px-0.5 font-mono text-sm font-bold text-mdl-ink"
                  data-mode="2"
                  data-id_value="<?= $id ?>"
                  data-value="<?= $f2Attr ?>"
                  title="Double-click untuk edit"
                ><?= $f2Html ?></span>
              </div>
            </div>

            <div class="ml-auto min-w-[5.5rem] text-right">
              <span class="text-[10px] font-bold uppercase tracking-wider text-mdl-soft">Partner</span>
              <div class="text-sm font-bold text-mdl-ink">
                <?php if ($canEditPartner) { ?>
                  <span
                    class="plg-edit cursor-text rounded px-0.5"
                    data-mode="5"
                    data-id_value="<?= $id ?>"
                    data-value="<?= $f5Attr ?>"
                    title="Double-click untuk edit"
                  ><?= $f5Attr ?></span>%
                <?php } else { ?>
                  <?= $f5Attr ?>%
                <?php } ?>
              </div>
            </div>
          </div>
        <?php } ?>

        <div id="plg-empty-filter" class="hidden px-4 py-12 text-center">
          <p class="m-0 text-base font-extrabold text-mdl-ink">Tidak ada hasil</p>
          <p class="m-0 mt-1 text-sm text-mdl-soft">Coba kata kunci lain.</p>
        </div>
      <?php } ?>
    </div>
  </div>
</div>

<script>
(function ($) {
  var $root = $('#plg-root');
  if (!$root.length) return;

  var editing = false;

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function applyFilter() {
    var q = ($('#plg-filter').val() || '').toLowerCase().trim();
    var hp = ($('#plg-hp').val() || '').toLowerCase();
    var hpTail = hp.length >= 8 ? hp.substring(hp.length - 8) : hp;
    var nama = ($('#plg-nama').val() || '').toLowerCase().trim();
    var visible = 0;

    $root.find('.plg-row').each(function () {
      var blob = ($(this).attr('data-search') || '').toLowerCase();
      var ok = true;
      if (q && blob.indexOf(q) === -1) ok = false;
      if (ok && nama && blob.indexOf(nama) === -1) ok = false;
      if (ok && hpTail && blob.indexOf(hpTail) === -1) ok = false;
      $(this).toggle(ok);
      if (ok) visible++;
    });

    var $emptyFilter = $('#plg-empty-filter');
    if ($emptyFilter.length) {
      $emptyFilter.toggleClass('hidden', visible > 0 || $root.find('.plg-row').length === 0);
    }
  }

  $('#plg-form').on('submit', function (e) {
    e.preventDefault();
    var $btn = $('#plg-submit');
    $btn.prop('disabled', true);
    $.ajax({
      url: $(this).attr('action'),
      data: $(this).serialize(),
      type: $(this).attr('method'),
      success: function (response) {
        if (String(response).trim() === '1') {
          location.reload(true);
        } else {
          alert(response);
          $btn.prop('disabled', false);
        }
      },
      error: function () {
        alert('Gagal menambah pelanggan');
        $btn.prop('disabled', false);
      }
    });
  });

  $root.on('dblclick', '.plg-edit', function () {
    if (editing) return;
    editing = true;

    var $span = $(this);
    var id_value = $span.attr('data-id_value');
    var value = $span.attr('data-value');
    var mode = $span.attr('data-mode');
    var value_before = value;

    var inputHtml;
    if (mode === '5') {
      inputHtml = "<input type='number' id='plg-value' class='w-16 rounded-lg border border-mdl-accent px-2 py-1 text-sm font-bold outline-none ring-2 ring-mdl-accent/30' value='" + escapeHtml(value) + "'>";
    } else {
      inputHtml = "<input type='text' id='plg-value' class='min-w-[10rem] rounded-lg border border-mdl-accent px-2 py-1 text-sm font-bold outline-none ring-2 ring-mdl-accent/30' value='" + escapeHtml(value) + "'>";
    }
    $span.html(inputHtml);

    var $input = $('#plg-value');
    $input.focus().select();

    function finish(restore) {
      if (restore) {
        if (mode === '1') {
          $span.html(escapeHtml(String(value_before).toUpperCase()));
        } else {
          $span.html(escapeHtml(value_before));
        }
      }
      editing = false;
    }

    $input.on('keydown', function (ev) {
      if (ev.key === 'Escape') {
        finish(true);
      }
      if (ev.key === 'Enter') {
        $(this).blur();
      }
    });

    $input.on('focusout', function () {
      var value_after = $(this).val();
      if (value_after === value_before || value_after.length === 0) {
        finish(true);
        return;
      }

      $.ajax({
        url: '<?= URL::BASE_URL ?>Data_List/updateCell/<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>',
        data: {
          id: id_value,
          value: value_after,
          mode: mode
        },
        type: 'POST',
        dataType: 'html',
        success: function () {
          $span.attr('data-value', value_after);
          if (mode === '1') {
            $span.html(escapeHtml(String(value_after).toUpperCase()));
          } else {
            $span.html(escapeHtml(value_after));
          }
          var $row = $span.closest('.plg-row');
          var blob = ($row.find('.plg-edit').map(function () {
            return $(this).attr('data-value');
          }).get().join(' ') + ' ' + id_value).toLowerCase();
          $row.attr('data-search', blob);
          editing = false;
        },
        error: function () {
          finish(true);
          alert('Gagal menyimpan');
        }
      });
    });
  });

  $('#plg-filter, #plg-nama, #plg-hp').on('keyup input', applyFilter);
})(jQuery);
</script>
