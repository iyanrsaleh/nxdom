class NexaRawAscii {

    constructor(width = 32) {
      this.width = width;   // 32 char = printer 58mm
      this.buffer = "";
  
      // ESC/POS
      this.ESC = '\x1B';
      this.GS  = '\x1D';
    }
  
    // tambah baris
    line(text = "") {
      this.buffer += text + "\n";
      return this;
    }
  
    // rata tengah
    center(text) {
      let space = Math.floor((this.width - text.length) / 2);
      this.buffer += " ".repeat(space) + text + "\n";
      return this;
    }
  
    // garis separator
    separator(char = "-") {
      this.buffer += char.repeat(this.width) + "\n";
      return this;
    }
  
    // format item kasir
    item(name, qty, price) {
      let total = qty * price;
  
      let left = `${name} x${qty}`;
      let right = total.toLocaleString("id-ID");
  
      let space = this.width - left.length - right.length;
      this.buffer += left + " ".repeat(space) + right + "\n";
  
      return this;
    }
  
    // total
    total(label, value) {
      let right = value.toLocaleString("id-ID");
      let space = this.width - label.length - right.length;
  
      this.buffer += label + " ".repeat(space) + right + "\n";
      return this;
    }
  
    // bold ON
    bold(on = true) {
      this.buffer += this.ESC + "E" + (on ? "\x01" : "\x00");
      return this;
    }
  
    // center mode printer
    align(mode = "left") {
      const map = { left:0, center:1, right:2 };
      this.buffer += this.ESC + "a" + String.fromCharCode(map[mode]);
      return this;
    }
  
    // cut paper
    cut() {
      this.buffer += this.GS + "V" + "\x00";
      return this;
    }
  
    // hasil akhir
    build() {
      return this.buffer;
    }
  }