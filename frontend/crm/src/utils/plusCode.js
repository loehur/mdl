// Encode koordinat menjadi Plus Code (Open Location Code).
// Berdasarkan implementasi referensi Google (Apache-2.0):
// https://github.com/google/open-location-code — hanya bagian encode.

const CODE_ALPHABET = "23456789CFGHJMPQRVWX";
const ENCODING_BASE = CODE_ALPHABET.length; // 20

const SEPARATOR = "+";
const SEPARATOR_POSITION = 8;
const PADDING_CHARACTER = "0";

const LATITUDE_MAX = 90;
const LONGITUDE_MAX = 180;
const MAX_DIGIT_COUNT = 15;
const PAIR_CODE_LENGTH = 10;

const GRID_CODE_LENGTH = MAX_DIGIT_COUNT - PAIR_CODE_LENGTH; // 5
const GRID_COLUMNS = 4;
const GRID_ROWS = 5;

const PAIR_PRECISION = Math.pow(ENCODING_BASE, 3); // 8000
const FINAL_LAT_PRECISION = PAIR_PRECISION * Math.pow(GRID_ROWS, GRID_CODE_LENGTH);
const FINAL_LNG_PRECISION = PAIR_PRECISION * Math.pow(GRID_COLUMNS, GRID_CODE_LENGTH);

function clipLatitude(latitude) {
  return Math.min(90, Math.max(-90, latitude));
}

function normalizeLongitude(longitude) {
  while (longitude < -180) longitude += 360;
  while (longitude >= 180) longitude -= 360;
  return longitude;
}

function locationToIntegers(latitude, longitude) {
  let latVal = Math.floor(latitude * FINAL_LAT_PRECISION);
  latVal += LATITUDE_MAX * FINAL_LAT_PRECISION;
  if (latVal < 0) {
    latVal = 0;
  } else if (latVal >= 2 * LATITUDE_MAX * FINAL_LAT_PRECISION) {
    latVal = 2 * LATITUDE_MAX * FINAL_LAT_PRECISION - 1;
  }
  let lngVal = Math.floor(longitude * FINAL_LNG_PRECISION);
  lngVal += LONGITUDE_MAX * FINAL_LNG_PRECISION;
  if (lngVal < 0) {
    lngVal = (lngVal % (2 * LONGITUDE_MAX * FINAL_LNG_PRECISION)) + 2 * LONGITUDE_MAX * FINAL_LNG_PRECISION;
  } else if (lngVal >= 2 * LONGITUDE_MAX * FINAL_LNG_PRECISION) {
    lngVal = lngVal % (2 * LONGITUDE_MAX * FINAL_LNG_PRECISION);
  }
  return [latVal, lngVal];
}

function encodeIntegers(latInt, lngInt, codeLength) {
  if (codeLength == null) codeLength = 10;
  codeLength = Math.min(MAX_DIGIT_COUNT, Number(codeLength));
  if (codeLength < 2 || (codeLength < PAIR_CODE_LENGTH && codeLength % 2 === 1)) {
    throw new Error("Invalid Open Location Code length");
  }
  const code = new Array(MAX_DIGIT_COUNT + 1);
  code[SEPARATOR_POSITION] = SEPARATOR;

  if (codeLength > PAIR_CODE_LENGTH) {
    for (let i = MAX_DIGIT_COUNT - PAIR_CODE_LENGTH; i >= 1; i--) {
      const latDigit = latInt % GRID_ROWS;
      const lngDigit = lngInt % GRID_COLUMNS;
      const ndx = latDigit * GRID_COLUMNS + lngDigit;
      code[SEPARATOR_POSITION + 2 + i] = CODE_ALPHABET.charAt(ndx);
      latInt = Math.floor(latInt / GRID_ROWS);
      lngInt = Math.floor(lngInt / GRID_COLUMNS);
    }
  } else {
    latInt = Math.floor(latInt / Math.pow(GRID_ROWS, GRID_CODE_LENGTH));
    lngInt = Math.floor(lngInt / Math.pow(GRID_COLUMNS, GRID_CODE_LENGTH));
  }

  code[SEPARATOR_POSITION + 1] = CODE_ALPHABET.charAt(latInt % ENCODING_BASE);
  code[SEPARATOR_POSITION + 2] = CODE_ALPHABET.charAt(lngInt % ENCODING_BASE);
  latInt = Math.floor(latInt / ENCODING_BASE);
  lngInt = Math.floor(lngInt / ENCODING_BASE);

  for (let i = PAIR_CODE_LENGTH / 2 + 1; i >= 0; i -= 2) {
    code[i] = CODE_ALPHABET.charAt(latInt % ENCODING_BASE);
    code[i + 1] = CODE_ALPHABET.charAt(lngInt % ENCODING_BASE);
    latInt = Math.floor(latInt / ENCODING_BASE);
    lngInt = Math.floor(lngInt / ENCODING_BASE);
  }

  if (codeLength >= SEPARATOR_POSITION) {
    return code.slice(0, codeLength + 1).join("");
  }
  return code.slice(0, codeLength).join("") +
    Array(SEPARATOR_POSITION - codeLength + 1).join(PADDING_CHARACTER) + SEPARATOR;
}

/**
 * @param {number} latitude
 * @param {number} longitude
 * @param {number} [codeLength] default 10 → akurasi ±14 m
 * @returns {string} Plus Code, mis. "6PG3GC4X+R4"
 */
export function encodePlusCode(latitude, longitude, codeLength = 10) {
  latitude = Number(clipLatitude(latitude));
  longitude = Number(normalizeLongitude(longitude));
  if (Number.isNaN(latitude) || Number.isNaN(longitude)) return "";
  const ints = locationToIntegers(latitude, longitude);
  return encodeIntegers(ints[0], ints[1], codeLength);
}
