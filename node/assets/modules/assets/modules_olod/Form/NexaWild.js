import { collectFormData } from "./NexaForm.js";
import { spinner as createSpinner } from "../Dom/NexaSpinner.js";

// Inject style sekali saja
function ensureStyle() {
  if (document.getElementById("nw-style")) return;
  const s = document.createElement("style");
  s.id = "nw-style";
  s.textContent = [
    ".nw-invalid{border-color:#cf222e!important;",
    "box-shadow:inset 0 1px 0 rgba(208,215,222,.2),",
    "0 0 0 3px rgba(207,34,46,.15)!important;}",
    ".nw-err{color:#cf222e;font-size:12px;margin-top:4px;font-weight:500;}",
  ].join("");
  document.head.appendChild(s);
}

/**
 * NexaWild — Kumpulkan data form + validasi otomatis.
 *
 * Config:
 *   elementFormId   : string  — ID elemen form / container
 *   getValidationBy : string[] — key method: "name" | "id" (default ["name"])
 *   required        : object  — { fieldName: "Label" } filed yang wajib diisi
 *                   : string[] — ["fieldName", ...] (label dari <label for=...>)
 *
 * Returns:
 *   Promise<object|null> — data field jika valid, null jika ada error
 *
 * Error ditampilkan langsung di DOM, di akhir parent terdekat tiap field.
 */
