/**
 * NexaEscpos — builder byte RAW ESC/POS untuk printer thermal (browser / Node).
 * Tidak mengirim ke hardware; hasilkan Uint8Array/Blob untuk WebUSB, backend, atau unduhan.
 */

const ESC = 0x1b;
const GS = 0x1d;
const FS = 0x1c;

export class NexaEscpos {
  constructor() {
    /** @type {number[]} */
    this._bytes = [];
  }

  /** Salin buffer saat ini */
  clone() {
    const c = new NexaEscpos();
    c._bytes = [...this._bytes];
    return c;
  }

  /** Tambah byte mentah (0–255), string hex "1B40", Uint8Array, atau array angka */
  raw(input) {
    if (input == null) return this;
    if (input instanceof Uint8Array) {
      for (let i = 0; i < input.length; i++) this._bytes.push(input[i] & 0xff);
      return this;
    }
    if (Array.isArray(input)) {
      for (const b of input) this._bytes.push(Number(b) & 0xff);
      return this;
    }
    if (typeof input === "string") {
      const s = input.replace(/\s+/g, "");
      for (let i = 0; i < s.length; i += 2) {
        this._bytes.push(parseInt(s.slice(i, i + 2), 16) & 0xff);
      }
      return this;
    }
    return this;
  }

  /** Inisialisasi printer (ESC @) */
  init() {
    this._bytes.push(ESC, 0x40);
    return this;
  }

  /**
   * Teks — default UTF-8 (TextEncoder). Untuk CP437/Windows-1252 gunakan raw() atau encode sendiri.
   * @param {string} s
   */
  text(s) {
    if (s == null || s === "") return this;
    const enc = new TextEncoder();
    for (const b of enc.encode(String(s))) this._bytes.push(b);
    return this;
  }

  /** Line feed (LF) */
  lf() {
    this._bytes.push(0x0a);
    return this;
  }

  /** CR + LF */
  crlf() {
    this._bytes.push(0x0d, 0x0a);
    return this;
  }

  /** ESC d n — feed n baris */
  feedLines(n = 1) {
    const k = Math.min(255, Math.max(0, Math.floor(Number(n) || 0)));
    this._bytes.push(ESC, 0x64, k);
    return this;
  }

  /**
   * Perataan: left | center | right
   * @param {'left'|'center'|'right'} mode
   */
  align(mode) {
    const m =
      mode === "center" ? 1 : mode === "right" ? 2 : 0;
    this._bytes.push(ESC, 0x61, m);
    return this;
  }

  /** ESC E n — tebal */
  bold(on = true) {
    this._bytes.push(ESC, 0x45, on ? 1 : 0);
    return this;
  }

  /** ESC - n — garis bawah (0 mati, 1 satu titik, 2 dua titik) */
  underline(mode = 0) {
    this._bytes.push(ESC, 0x2d, mode & 0xff);
    return this;
  }

  /**
   * GS ! n — ukuran karakter (perkalian lebar/tinggi 1–8)
   * @param {number} widthMul
   * @param {number} heightMul
   */
  size(widthMul = 1, heightMul = 1) {
    const w = Math.min(8, Math.max(1, Math.floor(widthMul))) - 1;
    const h = Math.min(8, Math.max(1, Math.floor(heightMul))) - 1;
    this._bytes.push(GS, 0x21, ((w & 0x0f) << 4) | (h & 0x0f));
    return this;
  }

  /** Reset ukuran & bold & underline umum */
  resetModes() {
    this.bold(false).underline(0).size(1, 1);
    return this;
  }

  /**
   * Potong kertas — partial | full (GS V)
   * @param {'partial'|'full'} kind
   */
  cut(kind = "partial") {
    if (kind === "full") this._bytes.push(GS, 0x56, 0x00);
    else this._bytes.push(GS, 0x56, 0x01);
    return this;
  }

  /** Buka laci (pulse) — model umum ESC p */
  cashDrawerPulse(m = 0, t1 = 120, t2 = 240) {
    this._bytes.push(ESC, 0x70, m & 0xff, t1 & 0xff, t2 & 0xff);
    return this;
  }

  /**
   * Barcode CODE128 (GS k m n d1...dk NUL)
   * data string ASCII; panjang dibatasi printer.
   */
  barcode128(data) {
    const s = String(data);
    const enc = new TextEncoder();
    const payload = enc.encode(s);
    if (payload.length > 255) {
      console.warn("NexaEscpos.barcode128: data dipotong ke 255 byte");
    }
    const len = Math.min(255, payload.length);
    this._bytes.push(GS, 0x6b, 73, len);
    for (let i = 0; i < len; i++) this._bytes.push(payload[i]);
    return this;
  }

  /**
   * QR Code (perintah GS ( k — kompatibel banyak clone Epson)
   * @param {string} data
   * @param {{ moduleSize?: number, ecc?: 'L'|'M'|'Q'|'H' }} opt
   */
  qr(data, opt = {}) {
    const str = String(data);
    const enc = new TextEncoder();
    const bytes = enc.encode(str);
    const moduleSize = Math.min(16, Math.max(1, Math.floor(opt.moduleSize ?? 6)));
    const ecc = opt.ecc ?? "M";
    const eccCode = { L: 0x30, M: 0x31, Q: 0x32, H: 0x33 }[ecc] ?? 0x31;

    const k = [0x1d, 0x28, 0x6b];

    // Model 2
    this._bytes.push(...k, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00);
    // Ukuran modul
    this._bytes.push(...k, 0x03, 0x00, 0x31, 0x43, moduleSize & 0xff);
    // Koreksi error
    this._bytes.push(...k, 0x03, 0x00, 0x31, 0x45, eccCode);

    const storeLen = bytes.length + 3;
    const pL = storeLen & 0xff;
    const pH = (storeLen >> 8) & 0xff;
    this._bytes.push(...k, pL, pH, 0x31, 0x50, 0x30);
    for (let i = 0; i < bytes.length; i++) this._bytes.push(bytes[i]);

    // Print QR
    this._bytes.push(...k, 0x03, 0x00, 0x31, 0x51, 0x30);
    return this;
  }

  /** Gabung instance / Uint8Array lain ke akhir */
  concat(other) {
    if (other instanceof NexaEscpos) {
      this._bytes.push(...other._bytes);
      return this;
    }
    if (other instanceof Uint8Array) {
      for (let i = 0; i < other.length; i++) this._bytes.push(other[i] & 0xff);
      return this;
    }
    return this;
  }

  /** Panjang byte */
  get length() {
    return this._bytes.length;
  }

  toUint8Array() {
    return new Uint8Array(this._bytes);
  }

  toArrayBuffer() {
    return this.toUint8Array().buffer;
  }

  toBlob() {
    return new Blob([this.toUint8Array()], {
      type: "application/octet-stream",
    });
  }

  /**
   * Unduh file .bin (atau nama custom)
   * @param {string} filename
   */
  download(filename = "escpos.bin") {
    const blob = this.toBlob();
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.style.display = "none";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(a.href);
    return this;
  }

  /**
   * Helper: rantai cepat init + teks + lf
   * @param {string} lines
   */
  static receipt(lines) {
    const e = new NexaEscpos();
    e.init();
    const parts = String(lines).split("\n");
    for (let i = 0; i < parts.length; i++) {
      if (i > 0) e.lf();
      e.text(parts[i]);
    }
    e.lf();
    return e;
  }
}

window.NexaEscpos = NexaEscpos;
export default NexaEscpos;
