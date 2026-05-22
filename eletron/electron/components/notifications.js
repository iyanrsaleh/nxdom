/**
 * About Page
 * Route: #about
 */

export  async function allCount() {
  try {
    const notif = await NXUI.ref.getAll("notifications");
    
    if (!notif.data || notif.data.length === 0) {
      return '';
    }
    
    // Return jumlah semua notifikasi
    return notif.data.length;
  } catch (error) {
    console.error('❌ Error counting all notifications:', error);
    return 0;
  }
}
export  async function notifCount() {
  try {
    const notif = await NXUI.ref.getAll("notifications");
    
    if (!notif.data || notif.data.length === 0) {
      return '';
    }
    
    // Filter notifikasi yang belum dibaca (row === 1)
    const unreadNotif = notif.data.filter(item => item.row === 1);
    
    return unreadNotif.length;
  } catch (error) {
    console.error('❌ Error counting notifications:', error);
    return 0;
  }
}

export  async function notifications() {
   const notif = await NXUI.ref.getAll("notifications");
   // Sort berdasarkan timestamp terbaru (descending)
   const sortedNotif = notif.data.sort((a, b) => {
     const timeA = a.timestamp_original || a.login_time_timestamp || a.last_activity_timestamp || 0;
     const timeB = b.timestamp_original || b.login_time_timestamp || b.last_activity_timestamp || 0;
     return timeB - timeA; // Terbaru di atas
   });
   
   // Jika tidak ada data
   if (!sortedNotif || sortedNotif.length === 0) {
     // Buat container untuk empty state dengan SVG
     const emptyContainer = document.createElement('div');
     emptyContainer.className = 'nx-alert nx-alert-info1';
     emptyContainer.style.cssText = `
       display: flex;
       flex-direction: column;
       align-items: center;
       justify-content: center;
       padding: 40px 20px;
       text-align: center;
       border: none;
     `;
     
     // Tambahkan SVG
     const svgElement = NXUI.Svg({
       name: 'forgot',
       width: 200,
       height:150,
       fill: '#0168fa',
       style: {
         marginBottom: '0px',
         filter: 'drop-shadow(0 4px 8px rgba(0,0,0,0.1))'
       }
     });
     
     // Tambahkan text
     const textElement = document.createElement('div');
     emptyContainer.appendChild(svgElement);
     return emptyContainer.outerHTML;
   }
   
   // Generate HTML untuk setiap notifikasi
   const notificationsHTML = sortedNotif.map(user => {
     // Handle avatar URL (bisa relative atau absolute)
     const avatarUrl = user.avatar 
       ? (user.avatar.startsWith('http') ? user.avatar : `${NEXA?.url || ''}/assets/drive/${user.avatar}`)
       : `${NEXA?.url || ''}/assets/images/pria.png`;
     
     // Icon berdasarkan action atau default (Material Symbols)
     const icon = user.icon || 'computer';
     const message = user.message || 'Login desktop';
     // Map icon name ke Material Symbols
     const materialIcon = icon === 'computer' ? 'computer' : icon;
     
     // Icon berdasarkan role user
     const roleIcon = user.role === 'admin' ? 'admin_panel_settings' : 
                      user.role === 'user' ? 'person' : 
                      user.role ? 'account_circle' : '';
     
     return `<div   class="nx-media notif-item" style="padding: 8px 10px; border-bottom: 1px solid #eee;">
      <img src="${avatarUrl}" 
           class="nx-media-img" 
           alt="${user.name || 'User'}" 
           style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;"
           onerror="this.src='${NEXA?.url || ''}/assets/images/pria.png'">
      <div class="nx-media-body">
        <div style="display: flex; justify-content: space-between; align-items: start;">
          <div>
            <h5 style="margin: 0 0 3px 0; font-size: 12px; font-weight: 600; line-height: 1.3;">${user.name || 'Unknown User'}</h5>
            <p style="margin: 0 0 2px 0; font-size: 10px; color: #000; display: flex; align-items: center; gap: 4px; line-height: 1.2;">
             
              ${message}
            </p>
            <p style="margin: 0; font-size: 10px; color: #999; display: flex; align-items: center; gap: 4px; line-height: 1.2;">
              <span class="material-symbols-outlined nx-icon-xs" style="font-size: 10px;">schedule</span>
              ${user.login_time || user.last_activity || ''}
            </p>
          </div>
          <div class="pull-right notification-actions" >
           <span class="material-symbols-outlined nx-icon-md" style="font-size: 10px;">${materialIcon}</span>
           <span onclick="notificationID('${user.id}');" id="${user.id}" class="material-symbols-outlined nx-icon-md notif-delete-btn">delete</span>
        </div>
        </div>
      </div>
    </div>`;
   }).join('');
   
   return `
   <style>
     .notif-delete-btn {
       display: none !important;
       transition: opacity 0.2s ease !important;
       color: #dc3545 !important;
     }
     .notif-item:hover .notif-delete-btn {
       display: inline-block !important;
       color: #dc3545 !important;
     }
     .notif-item .notif-delete-btn.material-symbols-outlined {
       color: #dc3545 !important;
     }
     .notif-item:hover .notif-delete-btn.material-symbols-outlined {
       color: #dc3545 !important;
     }
   </style>
    ${notificationsHTML}
  `;
}

