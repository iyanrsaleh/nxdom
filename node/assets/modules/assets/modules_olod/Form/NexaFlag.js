import { wilayah } from "./wilayah.js";

export async function setFlag(
  fieldName,
  placeholder,
  size,
  isFloating,
  fieldConfig,
  formSettings,
  setValue
) {
  try {

    // wilayah('11') = hanya Provinsi Aceh | wilayah() = seluruh Provinsi Indonesia
    const wilayahData = await wilayah(fieldConfig.flag);
    NXUI.dataFlag = wilayahData;
    // Gunakan data dari nexaStore
    // Handle setValue - bisa berupa string nama desa atau object data lengkap
    let hasil = null;

    if (typeof setValue === "string" && setValue !== "" &&
        setValue !== "Select Kabupaten" &&
        setValue !== "Select Kecamatan" &&
        setValue !== "Select Desa") {
      if (fieldName === "kecamatan") {
        // Cari berdasarkan nama kecamatan
        hasil = wilayahData.find(
          (item) => item.nm_kec.toLowerCase() === setValue.toLowerCase()
        );
      } else {
        // Cari berdasarkan nama desa, fallback ke kecamatan
        hasil = wilayahData.find(
          (item) => item.nama.toLowerCase() === setValue.toLowerCase()
        ) || wilayahData.find(
          (item) => item.nm_kec.toLowerCase() === setValue.toLowerCase()
        );
      }
    } else if (typeof setValue === "object" && setValue !== null) {
      // Jika setValue adalah object {kecamatan, desa} dari form data
      if (setValue.desa) {
        hasil = wilayahData.find(
          (item) => item.nama.toLowerCase() === setValue.desa.toLowerCase()
        );
      } else if (setValue.kecamatan) {
        hasil = wilayahData.find(
          (item) => item.nm_kec.toLowerCase() === setValue.kecamatan.toLowerCase()
        );
      } else if (setValue.nm_kab) {
        // Object sudah dalam format wilayah lengkap
        hasil = setValue;
      }
    }

    // Proses data untuk mendapatkan unique values
    const kabupatenList = [...new Set(wilayahData.map((item) => item.nm_kab))];

    // Generate options untuk select
    const generateOptions = (list, placeholder, selectedValue = null) => {
      return (
        `<option value="">${placeholder}</option>` +
        list
          .map((item) => {
            const selected =
              selectedValue && item === selectedValue ? " selected" : "";
            return `<option value="${item}"${selected}>${item}</option>`;
          })
          .join("")
      );
    };

    // Jika fieldName adalah 'kecamatan', tampilkan hanya Kabupaten dan Kecamatan
    if (fieldName == "kecamatan") {
      // Generate kecamatan options based on selected kabupaten
      const kecamatanList = hasil
        ? [
            ...new Set(
              wilayahData
                .filter((item) => item.nm_kab === hasil.nm_kab)
                .map((item) => item.nm_kec)
            ),
          ]
        : [];

      return `<div class="nx-row">
        <input type="hidden" name="provinsi" id="provinsi-input" value="${hasil ? hasil.nm_prov : ''}"/>
        <div class="nx-col-6">
          <div class="form-nexa-group">
            <select name="kabupaten" class="form-nexa-control flag-kabupaten-select" onchange="window.filterKecamatan(this.value)">
              ${generateOptions(
                kabupatenList,
                "Select Kabupaten",
                hasil ? hasil.nm_kab : null
              )}
            </select>
            <label>Kabupaten</label>
          </div>
        </div>
        <div class="nx-col-6">
          <div class="form-nexa-group">
            <select name="kecamatan" class="form-nexa-control flag-kecamatan-select" id="kecamatan-select">
              ${generateOptions(
                kecamatanList,
                "Select Kecamatan",
                hasil ? hasil.nm_kec : null
              )}
            </select>
            <label>Kecamatan</label>
          </div>
        </div>

      </div>
       `;
    } else {
      // Untuk fieldName lainnya, tampilkan Kabupaten, Kecamatan, dan Desa
      // Generate kecamatan and desa options based on selected values
      const kecamatanList = hasil
        ? [
            ...new Set(
              wilayahData
                .filter((item) => item.nm_kab === hasil.nm_kab)
                .map((item) => item.nm_kec)
            ),
          ]
        : [];
      const desaList = hasil
        ? [
            ...new Set(
              wilayahData
                .filter((item) => item.nm_kec === hasil.nm_kec)
                .map((item) => item.nama)
            ),
          ]
        : [];

      return `<div class="nx-row">
        <input type="hidden" name="provinsi" id="provinsi-input" value="${hasil ? hasil.nm_prov : ''}"/>
        <div class="nx-col-4">
          <div class="form-nexa-group">
            <select name="kabupaten" class="form-nexa-control flag-kabupaten-select" onchange="window.filterKecamatan(this.value)">
              ${generateOptions(
                kabupatenList,
                "Select Kabupaten",
                hasil ? hasil.nm_kab : null
              )}
            </select>
            <label>Kabupaten</label>
          </div>
        </div>
        <div class="nx-col-4">
          <div class="form-nexa-group">
            <select name="kecamatan" class="form-nexa-control flag-kecamatan-select" id="kecamatan-select" onchange="window.filterKecamatanDesa(this.value)">
              ${generateOptions(
                kecamatanList,
                "Select Kecamatan",
                hasil ? hasil.nm_kec : null
              )}
            </select>
            <label>Kecamatan</label>
          </div>
        </div>
        <div class="nx-col-4">
          <div class="form-nexa-group">
            <select name="desa" class="form-nexa-control flag-desa-select" id="desa-select">
              ${generateOptions(
                desaList,
                "Select Desa",
                hasil ? hasil.nama : null
              )}
            </select>
            <label>Desa</label>
          </div>
        </div>
      </div>
     `;
    }
  } catch (error) {
    console.error("Error getting data from nexaStore:", error);
    // Return fallback HTML jika ada error
    return `<div class="nx-row">
      <div class="nx-col-12">
        <div class="form-nexa-group">
          <label>Error loading data</label>
          <input type="text" class="form-nexa-control" disabled />
        </div>
      </div>
    </div>`;
  }
}
window.filterKecamatanDesa = function (kecamatan) {
  try {
    let wilayahData = NXUI.dataFlag;
    if (!wilayahData || !Array.isArray(wilayahData)) {
      console.warn('Flag data not available');
      return;
    }
    
    const desaSelect = document.getElementById("desa-select");
    if (!desaSelect) {
      console.warn('Desa select element not found');
      return;
    }
    
    const filteredDesa = [
      ...new Set(
        wilayahData
          .filter((item) => item.nm_kec === kecamatan)
          .map((item) => item.nama)
      ),
    ];

    if (window.$ && window.$.fn.select2 && $(desaSelect).hasClass('select2-hidden-accessible')) {
      $(desaSelect).empty().append('<option value="">Select Desa</option>');
      filteredDesa.forEach(desa => $(desaSelect).append(new Option(desa, desa)));
      $(desaSelect).trigger('change');
    } else {
      desaSelect.innerHTML = '<option value="">Select Desa</option>';
      filteredDesa.forEach((desa) => {
        const option = document.createElement("option");
        option.value = desa;
        option.textContent = desa;
        desaSelect.appendChild(option);
      });
    }
    
  } catch (error) {
    console.error('Error in filterKecamatanDesa:', error);
  }
};

