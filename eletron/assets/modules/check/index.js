// Route `/checkable` — demo lengkap NXUI.Checkable (NexaCheckable)

/**
 * Dipanggil setelah innerHTML: input harus sudah di DOM sebelum `new NXUI.Checkable()`.
 */
function initCheckableDemo(container) {
  const c = new NXUI.Checkable();
  window.__nexaCheckableDemo = c;

  const logEl = container.querySelector("#checkable-log");

  const logPayload = (inputData) => {
    const safe = {
      id: inputData.id,
      name: inputData.name,
      value: inputData.value,
      checked: inputData.checked,
      type: inputData.type,
      class: inputData.class,
      data: inputData.data,
      attributesKeys: inputData.attributes
        ? Object.keys(inputData.attributes).slice(0, 12)
        : [],
    };
    if (logEl) {
      logEl.textContent = JSON.stringify(safe, null, 2);
    }
    console.log("[Checkable] change →", safe);
    if (inputData.type === "radio" && inputData.name) {
      console.log(
        "  getSelectedRadio(" + JSON.stringify(inputData.name) + "):",
        c.getSelectedRadio(inputData.name)
      );
    }
  };

  c.onSaveCallback(logPayload);

  container.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-checkable-action]");
    if (!btn) return;
    const act = btn.getAttribute("data-checkable-action");
    const demo = window.__nexaCheckableDemo;
    if (!demo) return;

    switch (act) {
      case "dump-all":
        console.log("getAllValues()", demo.getAllValues());
        alert("Lihat konsol: getAllValues()");
        break;
      case "dump-checked":
        console.log("getCheckedValues()", demo.getCheckedValues());
        alert("Lihat konsol: getCheckedValues()");
        break;
      case "dump-unchecked":
        console.log("getUncheckedValues()", demo.getUncheckedValues());
        alert("Lihat konsol: getUncheckedValues()");
        break;
      case "dump-groups":
        console.log("getRadioGroups()", demo.getRadioGroups());
        alert("Lihat konsol: getRadioGroups()");
        break;
      case "dump-by-slug":
        console.log('getDataByAttribute("data-slug")', demo.getDataByAttribute("data-slug"));
        alert("Lihat konsol: getDataByAttribute(\"data-slug\")");
        break;
      case "counts":
        console.log({
          Elements: demo.Elements().length,
          getCheckboxes: demo.getCheckboxes().length,
          getRadios: demo.getRadios().length,
        });
        alert(
          "Checkbox: " +
            demo.getCheckboxes().length +
            ", Radio: " +
            demo.getRadios().length +
            " (detail di konsol)"
        );
        break;
      case "set-notify-on":
        demo.setValueById("demo_cb_notify", true);
        break;
      case "set-notify-off":
        demo.setValueById("demo_cb_notify", false);
        break;
      case "set-switch-on":
        demo.setValueById("demo_switch_cross", true);
        break;
      case "set-switch-off":
        demo.setValueById("demo_switch_cross", false);
        break;
      case "reset":
        demo.reset();
        logEl.textContent = "(reset — ubah lagi input untuk callback)";
        break;
      case "checkall":
        demo.checkAll();
        console.warn(
          "checkAll() meng-set semua checkbox+radio; grup radio bisa berperilaku aneh — hanya untuk demo API."
        );
        break;
      default:
        break;
    }
  });
}

