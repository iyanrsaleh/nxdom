
export async function setFailed(data) {
  try {
    const dataByFailed = await window.NXUI.ref.getAll("nexaStore");
    const storages = dataByFailed.data || [];
    
    // Gabungkan semua layar.data dari semua storage items
    const allLayarData = [];
    storages.forEach(storage => {
      if (storage?.layar?.data && Array.isArray(storage.layar.data)) {
        storage.layar.data.forEach(item => {
          const row = storage.layar[item];
          if (row) {
            allLayarData.push({
              failed: item,
              id: row?.token,
              icon: row?.icon,
              className: row?.className,
              text: row?.label,
              type: row?.type,
              action: row?.type,
              showCondition: "hasNoSelectedText",
              applications: row.applications
            });
          }
        });
      }
    });
    
    const result = allLayarData;

    const findDataByFailed = (data, failedValue) => {
      if (!failedValue) return [];
      // Gunakan filter untuk mendapatkan semua item dengan className yang sama (case-insensitive)
      return data.filter(item => {
        const match = item.className?.toLowerCase() === failedValue?.toLowerCase();
        return match;
      });
    };

    const complexQuery = await new NXUI.NexaModels()
      .Storage("controllers")
      .select(["*"])
      .where("userid", NEXA.userId)
      .where("categori", "Accses")
      .get();
    console.log('complexQuery.data:', complexQuery.data);
    // Konversi data array menjadi array of objects dengan semua data lengkap
    const result2 = [];
    complexQuery.data.forEach((item) => {
      // Hanya tambahkan jika status = 1
      if (item.status === "1") {
        // Coba beberapa kemungkinan field untuk matching
        const searchClassName = item.className || item.appname || item.label;
        const foundSubmenu = findDataByFailed(result, searchClassName);
        
        result2.push({
          id: item.appid,
          icon: item.appicon ?? item.icon ?? "inventory_2",
          text: item.appname ?? item.label,
          action: "contentElements",
          showCondition: "hasNoSelectedText",
          submenu: foundSubmenu,
        });
      }
    });
console.log('label:', result2);
    return `
      <div id="aner_${data?.id || 'failed'}"></div>
    `;
    
  } catch (error) {
    console.error("❌ Manual mode initialization failed:", error);
    return `
      <div class="alert alert-danger text-center">
        <h5>❌ Initialization Failed</h5>
        <p>Gagal menginisialisasi komponen. Error: ${error.message}</p>
      </div>
    `;
  }
}
