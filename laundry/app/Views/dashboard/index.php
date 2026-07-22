<style>
  /* Dashboard Admin — selaras chrome MDL (topnav/sidenav) + teks lebih tajam */
  .dash-admin-wrap {
    max-width: 920px;
    margin: 0 auto;
    padding: 10px 10px 18px;
    font-family: 'fontku', sans-serif;
    color: var(--mdl-ink, #243041);
  }

  .dash-mode-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
  }

  .dash-mode-btn {
    box-sizing: border-box;
    height: 34px;
    min-width: 140px;
    padding: 0 14px;
    border: 1.5px solid var(--mdl-line, #b8c4d2);
    border-radius: 0;
    background: var(--mdl-surface, #f4f7fb);
    color: var(--mdl-ink, #243041);
    font-family: 'fontku', sans-serif;
    font-size: 13px;
    font-weight: 800;
    line-height: 1;
    cursor: pointer;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
  }
  .dash-mode-btn:hover {
    background: #fff;
    border-color: #c0cad6;
  }
  .dash-mode-btn.is-active[data-mode="cuci"] {
    border-color: transparent;
    background: var(--mdl-accent, #3f74d4);
    color: #fff;
  }
  .dash-mode-btn.is-active[data-mode="cuci"]:hover {
    background: var(--mdl-accent-deep, #2f61bc);
    color: #fff;
  }
  .dash-mode-btn.is-active[data-mode="setrika"] {
    border-color: transparent;
    background: var(--mdl-live, #2f9e5f);
    color: #fff;
  }
  .dash-mode-btn.is-active[data-mode="setrika"]:hover {
    background: var(--mdl-live-deep, #268750);
    color: #fff;
  }

  .dash-empty {
    padding: 28px 14px;
    text-align: center;
    background: var(--mdl-surface, #f4f7fb);
    border: 1.5px solid var(--mdl-line, #b8c4d2);
    color: var(--mdl-ink, #243041);
  }
  .dash-empty__title {
    font-size: 15px;
    font-weight: 800;
    color: #0d1117;
    margin-bottom: 4px;
  }
  .dash-empty__sub {
    font-size: 14px;
    font-weight: 700;
    color: var(--mdl-ink, #243041);
  }

  .dash-loading {
    text-align: center;
    padding: 22px 12px;
    font-size: 14px;
    font-weight: 700;
    color: var(--mdl-ink, #243041);
  }

  .dash-board {
    background: #fff;
    border: 1.5px solid var(--mdl-line, #b8c4d2);
    box-shadow: var(--mdl-shadow, 0 5px 16px rgba(36, 48, 65, 0.12));
  }

  .dash-board__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 10px;
    color: #0d1117;
    font-weight: 800;
  }
  .dash-board--cuci .dash-board__head {
    background: linear-gradient(to bottom, #9ec0f0 0%, #b6d0f5 45%, #cfe0f8 100%);
    box-shadow: inset 0 -1px 0 rgba(47, 97, 188, 0.35);
  }
  .dash-board--setrika .dash-board__head {
    background: linear-gradient(to bottom, #8fd4ad 0%, #a8dfbf 45%, #c5ebd4 100%);
    box-shadow: inset 0 -1px 0 rgba(38, 135, 80, 0.35);
  }
  .dash-board__title {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: #0d1117;
    line-height: 1.25;
  }
  .dash-board__badge {
    flex-shrink: 0;
    height: 24px;
    padding: 0 8px;
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .03em;
    text-transform: uppercase;
    border: 1.5px solid rgba(13, 17, 23, 0.12);
    background: rgba(255, 255, 255, 0.72);
    color: #0d1117;
  }

  .dash-grid {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
  }
  .dash-grid thead th {
    padding: 8px 10px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #0d1117;
    background: var(--mdl-surface-2, #e5ecf4);
    border-bottom: 1.5px solid var(--mdl-line, #b8c4d2);
    white-space: nowrap;
  }
  .dash-grid thead th:first-child { text-align: left; }
  .dash-grid thead th:not(:first-child) { text-align: right; }

  .dash-grid tbody td {
    padding: 8px 10px;
    border-bottom: 1px solid #dce3ec;
    vertical-align: middle;
    background: #fff;
    color: #0d1117;
    font-weight: 700;
  }
  .dash-grid tbody tr:nth-child(even) td {
    background: var(--mdl-surface, #f4f7fb);
  }
  .dash-grid tbody tr:hover td {
    background: var(--mdl-accent-soft, #d9e6fa);
  }
  .dash-board--setrika .dash-grid tbody tr:hover td {
    background: #e8f6ee;
  }
  .dash-grid tbody tr:last-child td {
    border-bottom: 0;
  }

  .dash-cabang {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 26px;
    padding: 0 8px;
    border: 1.5px solid transparent;
    background: var(--mdl-accent, #3f74d4);
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: .03em;
  }
  .dash-board--setrika .dash-cabang {
    background: var(--mdl-live, #2f9e5f);
  }

  .dash-metric {
    text-align: right;
    white-space: nowrap;
  }
  .dash-qty {
    font-size: 15px;
    font-weight: 800;
    color: #0d1117;
    font-variant-numeric: tabular-nums;
  }
  .dash-diff {
    display: inline-flex;
    align-items: center;
    height: 22px;
    margin-left: 6px;
    padding: 0 7px;
    border: 1.5px solid transparent;
    font-size: 12px;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    vertical-align: middle;
  }
  .dash-diff--below {
    color: #7a4b00;
    background: linear-gradient(180deg, #fff8e8 0%, #ffefc8 100%);
    border-color: rgba(201, 148, 20, 0.28);
  }
  .dash-diff--above {
    color: #0d3d22;
    background: linear-gradient(180deg, #eaf8ef 0%, #d4efde 100%);
    border-color: rgba(38, 135, 80, 0.25);
  }
  .dash-diff--flat {
    color: #243041;
    background: #eef2f6;
    border-color: var(--mdl-line, #b8c4d2);
  }

  .dash-alert {
    margin-bottom: 8px;
    padding: 8px 10px;
    border: 1.5px solid rgba(201, 148, 20, 0.35);
    background: linear-gradient(180deg, #fff8e8 0%, #ffefc8 100%);
    color: #3d2a00;
    font-size: 14px;
    font-weight: 700;
  }
  .dash-empty-row {
    text-align: center !important;
    color: var(--mdl-ink, #243041) !important;
    padding: 18px 10px !important;
    font-size: 14px;
    font-weight: 700;
  }
</style>

<div class="dash-admin-wrap">
  <div class="dash-mode-row">
    <button type="button" class="dash-mode-btn" data-mode="cuci" id="btnDashCuci">Antri Cuci</button>
    <button type="button" class="dash-mode-btn" data-mode="setrika" id="btnDashSetrika">Antri Setrika/Pack</button>
  </div>

  <div id="dashboardEmpty" class="dash-empty">
    <div class="dash-empty__title">Dashboard Admin</div>
    <div class="dash-empty__sub">Pilih mode untuk memuat ringkasan per cabang.</div>
  </div>

  <div id="dashboardContent" class="d-none"></div>
</div>

<script>
  (function () {
    var base = "<?= URL::BASE_URL ?>";
    var $empty = $("#dashboardEmpty");
    var $content = $("#dashboardContent");

    function setActive(mode) {
      $(".dash-mode-btn").removeClass("is-active");
      if (mode === "cuci") {
        $("#btnDashCuci").addClass("is-active");
      } else if (mode === "setrika") {
        $("#btnDashSetrika").addClass("is-active");
      }
    }

    function loadMode(mode) {
      if (!mode) {
        return;
      }
      setActive(mode);

      var url = mode === "cuci"
        ? base + "Dashboard/loadCuci"
        : base + "Dashboard/loadSetrikaPack";

      $empty.addClass("d-none");
      $content.removeClass("d-none").html('<div class="dash-loading">Memuat ringkasan...</div>');

      $content.load(url, function (response, status) {
        if (status === "error") {
          $content.html('<div class="dash-alert">Gagal memuat data dashboard.</div>');
        }
      });
    }

    $("#btnDashCuci").on("click", function () {
      loadMode("cuci");
    });
    $("#btnDashSetrika").on("click", function () {
      loadMode("setrika");
    });
  })();
</script>
