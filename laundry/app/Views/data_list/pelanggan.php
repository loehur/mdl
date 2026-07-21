<?php
$page = $data['z']['page'];
$rows = $data['data_main'] ?? [];
$total = is_array($rows) ? count($rows) : 0;
$canEditPartner = ((int) $this->id_privilege === 100);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>
  #plg-root {
    --j-ink: #0B3D3A;
    --j-ink-soft: #134E4A;
    --j-foam: #F3F7F6;
    --j-card: #FFFFFF;
    --j-line: #D7E5E2;
    --j-mint: #2A9D8F;
    --j-amber: #E9A319;
    --j-coral: #E76F51;
    --j-muted: #5F7370;
    --j-radius: 18px;
    --j-font: "Plus Jakarta Sans", "Segoe UI", sans-serif;

    font-family: var(--j-font);
    color: var(--j-ink);
    padding: 4px 2px 40px;
    -webkit-font-smoothing: antialiased;
  }
  #plg-root * { box-sizing: border-box; }

  #plg-root .plg-toolbar {
    background: var(--j-card);
    border: 1px solid var(--j-line);
    border-radius: var(--j-radius);
    padding: 14px;
    margin-bottom: 12px;
    box-shadow: 0 8px 22px rgba(11, 61, 58, 0.04);
  }

  #plg-root .plg-search-wrap {
    position: relative;
    margin-bottom: 12px;
  }
  #plg-root .plg-search-wrap i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--j-muted);
    font-size: 0.85rem;
    pointer-events: none;
  }
  #plg-root .plg-input {
    width: 100%;
    border: 1px solid var(--j-line);
    background: #fff;
    border-radius: 12px;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--j-ink);
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  #plg-root .plg-input--search { padding-left: 38px; }
  #plg-root .plg-input:focus {
    border-color: var(--j-mint);
    box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.18);
  }
  #plg-root .plg-input::placeholder { color: #8A9E9B; font-weight: 500; }

  #plg-root .plg-form {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
  }
  #plg-root .plg-form-row {
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 8px;
  }
  #plg-root .plg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    border: 0;
    border-radius: 12px;
    padding: 11px 14px;
    background: linear-gradient(145deg, var(--j-ink) 0%, var(--j-ink-soft) 100%);
    color: #F8FFFE;
    font-family: inherit;
    font-size: 0.86rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    cursor: pointer;
    box-shadow: 0 10px 22px rgba(11, 61, 58, 0.18);
    transition: transform .12s ease, opacity .12s ease;
  }
  #plg-root .plg-btn:active { transform: scale(0.98); opacity: 0.92; }
  #plg-root .plg-btn:disabled { opacity: 0.6; cursor: not-allowed; }

  #plg-root .plg-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: min(72vh, 680px);
    overflow-y: auto;
    padding-bottom: 4px;
  }

  #plg-root .plg-card {
    background: var(--j-card);
    border: 1px solid var(--j-line);
    border-radius: var(--j-radius);
    padding: 14px;
    margin: 0;
    box-shadow: 0 8px 22px rgba(11, 61, 58, 0.04);
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  #plg-root .plg-card:hover {
    border-color: rgba(42, 157, 143, 0.45);
    box-shadow: 0 10px 26px rgba(11, 61, 58, 0.08);
  }

  #plg-root .plg-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
  }
  #plg-root .plg-card-head strong,
  #plg-root .plg-edit--nama {
    display: inline-block;
    max-width: 100%;
    font-size: 0.95rem;
    font-weight: 750;
    letter-spacing: -0.02em;
    line-height: 1.25;
    color: var(--j-ink);
  }
  #plg-root .plg-card-meta {
    display: block;
    margin-top: 4px;
    font-size: 0.74rem;
    color: var(--j-muted);
    line-height: 1.4;
  }
  #plg-root .plg-card-meta .plg-dot {
    margin: 0 4px;
    opacity: 0.55;
  }

  #plg-root .plg-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 999px;
    white-space: nowrap;
    background: #EEF3F2;
    color: var(--j-muted);
  }
  #plg-root .plg-badge--partner {
    background: rgba(42, 157, 143, 0.14);
    color: #1A7A6E;
  }

  #plg-root .plg-edit {
    cursor: text;
    border-radius: 4px;
    padding: 0 1px;
  }
  #plg-root .plg-edit:hover {
    background: rgba(42, 157, 143, 0.1);
  }

  #plg-root .plg-inline {
    width: auto;
    min-width: 8rem;
    max-width: 100%;
    padding: 4px 8px;
    border: 1px solid var(--j-mint);
    border-radius: 8px;
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--j-ink);
    outline: none;
    box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.2);
  }
  #plg-root .plg-inline--sm {
    min-width: 3.25rem;
    width: 3.25rem;
  }

  #plg-root .plg-empty {
    text-align: center;
    padding: 36px 16px;
    color: var(--j-muted);
    font-size: 0.86rem;
    background: var(--j-card);
    border: 1px dashed var(--j-line);
    border-radius: var(--j-radius);
  }
  #plg-root .plg-empty b {
    display: block;
    color: var(--j-ink);
    font-size: 0.95rem;
    margin-bottom: 4px;
  }
  #plg-root .plg-empty.is-hidden { display: none; }
