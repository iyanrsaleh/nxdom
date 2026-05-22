import { NexaDb } from "./NexaDb.js";
import NexaFirebase, { getFirebaseConfig } from "../Firebase/NexaFirebase.js";

/** Respons konsisten jika `endpoint.firebaseConfig` tidak diisi — SDK Firebase tidak dijalankan. */
function firebaseSkippedResponse(mode = "firebase") {
  return {
    success: false,
    mode,
    response: [],
    count: 0,
    totalCount: 0,
    skipped: true,
    reason:
      "Firebase not configured (set endpoint.firebaseConfig in App.js)",
  };
}

export class NexaFederated {
  constructor(storeData) {
    this.db = new NexaDb(window.NEXA?.userId || 1);
    this.id = storeData.id;
    /** Dipakai saat IndexedDB `nexaStore` belum punya record untuk `id` ini */
    this.storeDataInput = storeData;
    this.store = null;
    this.redStore = null;
    /** Wajib di-await di get/set agar loadStore tidak jalan sebelum db.Ref() (nexaStore ada di IndexedDB) */
    this._initPromise = this.initDatabase();
  }

  /** Tunggu init DB + loadStore; ulangi loadStore jika init gagal sebelum mengisi store */
  async _ensureInit() {
    try {
      await this._initPromise;
    } catch {
      /* initDatabase sudah log error */
    }
    if (!this.store) {
      await this.loadStore();
    }
  }

  /**
   * Memastikan object store `bucketsStore` ada di IndexedDB yang dipakai NXUI.ref.
   * DB bisa sudah dibuka dari init lama (hanya nexaStore) — tanpa ini set/get indexedDB gagal.
   */
  async _ensureBucketsStore() {
    const ref =
      typeof window !== "undefined" && window.NXUI && window.NXUI.ref;
    if (!ref) return;
    try {
      if (typeof ref.hasStore === "function" && ref.hasStore("bucketsStore")) {
        return;
      }
      if (typeof ref.addStores === "function") {
        await ref.addStores(["bucketsStore"]);
      }
    } catch (e) {
      console.warn("NexaFederated: bucketsStore ensure failed:", e);
    }
  }

  async initDatabase() {
    try {
      // Initialize the database using NexaDb instance
      await this.db.initDatabase();
      // Install/ensure all required stores exist
      await this.db.Ref();
      await this._ensureBucketsStore();
      // Load store data after database initialization
      await this.loadStore();
    } catch (error) {
      console.error('Failed to initialize NexaFederated database:', error);
      throw error;
    }
  }
   async loadStore() {
    const applyInputFallback = () => {
      const fb = this.storeDataInput || {};
      const className = fb.className ?? fb.tabelName?.[0];
      this.store = {
        storage: fb.settings?.storage ?? "database",
        callData: null,
        className,
        key: fb.key ?? 279283707314106,
        applications: fb.applications != null ? fb.applications : fb,
      };
      this.redStore = null;
    };
    try {
      const data = await NXUI.ref.get("nexaStore", this.id);
      if (!data) {
        applyInputFallback();
        return;
      }
      this.store = {
        storage: data?.settings?.storage ?? "database",
        callData: data,
        className: data?.className,
        key: data?.key,
        applications: data?.applications,
      };
      this.redStore = data;
    } catch (error) {
      console.error('Failed to load store data:', error);
      applyInputFallback();
    }
   }

  withTerritory(appConfig, territoryData) {
    let whereClause = "";
    const aslis = appConfig.tabelName[0];
    // Check if original WHERE clause exists and is not false
    if (appConfig.where && appConfig.where !== false) {
        whereClause = appConfig.where; // Keep original WHERE clause
    }
    
    // Add territory conditions
    if (territoryData.kecamatan) {
        if (whereClause) {
            whereClause += ` AND ${aslis}.kecamatan = '${territoryData.kecamatan}'`;
        } else {
            whereClause = `${aslis}.kecamatan = '${territoryData.kecamatan}'`;
        }
    }
    
    if (territoryData.desa) {
        if (whereClause) {
            whereClause += ` AND ${aslis}.desa = '${territoryData.desa}'`;
        } else {
            whereClause = `${aslis}.desa = '${territoryData.desa}'`;
        }
    }
    
    // Update the app configuration
    appConfig.where = whereClause;
    appConfig.access = "public"; // Change access to public
    
    return appConfig;
}


