<style>
  .dash-admin-wrap {
    --dash-ink: #1a2332;
    --dash-muted: #6b7a90;
    --dash-line: #d8e0ea;
    --dash-surface: #ffffff;
    --dash-wash: #f3f6fa;
    --dash-cuci: #0f7a6c;
    --dash-cuci-soft: #e6f5f2;
    --dash-cuci-deep: #0a554c;
    --dash-setrika: #b45309;
    --dash-setrika-soft: #fff4e8;
    --dash-setrika-deep: #7c3a0a;
    --dash-good: #0f7a45;
    --dash-good-bg: #e8f7ee;
    --dash-warn: #b45309;
    --dash-warn-bg: #fff3e0;
    --dash-flat: #64748b;
    --dash-flat-bg: #eef2f7;
    max-width: 920px;
    margin: 0 auto;
    padding: 1rem .85rem 1.5rem;
    font-family: "Titillium Web", "Segoe UI", sans-serif;
  }

  .dash-mode-row {
    display: flex;
    flex-wrap: wrap;
    gap: .55rem;
    margin-bottom: 1rem;
  }

  .dash-mode-btn {
    appearance: none;
    border: 0;
    cursor: pointer;
    min-width: 148px;
    padding: .7rem 1.1rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: .95rem;
    letter-spacing: .01em;
    color: var(--dash-ink);
    background: var(--dash-wash);
    box-shadow: inset 0 0 0 1px var(--dash-line);
    transition: transform .12s ease, background .15s ease, color .15s ease, box-shadow .15s ease;
  }
  .dash-mode-btn:hover {
    transform: translateY(-1px);
    background: #e8eef6;
  }
  .dash-mode-btn.is-active[data-mode="cuci"] {
    color: #fff;
    background: linear-gradient(145deg, var(--dash-cuci) 0%, var(--dash-cuci-deep) 100%);
    box-shadow: 0 8px 18px rgba(15, 122, 108, .28);
  }
  .dash-mode-btn.is-active[data-mode="setrika"] {
    color: #fff;
    background: linear-gradient(145deg, #d97706 0%, var(--dash-setrika-deep) 100%);
    box-shadow: 0 8px 18px rgba(180, 83, 9, .28);
  }

  .dash-empty {
    border-radius: 18px;
    padding: 2.4rem 1.4rem;
    text-align: center;
    color: var(--dash-muted);
    background:
      radial-gradient(circle at top right, rgba(15, 122, 108, .08), transparent 40%),
      radial-gradient(circle at bottom left, rgba(180, 83, 9, .08), transparent 42%),
      var(--dash-wash);
    box-shadow: inset 0 0 0 1px var(--dash-line);
  }
  .dash-empty__title {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--dash-ink);
    margin-bottom: .35rem;
  }

  .dash-loading {
    text-align: center;
    color: var(--dash-muted);
    padding: 2rem 1rem;
    font-weight: 600;
  }

  .dash-board {
    border-radius: 18px;
    overflow: hidden;
    background: var(--dash-surface);
    box-shadow:
      0 1px 0 rgba(255,255,255,.7) inset,
      0 12px 28px rgba(26, 35, 50, .08);
  }
  .dash-board--cuci {
    box-shadow:
      0 0 0 1px rgba(15, 122, 108, .14),
      0 12px 28px rgba(15, 122, 108, .10);
  }
  .dash-board--setrika {
    box-shadow:
      0 0 0 1px rgba(180, 83, 9, .14),
      0 12px 28px rgba(180, 83, 9, .10);
  }

  .dash-board__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .95rem 1.1rem;
    color: #fff;
  }
  .dash-board--cuci .dash-board__head {
    background: linear-gradient(120deg, var(--dash-cuci) 0%, #149688 55%, var(--dash-cuci-deep) 100%);
  }
  .dash-board--setrika .dash-board__head {
    background: linear-gradient(120deg, #d97706 0%, #c2610c 55%, var(--dash-setrika-deep) 100%);
  }
  .dash-board__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: .01em;
  }
  .dash-board__badge {
    flex-shrink: 0;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: .28rem .55rem;
    border-radius: 999px;
    background: rgba(255,255,255,.18);
  }

  .dash-grid {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
  }
  .dash-grid thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    padding: .7rem 1rem;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--dash-muted);
    background: var(--dash-wash);
    border-bottom: 1px solid var(--dash-line);
    white-space: nowrap;
  }
  .dash-grid thead th:first-child { text-align: left; }
  .dash-grid thead th:not(:first-child) { text-align: right; }

  .dash-grid tbody td {
    padding: .78rem 1rem;
    border-bottom: 1px solid #edf1f6;
    vertical-align: middle;
    background: #fff;
    transition: background .12s ease;
  }
  .dash-grid tbody tr:nth-child(even) td {
    background: #f7fafc;
  }
  .dash-board--cuci .dash-grid tbody tr:nth-child(even) td {
    background: #f3faf8;
  }
  .dash-board--setrika .dash-grid tbody tr:nth-child(even) td {
    background: #fffaf4;
  }
  .dash-grid tbody tr:hover td {
    background: #eef6ff !important;
  }
  .dash-board--cuci .dash-grid tbody tr:hover td {
    background: var(--dash-cuci-soft) !important;
  }
  .dash-board--setrika .dash-grid tbody tr:hover td {
    background: var(--dash-setrika-soft) !important;
  }
  .dash-grid tbody tr:last-child td {
    border-bottom: 0;
  }

  .dash-cabang {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.6rem;
    padding: .22rem .55rem;
    border-radius: .55rem;
    font-weight: 800;
    font-size: .92rem;
    letter-spacing: .04em;
    color: var(--dash-ink);
    background: #e8eef6;
  }
  .dash-board--cuci .dash-cabang {
    color: var(--dash-cuci-deep);
    background: var(--dash-cuci-soft);
  }
  .dash-board--setrika .dash-cabang {
    color: var(--dash-setrika-deep);
    background: var(--dash-setrika-soft);
  }

  .dash-metric {
    text-align: right;
    white-space: nowrap;
  }
  .dash-qty {
    font-weight: 800;
    font-size: 1.05rem;
    color: var(--dash-ink);
    font-variant-numeric: tabular-nums;
  }
  .dash-diff {
    display: inline-block;
    margin-left: .4rem;
    padding: .12rem .42rem;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    vertical-align: middle;
  }
  .dash-diff--below {
    color: var(--dash-warn);
    background: var(--dash-warn-bg);
  }
  .dash-diff--above {
    color: var(--dash-good);
    background: var(--dash-good-bg);
  }
  .dash-diff--flat {
    color: var(--dash-flat);
    background: var(--dash-flat-bg);
  }

  .dash-alert {
    margin-bottom: .85rem;
    padding: .75rem 1rem;
    border-radius: 12px;
    font-weight: 600;
    color: #7a4b00;
    background: #fff6e5;
    box-shadow: inset 0 0 0 1px #f0d7a4;
  }
  .dash-empty-row {
    text-align: center;
    color: var(--dash-muted);
    padding: 1.6rem 1rem !important;
    font-weight: 600;
  }
</style>

<div class="dash-admin-wrap">
  <div class="dash-mode-row">
    <button type="button" class="dash-mode-btn" data-mode="cuci" id="btnDashCuci">Antri Cuci</button>
    <button type="button" class="dash-mode-btn" data-mode="setrika" id="btnDashSetrika">Antri Setrika/Pack</button>
  </div>

  <div id="dashboardEmpty" class="dash-empty">
    <div class="dash-empty__title">Dashboard Admin</div>
    <div>Pilih mode untuk memuat ringkasan per cabang.</div>
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
