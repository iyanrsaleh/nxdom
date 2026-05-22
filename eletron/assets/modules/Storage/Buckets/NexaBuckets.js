import { setApplications } from "./NexaBucketsKey.js";
export class Buckets {
  constructor(store) {
    this.store = store;
  }
  metaData() {
    const tabelJoin = NEXA.tabel?.submenu || [];
    return tabelJoin;
  }

  // Variabel From
  async storage() {
    try {
      // Validasi this.store sebelum memanggil get
      if (!this.store || this.store === undefined || this.store === null || this.store === "") {
       // console.error("❌ storage() error: this.store tidak valid:", this.store);
        return null;
      }

      const dataform = await NXUI.ref.get("nexaStore", this.store);
      
      // Jika dataform null (tidak ditemukan), return null dengan informasi yang jelas
      if (!dataform) {
       // console.warn(`⚠️ Data dengan ID "${this.store}" tidak ditemukan di nexaStore`);
        return null;
      }

      return dataform;
    } catch (error) {
      // console.error("❌ storage() error:", error);
      // console.error("Store ID:", this.store);
      return null;
    }
  }
  async form() {
    try {
      const dataform = await NXUI.ref.get("nexaStore", this.store);
       return dataform.production.from;
    } catch (error) {
      console.error("❌ Auto mode initialization failed:", error);
    }
  }

  async upBucket() {
    const dataform = await NXUI.ref.get("nexaStore", this.store);
    await setApplications(dataform.id)
  }

  async upNested(Fields,form) {
    try {
      // Validasi this.store
      if (!this.store || this.store === undefined || this.store === null || this.store === "") {
        const error = new Error("Store ID tidak valid atau kosong");
        console.error("❌ upField error:", error.message);
        return {
          status: false,
          error: error.message,
          updatedFields: Fields,
        };
      }

      // Get data from storage
      const dataform = await NXUI.ref.get("nexaStore", this.store);

      // Validasi dataform
      if (!dataform) {
        const error = new Error(`Data dengan ID "${this.store}" tidak ditemukan di nexaStore`);
        console.error("❌ upField error:", error.message);
        return {
          status: false,
          error: error.message,
          updatedFields: Fields,
        };
      }

      // Validasi dataform.id
      if (!dataform.id || dataform.id === undefined || dataform.id === null || dataform.id === "") {
        const error = new Error("dataform.id tidak valid atau kosong");
        console.error("❌ upField error:", error.message);
        return {
          status: false,
          error: error.message,
          updatedFields: Fields,
        };
      }

      // Validasi dataform.production[form]
      if (!dataform.production?.[form] || typeof dataform.production[form] !== 'object') {
        const error = new Error(`dataform.production.${form} tidak valid atau tidak ada`);
        console.error("❌ upNested error:", error.message);
        return {
          status: false,
          error: error.message,
          updatedFields: Fields,
        };
      }

      // Validasi Fields
      if (!Fields || typeof Fields !== 'object' || Object.keys(Fields).length === 0) {
        const error = new Error("Fields parameter tidak valid atau kosong");
        console.error("❌ upNested error:", error.message);
        return {
          status: false,
          error: error.message,
          updatedFields: Fields,
        };
      }

      // Update fields
      Object.keys(Fields).forEach((fieldName) => {
        if (dataform.production[form][fieldName]) {
          Object.assign(dataform.production[form][fieldName], Fields[fieldName]);
        } else {
          console.warn(`⚠️ Field "${fieldName}" tidak ditemukan di dataform.production.${form}`);
        }
      });

      // Save the updated data
      await NXUI.ref.mergeData("nexaStore", this.store, dataform);

      return {
        status: true,
        updatedFields: Fields,
      };
    } catch (error) {
      console.error("❌ upField error:", error);
      return {
        status: false,
        error: error.message,
        updatedFields: Fields,
      };
    }
  }


