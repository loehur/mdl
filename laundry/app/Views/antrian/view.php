<div id="load" class="antrian-load"></div>
<script>
  var mode = "<?= $data['modeView'] ?>"

  $(document).ready(function() {
    loadContent();
  });

  $("body").dblclick(function() {
    $(".modal").hide();
  })

  function loadContent() {
    // Show loading spinner
    $("div#load").html(`
      <div class="d-flex justify-content-center align-items-center py-5">
        <div class="text-center">
          <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem; color:#2563eb;">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-muted mb-0" style="color:#1e293b;">Memuat antrian...</p>
        </div>
      </div>
    `);
    
    // Load content via AJAX
    var url = "<?= URL::BASE_URL ?>Antrian/loadList/" + <?= $data['modeView'] ?>;
    url += '?_=' + Date.now(); // Cache busting
    $("div#load").load(url, function() {
      if (typeof window.antrianAfterLoad === "function") {
        window.antrianAfterLoad();
      }
    });
  }

  $('span.clearTuntas').click(function() {
    $("div.backShow").removeClass('d-none');
  });
</script>