    async analysis(natKey) {
        await this._ensureInit();
      const storage = this.redStore;
      const app= storage?.layar[natKey].applications;
      const analysisConfig = storage?.layar[natKey].analysisConfig;
      analysisConfig.showSql=false
      const dataTabel = await NXUI.Storage().models("Office").directAnalysis(app, analysisConfig);
      return dataTabel.data;
    }
    async get(data) {
        await this._ensureInit();
        const dataStorage = this.store.className;
        const typeStorage = this.store.storage;
        const applications = this.store.applications;
        const key = this.store?.key;
        
        // Route berdasarkan tipe storage menggunakan Map (lebih modern)
        const storageHandlers = new Map([
            ['database', () => this.getdatabase(data, applications,dataStorage)],
            ['indexedDB', () => this.getindexedDB(data, applications,key)],
            ['firebase', () => this.getfirebase(data, applications, key)]
        ]);
        
        const handler = storageHandlers.get(typeStorage) || storageHandlers.get('database');
        return await handler();
    }
    async getdatabase(data, applications,className) {
       try {

         const updatedData = {
           ...applications,
           ...data
         };
        const access=NEXA.controllers?.data?.access?.[className]
          if (access) {
              updatedData.access = "public"; // Change access to public
          }
         const territory=NEXA.controllers?.data?.territory?.[className]
         if (territory && (territory.kecamatan || territory.desa)) {
           this.withTerritory(updatedData, territory);
         }
         
          const dataTabel = await NXUI.Storage().models("Office").executeOperation(updatedData);
          // ENHANCED: Handle case when dataTabel or dataTabel.data is undefined
          if (!dataTabel) {
              console.warn('NexaFederated: dataTabel is undefined');
              return {
                  "success": false,
                  "mode": 'database',
                  "response": [],
                  "count": 0,
                  "totalCount": 0
              };
          }
          
          // ENHANCED: Safe access to dataTabel.data with fallback values
          const responseData = dataTabel.data || dataTabel || {};
          
          return {
              "success": true,
              "mode": 'database',
              "response": responseData.response || responseData.data || responseData || [],
              "count": responseData.count || 0,
              "totalCount": responseData.totalCount || responseData.count || 0
          }
       } catch (error) {
           console.error('Database error:', error);
           // ENHANCED: Return safe fallback instead of throwing to prevent app crash
           return {
               "success": false,
               "mode": 'database',
               "response": [],
               "count": 0,
               "totalCount": 0,
               "error": error.message || 'Unknown error'
           };
       }
   }
    // Method untuk realtime data dengan callback
    async getRealtime(data, callback) {
        await this._ensureInit();
        const typeStorage = this.store.storage;
        const applications = this.store.applications;
        const key = this.store?.key;
        
        // Hanya Firebase yang support realtime saat ini
        if (typeStorage === 'firebase') {
            return await this.getfirebaseRealtime(data, applications, key, callback);
        } else {
            // Untuk storage lain, fallback ke polling
            console.warn(`Realtime not supported for ${typeStorage}, using polling instead`);
            
            // Setup polling untuk storage lain
            const pollInterval = setInterval(async () => {
                try {
                    const result = await this.get(data);
                    if (callback && typeof callback === 'function') {
                        callback({
                            ...result,
                            mode: `${result.mode}-polling`,
                            changeType: 'polling-update',
                            timestamp: new Date().toISOString()
                        });
                    }
                } catch (error) {
                    console.error('Polling error:', error);
                }
            }, 5000); // Poll setiap 5 detik
            
            // Return function untuk stop polling
            return () => {
                clearInterval(pollInterval);
            };
        }
    }

