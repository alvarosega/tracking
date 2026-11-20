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
 * Genera y descarga un PDF con el listado de clientes sin compra de una ruta,
 * agrupados por día de visita y con las columnas: Código de Cliente y Nombre de Cliente.
 *
 * @param {Object} params
 * @param {string} params.ruta - Código o nombre de la ruta
 * @param {string} [params.vendedor] - Nombre del preventista/vendedor
 * @param {string} [params.canal] - Canal de la ruta
 * @param {string} [params.fechaCorte] - Fecha de corte o período
 * @param {string} [params.kpiNombre] - Nombre del KPI evaluado (ej. Cobertura General)
 * @param {Array<Object>} params.clientes - Lista de clientes sin compra
 */
export function exportarPdfClientesSinCompra({
  ruta = 'Sin Ruta',
  vendedor = 'No Asignado',
  canal = 'GENERAL',
  fechaCorte = '',
  kpiNombre = 'Cobertura',
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

  // Ordenar clientes alfabéticamente dentro de cada día
  DIAS_ORDEN.forEach(d => {
    if (gruposPorDia[d]) {
      gruposPorDia[d].sort((a, b) =>
        String(a.cliente || '').localeCompare(String(b.cliente || ''), 'es', { numeric: true })
      );
    }
  });

  // 2. Encabezado institucional
  doc.setFillColor(29, 29, 31); // #1D1D1F
  doc.rect(marginX, currentY, pageWidth - (marginX * 2), 22, 'F');

  doc.setTextColor(255, 255, 255);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(13);
  doc.text('REPORTE DE CLIENTES SIN COMPRA', marginX + 6, currentY + 8);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.setTextColor(200, 200, 205);
  doc.text(`${kpiNombre} · Módulo de Avance y Cumplimiento`, marginX + 6, currentY + 14);

  // Fecha de generación a la derecha
  const now = new Date();
  const fechaGenStr = `${String(now.getDate()).padStart(2, '0')}/${String(now.getMonth() + 1).padStart(2, '0')}/${now.getFullYear()} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
  doc.setFontSize(7.5);
  doc.text(`Generado: ${fechaGenStr}`, pageWidth - marginX - 6, currentY + 8, { align: 'right' });
  if (fechaCorte) {
    doc.text(`Corte: ${fechaCorte}`, pageWidth - marginX - 6, currentY + 14, { align: 'right' });
  }

  currentY += 26;

  // 3. Ficha con parámetros clave de la ruta
  doc.setFillColor(248, 248, 250); // #F8F8FA
  doc.setDrawColor(220, 220, 225);
  doc.roundedRect(marginX, currentY, pageWidth - (marginX * 2), 16, 2, 2, 'FD');

  doc.setTextColor(30, 30, 35);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(9);

  const colW = (pageWidth - (marginX * 2)) / 4;

  // Ruta
  doc.setFont('helvetica', 'bold');
  doc.text('RUTA:', marginX + 4, currentY + 6);
  doc.setFont('helvetica', 'normal');
  doc.text(String(ruta), marginX + 4, currentY + 11);

  // Preventista / Vendedor
  doc.setFont('helvetica', 'bold');
  doc.text('PREVENTISTA:', marginX + colW + 2, currentY + 6);
  doc.setFont('helvetica', 'normal');
  const vendStr = String(vendedor).length > 24 ? `${String(vendedor).slice(0, 22)}...` : String(vendedor);
  doc.text(vendStr, marginX + colW + 2, currentY + 11);

  // Canal
  doc.setFont('helvetica', 'bold');
  doc.text('CANAL:', marginX + (colW * 2) + 2, currentY + 6);
  doc.setFont('helvetica', 'normal');
  doc.text(String(canal), marginX + (colW * 2) + 2, currentY + 11);

  // Total Sin Compra
  doc.setFont('helvetica', 'bold');
  doc.text('TOTAL SIN COMPRA:', marginX + (colW * 3) + 2, currentY + 6);
  doc.setFont('helvetica', 'bold');
  doc.setTextColor(185, 28, 28); // Rojo sobrio
  doc.text(`${clientes.length} Clientes`, marginX + (colW * 3) + 2, currentY + 11);

  currentY += 21;

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

    // Cabecera del Día de Visita
    const diaTitulo = `${DIAS_ETIQUETAS[diaKey] || diaKey} (${listadoDia.length} ${listadoDia.length === 1 ? 'cliente' : 'clientes'})`;

    doc.setFillColor(235, 238, 245);
    doc.setDrawColor(200, 205, 215);
    doc.rect(marginX, currentY, pageWidth - (marginX * 2), 6.5, 'FD');

    doc.setTextColor(0, 113, 227); // #0071E3
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8.5);
    doc.text(diaTitulo, marginX + 3, currentY + 4.5);

    currentY += 8;

    // Filas para la tabla
    const bodyRows = listadoDia.map(c => [
      String(c.cliente_id || '-'),
      String(c.cliente || 'CLIENTE S/N')
    ]);

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
      didDrawPage: (data) => {
        // Reservar espacio si se agregaron páginas automáticas
        currentY = data.cursor.y;
      }
    });

    currentY = doc.lastAutoTable.finalY + 5;
  });

  // Si no hubiera clientes sin compra
  if (!hayGruposConDatos) {
    doc.setTextColor(110, 110, 115);
    doc.setFont('helvetica', 'italic');
    doc.setFontSize(9);
    doc.text('La ruta seleccionada no registra clientes sin compra en el período.', marginX, currentY + 6);
  }

  // 5. Pie de página en todas las páginas (Número de página y confidencialidad)
  const totalPages = doc.internal.getNumberOfPages();
  for (let i = 1; i <= totalPages; i++) {
    doc.setPage(i);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.setTextColor(140, 140, 145);

    const footerText = `Página ${i} de ${totalPages} · Ruta ${ruta} (${vendedor}) · Documento de Control Operativo`;
    doc.text(footerText, pageWidth / 2, pageHeight - 7, { align: 'center' });
  }

  // 6. Descargar archivo PDF
  const nombreLimpio = String(ruta).replace(/[^a-zA-Z0-9_-]/g, '_');
  const fechaNombre = fechaCorte ? `_${fechaCorte}` : '';
  const fileName = `Sin_Compra_Ruta_${nombreLimpio}${fechaNombre}.pdf`;

  doc.save(fileName);
}
