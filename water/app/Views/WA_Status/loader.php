<div class="row mx-0">
  <div class="col pt-2">
    <div id="load">
      <div class="text-nowrap text-center">
        <i class="fas fa-spinner text-warning"></i> Loading...
      </div>
    </div>
  </div>
</div>

<script>
  $(document).ready(function() {
    reload();
  });

  function reload() {
    $("div#load").load("<?= URL::BASE_URL ?>WA_Status/content");
    setTimeout(reload, 5000);
  }

  reload();
</script>