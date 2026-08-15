<?php
$plgAddMode = $data['plg_add_mode'] ?? 'list';
$plgAddFormClass = $plgAddMode === 'order' ? 'ord-plg-modal__body' : 'ord-plg-form';
?>
<style>
  .ord-plg-form,
  .ord-plg-cek,
  .ord-plg-input,
  .ord-plg-msg,
  .ord-plg-submit,
  .ord-plg-exist-item,
  .ord-plg-exist-pakai,
  .ord-plg-ready__icon {
    border-radius: 0 !important;
  }
  .ord-plg-form { padding: 0; }
  .ord-plg-label {
    display: block;
    margin: 0 0 6px;
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #1e293b;
  }
  .ord-plg-input {
    width: 100%;
    margin-bottom: 10px;
    padding: 10px 12px;
    border: 1px solid #94a3b8;
    border-radius: 0;
    background: #fff;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    font-size: 0.92rem;
    font-weight: 800;
    color: #0f172a;
    outline: none;
  }
  .ord-plg-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
  }
  .ord-plg-hp-row {
    display: flex;
    gap: 8px;
    align-items: stretch;
    margin-bottom: 10px;
  }
  .ord-plg-hp-row .ord-plg-input { margin-bottom: 0; flex: 1; }
  .ord-plg-cek {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 0;
    padding: 10px 14px;
    background: linear-gradient(180deg, #2563eb, #1d4ed8);
    color: #fff;
    font-family: inherit;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
    white-space: nowrap;
  }
  .ord-plg-cek:disabled { opacity: 0.6; cursor: not-allowed; }
  .ord-plg-msg {
    margin: 0 0 10px;
    padding: 8px 10px;
    border-radius: 0;
    font-size: 0.78rem;
    font-weight: 800;
    background: linear-gradient(180deg, #fef2f2, #fff);
    border: 1px solid #fca5a5;
    color: #b91c1c;
  }
  .ord-plg-msg.is-hidden { display: none; }
  .ord-plg-result.is-hidden { display: none; }
  .ord-plg-ready { text-align: center; padding: 8px 0 2px; }
  .ord-plg-ready__icon {
    width: 48px;
    height: 48px;
    margin: 4px auto 12px;
    border-radius: 0;
    display: grid;
    place-items: center;
    font-size: 1.25rem;
    color: #fff;
    background: linear-gradient(180deg, #16a34a, #15803d);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    animation: ordPlgPop .42s cubic-bezier(.2,1.4,.4,1);
  }
  @keyframes ordPlgPop {
    0% { transform: scale(0.55); opacity: 0; }
    70% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
  }
  .ord-plg-ready__title {
    margin: 0 0 14px;
    font-size: 15px;
    font-weight: 900;
    color: #15803d;
  }
  .ord-plg-exist-title {
    margin: 0 0 10px;
    font-size: 13px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.35;
  }
  .ord-plg-exist-list { max-height: 240px; overflow-y: auto; }
  .ord-plg-exist-item {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
    padding: 8px;
    background: linear-gradient(180deg, #fffbeb, #fff);
    border: 1px solid #fcd34d;
    cursor: pointer;
  }
  .ord-plg-exist-item:last-child { margin-bottom: 0; }
  .ord-plg-exist-item:hover { border-color: #d97706; background: #fffbeb; }
  .ord-plg-exist-item .ord-plg-input { margin-bottom: 0; flex: 1; cursor: text; }
  .ord-plg-exist-pakai {
    flex: 0 0 auto;
    border: 0;
    padding: 9px 12px;
    background: linear-gradient(180deg, #16a34a, #15803d);
    color: #fff;
    font-family: inherit;
    font-size: 0.72rem;
    font-weight: 900;
    cursor: pointer;
    white-space: nowrap;
  }
  .ord-plg-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    border: 0;
    border-radius: 0;
    padding: 12px 14px;
    background: linear-gradient(180deg, #16a34a, #15803d);
    color: #fff;
    font-family: inherit;
    font-size: 0.95rem;
    font-weight: 900;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
  }
  .ord-plg-submit:disabled { opacity: 0.6; cursor: not-allowed; }
  .ord-plg-newanyway {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed #f59e0b;
  }
  .ord-plg-newanyway p {
    margin: 0 0 10px;
    font-size: 12px;
    font-weight: 800;
    color: #b45309;
    line-height: 1.35;
  }
  .ord-plg-submit--alt {
    background: linear-gradient(180deg, #f59e0b, #d97706);
    color: #111;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
  }
</style>

<form id="ordPlgForm" class="<?= htmlspecialchars($plgAddFormClass, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
  <label class="ord-plg-label" for="ordPlgHp">Nomor HP</label>
  <div class="ord-plg-hp-row">
    <input type="text" id="ordPlgHp" name="f2" class="ord-plg-input" required placeholder="08…" inputmode="tel">
    <button type="submit" class="ord-plg-cek" id="ordPlgCek">
      <i class="fas fa-search"></i> Cek
    </button>
  </div>

  <label class="ord-plg-label" for="ordPlgNama">Nama/Panggilan</label>
  <input type="text" id="ordPlgNama" name="f1" class="ord-plg-input" placeholder="Nama/Panggilan">

  <p class="ord-plg-msg is-hidden" id="ordPlgMsg"></p>
  <div class="ord-plg-result is-hidden" id="ordPlgResult"></div>
</form>

<script>
(function ($) {
  var CFG = {
    cekUrl: <?= json_encode(URL::BASE_URL . 'Pelanggan/cekHp') ?>,
    tambahUrl: <?= json_encode(URL::BASE_URL . 'Pelanggan/tambah') ?>,
    pilihUrl: <?= json_encode(URL::BASE_URL . 'Pelanggan/pilih') ?>
  };

  window.resetPelangganFormTambah = function () {
    $("#ordPlgMsg").addClass("is-hidden").text("");
    $("#ordPlgResult").addClass("is-hidden").empty();
    if ($("#ordPlgForm")[0]) {
      $("#ordPlgForm")[0].reset();
    }
    $("#ordPlgCek").prop("disabled", false);
  };

  window.cekOrdPlgHp = function () {
    var $btn = $("#ordPlgCek");
    var $msg = $("#ordPlgMsg");
    var $resBox = $("#ordPlgResult");
    $msg.addClass("is-hidden").text("");
    $resBox.addClass("is-hidden").empty();
    $btn.prop("disabled", true);

    $.ajax({
      url: CFG.cekUrl,
      type: "POST",
      dataType: "json",
      data: { f2: $("#ordPlgHp").val() },
      success: function (res) {
        $btn.prop("disabled", false);
        if (!res || !res.ok) {
          $msg.removeClass("is-hidden").text((res && res.msg) ? res.msg : "Gagal cek nomor");
          return;
        }
        if (res.exists && res.items && res.items.length) {
          var html = '<p class="ord-plg-exist-title">Nomor tersebut sudah terdaftar, silahkan gunakan:</p>';
          html += '<div class="ord-plg-exist-list">';
          res.items.forEach(function (it) {
            var nama = String(it.nama || "");
            var id = String(it.id || "");
            html += '<div class="ord-plg-exist-item" data-id="' + id.replace(/"/g, "") + '">'
              + '<input type="text" class="ord-plg-input ord-plg-exist-nama" value="' + $("<div>").text(nama).html() + '">'
              + '<button type="button" class="ord-plg-exist-pakai">Pakai</button>'
              + "</div>";
          });
          html += "</div>";
          html += '<div class="ord-plg-newanyway">'
            + '<p>Atau tetap tambah dengan <b>nama baru</b> (isi Nama/Panggilan di atas). Nama yang cuma mirip akan ditolak.</p>'
            + '<button type="button" class="ord-plg-submit ord-plg-submit--alt" id="ordPlgSimpanBaru">'
            + '<i class="fas fa-plus"></i> Simpan nama baru</button>'
            + "</div>";
          $resBox.html(html).removeClass("is-hidden");
          setTimeout(function () { $("#ordPlgNama").trigger("focus"); }, 40);
          return;
        }
        $resBox.html(
          '<div class="ord-plg-ready">'
          + '<div class="ord-plg-ready__icon"><i class="fas fa-user-check"></i></div>'
          + '<p class="ord-plg-ready__title">Pelanggan siap ditambahkan</p>'
          + '<button type="button" class="ord-plg-submit" id="ordPlgSimpan">'
          + '<i class="fas fa-plus"></i> Simpan Pelanggan</button>'
          + "</div>"
        ).removeClass("is-hidden");
        setTimeout(function () { $("#ordPlgNama").trigger("focus"); }, 40);
      },
      error: function () {
        $btn.prop("disabled", false);
        $msg.removeClass("is-hidden").text("Gagal cek nomor");
      }
    });
  };

  window.simpanOrdPlgBaru = function (cekMirip) {
    var $btn = cekMirip ? $("#ordPlgSimpanBaru") : $("#ordPlgSimpan");
    var $msg = $("#ordPlgMsg");
    $msg.addClass("is-hidden").text("");
    if ($.trim($("#ordPlgNama").val()) === "") {
      $msg.removeClass("is-hidden").text("Isi Nama/Panggilan dulu");
      $("#ordPlgNama").trigger("focus");
      return;
    }
    $btn.prop("disabled", true);
    var labelAsli = $btn.html();
    if (cekMirip) {
      $btn.html('<i class="fas fa-spinner fa-spin"></i> Cek nama…');
    }
    $.ajax({
      url: CFG.tambahUrl,
      type: "POST",
      dataType: "json",
      data: {
        f1: $("#ordPlgNama").val(),
        f2: $("#ordPlgHp").val(),
        cek_mirip: cekMirip ? 1 : 0
      },
      success: function (res) {
        $btn.html(labelAsli);
        if (!res || !res.ok) {
          $msg.removeClass("is-hidden").text((res && res.msg) ? res.msg : "Gagal menambah pelanggan");
          $btn.prop("disabled", false);
          return;
        }
        if (typeof window.onPelangganPicked === "function") {
          window.onPelangganPicked(res);
        } else {
          location.reload(true);
        }
        $btn.prop("disabled", false);
      },
      error: function () {
        $btn.html(labelAsli);
        $msg.removeClass("is-hidden").text("Gagal menambah pelanggan");
        $btn.prop("disabled", false);
      }
    });
  };

  window.pakaiOrdPlgLama = function ($item) {
    if (!$item || !$item.length) return;
    var $msg = $("#ordPlgMsg");
    var $btn = $item.find(".ord-plg-exist-pakai");
    $msg.addClass("is-hidden").text("");
    $btn.prop("disabled", true);
    $.ajax({
      url: CFG.pilihUrl,
      type: "POST",
      dataType: "json",
      data: {
        id: $item.attr("data-id"),
        nama: $item.find(".ord-plg-exist-nama").val()
      },
      success: function (res) {
        if (!res || !res.ok) {
          $msg.removeClass("is-hidden").text((res && res.msg) ? res.msg : "Gagal memakai pelanggan");
          $btn.prop("disabled", false);
          return;
        }
        if (typeof window.onPelangganPicked === "function") {
          window.onPelangganPicked(res);
        } else {
          location.reload(true);
        }
        $btn.prop("disabled", false);
      },
      error: function () {
        $msg.removeClass("is-hidden").text("Gagal memakai pelanggan");
        $btn.prop("disabled", false);
      }
    });
  };

  $(document).off("submit.ordPlg", "#ordPlgForm").on("submit.ordPlg", "#ordPlgForm", function (e) {
    e.preventDefault();
    window.cekOrdPlgHp();
  });
  $(document).off("input.ordPlgHp", "#ordPlgHp").on("input.ordPlgHp", "#ordPlgHp", function () {
    $("#ordPlgResult").addClass("is-hidden").empty();
    $("#ordPlgMsg").addClass("is-hidden").text("");
  });
  $(document).off("click.ordPlgSimpan", "#ordPlgSimpan").on("click.ordPlgSimpan", "#ordPlgSimpan", function (e) {
    e.preventDefault();
    window.simpanOrdPlgBaru(false);
  });
  $(document).off("click.ordPlgSimpanBaru", "#ordPlgSimpanBaru").on("click.ordPlgSimpanBaru", "#ordPlgSimpanBaru", function (e) {
    e.preventDefault();
    window.simpanOrdPlgBaru(true);
  });
  $(document).off("click.ordPlgPakai", ".ord-plg-exist-pakai").on("click.ordPlgPakai", ".ord-plg-exist-pakai", function (e) {
    e.preventDefault();
    e.stopPropagation();
    window.pakaiOrdPlgLama($(this).closest(".ord-plg-exist-item"));
  });
  $(document).off("click.ordPlgItem", ".ord-plg-exist-item").on("click.ordPlgItem", ".ord-plg-exist-item", function (e) {
    if ($(e.target).closest("input, .ord-plg-exist-pakai").length) return;
    window.pakaiOrdPlgLama($(this));
  });
  $(document).off("keydown.ordPlgExistNama", ".ord-plg-exist-nama").on("keydown.ordPlgExistNama", ".ord-plg-exist-nama", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      window.pakaiOrdPlgLama($(this).closest(".ord-plg-exist-item"));
    }
  });
})(jQuery);
</script>
