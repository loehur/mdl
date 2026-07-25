<style>
  .ord-item-fo {
    font-family: 'fontku', 'Segoe UI', sans-serif;
    color: #0f172a;
  }
  .ord-item-fo__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 55%, #16a34a 100%);
    color: #fff;
  }
  .ord-item-fo__head h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 900;
    letter-spacing: -0.02em;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
  }
  .ord-item-fo__head small {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    font-weight: 750;
    opacity: 0.95;
  }
  .ord-item-fo__close {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 0;
    background: rgba(255,255,255,.2);
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .ord-item-fo__close:hover { background: rgba(255,255,255,.32); }
  .ord-item-fo__body {
    padding: 14px 16px 8px;
    background:
      radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.10), transparent 50%),
      linear-gradient(180deg, #eff6ff, #fff);
  }
  .ord-item-fo__foot {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    padding: 12px 16px 16px;
    background: #fff;
    border-top: 1px solid #e2e8f0;
  }
  .ord-item-fo__label {
    display: block;
    margin: 0 0 5px;
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #1e293b;
  }
  .ord-item-fo__field { margin-bottom: 12px; }
  .ord-item-fo__input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #94a3b8;
    border-radius: 0;
    background: #fff;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    outline: none;
  }
  .ord-item-fo__input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
  }
  .ord-item-fo__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: 0;
    border-radius: 0;
    padding: 12px 14px;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    font-size: 0.95rem;
    font-weight: 900;
    cursor: pointer;
  }
  .ord-item-fo__btn--ghost {
    background: #e2e8f0;
    color: #0f172a;
  }
  .ord-item-fo__btn--primary {
    background: linear-gradient(135deg, #15803d, #16a34a);
    color: #fff;
    box-shadow: 0 8px 18px rgba(22, 163, 74, 0.3);
  }
  .ord-item-fo__btn--primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  /* Selectize: satu border saja */
  .ord-item-fo select.tize,
  .ord-item-fo select.selectized {
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    padding: 0 !important;
  }
  .ord-item-fo .selectize-control,
  .ord-item-fo .selectize-control.single {
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    margin: 0;
  }
  .ord-item-fo .selectize-control.single .selectize-input {
    border: 1px solid #94a3b8 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: #fff !important;
    font-weight: 800;
    min-height: 42px;
    padding: 10px 12px;
  }
  .ord-item-fo .selectize-control.single .selectize-input.focus {
    border-color: #2563eb !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22) !important;
  }
  .ord-item-fo .selectize-control.single .selectize-input:after {
    border: 0 !important;
  }
  .ord-item-fo .selectize-control.single .selectize-input input {
    width: auto !important;
    min-width: 8rem !important;
    opacity: 1 !important;
    position: relative !important;
    left: 0 !important;
    pointer-events: auto !important;
    color: #0f172a !important;
    font-weight: 800 !important;
  }
  .ord-item-fo .selectize-dropdown {
    border: 1px solid #94a3b8 !important;
    border-radius: 0 !important;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12) !important;
    z-index: 30 !important;
  }
  .ord-item-fo .selectize-dropdown .option {
    font-weight: 750;
    color: #0f172a;
    padding: 8px 12px;
  }
  .ord-item-fo .selectize-dropdown .option.active {
    background: #eff6ff;
    color: #1d4ed8;
  }
</style>

<form class="ord-item-fo" action="<?= URL::BASE_URL ?>Penjualan/addItem/<?= $data['id'] ?>" method="POST" autocomplete="off">
  <div class="ord-item-fo__head">
    <div>
      <h3 id="ordItemTitle">Tambah Item</h3>
      <small>Cari dan pilih item laundry</small>
    </div>
    <button type="button" class="ord-item-fo__close" data-ord-item-close aria-label="Tutup">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <div class="ord-item-fo__body">
    <div class="ord-item-fo__field">
      <label class="ord-item-fo__label" for="ord_item_select">Item</label>
      <select id="ord_item_select" name="f1" class="tize" style="width:100%" required>
        <?php foreach ($this->dItem as $c) { ?>
          <option value="<?= (int) $c['id_item'] ?>"><?= htmlspecialchars($c['item']) ?></option>
        <?php } ?>
      </select>
    </div>
    <div class="ord-item-fo__field">
      <label class="ord-item-fo__label" for="ord_item_qty">Banyak</label>
      <input type="number" id="ord_item_qty" name="f2" class="ord-item-fo__input" min="1" value="1" required>
    </div>
  </div>

  <div class="ord-item-fo__foot">
    <button type="button" class="ord-item-fo__btn ord-item-fo__btn--ghost" data-ord-item-close>Batal</button>
    <button type="submit" class="ord-item-fo__btn ord-item-fo__btn--primary">
      <i class="fas fa-plus"></i> Tambah
    </button>
  </div>
</form>

<script src="<?= URL::EX_ASSETS ?>js/selectize.min.js"></script>
<script>
  $(document).ready(function() {
    var $sel = $("#ord_item_select");
    if ($sel.length && !$sel[0].selectize) {
      $sel.selectize({
        placeholder: "Cari item…",
        sortField: "text",
        persist: false,
        onInitialize: function () {
          // Jangan biarkan item kosong/pertama "terpilih diam-diam"
          // (gejala: harus Backspace dulu baru bisa ketik)
          this.clear(true);
        }
      });
    }

    function closeItemModal() {
      if (typeof window.closeOrdItemModal === "function") {
        window.closeOrdItemModal();
        return;
      }
      var $bs = $("#exampleModal2");
      if ($bs.length && typeof bootstrap !== "undefined" && bootstrap.Modal) {
        var inst = bootstrap.Modal.getInstance($bs[0]);
        if (inst) inst.hide();
        else $bs.hide();
      } else {
        $(".modal").hide();
      }
    }

    $(document).off("click.ordItemClose", "[data-ord-item-close]").on("click.ordItemClose", "[data-ord-item-close]", function(e) {
      e.preventDefault();
      closeItemModal();
    });

    $("form.ord-item-fo").off("submit.ordItem").on("submit.ordItem", function(e) {
      e.preventDefault();
      var $form = $(this);
      var $btn = $form.find('button[type="submit"]');
      if ($btn.data("loading")) return;

      var itemVal = $sel[0].selectize ? $sel[0].selectize.getValue() : $sel.val();
      if (!itemVal) {
        alert("Pilih item terlebih dahulu.");
        return;
      }

      var prevHtml = $btn.html();
      $btn.data("loading", 1).prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Memuat…');
      if (typeof window.setOrdCartLoading === "function") {
        window.setOrdCartLoading(true);
      }
      $.ajax({
        url: $form.attr("action"),
        data: $form.serialize(),
        type: $form.attr("method"),
        success: function() {
          closeItemModal();
          if (typeof window.reloadOrdCart === "function") {
            window.reloadOrdCart();
          } else {
            $("div#cart").load("<?= URL::BASE_URL ?>Penjualan/cart", function() {
              if (typeof window.setOrdCartLoading === "function") {
                window.setOrdCartLoading(false);
              }
            });
          }
        },
        error: function() {
          alert("Gagal menambah item.");
          $btn.data("loading", 0).prop("disabled", false).html(prevHtml);
          if (typeof window.setOrdCartLoading === "function") {
            window.setOrdCartLoading(false);
          }
        }
      });
    });
  });
</script>