window.notificationID = async function(e) {

      const result = await NXUI.ref.delete("notifications", e);
     await renderingNotifications()
  // console.log('w:', result);
}
export async function renderingNotifications() {
  try {
    // Refresh tampilan notifikasi menggunakan NexaRender
    await NXUI.NexaRender.refreshInstant(
      {}, // data object (tidak digunakan karena notifications() mengambil data sendiri)
      async () => await notifications(), // generator function
      {
        containerSelector: "#notificationsDom",
        cardSelector: false, // Tidak ada card wrapper
      }
    );
    
    // Update badge count juga
    await updateNotificationBadge();
    
    console.log('✅ Notifikasi berhasil di-refresh');
  } catch (error) {
    console.error('❌ Error rendering notifications:', error);
  }
}

// Helper function untuk update badge
async function updateNotificationBadge() {
  const count = await notifCount();
  const badge = document.querySelector('.unread-badge');
  const bellElement = document.querySelector('.notification-bell-all');
  
  // Update badge icon
  if (bellElement && count > 0) {
    if (!badge) {
      const newBadge = document.createElement("span");
      newBadge.className = "unread-badge";
      newBadge.style.cssText = `
        position: absolute; top: -8px; right: -8px; background: #ff4444;
        color: white; border-radius: 50%; min-width: 18px; height: 18px;
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: bold; z-index: 1000;
        border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
      `;
      
      const parent = bellElement.parentElement || bellElement;
      if (getComputedStyle(parent).position === "static") {
        parent.style.position = "relative";
      }
      parent.appendChild(newBadge);
      newBadge.textContent = count > 99 ? '99+' : count;
    } else {
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    }
  } else if (badge) {
    badge.style.display = 'none';
  }
  
  // Update notification header count
  const headerElement = document.querySelector('.notifications-header h4');
  if (headerElement) {
    // Render ulang header dengan count yang sesuai
    headerElement.innerHTML = `NOTIFIKASI ${count > 0 ? `<span style="float:right;">${count}</span>` : ''}`;
  }
  
  // Update notification footer count dan tombol delete
  const allCountValue = await allCount();
  const footerCount = document.querySelector('.notifications-footer');
  if (footerCount) {
    // Render ulang footer dengan tombol delete yang sesuai
    footerCount.innerHTML = `
      ${allCountValue > 0 ? allCountValue:''} NOTIFIKASI  
      ${allCountValue > 0 ? `<a onclick="delAllNotifications();" class="view-all-link pull-right" href="#" >  
       <span style="font-size: 14px; color: #dc3545;" class="material-symbols-outlined nx-icon-md">delete</span>
      </a>` : ''}
    `;
  }
}

