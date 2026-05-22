import { wilayah } from "./wilayah.js";

function initWilayahSelect2() {
  const useJquery = window.$ && window.$.fn && window.$.fn.select2;
  if (!useJquery) return;

  const configMap = [
    { selector: "#provinsi-select", placeholder: "Select Provinsi" },
    { selector: "#kabupaten-select", placeholder: "Select Kabupaten" },
    { selector: "#kecamatan-select", placeholder: "Select Kecamatan" },
    { selector: "#desa-select", placeholder: "Select Desa" },
  ];

  configMap.forEach(({ selector, placeholder }) => {
    const $el = window.$(selector);
    if (!$el.length) return;

    if ($el.hasClass("select2-hidden-accessible")) {
      $el.trigger("change.select2");
      return;
    }

    $el.select2({
      width: "100%",
      placeholder,
      allowClear: true,
    });
  });
}

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
    const normalizeText = (value) => String(value ?? "").trim().toLowerCase();
    const levelOrder = ["provinsi", "kabupaten", "kecamatan", "desa"];
    const currentLevelName = normalizeText(fieldConfig?.name || fieldName);
    const currentLevelIndex = levelOrder.includes(currentLevelName)
      ? levelOrder.indexOf(currentLevelName)
      : 0;
    // Aturan hirarki:
    // provinsi -> kabupaten -> kecamatan -> desa
    // kabupaten -> kecamatan -> desa
    // kecamatan -> desa
    // desa -> desa
    const targetLevels = levelOrder.slice(currentLevelIndex);
    let preloadWilayah = null;
    if (NXUI.Rid) {
      try {
        const tableFromFailed =
          typeof fieldConfig?.failed === "string" && fieldConfig.failed.includes(".")
            ? fieldConfig.failed.split(".")[0]
            : null;
        const setTabel =
          fieldConfig?.table ||
          fieldConfig?.tabel ||
          formSettings?.table ||
          formSettings?.tabel ||
          tableFromFailed;
        const Rid = NXUI.Rid;
        if (!setTabel) {
          throw new Error("Nama tabel untuk preload wilayah tidak ditemukan");
        }

        const queryBuilder = NXUI.Storage().model(setTabel).where("id", "=", Rid);
        let complexQuery = null;
        try {
          // Ambil semua kolom agar tidak gagal saat nama kolom wilayah berbeda-beda.
          complexQuery = await queryBuilder.select("*").first();
        } catch (selectError) {
          complexQuery = await queryBuilder.first();
        }

        const rawPreload = complexQuery?.data || complexQuery || null;
        if (rawPreload) {
          const aliasMap = {
            provinsi: ["provinsi", "province", "prov", "nm_prov", "nama_provinsi"],
            kabupaten: ["kabupaten", "kab_kota", "kota_kabupaten", "nm_kab", "nama_kabupaten"],
            kecamatan: ["kecamatan", "kec", "nm_kec", "nama_kecamatan"],
            desa: ["desa", "kelurahan", "nm_desa", "nama_desa"],
          };
          const fieldMap = Object.keys(rawPreload).reduce((acc, key) => {
            acc[String(key).toLowerCase()] = rawPreload[key];
            return acc;
          }, {});
          const pickField = (aliases) => {
            const found = aliases.find((key) => fieldMap[key] != null && String(fieldMap[key]).trim() !== "");
            return found ? fieldMap[found] : null;
          };
          const pickByLevel = (level) =>
            targetLevels.includes(level) ? pickField(aliasMap[level]) : null;

          preloadWilayah = {
            nm_prov: pickByLevel("provinsi"),
            nm_kab: pickByLevel("kabupaten"),
            nm_kec: pickByLevel("kecamatan"),
            nama: pickByLevel("desa"),
          };
        }
      } catch (error) {
        // Jangan hentikan render saat preload edit belum siap.
        console.warn("setFlag: preload wilayah dilewati:", error);
      }
    }
    // Delegasikan filter kode ke wilayah(): null/''/'ALL' => semua Indonesia.
    const rawFlag = fieldConfig?.flag;
    const normalizedFlag =
      rawFlag === undefined || rawFlag === null || rawFlag === false
        ? null
        : String(rawFlag).trim();
    const isAllWilayah = !normalizedFlag || normalizedFlag.toUpperCase() === "ALL";
    const wilayahData = await wilayah(normalizedFlag);
    NXUI.dataFlag = wilayahData;
    const resolveWilayahRef = (candidate) => {
      if (!candidate || !Array.isArray(wilayahData)) return null;

      const byDesa = candidate.nama
        ? wilayahData.find((item) => normalizeText(item.nama) === normalizeText(candidate.nama))
        : null;
      if (byDesa) return byDesa;

      const byKecamatan = candidate.nm_kec
        ? wilayahData.find((item) => normalizeText(item.nm_kec) === normalizeText(candidate.nm_kec))
        : null;
      if (byKecamatan) return byKecamatan;

      const byKabupaten = candidate.nm_kab
        ? wilayahData.find((item) => normalizeText(item.nm_kab) === normalizeText(candidate.nm_kab))
        : null;
      if (byKabupaten) return byKabupaten;

      const byProvinsi = candidate.nm_prov
        ? wilayahData.find((item) => normalizeText(item.nm_prov) === normalizeText(candidate.nm_prov))
        : null;
      return byProvinsi || null;
    };
    // Gunakan data dari nexaStore
    // Handle setValue - bisa berupa string nama desa atau object data lengkap
    let hasil = resolveWilayahRef(preloadWilayah) || preloadWilayah;

    if (!hasil && typeof setValue === "string" && setValue !== "" &&
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
    } else if (!hasil && typeof setValue === "object" && setValue !== null) {
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
    const provinsiList = [...new Set(wilayahData.map((item) => item.nm_prov))];
    const kabupatenList = [...new Set(wilayahData.map((item) => item.nm_kab))];

    // Generate options untuk select
    const generateOptions = (
      list,
      placeholder,
      selectedValue = null,
      formatLabel = (value) => value
    ) => {
      return (
        `<option value="">${placeholder}</option>` +
        list
          .map((item) => {
            const selected =
              selectedValue && normalizeText(item) === normalizeText(selectedValue) ? " selected" : "";
            return `<option value="${item}"${selected}>${formatLabel(item)}</option>`;
          })
          .join("")
      );
    };

    // Tampilkan Kabupaten, Kecamatan, dan Desa
    {
      // Generate kecamatan and desa options based on selected values
      const selectedProvinsi = hasil?.nm_prov || (isAllWilayah ? "ALL" : null);
      const provinsiOptions = ["ALL", ...provinsiList];
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
        <div class="nx-col-3">
          <div class="form-nexa-group">
            <div class="form-group-header">
              <label for="provinsi-select">Provinsi</label>
              <span class="error" id="errors_provinsi" style="display:none;"></span>
            </div>
            <select name="provinsi" id="provinsi-select" class="form-nexa-control flag-provinsi-select" style="width:100%" onchange="window.filterKabupatenByProvinsi(this.value)">
              ${generateOptions(
                provinsiOptions,
                "Select Provinsi",
                selectedProvinsi,
                (item) => item === "ALL" ? "Pilih Provinsi" : item
              )}
            </select>
          </div>
        </div>
        <div class="nx-col-3">
          <div class="form-nexa-group">
            <div class="form-group-header">
              <label for="kabupaten-select">Kabupaten</label>
              <span class="error" id="errors_kabupaten" style="display:none;"></span>
            </div>
            <select name="kabupaten" id="kabupaten-select" class="form-nexa-control flag-kabupaten-select" style="width:100%" onchange="window.filterKecamatan(this.value)">
              ${generateOptions(
                kabupatenList,
                "Select Kabupaten",
                hasil ? hasil.nm_kab : null
              )}
            </select>
          </div>
        </div>
        <div class="nx-col-3">
          <div class="form-nexa-group">
            <div class="form-group-header">
              <label for="kecamatan-select">Kecamatan</label>
              <span class="error" id="errors_kecamatan" style="display:none;"></span>
            </div>
            <select name="kecamatan" class="form-nexa-control flag-kecamatan-select" id="kecamatan-select" style="width:100%" onchange="window.filterKecamatanDesa(this.value)">
              ${generateOptions(
                kecamatanList,
                "Select Kecamatan",
                hasil ? hasil.nm_kec : null
              )}
            </select>
          </div>
        </div>
        <div class="nx-col-3">
          <div class="form-nexa-group">
            <div class="form-group-header">
              <label for="desa-select">Desa</label>
              <span class="error" id="errors_desa" style="display:none;"></span>
            </div>
            <select name="desa" class="form-nexa-control flag-desa-select" id="desa-select" style="width:100%">
              ${generateOptions(
                desaList,
                "Select Desa",
                hasil ? hasil.nama : null
              )}
            </select>
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
  } finally {
    setTimeout(() => initWilayahSelect2(), 0);
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

    // Update select provinsi saat kabupaten dipilih
    const provinsiInput = document.getElementById("provinsi-select");
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

window.filterKabupatenByProvinsi = function (provinsi) {
  try {
    const wilayahData = NXUI.dataFlag;
    if (!wilayahData || !Array.isArray(wilayahData)) return;
    const kabupatenSelect = document.getElementById("kabupaten-select");
    const kecamatanSelect = document.getElementById("kecamatan-select");
    const desaSelect = document.getElementById("desa-select");
    if (!kabupatenSelect) return;

    const isAllProvinsi = provinsi === "ALL";
    const source = !provinsi || isAllProvinsi
      ? wilayahData
      : wilayahData.filter((item) => item.nm_prov === provinsi);
    const filteredKabupaten = [...new Set(source.map((item) => item.nm_kab))];

    const useJquery = window.$ && window.$.fn.select2;
    if (useJquery && $(kabupatenSelect).hasClass('select2-hidden-accessible')) {
      $(kabupatenSelect).empty().append('<option value="">Select Kabupaten</option>');
      filteredKabupaten.forEach(kab => $(kabupatenSelect).append(new Option(kab, kab)));
      $(kabupatenSelect).trigger('change');
    } else {
      kabupatenSelect.innerHTML = '<option value="">Select Kabupaten</option>';
      filteredKabupaten.forEach((kabupaten) => {
        const option = document.createElement("option");
        option.value = kabupaten;
        option.textContent = kabupaten;
        kabupatenSelect.appendChild(option);
      });
    }

    // Reset kecamatan (seragam dengan handler lain + dukung select2)
    if (kecamatanSelect) {
      if (useJquery && $(kecamatanSelect).hasClass('select2-hidden-accessible')) {
        $(kecamatanSelect).empty().append('<option value="">Select Kecamatan</option>').trigger('change');
      } else {
        kecamatanSelect.innerHTML = '<option value="">Select Kecamatan</option>';
      }
    }

    // Reset desa (seragam dengan handler lain + dukung select2)
    if (desaSelect) {
      if (useJquery && $(desaSelect).hasClass('select2-hidden-accessible')) {
        $(desaSelect).empty().append('<option value="">Select Desa</option>').trigger('change');
      } else {
        desaSelect.innerHTML = '<option value="">Select Desa</option>';
      }
    }
  } catch (error) {
    console.error('Error in filterKabupatenByProvinsi:', error);
  }
};
