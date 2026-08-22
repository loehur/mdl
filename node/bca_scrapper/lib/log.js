const TZ = process.env.TZ || 'Asia/Jakarta';

function timestamp() {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).formatToParts(new Date());
  const get = (type) => parts.find((p) => p.type === type)?.value || '00';
  return `${get('year')}-${get('month')}-${get('day')} ${get('hour')}:${get('minute')}:${get('second')}`;
}

function prefix() {
  return `[${timestamp()}]`;
}

function log(...args) {
  console.log(prefix(), ...args);
}

function warn(...args) {
  console.warn(prefix(), ...args);
}

function error(...args) {
  console.error(prefix(), ...args);
}

module.exports = {
  log,
  warn,
  error,
  timestamp,
};
