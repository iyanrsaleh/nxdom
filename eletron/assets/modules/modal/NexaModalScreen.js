import { NexaFormsScreen } from "../forms/NexaFormsScreen.js";

export class NexaModalScreen {
  static normalizeBucketKey(key) {
    return String(key || '').trim();
  }

  static async getStorage(key) {
    const bucketKey = NexaModalScreen.normalizeBucketKey(key);

    if (!bucketKey) {
      return null;
    }

    const Sdk = new NXUI.Buckets(bucketKey);
    return Sdk.storage();
  }

  static getFormsSetting(meta) {
    const production = meta?.production && typeof meta.production === 'object' ? meta.production : {};
    const existingSetting = production?.setting && typeof production.setting === 'object' ? production.setting : {};
    const existingForms = existingSetting?.modal && typeof existingSetting.modal === 'object' ? existingSetting.modal : {};

    return {
      production,
      existingSetting,
      existingForms,
    };
  }

  static async mergeFormsSetting(key, nextFormsSetting = {}) {
    const bucketKey = NexaModalScreen.normalizeBucketKey(key);

    if (!bucketKey) {
      return null;
    }

    const latestMeta = await NexaModalScreen.getStorage(bucketKey);

    if (!latestMeta) {
      return null;
    }

    const { production, existingSetting, existingForms } = NexaModalScreen.getFormsSetting(latestMeta);

    production.setting = {
      ...existingSetting,
      modal: {
        ...existingForms,
        ...nextFormsSetting,
      },
    };

    await NXUI.ref.merge(latestMeta, { production });
    return latestMeta;
  }

