<?php
$kodeCabang = htmlspecialchars((string) ($data['kode_cabang'] ?? ''));
$laundryNama = htmlspecialchars((string) ($this->dCabang['nama'] ?? 'MDL'));
$now = date('Y-m-d H:i:s');
$pelangganList = $data['pelanggan_list'] ?? [];
$dataMain = $data['data_main'] ?? [];
?>
<div class="pl-result">
  <style>
    #pl-root .pl-result { min-width: 0; }
    #pl-root .pl-nota-list { display: grid; gap: 10px; margin-bottom: 12px; }
    #pl-root .pl-card {
      border: 1px solid #cbd5e1;
      background: #fff;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }
    #pl-root .pl-card__head {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 8px;
      padding: 12px 14px;
      border-bottom: 1px solid #e2e8f0;
      background: linear-gradient(180deg, #f8fafc, #fff);
    }
    #pl-root .pl-card__id {
      font-size: 0.84rem;
      font-weight: 800;
      color: #334155;
    }
    #pl-root .pl-card__name {
      display: block;
      margin-top: 2px;
      font-size: 0.95rem;
      font-weight: 900;
      color: #0f172a;
      text-transform: uppercase;
    }
    #pl-root .pl-card__body {
      padding: 12px 14px;
      display: grid;
      gap: 8px;
    }
    @media (min-width: 700px) {
      #pl-root .pl-card__body { grid-template-columns: 1fr 1fr 1fr; }
    }
    #pl-root .pl-k {
      font-size: 0.72rem;
      font-weight: 900;
      color: #1e293b;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 2px;
    }
    #pl-root .pl-v {
      font-size: 0.86rem;
      font-weight: 800;
      color: #0f172a;
    }
    #pl-root .pl-badge {
      display: inline-flex;
      padding: 3px 8px;
      border: 1px solid #93c5fd;
      background: #eff6ff;
      color: #1d4ed8;
      font-size: 0.72rem;
      font-weight: 900;
      margin-right: 4px;
    }
    #pl-root .pl-print-wrap {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: flex-start;
    }
    #pl-root .pl-print-box {
      background: #fff;
      border: 1px solid #86efac;
      padding: 10px;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }
    #pl-root .pl-print-box table {
      width: 100%;
      border-collapse: collapse;
      text-align: center;
    }
    #pl-root .pl-print-box td {
      padding: 4px 0;
      font-weight: 800;
      color: #0f172a;
    }
    #pl-root .pl-print-box h1 {
      margin: 0;
      font-size: 1.6rem;
      font-weight: 900;
      text-transform: uppercase;
    }
    #pl-root .pl-print-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
  </style>

  <div class="pl-panel" style="border-color:#86efac;background:linear-gradient(180deg,#f0fdf4,#fff)">
    <h3 class="pl-panel__title">
      <span class="pl-ico" style="background:#16a34a"><i class="fas fa-receipt"></i></span>
      Nota ditemukan — Outlet <?= $kodeCabang ?>
    </h3>

    <div class="pl-nota-list">
      <?php foreach ($dataMain as $sale) {
        $id = $sale['id_penjualan'];
        $idPel = (int) ($sale['id_pelanggan'] ?? 0);
        $nama = '';
        foreach ($pelangganList as $pl) {
          if ((int) $pl['id_pelanggan'] === $idPel) {
            $nama = $pl['nama_pelanggan'];
            break;
          }
        }
        $jenis = '';
        foreach ($this->dPenjualan as $pj) {
          if ($pj['id_penjualan_jenis'] == $sale['id_penjualan_jenis']) {
            $jenis = $pj['penjualan_jenis'];
            break;
          }
        }
        $durasi = '';
        foreach ($this->dDurasi as $d) {
          if ($d['id_durasi'] == $sale['id_durasi']) {
            $durasi = strtoupper($d['durasi']);
            break;
          }
        }
        $qty = $this->fmtDecMax2(round((float) $sale['qty'], 2));
        $tgl = date('d-m-Y H:i', strtotime($sale['insertTime']));
      ?>
        <article class="pl-card">
          <div class="pl-card__head">
            <div>
              <span class="pl-card__id">#<?= htmlspecialchars((string) $id) ?></span>
              <span class="pl-card__name"><?= htmlspecialchars($nama) ?></span>
            </div>
            <div>
              <span class="pl-badge"><?= htmlspecialchars($jenis) ?></span>
              <span class="pl-badge"><?= htmlspecialchars($durasi) ?></span>
            </div>
          </div>
          <div class="pl-card__body">
            <div>
              <div class="pl-k">Qty</div>
              <div class="pl-v"><?= htmlspecialchars($qty) ?></div>
            </div>
            <div>
              <div class="pl-k">Masuk</div>
              <div class="pl-v"><?= htmlspecialchars($tgl) ?></div>
            </div>
            <div>
              <div class="pl-k">Pelanggan ID</div>
              <div class="pl-v">#<?= (int) $idPel ?></div>
            </div>
          </div>
        </article>
      <?php } ?>
    </div>
  </div>

  <?php foreach ($pelangganList as $pl) {
    $namaPel = htmlspecialchars($pl['nama_pelanggan']);
    $printId = 'print_' . (int) $pl['id_pelanggan'];
  ?>
  <div class="pl-panel">
    <h3 class="pl-panel__title">
      <span class="pl-ico" style="background:#16a34a"><i class="fas fa-print"></i></span>
      Label yang akan dicetak
    </h3>
    <div class="pl-print-wrap">
      <div class="pl-print-box">
        <div id="<?= $printId ?>">
          <table>
            <tr>
              <td>
                <b><?= $laundryNama ?> - <?= $kodeCabang ?></b><br>
                <?= $now ?>
              </td>
            </tr>
            <tr id="dashRow"><td></td></tr>
            <tr>
              <td><h1><b><?= $namaPel ?></b></h1></td>
            </tr>
            <tr id="dashRow"><td></td></tr>
            <tr>
              <td><?= URL::PACK_ROWS ?><b>- <?= $kodeCabang ?> -</b></td>
            </tr>
          </table>
        </div>
      </div>
      <div class="pl-print-actions">
        <button type="button" class="pl-btn pl-btn--primary pl-btn-cetak" data-print-id="<?= $printId ?>">
          <i class="fas fa-print"></i> Cetak Label
        </button>
      </div>
    </div>
  </div>
  <?php } ?>
