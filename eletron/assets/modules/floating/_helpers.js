// _helpers.js — Shared utility functions for NexaFloating modules

/**
 * Convert a File object to a binary array (Uint8Array as plain Array).
 * Used when serialising file uploads for IPC / storage transmission.
 */
export const fileToBinaryArray = (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const arrayBuffer = reader.result;
      const uint8Array = new Uint8Array(arrayBuffer);
      const binaryArray = Array.from(uint8Array);
      resolve(binaryArray);
    };
    reader.onerror = () => reject(reader.error);
    reader.readAsArrayBuffer(file);
  });
};

/**
 * Generate a collision-resistant unique name string.
 * Pattern: <clean_name>_<time_base36>_<random_base36>
 */
export function generateUniqueName(name = "") {
  const clean = name.trim().toLowerCase().replace(/\s+/g, "_");
  const rand  = Math.random().toString(36).substring(2, 10);
  const time  = Date.now().toString(36);
  return `${clean}_${time}_${rand}`;
}