   async getindexedDB(data, applications, key) {
       try {
          await this._ensureBucketsStore();
          const dataTabel = await NXUI.ref.get("bucketsStore", key);
          let response = [];
          
          if (dataTabel && dataTabel.response) {
              response = Array.isArray(dataTabel.response) ? dataTabel.response : [dataTabel.response];
              
           // Apply simple SQL-like filters
           if (data.order) {
               const match = data.order.match(/ORDER BY\s+(\w+(?:\.\w+)?)\s*(DESC|ASC)?/i);
               if (match) {
                   let field = match[1];
                   // Handle dot notation like "demo.id" -> "id"
                   if (field.includes('.')) {
                       field = field.split('.').pop();
                   }
                   const isDesc = match[2] && match[2].toUpperCase() === 'DESC';
                   response.sort((a, b) => {
                       const aVal = a[field];
                       const bVal = b[field];
                       // Convert to numbers if both are numeric
                       const aNum = parseFloat(aVal);
                       const bNum = parseFloat(bVal);
                       if (!isNaN(aNum) && !isNaN(bNum)) {
                           return isDesc ? bNum - aNum : aNum - bNum;
                       }
                       // String comparison
                       if (aVal < bVal) return isDesc ? 1 : -1;
                       if (aVal > bVal) return isDesc ? -1 : 1;
                       return 0;
                   });
               }
           }
              
              if (data.offset > 0) {
                  response = response.slice(data.offset);
              }
              
              if (data.limit > 0) {
                  response = response.slice(0, data.limit);
              }
          }
          
          return {
              "success": true,
              "mode": 'IndexedDB',
              "response": response,
              "count": response.length,
              "totalCount": dataTabel?.totalCount || 0
          };
       } catch (error) {
           console.error('IndexedDB error:', error);
           throw error;
       }
   }
   async getfirebase(data, applications, key) {
       try {
           if (!getFirebaseConfig()) {
               return firebaseSkippedResponse("firebase");
           }
           const firebaseStorage = await NexaFirebase();
           if (!firebaseStorage) {
               return firebaseSkippedResponse("firebase");
           }
           
           // Get data from Firebase
           const dataTabel = await firebaseStorage.get("bucketsStore", key);
           let response = [];
           
           if (dataTabel && dataTabel.response) {
               response = Array.isArray(dataTabel.response) ? dataTabel.response : [dataTabel.response];
               
           // Apply simple SQL-like filters
           if (data.order) {
               const match = data.order.match(/ORDER BY\s+(\w+(?:\.\w+)?)\s*(DESC|ASC)?/i);
               if (match) {
                   let field = match[1];
                   // Handle dot notation like "demo.id" -> "id"
                   if (field.includes('.')) {
                       field = field.split('.').pop();
                   }
                   const isDesc = match[2] && match[2].toUpperCase() === 'DESC';
                   response.sort((a, b) => {
                       const aVal = a[field];
                       const bVal = b[field];
                       // Convert to numbers if both are numeric
                       const aNum = parseFloat(aVal);
                       const bNum = parseFloat(bVal);
                       if (!isNaN(aNum) && !isNaN(bNum)) {
                           return isDesc ? bNum - aNum : aNum - bNum;
                       }
                       // String comparison
                       if (aVal < bVal) return isDesc ? 1 : -1;
                       if (aVal > bVal) return isDesc ? -1 : 1;
                       return 0;
                   });
               }
           }
               
               if (data.offset > 0) {
                   response = response.slice(data.offset);
               }
               
               if (data.limit > 0) {
                   response = response.slice(0, data.limit);
               }
           }
           
           return {
               "success": true,
               "mode": 'firebase',
               "response": response,
               "count": response.length,
               "totalCount": dataTabel?.totalCount || 0
           };
       } catch (error) {
           console.error('Firebase error:', error);
           throw error;
       }
   }

