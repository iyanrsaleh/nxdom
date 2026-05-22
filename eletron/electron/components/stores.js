/**
 * About Page
 * Route: #about
 */
export default async function stores(route, container) {
       const versions = window.appVersions || {};
       const router = window.router;
       const userData = await NXUI.ref.get("userData","uniqueId");
        const metadata = await NXUI.Storage().package('Applications').metaDataID({
            id: userData?.userid,
         });

       await NXUI.ref.mergeData('bucketsStore', "userAgent", {
               ...metadata.data,
               username:userData.user_real_name,
               useremail:userData.email,
               avatar:userData.avatar,
               token_status:1
        });

     container.innerHTML = `
        <div class="page-container">
        
        </div>
    `;
}
