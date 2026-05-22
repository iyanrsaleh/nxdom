
export async function controllers(key = false) {
  try {
    // Jika forceRefresh, langsung query dari database dan update cache
    let credential = await window.NXUI.ref.get("bucketsStore", 'credential');
        console.log('NEXA.userId:', NEXA.userId);
    if (!credential?.id) {
      const user = await new NXUI.NexaModels()
        .Storage("user")
        .select(["email","password"])
        .where("id",NEXA.userId)
        .first();
      
      await window.NXUI.ref.set("bucketsStore", {
        id: "credential",
        oauth: false,
        data: user.data,
      });
      
      // Get credential again after setting it
      credential = await window.NXUI.ref.get("bucketsStore", 'credential');
    }

    // Return in the same format as the original user object (with .data property)
    return {
      credential,
      oauth: credential?.oauth || false,
      data: credential?.data || null
    };
  } catch (error) {
    console.error("❌ Manual mode initialization failed:", error);
    return null;
  }
}


