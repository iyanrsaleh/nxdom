/**
 * Route `/scroll` — demo `NXUI.Scroll` / `NXUI.NexaScroll` (`assets/modules/Dom/NexaScroll.js`).
 * Menyimpan posisi scroll (vertikal / horizontal) ke localStorage dengan debounce.
 */
let _scrollDemo = null;

export async function scroll(page, route) {
  route.register(
    page,
    async (
      routeName,
      container,
      routeMeta = {
        title: "Scroll | App",
        description: "Demo NexaScroll — ingat posisi .nx-scroll / .nx-scroll-x.",
      },
      style,
      nav = {}
    ) => {
      route.routeMetaByRoute.set(page, routeMeta);

      if (_scrollDemo) {
        _scrollDemo.destroy();
        _scrollDemo = null;
      }

      const verticalLines = Array.from(
        { length: 36 },
        (_, i) =>
          `<p style="margin:0 0 10px">Baris ${i + 1} — gulir vertikal; posisi dipulihkan setelah reload / kunjungan ulang route.</p>`
      ).join("");

      const horizontalChunk =
        "Lorem ipsum dolor sit amet — scroll horizontal &nbsp;·&nbsp; ".repeat(30);

      container.innerHTML = `
        <style>
          .nexa-scroll-demo-page { max-width: 42rem; line-height: 1.55; }
          .nexa-scroll-demo-page h1 { margin-top: 0; }
          .nexa-scroll-demo-page .demo-actions {
            display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem;
          }
          .nexa-scroll-demo-page .demo-actions button {
            padding: 6px 12px; border-radius: 6px; border: 1px solid #ccc;
            background: #fafafa; cursor: pointer; font-size: 0.85rem;
          }
          .nexa-scroll-demo-page #nexa-scroll-demo-status {
            font: 0.8rem ui-monospace, monospace;
            margin: 0.75rem 0 0;
            padding: 8px 10px;
            background: #f0f4f8;
            border-radius: 6px;
          }
        </style>
        <div class="nexa-scroll-demo-page">
          <h1>NXUI.Scroll (NexaScroll)</h1>
          <p style="margin-top:0;opacity:.9">
            Kelas di <code>assets/modules/Dom/NexaScroll.js</code>.
            Akses: <code>new NXUI.Scroll(options)</code> atau <code>new NXUI.NexaScroll(options)</code>.
          </p>
          <p style="font-size:.9rem">
            Elemen dengan kelas seperti <code>.nx-scroll</code>, <code>.nx-scroll-x</code>, <code>.nx-scroll-rounded</code>, …
            didaftarkan; scroll disimpan ke <code>localStorage</code> (key <code>storageKey</code>) setelah
            <code>debounceDelay</code>. Gaya scrollbar: <code>assets/modules/assets/css/scroll.css</code> (via <code>nexa.css</code>).
          </p>
          <h2 style="font-size:1.05rem;margin:1.25rem 0 0.5rem">Vertikal</h2>
          <div
            id="nexa-scroll-demo-vertical"
            class="nx-scroll nx-scroll-rounded"
            style="max-height:220px;border:1px solid rgba(0,0,0,0.12);padding:12px;border-radius:8px;"
          >
            ${verticalLines}
          </div>
          <h2 style="font-size:1.05rem;margin:1.25rem 0 0.5rem">Horizontal</h2>
          <div
            id="nexa-scroll-demo-horizontal"
            class="nx-scroll-x nx-scroll-thin"
            style="max-width:100%;border:1px solid rgba(0,0,0,0.12);border-radius:8px;"
          >
            <div style="width:1400px;padding:12px;white-space:nowrap">${horizontalChunk}</div>
          </div>
          <p id="nexa-scroll-demo-status" aria-live="polite"></p>
          <div class="demo-actions">
            <button type="button" id="nexa-scroll-demo-top">Scroll vertikal ke atas</button>
            <button type="button" id="nexa-scroll-demo-clear">Hapus simpanan demo (localStorage)</button>
          </div>
        </div>
      `;

      if (typeof NXUI === "undefined" || !NXUI.Scroll) {
        console.error("NXUI.Scroll tidak tersedia.");
        return;
      }

      _scrollDemo = new NXUI.Scroll({
        storageKey: "nexa-scroll-demo",
        debounceDelay: 200,
        autoInit: false,
      });
      _scrollDemo.init();

      const statusEl = container.querySelector("#nexa-scroll-demo-status");
      const vertEl = container.querySelector("#nexa-scroll-demo-vertical");
      const horizEl = container.querySelector("#nexa-scroll-demo-horizontal");

      const updateStatus = () => {
        if (!statusEl || !_scrollDemo) return;
        const v = _scrollDemo.getScrollPosition("nexa-scroll-demo-vertical");
        const h = _scrollDemo.getScrollPosition("nexa-scroll-demo-horizontal");
        const parts = [];
        if (v) parts.push(`vertikal: top=${Math.round(v.scrollTop)}`);
        if (h) parts.push(`horizontal: left=${Math.round(h.scrollLeft)}`);
        statusEl.textContent = parts.length ? parts.join(" · ") : "(menunggu scroll)";
      };

      vertEl?.addEventListener("scroll", updateStatus, { passive: true });
      horizEl?.addEventListener("scroll", updateStatus, { passive: true });

      container.querySelector("#nexa-scroll-demo-top")?.addEventListener("click", () => {
        _scrollDemo?.setScrollPosition("nexa-scroll-demo-vertical", { scrollTop: 0 });
        updateStatus();
      });

      container.querySelector("#nexa-scroll-demo-clear")?.addEventListener("click", () => {
        _scrollDemo?.clearSavedPositions();
        updateStatus();
      });

      updateStatus();
    }
  );
}