</div>

<script>
(function() {
  function plPrint(printId, btn) {
    var el = document.getElementById(printId);
    if (!el) return;

    var prev = btn ? btn.innerHTML : '';
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="pl-spin" aria-hidden="true"></span> Mencetak…';
    }

    var rows = el.querySelectorAll("tr");
    var lines = [];
    var width = parseInt(localStorage.getItem("escpos_width") || "32", 10) || 32;

    var makeDash = function(w) {
      return Array(w + 1).join("-");
    };

    var sanitizeServerTd = function(td) {
      try {
        var s = td.innerHTML || "";
        s = s.replace(/<br\s*\/?>/gi, "[[BR]]");
        s = s.replace(/<h1[^>]*>/gi, "[[H1]]").replace(/<\/h1>/gi, "[[/H1]]");
        s = s.replace(/<b[^>]*>/gi, "[[B]]").replace(/<\/b>/gi, "[[/B]]");
        s = s.replace(/&nbsp;/gi, " ");
        s = s.replace(/\u00a0/g, " ");
        s = s.replace(/<[^>]+>/gi, "");
        s = s.replace(/[\r\n]+/g, " ");
        s = s.replace(/[ \t]+/g, " ").trim();
        return s;
      } catch (e) {
        return "";
      }
    };

    for (var i = 0; i < rows.length; i++) {
      var tr = rows[i];
      var tds = tr.querySelectorAll("td");
      if (tr.id && tr.id.toLowerCase() === "dashrow") {
        lines.push("[[TR]][[TD]]" + makeDash(width) + "[[/TD]][[/TR]]");
        continue;
      }
      if (tds.length === 0) continue;
      var v = sanitizeServerTd(tds[0]);
      lines.push("[[TR]][[TD]]" + v + "[[/TD]][[/TR]]");
    }

    lines = lines.filter(function(s) {
      var inner = String(s || "").replace(/\[\[(?:\/)?(?:TR|TD)\]\]/g, "");
      return inner.trim().length > 0;
    });

    var plain = lines.map(function(s) {
      s = String(s || "");
      s = s.replace(/\[\[BR\]\]/g, "<br>");
      s = s.replace(/\[\[B\]\]/g, "<b>");
      s = s.replace(/\[\[\/B\]\]/g, "</b>");
      s = s.replace(/\[\[H1\]\]/g, "<h1>");
      s = s.replace(/\[\[\/H1\]\]/g, "</h1>");
      s = s.replace(/\[\[TD\]\]/g, "<td>");
      s = s.replace(/\[\[\/TD\]\]/g, "</td>");
      s = s.replace(/\[\[TR\]\]/g, "<tr>");
      s = s.replace(/\[\[\/TR\]\]/g, "</tr>");
      return s;
    }).join("");

    var printFn = (window.PrintServer && window.PrintServer.fetch)
      ? window.PrintServer.fetch.bind(window.PrintServer)
      : window.printServerFetch;
    var errMsg = (window.PrintServer && window.PrintServer.errorMessage)
      ? window.PrintServer.errorMessage
      : window.printServerErrorMessage;

    printFn("/print", {
      text: plain,
      margin_top: 0,
      feed_lines: 3
    })
    .then(function(res) { return res.text().catch(function() { return ""; }); })
    .then(function() {
      if (window.MdlToast) MdlToast.ok("Label dikirim ke printer");
    })
    .catch(function(err) {
      var msg = typeof errMsg === "function" ? errMsg(err) : "Print server tidak aktif";
      if (window.MdlToast) MdlToast.error(msg);
      else if (window.PrintServer && typeof window.PrintServer.showAlert === "function") {
        window.PrintServer.showAlert(msg, "error");
      } else {
        alert(msg);
      }
      var a = window.open("");
      a.document.write("<html><title>Print Page</title><body>" + el.innerHTML + "</body></html>");
      a.print();
      setTimeout(function() { try { a.close(); } catch (e) {} }, 1000);
    })
    .finally(function() {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = prev || '<i class="fas fa-print"></i> Cetak Label';
      }
    });
  }

  $(document).off("click.plCetak", ".pl-btn-cetak").on("click.plCetak", ".pl-btn-cetak", function() {
    plPrint($(this).data("print-id"), this);
  });
})();
</script>
