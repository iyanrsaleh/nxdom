export class NexaFormsScreen {
  static normalizeBucketKey(key) {
    return String(key || '').trim();
  }

  static async getStorage(key) {
    const bucketKey = NexaFormsScreen.normalizeBucketKey(key);

    if (!bucketKey) {
      return null;
    }

    const Sdk = new NXUI.Buckets(bucketKey);
    return Sdk.storage();
  }

  static getFormsSetting(meta) {
    const production = meta?.production && typeof meta.production === 'object' ? meta.production : {};
    const existingSetting = production?.setting && typeof production.setting === 'object' ? production.setting : {};
    const existingForms = existingSetting?.forms && typeof existingSetting.forms === 'object' ? existingSetting.forms : {};

    return {
      production,
      existingSetting,
      existingForms,
    };
  }

  static async mergeFormsSetting(key, nextFormsSetting = {}) {
    const bucketKey = NexaFormsScreen.normalizeBucketKey(key);

    if (!bucketKey) {
      return null;
    }

    const latestMeta = await NexaFormsScreen.getStorage(bucketKey);

    if (!latestMeta) {
      return null;
    }

    const { production, existingSetting, existingForms } = NexaFormsScreen.getFormsSetting(latestMeta);

    production.setting = {
      ...existingSetting,
      forms: {
        ...existingForms,
        ...nextFormsSetting,
      },
    };

    await NXUI.ref.merge(latestMeta, { production });
    return latestMeta;
  }

  static async dismissToastInfo(key, button) {
    const bucketKey = NexaFormsScreen.normalizeBucketKey(key);
    const toast = button?.closest?.('.Toast');

    if (!bucketKey) {
      if (toast) {
        toast.classList.add('Toast--animateOut');
        setTimeout(() => toast.remove(), 180);
      }
      return;
    }

    try {
      await NexaFormsScreen.mergeFormsSetting(bucketKey, { ToastInfo: 'false' });
    } catch (err) {
      console.error('Gagal menyimpan ToastInfo:', err);
    }

    if (toast) {
      toast.classList.add('Toast--animateOut');
      setTimeout(() => toast.remove(), 180);
    }
  }

  static resolveHandler(functionName) {
    const method = String(functionName || '').trim();
    if (!method) {
      return null;
    }

    if (typeof globalThis?.nx?.[method] === 'function') {
      return globalThis.nx[method];
    }

    if (typeof globalThis?.NXUI?.[method] === 'function') {
      return globalThis.NXUI[method];
    }

    if (typeof globalThis?.[method] === 'function') {
      return globalThis[method];
    }

    if (typeof globalThis?.nx?._global?.[method] === 'function') {
      return globalThis.nx._global[method];
    }

    return null;
  }

  /**
   * ID baris untuk preload form (update). Hanya `recordId` — bukan `id` (menghindari tabrakan dengan PK form / kunci lain).
   */
  static resolveRecordId(data) {
    const payload = data && typeof data === 'object' ? data : {};
    const nested = payload.data && typeof payload.data === 'object' ? payload.data : {};
    const rid = payload.recordId ?? nested.recordId;
    if (rid === null || rid === undefined || rid === '') {
      return null;
    }
    if (typeof rid === 'number' && !Number.isFinite(rid)) {
      return null;
    }
    return rid;
  }

  static resolveConfig(data) {
    const payload = data && typeof data === 'object' ? data : {};
    const nested = payload.data && typeof payload.data === 'object' ? payload.data : {};
    // Kunci nexaStore / bucket: pakai appID (bukan `id` supaya tidak bentrok dengan PK baris / field form).
    const key = String(
      payload.appID ??
      payload.appId ??
      nested.appID ??
      nested.appId ??
      payload.key ??
      nested.key ??
      ''
    ).trim();
    const method = String(payload.method || nested.method || '').trim();
    const strictValidation = payload.strictValidation === true;
    const recordId = NexaFormsScreen.resolveRecordId(payload);
    const classSelect = String(
      payload.classSelect ??
      nested.classSelect ??
      payload.selectStorage ??
      nested.selectStorage ??
      ""
    ).trim();

    return {
      key,
      method,
      strictValidation,
      recordId,
      classSelect,
    };
  }

  static normalizeSelectStorage(classSelect = "") {
    const s = String(classSelect || "").trim().toLowerCase();
    if (s === "indexeddb" || s === "indexed_db" || s === "indexed-db") {
      return "indexedDB";
    }
    if (s === "firebase") {
      return "firebase";
    }
    if (s === "sqlite3" || s === "sqlite") {
      return "sqlite3";
    }
    if (s === "websocket" || s === "ws") {
      return "websocket";
    }
    return "";
  }

  /** Salin dangkal field form agar hydration tidak mengubah objek prod di penyimpanan. Hanya field dengan `forms: true` dipakai di layar form. */
  static cloneFormFields(from = {}) {
    if (!from || typeof from !== 'object') return {};
    return Object.fromEntries(
      Object.entries(from)
        .filter(([, f]) => f && typeof f === 'object' && !Array.isArray(f) && f.forms === true)
        .map(([k, f]) => [k, { ...f }])
    );
  }

  /** Isi `.value` per field dari satu baris (nama kolom sama dengan key `demoForm`). */
  static hydrateDemoFormFromRow(demoForm, row) {
    if (!demoForm || typeof demoForm !== 'object' || !row || typeof row !== 'object') {
      return demoForm;
    }
    for (const fieldName of Object.keys(demoForm)) {
      const spec = demoForm[fieldName];
      if (!spec || typeof spec !== 'object' || Array.isArray(spec)) continue;
      if (!Object.prototype.hasOwnProperty.call(row, fieldName)) continue;
      demoForm[fieldName] = { ...spec, value: row[fieldName] };
    }
    return demoForm;
  }

  /** Ambil satu objek baris pertama dari jawaban `Office.executeOperation` / Payload.select. */
  static pickFirstExecuteRow(result) {
    const envelope = result?.data ?? result ?? {};
    const list = envelope.response ?? envelope.rows;
    return Array.isArray(list) && list.length ? list[0] : null;
  }

  static async render(data) {
    const config = NexaFormsScreen.resolveConfig(data);
    const key = config.key;

    if (!key) {
      return '<div class="setting-info"><p>nexaStore key belum diset pada tools item ini.</p></div>';
    }

    const stor = await NexaFormsScreen.getStorage(key);
    const demoForm = NexaFormsScreen.cloneFormFields(stor.production.from);

    // if (!demoForm || typeof demoForm !== 'object' || Object.keys(demoForm).length === 0) {
    //   return '<div class="setting-info"><p>Konfigurasi form tidak ditemukan atau kosong.</p></div>';
    // }

    const rowId = NexaFormsScreen.resolveRecordId(data);
    const appForPayload = String(data?.appID ?? data?.appId ?? key ?? '').trim();
    const selectStorage = NexaFormsScreen.normalizeSelectStorage(config.classSelect);
    const hasNumericOrStringId =
      rowId !== null &&
      rowId !== undefined &&
      rowId !== '' &&
      !(typeof rowId === 'number' && !Number.isFinite(rowId));

    if (hasNumericOrStringId && appForPayload && typeof NXUI?.Payload !== 'undefined') {
      try {
        const payloadForSelect = {
          appId: appForPayload,
          recordId: rowId,
        };
        if (selectStorage) {
          payloadForSelect.storage = selectStorage;
        }
        const rows = await new NXUI.Payload(payloadForSelect).select();
        const row = NexaFormsScreen.pickFirstExecuteRow(rows);
        if (row) {
          NexaFormsScreen.hydrateDemoFormFromRow(demoForm, row);
        }
      } catch (err) {
        console.warn('NexaFormsScreen: gagal memuat baris untuk mode update:', err);
      }
    }

    const setting = stor?.production?.setting?.forms || {};
    const rawFormTitle = setting?.formTitle;
    const normalizedFormTitle = String(rawFormTitle ?? '').trim().toLowerCase();
    const isHiddenTitle = rawFormTitle === "false" || normalizedFormTitle === 'false';
    const textTitle = isHiddenTitle ? ' ' : `${rawFormTitle || stor.appname}`;
    const formtitle = textTitle.charAt(0).toUpperCase() + textTitle.slice(1);
    const hideReset = setting?.resetClass === "false" || String(setting?.resetClass || '').trim().toLowerCase() === 'false';
    const submitClassRaw = String(setting?.sendClass || '').trim();
    let submitClass = 'btn btn-primary';

    if (submitClassRaw) {
      if (/(^|\s)btn(\s|$)/.test(submitClassRaw)) {
        submitClass = submitClassRaw;
      } else {
        submitClass = `btn btn-${submitClassRaw}`;
      }
    }

    const appName = String(stor?.appname || 'Form');
    const elementById = appName.replaceAll(/\s+/g, '');
    const methodById = `method${appName.replaceAll(/\s+/g, '')}`;
    const keys = Object.keys(demoForm);
    const cardRaw = String(setting?.card ?? '').trim().toLowerCase();
    const showCard = cardRaw === 'true';

    const savedValue = Object.fromEntries(
      Object.values(demoForm)
        .filter(f => f.value !== false && f.value !== undefined && f.value !== null && f.value !== '')
        .map(f => [f.name, f.value])
    );

    const floatingConfig = {
      id: setting?.elementById || elementById,
      label: showCard ? '' : formtitle,
      variables: keys,
      form: demoForm,
      value: savedValue,
      settings: {
        floating: true,
        layout: 'vertical',
      },
    };

    const validasi = Object.fromEntries(
      Object.values(demoForm)
        .filter(f => f.condition === true)
        .map(f => [f.name, Number(f.validation)])
        .filter(([name, rule]) => Boolean(name) && Number.isFinite(rule) && rule > 0)
    );

    if (config.strictValidation && Object.keys(validasi).length === 0) {
      return '<div class="setting-info"><p>Validasi aktif, tetapi aturan validasi belum diset.</p></div>';
    }

    const defaultSendMethod = setting?.methodById || methodById;
    const preferredSendMethod = config.method || defaultSendMethod;
    const hasPreferredHandler = Boolean(NexaFormsScreen.resolveHandler(preferredSendMethod));
    const sendMethod = hasPreferredHandler ? preferredSendMethod : defaultSendMethod;
    const resetMethodRaw = String(setting?.methodResetById || '').trim();
    const normalizedResetMethod = resetMethodRaw.toLowerCase();
    const resetMethod = resetMethodRaw && normalizedResetMethod !== 'false' ? resetMethodRaw : '';
    const resetMethodLabel = hideReset ? 'false' : (setting?.resetTitle || 'Reset');
    const resetMethodSample = (!hideReset && resetMethod) ? `
//Method ${resetMethodLabel}
nx.${resetMethod} = async (formElementId, data) => {
  console.log("hello", formElementId, data);
}
` : '';
    const toastInfoRaw = String(setting?.ToastInfo ?? '').trim().toLowerCase();
    const showToastInfo = toastInfoRaw !== 'false';

    if (preferredSendMethod !== sendMethod) {
      console.warn(`Method "${preferredSendMethod}" tidak valid, fallback ke "${sendMethod}".`);
    }

    if (config.strictValidation && !NexaFormsScreen.resolveHandler(sendMethod)) {
      return `<div class="setting-info"><p>Method handler "${sendMethod}" tidak ditemukan pada object nx.</p></div>`;
    }

    const ridSdk = config.recordId;
    const hasRidSdk =
      ridSdk !== null &&
      ridSdk !== undefined &&
      ridSdk !== '' &&
      !(typeof ridSdk === 'number' && !Number.isFinite(ridSdk));
    let recordIdSdkPart;
    if (hasRidSdk) {
      const lit =
        typeof ridSdk === 'number'
          ? String(ridSdk)
          : /^\s*\d+\s*$/.test(String(ridSdk))
            ? String(ridSdk).trim()
            : JSON.stringify(String(ridSdk));
      recordIdSdkPart = `  recordId: ${lit},`;
    } else {
      recordIdSdkPart = `  // recordId — opsional: mode update / preload baris`;
    }

    setTimeout(async () => {
      try {
        await NXUI.Form({
          elementById: setting?.elementById || elementById,
           label: showCard ? '' : formtitle,
          floating: floatingConfig,
          onclick: {
            title: setting?.sendTitle || 'Send',
            cancel: hideReset ? false : (setting?.resetTitle || 'Reset'),
            submitClass,
            submitIcon: setting?.submitIcon || '',
            send: sendMethod,
            reset: resetMethod,
            validation: validasi,
          },
          setDataBy: {
            form: demoForm,
            ...config,
          },
          getFormBy: ['name'],
          getValidationBy: ['name'],
        });
      } catch (err) {
        console.error('NXUI.Form gagal:', err);
      }
    }, 50);
    let info = showToastInfo ? `
        <div style="width:100%;" class="pt-10px">
           <div class="Toast">
          <div class="Toast-border">
            <span class="Toast-icon" aria-hidden="true"><span class="material-symbols-outlined Toast-iconSymbol">info</span></span>
            <div class="Toast-content">${setting?.deskripsi || 'Informasi: Data berhasil dimuat.'} </div>
            <button class="Toast-dismissButton" type="button" aria-label="Tutup notifikasi" onclick="NexaFormsScreen.dismissToastInfo('${key}', this)">✕</button>
          </div>
          </div> 
          </div> 
      ` : '';
  

     let codeSDK=''
    if (setting?.applications=='development') {
      codeSDK= `
    
      <div class="nx-readme-term nx-readme-term--light w-700px">
      <div class="nx-readme-term-bar"><span></span><span></span><span></span><label>SDK Usage</label></div>
      <div class="nx-readme-pre"><pre class="language-javascript"><code class="language-javascript">const Screen = new NX.Screen({
  appID: "${key}",
  method: "${sendMethod}",
  strictValidation: true,
${recordIdSdkPart}
});

const Screenforms = await Screen.run('forms');

//Method ${setting?.sendTitle || 'Send'} 
nx.${sendMethod} = async function (formElementId, data,cfg) {
  console.log(formElementId, data,cfg);
}
${resetMethodSample}
</code></pre>
</div>
        </div>
   `;
    }



    if (showCard) {
      return `
     <div class="nx-card" style="width:100%;">
      <div class="nx-card-header bold">
        ${formtitle || 'Header Card'}
      </div>
      <div class="nx-card-body">
        <div id="${setting?.elementById || elementById}"></div>
        ${info}
        ${codeSDK}
      </div>
    </div>
   `;
    }




    return `
     <div style="width:100%;">
        <div id="${setting?.elementById || elementById}"></div>
         ${info}
        ${codeSDK}
      </div>
   `;
  }
}

globalThis.NexaFormsScreen = NexaFormsScreen;

