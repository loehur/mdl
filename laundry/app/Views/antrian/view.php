<div id="load"></div>
<script>
  var mode = <?= (int) $data['modeView'] ?>;
  var isLaundryMode = (mode !== 100);
  var antrianOffset = 0;
  var antrianLoading = false;
  var antrianHasMore = true;
  var antrianQuery = '';

  $(document).ready(function() {
    loadContent({ append: false });
  });

  $("body").dblclick(function() {
    $(".modal").hide();
  });

  window.antrianSearch = function(q) {
    antrianQuery = q || '';
    antrianOffset = 0;
    antrianHasMore = true;
    loadContent({ append: false, q: antrianQuery });
  };

  function loadContent(opts) {
    opts = opts || {};
    var append = !!opts.append;
    var q = (opts.q !== undefined) ? opts.q : antrianQuery;

    if (antrianLoading) return;
    if (append && (!isLaundryMode || !antrianHasMore)) return;

    antrianLoading = true;

    if (!append) {
      antrianOffset = 0;
      antrianHasMore = true;
      $("div#load").html(`
        <div class="d-flex justify-content-center align-items-center py-5">
          <div class="text-center">
            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mb-0">Memuat antrian...</p>
          </div>
        </div>
      `);
    } else {
      $("#antrianLoadMore").removeClass('d-none').html(`
        <div class="text-center py-3">
          <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
          <span class="text-muted ms-2">Memuat data...</span>
        </div>
      `);
    }

    var url = "<?= URL::BASE_URL ?>Antrian/loadList/" + mode;
    if (isLaundryMode) {
      url += "?offset=" + antrianOffset + "&limit=20";
      if (q && q.length >= 2) {
        url += "&q=" + encodeURIComponent(q);
      }
      url += "&_=" + Date.now();
    } else {
      url += "?_=" + Date.now();
    }

    $.get(url)
      .done(function(html) {
        if (append) {
          var nodes = $.parseHTML(html, document, true);
          var $frag = $(nodes);
          var $cards = $frag.filter('.antrian-card');
          if ($cards.length) {
            $("#antrianList").append($cards);
          }
          $frag.filter('script').each(function() {
            $.globalEval(this.text || this.textContent || this.innerHTML || '');
          });
        } else {
          $("div#load").html(html);
        }

        if (window.antrianPage) {
          antrianHasMore = !!window.antrianPage.hasMore;
          antrianOffset = window.antrianPage.nextOffset || 0;
        } else {
          antrianHasMore = false;
        }

        if (!antrianHasMore) {
          $("#antrianLoadMore").addClass('d-none').empty();
        } else {
          $("#antrianLoadMore").removeClass('d-none').html('<div class="text-center py-2 text-muted small">Scroll untuk memuat lagi</div>');
        }
      })
      .fail(function() {
        if (!append) {
          $("div#load").html('<div class="alert alert-danger m-2">Gagal memuat antrian.</div>');
        }
        $("#antrianLoadMore").html('<div class="text-center py-2 text-danger small">Gagal memuat. Scroll untuk coba lagi.</div>');
      })
      .always(function() {
        antrianLoading = false;
      });
  }

  if (isLaundryMode) {
    $(window).on('scroll.antrianInfinite', function() {
      if (antrianLoading || !antrianHasMore) return;
      var sentinel = document.getElementById('antrianSentinel');
      if (!sentinel) return;
      var rect = sentinel.getBoundingClientRect();
      if (rect.top < window.innerHeight + 250) {
        loadContent({ append: true });
      }
    });
  }

  $('span.clearTuntas').click(function() {
    $("div.backShow").removeClass('d-none');
  });
</script>