  static async dismissToastInfo(key, button) {
    const bucketKey = NexaModalScreen.normalizeBucketKey(key);
    const toast = button?.closest?.('.Toast');

    if (!bucketKey) {
      if (toast) {
        toast.classList.add('Toast--animateOut');
        setTimeout(() => toast.remove(), 180);
      }
      return;
    }

    try {
      await NexaModalScreen.mergeFormsSetting(bucketKey, { ToastInfo: 'false' });
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

  static resolveConfig(data) {
    const payload = data && typeof data === 'object' ? data : {};
    const nested = payload.data && typeof payload.data === 'object' ? payload.data : {};
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

    return {
      key,
      method,
      strictValidation,
      recordId,
    };
  }

  static async render(data) {
    const config = NexaModalScreen.resolveConfig(data);
    const key = config.key;

    if (!key) {
      return '<div class="setting-info"><p>nexaStore key belum diset pada tools item ini.</p></div>';
    }

    const stor = await NexaModalScreen.getStorage(key);
    let demoForm = NexaFormsScreen.cloneFormFields(stor.production.from ?? {});

    if (!demoForm || typeof demoForm !== 'object' || Object.keys(demoForm).length === 0) {
      return '<div class="setting-info"><p>Konfigurasi form tidak ditemukan atau kosong.</p></div>';
    }

    const rowId = NexaFormsScreen.resolveRecordId(data);
    const appForPayload = String(data?.appID ?? data?.appId ?? key ?? '').trim();
    const hasNumericOrStringId =
      rowId !== null &&
      rowId !== undefined &&
      rowId !== '' &&
      !(typeof rowId === 'number' && !Number.isFinite(rowId));

    if (hasNumericOrStringId && appForPayload && typeof NXUI?.Payload !== 'undefined') {
      try {
        const rows = await new NXUI.Payload({
          appId: appForPayload,
          recordId: rowId,
        }).select();
        const row = NexaFormsScreen.pickFirstExecuteRow(rows);
        if (row) {
          NexaFormsScreen.hydrateDemoFormFromRow(demoForm, row);
        }
      } catch (err) {
        console.warn('NexaModalScreen: gagal memuat baris untuk mode update:', err);
      }
    }

    const setting = stor?.production?.setting?.modal || {};


    const rawFormTitle = setting?.formTitle;
    const normalizedFormTitle = String(rawFormTitle ?? '').trim().toLowerCase();
    const isHiddenTitle = rawFormTitle === "false" || normalizedFormTitle === 'false';
    const textTitle = isHiddenTitle ? ' ' : `${rawFormTitle || stor.appname}`;
    const formtitle = textTitle.charAt(0).toUpperCase() + textTitle.slice(1);
    const methodResetValue = String(setting?.methodResetById || '').trim().toLowerCase();
    const hideReset = setting?.resetClass === "false"
      || String(setting?.resetClass || '').trim().toLowerCase() === 'false';
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
    const savedValue = Object.fromEntries(
      Object.values(demoForm)
        .filter(f => f.value !== false && f.value !== undefined && f.value !== null && f.value !== '')
        .map(f => [f.name, f.value])
    );

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
    const hasPreferredHandler = Boolean(NexaModalScreen.resolveHandler(preferredSendMethod));
    const sendMethod = hasPreferredHandler ? preferredSendMethod : defaultSendMethod;
    const resetMethodRaw = String(setting?.methodResetById || '').trim();
    const normalizedResetMethod = resetMethodRaw.toLowerCase();
    const resetMethod = resetMethodRaw && normalizedResetMethod !== 'false' ? resetMethodRaw : '';
    const resetMethodLabel = hideReset ? 'false' : (setting?.resetTitle || 'Reset');
    const resetMethodSample = (!hideReset && resetMethod) ? `
//Method ${resetMethodLabel}
nx.${resetMethod} = async (id, data) => {
  console.log("hello", data);
}
` : '';
    const toastInfoRaw = String(setting?.ToastInfo ?? '').trim().toLowerCase();
    const showToastInfo = toastInfoRaw !== 'false';

    if (preferredSendMethod !== sendMethod) {
      console.warn(`Method "${preferredSendMethod}" tidak valid, fallback ke "${sendMethod}".`);
    }

    if (config.strictValidation && !NexaModalScreen.resolveHandler(sendMethod)) {
      return `<div class="setting-info"><p>Method handler "${sendMethod}" tidak ditemukan pada object nx.</p></div>`;
    }

    const openModal = String(setting?.openModal || 'openModal').trim();
    const modalID = `setModal_${key}`;
    const resolvedElementById = setting?.elementById || elementById;
    const styleClass = String(setting?.styleClass || 'w-500px').trim();
    const minimize = String(setting?.minimize ?? 'true').trim().toLowerCase() !== 'false';
    const showMinimize = String(setting?.showMinimize ?? 'false').trim().toLowerCase() === 'true';
    const minimizedBg = String(setting?.minimizedBg || '').trim() || undefined;
    const paddingTop = String(setting?.paddingTop || '').trim() || undefined;
    const bodyPadding = String(setting?.bodyPadding || '').trim() || undefined;

    // Build footer: structured buttons dari footerButtons setting
    const footerButtonsRaw = String(setting?.footerButtons || '').trim();
    const footerButtons = footerButtonsRaw
      ? footerButtonsRaw.split('|').map(entry => {
          const parts = entry.trim().split(':').map(s => s.trim());
          const label = parts[0] || '';
          const cls   = parts[1] || 'secondary';
          const method = parts[2] || label; // default method = label jika tidak diisi
          if (!label) return null;
          return { label, cls, method };
        }).filter(Boolean)
      : undefined;
    const footer = undefined; // raw HTML tidak digunakan lagi
    const floatingModalConfig = {
      id: resolvedElementById,
      label: formtitle,
      variables: keys,
      value: savedValue,
      form: demoForm,
      settings: {
        floating: true,
        layout: 'vertical',
      },
    };

    globalThis.nx = globalThis.nx || {};
    globalThis.nx[openModal] = async function () {
      try {
        await NXUI.Modal({
          elementById: modalID,
          styleClass,
          minimize,
          showMinimize,
          ...(minimizedBg && { minimizedBg }),
          ...(paddingTop && { paddingTop }),
          ...(bodyPadding && { bodyPadding }),
          ...(footer && { footer }),
          ...(footerButtons && { footerButtons }),
          label: formtitle,
          floating: floatingModalConfig,
          toastInfo: {
            show: showToastInfo,
            text: setting?.deskripsi || 'Informasi: Data berhasil dimuat.',
            key,
          },
          setDataBy: {
            form: demoForm,
            modalKey: key,
            keepValuesOnSubmit: true,
            ...config,
          },
          getFormBy: ['name'],
          getValidationBy: ['name'],
          onclick: {
            title: setting?.sendTitle || 'Send',
            cancel: hideReset ? false : (setting?.resetTitle || 'Reset'),
            submitClass,
            submitIcon: setting?.submitIcon || '',
            submitIconType: String(setting?.submitIconType || 'octicon').trim(),
            send: sendMethod,
            reset: resetMethod,
            validation: validasi,
          },
        });
        await NXUI.Modal.open(modalID);
      } catch (err) {
        console.error('NexaModalScreen modal error:', err);
      }
    };

    return '';
  }
}

globalThis.NexaModalScreen = NexaModalScreen;

