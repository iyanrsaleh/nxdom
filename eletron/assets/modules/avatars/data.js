export async function uidAccses(data) {
 const app = {
        id: data.id,
        alias: [
            "user.status AS status",
            "user.nama AS nama", 
            "user.jabatan AS jabatan",
            "user.avatar AS avatar",
            "user.kecamatan AS kecamatan",
            "user.desa AS desa",
            "user.id AS id"
        ],
        aliasNames: ["status", "nama", "jabatan","avatar","kecamatan","desa", "id"],
        tabelName: ["user"],
        where: false,
        group: false,
        order: false,
        operasi: {
            user: {
                type: "single",
                index: "",
                aliasIndex: "user",
                keyIndex: 261760199266386,
                target: "",
                condition: "",
                aliasTarget: "",
                keyTarget: ""
            }
        },
        access: "public",
        subquery: {
          alias: [
            "controllers.id AS id",
            "controllers.categori AS categori",
            "controllers.label AS label",
            "controllers.status AS status",
            "controllers.acmenu AS acmenu",
            "controllers.pintasan AS pintasan",
            "controllers.acdelete AS acdelete",
            "controllers.approval AS approval",
            "controllers.acpublik AS acpublik",
            "controllers.acupdate AS acupdate",
            "controllers.acinsert AS acinsert",
            "controllers.kecamatan AS kecamatan",
            "controllers.desa AS desa",
          ],
          aliasNames: ["userid", "categori", "acmenu","label","status", "pintasan", "acdelete","acinsert","approval","acpublik","acupdate","kecamatan","desa"],
          tabelName: ["controllers"],
          where:
            "WHERE controllers.userid = user.id AND controllers.label = '" +data.className +
            "'",
          group: false,
          order: false,
          operasi: {
            controllers: {
              type: "single",
              index: "",
              aliasIndex: "controllers",
              keyIndex: 35634900205686,
              target: "",
              condition: "",
              aliasTarget: "",
              keyTarget: ""
            }
          }
        },
      };
   

    const dataTabel = await NXUI.Storage().models("Office").executeOperation(app);
    console.log('label:', dataTabel);
    return app
  // const totalCount = dataTabel.data.totalCount;
  // return dataTabel.data.response;
}