</style>

<div id="plg-root">
  <div class="plg-toolbar">
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

    <form id="plg-form" class="plg-form" action="<?= URL::BASE_URL; ?>Data_List/insert/<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>" method="POST">
      <div class="plg-form-row">
        <input type="text" id="plg-hp" name="f2" class="plg-input" required autocomplete="off" placeholder="Nomor HP">
        <input type="text" id="plg-nama" name="f1" class="plg-input" required autocomplete="off" placeholder="Nama pelanggan">
      </div>
      <button type="submit" id="plg-submit" class="plg-btn">
        <i class="fas fa-plus"></i>
        Tambah pelanggan
      </button>
    </form>
  </div>

  <div class="plg-list" id="plg-list">
    <?php if ($total === 0) { ?>
      <div class="plg-empty" id="plg-empty-all">
        <b>Belum ada pelanggan</b>
        Tambah pelanggan baru lewat form di atas.
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
        $partnerClass = ((float) $f5 > 0) ? 'plg-badge plg-badge--partner' : 'plg-badge';
      ?>
        <article class="plg-card plg-row" data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
          <div class="plg-card-head">
            <div style="min-width:0">
              <strong>
                <span
                  class="plg-edit plg-edit--nama"
                  data-mode="1"
                  data-id_value="<?= $id ?>"
                  data-value="<?= $f1Attr ?>"
                  title="Double-click untuk edit"
                ><?= $f1Html ?></span>
              </strong>
              <span class="plg-card-meta">
                #<?= $id ?>
                <span class="plg-dot">·</span>
                <span
                  class="plg-edit"
                  data-mode="2"
                  data-id_value="<?= $id ?>"
                  data-value="<?= $f2Attr ?>"
                  title="Double-click untuk edit"
                ><?= $f2Html ?></span>
              </span>
            </div>
            <span class="<?= $partnerClass ?>">
              Partner
              <?php if ($canEditPartner) { ?>
                <span
                  class="plg-edit"
                  data-mode="5"
                  data-id_value="<?= $id ?>"
                  data-value="<?= $f5Attr ?>"
                  title="Double-click untuk edit"
                ><?= $f5Attr ?></span>%
              <?php } else { ?>
                <?= $f5Attr ?>%
              <?php } ?>
            </span>
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
      $emptyFilter.toggleClass('is-hidden', visible > 0 || $root.find('.plg-row').length === 0);
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

    var inputClass = mode === '5' ? 'plg-inline plg-inline--sm' : 'plg-inline';
    var inputType = mode === '5' ? 'number' : 'text';
    $span.html(
      "<input type='" + inputType + "' id='plg-value' class='" + inputClass + "' value='" + escapeHtml(value) + "'>"
    );

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
      if (ev.key === 'Escape') finish(true);
      if (ev.key === 'Enter') $(this).blur();
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