  async upField(Fields) {
    try {
      // Validasi this.store
      if (!this.store || this.store === undefined || this.store === null || this.store === "") {
        const error = new Error("Store ID tidak valid atau kosong");
        console.error("❌ upField error:", error.message);
        return {
          status: false,
          error: error.message,
          updatedFields: Fields,
        };
      }

      // Get data from storage
      const dataform = await NXUI.ref.get("nexaStore", this.store);

      // Validasi dataform
      if (!dataform) {
        const error = new Error(`Data dengan ID "${this.store}" tidak ditemukan di nexaStore`);
        console.error("❌ upField error:", error.message);
        return {
          status: false,
          error: error.message,
          updatedFields: Fields,
        };
      }

      // Validasi dataform.id
      if (!dataform.id || dataform.id === undefined || dataform.id === null || dataform.id === "") {
        const error = new Error("dataform.id tidak valid atau kosong");
        console.error("❌ upField error:", error.message);
        return {
          status: false,
          error: error.message,
          updatedFields: Fields,
        };
      }

      // Validasi dataform.production.from
      if (!dataform.production?.from || typeof dataform.production.from !== 'object') {
        const error = new Error("dataform.production.from tidak valid atau tidak ada");
        console.error("❌ upField error:", error.message);
        return {
          status: false,
          error: error.message,
          updatedFields: Fields,
        };
      }

      // Validasi Fields
      if (!Fields || typeof Fields !== 'object' || Object.keys(Fields).length === 0) {
        const error = new Error("Fields parameter tidak valid atau kosong");
        console.error("❌ upField error:", error.message);
        return {
          status: false,
          error: error.message,
          updatedFields: Fields,
        };
      }

      // Update fields
      Object.keys(Fields).forEach((fieldName) => {
        if (dataform.production.from[fieldName]) {
          Object.assign(dataform.production.from[fieldName], Fields[fieldName]);
        } else {
          console.warn(`⚠️ Field "${fieldName}" tidak ditemukan di dataform.production.from`);
        }
      });

      // Save the updated data
      await NXUI.ref.mergeData("nexaStore", this.store, dataform);

      return {
        status: true,
        updatedFields: Fields,
      };
    } catch (error) {
      console.error("❌ upField error:", error);
      return {
        status: false,
        error: error.message,
        updatedFields: Fields,
      };
    }
  }


  async searchKeys(pattern) {
     const dataform = await NXUI.ref.get("nexaStore", this.store);
    return dataform.buckets.variablesAlias.find((item) => item.includes(pattern));
  }

  async searchKeysOrigin(pattern) {
     const dataform = await NXUI.ref.get("nexaStore", this.store);
    return dataform.variablesOrigin.find((item) => item.includes(pattern));
  }


  async upSettings(Fields) {
    try {
      const dataform = await NXUI.ref.get("nexaStore", this.store);

      // Initialize settings if it doesn't exist
      if (!dataform.settings) {
        dataform.settings = {};
      }

      // Update each field in settings
      Object.keys(Fields).forEach((fieldName) => {
        dataform.settings[fieldName] = Fields[fieldName];
      });

      // Save the updated data
      await NXUI.ref.mergeData("nexaStore", this.store, dataform);

      return {
        status: true,
        updatedFields: dataform.settings,
      };
    } catch (error) {
      return {
        status: false,
        error: error.message,
        updatedFields: Fields,
      };
    }
  }