export async function checkable(page, route) {
  route.register(
    page,
    async (
      routeName,
      container,
      routeMeta = {
        title: "NXUI.Checkable — demo fitur",
        description: "Checkbox, switch (checkbox bergaya), radio, callback, getAllValues, data-*.",
      },
      style,
      nav = {}
    ) => {
      route.routeMetaByRoute.set(page, routeMeta);
      console.log("📍 Navigating to:", NEXA);

      container.innerHTML = `
<style>
  .nexa-checkable-demo { max-width: 720px; margin: 0 auto; font-family: system-ui, sans-serif; }
  .nexa-checkable-demo h1 { font-size: 1.4rem; }
  .nexa-checkable-demo .sub { color: #555; margin-bottom: 1rem; }
  .nexa-checkable-demo section {
    margin-bottom: 1.25rem; padding: 1rem; border: 1px solid #e0e0e0; border-radius: 8px; background: #fafafa;
  }
  .nexa-checkable-demo h2 { font-size: 1rem; margin: 0 0 0.6rem; }
  .nexa-checkable-demo .row { margin: 0.4rem 0; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
  .nexa-checkable-demo label { cursor: pointer; }
  .nexa-checkable-demo .actions { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.75rem; }
  .nexa-checkable-demo button {
    padding: 0.35rem 0.65rem; font-size: 0.85rem; cursor: pointer; border: 1px solid #ccc; border-radius: 4px; background: #fff;
  }
  .nexa-checkable-demo button:hover { background: #f0f8ff; }
  .nexa-checkable-demo pre#checkable-log {
    margin-top: 1rem; padding: 0.75rem; background: #1e1e1e; color: #d4d4d4; border-radius: 6px; font-size: 0.75rem; overflow: auto; max-height: 220px;
  }
  .nexa-checkable-demo .hint { font-size: 0.8rem; color: #666; margin-top: 0.35rem; }
  /* Switch (sama pola dengan Form/form.css — di sini scoped karena halaman ini tidak memuat form.css) */
  .nexa-checkable-demo .nx-switch-grid { display: flex; flex-wrap: wrap; gap: 15px; }
  .nexa-checkable-demo .nx-switch-item { position: relative; }
  .nexa-checkable-demo .nx-switch-item input[type="checkbox"] {
    position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0;
  }
  .nexa-checkable-demo .nx-switch-item label {
    display: flex; align-items: center; cursor: pointer; padding: 0; transition: all 0.3s ease;
    font-weight: 500; color: #333; position: relative; overflow: hidden;
  }
  .nexa-checkable-demo .nx-switch {
    position: relative; width: 50px; height: 24px; background: #ccc; border-radius: 24px;
    margin-right: 12px; transition: all 0.3s ease; flex-shrink: 0;
  }
  .nexa-checkable-demo .nx-switch::after {
    content: ""; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px;
    background: #fff; border-radius: 50%; transition: all 0.3s ease;
  }
  .nexa-checkable-demo .nx-switch-item input:checked ~ label .nx-switch { background: #0168fa; }
  .nexa-checkable-demo .nx-switch-item input:checked ~ label .nx-switch::after { transform: translateX(26px); }
  .nexa-checkable-demo .nx-switch-item input[type="checkbox"]:focus ~ label .nx-switch {
    box-shadow: 0 0 0 3px rgba(1, 104, 250, 0.2);
  }
  .nexa-checkable-demo .nx-switch-item label:hover .nx-switch { background: #bbb; }
  .nexa-checkable-demo .nx-switch-item input:checked ~ label:hover .nx-switch { background: #0152d9; }
</style>
<div class="nexa-checkable-demo">
  <h1>NXUI.Checkable — demo fitur</h1>
  <p class="sub">Route: <code>${routeName}</code> · Instance memindai DOM saat <code>new NXUI.Checkable()</code> — ubah input untuk melihat callback · Detail API di konsol.</p>

  <section>
    <h2>Checkbox (id unik + <code>data-*</code> → camelCase di <code>inputData.data</code>)</h2>
    <div class="row">
      <input type="checkbox" id="demo_cb_notify" name="notify" value="yes" data-user-tier="pro" data-feature-flag="beta" />
      <label for="demo_cb_notify">Notifikasi email (<code>id=demo_cb_notify</code>, <code>data-user-tier</code> → <code>userTier</code>)</label>
    </div>
    <div class="row">
      <input type="checkbox" id="demo_cb_terms" name="terms" value="accepted" class="demo-legal" data-slug="terms-v2" />
      <label for="demo_cb_terms">Setuju syarat (<code>data-slug</code> untuk <code>getDataByAttribute</code>)</label>
    </div>
    <div class="row">
      <input type="checkbox" id="demo_cb_extra" name="extra" value="on" />
      <label for="demo_cb_extra">Opsi tambahan</label>
    </div>
  </section>

  <section>
    <h2>Radio — grup <code>plan</code> &amp; <code>size</code></h2>
    <p class="hint"><code>getSelectedRadio("plan")</code> / <code>getRadioGroups()</code></p>
    <div class="row"><strong>Plan:</strong></div>
    <div class="row">
      <input type="radio" id="demo_r_plan_basic" name="plan" value="basic" checked data-slug="plan-basic" />
      <label for="demo_r_plan_basic">Basic</label>
    </div>
    <div class="row">
      <input type="radio" id="demo_r_plan_pro" name="plan" value="pro" data-slug="plan-pro" />
      <label for="demo_r_plan_pro">Pro</label>
    </div>
    <div class="row"><strong>Ukuran:</strong></div>
    <div class="row">
      <input type="radio" id="demo_r_size_s" name="size" value="s" />
      <label for="demo_r_size_s">S</label>
    </div>
    <div class="row">
      <input type="radio" id="demo_r_size_m" name="size" value="m" checked />
      <label for="demo_r_size_m">M</label>
    </div>
    <div class="row">
      <input type="radio" id="demo_r_size_l" name="size" value="l" />
      <label for="demo_r_size_l">L</label>
    </div>
  </section>

  <section>
    <h2>Switch UI (tetap <code>type="checkbox"</code>)</h2>
    <p class="hint">Pola Nexa/Ekastic: <code>input</code> disembunyikan, <code>label</code> berisi <code>span.nx-switch</code>. <strong>NXUI.Checkable</strong> memperlakukannya seperti checkbox biasa (<code>change</code>, <code>setValueById</code> menggerakkan thumb).</p>
    <div class="nx-switch-grid">
      <div class="nx-switch-item">
        <input type="checkbox" id="demo_switch_cross" name="ui_cross" value="on" data-ui-kind="switch" />
        <label for="demo_switch_cross">
          <span class="nx-switch"></span>
          Type Cross
        </label>
      </div>
    </div>
  </section>

  <section>
    <h2>Aksi demo (API instance)</h2>
    <div class="actions">
      <button type="button" data-checkable-action="dump-all">getAllValues()</button>
      <button type="button" data-checkable-action="dump-checked">getCheckedValues()</button>
      <button type="button" data-checkable-action="dump-unchecked">getUncheckedValues()</button>
      <button type="button" data-checkable-action="dump-groups">getRadioGroups()</button>
      <button type="button" data-checkable-action="dump-by-slug">getDataByAttribute("data-slug")</button>
      <button type="button" data-checkable-action="counts">Elements / getCheckboxes / getRadios</button>
    </div>
    <div class="actions">
      <button type="button" data-checkable-action="set-notify-on">setValueById("demo_cb_notify", true)</button>
      <button type="button" data-checkable-action="set-notify-off">setValueById("demo_cb_notify", false)</button>
      <button type="button" data-checkable-action="set-switch-on">setValueById("demo_switch_cross", true)</button>
      <button type="button" data-checkable-action="set-switch-off">setValueById("demo_switch_cross", false)</button>
      <button type="button" data-checkable-action="reset">reset()</button>
      <button type="button" data-checkable-action="checkall">checkAll() ⚠️</button>
    </div>
    <p class="hint"><code>checkAll()</code> memakai semua input terdaftar; pada grup radio hasilnya bisa tidak wajar — hanya untuk uji API. <code>getElementById</code> / <code>getElementsByName</code> / <code>getElementsByClass</code> adalah wrapper query DOM — coba di konsol: <code>__nexaCheckableDemo.getElementById("demo_cb_notify")</code>.</p>
  </section>

  <section>
    <h2>Payload callback terakhir (tanpa node DOM)</h2>
    <pre id="checkable-log">(centang / pilih radio untuk mengisi)</pre>
  </section>
</div>
`;

      initCheckableDemo(container);
    }
  );
}
