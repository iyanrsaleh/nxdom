/**
 * Konversi `production` (state Buckets) → payload siap kirim ke executeOperation.
 * Menangani single-table maupun multi-join secara otomatis.
 */
export async function buildQuery(production) {
  // const Store = await NXUI.ref.nexaStore(id).get();
  // if (!Store) throw new Error(`buildQuery: store tidak ditemukan untuk id "${id}"`);
  // const production=Store.production;
  const prod = production || {};
  const alias        = prod.alias      || [];
  const original     = prod.original   || [];
  const aggregate    = prod.aggregate  || [];
  const arithmetic   = prod.arithmetic || [];

  const finalAlias = mergeAliasWithTransformations(alias, arithmetic, aggregate);

  let tabelName, operasi;
  if (prod.join?.tables?.length > 0) {
    // Multi-join: gunakan data dari production.join
    tabelName = prod.join.tables;
    operasi   = prod.join.operasi || {};
  } else {
    // Single table: bangun operasi minimal dari production.tabel / production.key
    tabelName = [prod.tabel];
    operasi   = {
      [prod.tabel]: {
        type:        'single',
        index:       '',
        aliasIndex:  prod.tabel,
        keyIndex:    prod.key,
        target:      '',
        condition:   '',
        aliasTarget: '',
        keyTarget:   '',
      },
    };
  }
// const checkedItems = await Sdk.getFields("select");
  return {
    
    alias: finalAlias,
    aliasNames: original,
    tabelName,
    operasi,
    where:  prod.where?.alias   || false,
    group:  prod.groupBy?.alias || false,
    order:  prod.orderBy?.alias || false,
    limit:  prod.limit  != null ? prod.limit  : null,
    offset: prod.offset != null ? prod.offset : null,
    // UNTUK FOMULIR
    formulir:         production.from,
  };
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
      return fieldMatch && fieldMatch[1] === arithmeticField;
    });

    if (arithmeticIndex !== -1) {
      mergedAlias[arithmeticIndex] = arithmetic.alias;
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
          return fieldMatch && fieldMatch[1] === arithmeticField;
        });

        if (arithmeticIndex !== -1) {
          mergedAlias[arithmeticIndex] = arith.alias;
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
          return fieldMatch && fieldMatch[1] === aggField;
        });

        if (aggIndex !== -1) {
          mergedAlias[aggIndex] = agg.alias;
        }
      }
    });
  }

  return mergedAlias;
}