   // Method baru untuk realtime Firebase data
   async getfirebaseRealtime(data, applications, key, callback) {
       try {
           if (!getFirebaseConfig()) {
               return () => {};
           }
           const firebaseStorage = await NexaFirebase();
           if (!firebaseStorage) {
               return () => {};
           }
           
           // Samakan bentuk payload dengan getfirebase/get(): setiap event baca ulang dokumen
           // (watch mengirim daftar dokumen; field id baris bisa menimpa id kunci — jangan parse manual).
           const unsubscribe = firebaseStorage.watch("bucketsStore", async (event) => {
               try {
                   const snapshot = await this.getfirebase(data, applications, key);
                   if (callback && typeof callback === 'function') {
                       callback({
                           ...snapshot,
                           mode: 'firebase-realtime',
                           changeType: event.changeType,
                           timestamp: event.timestamp,
                           watchId: event.watchId,
                       });
                   }
               } catch (err) {
                   console.error('Firebase realtime refresh:', err);
               }
           });
           
           return unsubscribe;
           
       } catch (error) {
           console.error('Firebase realtime error:', error);
           throw error;
       }
   }
   

   // Helper method untuk stop real-time listener
   stopRealtime(unsubscribeFunction) {
       if (typeof unsubscribeFunction === 'function') {
           unsubscribeFunction();
           return true;
       }
       return false;
   }
   // Set Data Peyimpanan data 
   async set(data,hasFile) {
        await this._ensureInit();
        const typeStorage = this.store.storage;
        const key = this.store?.key;
        // Route berdasarkan tipe storage menggunakan Map (lebih modern)
        const storageHandlers = new Map([
            ['database', () => this.setdatabase(data, hasFile,key)],
            ['indexedDB', () => this.setindexedDB(data, hasFile,key)],
            ['firebase', () => this.setfirebase(data, hasFile,key)]
        ]);
        
        const handler = storageHandlers.get(typeStorage) || storageHandlers.get('database');
        return await handler();
    }
    async setdatabase(data, hasFile,key) {
        const app = this.store.callData?.app;
        if (app === "Cross") {
          console.warn(
            "[NexaFederated] Cross multi-table insert disabled: legacy Office.buckInsert removed. Wire new batch API when ready."
          );
          return false;
        } else {
            console.log(key, key, data, hasFile);
           const res = await NXUI.Storage()
           .models("Office")
           .setRetInsert(key, key, data, hasFile);
           console.log('res:', res);
           return res.success; 

        }

    }

    async setindexedDB(data, hasFile,key) {
           await this._ensureBucketsStore();
           const dataTabel = await NXUI.ref.get("bucketsStore",key);
           const currentTotalCount = dataTabel?.totalCount || 0;
           const dataWithId = {
               "id": currentTotalCount + 1,
               ...data
           };
           
           const existingResponse = dataTabel?.response || [];
           
           const updatedResponse = Array.isArray(existingResponse) 
               ? [...existingResponse, dataWithId]  // Add to existing array
               : existingResponse ? [existingResponse, dataWithId] : [dataWithId];    // Handle empty or single item
           

           const makeDir = {
                         "success": true,
                         "mode": 'indexedDB',
                         "response": updatedResponse,
                         "count": updatedResponse.length,
                         "totalCount": currentTotalCount + 1
                  };

           // Check if data exists, use set for new data or mergeData for existing
           if (dataTabel && dataTabel.response) {
               // Data exists, use mergeData to preserve structure
               await NXUI.ref.mergeData("bucketsStore", key, makeDir);
           } else {
               // No existing data, use set to create new record
               await NXUI.ref.set("bucketsStore", {
                   id: key,
                   ...makeDir
               });
           }
           return makeDir;
    }

