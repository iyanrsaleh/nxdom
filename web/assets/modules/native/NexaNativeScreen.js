import { buildQuery } from "./buildQuery.js";
export class NexaNativeScreen {
  static normalizeObject(value) {
    return value && typeof value === "object" && !Array.isArray(value) ? value : {};
  }

  static resolveRecordId(data) {
    const payload = NexaNativeScreen.normalizeObject(data);
    const nested = NexaNativeScreen.normalizeObject(payload.data);
    const rid = payload.recordId ?? nested.recordId;
    if (rid === null || rid === undefined || rid === "") return null;
    if (typeof rid === "number" && !Number.isFinite(rid)) return null;
    return rid;
  }

  static resolveHandler(functionName) {
    const method = String(functionName || "").trim();
    if (!method) return null;
    if (typeof globalThis?.nx?.[method] === "function") return globalThis.nx[method];
    if (typeof globalThis?.NXUI?.[method] === "function") return globalThis.NXUI[method];
    if (typeof globalThis?.[method] === "function") return globalThis[method];
    if (typeof globalThis?.nx?._global?.[method] === "function") return globalThis.nx._global[method];
    return null;
  }

  static async resolveConfig(data) {
    let rowData = null;
    try {
      const production = NexaNativeScreen.normalizeObject(data?.production);
      if (Object.keys(production).length > 0) {
        const setbuildQuery = await buildQuery(production);
        const dataTabel = await NXUI.Storage().models("Office").executeOperation(setbuildQuery);
        rowData = dataTabel?.data?.response?.[0] ?? null;
      }
    } catch (err) {}







    const payload = NexaNativeScreen.normalizeObject(data);
    const nested = NexaNativeScreen.normalizeObject(payload.data);
    const payloadApp = NexaNativeScreen.normalizeObject(payload.app);
    const nestedApp = NexaNativeScreen.normalizeObject(nested.app);
    // NOTE:
    // Di project ini, bucket/setting untuk IndexedDB biasanya memakai `key`,
    // sedangkan `id` adalah id entitas lain (mis. "3ras_*").
    // Maka `key` harus diprioritaskan dulu agar lookup storage tidak salah.
    const key = String(
      payload.key ??
      payloadApp.key ??
      nestedApp.key ??
      payload.appID ??
      payloadApp.appID ??
      nestedApp.appID ??
      payload.appId ??
      payloadApp.appId ??
      nestedApp.appId ??
      payload.id ??
      payloadApp.id ??
      nestedApp.id ??
      nested.key ??
      nested.appID ??
      nested.appId ??
      nested.id ??
      ""
    ).trim();
    const method = String(
      payload.method ||
      payloadApp.method ||
      nestedApp.method ||
      nested.method ||
      ""
    ).trim();
    const strictValidation =
      payload.strictValidation === true ||
      payloadApp.strictValidation === true ||
      nestedApp.strictValidation === true ||
      nested.strictValidation === true;
    const recordId = NexaNativeScreen.resolveRecordId(payload);

    return { key, method, strictValidation, recordId, rowData };
  }

  static cloneFormFields(from = {}) {
    if (!from || typeof from !== "object") return {};
    return Object.fromEntries(
      Object.entries(from)
        .filter(([, f]) => f && typeof f === "object" && !Array.isArray(f) && f.forms === true)
        .map(([k, f]) => [k, { ...f }])
    );
  }

  static hydratemyFromFromRow(myFrom, row) {
    if (!myFrom || typeof myFrom !== "object" || !row || typeof row !== "object") return myFrom;
    for (const fieldName of Object.keys(myFrom)) {
      const spec = myFrom[fieldName];
      if (!spec || typeof spec !== "object" || Array.isArray(spec)) continue;
      if (!Object.prototype.hasOwnProperty.call(row, fieldName)) continue;
      myFrom[fieldName] = { ...spec, value: row[fieldName] };
    }
    return myFrom;
  }

  static pickFirstExecuteRow(result) {
    const envelope = result?.data ?? result ?? {};
    const list = envelope.response ?? envelope.rows;
    return Array.isArray(list) && list.length ? list[0] : null;
  }

