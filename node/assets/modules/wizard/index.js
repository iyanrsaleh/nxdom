/**
 * Route `/wizard` — contoh NexaWizard (form bertahap).
 * Selaras modul `forms`: kontrol memakai `.form-control` / `.form-select`, grid `nx-col-*`,
 * footer wizard memakai `.btn` Primer (lihat `NexaWizard.js` + `wizard/index.css`).
 *
 * - Skema sama seperti NexaFloating (`form` + `variables`)
 * - `settings.wizard` mengatur pembagian langkah (`fieldsPerStep` atau `steps` + `titles`)
 * - `settings.onSubmit(detail)` — callback ringkas; event `nexaFormSubmit` tetap di-dispatch
 *
 * `WIZARD_UI`:
 * - `"content"` — wizard di-render di halaman (`#nexaWizardDemo`)
 * - `"modal"` — tombol membuka `NXUI.Modal` dengan `wizard:` (sama pola `templates/modal.js` + `floating:`)
 */
export async function wizard(page, route) {
  route.register(
    page,
    async (
      routeName,
      container,
      routeMeta = {
        title: "Contoh NexaWizard | App",
        description: "Form step-by-step dengan NexaWizard.",
      },
      style,
      nav = {},
    ) => {
      route.routeMetaByRoute.set(page, routeMeta);

      /** `"content"` | `"modal"` */
      const WIZARD_UI = "content";

      const wizardConfig = buildWizardSchema("form_demo_wizard");

      if (WIZARD_UI === "modal") {
        container.innerHTML = `
        <section class="nexa-wizard-demo">
          <h1>Contoh NexaWizard (modal)</h1>
          <p class="note">Wizard dibuka di <strong>NXUI.Modal</strong> — isi body dari <code>NexaWizard</code> lewat opsi <code>wizard:</code> di <code>NexaModalHtml.js</code> (mirip <code>floating:</code> untuk NexaFloating).</p>
          <p><a href="javascript:void(0)" onclick="nx.openWizardModalDemo('wizard1'); return false;">Buka wizard di modal</a></p>
          <h2 class="nexa-wizard-demo__output-title">Output (<code>nexaFormSubmit</code>)</h2>
          <pre id="nexaWizardDemoOutput" class="nexa-wizard-demo__output"></pre>
        </section>
      `;
        return;
      }

      container.innerHTML = `
        <section class="nexa-wizard-demo">
          <h1>Contoh NexaWizard (content)</h1>
          <p class="note">Field dikelompokkan per langkah lewat <code>settings.wizard</code>. Gunakan <strong>Berikutnya</strong> sampai langkah terakhir, lalu <strong>Kirim</strong>.</p>
          <div id="nexaWizardDemo"></div>
          <h2 class="nexa-wizard-demo__output-title">Output (<code>nexaFormSubmit</code>)</h2>
          <pre id="nexaWizardDemoOutput" class="nexa-wizard-demo__output"></pre>
        </section>
      `;

      const mount = document.getElementById("nexaWizardDemo");
      if (!mount) {
        return;
      }

      try {
        const w = new NXUI.FormWizard(wizardConfig, {
          mode: "insert",
          footer: true,
        });
        mount.innerHTML = "";
        w.render(mount);
      } catch (err) {
        console.error("[wizard.js] NexaWizard gagal:", err);
        mount.insertAdjacentHTML(
          "beforeend",
          `<p class="note color-fg-danger" role="alert">Gagal memuat wizard: ${String(err?.message || err)}</p>`,
        );
      }
    },
  );
}

/** Definisi field — dipakai wizard inline & modal (satu sumber). */
function getWizardFormDef() {
  return {
    nama: {
      condition: true,
      type: "text",
      label: "Nama",
      placeholder: "Nama lengkap",
      name: "nama",
      id: "nama",
      validation: "2",
    },
    email: {
      condition: true,
      type: "email",
      label: "Email",
      placeholder: "email@contoh.com",
      name: "email",
      id: "email",
      validation: "2",
    },
    telepon: {
      condition: true,
      type: "tel",
      label: "Telepon",
      placeholder: "08xxxxxxxxxx",
      name: "telepon",
      id: "telepon",
      validation: "2",
    },
    kota: {
      condition: true,
      type: "text",
      label: "Kota",
      placeholder: "Kota / kabupaten",
      name: "kota",
      id: "kota",
      validation: "2",
    },
    pesan: {
      condition: true,
      type: "text",
      label: "Pesan",
      placeholder: "Pesan singkat",
      name: "pesan",
      id: "pesan",
      validation: "2",
    },
  };
}

/**
 * Skema lengkap NexaWizard untuk `new NXUI.FormWizard(...)` / `NXUI.Modal({ wizard })`.
 * @param {string} formId — id unik &lt;form&gt; (bedakan inline vs modal)
 */
function buildWizardSchema(formId) {
  const demoForm = getWizardFormDef();
  return {
    id: formId,
    label: "Wizard demo",
    variables: ["nama", "email", "telepon", "kota", "pesan"],
    form: demoForm,
    settings: {
      floating: true,
      layout: "vertical",
      validation: true,
      wizard: {
        fieldsPerStep: 2,
        titles: ["Data diri", "Kontak", "Lokasi & pesan"],
        labels: {
          prev: "Sebelumnya",
          next: "Berikutnya",
          submit: "Kirim",
        },
      },
      onSubmit(detail) {
        const out = document.getElementById("nexaWizardDemoOutput");
        if (out) {
          out.textContent = JSON.stringify(detail, null, 2);
        }
        console.log("[wizard.js] nexaFormSubmit", detail);
      },
    },
  };
}

/**
 * Buka modal berisi NexaWizard — setara `nx.openModalFloatingDemo` di `modal.js`, dengan `wizard:` menggantikan `floating:`.
 */
nx.openWizardModalDemo = async function (id) {
  try {
    const modalID = "setModal_" + id;
    const formId = `form_modal_${id}_nexa`;
    const wizardCfg = buildWizardSchema(formId);

    await NXUI.Modal({
      elementById: modalID,
      styleClass: "w-500px",
      minimize: true,
      label: "Wizard — langkah demi langkah",
      wizard: wizardCfg,
      mode: "insert",
    });
    await NXUI.Modal.open(modalID);
  } catch (e) {
    console.error("[wizard.js] Modal wizard error:", e);
  }
};