    async setfirebase(data, hasFile, key) {
        try {
            if (!getFirebaseConfig()) {
                return firebaseSkippedResponse("firebase");
            }
            const firebaseStorage = await NexaFirebase();
            if (!firebaseStorage) {
                return firebaseSkippedResponse("firebase");
            }
            
            // Get existing data from Firebase to determine current totalCount
            const dataTabel = await firebaseStorage.get("bucketsStore", key);
            // Add id based on totalCount + 1
            const currentTotalCount = dataTabel?.totalCount || 0;
            const dataWithId = {
                "id": currentTotalCount + 1,
                ...data
            };
            
            // Get existing response array and add new data
            const existingResponse = dataTabel?.response || [];
            
            const updatedResponse = Array.isArray(existingResponse) 
                ? [...existingResponse, dataWithId]  // Add to existing array
                : existingResponse ? [existingResponse, dataWithId] : [dataWithId];    // Handle empty or single item
            

            const makeDir = {
                "success": true,
                "mode": 'firebase',
                "response": updatedResponse,
                "count": updatedResponse.length,
                "totalCount": currentTotalCount + 1
            };

            // Check if data exists, handle accordingly (same logic as setindexedDB)
            if (dataTabel && dataTabel.response) {
                // Data exists, create Firebase record structure for update (flat structure)
                const firebaseRecord = {
                    id: key,
                    createdAt: dataTabel?.createdAt || new Date().toISOString(),
                    updatedAt: new Date().toISOString(),
                    hasFileType: hasFile || false,
                    response: updatedResponse,  // Direct response array
                    count: updatedResponse.length,
                    totalCount: currentTotalCount + 1,
                    insetLast: {
                        timestamp: new Date().toISOString(),
                        action: "update",
                        dataId: currentTotalCount + 1
                    }
                };
                await firebaseStorage.set("bucketsStore", firebaseRecord);
            } else {
                // No existing data, create new record (flat structure)
                const firebaseRecord = {
                    id: key,
                    createdAt: new Date().toISOString(),
                    updatedAt: new Date().toISOString(),
                    hasFileType: hasFile || false,
                    response: updatedResponse,  // Direct response array
                    count: updatedResponse.length,
                    totalCount: currentTotalCount + 1,
                    insetLast: {
                        timestamp: new Date().toISOString(),
                        action: "insert",
                        dataId: currentTotalCount + 1
                    }
                };
                await firebaseStorage.set("bucketsStore", firebaseRecord);
            }

            // Return the same structure as setindexedDB
            return makeDir;

        } catch (error) {
            console.error('Firebase set error:', error);
            throw error;
        }
    }
   // Set Data
   async del(data) {
        await this._ensureInit();
        const typeStorage = this.store.storage;
        const applications = this.store.applications;
        const key = this.store?.key;
        
        // Route berdasarkan tipe storage
        const storageHandlers = new Map([
            ['database', () => this.deldatabase(data, applications,key)],
            ['indexedDB', () => this.delindexedDB(data, applications, key)],
            ['firebase', () => this.delfirebase(data, applications, key)]
        ]);
        
        const handler = storageHandlers.get(typeStorage) || storageHandlers.get('database');
        return await handler();
   }

   async deldatabase(data, applications,key) {
       try {
          const app = this.store.callData.app;
          if (app === "Cross") {
            console.warn(
              "[NexaFederated] Cross multi-table delete disabled: legacy Office.buckDelete removed. Wire new batch API when ready."
            );
            return { success: false, error: "cross_batch_delete_unconfigured" };
          } else {
            const result = await NXUI.Storage()
              .models("Office")
              .setRettDelete(key, key, data.id);
            return result;
          }
       } catch (error) {
           console.error('Database delete error:', error);
           throw error;
       }
   }

