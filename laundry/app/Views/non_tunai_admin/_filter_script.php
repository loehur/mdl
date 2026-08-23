<script>
(function () {
  var maxDays = <?= (int) ($data['maxRangeDays'] ?? 7) ?> - 1;
  var form = document.getElementById('ntaFilterForm');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    var start = form.querySelector('[name=start]').value;
    var end = form.querySelector('[name=end]').value;
    if (!start || !end) return;

    var d1 = new Date(start + 'T00:00:00');
    var d2 = new Date(end + 'T00:00:00');
    var diff = Math.round((d2 - d1) / 86400000);

    if (diff < 0) {
      e.preventDefault();
      alert('Tanggal akhir harus setelah tanggal awal');
      return;
    }
    if (diff >= maxDays + 1) {
      e.preventDefault();
      alert('Rentang tanggal maksimal ' + (maxDays + 1) + ' hari');
    }
  });
})();
</script>
