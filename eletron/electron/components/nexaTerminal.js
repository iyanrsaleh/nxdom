/**
 * Terminal Component
 * Handler untuk context menu item "Terminal"
 */
export async function nexaTerminal(route) {
  try {
    console.log('Opening terminal...');
    return { success: true };
  } catch (error) {
    console.error('❌ Error opening terminal:', error);
    return { success: false, error: error.message };
  }
}
