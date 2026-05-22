import { oprasi } from "./oprasi.js";

export async function testing(data) {
  const key = data?.data?.key ?? data?.key;
 
  if (!key) {
    return '<div class="setting-info"><p>nexaStore key belum diset pada tools item ini.</p></div>';
  }
  const Sdk = new NXUI.Buckets(key);
  const ad = await Sdk.storage();
      const checkedItems = await Sdk.retFields("populate");

//   {
//     "key": "nama",
//     "tabel": "230351593630038",
//     "fieldSearch": "nama",
//     "relasiKey": "userid"
// }
    const populateIndex=checkedItems.populate.metadata.index;
    const populateTarget=checkedItems.populate.metadata.targets;
    console.log('populateIndex:', populateIndex);
    console.log('populateTarget:', populateTarget);
    const result = await NXUI.Storage().models('Office').searchPopulate({
      search:"HENDRIK TAUE",
      ...populateIndex,
    });
    console.log('result:', result.data.response);
    const responIndex=result.data.response[0];

    console.log('UNTUK ID VALUE INPUT INDEX');
     console.log(`item fields id #${populateIndex?.key} set value: `,responIndex[populateIndex?.fieldSearch]);
    console.log('UNTUK ID VALUE INPUT TARGET');
    const html = await NXUI.map(populateTarget, (item) => {
      console.log(`item fields id #${item?.key} set value: `,responIndex[item?.fields]);
      // UNTUK VALUE SEND TABEL
    

    });

  
  return `<div id="form-layer" class="nx-row">aaaaaaaaaaa</div>`;

}

