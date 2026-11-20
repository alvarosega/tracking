import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

const DIAS_ORDEN = [
  'LUNES',
  'MARTES',
  'MIERCOLES',
  'JUEVES',
  'VIERNES',
  'SABADO',
  'DOMINGO',
  'SIN DIA'
];

const DIAS_ETIQUETAS = {
  'LUNES': 'LUNES',
  'MARTES': 'MARTES',
  'MIERCOLES': 'MIÉRCOLES',
  'JUEVES': 'JUEVES',
  'VIERNES': 'VIERNES',
  'SABADO': 'SÁBADO',
  'DOMINGO': 'DOMINGO',
  'SIN DIA': 'SIN DÍA ASIGNADO'
};

function normalizarDia(diaRaw) {
  if (!diaRaw) return 'SIN DIA';
  const d = String(diaRaw)
    .trim()
    .toUpperCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
  if (d.includes('LUN')) return 'LUNES';
  if (d.includes('MAR')) return 'MARTES';
  if (d.includes('MIE')) return 'MIERCOLES';
  if (d.includes('JUE')) return 'JUEVES';
  if (d.includes('VIE')) return 'VIERNES';
  if (d.includes('SAB')) return 'SABADO';
  if (d.includes('DOM')) return 'DOMINGO';
  return d || 'SIN DIA';
}

/**
 * Genera y descarga un PDF con el listado de clientes no cubiertos de una ruta
 * (incluyendo tanto a los clientes sin compra como a los que están bajo el umbral mínimo),
 * agrupados por día de visita y con las columnas: Código de Cliente y Nombre de Cliente.
 *
 * @param {Object} params
 * @param {string} params.ruta - Código o nombre de la ruta
 * @param {string} [params.vendedor] - Nombre del preventista/vendedor
 * @param {string} [params.canal] - Canal de la ruta
 * @param {string} [params.fechaCorte] - Fecha de corte o período
 * @param {string} [params.kpiNombre] - Nombre del KPI evaluado (ej. Cobertura General)
 * @param {number} [params.reglaMin] - Umbral mínimo del canal
 * @param {Array<Object>} params.clientes - Lista de clientes (sin compra y bajo umbral)
 */
