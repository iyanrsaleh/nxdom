/**
 * Terminal Component
 * Handler untuk context menu item "Terminal"
 */
export async function nexaBeranda(route) {
  try {
    return NXUI.load('beranda');
  } catch (error) {
    console.error('❌ Error opening terminal:', error);
    return { success: false, error: error.message };
  }
}
