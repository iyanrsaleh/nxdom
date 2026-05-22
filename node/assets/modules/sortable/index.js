/**
 * Route `/sortable` — demo `NXUI.Sortable` / `NXUI.NexaSortable` (`assets/modules/Dom/NexaSortable.js`).
 * Drag & drop HTML5 pada `<li draggable="true">` dengan isi `.sortable-item`.
 */
export async function sortable(page, route) {
  route.register(
    page,
    async (
      routeName,
      container,
      routeMeta = {
        title: "Sortable | App",
        description: "Demo NexaSortable — urut ulang item (drag & drop).",
      },
      style,
      nav = {}
    ) => {
      route.routeMetaByRoute.set(page, routeMeta);

      container.innerHTML = `
        <style>
          .nexa-sortable-demo { max-width: 36rem; line-height: 1.55; }
          .nexa-sortable-demo h1 { margin-top: 0; }
          .nexa-sortable-demo ul#nexa-sortable-demo-list {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
            border: 1px solid rgba(0,0,0,0.12);
            border-radius: 8px;
            overflow: hidden;
          }
          .nexa-sortable-demo ul#nexa-sortable-demo-list > li {
            padding: 0.65rem 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            background: #fafafa;
          }
          .nexa-sortable-demo ul#nexa-sortable-demo-list > li:last-child {
            border-bottom: none;
          }
          .nexa-sortable-demo .sortable-item { display: block; }
          .nexa-sortable-demo #nexa-sortable-demo-output {
            font-family: ui-monospace, monospace;
            font-size: 0.85rem;
            padding: 0.75rem 1rem;
            background: #f0f4f8;
            border-radius: 6px;
            margin-top: 0.75rem;
            word-break: break-all;
          }
        </style>
        <div class="nexa-sortable-demo">
          <h1>NXUI.Sortable (NexaSortable)</h1>
          <p style="margin-top:0;opacity:.9">
            Kelas di <code>assets/modules/Dom/NexaSortable.js</code>.
            Akses: <code>new NXUI.Sortable(options)</code> atau <code>new NXUI.NexaSortable(options)</code>.
          </p>
          <p style="font-size:.9rem">
            Panggil <code>.onCallback(fn)</code> (opsional) lalu <code>.initSortable()</code>.
            Setelah urutan berubah, <code>NexaSortable</code> juga memancarkan event dokumen
            <code>nexaSortableDemoReordered</code> dengan <code>detail.newOrder</code> (nama event =
            <code>{eventKey}Reordered</code>).
          </p>
          <p style="font-size:.9rem;margin-bottom:0.5rem"><strong>Seret</strong> baris untuk menukar posisi:</p>
          <ul id="nexa-sortable-demo-list" aria-label="Daftar dapat diurutkan">
            <li draggable="true" data-index="0">
              <span class="sortable-item" value="alpha">Alpha</span>
            </li>
            <li draggable="true" data-index="1">
              <span class="sortable-item" value="beta">Beta</span>
            </li>
            <li draggable="true" data-index="2">
              <span class="sortable-item" value="gamma">Gamma</span>
            </li>
            <li draggable="true" data-index="3">
              <span class="sortable-item" value="delta">Delta</span>
            </li>
          </ul>
          <p style="font-size:.85rem;margin:0 0 0.25rem">Urutan nilai (<code>value</code> / teks):</p>
          <div id="nexa-sortable-demo-output" aria-live="polite">(geser item untuk memperbarui)</div>
          <p style="font-size:.8rem;opacity:.85;margin-top:1rem">
            Opsi: <code>containerId</code>, <code>itemClass</code>, <code>eventKey</code>, <code>logPrefix</code>.
            <code>getCurrentOrder()</code> mengembalikan array dan menulis log ke konsol.
          </p>
        </div>
      `;

      if (typeof NXUI === "undefined" || !NXUI.Sortable) {
        console.error("NXUI.Sortable tidak tersedia.");
        return;
      }

      const outputEl = container.querySelector("#nexa-sortable-demo-output");

      const syncOutput = (newOrder) => {
        if (outputEl && Array.isArray(newOrder)) {
          outputEl.textContent = newOrder.join(" → ");
        }
      };

      const sortableInst = new NXUI.Sortable({
        containerId: "nexa-sortable-demo-list",
        itemClass: "sortable-item",
        eventKey: "nexaSortableDemo",
        logPrefix: "SortableDemo",
      });

      sortableInst.onCallback((newOrder) => {
        syncOutput(newOrder);
      });

      sortableInst.initSortable();

      syncOutput(sortableInst.getCurrentOrder());
    }
  );
}