  static async dismissToastInfo(key, button) {
    const toast = button?.closest?.(".Toast");
    if (toast) {
      toast.classList.add("Toast--animateOut");
      setTimeout(() => toast.remove(), 180);
    }
  }

  static normalizeStandard(source = {}, method = "", strictValidation = false, recordId = null) {
    const obj = NexaNativeScreen.normalizeObject(source);
    const appname = String(obj?.appname || "Form");
    const methodById = `method${appname.replaceAll(/\s+/g, "")}`;
    const prod = NexaNativeScreen.normalizeObject(obj.production);
    const setting = NexaNativeScreen.normalizeObject(prod.setting);
    const forms = NexaNativeScreen.normalizeObject(setting.forms);
    const key = String(obj?.key ?? obj?.appID ?? obj?.appId ?? obj?.id ?? "").trim();

    return {
      ...obj,
      appID: key,
      key,
      method: method || forms.methodById || methodById,
      strictValidation: strictValidation === true,
      recordId: recordId ?? null,
      production: {
        ...prod,
        setting: {
          ...setting,
          forms: {
            ...forms,
            methodById: method || forms.methodById || methodById,
          },
        },
      },
    };
  }

  static pickInlineSource(data) {
    const payload = NexaNativeScreen.normalizeObject(data);
    const nested = NexaNativeScreen.normalizeObject(payload.data);
    const payloadApp = NexaNativeScreen.normalizeObject(payload.app);
    const nestedApp = NexaNativeScreen.normalizeObject(nested.app);
    const candidates = [nestedApp, payloadApp, nested, payload];
    for (const item of candidates) {
      if (item?.production && typeof item.production === "object") {
        return item;
      }
    }
    return null;
  }

