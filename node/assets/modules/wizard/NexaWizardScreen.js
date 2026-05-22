import { NexaFormsScreen } from "../forms/NexaFormsScreen.js";

/**
 * Route `/wizard` — contoh NexaWizard (form bertahap):
 * - Skema sama seperti NexaFloating (`form` + `variables`)
 * - `settings.wizard` mengatur pembagian langkah (`fieldsPerStep` atau `steps` + `titles`)
 * - `settings.card === true` — bungkus wizard dengan `.nx-card` (header = Form title)
 * - `settings.ToastInfo !== "false"` — toast info (markup selaras `NexaFormsScreen`)
 * - `settings.applications === "development"` — blok SDK Usage (selaras `NexaFormsScreen`)
 * - `settings.onSubmit(detail)` — callback ringkas; event `nexaFormSubmit` tetap di-dispatch
 *
 * `WIZARD_UI`:
 * - `"content"` — wizard di-render di halaman (`#nexaWizardDemo`)
 * - `"modal"` — tombol membuka `NXUI.Modal` dengan `wizard:` (sama pola `templates/modal.js` + `floating:`)
 */

/** Sama urutan pencarian dengan `NexaModalScreen.resolveHandler` (tanpa impor modal). */
function resolveWizardSubmitHandler(functionName) {
  const method = String(functionName || "").trim();
  if (!method) {
    return null;
  }
  if (typeof globalThis?.nx?.[method] === "function") {
    return globalThis.nx[method];
  }
  if (typeof globalThis?.NXUI?.[method] === "function") {
    return globalThis.NXUI[method];
  }
  if (typeof globalThis?.[method] === "function") {
    return globalThis[method];
  }
  if (typeof globalThis?.nx?._global?.[method] === "function") {
    return globalThis.nx._global[method];
  }
  return null;
}