export async function delNotifications() {
  try {
    const notif = await NXUI.ref.getAll("notifications");
    
    if (!notif.data || notif.data.length === 0) {
      console.log('ℹ️ Tidak ada notifikasi untuk dihapus');
      return {
        success: true,
        message: 'Tidak ada notifikasi',
        deleted: 0
      };
    }
    
    // Hapus semua notifikasi satu per satu
    let deletedCount = 0;
    for (const notification of notif.data) {
      try {
        await NXUI.ref.delete("notifications", notification.id);
        deletedCount++;
        console.log(`🗑️ Notifikasi dihapus: ${notification.id}`);
      } catch (error) {
        console.error(`❌ Error menghapus notifikasi ${notification.id}:`, error);
      }
    }
    
    // Refresh UI setelah semua dihapus
    await renderingNotifications();
    
    console.log(`✅ Berhasil menghapus ${deletedCount} notifikasi`);
    
    return {
      success: true,
      message: `Berhasil menghapus ${deletedCount} notifikasi`,
      deleted: deletedCount
    };
  } catch (error) {
    console.error('❌ Error menghapus semua notifikasi:', error);
    return {
      success: false,
      message: 'Gagal menghapus notifikasi',
      error: error.message
    };
  }
}
export  async function setNotifications() {
  const credentialID= await window.NXUI.ref.get("bucketsStore", "credential");
  const nexaUI = NexaUI();
  const render = await nexaUI.Storage().api("oauth").config({
    token: true
  });
  const crypto = nexaUI.Crypto("NexaQrV1");
  const Config = crypto.decode(render.token);
  // Firebase V1
  const crud = nexaUI.Firebase("qrlogin", Config);
   const unsubscribe = crud.red(async(allData) => {
   const formatToIndonesianDateTime = (timestamp) => {
       if (!timestamp) return '';
       const date = new Date(timestamp * 1000); // Convert Unix timestamp to milliseconds
       const now = new Date();
       
       // Set waktu ke awal hari (00:00:00) untuk perbandingan
       const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
       const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
       
       // Hitung selisih hari
       const diffTime = today - dateOnly;
       const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
       
       // Format waktu
       const timeOptions = {
         timeZone: 'Asia/Jakarta',
         hour: '2-digit',
         minute: '2-digit',
         second: '2-digit',
         hour12: false
       };
       const timeStr = date.toLocaleTimeString('id-ID', timeOptions);
       
       // Format tanggal lengkap
       const dateOptions = {
         timeZone: 'Asia/Jakarta',
         year: 'numeric',
         month: 'long',
         day: 'numeric',
         hour: '2-digit',
         minute: '2-digit',
         second: '2-digit',
         hour12: false
       };
       const fullDateStr = date.toLocaleString('id-ID', dateOptions);
       
       // Tentukan label berdasarkan selisih hari
       let label = '';
       if (diffDays === 0) {
         label = 'Hari ini';
       } else if (diffDays === 1) {
         label = 'Kemarin';
       } else if (diffDays === 2) {
         label = '2 hari yang lalu';
       } else if (diffDays === 3) {
         label = '3 hari yang lalu';
       } else if (diffDays <= 7) {
         label = `${diffDays} hari yang lalu`;
       } else {
         // Lebih dari 7 hari, tampilkan tanggal lengkap
         return fullDateStr;
       }
       
       return `${label}, ${timeStr}`;
     };
     
     // Filter untuk menghapus email duplikat, pertahankan data pertama yang ditemukan
     const uniqueByEmail = allData.reduce((acc, current) => {
       const existing = acc.find(item => item.email === current.email);
       if (!existing) {
         // Hapus field password dari data dan format tanggal
         const { password, ...dataWithoutPassword } = current;
         
         // Tambahkan format tanggal Indonesia
         dataWithoutPassword.last_activity_formatted = formatToIndonesianDateTime(dataWithoutPassword.last_activity);
         dataWithoutPassword.login_time_formatted = formatToIndonesianDateTime(dataWithoutPassword.login_time);
         
         // Email belum ada, tambahkan tanpa password
         acc.push(dataWithoutPassword);
       }
       // Jika email sudah ada, jangan lakukan apa-apa (pertahankan yang sudah ada)
       return acc;
     }, []);

    // Map array menjadi format notifikasi yang dibutuhkan
    const notif = uniqueByEmail.map(item => ({
      id: item.key,
      name: item.user_real_name,
      avatar: item.avatar,
      email: item.email,
      last_activity: item.last_activity_formatted,
      login_time: item.login_time_formatted,
      // Timestamp original tanpa konversi
      timestamp_original: item.login_time || item.last_activity,
      // Timestamp asli untuk sorting
      last_activity_timestamp: item.last_activity,
      login_time_timestamp: item.login_time,
      // Field tambahan
      role: item.role,
      status: item.status,
      user_id: item.user_id || item.userid,
      dashboard_url: item.dashboard_url,
      redirect: item.redirect,
    }));


         const existingNotifications = await NXUI.ref.getAll("notifications");
        
         for (const user of notif) {
           // Validasi: Cari semua notifikasi dengan email yang sama
           const duplicateNotifs = existingNotifications.data.filter(n => n.email === user.email);
        
           // Hapus semua notifikasi lama dengan email yang sama (hanya simpan yang terbaru)
           if (duplicateNotifs.length > 0) {
             for (const oldNotif of duplicateNotifs) {
               await NXUI.ref.delete("notifications", oldNotif.id);
               console.log(`🗑️ Notifikasi lama dihapus (email: ${user.email}, id: ${oldNotif.id})`);
             }
           }
        
           // Gunakan email sebagai ID untuk memastikan hanya satu notifikasi per email
           // Format: email dengan sanitasi untuk ID yang valid
           const notificationId = `notif_${user.email.replace(/[^a-zA-Z0-9]/g, '_')}`;
        
           // Simpan notifikasi baru (hanya yang terbaru berdasarkan timestamp)
           const notification = {
             id: notificationId,
             icon: 'computer',
             row: 1,
             action: 'desktop',
             message: 'Login desktop',
             ...user,
           };
           
           console.log('credentialID:', credentialID?.data?.key);
           await NXUI.ref.set("notifications", notification);
           // // Hapus dari Firebase menggunakan key (id)
             if (user.id ==credentialID?.data?.key) {
                await crud.del(credentialID?.data?.key);
             }
         }

         // 🔄 Auto-refresh UI setelah data tersimpan
         await renderingNotifications();

         console.log('🔔 Notifikasi UI berhasil di-update secara realtime');

   })
// Firebase V2
  const storage = await NXUI.FirebaseStorage();
  
  // Flag untuk mencegah loop saat delete
  let isProcessing = false;
  const processedIds = new Set();
  
  const unwatchUsers = storage.watch('notifications', async (result) => {
    // ⛔ Skip jika sedang processing
    if (isProcessing) {
      console.log('⏳ Already processing, skipping...');
      return;
    }
    
    console.log('👥 Notifications changed:', result);
    
    // ✅ PENTING: Pastikan result.data adalah array
    let notifications = [];
    
    if (Array.isArray(result.data)) {
      // Sudah array
      notifications = result.data;
    } else if (result.data && typeof result.data === 'object') {
      // Single object, convert ke array
      notifications = [result.data];
    } else {
      // Tidak ada data - ini normal, tunggu data baru
      console.log('⚠️ No data in Firebase, waiting for new notifications...');
      return;
    }
    
    // Filter hanya notifikasi yang belum diproses
    const newNotifications = notifications.filter(n => n && n.id && !processedIds.has(n.id));
    
    if (newNotifications.length === 0) {
      console.log('✅ All notifications already processed');
      return;
    }
    
    console.log('📦 Processing new notifications:', newNotifications.length);
    
    // Set flag
    isProcessing = true;
    
    try {
      // Sync dari Firebase ke IndexedDB
      for (const notification of newNotifications) {
        // Save ke IndexedDB
        await NXUI.ref.set("notifications", notification);
        
        // Hapus dari Firebase (gunakan ID yang benar)
        const firebaseId = notification.action + "_" + credentialID?.data?.key;
        await storage.delete('notifications', firebaseId);
        
        // Tandai sudah diproses
        processedIds.add(notification.id);
        
        console.log('✅ Processed & synced:', notification.id);
      }
      
      // Update UI
      await renderingNotifications();
      await updateNotificationBadge();
      
      console.log('✅ UI updated, waiting for next notification...');
      
    } catch (error) {
      console.error('❌ Error processing notifications:', error);
    } finally {
      // Reset flag setelah selesai
      isProcessing = false;
    }
  });
  
  console.log('🔄 Firebase notification watcher is active and ready!');
}
export  async function seenNotifications() {
  try {
    const existingNotifications = await NXUI.ref.getAll("notifications");
    
    if (!existingNotifications.data || existingNotifications.data.length === 0) {
      console.log('ℹ️ Tidak ada notifikasi untuk ditandai sebagai sudah dibaca');
      return {
        success: true,
        message: 'Tidak ada notifikasi',
        updated: 0
      };
    }
    
    // Update semua notifikasi yang row === 1 menjadi row === 0
    let updatedCount = 0;
    for (const notification of existingNotifications.data) {
      if (notification.row === 1) {
        try {
          // Update field row menjadi 0 (sudah dibaca)
          await NXUI.ref.updateField("notifications", notification.id, "row", 0);
          updatedCount++;
          console.log(`✅ Notifikasi ditandai sudah dibaca: ${notification.id}`);
        } catch (error) {
          console.error(`❌ Error update notifikasi ${notification.id}:`, error);
        }
      }
    }
    
    // Refresh UI setelah semua diupdate
    await updateNotificationBadge();
    
    console.log(`✅ ${updatedCount} notifikasi ditandai sebagai sudah dibaca`);
    
    return {
      success: true,
      message: `${updatedCount} notifikasi ditandai sebagai sudah dibaca`,
      updated: updatedCount
    };
  } catch (error) {
    console.error('❌ Error seenNotifications:', error);
    return {
      success: false,
      message: 'Gagal menandai notifikasi',
      error: error.message
    };
  }
}

// Expose global function untuk manual refresh (debugging atau manual trigger)
window.refreshNotifications = async function() {
  try {
    await renderingNotifications();
    console.log('✅ Manual refresh notifikasi berhasil');
  } catch (error) {
    console.error('❌ Manual refresh notifikasi gagal:', error);
  }
};


