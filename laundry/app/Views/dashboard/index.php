<style>
  .dash-admin-wrap { max-width: 1100px; }
  .dash-mode-btn {
    border: 1px solid #ced4da;
    background: #fff;
    color: #334155;
    border-radius: .5rem;
    padding: .65rem 1rem;
    font-weight: 600;
    min-width: 180px;
    transition: background .15s ease, border-color .15s ease, box-shadow .15s ease;
  }
  .dash-mode-btn:hover {
    border-color: #94a3b8;
    background: #f8fafc;
  }
  .dash-mode-btn.is-active {
    border-color: #0d6efd;
    background: #eef5ff;
    color: #0b3d91;
    box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .15);
  }
  .dash-empty {
    border: 1px dashed #cbd5e1;
    border-radius: .75rem;
    background: #f8fafc;
    color: #64748b;
    padding: 2.5rem 1.25rem;
    text-align: center;
  }
  .dash-card .card-header {
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
  }
  .dash-kpi {
    border: 1px solid #e2e8f0;
    border-radius: .65rem;
    background: #fff;
    padding: .85rem 1rem;
    height: 100%;
  }
  .dash-kpi__label {
    font-size: .78rem;
    letter-spacing: .03em;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
  }
  .dash-kpi__value {
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
  }
  .dash-kpi__sub {
    font-size: .85rem;
    color: #64748b;
  }
  .dash-kpi--due { border-left: 4px solid #dc3545; }
  .dash-kpi--today { border-left: 4px solid #0d6efd; }
  .dash-kpi--yesterday { border-left: 4px solid #6c757d; }
  .dash-kpi--besok { border-left: 4px solid #fd7e14; }
  .dash-table th {
    white-space: nowrap;
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: .02em;
    color: #475569;
  }
  .dash-table td { vertical-align: middle; }
  .dash-qty { font-weight: 700; color: #0f172a; }
  .dash-nota { color: #64748b; font-size: .85rem; }
</style>

<div class="container-fluid px-3 py-3 dash-admin-wrap">
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <button type="button" class="dash-mode-btn" data-mode="cuci" id="btnDashCuci">
      <i class="fas fa-tint me-1"></i> Antrian Cuci
    </button>
    <button type="button" class="dash-mode-btn" data-mode="setrika" id="btnDashSetrika">
      <i class="fas fa-tshirt me-1"></i> Antrian Setrika/Pack
    </button>
  </div>

  <div id="dashboardEmpty" class="dash-empty">
    <div class="fw-bold mb-1">Dashboard Admin</div>
    <div>Pilih <b>Antrian Cuci</b> atau <b>Antrian Setrika/Pack</b> untuk memuat ringkasan seluruh cabang.</div>
  </div>

  <div id="dashboardContent" class="d-none"></div>
</div>

<script>
  (function () {
    var base = "<?= URL::BASE_URL ?>";
    var $empty = $("#dashboardEmpty");
    var $content = $("#dashboardContent");
    var activeMode = "";

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
      activeMode = mode;
      setActive(mode);

      var url = mode === "cuci"
        ? base + "Dashboard/loadCuci"
        : base + "Dashboard/loadSetrikaPack";

      $empty.addClass("d-none");
      $content.removeClass("d-none").html(
        '<div class="text-muted py-4 text-center"><i class="fas fa-spinner fa-spin me-1"></i> Memuat ringkasan...</div>'
      );

      $content.load(url, function (response, status) {
        if (status === "error") {
          $content.html('<div class="alert alert-danger mb-0">Gagal memuat data dashboard.</div>');
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