function escapeHtml(str) {
  return String(str ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/"/g, "&quot;");
}

function escapeJsSingleQuoted(str) {
  return String(str ?? "").replace(/\\/g, "\\\\").replace(/'/g, "\\'");
}

/**
 * Tutup toast wizard & simpan `ToastInfo: false` di `production.setting.wizard`
 * (selaras `NexaFormsScreen.dismissToastInfo` untuk forms).
 */
export const NexaWizardScreen = {
  /**
   * Entry `Screen.run('wizard')` / `NX.Screen` — mengembalikan HTML string (sama `wizard(key)`).
   * Kunci bucket: `appID` / `key` — bukan `id` baris ({@see NexaFormsScreen.resolveConfig}).
   * Preload satu baris: `recordId` (opsional), selaras formulir layar penuh.
   */
  async render(data) {
    const payload = data && typeof data === "object" ? data : {};
    const nested = payload.data && typeof payload.data === "object" ? payload.data : {};
    const key = String(
      payload.appID ??
        payload.appId ??
        nested.appID ??
        nested.appId ??
        payload.key ??
        nested.key ??
        "",
    ).trim();
    if (!key) {
      return '<div class="setting-info"><p>nexaStore key belum diset (appID atau key).</p></div>';
    }
    const recordId = NexaFormsScreen.resolveRecordId(payload);
    const appForPayload = String(
      payload.appID ??
        payload.appId ??
        nested.appID ??
        nested.appId ??
        key ??
        "",
    ).trim();
    return wizard(key, { recordId, appForPayload });
  },

  async dismissToastInfo(key, button) {
    const bucketKey = String(key || "").trim();
    const toast = button?.closest?.(".Toast");

    if (!bucketKey) {
      if (toast) {
        toast.classList.add("Toast--animateOut");
        setTimeout(() => toast.remove(), 180);
      }
      return;
    }

    try {
      const latestMeta = await NXUI.ref.nexaStore(bucketKey).get();
      const production =
        latestMeta?.production && typeof latestMeta.production === "object"
          ? latestMeta.production
          : {};
      const existingSetting =
        production?.setting && typeof production.setting === "object"
          ? production.setting
          : {};
      const existingWizard =
        existingSetting?.wizard && typeof existingSetting.wizard === "object"
          ? existingSetting.wizard
          : {};
      production.setting = {
        ...existingSetting,
        wizard: {
          ...existingWizard,
          ToastInfo: "false",
        },
      };
      await NXUI.ref.merge(latestMeta, { production });
    } catch (err) {
      console.error("Gagal menyimpan ToastInfo (wizard):", err);
    }

    if (toast) {
      toast.classList.add("Toast--animateOut");
      setTimeout(() => toast.remove(), 180);
    }
  },
};

globalThis.NexaWizardScreen = NexaWizardScreen;

/** Supaya `wizard()` dipanggil berulang tidak merender instance usang. */
let _wizardMountGeneration = 0;

/**
 * Tunggu `#mountId` benar-benar ada di DOM (HTML dari `wizard()` sering disisipkan async).
 * `setTimeout(50)` tidak cukup — sering `getElementById` null → wizard tidak tampil.
 */
function mountFormWizardWhenReady(mountId, wizardConfig, generation, formWizardOpts = {}) {
  const maxFrames = 300;
  let frames = 0;

  const tick = () => {
    if (generation !== _wizardMountGeneration) {
      return;
    }
    const mount = document.getElementById(mountId);
    if (mount) {
      try {
        const mode =
          formWizardOpts.mode === "update" ? "update" : "insert";
        const initialValue = {};
        const formSpec = wizardConfig?.form && typeof wizardConfig.form === "object"
          ? wizardConfig.form
          : {};
        for (const [fieldName, spec] of Object.entries(formSpec)) {
          if (!spec || typeof spec !== "object" || Array.isArray(spec)) continue;
          if (!Object.prototype.hasOwnProperty.call(spec, "value")) continue;
          initialValue[fieldName] = spec.value;
        }
        const w = new NXUI.FormWizard(wizardConfig, {
          mode,
          footer: true,
          value: initialValue,
        });
        mount.innerHTML = "";
        w.render(mount);
        mount._nexaWizardInstance = w;
      } catch (err) {
        console.error("[wizard.js] NexaWizard gagal:", err);
        mount.insertAdjacentHTML(
          "beforeend",
          `<p style="color:#b00020;">Gagal memuat wizard: ${String(err?.message || err)}</p>`,
        );
      }
      return;
    }
    if (++frames > maxFrames) {
      console.warn(
        "[wizard.js] Elemen mount belum ada setelah menunggu:",
        mountId,
        "(periksa elementById & urutan render NXUI.Refresh)",
      );
      return;
    }
    requestAnimationFrame(tick);
  };

  requestAnimationFrame(tick);
}

export async function wizard(key, renderOpts = {}) {
  const rawGet = await NXUI.ref.nexaStore(key).get();
  const appName = String(rawGet?.appname || "Wizard").trim() || "Wizard";
  const fallbackElementById = `wizard_${appName.replace(/[^a-zA-Z0-9_-]/g, "") || "mount"}`;
  const settings = {
    elementById: fallbackElementById,
    ...(rawGet?.production?.setting?.wizard || {}),
  };

  let wizardMode = "insert";
  const fromClone = NexaFormsScreen.cloneFormFields(
    rawGet.production?.from ?? {},
  );
  const rid = renderOpts.recordId;
  const payloadApp = String(renderOpts.appForPayload ?? key ?? "").trim();
  const hasRecordId =
    rid !== null &&
    rid !== undefined &&
    rid !== "" &&
    !(typeof rid === "number" && !Number.isFinite(rid));

  if (hasRecordId && payloadApp && typeof NXUI?.Payload !== "undefined") {
    try {
      const rows = await new NXUI.Payload({
        appId: payloadApp,
        recordId: rid,
      }).select();
      const row = NexaFormsScreen.pickFirstExecuteRow(rows);
      if (row) {
        NexaFormsScreen.hydrateDemoFormFromRow(fromClone, row);
        wizardMode = "update";
      }
    } catch (err) {
      console.warn(
        "NexaWizardScreen: gagal memuat baris untuk preload (recordId):",
        err,
      );
    }
  }

  const rawForSchema = {
    ...rawGet,
    production: {
      ...(rawGet.production && typeof rawGet.production === "object"
        ? rawGet.production
        : {}),
      from: fromClone,
    },
  };

  const mountId = String(settings?.elementById || fallbackElementById).trim() || fallbackElementById;
  const wizardConfig = await buildWizardSchema(settings, rawForSchema, {
    mountId,
    preserveOnUpdate: wizardMode === "update",
  });

  const formTitleRaw = settings?.formTitle;
  const hidePageTitle =
    formTitleRaw === false ||
    formTitleRaw === "false" ||
    String(formTitleRaw ?? "").trim().toLowerCase() === "false";
  const pageHeadingHtml = hidePageTitle
    ? ""
    : `<h2>${formTitleRaw || rawGet?.appname}</h2>`;

  const generation = ++_wizardMountGeneration;
  mountFormWizardWhenReady(mountId, wizardConfig, generation, {
    mode: wizardMode,
  });

  const toastInfoRaw = String(settings?.ToastInfo ?? "").trim().toLowerCase();
  const showToastInfo = toastInfoRaw !== "false";
  const toastBody = escapeHtml(
    String(
      settings?.deskripsi ||
        rawGet?.deskripsi ||
        "Informasi: Data berhasil dimuat.",
    ),
  );
  const toastKeyJs = escapeJsSingleQuoted(key);
  const toastHtml = showToastInfo
    ? `<div style="width:100%;" class="pt-10px">
        <div class="Toast">
          <div class="Toast-border">
            <span class="Toast-icon" aria-hidden="true"><span class="material-symbols-outlined Toast-iconSymbol">info</span></span>
            <div class="Toast-content">${toastBody}</div>
            <button class="Toast-dismissButton" type="button" aria-label="Tutup notifikasi" onclick="NexaWizardScreen.dismissToastInfo('${toastKeyJs}', this)">✕</button>
          </div>
        </div>
      </div>`
    : "";

  const appNameSdk = String(rawGet?.appname || "Form");
  const defaultMethodSdk = `method${appNameSdk.replaceAll(/\s+/g, "")}`;
  const sendMethodSdk = String(
    settings?.methodSubmit || settings?.methodById || defaultMethodSdk,
  ).trim();
  const submitLabelSdk = String(settings?.labelSubmit || "Submit");
  const appsRaw = String(settings?.applications ?? "").trim().toLowerCase();
  const codeSDK =
    appsRaw === "development"
      ? `

      <div class="nx-readme-term nx-readme-term--light w-700px">
      <div class="nx-readme-term-bar"><span></span><span></span><span></span><label>SDK Usage</label></div>
      <div class="nx-readme-pre"><pre class="language-javascript"><code class="language-javascript">const Screen = new NX.Screen({
  appID: "${String(key).replace(/\\/g, "\\\\").replace(/"/g, '\\"')}",
  method: "${String(sendMethodSdk).replace(/\\/g, "\\\\").replace(/"/g, '\\"')}",
  strictValidation: true,
  // recordId: 19, // opsional: mode update / preload baris
});

const ScreenWizard = await Screen.run('wizard');

// Finis — handler boleh async; NexaWizard meng-await Promise sebelum reset form.
nx.${sendMethodSdk} = async function (data) {
  // await fetch('/api/...', { method: 'POST', body: JSON.stringify(data.formData) });
};
</code></pre>
</div>
        </div>
   `
      : "";

  const useCard =
    settings?.card === true ||
    String(settings?.card ?? "").trim().toLowerCase() === "true";

  if (useCard) {
    // Judul card = Form title (sama logika label wizard); bukan field cardHeader.
    const cardHeaderPlain = hidePageTitle
      ? String(rawGet?.appname || "Wizard")
      : String(formTitleRaw || rawGet?.appname || "Wizard");
    const cardHeaderText = escapeHtml(cardHeaderPlain);

    return `
      <div class="nx-card">
        <div class="nx-card-header">${cardHeaderText}</div>
        <div class="nx-card-body">
          <div id="${settings.elementById}"></div>
          ${toastHtml}
          ${codeSDK}
        </div>
      </div>
    `;
  }

  return `
      ${pageHeadingHtml}
      <div style="width:100%;">
        <div id="${settings.elementById}"></div>
        ${toastHtml}
        ${codeSDK}
      </div>
  `;
}


/**
 * Skema lengkap NexaWizard untuk `new NXUI.FormWizard(...)` / `NXUI.Modal({ wizard })`.
 * @param {string} formId — id unik &lt;form&gt; (bedakan inline vs modal)
 */
export async function buildWizardSchema(settings,rawGet, runtimeOpts = {}) {
  const froms = rawGet?.production?.from && typeof rawGet.production.from === "object"
    ? rawGet.production.from
    : {};
  const variables = Object.keys(froms);
  const titles = settings?.stepLabel
    ? settings.stepLabel.split("|").map((s) => s.trim())
    : [];

  const ft = settings?.formTitle;
  const formTitleIsDisabled =
    ft === false ||
    ft === "false" ||
    String(ft ?? "").trim().toLowerCase() === "false";
  const wizardLabel = formTitleIsDisabled
    ? rawGet?.appname || "Wizard"
    : ft || rawGet?.appname || "Wizard";

  const appName = String(rawGet?.appname || "Form");
  const defaultMethodSubmit = `method${appName.replaceAll(/\s+/g, "")}`;
  // Kanonik: `methodSubmit` (sama dengan `setting.js`). `methodById` hanya dibaca jika data lama belum dimigrasi.
  const sendMethod = String(
    settings?.methodSubmit || settings?.methodById || defaultMethodSubmit,
  ).trim();

  return {
    id: String(settings?.elementById || runtimeOpts?.mountId || "nexaWizardMount"),
    methodSubmit: sendMethod,
    label: wizardLabel,
    variables: variables,
    form: froms,
    settings: {
      floating: true,
      layout: "vertical",
      validation: true,
      wizard: {
        fieldsPerStep: Number(settings?.perStep),
        titles: titles,
        labels: {
          prev: settings?.labelPrev,
          next: settings?.labelNext,
          submit: settings?.labelSubmit || "Submit",
        },
      },
      async onSubmit(detail) {
        let fn = resolveWizardSubmitHandler(sendMethod);
        let used = sendMethod;
        if (typeof fn !== "function" && sendMethod !== defaultMethodSubmit) {
          fn = resolveWizardSubmitHandler(defaultMethodSubmit);
          if (typeof fn === "function") {
            used = defaultMethodSubmit;
            console.warn(
              `[wizard] Handler "${sendMethod}" tidak ada; memakai bawaan "${defaultMethodSubmit}" (sesuai appname). Perbarui methodSubmit di pengaturan wizard.`,
            );
          }
        }
        if (typeof fn === "function") {
          try {
            await Promise.resolve(fn(detail));
          } catch (e) {
            console.error("[wizard] onSubmit handler error:", e);
          }
        } else if (used) {
          console.warn(
            `[wizard] Handler "${sendMethod}"${sendMethod !== defaultMethodSubmit ? ` dan "${defaultMethodSubmit}"` : ""} tidak ditemukan pada nx / NXUI.`,
          );
        }

        const shouldPreserve = runtimeOpts?.preserveOnUpdate === true;
        if (shouldPreserve && detail?.formData && typeof detail.formData === "object") {
          const mount = document.getElementById(String(runtimeOpts?.mountId || ""));
          const instance = mount?._nexaWizardInstance;
          if (instance && typeof instance.setData === "function") {
            instance.setData(detail.formData);
          }
        }
       
      },
    },
  };
}

/**
 * Buka modal berisi NexaWizard — setara `nx.openModalFloatingDemo` di `modal.js`, dengan `wizard:` menggantikan `floating:`.
 */
// nx.methodPetani = async function (data) { }
// nx.openWizardModalDemo = async function (id) {
//   try {
//     const modalID = "setModal_" + id;
//     const formId = `form_modal_${id}_nexa`;
//     const wizardCfg = buildWizardSchema(formId);

//     await NXUI.Modal({
//       elementById: modalID,
//       styleClass: "w-500px",
//       minimize: true,
//       label: "Wizard — langkah demi langkah",
//       wizard: wizardCfg,
//       mode: "insert",
//     });
//     await NXUI.Modal.open(modalID);
//   } catch (e) {
//     console.error("[wizard.js] Modal wizard error:", e);
//   }
// };
