export class SearchField {
  /**
   * @param {string} storageKey - Key IndexedDB
   * @param {number} minChars - Minimal karakter sebelum search
   * @param {object} options - Opsi tambahan, misal nama field untuk server
   */
  constructor(storageKey = 'data', minChars = 1, options = {}) {
    this.storageKey = storageKey;
    this.minChars = minChars;
    this.myDB = "NexaStoreDB";
    this.verDB = 5;
    this.buckets = "bucketsStore";
    this.bucketsID = options?.key;
    this.options = options; // digunakan untuk query ke server
  }

  // Simpan data ke IndexedDB, merge tanpa duplikasi
  async saveData(newData) {
    const dDB = NXUI.Storage();
    const db = await dDB.indexedDB.init(this.myDB, this.verDB);

    let existingRecord = await db.get(this.buckets, this.bucketsID);
    let existingData = existingRecord?.data || [];
    let mergedData;

    if (Array.isArray(existingData) && Array.isArray(newData)) {
      const existingIds = new Set(existingData.map(item => item.id));
      const filteredNewData = newData.filter(item => !existingIds.has(item.id));
      mergedData = [...existingData, ...filteredNewData];
    } else if (typeof existingData === "object" && typeof newData === "object") {
      mergedData = { ...existingData, ...newData };
    } else {
      mergedData = newData;
    }

    await db.set(this.buckets, { id: this.bucketsID, data: mergedData });
    console.log("Data tersimpan:", mergedData);
  }

  // Ambil data dari IndexedDB
  async getData() {
    const dDB = NXUI.Storage();
    const db = await dDB.indexedDB.init(this.myDB, this.verDB);
    let existingRecord = await db.get(this.buckets, this.bucketsID);
    return existingRecord?.data || [];
  }

  // Dapatkan suggestion dari IndexedDB
  async getSuggestions(query) {
    const data = await this.getData();
    if (!Array.isArray(data)) return [];

    const q = query.toLowerCase();
    return data.filter(item => {
      const labelMatch = item.label && typeof item.label === 'string' && item.label.toLowerCase().includes(q);
      const dataStr = item.data != null ? String(item.data).toLowerCase() : '';
      const dataMatch = dataStr.includes(q);
      return labelMatch || dataMatch;
    });
  }

  // Tampilkan suggestion ke DOM, ambil dari server jika tidak ada di IndexedDB
  async displaySuggestions(query, inputId, suggestionsId, hasilId) {
    const suggestionsContainer = document.getElementById(suggestionsId);
    suggestionsContainer.innerHTML = '';

    let suggestions = await this.getSuggestions(query);

    // Jika IndexedDB kosong atau tidak ada hasil, ambil dari server dan tampilkan langsung
    if (suggestions.length === 0 && query.length >= this.minChars) {
      try {
        const tabel = await NXUI.Storage().models("Office").searchAt(this.options, query);
        if (tabel.data && tabel.data.length > 0) {
          const mappedData = tabel.data.map((row, index) => ({
            id: row.id,
            label: row.label || `Item ${index + 1}`,
            data: row.data || "-",
            value: row.value || "-",
            bg: "bg-primary"
          }));

          // Simpan ke IndexedDB untuk pencarian berikutnya
          await this.saveData(mappedData);
          suggestions = mappedData;
        }
      } catch (err) {
        console.error("Gagal ambil data dari server:", err);
      }
    }

    if (suggestions.length === 0) {
      const noData = document.createElement('li');
      noData.innerHTML = `<div class="nx-alert nx-alert-danger">
                Perhatian: Data "${query}" tidak ditemukan.
              </div>`;

      suggestionsContainer.appendChild(noData);
      return;
    }

    suggestions.forEach(suggestion => {

      const suggestionItem = document.createElement('li');
      suggestionItem.className = 'list-group-item d-flex align-items-center suggestion-item';

      const contentDiv = document.createElement('div');
      const title = document.createElement('h6');
      title.className = 'tx-13 tx-inverse tx-semibold mg-b-0';
      title.textContent = suggestion.data;

      const subtitle = document.createElement('span');
      subtitle.className = 'd-block tx-11 text-muted';
      subtitle.textContent = `Data: ${suggestion.label}`;

      contentDiv.appendChild(title);
      contentDiv.appendChild(subtitle);
      suggestionItem.appendChild(contentDiv);

      suggestionItem.addEventListener('click', async () => {
        const inputEl = document.getElementById(inputId);
        if (inputEl) inputEl.value = suggestion.value;
        if (this.options?.callback) {

try {

  // const tabelCek = await NXUI.Storage().models("Office").setAtFind(this.options?.inputId, suggestion.data);
  // console.log(tabelCek);
   const tabelCek = await NXUI.Storage().models("Office").setAtFind(this.options, `${suggestion.data}`);
   console.log(tabelCek)
  if (tabelCek?.data?.id) { 
      const callbackhasilId = 'return_' + this.options.variable;
      const inputElcall = document.getElementById(callbackhasilId);

      if (inputElcall) {
          inputElcall.innerHTML =`<div class="nx-alert nx-alert-danger">
                Perhatian: Data ${suggestion.data}  sudah ada di sistem, silakan gunakan data lain
              </div>`;

          // Hilangkan setelah 3 detik
          setTimeout(() => {
              inputElcall.innerHTML = '';
          }, 3000); // 3000ms = 3 detik
      }

      const inputEl = document.getElementById(inputId);
      if (inputEl) {
          inputEl.value = '';
          inputEl.placeholder = this.options.placeholder;
      }
  }


} catch (error) {
  console.error("❌ Auto mode initialization failed:", error);
  
}




          
        }
   
        suggestionsContainer.innerHTML = '';

        const hasilContainer = document.getElementById(hasilId);
        if (!hasilContainer) return;

        const hasilItem = document.createElement('li');
        hasilItem.className = 'list-group-item d-flex align-items-center';
        hasilItem.classList.add(suggestion.bg || 'bg-primary');

        hasilItem.innerHTML = `
          <div>
            <h6 class="tx-13 tx-inverse tx-semibold mg-b-0">${suggestion.label}</h6>
            <span class="d-block tx-11 text-muted">Data: ${suggestion.data}</span>
          </div>
        `;

        hasilContainer.innerHTML = '';
        hasilContainer.appendChild(hasilItem);
      });

      suggestionsContainer.appendChild(suggestionItem);
    });
  }

  // Inisialisasi input listener
  init(inputId, suggestionsId, hasilId) {
    const inputEl = document.getElementById(inputId);
    if (!inputEl) return;

    inputEl.addEventListener('input', async e => {
      const query = e.target.value;
      if (query.length >= this.minChars) {
         await this.displaySuggestions(query, inputId, suggestionsId, hasilId);
         
      } else {
        document.getElementById(suggestionsId).innerHTML = '';
      }
    });

  }
}
