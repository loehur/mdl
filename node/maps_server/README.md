# Maps Server — Google Maps URL → lat/lng

Service kecil untuk AI / API PHP: unfurl link Maps (termasuk `maps.app.goo.gl`) dan ekstrak koordinat.

## Setup

```bash
cd node/maps_server
cp .env.example .env
npm install
npm start
```

Default: `http://127.0.0.1:3020`

## Endpoints

### `GET /health`
```json
{ "ok": true, "status": "running", "service": "maps_server" }
```

### `POST /resolve`
```bash
curl -X POST http://127.0.0.1:3020/resolve \
  -H "Content-Type: application/json" \
  -H "X-Maps-Token: YOUR_TOKEN" \
  -d "{\"url\":\"https://www.google.com/maps/@0.5071,101.4478,15z\"}"
```

Body boleh salah satu: `url`, `gmaps`, `link`, atau `text` (URL di tengah teks ikut diekstrak).

Sukses:
```json
{
  "ok": true,
  "lat": 0.5071,
  "lng": 101.4478,
  "latt": 0.5071,
  "long": 101.4478,
  "source": "at-pattern",
  "accuracy": "exact",
  "input_url": "...",
  "final_url": "..."
}
```

Gagal: `{ "ok": false, "error": "no_coords|url_required|timeout|..." }`

### `GET /resolve?url=...`
Sama seperti POST.

## PHP

Pakai helper `App\Helpers\CRM\MapsServer::resolve($url)`.

Env API:
- `MAPS_SERVER_URL` = `http://127.0.0.1:3020/resolve`
- `MAPS_SERVER_TOKEN` = sama dengan token di `.env` maps_server (opsional)
