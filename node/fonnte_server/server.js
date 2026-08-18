require('dotenv').config({ path: require('path').resolve(__dirname, '.env') });

const express = require('express');
const path = require('path');
const { mountFonnteRoutes } = require('./lib/routes');
const { startBaileys } = require('./lib/baileys');
const { ensureMediaDir, MEDIA_DIR } = require('./lib/media');

const PORT = Number(process.env.PORT || 3025);
const HOST = process.env.HOST || '0.0.0.0';

const app = express();
app.use(express.json({ limit: '2mb' }));
app.use(express.urlencoded({ extended: true, limit: '2mb' }));

ensureMediaDir();
app.use('/media', express.static(MEDIA_DIR, { maxAge: '7d', fallthrough: false }));

mountFonnteRoutes(app);

app.get('/', (_req, res) => {
  res.json({
    ok: true,
    service: 'fonnte_server',
    endpoints: ['POST /send', 'POST /device', 'GET /health', 'GET /qr', 'GET /media/:file'],
  });
});

async function main() {
  await startBaileys();

  app.listen(PORT, HOST, () => {
    console.log('========================================');
    console.log('  fonnte_server (Baileys self-hosted)');
    console.log('========================================');
    console.log(`  HTTP:  http://${HOST}:${PORT}`);
    console.log(`  Send:  POST /send  (Authorization: FONNTE_TOKEN)`);
    console.log(`  Hook:  ${process.env.WEBHOOK_URL || '(WEBHOOK_URL not set)'}`);
    console.log(`  Media: ${process.env.MEDIA_PUBLIC_BASE_URL || `http://127.0.0.1:${PORT}`}/media/…`);
    console.log('========================================');
  });
}

main().catch((err) => {
  console.error('[fonnte_server] fatal:', err);
  process.exit(1);
});
