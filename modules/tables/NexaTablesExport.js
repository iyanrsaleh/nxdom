/**
 * Export helper untuk NexaTables (client-side).
 * Tujuan: memisahkan logika export agar lebih mudah di-maintain/di-kembangkan.
 */

function sanitizeFileName(name) {
  return String(name ?? "nexa-tables")
    .trim()
    .replace(/[\\/:*?"<>|]+/g, "-")
    .replace(/\s+/g, "_");
}

function dateStamp() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function downloadFile(fileName, mime, content) {
  const blob = new Blob([content], { type: mime });
  const url = URL.createObjectURL(blob);
  try {
    const link = document.createElement("a");
    link.href = url;
    link.download = fileName;
    link.style.display = "none";
    document.body.appendChild(link);
    link.click();
    link.remove();
  } finally {
    setTimeout(() => URL.revokeObjectURL(url), 0);
  }
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function rowValueForExport(row, colKey, formatCell) {
  const raw = row?.[colKey];
  if (typeof formatCell === "function") {
    try {
      return formatCell(raw, colKey, row);
    } catch {
      // ignore, fall back to raw
    }
  }
  return raw;
}

function escapeCSVCell(v) {
  if (v == null) return '""';
  let s;
  if (typeof v === "object") {
    try {
      s = JSON.stringify(v);
    } catch {
      s = String(v);
    }
  } else {
    s = String(v);
  }
  s = s.replace(/\r?\n/g, " ");
  return `"${s.replace(/"/g, '""')}"`;
}

function toCSV(rows, columns, formatCell) {
  const cols = Array.isArray(columns) ? columns : [];
  const headers = cols.map((c) => c.title || c.key);
  const keys = cols.map((c) => c.key);

  const headerLine = headers
    .map((h) => `"${String(h).replace(/"/g, '""')}"`)
    .join(",");

  const lines = rows.map((row) => {
    return keys.map((k) => escapeCSVCell(rowValueForExport(row, k, formatCell))).join(",");
  });

  return `${headerLine}\n${lines.join("\n")}\n`;
}

function toXLSXHtml(rows, columns, formatCell) {
  const cols = Array.isArray(columns) ? columns : [];
  const headers = cols.map((c) => c.title || c.key);
  const keys = cols.map((c) => c.key);

  const headHtml = headers.map((h) => `<th>${escapeHtml(h)}</th>`).join("");

  const bodyHtml = rows
    .map((row) => {
      const tds = keys
        .map((k) => {
          const v = rowValueForExport(row, k, formatCell);
          const s =
            v == null ? "" : typeof v === "object" ? JSON.stringify(v) : String(v);
          return `<td>${escapeHtml(s)}</td>`;
        })
        .join("");
      return `<tr>${tds}</tr>`;
    })
    .join("");

  // Excel bisa membaca HTML dengan MIME xlsx (mirip recordExport.js)
  return `
      <html>
        <head>
          <meta charset="utf-8">
          <style>
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #4CAF50; color: white; font-weight: bold; padding: 8px; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
          </style>
        </head>
        <body>
          <table>
            <thead>
              <tr>${headHtml}</tr>
            </thead>
            <tbody>
              ${bodyHtml}
            </tbody>
          </table>
        </body>
      </html>
    `.trim();
}

function exportToPDF(rows, columns, baseFileName, formatCell) {
  const cols = Array.isArray(columns) ? columns : [];
  const headers = cols.map((c) => c.title || c.key);
  const keys = cols.map((c) => c.key);

  const headHtml = headers.map((h) => `<th>${escapeHtml(h)}</th>`).join("");

  const bodyHtml = rows
    .map((row) => {
      const tds = keys
        .map((k) => {
          const v = rowValueForExport(row, k, formatCell);
          const s =
            v == null ? "" : typeof v === "object" ? JSON.stringify(v) : String(v);
          return `<td>${escapeHtml(s)}</td>`;
        })
        .join("");
      return `<tr>${tds}</tr>`;
    })
    .join("");

  const htmlContent = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${escapeHtml(baseFileName)}</title>
  <style>
    @media print {
      body { margin: 0; }
      .no-print { display: none; }
    }
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #333; margin-bottom: 10px; }
    .export-info { margin-bottom: 15px; color: #666; font-size: 12px; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; page-break-inside: auto; }
    tr { page-break-inside: avoid; page-break-after: auto; }
    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    th, td { border: 1px solid #333; padding: 8px; text-align: left; font-size: 11px; }
    th { background-color: #f0f0f0; font-weight: bold; }
    .no-print { margin-top: 20px; padding: 10px; }
    button { padding: 8px 16px; margin-right: 10px; cursor: pointer; }
  </style>
</head>
<body>
  <h1>${escapeHtml(baseFileName)}</h1>
  <div class="export-info">
    <p>Tanggal Export: ${escapeHtml(new Date().toLocaleDateString("id-ID"))}</p>
    <p>Total Records: ${escapeHtml(String(rows.length))}</p>
  </div>
  <table>
    <thead>
      <tr>${headHtml}</tr>
    </thead>
    <tbody>
      ${bodyHtml}
    </tbody>
  </table>
  <div class="no-print">
    <button onclick="window.print()">Print / Save as PDF</button>
    <button onclick="window.close()">Close</button>
  </div>
</body>
</html>
  `.trim();

  const printWindow = window.open("", "_blank");
  if (!printWindow) return;
  printWindow.document.write(htmlContent);
  printWindow.document.close();
  setTimeout(() => {
    try {
      printWindow.print();
    } catch {
      // ignore
    }
  }, 200);
}

export {
  sanitizeFileName,
  dateStamp,
  downloadFile,
  toCSV,
  toXLSXHtml,
  exportToPDF,
};

