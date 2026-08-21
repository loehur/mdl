(function (window, $) {
  'use strict';

  function isMinyakKendaraan(val) {
    if (!val) {
      return false;
    }
    var parts = String(val).split('<explode>');
    var name = (parts[1] || '').toUpperCase();
    return name.indexOf('MINYAK') >= 0 && name.indexOf('KENDARAAN') >= 0;
  }

  function syncKeteranganMode($form) {
    if (!$form || !$form.length) {
      return;
    }
    var val = $form.find('[name=f1a]').val();
    var minyak = isMinyakKendaraan(val);
    var $free = $form.find('.pg-ket-normal');
    var $minyakWrap = $form.find('.pg-ket-minyak');
    var $freeInput = $form.find('.pg-ket-input-free');
    var $sel = $form.find('.pg-ket-kendaraan-select');
    var $lain = $form.find('.pg-ket-lainnya');
    var $label = $form.find('.pg-ket-label');

    if (minyak) {
      $label.text('Kendaraan');
      $free.addClass('d-none');
      $minyakWrap.removeClass('d-none');
      $freeInput.prop('required', false).prop('disabled', true);
      $sel.prop('required', true).prop('disabled', false);
    } else {
      $label.text('Keterangan/Banyak');
      $free.removeClass('d-none');
      $minyakWrap.addClass('d-none');
      $freeInput.prop('required', false).prop('disabled', false);
      $sel.prop('required', false).prop('disabled', true).val('');
      $lain.addClass('d-none').prop('required', false).prop('disabled', true).val('');
    }
  }

  function syncLainnyaField($form) {
    var $sel = $form.find('.pg-ket-kendaraan-select');
    var $lain = $form.find('.pg-ket-lainnya');
    var lainnya = String($sel.find('option:selected').attr('data-lainnya')) === '1';
    if (lainnya) {
      $lain.removeClass('d-none').prop('required', true).prop('disabled', false);
    } else {
      $lain.addClass('d-none').prop('required', false).prop('disabled', true).val('');
    }
  }

  function prepareSubmit($form) {
    if (($form.attr('action') || '').indexOf('insert_pengeluaran') < 0) {
      return true;
    }
    if (!isMinyakKendaraan($form.find('[name=f1a]').val())) {
      return true;
    }

    var $sel = $form.find('.pg-ket-kendaraan-select');
    var id = $sel.val();
    if (!id) {
      alert('Pilih kendaraan terlebih dahulu.');
      $sel.focus();
      return false;
    }

    var $opt = $sel.find('option:selected');
    var lainnya = String($opt.attr('data-lainnya')) === '1';
    var $lain = $form.find('.pg-ket-lainnya');
    var $freeInput = $form.find('.pg-ket-input-free');

    if (lainnya) {
      var text = $.trim($lain.val());
      if (!text) {
        alert('Keterangan wajib diisi untuk opsi Lainnya.');
        $lain.focus();
        return false;
      }
      $freeInput.val(text);
    } else {
      $freeInput.val($.trim($opt.text()));
    }

    return true;
  }

  function bindJenisSelect($select) {
    var $form = $select.closest('form');
    var el = $select[0];
    if (el && el.selectize) {
      el.selectize.on('change', function () {
        syncKeteranganMode($form);
        syncLainnyaField($form);
      });
      return;
    }
    $select.on('change', function () {
      syncKeteranganMode($form);
      syncLainnyaField($form);
    });
  }

  function init() {
    $('select.jenisKeluar, select.jenisKeluarBesar').each(function () {
      bindJenisSelect($(this));
      syncKeteranganMode($(this).closest('form'));
    });

    $(document).on('change', '.pg-ket-kendaraan-select', function () {
      syncLainnyaField($(this).closest('form'));
    });

    $('.modal').on('show.bs.modal', function () {
      var $form = $(this).find('form[action*="insert_pengeluaran"]');
      if ($form.length) {
        syncKeteranganMode($form);
        syncLainnyaField($form);
      }
    });
  }

  window.KasPengeluaranKendaraan = {
    init: init,
    prepareSubmit: prepareSubmit,
    syncKeteranganMode: syncKeteranganMode
  };
})(window, jQuery);
