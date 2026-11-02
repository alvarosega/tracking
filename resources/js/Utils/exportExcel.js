import * as XLSX from 'xlsx';

/**
 * Descarga una matriz bidimensional (rows) como archivo Excel (.xlsx) nativo.
 *
 * @param {Array<Array<any>>} rows - Matriz [ [headers...], [fila1...], ... ]
 * @param {string} nombreArchivo - Nombre del archivo (con o sin extensión .xlsx)
 * @param {string} [nombreHoja='Datos'] - Nombre de la hoja de cálculo
 */
export function descargarExcelXlsx(rows, nombreArchivo, nombreHoja = 'Datos') {
  const nombreFinal = nombreArchivo.endsWith('.xlsx')
    ? nombreArchivo
    : `${nombreArchivo.replace(/\.csv$/, '')}.xlsx`;

  const ws = XLSX.utils.aoa_to_sheet(rows);

  // Auto-ajustar anchos de columnas según el contenido máximo
  const colWidths = [];
  rows.forEach(row => {
    row.forEach((val, colIdx) => {
      const len = val !== null && val !== undefined ? String(val).length : 0;
      colWidths[colIdx] = Math.max(colWidths[colIdx] || 10, Math.min(len + 3, 50));
    });
  });
  ws['!cols'] = colWidths.map(w => ({ wch: w }));

  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, nombreHoja.slice(0, 31));

  XLSX.writeFile(wb, nombreFinal);
}

/**
 * Descarga alternativa en CSV delimitado por comas (,) con UTF-8 BOM.
 *
 * @param {Array<Array<any>>} rows
 * @param {string} nombreArchivo
 */
export function descargarCsvComas(rows, nombreArchivo) {
  const nombreFinal = nombreArchivo.endsWith('.csv')
    ? nombreArchivo
    : `${nombreArchivo.replace(/\.xlsx$/, '')}.csv`;

  const BOM = '\uFEFF';
  const csvBody = rows
    .map(row =>
      row
        .map(val => {
          if (val === null || val === undefined) return '""';
          const str = String(val).replace(/[\r\n]+/g, ' ').replace(/"/g, '""');
          return `"${str}"`;
        })
        .join(',')
    )
    .join('\r\n');

  const blob = new Blob([BOM + csvBody], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', nombreFinal);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