  static async render(data) {
    const config = await NexaNativeScreen.resolveConfig(data);
    const inlineSource = NexaNativeScreen.pickInlineSource(data);
    let stor;

    if (inlineSource) {
      // Native mode: utamakan seluruh payload yang dikirim caller.
      stor = NexaNativeScreen.normalizeStandard(
        inlineSource,
        config.method,
        config.strictValidation,
        config.recordId
      );
    } else {
      // MODE NATIVE TIDAK TERGANTUNG nexaStore / IndexedDB.
      // Jika payload tidak membawa konfigurasi `production`, maka tidak ada sumber data untuk dirender.
      return '<div class="setting-info"><p>Native membutuhkan payload konfigurasi (production). Bucket/nexaStore dimatikan untuk mode native.</p></div>';
    }

    const myFrom = NexaNativeScreen.cloneFormFields(stor.production.from ?? {});
    // MODE NATIVE:
    // Berbeda dengan NexaFormsScreen (yang tergantung nexaStore / IndexedDB),
    // untuk mode native kita TIDAK melakukan preload baris lewat NXUI.Payload.
    // Seluruh konfigurasi + default value diambil langsung dari payload `data`
    // (inlineSource) atau dari bucket (jika diberikan), sehingga tidak ada
    // ketergantungan ke store eksternal.

    if (config.rowData && typeof config.rowData === "object") {
      NexaNativeScreen.hydratemyFromFromRow(myFrom, config.rowData);
    }

    const setting = stor?.production?.setting?.forms || {};
    const toastKey = String(stor?.key ?? config.key ?? "").trim();
    const appName = String(stor?.appname || "Form");
    const elementById = appName.replaceAll(/\s+/g, "");
    const defaultMethod = setting?.methodById || `method${elementById}`;
    const preferredMethod = config.method || defaultMethod;
    const hasPreferredHandler = Boolean(NexaNativeScreen.resolveHandler(preferredMethod));
    const sendMethod = hasPreferredHandler ? preferredMethod : defaultMethod;

    const validasi = Object.fromEntries(
      Object.values(myFrom)
        .filter((f) => f.condition === true)
        .map((f) => [f.name, Number(f.validation)])
        .filter(([name, rule]) => Boolean(name) && Number.isFinite(rule) && rule > 0)
    );

    if (config.strictValidation && Object.keys(validasi).length === 0) {
      return '<div class="setting-info"><p>Validasi aktif, tetapi aturan validasi belum diset.</p></div>';
    }

    if (config.strictValidation && !NexaNativeScreen.resolveHandler(sendMethod)) {
      return `<div class="setting-info"><p>Method handler "${sendMethod}" tidak ditemukan pada object nx.</p></div>`;
    }
    const rawFormTitle = setting?.formTitle;
    const normalizedFormTitle = String(rawFormTitle ?? "").trim().toLowerCase();
    const isHiddenTitle = rawFormTitle === "false" || normalizedFormTitle === "false";
    const textTitle = isHiddenTitle ? " " : `${rawFormTitle || stor.appname}`;
    const formtitle = textTitle.charAt(0).toUpperCase() + textTitle.slice(1);
    const hideReset = setting?.resetClass === "false" || String(setting?.resetClass || "").trim().toLowerCase() === "false";
    const submitClassRaw = String(setting?.sendClass || "").trim();
    const submitClass = submitClassRaw
      ? (/(^|\s)btn(\s|$)/.test(submitClassRaw) ? submitClassRaw : `btn btn-${submitClassRaw}`)
      : "btn btn-primary";
    const resetMethodRaw = String(setting?.methodResetById || "").trim();
    const resetMethod = resetMethodRaw && resetMethodRaw.toLowerCase() !== "false" ? resetMethodRaw : "";
    const cardRaw = String(setting?.card ?? "").trim().toLowerCase();
    const showCard = cardRaw === "true";

    const keys = Object.keys(myFrom);
    const savedValue = Object.fromEntries(
      Object.values(myFrom)
        .filter((f) => f.value !== false && f.value !== undefined && f.value !== null && f.value !== "")
        .map((f) => [f.name, f.value])
    );
    const floatingConfig = {
      id: setting?.elementById || elementById,
      label: showCard ? "" : formtitle,
      variables: keys,
      form: myFrom,
      value: savedValue,
      settings: {
        floating: true,
        layout: "vertical",
      },
    };

    setTimeout(async () => {
      try {
        await NXUI.Form({
          elementById: setting?.elementById || elementById,
          label: showCard ? "" : formtitle,
          floating: floatingConfig,
          onclick: {
            title: setting?.sendTitle || "Send",
            cancel: hideReset ? false : (setting?.resetTitle || "Reset"),
            submitClass,
            submitIcon: setting?.submitIcon || "",
            send: sendMethod,
            reset: resetMethod,
            validation: validasi,
          },
          setDataBy: {
            form: myFrom,
            ...NexaNativeScreen.normalizeStandard(stor, sendMethod, config.strictValidation, config.recordId),
          },
          getFormBy: ["name"],
          getValidationBy: ["name"],
        });
      } catch (err) {}
    }, 50);

    const toastInfoRaw = String(setting?.ToastInfo ?? "").trim().toLowerCase();
    const showToastInfo = toastInfoRaw !== "false";
    const info = showToastInfo
      ? `
        <div style="width:100%;" class="pt-10px">
          <div class="Toast">
            <div class="Toast-border">
              <span class="Toast-icon" aria-hidden="true"><span class="material-symbols-outlined Toast-iconSymbol">info</span></span>
              <div class="Toast-content">${setting?.deskripsi || "Informasi: Data berhasil dimuat."}</div>
              <button class="Toast-dismissButton" type="button" aria-label="Tutup notifikasi" onclick="NexaNativeScreen.dismissToastInfo('${toastKey}', this)">✕</button>
            </div>
          </div>
        </div>
      `
      : "";

    if (showCard) {
      return `
        <div class="nx-card" style="width:100%;">
          <div class="nx-card-header bold">${formtitle || "Header Card"}</div>
          <div class="nx-card-body">
            <div id="${setting?.elementById || elementById}"></div>
            ${info}
          </div>
        </div>
      `;
    }

    return `
      <div style="width:100%;">
        <div id="${setting?.elementById || elementById}"></div>
        ${info}
      </div>
    `;
  }
}

globalThis.NexaNativeScreen = NexaNativeScreen;