export class NexaWild {
  constructor({ elementFormId, getValidationBy = ["name"], required = {}, noValidationBy = [], type = "insert", spinner = null } = {}) {
    return new Promise(async (resolve) => {
      const form = document.getElementById(elementFormId);
      if (!form) { resolve(null); return; }

      // Siapkan spinner jika config diberikan dan enabled
      let sp = null;
      if (spinner != null && spinner?.enabled !== false) {
        const spinnerType = spinner.centerScreen === false
          ? (spinner.type || "inline")
          : "overlay";
        sp = createSpinner({
          type: spinnerType,
          size: spinner.size || "medium",
          color: spinner.color || "#007bff",
          message: spinner.message || "",
          position: spinner.position || "center",
          ...(spinnerType === "inline" ? { target: `#${elementFormId}` } : {}),
        });
        sp.show();
      }

      // Bungkus resolve agar spinner selalu di-hide
      const done = (val) => { sp?.hide(); resolve(val); };

      ensureStyle();

      // Bersihkan error sebelumnya
      form.querySelectorAll(".nw-err").forEach((e) => e.remove());
      form.querySelectorAll(".nw-invalid").forEach((e) => e.classList.remove("nw-invalid"));

      // Normalisasi required → { fieldName: "Label" }
      let rules = {};
      if (Array.isArray(required) && required.length > 0) {
        required.forEach((name) => {
          const el = form.querySelector(`[name="${name}"]`) || form.querySelector(`#${name}`);
          const lbl = el
            ? (form.querySelector(`label[for="${el.id || name}"]`)?.textContent?.trim() || name)
            : name;
          rules[name] = lbl;
        });
      } else if (!Array.isArray(required) && Object.keys(required).length > 0) {
        rules = required;
      } else {
        // Auto-deteksi dari atribut [required] di HTML
        const seen = new Set();
        form.querySelectorAll("[required]").forEach((el) => {
          const name = el.name || el.id;
          if (!name || seen.has(name)) return;
          seen.add(name);
          const lbl =
            form.querySelector(`label[for="${el.id}"]`)?.textContent?.trim() ||
            form.querySelector(`label[for="${name}"]`)?.textContent?.trim() ||
            name;
          rules[name] = lbl;
        });
      }

      let isValid = true;

      // Hapus error satu field saat sudah valid (live validation)
      const clearError = (els) => {
        (Array.isArray(els) ? els : [els]).forEach((el) => {
          el.classList.remove("nw-invalid");
          // Hapus semua .nw-err di parentElement field ini
          el.parentElement?.querySelectorAll(".nw-err").forEach((e) => e.remove());
        });
      };

      // Pasang live listener pada semua field dalam rules
      const attachLive = (name) => {
        const radios = Array.from(form.querySelectorAll(`input[type="radio"][name="${name}"]`));
        if (radios.length) {
          radios.forEach((r) => r.addEventListener("change", () => clearError(radios), { once: false }));
          return;
        }
        const checkboxes = Array.from(form.querySelectorAll(`input[type="checkbox"][name="${name}"]`));
        if (checkboxes.length) {
          checkboxes.forEach((c) => c.addEventListener("change", () => {
            if (checkboxes.some((x) => x.checked)) clearError(checkboxes);
          }, { once: false }));
          return;
        }
        const el = form.querySelector(`[name="${name}"]`) || form.querySelector(`#${name}`);
        if (!el) return;
        const evt = el.type === "file" ? "change" : "input";
        el.addEventListener(evt, () => {
          const filled = el.type === "file" ? el.files.length > 0 : (el.value || "").trim() !== "";
          if (filled) clearError(el);
        }, { once: false });
      };

      const markError = (fieldName, label) => {
        isValid = false;
        // Radio: ambil elemen pertama dalam grup
        const el =
          form.querySelector(`input[type="radio"][name="${fieldName}"]`) ||
          form.querySelector(`[name="${fieldName}"]`) ||
          form.querySelector(`#${fieldName}`);
        if (!el) return;

        el.classList.add("nw-invalid");

        const err = document.createElement("div");
        err.className = "nw-err";
        err.textContent = `${label} wajib diisi`;

        // Tempel error di parent langsung field
        const group = el.parentElement;
        group.appendChild(err);
      };

      // Hapus field yang dikecualikan dari validasi
      if (Array.isArray(noValidationBy) && noValidationBy.length > 0) {
        noValidationBy.forEach((name) => { delete rules[name]; });
      }

      // Jalankan validasi
      for (const [name, label] of Object.entries(rules)) {
        const el =
          form.querySelector(`[name="${name}"]`) ||
          form.querySelector(`#${name}`);
        if (!el) continue;

        // RADIO
        if (el.type === "radio") {
          const radios = form.querySelectorAll(`input[type="radio"][name="${name}"]`);
          const checked = Array.from(radios).some(r => r.checked);
          if (!checked) { markError(name, label); attachLive(name); }
          continue;
        }
        // CHECKBOX
        if (el.type === "checkbox") {
          const checkboxes = form.querySelectorAll(`input[type="checkbox"][name="${name}"]`);
          const checked = Array.from(checkboxes).some(c => c.checked);
          if (!checked) { markError(name, label); attachLive(name); }
          continue;
        }
        // FILE
        if (el.type === "file") {
          if (el.required && el.files.length === 0) {
            markError(name, label); attachLive(name);
          }
          continue;
        }
        // COLOR
        if (el.type === "color") {
          if (!el.value || !/^#[0-9A-Fa-f]{6}$/.test(el.value)) {
            markError(name, label); attachLive(name);
          }
          continue;
        }
        // RANGE
        if (el.type === "range") {
          const val = el.value;
          const min = el.min !== "" ? Number(el.min) : null;
          const max = el.max !== "" ? Number(el.max) : null;
          if (val === "" || isNaN(val)) {
            markError(name, label); attachLive(name);
            continue;
          }
          const num = Number(val);
          if ((min !== null && num < min) || (max !== null && num > max)) {
            markError(name, label + ` harus antara ${min} - ${max}`); attachLive(name);
          }
          continue;
        }
        // SELECT, TEXT, EMAIL, PASSWORD, DATE, TEXTAREA, DLL
        const val = (el.value || "").trim();
        if (!val) { markError(name, label); attachLive(name); }
      }

      if (!isValid) { done({ status: false, data: null }); return; }

      // Kumpulkan semua data
      const data = await collectFormData(elementFormId, getValidationBy);

      // Auto-reset form hanya untuk insert, bukan update
      if (type !== "update") {
        form.reset();
        NexaWild.clear(elementFormId);
      }

      done({ status: true, data });
    });
  }

  /** Bersihkan semua error validasi pada form */
  static clear(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    form.querySelectorAll(".nw-err").forEach((e) => e.remove());
    form.querySelectorAll(".nw-invalid").forEach((e) => e.classList.remove("nw-invalid"));
  }
}
