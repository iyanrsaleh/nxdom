/**
 * NexaWebWorker — fetch + parse respons di thread terpisah agar UI tidak terblokir
 * oleh jaringan atau JSON.parse payload besar.
 * Tidak mengimpor modul eksternal (worker klasik).
 */
self.onmessage = async (e) => {
  const msg = e.data || {};
  const { id, type } = msg;

  if (type === "FETCH") {
    const { url, method, body, headers } = msg;
    try {
      const res = await fetch(url, {
        method: method || "POST",
        body: body != null ? body : undefined,
        headers: headers || { "Content-Type": "application/json" },
      });

      const contentType = (res.headers.get("content-type") || "").toLowerCase();
      let parsed;

      if (contentType.includes("application/json")) {
        const text = await res.text();
        try {
          parsed = text ? JSON.parse(text) : null;
        } catch {
          parsed = text;
        }
      } else if (contentType.startsWith("text/")) {
        parsed = await res.text();
      } else {
        parsed = await res.text();
      }

      if (!res.ok) {
        let errMsg = `HTTP Error: ${res.status} ${res.statusText}`;
        if (parsed && typeof parsed === "object") {
          if (parsed.message) errMsg += ` - ${parsed.message}`;
          else if (parsed.error) errMsg += ` - ${parsed.error}`;
        } else if (typeof parsed === "string" && parsed.trim()) {
          errMsg += ` - ${parsed.substring(0, 200)}`;
        }
        self.postMessage({
          id,
          ok: false,
          status: res.status,
          statusText: res.statusText,
          errorMessage: errMsg,
          parsed,
        });
        return;
      }

      self.postMessage({ id, ok: true, parsed });
    } catch (err) {
      self.postMessage({
        id,
        ok: false,
        errorMessage: err && err.message ? err.message : String(err),
      });
    }
    return;
  }

  if (type === "PARSE_JSON") {
    try {
      const { text } = msg;
      const parsed = text ? JSON.parse(text) : null;
      self.postMessage({ id, ok: true, parsed });
    } catch (err) {
      self.postMessage({
        id,
        ok: false,
        errorMessage: err && err.message ? err.message : String(err),
      });
    }
  }
};
