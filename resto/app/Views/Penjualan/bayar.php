<?php
$total = 0;
foreach ($data['order'] as $dk) {
  $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
  $total += $subTotal;
}

foreach ($data['bayar'] as $b) {
  $total -= $b['jumlah'];
}
?>

<div x-data="data">
  <!-- Normal Payment View -->
  <div x-show="!showQris">
    <div class="w-100 mt-2">
      <div class="text-center">Total</div>
      <div class="text-center fs-5 fw-bold"><?= number_format($total) ?></div>
    </div>
    <div class="w-100 mt-3">
      <div class="d-flex justify-content-center">
        <div class="px-1"><span x-on:click="total_bayar = <?= $total ?>" onclick="cash()" class="btn btn-outline-primary">Pas</span></div>
        <div class="px-1"><span x-on:click="total_bayar = 20000" onclick="cash()" class="pilihBayar btn btn-outline-primary">20.000</span></div>
        <div class="px-1"><span x-on:click="total_bayar = 50000" onclick="cash()" class="pilihBayar btn btn-outline-primary">50.000</span></div>
        <div class="px-1"><span x-on:click="total_bayar = 100000" onclick="cash()" class="pilihBayar btn btn-outline-primary">100.000</span></div>
      </div>
    </div>
    <div class="w-100 mt-3">
      <div class="text-center">Input Jumlah Bayar</div>
      <div class="text-center"><input x-model.number="total_bayar" class="border-top-0 border-start-0 border-end-0 border-bottom fs-2 text-success w-100 text-center" type="number"></div>
    </div>
    <div class="w-100 mt-3">
      <div class="text-center">Dibayar</div>
      <div class="text-center fs-5 fw-bold" x-text="number_format(total_bayar)"></div>
    </div>
    <div class="w-100 mt-3">
      <div class="d-flex justify-content-center">
        <div class="px-3 border-end">
          <div class="text-end">Kembalian</div>
          <div class="text-end fs-5 fw-bold text-danger" x-text="total_bayar - bill > 0 ? number_format(total_bayar - bill) : 0"></div>
        </div>
        <div class="px-3">
          <div class="text-center">Metode Bayar</div>
          <?php foreach (URL::METOD_BAYAR as $key => $value) { ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" value="<?= $key ?>" x-model="metodePilih" x-on:change="metodeBayar" name="metode" id="option<?= $key ?>">
              <label class="form-check-label" for="option<?= $key ?>">
                <?= strtoupper($value) ?>
              </label>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>

    <div class="w-100 mt-3">
      <div class="text-center">Catatan</div>
      <div class="text-center"><input name="catatan" x-bind:class="metodePilih == 1 ? '' : 'border-danger'" x-bind:required="metodePilih == 1 ? 0 : metodePilih" class="border-top-0 border-start-0 border-end-0 border-bottom fs-2 text-danger w-100 text-center" type="text"></div>
    </div>

    <div class="w-100 mt-4">
      <div class="text-center fs-5 fw-bold">
        <span class="btn btn-success w-100 bg-gradient rounded-0" 
              x-bind:class="isProcessing ? 'disabled opacity-50' : ''" 
              x-bind:style="isProcessing ? 'pointer-events: none' : ''"
              x-on:click="bayarOK()" 
              x-text="isProcessing ? 'Memproses...' : 'Bayar'"></span>
      </div>
    </div>
  </div>

  <!-- QRIS Payment View -->
  <div x-show="showQris" class="text-center">
    <div class="mb-3">
      <h5>Scan QR untuk Pembayaran</h5>
      <div class="text-muted">Total: <strong x-text="number_format(qrisNominal || total_bayar)"></strong></div>
    </div>
    
    <div class="d-flex justify-content-center my-3">
      <div id="qrcode" style="padding: 10px; background: white; display: inline-block;"></div>
    </div>
    
    <div class="my-3">
      <div class="spinner-border spinner-border-sm text-primary me-2" role="status" x-show="qrisStatus == 'pending' || qrisStatus == 'generating'"></div>
      <span x-text="qrisMessage" x-bind:class="qrisStatus == 'paid' ? 'text-success fw-bold' : (qrisStatus == 'expired' ? 'text-danger' : 'text-warning')"></span>
    </div>
    
    <div class="mt-3" x-show="qrisStatus == 'pending'">
      <button class="btn btn-primary w-100 mb-2" x-on:click="checkQrisStatus()">
        <i class="fas fa-sync-alt me-1"></i> Cek Status Pembayaran
      </button>
      <div class="text-muted small">
        <i class="fas fa-info-circle"></i> Klik tombol di atas jika sudah melakukan pembayaran.
      </div>
    </div>

    <div class="mt-3" x-show="qrisStatus == 'expired'">
      <button class="btn btn-warning w-100" x-on:click="regenerateQris()">
        <i class="fas fa-redo me-1"></i> Generate QR Baru
      </button>
    </div>
  </div>
</div>

<script src="<?= URL::ASSETS_URL ?>js/alpine.min.js" defer></script>
<script src="<?= URL::ASSETS_URL ?>js/qrcode.js"></script>

<script>
  function cash() {
    $('input#option1').click();
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('data', () => ({
      metodePilih: 1,
      bill: parseInt(<?= $total ?>),
      total_bayar: parseInt(<?= $total ?>),
      kembalian: 0,
      isProcessing: false,
      showQris: false,
      qrString: '',
      qrisStatus: 'pending',
      qrisMessage: 'Menunggu pembayaran...',
      qrisInterval: null,
      trxId: '',
      refBayar: '',
      qrisNominal: 0,

      init() {
        <?php if (isset($data['qris_pending'])) : ?>
          this.qrString = '<?= $data['qris_pending']['qr_string'] ?>';
          this.trxId = '<?= $data['qris_pending']['trx_id'] ?>';
          this.refBayar = '<?= $data['qris_pending']['ref_bayar'] ?>';
          this.qrisNominal = <?= $data['qris_pending']['nominal'] ?>;
          this.showQris = true;
          
          let elapsed = <?= $data['qris_pending']['elapsed'] ?? 0 ?>;
          if (elapsed >= 300) {
             this.qrisStatus = 'expired';
             this.qrisMessage = 'QR Code Expired';
          } else {
             this.qrisStatus = 'pending';
             this.qrisMessage = 'Melanjutkan pembayaran...';
             
             // Adjust start time to match server time
             this.offset = elapsed; 
          }
          
          setTimeout(() => {
            if (this.qrisStatus != 'expired') {
                $('#qrcode').empty();
                new QRCode(document.getElementById("qrcode"), {
                     text: this.qrString,
                     width: 200,
                     height: 200,
                     colorDark: "#000000",
                     colorLight: "#ffffff",
                     correctLevel: QRCode.CorrectLevel.M
                });
                // Start countdown with offset
                this.startCountdown(this.offset);
            }
          }, 300);
        <?php endif; ?>
      },

      metodeBayar() {
        // Tidak lagi auto-reset ke total agar partial payment tetap bisa dilakukan
        // dengan metode bayar apapun
      },

      bayarOK() {
        // Cegah double click
        if (this.isProcessing) {
          return;
        }

        let metode = $('input[name="metode"]:checked').val();

        if (this.total_bayar <= 0) {
          alert('Jumlah bayar tidak boleh kurang dari 0');
          return;
        }

        if ((this.metodePilih == 4 || this.metodePilih == 5) && $('input[name="catatan"]').val() == '') {
          alert('Catatan harus diisi');
          return;
        }

        // Set processing state
        this.isProcessing = true;

        console.log('DEBUG dibayar:', this.total_bayar, 'metode:', metode);

        // Untuk QRIS (metode = 2), panggil bayar() dulu untuk insert kas record
        // lalu panggil generate_qris() dengan ref_bayar yang didapat
        $.ajax({
          url: "<?= URL::BASE_URL ?>Penjualan/bayar",
          data: {
            ref: '<?= $data['ref'] ?>',
            dibayar: this.total_bayar,
            metode: metode
          },
          type: "POST",
          context: this,
          success: function(res) {
            console.log('Bayar Response:', res);
            
            // Cek apakah response adalah JSON (untuk QRIS)
            if (typeof res === 'object' && res.status === 'qris_pending') {
              // QRIS: lanjut generate QR dengan ref_bayar
              this.refBayar = res.ref_bayar;
              this.qrisNominal = res.nominal;
              this.generateQris(res.ref_bayar, res.ref, res.nominal);
              return;
            }
            
            // Non-QRIS response
            if (res == 0) {
              $('.offcanvas.show').each(function() {
                $(this).offcanvas('hide');
              });
              $('button.pilih[data-group=nomor][data-id=' + nomor + '][data-mode=' + mode_dt + ']').removeClass('border-2 border-dark');
              load_pesanan(mode_dt, nomor);
            } else if (res == 1) {
              $('.offcanvas.show').each(function() {
                $(this).offcanvas('hide');
              });
              load_pesanan(mode_dt, nomor);
            } else {
              console.log(res);
              // Reset processing state jika error
              this.isProcessing = false;
            }
          },
          error: function() {
            // Reset processing state jika error
            this.isProcessing = false;
            alert('Terjadi kesalahan. Silakan coba lagi.');
          }
        });
      },

      generateQris(ref_bayar, ref, nominal) {
        this.qrisStatus = 'generating';
        this.qrisMessage = 'Membuat QR Code...';

        $.ajax({
          url: "<?= URL::BASE_URL ?>Penjualan/generate_qris",
          data: {
            ref_bayar: ref_bayar,
            ref: ref,
            nominal: nominal
          },
          type: "POST",
          context: this,
          success: function(res) {
            console.log('QRIS Response:', res);
            if (res.status == 'success') {
              this.qrString = res.qr_string;
              this.trxId = res.trx_id;
              this.showQris = true;
              this.qrisStatus = 'pending';
              this.qrisMessage = 'Menunggu pembayaran...';
              
              // Render QR Code
              setTimeout(() => {
                $('#qrcode').empty();
                new QRCode(document.getElementById("qrcode"), {
                  text: this.qrString,
                  width: 200,
                  height: 200,
                  colorDark: "#000000",
                  colorLight: "#ffffff",
                  correctLevel: QRCode.CorrectLevel.M
                });
              }, 100);
              
              // Start Local Countdown
              this.startCountdown();
            } else {
              alert(res.msg || 'Gagal generate QRIS');
              this.isProcessing = false;
            }
          },
          error: function() {
            alert('Gagal membuat QRIS. Silakan coba lagi.');
            this.isProcessing = false;
          }
        });
      },

      startCountdown(offset = 0) {
        // Reset interval jika ada
        if (this.qrisInterval) clearInterval(this.qrisInterval);
        
        // Waktu mulai (lokal estimasi) - dikurangi offset agar lanjut
        this.startTime = Date.now() - (offset * 1000);
        this.duration = 300; // 5 menit

        // Update tampilan setiap detik secara visual saja
        this.qrisInterval = setInterval(() => {
          let elapsed = Math.floor((Date.now() - this.startTime) / 1000);
          let remaining = this.duration - elapsed;

          if (remaining <= 0) {
            this.qrisStatus = 'expired';
            this.qrisMessage = 'QR Code Expired';
            clearInterval(this.qrisInterval);
          } else {
            let mins = Math.floor(remaining / 60);
            let secs = remaining % 60;
            this.qrisMessage = `Menunggu pembayaran... (${mins}:${secs.toString().padStart(2, '0')})`;
          }
        }, 1000);
      },

      checkQrisStatus() {
        this.qrisMessage = 'Mengecek status...';
        
        $.ajax({
          url: "<?= URL::BASE_URL ?>Penjualan/check_qris_status",
          data: {
            ref: '<?= $data['ref'] ?>',
            ref_bayar: this.refBayar
          },
          type: "POST",
          context: this,
          success: function(res) {
            console.log('QRIS Status:', res);
            if (res.status == 'paid') {
              this.qrisStatus = 'paid';
              this.qrisMessage = '✓ Pembayaran Berhasil!';
              if (this.qrisInterval) clearInterval(this.qrisInterval);
              
              // Auto close dan refresh
              setTimeout(() => {
                $('.offcanvas.show').each(function() {
                  $(this).offcanvas('hide');
                });
                load_pesanan(mode_dt, nomor);
              }, 1500);
            } else if (res.status == 'expired') {
              this.qrisStatus = 'expired';
              this.qrisMessage = 'QR Code Expired (Server)';
              if (this.qrisInterval) clearInterval(this.qrisInterval);
            } else {
              // Masih pending, update timer berdasarkan server
              let elapsed = res.elapsed || 0;
              let remaining = 300 - elapsed;
              if (res.status == 'pending') {
                 // Sync local timer dengan server time
                 this.duration = 300; 
                 this.startTime = Date.now() - (elapsed * 1000);
              }
              
              let mins = Math.floor(remaining / 60);
              let secs = remaining % 60;
              this.qrisMessage = `Belum dibayar... (${mins}:${secs.toString().padStart(2, '0')})`;
            }
          },
          error: function() {
            console.log('Error checking QRIS status');
            this.qrisMessage = 'Gagal cek status';
          }
        });
      },

      regenerateQris() {
        this.qrisStatus = 'generating';
        this.qrisMessage = 'Sedang membuat QR baru...';

        // Generate QR baru menggunakan ref_bayar yang sudah ada
        $.ajax({
          url: "<?= URL::BASE_URL ?>Penjualan/generate_qris",
          data: {
            ref_bayar: this.refBayar,
            ref: '<?= $data['ref'] ?>',
            nominal: this.qrisNominal || this.total_bayar
          },
          type: "POST",
          context: this,
          success: function(res) {
            if (res.status == 'success') {
              this.qrString = res.qr_string;
              this.trxId = res.trx_id;
              this.qrisStatus = 'pending';
              this.qrisMessage = 'Menunggu pembayaran...';
              
              // Re-render QR Code
              $('#qrcode').empty();
              new QRCode(document.getElementById("qrcode"), {
                text: this.qrString,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
              });
              
              // Restart Countdown
              this.startCountdown();
            } else {
              this.qrisMessage = 'Gagal membuat QR baru';
            }
          },
          error: function() {
            this.qrisMessage = 'Error generate QR';
          }
        });
      },

      // cancelQris removed - QRIS payment must be completed

      number_format(number, decimals, dec_point, thousands_sep) {
        number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+number) ? 0 : +number,
          prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
          sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
          dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
          s = '',
          toFixedFix = function(n, prec) {
            var k = Math.pow(10, prec);
            return '' + Math.round(n * k) / k;
          };
        // Fix for IE parseFloat(0.55).toFixed(0) = 0;
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
          s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
          s[1] = s[1] || '';
          s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
      },
    }))
  })
</script>
