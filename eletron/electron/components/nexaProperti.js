/**
 * Terminal Component
 * Handler untuk context menu item "Terminal"
 */
export async function nexaProperti(route) {
  try {
    // const Terminal = new NXUI.Terminal();
    // await Terminal.open();
    return { success: true };
  } catch (error) {
    console.error('❌ Error opening terminal:', error);
    return { success: false, error: error.message };
  }
}
