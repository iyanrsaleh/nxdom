export async function setApplications(id) {
  // Kirim data lengkap ke server untuk diproses
  let data = null;
  try {

const Sdk = new NXUI.Buckets(id);
data = await Sdk.storage();

// Pastikan data.applications ada
if (!data.applications) {
  data.applications = {};
}

// Ambil data fields dan items yang sudah dicek
let checkedItems = [];
try {
  checkedItems = await Sdk.getFields("tabel") || [];
} catch (error) {
  checkedItems = [];
}
// Validasi: pastikan checkedItems berisi array dan tidak kosong
if (Array.isArray(checkedItems) && checkedItems.length > 0) {
  // Ambil hanya properti failed dan failedAs
  const result = checkedItems.map(item => ({
    failed: item.name,
    failedAs: item.failedAs
  }));
  // Ganti alias dan aliasNames di storage.applications
  data.applications.alias = result.map(item => item.failedAs);
  data.applications.aliasNames = result.map(item => item.failed);
}


  

      const Alias = data.applications.alias || [];
      const aggregateType = data.aggregateType;
      const arithmetic = data.arithmetic;
      const finalAlias = mergeAliasWithTransformations(
        Alias,
        arithmetic,
        aggregateType
      );

      data.applications.alias = finalAlias;
        await Sdk.upIndex({

          applications:data.applications || false,
 
        });




        
  } catch (error) {
    console.error("Error:", error);
    return { error: error.message, data: data };
  }
}
export function mergeAliasWithTransformations(
  alias,
  arithmetic,
  aggregateType
) {
  if (!alias || !Array.isArray(alias)) {
    return [];
  }

  // Create a copy of the original alias array
  let mergedAlias = [...alias];

  // Handle arithmetic as object
  if (
    arithmetic &&
    !Array.isArray(arithmetic) &&
    arithmetic.field &&
    arithmetic.alias
  ) {
    const arithmeticField = arithmetic.field;

    // Find and replace the matching field in alias array
    const arithmeticIndex = mergedAlias.findIndex((item) => {
      // Extract field name from "field AS alias" format
      const fieldMatch = item.match(/^([^\s]+)/);
      if (fieldMatch) {
        const fieldName = fieldMatch[1];
        // Check if the field matches exactly
        if (fieldName === arithmeticField) {
          return true;
        }
        // Check if the field matches by alias
        const aliasMatch = item.match(/AS\s+([^\s]+)$/);
        if (aliasMatch) {
          const aliasName = aliasMatch[1];
          const fieldBaseName = arithmeticField.split('.').pop();
          return aliasName === fieldBaseName;
        }
      }
      return false;
    });

    if (arithmeticIndex !== -1) {
      // Extract the original field name from the matched item
      const matchedItem = mergedAlias[arithmeticIndex];
      const originalFieldMatch = matchedItem.match(/^([^\s]+)/);
      if (originalFieldMatch) {
        const originalField = originalFieldMatch[1];
        // Replace the alias with the arithmetic function using the original field name
        const newAlias = arithmetic.alias.replace(arithmeticField, originalField);
        mergedAlias[arithmeticIndex] = newAlias;
      } else {
        mergedAlias[arithmeticIndex] = arithmetic.alias;
      }
    }
  }

  // Handle arithmetic as array (similar to aggregateType)
  if (arithmetic && Array.isArray(arithmetic)) {
    arithmetic.forEach((arith) => {
      if (arith.field && arith.alias) {
        const arithmeticField = arith.field;

        // Find and replace the matching field in alias array
        const arithmeticIndex = mergedAlias.findIndex((item) => {
          // Extract field name from "field AS alias" format
          const fieldMatch = item.match(/^([^\s]+)/);
          if (fieldMatch) {
            const fieldName = fieldMatch[1];
            // Check if the field matches exactly
            if (fieldName === arithmeticField) {
              return true;
            }
            // Check if the field matches by alias
            const aliasMatch = item.match(/AS\s+([^\s]+)$/);
            if (aliasMatch) {
              const aliasName = aliasMatch[1];
              const fieldBaseName = arithmeticField.split('.').pop();
              return aliasName === fieldBaseName;
            }
          }
          return false;
        });

        if (arithmeticIndex !== -1) {
          // Extract the original field name from the matched item
          const matchedItem = mergedAlias[arithmeticIndex];
          const originalFieldMatch = matchedItem.match(/^([^\s]+)/);
          if (originalFieldMatch) {
            const originalField = originalFieldMatch[1];
            // Replace the alias with the arithmetic function using the original field name
            const newAlias = arith.alias.replace(arithmeticField, originalField);
            mergedAlias[arithmeticIndex] = newAlias;
          } else {
            mergedAlias[arithmeticIndex] = arith.alias;
          }
        }
      }
    });
  }

  // Handle aggregateType transformations
  if (aggregateType && Array.isArray(aggregateType)) {
    aggregateType.forEach((agg) => {
      if (agg.field && agg.alias) {
        const aggField = agg.field;

        // Find and replace the matching field in alias array
        const aggIndex = mergedAlias.findIndex((item) => {
          // Extract field name from "field AS alias" format
          const fieldMatch = item.match(/^([^\s]+)/);
          if (fieldMatch) {
            const fieldName = fieldMatch[1];
            // Check if the field matches exactly
            if (fieldName === aggField) {
              return true;
            }
            // Check if the field matches by alias (e.g., demo.nama1 matches demo.nama AS nama1)
            const aliasMatch = item.match(/AS\s+([^\s]+)$/);
            if (aliasMatch) {
              const aliasName = aliasMatch[1];
              const fieldBaseName = aggField.split('.').pop();
              return aliasName === fieldBaseName;
            }
          }
          return false;
        });

        if (aggIndex !== -1) {
          // Extract the original field name from the matched item
          const matchedItem = mergedAlias[aggIndex];
          const originalFieldMatch = matchedItem.match(/^([^\s]+)/);
          if (originalFieldMatch) {
            const originalField = originalFieldMatch[1];
            // Replace the alias with the aggregate function using the original field name
            const newAlias = agg.alias.replace(aggField, originalField);
            mergedAlias[aggIndex] = newAlias;
          } else {
            mergedAlias[aggIndex] = agg.alias;
          }
        }
      }
    });
  }

  return mergedAlias;
}
