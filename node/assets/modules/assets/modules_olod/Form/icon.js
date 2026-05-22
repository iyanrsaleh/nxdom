export function getIconByType(type, customIcon = null) {
  // Jika ada custom icon dan bukan "attach_file", gunakan custom icon tersebut
  if (customIcon && customIcon !== "attach_file") return customIcon;

  // Mapping tipe field ke Material Icons
  const iconMap = {
    text: "edit_note", // Icon untuk field text biasa
    hidden: "visibility_off", // Icon untuk field hidden
    email: "email", // Icon untuk field email
    password: "lock", // Icon untuk field password
    number: "numbers", // Icon untuk field number
    tel: "phone", // Icon untuk field telepon
    url: "link", // Icon untuk field URL
    search: "search", // Icon untuk field search
    date: "calendar_today", // Icon untuk field date
    "datetime-local": "schedule", // Icon untuk field datetime-local
    time: "access_time", // Icon untuk field time
    textarea: "description", // Icon untuk field textaerea
    select: "event_list", // Icon untuk field select
    radio: "radio_button_checked", // Icon untuk field radio
    checkbox: "check_box", // Icon untuk field checkbox
    switch: "split_scene_right", // Icon untuk field switch
    file: "attach_file", // Icon untuk field file
    range: "tune", // Icon untuk field range
    color: "palette", // Icon untuk field color
    flag: "flag", // Icon untuk field flag
    currency: "attach_money", // Icon untuk field currency
    approval: "approval", // Icon untuk field currency
    slug:"dataset_linked",
    keyup:"action_key",
    tags:"tag",
    instansi:'business',
    maps:'distance'
  };
 
  // Kembalikan icon sesuai tipe, atau icon default jika tipe tidak ditemukan
  return iconMap[type] || "help_outline";
}
