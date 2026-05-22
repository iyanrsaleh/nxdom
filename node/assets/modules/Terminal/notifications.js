export async function rtdbRotifications(data) {
    const rtdb = await window.NXUI.FirebaseStorage();
    await rtdb.set('notifications', { 
      id: data?.action + "_" + NEXA.userData.key, 
      avatar: NEXA.userData.avatar, 
      name: NEXA.userData.user_real_name,
      row: 1,
      icon: data?.icon,
      action: data?.action,
      message: data?.message,
      last_activity: new Date().toISOString(),
      login_time: new Date().toISOString(),
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString()
    });
  }


