let tabelViewTableInstance = null;
function detectDateTimeString(value = "") {
  return typeof value === "string" && /^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/.test(value);
}

function detectDateString(value = "") {
  return typeof value === "string" && /^\d{4}-\d{2}-\d{2}$/.test(value);
}

function detectTimeString(value = "") {
  return typeof value === "string" && /^\d{2}:\d{2}(:\d{2})?$/.test(value);
}

function titleFromKey(key = "") {
  const text = String(key).replaceAll("_", " ");
  return text.split(" ").map((part) => {
    if (!part) {
      return part;
    }

    return part.charAt(0).toUpperCase() + part.slice(1);
  }).join(" ");
}

function resolveStoreId(actionId = "") {
  const raw = String(actionId || "").trim();
  if (!raw) {
    return "";
  }

  const [storeId] = raw.split(":");
  return (storeId || raw).trim();
}

function escapeHtml(text = "") {
  return String(text)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

function escapeJsSingleQuoted(str) {
  return String(str ?? "").replace(/\\/g, "\\\\").replace(/'/g, "\\'");
}

function inferStringEditConfig(key, sampleValue) {
  const loweredKey = String(key).toLowerCase();

  if (loweredKey.includes("email")) {
    return { type: "email" };
  }

  if (loweredKey.includes("url") || String(sampleValue).startsWith("http://") || String(sampleValue).startsWith("https://")) {
    return { type: "url" };
  }

  if (loweredKey.includes("tel") || loweredKey.includes("phone") || loweredKey.includes("nik") || /^\+?[0-9\s-]{8,}$/.test(sampleValue)) {
    return { type: "tel" };
  }

  if (/^#[0-9a-fA-F]{6}$/.test(sampleValue)) {
    return { type: "color" };
  }

  if (sampleValue.length > 80) {
    return { type: "textarea", rows: 3 };
  }

  return { type: "text" };
}

function inferEditConfig(key, sampleValue) {
  if (typeof sampleValue === "boolean") {
    return { type: "checkbox" };
  }

  if (typeof sampleValue === "number") {
    return { type: "number", step: 1 };
  }

  if (detectDateTimeString(sampleValue)) {
    return { type: "datetime-local" };
  }

  if (detectDateString(sampleValue)) {
    return { type: "date" };
  }

  if (detectTimeString(sampleValue)) {
    return { type: "time" };
  }

  if (typeof sampleValue === "string") {
        return inferStringEditConfig(key, sampleValue);
  }

  return { type: "text" };
}

function buildColumns(rows = []) {
  const firstRow = rows[0] || {};
  return Object.keys(firstRow).map((key) => ({
    key,
    title: titleFromKey(key),
  }));
}

function buildEditingConfig(rows = []) {
  const firstRow = rows[0] || {};
  const editing = {};

  Object.entries(firstRow).forEach(([key, value]) => {
    editing[key] = inferEditConfig(key, value);
  });

  return editing;
}

/**
 * Hanya field dengan `tabel: true` dipakai NexaTables (kolom, inline, hidden).
 */
function filterFormulirForTabel(formulirRaw = {}) {
  if (!formulirRaw || typeof formulirRaw !== "object" || Array.isArray(formulirRaw)) {
    return {};
  }
  return Object.fromEntries(
    Object.entries(formulirRaw).filter(
      ([, f]) => f && typeof f === "object" && !Array.isArray(f) && f.tabel === true,
    ),
  );
}

/**
 * Bangun editing config NexaTables langsung dari objek `formulir`.
 * Type diambil dari `formulir[key].type`, bukan diinfer dari nilai baris.
 */
function buildEditingConfigFromFormulir(formulir = {}) {
  // Peta type formulir → type NexaTables editing
  const typeMap = {
    text: "text",
    email: "email",
    password: "password",
    number: "number",
    tel: "tel",
    url: "url",
    date: "date",
    "datetime-local": "datetime-local",
    time: "time",
    color: "color",
    range: "range",
    textarea: "textarea",
    search: "search",
    checkbox: "checkbox",
    switch: "checkbox",
    radio: "select",
    select: "select",
    // Special formulir types → mapped ke NexaTables type
    inline: "text",
    flag: "checkbox",
    filtering: "text",
    approval: "text",
    keyup: "text",
    slug: "text",
    tags: "text",
    maps: "text",
    instansi: "text",
    avatar: "text",
    modal: "text",
    currency: "number",
    file: "text",
  };

  const editing = {};
  Object.entries(formulir).forEach(([key, field]) => {
    const nexaType = typeMap[field.type] || "text";
    const cfg = { type: nexaType };
    if (nexaType === "number") {
      cfg.step = 1;
    }
    if (nexaType === "select") {
      // Ambil opsi dari field.select.data (struktur formulir standar)
      const selectData = field.select?.data;
      if (Array.isArray(selectData) && selectData.length) {
        cfg.options = selectData.map((item) => ({
          value: item.value ?? item.key,
          label: item.label ?? item.value ?? item.key,
        }));
      } else if (Array.isArray(field.options) && field.options.length) {
        cfg.options = field.options;
      }
    }
    editing[key] = cfg;
  });
  return editing;
}

async function getQueryResult(actionId = "", page = 0, pageSize = null, skipCount = false, search = "") {
  const storeId = resolveStoreId(actionId);
  const buildQuery = await NX.BuildQuery(storeId);


  // Use provided pageSize; fallback to production config's limit.
  const effectivePageSize = (pageSize != null && pageSize > 0) ? pageSize : (buildQuery.limit || 10);
  buildQuery.limit = effectivePageSize;
  buildQuery.offset = page * effectivePageSize;

  // Skip COUNT(*) on the backend when we already know the total (page > 0, no new search).
  if (skipCount) buildQuery.skipCount = true;

  // Server-side search: send search term so backend adds WHERE LIKE.
  if (search && search.trim() !== "") {
    buildQuery.search = search.trim();
    // Search resets total — always run COUNT.
    delete buildQuery.skipCount;
  } else {
    delete buildQuery.search;
  }

  const result = await NXUI.Storage()
    .models("Office")
    .executeOperation(buildQuery);
  return result;
}

/** Selaras `Model/index.js` + `Packages/accses` (`work`: insert/update/delete/approval …). */
function tabelWorkPermissionActive(work, key) {
  if (!work || typeof work !== "object") return false;
  const v = work[key];
  return v != null && v !== "" && Number(v) !== 0;
}

async function loadBucketsStoreWorkAccses() {
  try {
    const row = await NXUI.ref.bucketsStore("accses").get();
    const w = row?.work;
    return w && typeof w === "object" ? w : null;
  } catch (_) {
    return null;
  }
}

function notifyTabelInlineAccessDenied(subtitle) {
  const msg = String(subtitle || "Tidak ada hak ubah (update).").trim();
  try {
    const Ctor = NXUI?.Notifikasi;
    if (typeof Ctor === "function") {
      new Ctor({ autoHideDelay: 4500 }).show({
        type: "warning",
        title: "Akses ditolak",
        subtitle: msg,
        actions: false,
        autoHide: true,
      });
      return;
    }
  } catch (_) {
    /* abaikan */
  }
  console.warn("[NexaTabelScreen]", msg);
}

function resolveMainAlias(buildQuery = {}) {
  if (Array.isArray(buildQuery?.tabelName) && buildQuery.tabelName.length > 0) {
    return String(buildQuery.tabelName[0] || "").trim() || null;
  }
  if (typeof buildQuery?.tabelName === "string" && buildQuery.tabelName.trim()) {
    return buildQuery.tabelName.trim();
  }
  if (buildQuery?.operasi && typeof buildQuery.operasi === "object") {
    const keys = Object.keys(buildQuery.operasi);
    if (keys.length > 0) return String(keys[0] || "").trim() || null;
  }
  return null;
}

function resolveMainTableForMutation(buildQuery = {}, mainAlias = "") {
  if (!mainAlias) return null;
  const op = buildQuery?.operasi?.[mainAlias];
  const t = op?.aliasIndex || op?.table || mainAlias;
  const s = String(t || "").trim();
  return s || null;
}

async function persistInlineEditFallback(buildQuery, rowArg, key, value) {
  const row = rowArg && typeof rowArg === "object" ? rowArg : null;
  if (!row) return false;
  const mainAlias = resolveMainAlias(buildQuery);
  if (!mainAlias) return false;
  const tableName = resolveMainTableForMutation(buildQuery, mainAlias);
  if (!tableName) return false;
  const pk = buildQuery?.operasi?.[mainAlias]?.keyIndex || "id";
  const idVal = row?.[pk] ?? row?.id ?? null;
  if (idVal == null || idVal === "") return false;

  await NXUI.Storage()
    .model(tableName)
    .where(pk, "=", idVal)
    .update({ [key]: value });
  return true;
}

function safeSqlScalarLiteral(val) {
  if (val === null || val === undefined) return "NULL";
  if (typeof val === "boolean") return val ? "1" : "0";
  if (typeof val === "number") return Number.isFinite(val) ? String(val) : "NULL";
  const s = String(val);
  return `'${s.replace(/\\/g, "\\\\").replace(/'/g, "''")}'`;
}

function mergeWhereFragment(buildQuery, fragment) {
  const frag = String(fragment || "").trim();
  if (!frag) return;
  const w = buildQuery?.where;
  if (w !== false && w != null && String(w).trim() !== "") {
    const inner = String(w).replace(/^\s*WHERE\s+/i, "").trim();
    buildQuery.where = `${frag} AND (${inner})`;
    return;
  }
  buildQuery.where = frag;
}

/** Kolom scope wilayah dari bucketsStore `access` ({@see Packages/accses.js}). */
const QUERY_SCOPE_WILAYAH_KEYS = ["provinsi", "kabupaten", "kecamatan", "desa"];

/** Nilai wilayah di scope dianggap perlu clause SQL (nama teks atau kode non‑nol). */
function queryScopeRegionalValueActive(value) {
  if (value === null || value === undefined || value === "") return false;
  if (typeof value === "boolean") return value === true;
  if (typeof value === "string") {
    const t = value.trim();
    if (t === "") return false;
    const n = Number(t);
    if (Number.isFinite(n) && n === 0) return false;
    return true;
  }
  const n = Number(value);
  if (Number.isFinite(n)) return n !== 0;
  return true;
}

function queryScopeHasRegionalFilter(scope) {
  if (!scope || typeof scope !== "object") return false;
  return QUERY_SCOPE_WILAYAH_KEYS.some((k) => queryScopeRegionalValueActive(scope[k]));
}

/**
 * Gabung `payload` Screen / partial → objek scope datar untuk `getQueryResultWithScope`.
 * Membaca `access` (slim accses) plus fallback `userid` top-level.
 */
function normalizeDatatableQueryScope(payload = {}) {
  const root = payload && typeof payload === "object" ? payload : {};
  const inner = root.data && typeof root.data === "object" ? root.data : {};
  const accessRaw = root.access ?? inner.access ?? null;
  const access = accessRaw && typeof accessRaw === "object" ? accessRaw : {};

  const userid =
    root.userid ??
    root.userId ??
    inner.userid ??
    inner.userId ??
    access.userid ??
    access.userId ??
    null;

  const recordId = root.recordId ?? inner.recordId ?? null;
  const classSelect =
    root.classSelect ??
    root.selectStorage ??
    inner.classSelect ??
    inner.selectStorage ??
    null;

  const pickRegional = (k) =>
    access[k] ??
    root[k] ??
    inner[k];

  return {
    recordId,
    classSelect,
    userid,
    provinsi: pickRegional("provinsi"),
    kabupaten: pickRegional("kabupaten"),
    kecamatan: pickRegional("kecamatan"),
    desa: pickRegional("desa"),
  };
}

function normalizeSelectStorageMode(raw) {
  const s = String(raw ?? "").trim().toLowerCase();
  if (s === "indexeddb" || s === "indexed_db" || s === "indexed-db") return "indexeddb";
  if (s === "firebase") return "firebase";
  if (s === "sqlite3" || s === "sqlite") return "sqlite3";
  if (s === "websocket" || s === "ws") return "websocket";
  return "database";
}

function sortRowsLatestDesc(rows = []) {
  const list = Array.isArray(rows) ? [...rows] : [];
  return list.sort((a, b) => {
    const aId = Number(a?.id);
    const bId = Number(b?.id);
    if (Number.isFinite(aId) && Number.isFinite(bId)) return bId - aId;

    const aTs = Date.parse(a?.updatedAt ?? a?.createdAt ?? 0);
    const bTs = Date.parse(b?.updatedAt ?? b?.createdAt ?? 0);
    if (Number.isFinite(aTs) && Number.isFinite(bTs)) return bTs - aTs;
    return 0;
  });
}

function applyClientScopeToRows(rows = [], scope = {}) {
  const userid = scope?.userid;
  const filters = {
    provinsi: scope?.provinsi,
    kabupaten: scope?.kabupaten,
    kecamatan: scope?.kecamatan,
    desa: scope?.desa,
  };
  return rows.filter((row) => {
    if (!row || typeof row !== "object") return false;
    if (userid != null && userid !== "") {
      const uid = row.userid ?? row.userId;
      if (uid == null || String(uid) !== String(userid)) return false;
    }
    for (const [k, v] of Object.entries(filters)) {
      if (!queryScopeRegionalValueActive(v)) continue;
      if (String(row?.[k] ?? "") !== String(v)) return false;
    }
    return true;
  });
}

function applyUserIdFilterToBuildQuery(buildQuery, userid) {
  if (userid == null || userid === "") return;
  const mainAlias = resolveMainAlias(buildQuery);
  if (!mainAlias) return;
  const fragment = `${mainAlias}.userid = ${safeSqlScalarLiteral(userid)}`;
  mergeWhereFragment(buildQuery, fragment);
}

/** Batas wilayah: `tb.kecamatan = '…'` dll. dikirim ke server lewat field `where` pada buildQuery ({@see JoinTabel}). */
function applyRegionalScopeToBuildQuery(buildQuery, scope) {
  if (!scope || typeof scope !== "object") return;
  const mainAlias = resolveMainAlias(buildQuery);
  if (!mainAlias) return;
  for (const col of QUERY_SCOPE_WILAYAH_KEYS) {
    const raw = scope[col];
    if (!queryScopeRegionalValueActive(raw)) continue;
    const fragment = `${mainAlias}.${col} = ${safeSqlScalarLiteral(raw)}`;
    mergeWhereFragment(buildQuery, fragment);
  }
}

function applyRecordIdFilterToBuildQuery(buildQuery, recordId) {
  if (recordId == null || recordId === "") return;
  const mainAlias = resolveMainAlias(buildQuery);
  if (!mainAlias) return;
  const n = Number(recordId);
  if (!Number.isFinite(n)) return;
  const pk = buildQuery?.operasi?.[mainAlias]?.keyIndex || "id";
  const fragment = `${mainAlias}.${pk} = ${Math.trunc(n)}`;
  mergeWhereFragment(buildQuery, fragment);
}

async function getQueryResultWithScope(
  actionId = "",
  page = 0,
  pageSize = null,
  skipCount = false,
  search = "",
  scope = {},
) {
  const storeId = resolveStoreId(actionId);
  const selectMode = normalizeSelectStorageMode(scope?.classSelect);
  const effectivePageSize = (pageSize != null && pageSize > 0) ? pageSize : 10;

  if (selectMode !== "database") {
    const payloadSelect = {
      appId: storeId,
      page,
      pageSize: effectivePageSize,
      skipCount,
      search,
      recordId: scope?.recordId ?? null,
      storage: selectMode,
    };
    const result = await new NXUI.Payload(payloadSelect).select();
    const rawRows = Array.isArray(result?.response)
      ? result.response
      : Array.isArray(result?.data?.response)
        ? result.data.response
        : [];
    const sortedRows = sortRowsLatestDesc(rawRows);
    return {
      success: true,
      mode: selectMode,
      response: sortedRows,
      count: sortedRows.length,
      totalCount: sortedRows.length,
    };
  }

  const buildQuery = await NX.BuildQuery(storeId);
  const pageSizeFromQuery = buildQuery.limit || 10;
  const finalPageSize = (pageSize != null && pageSize > 0) ? pageSize : pageSizeFromQuery;
  buildQuery.limit = finalPageSize;
  buildQuery.offset = page * finalPageSize;
  if (skipCount) buildQuery.skipCount = true;

  applyRecordIdFilterToBuildQuery(buildQuery, scope?.recordId);
  applyUserIdFilterToBuildQuery(buildQuery, scope?.userid);
  applyRegionalScopeToBuildQuery(buildQuery, scope);

  if (search && search.trim() !== "") {
    buildQuery.search = search.trim();
    delete buildQuery.skipCount;
  } else {
    delete buildQuery.search;
  }

  return NXUI.Storage().models("Office").executeOperation(buildQuery);
}

function extractRows(result) {
  if (Array.isArray(result?.data?.response)) return result.data.response;
  if (Array.isArray(result?.response)) return result.response;
  return [];
}

function extractTotal(result) {
  if (Number.isFinite(result?.data?.totalCount)) return result.data.totalCount;
  if (Number.isFinite(result?.totalCount)) return result.totalCount;
  return 0;
}

/** Boolean dari nilai setting (string / boolean). */
function tabelCfgBool(val, defaultVal = true) {
  if (val === undefined || val === null || val === "") return defaultVal;
  const s = String(val).toLowerCase().trim();
  if (s === "false" || s === "0" || s === "no") return false;
  if (s === "true" || s === "1" || s === "yes") return true;
  return defaultVal;
}

function tabelCfgInt(val, fallback) {
  const n = parseInt(String(val), 10);
  return Number.isFinite(n) && n > 0 ? n : fallback;
}

function parseExportTypes(raw) {
  const allowed = new Set(["csv", "json", "xlsx", "pdf"]);
  if (raw == null || raw === "") return ["csv", "json", "xlsx", "pdf"];
  const parts = String(raw)
    .split(/[,|]/)
    .map((s) => s.trim().toLowerCase())
    .filter((s) => allowed.has(s));
  return parts.length ? parts : ["csv", "json", "xlsx", "pdf"];
}

/** Kunci aksi baris yang didukung NexaTables (`tables.js` demo). */
const NEXA_TABLE_ROW_ACTION_KEYS = [
  "view",
  "add",
  "edit",
  "delete",
  "export",
  "import",
  "print",
  "share",
];

function parseRowActionsFromObject(raw) {
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) return null;
  const out = {};
  let any = false;
  for (const key of NEXA_TABLE_ROW_ACTION_KEYS) {
    if (!Object.prototype.hasOwnProperty.call(raw, key)) continue;
    if (tabelCfgBool(raw[key], false)) {
      out[key] = true;
      any = true;
    }
  }
  return any ? out : null;
}

/**
 * Opsi `actions` untuk NexaTables: dari `setting.actions` (objek) atau
 * toggle datar `rowAct_*` di form `setting.js` (satu kunci per aksi).
 */
function parseRowActionsFromSetting(setting = {}) {
  const fromNested = parseRowActionsFromObject(setting.actions);
  if (fromNested) return fromNested;

  const flatPairs = [
    ["rowAct_view", "view"],
    ["rowAct_add", "add"],
    ["rowAct_edit", "edit"],
    ["rowAct_delete", "delete"],
    ["rowAct_export", "export"],
    ["rowAct_import", "import"],
    ["rowAct_print", "print"],
    ["rowAct_share", "share"],
  ];
  const out = {};
  let any = false;
  for (const [settingKey, nexaKey] of flatPairs) {
    if (tabelCfgBool(setting[settingKey], false)) {
      out[nexaKey] = true;
      any = true;
    }
  }
  return any ? out : null;
}

/** Sama urutan pencarian dengan `NexaWizardScreen.resolveWizardSubmitHandler` — `nx.methodPetani`, dll. */
function resolveMethodSubmitHandler(functionName) {
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

/** ID aman untuk atribut `id` mount tabel (seragam dengan host orchestrator). */
function sanitizeTabelMountId(raw) {
  const s = String(raw || "nexaTabel").trim();
  return s.replace(/[^a-zA-Z0-9_-]/g, "_") || "nexaTabel";
}

/** Tutup toast & set `production.setting.tabel.ToastInfo` ke `"false"` (selaras wizard/forms). */
export const NexaTabelScreen = {
  /**
   * Entry `Screen.run('datatable')` / `await new NX.Screen(data).datatable()` —
   * pola payload `NexaFormsScreen`: `appID` (bucket), `recordId` (preload update). Tabel/wizard: `appID`, `data.id`, `key`.
   * @param {object} data
   * @param {string} [data.containerSelector] — opsional; default `#<elementById>` dari bucket `production.setting.tabel`.
   */
  async render(data) {
    const payload = data && typeof data === "object" ? data : {};
    const key = String(
      payload?.data?.id ??
        payload?.appID ??
        payload?.id ??
        payload?.key ??
        "",
    ).trim();

    if (!key) {
      return '<div class="setting-info"><p>nexaStore key belum diset (appID, data.id, atau key).</p></div>';
    }

    let row = null;
    try {
      row = await NXUI.ref.nexaStore(key).get();
    } catch (_) {
      row = null;
    }

    const setting = row?.production?.setting?.tabel || {};
    const appName = String(row?.appname || "Form");
    const mountId = sanitizeTabelMountId(setting?.elementById || appName.replaceAll(/\s+/g, ""));
    const customSel = String(payload.containerSelector || payload.selector || "").trim();
    const containerSelector = customSel || `#${mountId}`;

    const queryScope = normalizeDatatableQueryScope(payload);

    setTimeout(async () => {
      try {
        await renderQueryResultTable(containerSelector, key, queryScope);
      } catch (e) {
        console.error("[NexaTabelScreen.render] renderQueryResultTable:", e);
      }
    }, 50);

    if (customSel) {
      return '<div class="nexa-datatable-screen-host" style="width:100%"></div>';
    }

    return `<div style="width:100%;"><div id="${escapeHtml(mountId)}"></div></div>`;
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
      const existingTabel =
        existingSetting?.tabel && typeof existingSetting.tabel === "object"
          ? existingSetting.tabel
          : {};
      production.setting = {
        ...existingSetting,
        tabel: {
          ...existingTabel,
          ToastInfo: "false",
        },
      };
      await NXUI.ref.merge(latestMeta, { production });
    } catch (err) {
      console.error("Gagal menyimpan ToastInfo (tabel):", err);
    }

    if (toast) {
      toast.classList.add("Toast--animateOut");
      setTimeout(() => toast.remove(), 180);
    }
  },
};

globalThis.NexaTabelScreen = NexaTabelScreen;

export async function renderQueryResultTable(containerSelector, actionId = "", queryScope = {}) {




  const storeId = resolveStoreId(actionId);
  let row = null;
  try {
    row = await NXUI.ref.nexaStore(storeId).get();
  } catch (_) {
    row = null;
  }
  const setting = row?.production?.setting?.tabel || {};

  const flatMountId = `nx_tabel_flat_${String(storeId).replace(/[^a-zA-Z0-9_-]/g, "_")}`;
  const tableHostId = `nx_tabel_card_body_${String(storeId).replace(/[^a-zA-Z0-9_-]/g, "_")}`;

  const toastInfoRaw = String(setting?.ToastInfo ?? "").trim().toLowerCase();
  const showToastInfo = toastInfoRaw !== "false";
  const toastKeyJs = escapeJsSingleQuoted(storeId);
  const toastBody = escapeHtml(
    String(
      setting?.deskripsi ||
        row?.deskripsi ||
        "Data tabel siap. Gunakan filter, sort, dan export sesuai kebutuhan.",
    ),
  );
  const toastHtml = showToastInfo
    ? `<div style="width:100%;" class="mt-10px nx-tabel-toast-banner">
        <div class="Toast">
            <span class="Toast-icon" aria-hidden="true"><span class="material-symbols-outlined Toast-iconSymbol">info</span></span>
            <div class="Toast-content">${toastBody}</div>
            <button class="Toast-dismissButton" type="button" aria-label="Tutup notifikasi" onclick="NexaTabelScreen.dismissToastInfo('${toastKeyJs}', this)">✕</button>
        </div>
      </div>`
    : "";

  let result = null;
  let queryError = null;
  try {
    result = await getQueryResultWithScope(storeId, 0, null, false, "", queryScope);
  } catch (e) {
    queryError = e;
    console.warn("[NexaTabelScreen] query belum siap untuk package ini:", e);
  }
  const rows = queryError ? [] : extractRows(result);
  const serverTotal = queryError ? 0 : extractTotal(result);

  const container = document.querySelector(containerSelector);
  if (!container) {
    return null;
  }

  const containerIsFlatMount = Boolean(container.id && container.id === flatMountId);
  const containerIsCardTableHost = Boolean(container.id && container.id === tableHostId);

  if (tabelViewTableInstance) {
    const existingTable = tabelViewTableInstance;
    if (typeof existingTable.destroy === "function") {
      existingTable.destroy();
    }
    tabelViewTableInstance = null;
  }

  if (rows.length === 0) {
    const scopedByUser =
      (queryScope?.userid != null && String(queryScope.userid).trim() !== "") ||
      queryScopeHasRegionalFilter(queryScope);
    const msg = queryError
      ? "Package baru belum dikonfigurasi. Silakan klik konfigurasi pada canonical untuk set query tabel."
      : scopedByUser
        ? "Tidak ada data untuk akun Anda pada tabel ini."
        : "Tidak ada data hasil query. Silakan klik konfigurasi pada canonical.";
    container.innerHTML = `<div class="nx-empty-state" style="padding:12px;">${escapeHtml(msg)}</div>`;
    return null;
  }

  const useCard =
    setting?.card === true ||
    String(setting?.card ?? "").trim().toLowerCase() === "true";

  let tableContainerSelector = containerSelector;

  if (useCard) {
    if (containerIsCardTableHost) {
      /* Partial refresh: hanya host isi tabel di dalam kartu — jangan ulang kartu/toast */
      container.innerHTML = "";
    } else {
      const cardTitle = String(setting.formTitle || setting.caption || row?.appname || "Tabel").trim();
      container.innerHTML = `
      <div class="nx-card nx-tabel-card-wrap">
        <div class="nx-card-header">${escapeHtml(cardTitle)}</div>
        <div class="nx-card-body">
          <div id="${tableHostId}" class="nx-tabel-card-inner"></div>
          ${toastHtml}
        </div>
      </div>`;
    }
    tableContainerSelector = `#${tableHostId}`;
  } else if (showToastInfo) {
    if (containerIsFlatMount) {
      /* Partial refresh (#nx_tabel_flat_*): toast tetap di sibling parent — jangan sisipkan lagi */
      container.innerHTML = "";
    } else {
      container.innerHTML = `
       
        <div style="padding-bottom: 10px;"> ${toastHtml}</div>
        <div id="${flatMountId}"></div>
      `;
    }
    tableContainerSelector = `#${flatMountId}`;
  } else {
    container.innerHTML = "";
  }

  let initialPageSize = tabelCfgInt(setting.pageSize, 0);
  if (!initialPageSize) {
    initialPageSize = 10;
    if (serverTotal > 0 && rows.length > 0) {
      initialPageSize = rows.length;
    }
  }

  let buildQuery = null;
  try {
    buildQuery = await NX.BuildQuery(storeId);
  } catch (e) {
    console.warn("[NexaTabelScreen] BuildQuery belum siap, pakai fallback kolom:", e);
  }
  const formulirRaw =
    buildQuery?.formulir && typeof buildQuery.formulir === "object" ? buildQuery.formulir : {};
  const hasFormulirSchema = Object.keys(formulirRaw).length > 0;
  const formulir = hasFormulirSchema ? filterFormulirForTabel(formulirRaw) : {};
  const setColom = Object.keys(formulir).map((key) => ({
    key,
    title:
      (formulir[key].placeholder &&
        String(formulir[key].placeholder).charAt(0).toUpperCase() +
          String(formulir[key].placeholder).slice(1)) ||
      titleFromKey(key),
  }));

  const setInline = Object.keys(formulir).filter((key) => formulir[key].inline === true);

  const workAccses = await loadBucketsStoreWorkAccses();
  const enforceWorkAccses = workAccses != null;
  const updatePermissionOk = !enforceWorkAccses || tabelWorkPermissionActive(workAccses, "update");
  const allowInlineEditing = setInline.length > 0 && updatePermissionOk;

  const setHidden =
    hasFormulirSchema
      ? Object.keys(formulir).filter((key) => formulir[key].hidden === true)
      : ["id", "userid"];

  const captionText =
    String(setting.caption || setting.formTitle || "").trim() || `Hasil Query ${actionId}`;
  const exportIncludeRaw = String(setting.exportInclude || "filtered").toLowerCase();
  const exportInclude = ["filtered", "all", "page"].includes(exportIncludeRaw) ? exportIncludeRaw : "filtered";
  const exportFileName =
    String(setting.exportFileName || "export")
      .trim()
      .replace(/[^\w.\-]/g, "_") || "export";

  const tableCaption = useCard ? "" : captionText;

  const appNameForMethod = String(row?.appname || "Form").trim();
  const tabelDefaultMethod = `method${appNameForMethod.replaceAll(/\s+/g, "")}`;
  const tabelSendMethod = String(
    setting.methodSubmit || setting.methodById || tabelDefaultMethod,
  ).trim();

  const defaultOnEditMethod = `${tabelDefaultMethod}OnEdit`;
  const tabelOnEditMethod = String(setting.methodOnEdit ?? "").trim() || defaultOnEditMethod;

  const showRowActionsMenu = tabelCfgBool(setting.showRowActionsMenu, true);
  const rowActions = showRowActionsMenu ? parseRowActionsFromSetting(setting) : null;
  const actionsColumnTitle =
    String(setting.actionsColumnTitle || "Actions").trim() || "Actions";

  const tableOptions = {
    setting,
    container: tableContainerSelector,
    data: rows,
    caption: tableCaption,
    pageSize: initialPageSize,
    paginationSize: String(setting.paginationSize || "sm").toLowerCase() === "default" ? "default" : "sm",
    rowNumberColumn: tabelCfgBool(setting.rowNumberColumn, true),
    searchable: tabelCfgBool(setting.searchable, true),
    sortable: tabelCfgBool(setting.sortable, true),
    columnMenu: tabelCfgBool(setting.columnMenu, true),
    hideColumnKeys: setHidden,
    columns:
      setColom.length > 0 ? setColom : !hasFormulirSchema ? buildColumns(rows) : [],
    editing: allowInlineEditing ? buildEditingConfigFromFormulir(formulir) : null,
    inline: allowInlineEditing ? setInline : null,
    export: {
      enabled: tabelCfgBool(setting.exportEnabled, true),
      types: parseExportTypes(setting.exportTypes),
      include: exportInclude,
      fileName: exportFileName,
    },
    spinner: tabelCfgBool(setting.spinnerEnabled, false)
      ? { enabled: true, type: "overlay", size: "medium" }
      : undefined,

    serverSide: true,
    serverTotal: serverTotal,
    onFetchPage: async (page, pageSize, search = "") => {
      const skip = page > 0 && search === "";
      const res = await getQueryResultWithScope(actionId, page, pageSize, skip, search, queryScope);
      return {
        rows: extractRows(res),
        total: extractTotal(res),
      };
    },

    onEdit: async (key, value, rowArg) => {
      const detail = {
        source: "nexa-tabel-inline-edit",
        key,
        value,
        row: rowArg,
        storeId,
        actionId: String(actionId || ""),
      };

      if (enforceWorkAccses && !tabelWorkPermissionActive(workAccses, "update")) {
        notifyTabelInlineAccessDenied("Edit inline memerlukan hak Update di Accses.");
        return;
      }

      let fnEdit = resolveMethodSubmitHandler(tabelOnEditMethod);
      let usedEdit = tabelOnEditMethod;
      if (typeof fnEdit !== "function" && tabelOnEditMethod !== defaultOnEditMethod) {
        fnEdit = resolveMethodSubmitHandler(defaultOnEditMethod);
        if (typeof fnEdit === "function") {
          usedEdit = defaultOnEditMethod;
          console.warn(
            `[tabel] onEdit handler "${tabelOnEditMethod}" tidak ada; memakai "${defaultOnEditMethod}" (sesuai appname).`,
          );
        }
      }
      if (typeof fnEdit === "function") {
        try {
          await Promise.resolve(fnEdit(detail));
        } catch (e) {
          console.error("[tabel] methodOnEdit / onEdit error:", e);
        }
      } else if (allowInlineEditing) {
        try {
          const saved = await persistInlineEditFallback(buildQuery, rowArg, key, value);
          if (!saved && usedEdit) {
            console.warn(
              `[tabel] onEdit handler "${tabelOnEditMethod}"${tabelOnEditMethod !== defaultOnEditMethod ? ` dan "${defaultOnEditMethod}"` : ""} tidak ditemukan pada nx / NXUI.`,
            );
          }
        } catch (e) {
          console.error("[tabel] fallback inline save error:", e);
          if (usedEdit) {
            console.warn(
              `[tabel] onEdit handler "${tabelOnEditMethod}"${tabelOnEditMethod !== defaultOnEditMethod ? ` dan "${defaultOnEditMethod}"` : ""} tidak ditemukan pada nx / NXUI.`,
            );
          }
        }
      }

      try {
        container.dispatchEvent(
          new CustomEvent("nexa-tabel-inline-edit", {
            bubbles: true,
            detail,
          }),
        );
      } catch (err) {
        console.warn("nexa-tabel-inline-edit:", err);
      }
    },
    formatCell: (value) => {
      if (value == null) {
        return "";
      }

      if (typeof value === "object") {
        return JSON.stringify(value);
      }

      return String(value);
    },
  };

  if (rowActions) {
    tableOptions.actions = rowActions;
    tableOptions.actionsColumnTitle = actionsColumnTitle;
    tableOptions.onAction = async (action, rowArg) => {
      const detail = {
        source: "nexa-tabel-row-action",
        action,
        row: rowArg,
        storeId,
        actionId: String(actionId || ""),
      };

      let fn = resolveMethodSubmitHandler(tabelSendMethod);
      let usedMethod = tabelSendMethod;
      if (typeof fn !== "function" && tabelSendMethod !== tabelDefaultMethod) {
        fn = resolveMethodSubmitHandler(tabelDefaultMethod);
        if (typeof fn === "function") {
          usedMethod = tabelDefaultMethod;
          console.warn(
            `[tabel] Handler "${tabelSendMethod}" tidak ada; memakai "${tabelDefaultMethod}" (sesuai appname).`,
          );
        }
      }
      if (typeof fn === "function") {
        try {
          await Promise.resolve(fn(detail));
        } catch (e) {
          console.error("[tabel] methodSubmit / onAction error:", e);
        }
      } else if (usedMethod) {
        console.warn(
          `[tabel] Handler "${tabelSendMethod}"${tabelSendMethod !== tabelDefaultMethod ? ` dan "${tabelDefaultMethod}"` : ""} tidak ditemukan pada nx / NXUI.`,
        );
      }

      try {
        container.dispatchEvent(
          new CustomEvent("nexa-tabel-row-action", {
            bubbles: true,
            detail,
          }),
        );
      } catch (err) {
        console.warn("nexa-tabel-row-action:", err);
      }
    };
  }

  const table = new NXUI.NexaTables(tableOptions);

  await table.mount();
  tabelViewTableInstance = table;
  return table;
}
