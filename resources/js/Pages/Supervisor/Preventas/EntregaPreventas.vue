<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden font-sans text-[#1D1D1F] bg-[#F5F5F7]">
      
      <!-- 1. Barra de Filtros Universal de Preventa -->
      <header class="p-2 sm:p-3 pb-0 shrink-0 space-y-2">
        <PreventaFiltrosBar
          currentSubTab="entrega-preventas"
          @consultar="cargarDatos"
          @cambio-fecha="cargarDatos"
        />

        <!-- 2. Barra de Control de Entregas y Filtro de Estados -->
        <div class="apple-glass-card rounded-[14px] p-2 flex flex-wrap items-center justify-between gap-2 bg-white/80 backdrop-blur-md border border-black/[0.06] text-xs">
          
          <!-- Filtro de Criterio de Fecha (Preventa vs Entrega) -->
          <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-[#86868B] uppercase">Ver por:</span>
            <div class="inline-flex p-0.5 rounded-[10px] bg-black/[0.05] border border-black/[0.04] text-[11px]">
              <button
                type="button"
                @click="cambiarCriterioFecha('preventa')"
                class="px-2.5 py-1 rounded-[8px] transition-all cursor-pointer font-bold"
                :class="criterioFecha === 'preventa' ? 'bg-white text-[#1D1D1F] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              >
                Fecha Preventa
              </button>
              <button
                type="button"
                @click="cambiarCriterioFecha('entrega')"
                class="px-2.5 py-1 rounded-[8px] transition-all cursor-pointer font-bold"
                :class="criterioFecha === 'entrega' ? 'bg-white text-[#1D1D1F] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              >
                Fecha Entrega
              </button>
            </div>
          </div>

          <!-- Filtro Rápido de Estados de Entrega (Pills) -->
          <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
            <button
              type="button"
              @click="estadoFiltro = 'TODOS'"
              class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer"
              :class="estadoFiltro === 'TODOS' ? 'bg-[#1D1D1F] text-white' : 'bg-white text-[#6E6E73] border border-black/[0.06] hover:bg-black/[0.02]'"
            >
              Todas ({{ totales.total_preventas }})
            </button>
            <button
              type="button"
              @click="estadoFiltro = 'ENTREGADO'"
              class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer flex items-center gap-1"
              :class="estadoFiltro === 'ENTREGADO' ? 'bg-[#34C759] text-white' : 'bg-white text-[#1E6B37] border border-[#D1F2D9] hover:bg-emerald-50'"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-[#34C759]" :class="estadoFiltro === 'ENTREGADO' ? 'bg-white' : ''"></span>
              <span>Entregadas ({{ totales.conteo_estados.entregado }})</span>
            </button>
            <button
              type="button"
              @click="estadoFiltro = 'PARCIAL'"
              class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer flex items-center gap-1"
              :class="estadoFiltro === 'PARCIAL' ? 'bg-[#FF9500] text-white' : 'bg-white text-amber-800 border border-amber-200 hover:bg-amber-50'"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-[#FF9500]" :class="estadoFiltro === 'PARCIAL' ? 'bg-white' : ''"></span>
              <span>Parciales ({{ totales.conteo_estados.parcial }})</span>
            </button>
            <button
              type="button"
              @click="estadoFiltro = 'RECHAZADO'"
              class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer flex items-center gap-1"
              :class="estadoFiltro === 'RECHAZADO' ? 'bg-[#FF3B30] text-white' : 'bg-white text-[#C9342C] border border-[#FADCD5] hover:bg-red-50'"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-[#FF3B30]" :class="estadoFiltro === 'RECHAZADO' ? 'bg-white' : ''"></span>
              <span>Rechazadas ({{ totales.conteo_estados.rechazado }})</span>
            </button>
            <button
              type="button"
              @click="estadoFiltro = 'PENDIENTE'"
              class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer flex items-center gap-1"
              :class="estadoFiltro === 'PENDIENTE' ? 'bg-[#0071E3] text-white' : 'bg-white text-[#0071E3] border border-blue-200 hover:bg-blue-50'"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]" :class="estadoFiltro === 'PENDIENTE' ? 'bg-white' : ''"></span>
              <span>Pendientes ({{ totales.conteo_estados.pendiente }})</span>
            </button>
          </div>

          <!-- Métricas Resumen Compactas en Barra -->
          <div class="flex items-center gap-3 text-[11px] font-mono ml-auto">
            <div class="hidden md:flex items-center gap-1.5">
              <span class="text-[#86868B]">Monto:</span>
              <strong class="text-emerald-700">Bs {{ formatoMonto(totales.monto_total_entregado) }}</strong>
              <span class="text-[#86868B]">/ Bs {{ formatoMonto(totales.monto_total_preventa) }}</span>
            </div>
            <div class="h-3.5 w-px bg-black/[0.08] hidden md:block"></div>
            <div class="flex items-center gap-1">
              <span class="text-[#86868B]">Efectividad:</span>
              <strong
                class="px-1.5 py-0.5 rounded-md font-bold text-[10px]"
                :class="totales.efectividad_entrega_pct >= 90 ? 'bg-[#EBF9EF] text-[#1E6B37]' : (totales.efectividad_entrega_pct >= 75 ? 'bg-amber-100 text-amber-900' : 'bg-red-100 text-red-900')"
              >
                {{ totales.efectividad_entrega_pct }}%
              </strong>
            </div>
          </div>

        </div>
      </header>

      <!-- 3. Contenido Principal: Mapa + Lista + Drawer de Detalle -->
      <main class="flex-1 flex min-h-0 relative overflow-hidden p-2 sm:p-3 pt-2 gap-2">
        
        <!-- PANEL LATERAL IZQUIERDO: LISTA DE PREVENTAS -->
        <aside
          class="w-full sm:w-[320px] md:w-[360px] bg-white rounded-[16px] border border-black/[0.06] shadow-sm flex flex-col shrink-0 overflow-hidden transition-all duration-200"
          :class="vistaMovil === 'mapa' ? 'hidden sm:flex' : 'flex'"
        >
          <!-- Buscador y Control de Lista -->
          <div class="p-2.5 border-b border-black/[0.06] bg-[#FAFBFD] space-y-2 shrink-0">
            <div class="flex items-center justify-between">
              <h3 class="text-xs font-extrabold text-[#1D1D1F] uppercase tracking-tight flex items-center gap-1.5">
                <span>📦 Pedidos en Ruta</span>
                <span class="text-[10px] text-[#86868B] font-mono">({{ preventasFiltradas.length }})</span>
              </h3>

              <!-- Toggle para ir al mapa en móvil -->
              <button
                type="button"
                @click="vistaMovil = 'mapa'"
                class="sm:hidden px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#0071E3] text-white"
              >
                Ver Mapa &rarr;
              </button>
            </div>

            <!-- Input de Búsqueda Rápida -->
            <div class="relative">
              <input
                v-model="busquedaTexto"
                type="text"
                placeholder="Buscar cliente, código o N° preventa..."
                class="w-full pl-7 pr-2.5 py-1 rounded-lg text-xs bg-white border border-black/[0.08] text-[#1D1D1F] focus:outline-none focus:ring-1 focus:ring-[#0071E3]"
              />
              <svg class="w-3.5 h-3.5 text-[#86868B] absolute left-2 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </div>

          <!-- Lista Scrolleable de Pedidos -->
          <div class="flex-1 overflow-y-auto custom-scroll divide-y divide-black/[0.04]">
            <div v-if="cargando" class="p-8 text-center text-xs text-[#86868B] space-y-2">
              <span class="w-4 h-4 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin inline-block"></span>
              <p>Consultando preventas y entregas...</p>
            </div>

            <div v-else-if="preventasFiltradas.length === 0" class="p-8 text-center text-xs text-[#86868B]">
              No se encontraron preventas para los filtros seleccionados.
            </div>

            <div
              v-else
              v-for="p in preventasFiltradas"
              :key="p.nro_preventa"
              @click="seleccionarPreventa(p)"
              class="p-2.5 hover:bg-blue-50/40 transition-colors cursor-pointer group"
              :class="preventaSeleccionada?.nro_preventa === p.nro_preventa ? 'bg-blue-50/80 border-l-4 border-l-[#0071E3]' : ''"
            >
              <div class="flex items-start justify-between gap-1.5">
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1.5">
                    <span
                      class="w-2 h-2 rounded-full shrink-0"
                      :style="{ backgroundColor: p.color }"
                    ></span>
                    <strong class="text-xs text-[#1D1D1F] truncate group-hover:text-[#0071E3] transition-colors">
                      {{ p.cliente }}
                    </strong>
                  </div>
                  <p class="text-[10px] text-[#6E6E73] truncate font-mono mt-0.5">
                    #{{ p.nro_preventa }} &bull; Cód: {{ p.codigo_cliente }} &bull; {{ p.ruta }}
                  </p>
                </div>

                <!-- Badge de Estado -->
                <span
                  class="text-[9px] font-bold px-1.5 py-0.2 rounded font-mono shrink-0"
                  :class="p.estado === 'ENTREGADO' ? 'bg-[#EBF9EF] text-[#1E6B37]' : (p.estado === 'PARCIAL' ? 'bg-amber-100 text-amber-900' : (p.estado === 'RECHAZADO' ? 'bg-[#FDF0EF] text-[#C9342C]' : 'bg-blue-50 text-blue-700'))"
                >
                  {{ p.porcentaje_entrega }}%
                </span>
              </div>

              <!-- Montos y Cantidad de Ítems -->
              <div class="flex items-center justify-between text-[10px] mt-1.5 pt-1 border-t border-black/[0.03]">
                <span class="text-[#86868B] font-mono">{{ p.total_items }} ítems &bull; {{ p.unidades_preventa }} u.</span>
                <span class="font-bold font-mono text-[#1D1D1F]">
                  Bs {{ formatoMonto(p.monto_preventa) }}
                </span>
              </div>
            </div>
          </div>
        </aside>

        <!-- ÁREA CENTRAL: MAPA LEAFLET -->
        <section
          class="flex-1 flex flex-col min-h-0 bg-white rounded-[16px] border border-black/[0.06] shadow-sm relative overflow-hidden"
          :class="vistaMovil === 'panel' ? 'hidden sm:flex' : 'flex'"
        >
          <!-- Controles Flotantes del Mapa -->
          <div class="absolute top-3 left-3 z-[1000] flex items-center gap-2">
            <!-- Botón Volver a Lista en móvil -->
            <button
              type="button"
              @click="vistaMovil = 'panel'"
              class="sm:hidden px-2.5 py-1 rounded-lg text-xs font-bold bg-white text-[#1D1D1F] border border-black/[0.1] shadow-md flex items-center gap-1"
            >
              &larr; Lista
            </button>

            <!-- Alternador de Capa de Mapa (Calles vs Satélite) -->
            <div class="bg-white/90 backdrop-blur-md rounded-lg p-0.5 border border-black/[0.08] shadow-md flex items-center text-[10px] font-bold">
              <button
                type="button"
                @click="cambiarCapaMapa('openStreetMap')"
                class="px-2 py-0.5 rounded-md transition-all cursor-pointer"
                :class="capaMapaActiva === 'openStreetMap' ? 'bg-[#1D1D1F] text-white' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              >
                Calles
              </button>
              <button
                type="button"
                @click="cambiarCapaMapa('googleSat')"
                class="px-2 py-0.5 rounded-md transition-all cursor-pointer"
                :class="capaMapaActiva === 'googleSat' ? 'bg-[#1D1D1F] text-white' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              >
                Satélite
              </button>
            </div>
          </div>

          <!-- Leyenda Flotante del Mapa -->
          <div class="absolute bottom-3 left-3 z-[1000] bg-white/90 backdrop-blur-md rounded-xl p-2 px-3 border border-black/[0.08] shadow-md text-[10px] font-medium text-[#6E6E73] flex flex-wrap items-center gap-3">
            <span class="font-bold text-[#1D1D1F]">Semáforo Entrega:</span>
            <div class="flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-[#34C759]"></span>
              <span>Entregado</span>
            </div>
            <div class="flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-[#FF9500]"></span>
              <span>Parcial</span>
            </div>
            <div class="flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-[#FF3B30]"></span>
              <span>Rechazado</span>
            </div>
            <div class="flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
              <span>Pendiente</span>
            </div>
          </div>

          <!-- Contenedor del Mapa -->
          <div ref="mapaContainerRef" class="flex-1 w-full h-full min-h-0 bg-[#E5E5EA]"></div>
        </section>

        <!-- DRAWER / PANEL LATERAL DERECHO: DETALLE COMPLETO DE LO QUE SE DEBIÓ ENTREGAR -->
        <transition
          enter-active-class="transform transition ease-in-out duration-300"
          enter-from-class="translate-x-full"
          enter-to-class="translate-x-0"
          leave-active-class="transform transition ease-in-out duration-200"
          leave-from-class="translate-x-0"
          leave-to-class="translate-x-full"
        >
          <aside
            v-if="preventaSeleccionada"
            class="absolute top-2 bottom-2 right-2 sm:static sm:top-auto sm:bottom-auto sm:right-auto w-[92vw] sm:w-[420px] md:w-[460px] lg:w-[500px] bg-white rounded-[16px] border border-black/[0.08] shadow-2xl z-[1010] flex flex-col overflow-hidden"
          >
            <!-- Cabecera del Drawer -->
            <div class="p-3.5 bg-gradient-to-r from-slate-900 to-slate-800 text-white flex items-center justify-between shrink-0">
              <div class="min-w-0 pr-2">
                <div class="flex items-center gap-2">
                  <span class="text-[10px] font-mono font-bold bg-white/20 text-white px-2 py-0.5 rounded">
                    Preventa #{{ preventaSeleccionada.nro_preventa }}
                  </span>
                  <span
                    class="text-[9.5px] font-bold px-2 py-0.5 rounded font-mono"
                    :class="preventaSeleccionada.estado === 'ENTREGADO' ? 'bg-[#34C759] text-white' : (preventaSeleccionada.estado === 'PARCIAL' ? 'bg-[#FF9500] text-white' : (preventaSeleccionada.estado === 'RECHAZADO' ? 'bg-[#FF3B30] text-white' : 'bg-[#0071E3] text-white'))"
                  >
                    {{ preventaSeleccionada.estado_label }} ({{ preventaSeleccionada.porcentaje_entrega }}%)
                  </span>
                </div>
                <h3 class="text-sm font-extrabold truncate text-white mt-1">
                  {{ preventaSeleccionada.cliente }}
                </h3>
                <p class="text-[10px] text-slate-300 font-mono truncate">
                  Cód: {{ preventaSeleccionada.codigo_cliente }} &bull; Ruta: {{ preventaSeleccionada.ruta }} &bull; Vendedor: {{ preventaSeleccionada.vendedor }}
                </p>
              </div>

              <button
                type="button"
                @click="preventaSeleccionada = null"
                class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer shrink-0 font-bold"
                title="Cerrar detalle"
              >
                ✕
              </button>
            </div>

            <!-- Resumen Financiero y Logístico -->
            <div class="p-3 bg-[#FAFBFD] border-b border-black/[0.06] grid grid-cols-3 gap-2 text-center text-xs shrink-0">
              <div class="p-2 rounded-lg bg-white border border-black/[0.04]">
                <span class="text-[9.5px] text-[#86868B] block">Preventa Pedida</span>
                <strong class="font-mono text-[#1D1D1F] text-xs">Bs {{ formatoMonto(preventaSeleccionada.monto_preventa) }}</strong>
                <span class="text-[9px] text-[#86868B] block mt-0.5">{{ preventaSeleccionada.unidades_preventa }} unidades</span>
              </div>
              <div class="p-2 rounded-lg bg-white border border-black/[0.04]">
                <span class="text-[9.5px] text-[#86868B] block">Entregado/Facturado</span>
                <strong class="font-mono text-emerald-700 text-xs">Bs {{ formatoMonto(preventaSeleccionada.monto_entregado) }}</strong>
                <span class="text-[9px] text-emerald-600 block mt-0.5">{{ preventaSeleccionada.unidades_entregadas }} unidades</span>
              </div>
              <div class="p-2 rounded-lg bg-white border border-black/[0.04]">
                <span class="text-[9.5px] text-[#86868B] block">Brecha / Faltante</span>
                <strong class="font-mono text-red-600 text-xs">
                  Bs {{ formatoMonto(Math.max(0, preventaSeleccionada.monto_preventa - preventaSeleccionada.monto_entregado)) }}
                </strong>
                <span class="text-[9px] text-red-500 block mt-0.5">
                  {{ Math.max(0, preventaSeleccionada.unidades_preventa - preventaSeleccionada.unidades_entregadas) }} u. pend.
                </span>
              </div>
            </div>

            <!-- Datos Adicionales de Entrega -->
            <div class="px-3 py-2 bg-white border-b border-black/[0.04] text-[10.5px] text-[#6E6E73] flex flex-wrap items-center justify-between gap-2 shrink-0">
              <div>
                <span>📍 Dirección: </span>
                <strong class="text-[#1D1D1F]">{{ preventaSeleccionada.direccion }} ({{ preventaSeleccionada.territorio }})</strong>
              </div>
              <div v-if="preventaSeleccionada.nro_factura">
                <span>🧾 Factura: </span>
                <strong class="font-mono text-[#0071E3]">#{{ preventaSeleccionada.nro_factura }}</strong>
              </div>
            </div>

            <!-- AUDITORÍA DE RECHAZO (Si el repartidor registró rechazo) -->
            <div
              v-if="preventaSeleccionada.rechazo"
              class="m-3 p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-900 space-y-1.5 shrink-0"
            >
              <div class="flex items-center justify-between font-bold">
                <span class="flex items-center gap-1.5">
                  <span>⚠️ Rechazo Registrado en Terreno</span>
                  <span class="px-1.5 py-0.2 rounded bg-red-200 text-red-800 text-[9px] font-mono">
                    {{ preventaSeleccionada.rechazo.tipo_rechazo }}
                  </span>
                </span>
                <span class="text-[10px] font-mono text-red-700">
                  {{ preventaSeleccionada.rechazo.fecha_rechazo }}
                </span>
              </div>
              <p class="text-[11px]">
                <strong>Motivo:</strong> {{ preventaSeleccionada.rechazo.motivo }}
                <span v-if="preventaSeleccionada.rechazo.comentarios"> &bull; {{ preventaSeleccionada.rechazo.comentarios }}</span>
              </p>
              
              <!-- Botón Ver Foto de Rechazo -->
              <div v-if="preventaSeleccionada.rechazo.foto_url" class="pt-1">
                <button
                  type="button"
                  @click="abrirFotoModal(preventaSeleccionada.rechazo.foto_url, preventaSeleccionada.cliente)"
                  class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-red-600 hover:bg-red-700 text-white transition-colors cursor-pointer flex items-center gap-1"
                >
                  <span>📷 Ver Foto de Evidencia en Terreno</span>
                </button>
              </div>
            </div>

            <!-- TABLA DE ÍTEMS QUE SE DEBIERON ENTREGAR -->
            <div class="p-3 pb-1 flex items-center justify-between text-xs shrink-0">
              <h4 class="font-extrabold text-[#1D1D1F] uppercase text-[11px] tracking-tight">
                Detalle de Productos a Entregar ({{ preventaSeleccionada.items.length }})
              </h4>
              <span class="text-[10px] text-[#86868B] font-mono">Pedido vs Entregado</span>
            </div>

            <div class="flex-1 overflow-y-auto custom-scroll p-3 pt-0">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="sticky top-0 bg-white/95 backdrop-blur-xs text-[9.5px] font-bold text-[#86868B] uppercase border-b border-black/[0.08] z-10">
                  <tr>
                    <th class="py-1.5 pr-2">Producto</th>
                    <th class="py-1.5 px-2 text-center">Ped.</th>
                    <th class="py-1.5 px-2 text-center">Ent.</th>
                    <th class="py-1.5 pl-2 text-right">Monto (Bs)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-black/[0.04] text-[11px]">
                  <tr
                    v-for="item in preventaSeleccionada.items"
                    :key="item.producto_id"
                    class="hover:bg-slate-50 transition-colors"
                  >
                    <!-- Producto y Categoría -->
                    <td class="py-2 pr-2">
                      <div class="font-bold text-[#1D1D1F] line-clamp-2">
                        {{ item.producto }}
                      </div>
                      <div class="text-[9.5px] text-[#86868B] flex items-center gap-1 font-mono mt-0.5">
                        <span>{{ item.codigo }}</span>
                        <span>&bull;</span>
                        <span>{{ item.categoria }}</span>
                      </div>
                      <div v-if="item.motivo_rechazo" class="text-[9px] text-red-600 font-semibold mt-0.5">
                        Falla: {{ item.motivo_rechazo }}
                      </div>
                    </td>

                    <!-- Cantidad Pedida -->
                    <td class="py-2 px-2 text-center font-mono font-bold text-[#1D1D1F]">
                      {{ item.cantidad_preventa }}
                    </td>

                    <!-- Cantidad Entregada -->
                    <td class="py-2 px-2 text-center font-mono">
                      <span
                        class="px-1.5 py-0.5 rounded font-bold text-[10px]"
                        :class="item.estado_item === 'ENTREGADO' ? 'bg-[#EBF9EF] text-[#1E6B37]' : (item.estado_item === 'PARCIAL' ? 'bg-amber-100 text-amber-900' : 'bg-red-100 text-red-900')"
                      >
                        {{ item.cantidad_entregada }}
                      </span>
                    </td>

                    <!-- Monto Bs -->
                    <td class="py-2 pl-2 text-right font-mono">
                      <div class="font-bold text-[#1D1D1F]">
                        Bs {{ formatoMonto(item.monto_preventa) }}
                      </div>
                      <div v-if="item.monto_entregado > 0 && item.monto_entregado < item.monto_preventa" class="text-[9px] text-emerald-700">
                        Entr: Bs {{ formatoMonto(item.monto_entregado) }}
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Footer del Drawer -->
            <div class="p-3 border-t border-black/[0.06] bg-[#FAFBFD] flex items-center justify-between text-xs shrink-0">
              <span class="text-[10px] text-[#86868B]">
                Despachado por: <strong class="text-[#1D1D1F]">{{ preventaSeleccionada.distribuidor }}</strong>
              </span>
              <button
                type="button"
                @click="centrarEnMapa(preventaSeleccionada)"
                class="px-2.5 py-1 rounded-lg text-[11px] font-bold text-[#0071E3] hover:bg-[#0071E3] hover:text-white transition-all cursor-pointer border border-[#0071E3]/20"
              >
                Centrar en Mapa 🎯
              </button>
            </div>
          </aside>
        </transition>

      </main>

      <!-- MODAL VISOR DE FOTO DE RECHAZO / TERRENO -->
      <Teleport to="body">
        <div
          v-if="fotoModalUrl"
          class="fixed inset-0 z-[99999] bg-slate-900/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5"
          @click="fotoModalUrl = null"
        >
          <div class="bg-white rounded-2xl overflow-hidden max-w-lg w-full shadow-2xl border border-slate-200 flex flex-col" @click.stop>
            <div class="p-3 bg-slate-900 text-white flex items-center justify-between">
              <span class="text-xs font-bold font-mono">Evidencia Fotográfica de Entrega</span>
              <button
                type="button"
                @click="fotoModalUrl = null"
                class="w-6 h-6 rounded bg-slate-800 text-white flex items-center justify-center font-bold"
              >
                ✕
              </button>
            </div>
            <div class="p-4 bg-slate-950 flex items-center justify-center min-h-[300px]">
              <img :src="fotoModalUrl" alt="Foto en terreno" class="max-h-[70vh] object-contain rounded-lg" />
            </div>
            <div class="p-3 bg-slate-100 text-center text-xs font-medium text-slate-700">
              {{ fotoModalCliente }}
            </div>
          </div>
        </div>
      </Teleport>

    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import { usePreventaStore } from '@/Stores/preventaStore'
import PreventaFiltrosBar from './Cumplimiento/PreventaFiltrosBar.vue'
import { mapsConfig } from '@/Config/maps'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
  catalogo_rutas: {
    type: Array,
    default: () => []
  },
  canales: {
    type: Array,
    default: () => []
  },
  fecha_default: {
    type: String,
    default: ''
  }
})

const store = usePreventaStore()

// Estados y Filtros
const cargando = ref(false)
const criterioFecha = ref('preventa') // 'preventa' | 'entrega'
const estadoFiltro = ref('TODOS')     // 'TODOS' | 'ENTREGADO' | 'PARCIAL' | 'RECHAZADO' | 'PENDIENTE'
const busquedaTexto = ref('')
const vistaMovil = ref('panel')       // 'panel' | 'mapa'

const preventaSeleccionada = ref(null)

// Datos del backend
const totales = ref({
  total_preventas: 0,
  total_clientes: 0,
  monto_total_preventa: 0.0,
  monto_total_entregado: 0.0,
  unidades_total_preventa: 0,
  unidades_total_entregado: 0,
  efectividad_entrega_pct: 0.0,
  conteo_estados: {
    entregado: 0,
    parcial: 0,
    rechazado: 0,
    pendiente: 0,
  },
  con_coordenadas: 0,
  sin_coordenadas: 0,
})

const preventas = ref([])

// Foto Modal
const fotoModalUrl = ref(null)
const fotoModalCliente = ref('')

// Referencia Leaflet
const mapaContainerRef = ref(null)
let mapInstance = null
let tileLayerActual = null
let markersGroup = null
const capaMapaActiva = ref('openStreetMap')

// Filtrar preventas por texto de búsqueda y estado
const preventasFiltradas = computed(() => {
  let list = preventas.value || []

  if (estadoFiltro.value !== 'TODOS') {
    list = list.filter(p => p.estado === estadoFiltro.value)
  }

  if (busquedaTexto.value.trim() !== '') {
    const q = busquedaTexto.value.trim().toLowerCase()
    list = list.filter(p =>
      (p.cliente && p.cliente.toLowerCase().includes(q)) ||
      (p.codigo_cliente && String(p.codigo_cliente).toLowerCase().includes(q)) ||
      (p.nro_preventa && String(p.nro_preventa).includes(q)) ||
      (p.ruta && p.ruta.toLowerCase().includes(q))
    )
  }

  return list
})

function formatoMonto(val) {
  if (val === null || val === undefined || isNaN(val)) return '0.00'
  return Number(val).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

function cambiarCriterioFecha(nuevo) {
  if (criterioFecha.value === nuevo) return
  criterioFecha.value = nuevo
  cargarDatos()
}

function abrirFotoModal(url, cliente) {
  fotoModalUrl.value = url
  fotoModalCliente.value = cliente || 'Evidencia de Entrega'
}

function seleccionarPreventa(p) {
  preventaSeleccionada.value = p
  centrarEnMapa(p)
}

function centrarEnMapa(p) {
  if (!mapInstance || !p.latitud || !p.longitud) return
  mapInstance.setView([p.latitud, p.longitud], 16, { animate: true })
}

// Inicializar Mapa Leaflet
function inicializarMapa() {
  if (!mapaContainerRef.value || mapInstance) return

  const defaultCenter = [-16.5000, -68.1500]
  const defaultZoom = 13

  mapInstance = L.map(mapaContainerRef.value, {
    center: defaultCenter,
    zoom: defaultZoom,
    zoomControl: false,
    attributionControl: false,
  })

  L.control.zoom({ position: 'topright' }).addTo(mapInstance)
  establecerCapaBase(capaMapaActiva.value)

  markersGroup = L.featureGroup().addTo(mapInstance)

  actualizarMarcadoresEnMapa()
}

function establecerCapaBase(nombreCapa) {
  if (!mapInstance) return

  if (tileLayerActual) {
    mapInstance.removeLayer(tileLayerActual)
    tileLayerActual = null
  }

  const capaCfg = mapsConfig.baseLayers[nombreCapa] || mapsConfig.baseLayers.openStreetMap
  tileLayerActual = L.tileLayer(capaCfg.url, {
    maxZoom: capaCfg.maxZoom || 19,
    subdomains: capaCfg.subdomains || 'abc',
  }).addTo(mapInstance)

  capaMapaActiva.value = nombreCapa
}

function cambiarCapaMapa(nombreCapa) {
  establecerCapaBase(nombreCapa)
}

function actualizarMarcadoresEnMapa() {
  if (!mapInstance || !markersGroup) return

  markersGroup.clearLayers()

  const list = preventasFiltradas.value.filter(p => p.tiene_coords)

  if (list.length === 0) return

  list.forEach(p => {
    // Marcador circular estilizado con el color del estado de entrega
    const markerHtml = `
      <div style="
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background-color: ${p.color};
        border: 2px solid #ffffff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform 0.15s ease;
      ">
        <div style="width: 6px; height: 6px; border-radius: 50%; background-color: #ffffff;"></div>
      </div>
    `

    const customIcon = L.divIcon({
      html: markerHtml,
      className: 'preventa-marker-pin',
      iconSize: [18, 18],
      iconAnchor: [9, 9],
      popupAnchor: [0, -10],
    })

    const marker = L.marker([p.latitud, p.longitud], { icon: customIcon })

    // Popup rápido al hacer hover / click
    const popupHtml = `
      <div style="font-family: system-ui, sans-serif; font-size: 11px; padding: 4px; min-width: 180px;">
        <div style="font-weight: 800; color: #1d1d1f; margin-bottom: 2px;">${p.cliente}</div>
        <div style="color: #6e6e73; font-size: 10px; margin-bottom: 6px;">#${p.nro_preventa} · Ruta ${p.ruta}</div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
          <span>Pedido: <strong>Bs ${formatoMonto(p.monto_preventa)}</strong></span>
          <span style="font-weight: 700; color: ${p.color};">${p.estado_label}</span>
        </div>
        <div style="text-align: right;">
          <span style="color: #0071e3; font-weight: 700; cursor: pointer;">Clic para ver ítems &rarr;</span>
        </div>
      </div>
    `
    marker.bindPopup(popupHtml)

    marker.on('click', () => {
      seleccionarPreventa(p)
    })

    markersGroup.addLayer(marker)
  })

  // Ajustar vista del mapa para abarcar todos los marcadores
  if (markersGroup.getLayers().length > 0) {
    try {
      const bounds = markersGroup.getBounds()
      if (bounds.isValid()) {
        mapInstance.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 })
      }
    } catch {
      // Ignorar si bounds no son válidos
    }
  }
}

async function cargarDatos() {
  cargando.value = true
  try {
    const res = await axios.post(route('supervisor.preventas.entrega-preventas.data'), {
      fecha: store.fecha,
      canales: store.filtrosAplicados?.canales?.length ? store.filtrosAplicados.canales : store.canalesSeleccionados,
      rutas: store.filtrosAplicados?.rutas?.length ? store.filtrosAplicados.rutas : store.rutasSeleccionadas,
      estado_filtro: estadoFiltro.value,
      criterio_fecha: criterioFecha.value,
    })

    const data = res.data || {}
    totales.value = data.totales || totales.value
    preventas.value = data.preventas || []

    nextTick(() => {
      actualizarMarcadoresEnMapa()
    })
  } catch (err) {
    console.error('[EntregaPreventas] Error cargando datos de entrega:', err)
  } finally {
    cargando.value = false
  }
}

// Watchers
watch(
  [() => store.fecha, () => store.canalesSeleccionados, () => store.rutasSeleccionadas],
  () => {
    cargarDatos()
  },
  { deep: true }
)

watch(
  () => estadoFiltro.value,
  () => {
    nextTick(() => {
      actualizarMarcadoresEnMapa()
    })
  }
)

onMounted(() => {
  if (!store.fecha && props.fecha_default) {
    store.fecha = props.fecha_default
  }
  inicializarMapa()
  cargarDatos()
})

onUnmounted(() => {
  if (mapInstance) {
    mapInstance.remove()
    mapInstance = null
  }
})
</script>

<style scoped>
.custom-scroll::-webkit-scrollbar {
  width: 5px;
  height: 5px;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.15);
  border-radius: 4px;
}
:deep(.leaflet-popup-content-wrapper) {
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
  border: 1px solid rgba(0, 0, 0, 0.08);
}
</style>