   async delindexedDB(data, applications, key) {
       try {
           await this._ensureBucketsStore();
           const dataTabel = await NXUI.ref.get("bucketsStore", key);
           
           // Get existing response array
           const existingResponse = dataTabel?.response || [];
           
           // Find and remove item by ID
           const itemId = data.id;
           if (!itemId) {
               throw new Error('ID is required for delete operation');
           }
           
           // Convert itemId to number for comparison
           const deleteId = parseInt(itemId);
           
           const updatedResponse = Array.isArray(existingResponse) 
               ? existingResponse.filter(item => {
                   const itemIdNum = parseInt(item.id);
                   return itemIdNum !== deleteId;
               })
               : [];
           
           
           const makeDir = {
               "success": true,
               "mode": 'indexedDB',
               "response": updatedResponse,
               "count": updatedResponse.length,
               "totalCount": updatedResponse.length // Update totalCount to match actual data count
           };
           
           
           // Update IndexedDB
           await NXUI.ref.mergeData("bucketsStore", key, makeDir);
           
           return makeDir;
           
       } catch (error) {
           console.error('IndexedDB delete error:', error);
           throw error;
       }
   }

   async delfirebase(data, applications, key) {
       try {
           if (!getFirebaseConfig()) {
               return firebaseSkippedResponse("firebase");
           }
           const firebaseStorage = await NexaFirebase();
           if (!firebaseStorage) {
               return firebaseSkippedResponse("firebase");
           }
           
           // Get existing data from Firebase
           const dataTabel = await firebaseStorage.get("bucketsStore", key);
           
           // Get existing response array from flat structure
           const existingResponse = dataTabel?.response || [];
           
           // Find and remove item by ID
           const itemId = data.id;
           if (!itemId) {
               throw new Error('ID is required for delete operation');
           }
           
           // Convert itemId to number for comparison
           const deleteId = parseInt(itemId);
           
           const updatedResponse = Array.isArray(existingResponse) 
               ? existingResponse.filter(item => {
                   const itemIdNum = parseInt(item.id);
                   return itemIdNum !== deleteId;
               })
               : [];
           
           
           const makeDir = {
               "success": true,
               "mode": 'firebase',
               "response": updatedResponse,
               "count": updatedResponse.length,
               "totalCount": updatedResponse.length // Update totalCount to match actual data count
           };
           
           
           // Create Firebase record structure (flat)
           const firebaseRecord = {
               id: key,
               createdAt: dataTabel?.createdAt || new Date().toISOString(),
               updatedAt: new Date().toISOString(),
               hasFileType: dataTabel?.hasFileType || false,
               response: updatedResponse,  // Direct response array
               count: updatedResponse.length,
               totalCount: updatedResponse.length, // Update totalCount to match actual data count
               insetLast: {
                   timestamp: new Date().toISOString(),
                   action: "delete",
                   dataId: itemId
               }
           };
           
           
           // Save to Firebase
           await firebaseStorage.set("bucketsStore", firebaseRecord);
           
           return makeDir;
           
       } catch (error) {
           console.error('Firebase delete error:', error);
           throw error;
       }
   }

   async upt(data,id,hasFileType) {
        await this._ensureInit();
        const typeStorage = this.store.storage;
        const applications = this.store.applications;
        const key = this.store?.key;
        
        // Route berdasarkan tipe storage
        const storageHandlers = new Map([
            ['database', () => this.uptdatabase(data, id,key,hasFileType)],
            ['indexedDB', () => this.uptindexedDB(data, id, key,hasFileType)],
            ['firebase', () => this.uptfirebase(data, id, key,hasFileType)]
        ]);
        
        const handler = storageHandlers.get(typeStorage) || storageHandlers.get('database');
        return await handler();
   }


   async uptdatabase(data, recordId,key,hasFileType=null) {
       try {



        const app = this.store.callData.app;
        if (app === "Cross") {
          console.warn(
            "[NexaFederated] Cross multi-table update disabled: legacy Office.buckUpdate removed. Wire new batch API when ready."
          );
          return false;
        } else {
             const response = await NXUI.Storage()
              .models("Office")
              .setRetUpdate(
                key,
                key,
                data,
                recordId,
                hasFileType
              );
              return response;

        }
      
       } catch (error) {
           console.error('Database update error:', error);
           throw error;
       }
   }