window.filterKecamatan = function (kabupaten) {
  try {
    let wilayahData = NXUI.dataFlag;
    if (!wilayahData || !Array.isArray(wilayahData)) {
      console.warn('Flag data not available');
      return;
    }
    
    const kecamatanSelect = document.getElementById("kecamatan-select");
    const desaSelect = document.getElementById("desa-select");
    
    if (!kecamatanSelect) {
      console.warn('Kecamatan select element not found');
      return;
    }

    const filteredKecamatan = [
      ...new Set(
        wilayahData
          .filter((item) => item.nm_kab === kabupaten)
          .map((item) => item.nm_kec)
      ),
    ];

    // Update hidden provinsi input
    const provinsiInput = document.getElementById("provinsi-input");
    if (provinsiInput && kabupaten) {
      const ref = wilayahData.find(item => item.nm_kab === kabupaten);
      if (ref) provinsiInput.value = ref.nm_prov;
    }

    const useJquery = window.$ && window.$.fn.select2;

    if (useJquery && $(kecamatanSelect).hasClass('select2-hidden-accessible')) {
      $(kecamatanSelect).empty().append('<option value="">Select Kecamatan</option>');
      filteredKecamatan.forEach(kec => $(kecamatanSelect).append(new Option(kec, kec)));
      $(kecamatanSelect).trigger('change');
    } else {
      kecamatanSelect.innerHTML = '<option value="">Select Kecamatan</option>';
      filteredKecamatan.forEach((kecamatan) => {
        const option = document.createElement("option");
        option.value = kecamatan;
        option.textContent = kecamatan;
        kecamatanSelect.appendChild(option);
      });
    }

    // Reset desa
    if (desaSelect) {
      if (useJquery && $(desaSelect).hasClass('select2-hidden-accessible')) {
        $(desaSelect).empty().append('<option value="">Select Desa</option>').trigger('change');
      } else {
        desaSelect.innerHTML = '<option value="">Select Desa</option>';
      }
    }
    
  } catch (error) {
    console.error('Error in filterKecamatan:', error);
  }
};
