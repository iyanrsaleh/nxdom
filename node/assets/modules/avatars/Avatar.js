export async function uiAvatar(data) {
  const avatarTabel = await NXUI.Storage().models("Office").avatar(data);
  if (data?.avatar=='images') {
   NXUI.id(data.id).html(`<div class="nx-avatar nx-avatar-xs">
  <img src="${NEXA?.urlDrive}/${avatarTabel.data?.avatar ||`${NEXA?.url}/assets/images/pria.png`}" alt="Avatar XS">
</div>`);
  } else if (data?.avatar=='media') {

 const media=`
<div class="nx-media nx-media-center">
  <img style="  width:36px;height:36px;" src="${NEXA?.urlDrive}/${avatarTabel.data?.avatar ||`${NEXA?.url}/assets/images/pria.png`}" class="nx-media-img" alt="Media">
  <div class="nx-media-body">
    <h4 class="bold">${avatarTabel.data?.nama || 'Noname'}</h4>
     <h4>${avatarTabel.data?.email || 'Developer'}</h4>
  </div>
</div>
`;
   NXUI.id(data.id).html(media);
  } else {
    NXUI.id(data.id).textContent = avatarTabel.data?.nama || 'Noname';
  }
}
