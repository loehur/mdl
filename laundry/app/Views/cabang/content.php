<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

<?php $log = $this->dCabang; ?>

<style>
    #map {
        width: 100%;
        height: 300px;
        border-radius: 0;
    }
</style>

<form class="ajax" action="<?= URL::BASE_URL ?>Cabang_Lokasi/update" method="POST">
    <div class="container">
        <div style="max-width: 500px;">
            <div class="row">
                <div class="col px-1 mb-1">
                    <div class="form-floating">
                        <input id="latitude" class="form-control shadow-none alamat" name="lat" value="<?= htmlspecialchars((string) ($log['latt'] ?? '')) ?>" required />
                        <label for="latitude">Latitude</label>
                    </div>
                </div>
                <div class="col px-1 mb-1">
                    <div class="form-floating">
                        <input id="longitude" class="form-control shadow-none alamat" name="long" value="<?= htmlspecialchars((string) ($log['long'] ?? '')) ?>" required />
                        <label for="longitude">Longitude</label>
                    </div>
                </div>
            </div>
            <div class="row mt-1">
                <div class="col px-1 mb-1">
                    <div id="map"></div>
                </div>
            </div>
            <div class="row">
                <div class="col px-1 mb-1">
                    <div class="form-floating">
                        <input type="text" class="form-control shadow-none" name="gmaps" value="<?= htmlspecialchars((string) ($log['gmaps'] ?? '')) ?>" id="floatingGmaps" placeholder="https://maps.google.com/...">
                        <label for="floatingGmaps">Link Google Maps</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col px-1 mb-1">
                    <div class="form-floating">
                        <input type="text" class="form-control shadow-none" name="nama" required value="<?= strtoupper($log['nama'] ?? '') ?>" id="floatingInput456">
                        <label for="floatingInput456">Nama</label>
                    </div>
                </div>
                <div class="col px-1 mb-1">
                    <div class="form-floating">
                        <input type="text" class="form-control shadow-none" name="hp" required value="<?= htmlspecialchars((string) ($log['hp'] ?? '')) ?>" id="floatingInput1654">
                        <label for="floatingInput1654">Nomor HP</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col px-1 mb-1">
                    <div class="form-floating">
                        <input class="form-control shadow-none alamat" required name="alamat" value="<?= htmlspecialchars((string) ($log['alamat'] ?? '')) ?>" id="floatingTextarea" />
                        <label for="floatingTextarea">Jalan/No. Rumah/Dll</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col px-1 mb-1">
                    <div class="form-floating">
                        <select class="form-select shadow-none" id="kecamatan" name="kecamatan" required>
                            <option selected value=""></option>
                            <?php
                            foreach ($data['kec'] as $key => $dp) { ?>
                                <option value="<?= $key ?>"><?= str_replace("+", " ", $key) ?></option>
                            <?php } ?>
                        </select>
                        <label for="kecamatan">Kecamatan</label>
                    </div>
                </div>
                <div class="col px-1 mb-1" id="selKodePos">
                    <small class='text-secondary'>Kode Pos</small>
                </div>
            </div>
            <div class="row">
                <div class="col text-primary fw-bold">
                    <?= htmlspecialchars((string) ($log['area_name'] ?? '')) ?>
                </div>
            </div>
            <div class="row">
                <div class="col px-1 mb-1">
                    <div class="form-floating">
                        <input type="number" class="form-control shadow-none" name="rent" value="<?= $log['rent'] ?? '' ?>" id="floatingRent" placeholder="0" min="0" step="1">
                        <label for="floatingRent">Rent</label>
                    </div>
                </div>
            </div>
            <div class="row mt-1 border-top pt-2">
                <div class="col px-1 mb-1">
                    <button type="submit" class="btn btn-success w-100">Simpan Lokasi</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    var glat = <?= json_encode((float) ($data['geo']['lat'] ?? 0)) ?>;
    var glong = <?= json_encode((float) ($data['geo']['long'] ?? 0)) ?>;
    if (!glat && !glong) {
        glat = 0.5071;
        glong = 101.4478;
    }

    $(document).ready(function() {
        var mapOptions = {
            center: [glat, glong],
            zoom: 15
        };
        var map = new L.map('map', mapOptions);
        var layer = new L.TileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        });
        var marker = null;
        map.addLayer(layer);

        marker = L.marker([glat, glong]).addTo(map);

        document.getElementById('latitude').value = glat;
        document.getElementById('longitude').value = glong;
        $("div.leaflet-control-attribution").addClass("d-none");
        map.on('click', function(event) {
            if (marker !== null) {
                map.removeLayer(marker);
            }
            marker = L.marker([event.latlng.lat, event.latlng.lng]).addTo(map);
            document.getElementById('latitude').value = event.latlng.lat;
            document.getElementById('longitude').value = event.latlng.lng;
        });
    });

    $("form.ajax").submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: $(this).attr("action"),
            data: $(this).serialize(),
            type: $(this).attr("method"),
            dataType: 'html',
            success: function(res) {
                if (res == 0) {
                    $("#contentLok").load("<?= URL::BASE_URL ?>Cabang_Lokasi/content");
                } else {
                    alert("Gagal: " + res);
                }
            },
        });
    });
</script>
