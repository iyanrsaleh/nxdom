export async  function allVersion() {
const dataInstall = await new NXUI.NexaModels()
   .Storage("nexa_office")
   .select(["data_key AS version","status","description","created_at"])
   .where("data_type", 'System')
   .orderBy('id', "DESC")
   .get();
   return dataInstall.data;
}
export async function lastVersion() {
const dataInstall = await new NXUI.NexaModels()
   .Storage("nexa_office")
   .select(["data_key AS updateVersion","created_at","description"])
   .where("data_type", 'System')
   .where("status", 'production')
   .orderBy('id', "DESC")
   .first();
   return dataInstall.data;
}
export async  function tabelVersion(version) {
   const data=await allVersion(version);
   return  data;
}

export async function packaceVersion(version) {
    try {
          const dataInstall = await new NXUI.NexaModels()
              .Storage("nexa_office")
              .select(["id","data_key AS version","status","description","created_at"])
              .where("data_type", 'System')
              .where('data_key', version)  // Use the provided version parameter
              .first();
        
        // if (!dataInstall || !dataInstall.data || !dataInstall.data.data_value) {
        //     console.warn(`No data found for version ${version}`);
        //     return [];
        // }
        
        // const dStotage = JSON.parse(dataInstall.data.data_value);
        
        // // Check if the version exists in the parsed data
        // if (!dStotage[version] || !dStotage[version].application) {
        //     console.warn(`No application data found for version ${version}`);
        //     return [];
        // }
        
        
       // const packages = dStotage[version].application;
         await packaceUpdate(dataInstall.data)
         return dataInstall.data;
    } catch (error) {
        console.error('Error in packaceVersion:', error);
        return [];
    }
}

export async function packaceUpdate(row) {
  const query = await new NXUI.NexaModels()
  .table("nexa_office")
  .where("id", "=", row?.id)
  .update({
    status: "production",
   });
}