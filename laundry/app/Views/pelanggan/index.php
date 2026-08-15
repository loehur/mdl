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
  }
  #plg-root .plg-card:hover {
    border-color: #2563eb;
    box-shadow: 0 12px 26px rgba(37, 99, 235, 0.14);
  }
  #plg-root .plg-card.is-partner {
    border-color: #fcd34d;
    background: linear-gradient(180deg, #fffbeb, #fff);
  }
  #plg-root .plg-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
  }
  #plg-root .plg-card-head strong,
  #plg-root .plg-edit--nama {
    display: inline-block;
    max-width: 100%;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.01em;
    line-height: 1.3;
    color: var(--plg-ink);
  }
  #plg-root .plg-card-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--plg-muted);
    line-height: 1.35;
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
    justify-content: flex-end;
    margin-top: 10px;
    padding-top: 8px;
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

  #plg-root .plg-edit {
    cursor: text;
    padding: 0 1px;
  }
  #plg-root .plg-edit:hover {
    background: rgba(37, 99, 235, 0.12);
  }
  #plg-root .plg-inline {
    width: auto;
    min-width: 7rem;
    max-width: 100%;
    padding: 3px 7px;
    border: 1px solid var(--plg-blue);
    font-family: inherit;
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--plg-ink);
    outline: none;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
  }
  #plg-root .plg-inline--sm {
    min-width: 3rem;
    width: 3rem;
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
              $isPartner = ((float) $f5 > 0);
              $cardClass = $isPartner ? 'plg-card plg-row is-partner' : 'plg-card plg-row';
              $chipClass = $isPartner ? 'plg-chip plg-chip--yellow' : 'plg-chip';
              $hpDigits = preg_replace('/\D/', '', (string) $f2);
              $canChat = strlen($hpDigits) >= 8;
            ?>
              <article class="<?= $cardClass ?>" data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
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
                      <span class="plg-badge">#<?= $id ?></span>
                      <span
                        class="plg-edit plg-chip plg-chip--blue"
                        data-mode="2"
                        data-id_value="<?= $id ?>"
                        data-value="<?= $f2Attr ?>"
                        title="Double-click untuk edit"
                      ><?= $f2Html ?></span>
                    </span>
                  </div>
                  <span class="<?= $chipClass ?>">
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
                <div class="plg-card-actions">
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
    var hp = ($('#ordPlgHp').val() || '').toLowerCase();
    var hpTail = hp.length >= 8 ? hp.substring(hp.length - 8) : hp;
    var nama = ($('#ordPlgNama').val() || '').toLowerCase().trim();
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

    var $count = $('#plg-count');
    if ($count.length) $count.text(visible);

    var $emptyFilter = $('#plg-empty-filter');
    if ($emptyFilter.length) {
      $emptyFilter.toggleClass('is-hidden', visible > 0 || $root.find('.plg-row').length === 0);
    }
  }

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
        url: '<?= URL::BASE_URL ?>Pelanggan/updateCell',
        data: {
          id: id_value,
          value: value_after,
          mode: mode
        },
        type: 'POST',
        dataType: 'html',
        success: function (res) {
          var raw = String(res || '').trim();
          if (raw !== '' && raw !== '0') {
            finish(true);
            toast(raw, 'error');
            return;
          }
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
          $row.toggleClass('is-partner', mode === '5' ? parseFloat(value_after) > 0 : $row.hasClass('is-partner'));
          var $chat = $row.find('.plg-chat-btn');
          if ($chat.length) {
            if (mode === '1') $chat.attr('data-nama', value_after);
            if (mode === '2') {
              $chat.attr('data-hp', value_after);
              $chat.prop('disabled', String(value_after).replace(/\D/g, '').length < 8);
            }
          }
          editing = false;
          toast('Tersimpan', 'ok');
        },
        error: function () {
          finish(true);
          toast('Gagal menyimpan', 'error');
        }
      });
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
