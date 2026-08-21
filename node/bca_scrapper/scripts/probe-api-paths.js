const paths = [
  '/login',
  '/auth/login',
  '/api/login',
  '/api/v1/login',
  '/api/auth/login',
  '/merchant/login',
  '/user/login',
  '/oauth/token',
  '/api/merchant/login',
  '/api/user/login',
  '/bms/login',
  '/mssi/login',
  '/api/authenticate',
  '/authenticate',
];

async function probe(base) {
  for (const p of paths) {
    const url = base.replace(/\/$/, '') + p;
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email: 'test@test.com', password: 'test123' }),
      });
      const ct = res.headers.get('content-type') || '';
      let body = '';
      try {
        body = ct.includes('json') ? JSON.stringify(await res.json()) : (await res.text()).slice(0, 120);
      } catch (_) {}
      console.log(base, p, res.status, body.slice(0, 150));
    } catch (err) {
      console.log(base, p, 'ERR', err.message);
    }
  }
}

(async () => {
  await probe('https://bms.ebanksvc.bca.co.id');
  await probe('https://mssi.ebanksvc.bca.co.id');
})();
