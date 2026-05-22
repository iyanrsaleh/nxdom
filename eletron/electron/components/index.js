/**
 * Router handler menu konteks (renderer) — berkas di electron/components, disajikan lewat /nexa-context/ (index.js).
 */
export async function components(route) {
  try {
    // Pastikan route dan role ada
    if (!route || !route.role) {
      console.warn('⚠️ Route atau role tidak ditemukan:', route);
      return { success: false, error: 'Route atau role tidak ditemukan' };
    }

    // Dynamic import module berdasarkan role
    const module = await import(`./${route.role}.js`);
    
    // Ambil function dari module (nama function sama dengan role)
    const functionName = route.role;
    const contentFunction = module[functionName];
    
    // Pastikan function ada
    if (!contentFunction || typeof contentFunction !== 'function') {
      console.warn(`⚠️ Function ${functionName} tidak ditemukan di module`);
      return { success: false, error: `Function ${functionName} tidak ditemukan` };
    }
    
    // Panggil function dengan route sebagai parameter
    const result = await contentFunction(route);
    return result;
  } catch (error) {
    console.error('❌ Error di components:', error);
    return { success: false, error: error.message };
  }
}