export function exportarPdfClientesSinCompra({
  ruta = 'Sin Ruta',
  vendedor = 'No Asignado',
  canal = 'GENERAL',
  fechaCorte = '',
  kpiNombre = 'Cobertura',
  reglaMin = 0,
  clientes = []
}) {
  const doc = new jsPDF({
    orientation: 'portrait',
    unit: 'mm',
    format: 'a4'
  });

  const pageWidth = doc.internal.pageSize.getWidth();
  const pageHeight = doc.internal.pageSize.getHeight();
  const marginX = 14;
  let currentY = 14;

  const sinCompraCount = clientes.filter(c => !c.tiene_logro && Number(c.monto_bs || 0) <= 0).length;
  const bajoUmbralCount = clientes.filter(c => !c.tiene_logro && Number(c.monto_bs || 0) > 0).length;

  // 1. Agrupar clientes por día de visita normalizado
  const gruposPorDia = {};
  DIAS_ORDEN.forEach(d => {
    gruposPorDia[d] = [];
  });

  clientes.forEach(c => {
    const diaNorm = normalizarDia(c.dia_visita || c.dia);
    if (!gruposPorDia[diaNorm]) {
      gruposPorDia[diaNorm] = [];
    }
    gruposPorDia[diaNorm].push(c);
  });

  // Ordenar clientes dentro de cada día: primero los que tienen compra bajo umbral descendente por monto, luego alfabético
  DIAS_ORDEN.forEach(d => {
    if (gruposPorDia[d]) {
      gruposPorDia[d].sort((a, b) => {
        const montoA = Number(a.monto_bs || 0);
        const montoB = Number(b.monto_bs || 0);
        if (montoA > 0 && montoB > 0) {
          return montoB - montoA;
        }
        if (montoA > 0) return -1;
        if (montoB > 0) return 1;
        return String(a.cliente || '').localeCompare(String(b.cliente || ''), 'es', { numeric: true });
      });
    }
  });

  // 2. Encabezado institucional
  doc.setFillColor(29, 29, 31); // #1D1D1F
  doc.rect(marginX, currentY, pageWidth - (marginX * 2), 22, 'F');

  doc.setTextColor(255, 255, 255);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(12.5);
  doc.text('REPORTE DE CLIENTES SIN COBERTURA', marginX + 6, currentY + 8);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.setTextColor(200, 200, 205);
  doc.text(`${kpiNombre} · Sin Compra y Bajo Umbral Mínimo`, marginX + 6, currentY + 14);

  // Fecha de generación a la derecha
  const now = new Date();
  const fechaGenStr = `${String(now.getDate()).padStart(2, '0')}/${String(now.getMonth() + 1).padStart(2, '0')}/${now.getFullYear()} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
  doc.setFontSize(7.5);
  doc.text(`Generado: ${fechaGenStr}`, pageWidth - marginX - 6, currentY + 8, { align: 'right' });
  if (fechaCorte) {
    doc.text(`Fecha Corte: ${fechaCorte}`, pageWidth - marginX - 6, currentY + 14, { align: 'right' });
  }

  currentY += 26;

  // 3. Ficha con parámetros clave de la ruta
  doc.setFillColor(248, 248, 250); // #F8F8FA
  doc.setDrawColor(220, 220, 225);
  doc.roundedRect(marginX, currentY, pageWidth - (marginX * 2), 17, 2, 2, 'FD');

  doc.setTextColor(30, 30, 35);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(8.5);

  const colW = (pageWidth - (marginX * 2)) / 4;

  // Columna 1: Ruta
  doc.setFont('helvetica', 'bold');
  doc.text('RUTA:', marginX + 4, currentY + 5.5);
  doc.setFont('helvetica', 'normal');
  doc.text(String(ruta), marginX + 4, currentY + 11);

  // Columna 2: Preventista / Vendedor
  doc.setFont('helvetica', 'bold');
  doc.text('PREVENTISTA:', marginX + colW + 2, currentY + 5.5);
  doc.setFont('helvetica', 'normal');
  const vendStr = String(vendedor).length > 24 ? `${String(vendedor).slice(0, 22)}...` : String(vendedor);
  doc.text(vendStr, marginX + colW + 2, currentY + 11);

  // Columna 3: Canal y Umbral
  doc.setFont('helvetica', 'bold');
  doc.text('CANAL:', marginX + (colW * 2) + 2, currentY + 5.5);
  doc.setFont('helvetica', 'normal');
  const umbralInfo = reglaMin > 0 ? ` (Mín: Bs ${reglaMin})` : '';
  doc.text(`${canal}${umbralInfo}`, marginX + (colW * 2) + 2, currentY + 11);

  // Columna 4: Total No Coberturados
  doc.setFont('helvetica', 'bold');
  doc.text('NO COBERTURADOS:', marginX + (colW * 3) + 2, currentY + 5.5);
  doc.setFont('helvetica', 'bold');
  doc.setTextColor(185, 28, 28);
  doc.text(`${clientes.length} Clientes`, marginX + (colW * 3) + 2, currentY + 10.5);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(7);
  doc.setTextColor(100, 100, 105);
  if (bajoUmbralCount > 0) {
    doc.text(`${sinCompraCount} s/compra · ${bajoUmbralCount} bajo umbral`, marginX + (colW * 3) + 2, currentY + 14.5);
  } else {
    doc.text(`${sinCompraCount} sin compra`, marginX + (colW * 3) + 2, currentY + 14.5);
  }

  currentY += 22;

  // 4. Tablas por Día de Visita
  let hayGruposConDatos = false;

  DIAS_ORDEN.forEach(diaKey => {
    const listadoDia = gruposPorDia[diaKey] || [];
    if (listadoDia.length === 0) {
      return;
    }
    hayGruposConDatos = true;

    // Verificar si queda espacio suficiente para la cabecera del día + al menos 2 filas (aprox 25mm)
    if (currentY > pageHeight - 35) {
      doc.addPage();
      currentY = 16;
    }

    const diaSinCompra = listadoDia.filter(c => !c.tiene_logro && Number(c.monto_bs || 0) <= 0).length;
    const diaBajoUmbral = listadoDia.filter(c => !c.tiene_logro && Number(c.monto_bs || 0) > 0).length;

    let subConteo = `${listadoDia.length} ${listadoDia.length === 1 ? 'cliente' : 'clientes'}`;
    if (diaBajoUmbral > 0) {
      subConteo += ` (${diaSinCompra} sin compra, ${diaBajoUmbral} bajo umbral)`;
    }

    // Cabecera del Día de Visita
    const diaTitulo = `${DIAS_ETIQUETAS[diaKey] || diaKey} — ${subConteo}`;

    doc.setFillColor(235, 238, 245);
    doc.setDrawColor(200, 205, 215);
    doc.rect(marginX, currentY, pageWidth - (marginX * 2), 6.5, 'FD');

    doc.setTextColor(0, 113, 227); // #0071E3
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8.5);
    doc.text(diaTitulo, marginX + 3, currentY + 4.5);

    currentY += 8;

    // Filas para la tabla
    const bodyRows = listadoDia.map(c => {
      const montoBs = Number(c.monto_bs || 0);
      const cant = Number(c.cantidad || 0);
      const esBajoUmbral = (!c.tiene_logro && (montoBs > 0 || cant > 0));

      let nombreTexto = String(c.cliente || 'CLIENTE S/N');
      if (esBajoUmbral) {
        const minVal = c.umbral_minimo ?? reglaMin;
        const minTexto = minVal > 0 ? ` | Mínimo: Bs ${Number(minVal).toFixed(2)}` : '';
        nombreTexto += `\n[BAJO UMBRAL - Compró: Bs ${montoBs.toFixed(2)}${minTexto}]`;
      }

      return [
        String(c.cliente_id || '-'),
        nombreTexto
      ];
    });

    autoTable(doc, {
      startY: currentY,
      margin: { left: marginX, right: marginX },
      head: [['Código de Cliente', 'Nombre de Cliente']],
      body: bodyRows,
      theme: 'grid',
      styles: {
        font: 'helvetica',
        fontSize: 8,
        cellPadding: 2,
        textColor: [30, 30, 35],
        lineColor: [225, 225, 230],
        lineWidth: 0.2
      },
      headStyles: {
        fillColor: [242, 243, 245],
        textColor: [60, 60, 65],
        fontStyle: 'bold',
        fontSize: 8,
        halign: 'left',
        lineColor: [215, 215, 220],
        lineWidth: 0.2
      },
      columnStyles: {
        0: { cellWidth: 45, halign: 'left', fontStyle: 'bold' },
        1: { cellWidth: 'auto', halign: 'left' }
      },
      alternateRowStyles: {
        fillColor: [250, 250, 252]
      },
      didParseCell: (data) => {
        if (data.section === 'body') {
          const rawItem = listadoDia[data.row.index];
          if (rawItem && !rawItem.tiene_logro && Number(rawItem.monto_bs || 0) > 0) {
            // Fondo ámbar suave para clientes que compraron pero quedaron bajo el umbral
            data.cell.styles.fillColor = [255, 251, 235];
            if (data.column.index === 0) {
              data.cell.styles.textColor = [180, 83, 9]; // amber-800
            }
          }
        }
      },
      didDrawPage: (data) => {
        currentY = data.cursor.y;
      }
    });

    currentY = doc.lastAutoTable.finalY + 5;
  });

  // Si no hubiera clientes no cubiertos
  if (!hayGruposConDatos) {
    doc.setTextColor(110, 110, 115);
    doc.setFont('helvetica', 'italic');
    doc.setFontSize(9);
    doc.text('La ruta seleccionada tiene el 100% de cobertura (sin clientes pendientes ni bajo umbral).', marginX, currentY + 6);
  }

  // 5. Pie de página en todas las páginas
  const totalPages = doc.internal.getNumberOfPages();
  for (let i = 1; i <= totalPages; i++) {
    doc.setPage(i);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.setTextColor(140, 140, 145);

    const footerText = `Página ${i} de ${totalPages} · Ruta ${ruta} (${vendedor}) · Control de Cobertura y Seguimiento`;
    doc.text(footerText, pageWidth / 2, pageHeight - 7, { align: 'center' });
  }

  // 6. Descargar archivo PDF
  const nombreLimpio = String(ruta).replace(/[^a-zA-Z0-9_-]/g, '_');
  const fechaNombre = fechaCorte ? `_${fechaCorte}` : '';
  const fileName = `Sin_Cobertura_Ruta_${nombreLimpio}${fechaNombre}.pdf`;

  doc.save(fileName);
}