   async uptindexedDB(data, id, key) {

       try {
           await this._ensureBucketsStore();
           const dataTabel = await NXUI.ref.get("bucketsStore", key);
           
           // Get existing response array
           const existingResponse = dataTabel?.response || [];
           
           // Find and update item by ID
           const itemId = id; // Use the separate id parameter
           if (!itemId) {
               throw new Error('ID is required for update operation');
           }
           
           // Convert itemId to number for comparison
           const updateId = parseInt(itemId);
           
           const updatedResponse = Array.isArray(existingResponse) 
               ? existingResponse.map(item => {
                   const itemIdNum = parseInt(item.id);
                   return itemIdNum === updateId 
                       ? { ...item, ...data, id: updateId, updatedAt: new Date().toISOString() }
                       : item;
                 })
               : [];
           
           // Check if item was found and updated
           const itemFound = existingResponse.some(item => parseInt(item.id) === updateId);
           if (!itemFound) {
               throw new Error(`Item with ID ${updateId} not found`);
           }
           
           
           const makeDir = {
               "success": true,
               "mode": 'indexedDB',
               "response": updatedResponse,
               "count": updatedResponse.length,
               "totalCount": updatedResponse.length // Update totalCount to match actual data count
           };
           
           
           // Update IndexedDB
           await NXUI.ref.mergeData("bucketsStore", key, makeDir);
           
           return makeDir;
           
       } catch (error) {
           console.error('IndexedDB update error:', error);
           throw error;
       }
   }

   async uptfirebase(data, id, key) {
       try {
           if (!getFirebaseConfig()) {
               return firebaseSkippedResponse("firebase");
           }
           const firebaseStorage = await NexaFirebase();
           if (!firebaseStorage) {
               return firebaseSkippedResponse("firebase");
           }
           
           // Get existing data from Firebase
           const dataTabel = await firebaseStorage.get("bucketsStore", key);
           
           // Get existing response array from flat structure
           const existingResponse = dataTabel?.response || [];
           
           // Find and update item by ID
           const itemId = id; // Use the separate id parameter
           if (!itemId) {
               throw new Error('ID is required for update operation');
           }
           
           // Convert itemId to number for comparison
           const updateId = parseInt(itemId);
           
           const updatedResponse = Array.isArray(existingResponse) 
               ? existingResponse.map(item => {
                   const itemIdNum = parseInt(item.id);
                   return itemIdNum === updateId 
                       ? { ...item, ...data, id: updateId, updatedAt: new Date().toISOString() }
                       : item;
                 })
               : [];
           
           // Check if item was found and updated
           const itemFound = existingResponse.some(item => parseInt(item.id) === updateId);
           if (!itemFound) {
               throw new Error(`Item with ID ${updateId} not found`);
           }
           
           
           const makeDir = {
               "success": true,
               "mode": 'firebase',
               "response": updatedResponse,
               "count": updatedResponse.length,
               "totalCount": updatedResponse.length // Update totalCount to match actual data count
           };
           
           
           // Create Firebase record structure (flat)
           const firebaseRecord = {
               id: key,
               createdAt: dataTabel?.createdAt || new Date().toISOString(),
               updatedAt: new Date().toISOString(),
               hasFileType: dataTabel?.hasFileType || false,
               response: updatedResponse,  // Direct response array
               count: updatedResponse.length,
               totalCount: dataTabel?.totalCount || 0,
               insetLast: {
                   timestamp: new Date().toISOString(),
                   action: "update",
                   dataId: itemId
               }
           };
           
           
           // Save to Firebase
           await firebaseStorage.set("bucketsStore", firebaseRecord);
           
           return makeDir;
           
       } catch (error) {
           console.error('Firebase update error:', error);
           throw error;
       }
   }
} 