  async upIndex(Fields) {
    try {
      let dataform = await NXUI.ref.get("nexaStore", this.store);

      // Initialize dataform if it doesn't exist
      if (!dataform) {
        dataform = {
          store: this.store,
          id: this.store,
        };
      }

      // Pastikan store & id selalu ada (data lama mungkin tidak punya field ini)
      if (!dataform.store) dataform.store = this.store;
      if (!dataform.id) dataform.id = this.store;

      // Deep merge function to handle nested objects
      const deepMerge = (target, source) => {
        for (const key in source) {
          if (
            source[key] &&
            typeof source[key] === "object" &&
            !Array.isArray(source[key])
          ) {
            if (!target[key] || typeof target[key] !== "object") {
              target[key] = {};
            }
            deepMerge(target[key], source[key]);
          } else {
            target[key] = source[key];
          }
        }
        return target;
      };

      // Update each field in dataform with deep merge
      Object.keys(Fields).forEach((fieldName) => {
        if (
          Fields[fieldName] &&
          typeof Fields[fieldName] === "object" &&
          !Array.isArray(Fields[fieldName])
        ) {
          // For nested objects, use deep merge
          if (!dataform[fieldName]) {
            dataform[fieldName] = {};
          }
          deepMerge(dataform[fieldName], Fields[fieldName]);
        } else {
          // For primitive values or arrays, direct assignment
          dataform[fieldName] = Fields[fieldName];
        }
      });

      // Save the updated data
      await NXUI.ref.mergeData("nexaStore", this.store, dataform);

      return {
        status: true,
        updatedFields: dataform,
      };
    } catch (error) {
      return {
        status: false,
        error: error.message,
        updatedFields: Fields,
      };
    }
  }

async getFields(condition = "condition") {
   const formData = await this.form();
   if (!formData) return false;
   const files = Object.entries(formData)
     .filter(([_, field]) => field[condition])
     .map(([key, field]) => field);
   return files.length > 0 ? files : false;
}

async retFields(condition = "condition") {
   const formData = await this.form();
   if (!formData) return false;
   const files = Object.entries(formData)
     .filter(([_, field]) => field[condition])
     .map(([key, field]) => field);
   return files.length > 0 ? files[0] : false;
}





  async Fields() {
    const formData = await this.form();
    if (!formData) return false;
    const files = Object.entries(formData)
      .filter(([_, field]) => field) // cari field dengan modal true
      .map(([key, field]) => {
        return field;
      });
// console.log('files:', files);
    return files.length > 0 ? files : false;
  }






  async metaField(id, tabel) {
    try {
      const Field = await NXUI.Storage()
        .models("Office")
        .tabelVariables(id, tabel);
      return Field;
    } catch (error) {
      console.error("❌ Auto mode initialization failed:", error);
      // Return a default structure to prevent undefined errors
      return {
        data: {
          [tabel]: {
            variables: [],
            table_name: tabel,
          },
        },
      };
    }
  }


  async metaFieldType(id, tabel) {
    try {
      const Field = await NXUI.Storage()
        .models("Office")
        .tabelVariablesType(id, tabel);
      return Field;
    } catch (error) {
      console.error("❌ Auto mode initialization failed:", error);
      // Return a default structure to prevent undefined errors
      return {
        data: {
          [tabel]: {
            variables: [],
            table_name: tabel,
          },
        },
      };
    }
  }






  metaIndex(name) {
    const tabelJoin = NEXA.tabel?.submenu || [];
    const selectedMetadata = tabelJoin.find((item) => item.label === name);
    return selectedMetadata;
  }
  metaKeyName(key) {
    const tabelJoin = NEXA.tabel?.submenu || [];
    const selectedMetadata = tabelJoin.find((item) => item.key === key);
    return selectedMetadata;
  } 

 async metaIndexKey(name) {
    const tabelJoin = NEXA.tabel?.submenu || [];
    const red = tabelJoin.find((item) => item.key === name);

    if (!red) {
      console.error(`❌ metaIndexKey: No item found with key '${name}'`);
      console.log(
        "Available keys:",
        tabelJoin.map((item) => item.key)
      );
      return null;
    }

    const data = await this.metaField(red.key, red.label);
    return data.data[red.label];
  }


  async metaIndexKeyType(name) {
    const tabelJoin = NEXA.tabel?.submenu || [];
    const red = tabelJoin.find((item) => item.key === name);

    if (!red) {
      console.error(`❌ metaIndexKey: No item found with key '${name}'`);
      console.log(
        "Available keys:",
        tabelJoin.map((item) => item.key)
      );
      return null;
    }

    const data = await this.metaFieldType(red.key, red.label);
    return data.data[red.label];
  }
}
