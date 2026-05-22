/**
 * PWA: meta manifest, banner update Service Worker, hook background sync.
 */

const BANNER_ID = "nexa-pwa-update-banner";

function ensureUpdateBanner(themeColor) {
  if (typeof document === "undefined") return null;
  let el = document.getElementById(BANNER_ID);
  if (el) return el;

  const color = themeColor || "#CB2F2F";
  el = document.createElement("div");
  el.id = BANNER_ID;
  el.setAttribute("role", "status");
  el.hidden = true;
  el.innerHTML =
    '<style>#' +
    BANNER_ID +
    "{position:fixed;left:0;right:0;bottom:0;z-index:100000;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.75rem;padding:.75rem 1rem;background:" +
    color +
    ";color:#fff;font-family:system-ui,sans-serif;font-size:.9rem;box-shadow:0 -4px 20px rgba(0,0,0,.15)}#" +
    BANNER_ID +
    '[hidden]{display:none!important}#' +
    BANNER_ID +
    " button{border:none;border-radius:6px;padding:.45rem .9rem;cursor:pointer;font-size:.85rem}#" +
    BANNER_ID +
    " .nexa-pwa-update__reload{background:#fff;color:" +
    color +
    ";font-weight:600}#" +
    BANNER_ID +
    ' .nexa-pwa-update__dismiss{background:transparent;color:#fff;text-decoration:underline}</style><span>Versi baru tersedia.</span><button type="button" class="nexa-pwa-update__reload">Muat ulang</button><button type="button" class="nexa-pwa-update__dismiss">Nanti</button>';

  document.body.appendChild(el);
  el.querySelector(".nexa-pwa-update__reload")?.addEventListener("click", () => {
    window.location.reload();
  });
  el.querySelector(".nexa-pwa-update__dismiss")?.addEventListener("click", () => {
    el.hidden = true;
  });
  return el;
}

function showUpdateBanner(themeColor) {
  const el = ensureUpdateBanner(themeColor);
  if (el) el.hidden = false;
}

export function watchNexaPwaUpdate(options = {}) {
  if (typeof navigator === "undefined" || !("serviceWorker" in navigator)) {
    return () => {};
  }
  if (options.promptUpdate === false) return () => {};

  const themeColor = options.themeColor || "#CB2F2F";
  let disposed = false;

  const onWaiting = (reg) => {
    if (disposed || !reg?.waiting) return;
    showUpdateBanner(themeColor);
  };

  navigator.serviceWorker.ready
    .then((reg) => {
      if (reg.waiting) onWaiting(reg);
      reg.addEventListener("updatefound", () => {
        const installing = reg.installing;
        if (!installing) return;
        installing.addEventListener("statechange", () => {
          if (
            installing.state === "installed" &&
            navigator.serviceWorker.controller
          ) {
            onWaiting(reg);
          }
        });
      });
    })
    .catch(() => {});

  return () => {
    disposed = true;
  };
}

export function initNexaPwa(config = {}) {
  if (config.enabled === false) return { dispose: () => {} };

  const themeColor = config.themeColor || "#CB2F2F";

  if (typeof document !== "undefined" && config.manifestUrl) {
    let link = document.querySelector('link[rel="manifest"]');
    if (!link) {
      link = document.createElement("link");
      link.rel = "manifest";
      document.head.appendChild(link);
    }
    link.href = config.manifestUrl;
  }

  if (typeof document !== "undefined") {
    let meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) {
      meta = document.createElement("meta");
      meta.name = "theme-color";
      document.head.appendChild(meta);
    }
    meta.content = themeColor;
  }

  const disposeUpdate = watchNexaPwaUpdate({
    themeColor,
    promptUpdate: config.promptUpdate,
  });

  const onSync =
    typeof config.onBackgroundSync === "function"
      ? config.onBackgroundSync
      : null;
  const syncHandler = (event) => {
    if (onSync && event?.detail) onSync(event.detail);
  };
  if (onSync && typeof window !== "undefined") {
    window.addEventListener("nexaBackgroundSync", syncHandler);
  }

  if (typeof window !== "undefined" && window.NEXA) {
    window.NEXA.pwa = {
      ...(window.NEXA.pwa || {}),
      enabled: true,
      manifestUrl: config.manifestUrl,
      themeColor,
    };
  }

  return {
    dispose: () => {
      disposeUpdate();
      if (onSync) window.removeEventListener("nexaBackgroundSync", syncHandler);
    },
  };
}
