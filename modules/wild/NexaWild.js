import { collectFormData } from "../forms/NexaForm.js";
import { spinner as createSpinner } from "../spinner/NexaSpinner.js";

// Inject style sekali saja
function ensureStyle() {
  let s = document.getElementById("nw-style");
  if (!s) {
    s = document.createElement("style");
    s.id = "nw-style";
    document.head.appendChild(s);
  }
  s.textContent = [
    ".nw-invalid{}",
    ".nw-invalid-select2{}",
    ".nw-err{color:#cf222e;font-size:12px;margin-top:4px;font-weight:500;}",
    ".nw-inline-err{color:#cf222e;font-size:12px;font-weight:500;margin-left:6px;}",
  ].join("");
}

function getLabelText(form, el, fallbackName) {
  const labelEl = form.querySelector(`label[for="${el?.id || fallbackName}"]`) || form.querySelector(`label[for="${fallbackName}"]`);
  if (!labelEl) return fallbackName;

  // Clone supaya aman diolah tanpa ubah DOM asli.
  const clone = labelEl.cloneNode(true);
  clone.querySelectorAll(
    ".octicon,.fa,.fas,.far,.fab,.material-icons,.material-icons-outlined,.material-icons-round,.material-symbols-outlined,.material-symbols-rounded,.form-label-icon"
  ).forEach((n) => n.remove());

  return (clone.textContent || "").trim() || fallbackName;
}

function getSelect2Selection(el) {
  if (!el) return null;
  const isSelect = el?.tagName?.toLowerCase() === "select";
  if (!isSelect) return null;

  // Select2 biasanya menyisipkan .select2 tepat setelah <select>.
  const sib = el.nextElementSibling;
  if (sib?.classList?.contains("select2")) {
    return sib.querySelector(".select2-selection");
  }
  return null;
}

function ensureGlobalFormHelpers() {
  if (globalThis.window !== undefined && typeof globalThis.fileUploadUpdate !== "function") {
    globalThis.fileUploadUpdate = function fileUploadUpdate(input, labelId, dropId) {
      const labelEl = document.getElementById(labelId);
      const dropEl = document.getElementById(dropId);
      if (!input || !labelEl || !dropEl) return;

      const count = input.files ? input.files.length : 0;
      if (count <= 0) {
        labelEl.textContent = "JPG, PNG, GIF, WEBP";
        dropEl.classList.remove("has-file");
        return;
      }

      labelEl.textContent = count === 1 ? input.files[0].name : `${count} file dipilih`;
      dropEl.classList.add("has-file");
    };
  }

  if (globalThis.window !== undefined && typeof globalThis.togglePasswordVisibility !== "function") {
    globalThis.togglePasswordVisibility = function togglePasswordVisibility(buttonEl) {
      const targetId = buttonEl?.dataset?.target;
      const inputEl = targetId ? document.getElementById(targetId) : null;
      if (!inputEl) return;

      const show = inputEl.type === "password";
      inputEl.type = show ? "text" : "password";
      buttonEl.dataset.passwordVisible = show ? "true" : "false";
      buttonEl.setAttribute("aria-label", show ? "Sembunyikan password" : "Tampilkan password");

      const eyeOn = buttonEl.querySelector(".pwd-eye-on");
      const eyeOff = buttonEl.querySelector(".pwd-eye-off");
      if (eyeOn && eyeOff) {
        eyeOn.style.display = show ? "none" : "inline-flex";
        eyeOff.style.display = show ? "inline-flex" : "none";
      }
    };
  }

  if (globalThis.window !== undefined && typeof globalThis.initDeploymentFormSelects !== "function") {
    globalThis.initDeploymentFormSelects = function initDeploymentFormSelects(formId = "myForm") {
      const root = document.getElementById(formId);
      if (!root) return false;

      const selects = root.querySelectorAll(".js-select2");
      if (!selects.length) return false;

      selects.forEach((el) => {
        const selector = `#${el.id}`;
        const placeholder = String(el.dataset.placeholder || (el.multiple ? "Pilih tags" : "Pilih opsi"));
        const allowClear = String(el.dataset.allowClear || "false") === "true";
        const opts = {
          placeholder,
          allowClear,
          width: "100%"
        };

        if (globalThis.NXUI && typeof globalThis.NXUI.initSelect2 === "function") {
          globalThis.NXUI.initSelect2(selector, opts);
        } else if (typeof globalThis.$?.fn?.select2 === "function") {
          globalThis.$(selector).select2(opts);
        }
      });

      return true;
    };
  }
}

function initFormEnhancers(formId = "myForm") {
  ensureGlobalFormHelpers();
  if (typeof globalThis.initDeploymentFormSelects === "function") {
    return globalThis.initDeploymentFormSelects(formId);
  }
  return false;
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
  static initFormEnhancers(formId = "myForm") {
    return initFormEnhancers(formId);
  }

  constructor({ elementFormId, getValidationBy = ["name"], required = {}, noValidationBy = [], type = "insert", spinner = null } = {}) {
    return new Promise(async (resolve) => {
      const form = document.getElementById(elementFormId);
      if (!form) { resolve(null); return; }

      // Auto-bind helper terkait type field pada form target.
      initFormEnhancers(elementFormId);

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
          const lbl = el ? getLabelText(form, el, name) : name;
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
            getLabelText(form, el, name);
          rules[name] = lbl;
        });
      }

      let isValid = true;

      // Hapus error satu field saat sudah valid (live validation)
      const clearError = (els) => {
        (Array.isArray(els) ? els : [els]).forEach((el) => {
          el.classList.remove("nw-invalid");
          const s2 = getSelect2Selection(el);
          if (s2) s2.classList.remove("nw-invalid-select2");
          // Hapus semua .nw-err di parentElement field ini
          el.parentElement?.querySelectorAll(".nw-err").forEach((e) => e.remove());
          const fg = el.closest(".form-group");
          fg?.querySelectorAll(".nw-inline-err").forEach((e) => e.remove());
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
        const isSelect = el?.tagName?.toLowerCase() === "select";
        const evt = (el.type === "file" || isSelect) ? "change" : "input";
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
        const s2 = getSelect2Selection(el);
        if (s2) s2.classList.add("nw-invalid-select2");

        const formGroup = el.closest(".form-group");
        const labelEl =
          form.querySelector(`label[for="${el.id || fieldName}"]`) ||
          form.querySelector(`label[for="${fieldName}"]`) ||
          formGroup?.querySelector(".form-group-header label") ||
          formGroup?.querySelector("dt label");

        if (labelEl) {
          labelEl.querySelectorAll(".nw-inline-err").forEach((e) => e.remove());
          const errInline = document.createElement("span");
          errInline.className = "nw-inline-err";
          errInline.textContent = "wajib diisi";
          labelEl.appendChild(errInline);
          return;
        }

        const err = document.createElement("div");
        err.className = "nw-err";
        err.textContent = `${label} wajib diisi`;
        const group = el.parentElement;
        group?.appendChild(err);
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
