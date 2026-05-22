/**
 * Reload Component
 * Handler untuk context menu item "Refresh"
 * Melakukan update IndexedDB lalu reload window
 */
export async function reload(route) {
  try {
    // Pastikan NXUI tersedia
    if (!window.NXUI || !window.NXUI.ref || !window.NXUI.Storage) {
      console.warn('⚠️ NXUI belum ready, langsung reload window');
      window.location.reload();
      return { success: true };
    }

    // Update IndexedDB dengan data terbaru
    const userData = await NXUI.ref.get("userData", "uniqueId");
    
    // Validasi userData sebelum digunakan
    if (!userData || !userData.success) {
      console.warn('⚠️ userData tidak tersedia, langsung reload window');
      window.location.reload();
      return { success: true };
    }
    
    const metadata = await NXUI.Storage().package('Applications').metaDataID({
      id: userData?.userid,
    });

    await NXUI.ref.set('bucketsStore', {
      id: "userAgent",
      ...metadata.data,
      username: userData.user_real_name,
      useremail: userData.email,
      avatar: userData.avatar,
      token_status: 1
    });
    
    // Pastikan NEXA tersedia sebelum digunakan
    if (typeof NEXA !== 'undefined') {
      await NXUI.ref.set('bucketsStore', {
        id: "nexa",
        ...NEXA
      });
    }
    // Tunggu sedikit untuk memastikan IndexedDB selesai diupdate
    await new Promise(resolve => setTimeout(resolve, 100));

    // Reload window setelah IndexedDB selesai diupdate
    window.location.reload();

    return { success: true };
  } catch (error) {
    console.error('❌ Error in reload component:', error);
    return { success: false, error: error.message };
  }
}
