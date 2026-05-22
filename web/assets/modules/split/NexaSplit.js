
export class NexaSplit {
  constructor(str, separator = '/') {
    this.str = str;
    this.separator = separator;
    this.parts = this.str.split(this.separator);
  }

  // Ambil semua array
  toArray() {
    return this.parts;
  }

  // Ambil berdasarkan index
  get(index) {
    return this.parts[index] ?? null;
  }

  // Pertama
  first() {
    return this.get(0);
  }

  // Tengah (default index 1)
  middle(index = 1) {
    return this.get(index);
  }

  // Terakhir
  last() {
    return this.parts[this.parts.length - 1] ?? null;
  }

  // Semua kecuali pertama
  exceptFirst() {
    return this.parts.slice(1);
  }

  // Semua kecuali terakhir
  exceptLast() {
    return this.parts.slice(0, -1);
  }

  // Cek ada value
  has(value) {
    return this.parts.includes(value);
  }

  // Cari index
  indexOf(value) {
    return this.parts.indexOf(value);
  }
}
// const ns = new NexaSplit("Tools/work/Query");

// console.log(ns.toArray()); // ["Tools", "work", "Query"]
// console.log(ns.first());   // Tools
// console.log(ns.middle());  // work ✅
// console.log(ns.last());    // Query

// console.log(ns.get(1));    // work
// console.log(ns.has("work")); // true