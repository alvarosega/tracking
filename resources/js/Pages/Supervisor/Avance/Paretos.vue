<template>
  <SupervisorLayout title="Avance > Paretos & Scoring Multicriterio">
    <div class="space-y-2.5 font-sans select-none">
      <!-- ── Subnavegación de Módulo Avance ── -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- ── Barra de Filtros Centralizada (Fechas, Canales, Rutas, Búsqueda) ── -->
      <AvanceFiltrosBar @consultar="ejecutarConsulta" />

      <!-- ── Barra Superior: Presets Split, Filtros Contextuales y Conteo ── -->
      <section class="relative z-20 apple-glass-card rounded-[14px] p-2 shadow-xs flex flex-wrap items-center justify-between gap-2 select-none border border-black/[0.06]">
        <!-- Presets de División Pantalla Split -->
        <div class="flex items-center gap-1.5">
          <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider hidden sm:inline">Vista Split:</span>
          <div class="inline-flex apple-segmented p-0.5 rounded-[8px] text-[11px]">
            <button
              type="button"
              @click="setPresetLayout(70)"
              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer font-bold flex items-center gap-1"
              :class="anchoTablaPct === 70
                ? 'apple-segmented-active text-[#1D1D1F]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              title="70% Tabla / 30% Mapa"
            >
              <span class="text-[12px] leading-none">◧</span>
              <span>70/30</span>
            </button>
            <button
              type="button"
              @click="setPresetLayout(50)"
              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer font-bold flex items-center gap-1"
              :class="anchoTablaPct === 50
                ? 'apple-segmented-active text-[#1D1D1F]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              title="50% Tabla / 50% Mapa (Balanceado)"
            >
              <span class="text-[12px] leading-none">◫</span>
              <span>50/50</span>
            </button>
            <button
              type="button"
              @click="setPresetLayout(30)"
              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer font-bold flex items-center gap-1"
              :class="anchoTablaPct === 30
                ? 'apple-segmented-active text-[#1D1D1F]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              title="30% Tabla / 70% Mapa"
            >
              <span class="text-[12px] leading-none">◨</span>
              <span>30/70</span>
            </button>
            <button
              type="button"
              @click="setPresetLayout(100)"
              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer font-bold flex items-center gap-1"
              :class="anchoTablaPct === 100
                ? 'apple-segmented-active text-[#0071E3]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              title="Maximizar Tabla (Ocultar Mapa)"
            >
              <span class="text-[11px] leading-none">▤</span>
              <span class="hidden md:inline">Solo Tabla</span>
            </button>
            <button
              type="button"
              @click="setPresetLayout(0)"
              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer font-bold flex items-center gap-1"
              :class="anchoTablaPct === 0
                ? 'apple-segmented-active text-[#0071E3]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              title="Maximizar Mapa (Ocultar Tabla)"
            >
              <span class="text-[11px] leading-none">🗺</span>
              <span class="hidden md:inline">Solo Mapa</span>
            </button>
          </div>
        </div>

        <!-- Filtros Contextuales de Pareto -->
        <div class="flex items-center gap-2 text-[11px]">
          <!-- Selector de Clase Pareto -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[8px] px-2.5 py-1">
            <label class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Pareto:</label>
            <select
              v-model="filtroClase"
              class="bg-transparent font-bold text-[#1D1D1F] cursor-pointer focus:outline-none"
            >
              <option value="TODAS">Todas (A + B + C)</option>
              <option value="A">Solo A (Top 80%)</option>
              <option value="B">Solo B (Medio 15%)</option>
              <option value="C">Solo C (Bajo 5%)</option>
            </select>
          </div>

          <!-- Selector Tipo de Negocio -->
          <div
            v-if="tiposNegocioDisponibles.length > 0"
            class="flex items-center gap-1.5 apple-control-pill rounded-[8px] px-2.5 py-1"
          >
            <label class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Negocio:</label>
            <select
              v-model="filtroTipoNegocio"
              class="bg-transparent text-[11px] font-bold text-[#1D1D1F] cursor-pointer focus:outline-none max-w-[120px] truncate"
            >
              <option value="">Todos</option>
              <option v-for="t in tiposNegocioDisponibles" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>

          <!-- Toggle Inactivos -->
          <label class="flex items-center gap-1.5 px-2.5 py-1 rounded-[8px] apple-control-pill cursor-pointer text-[11px] font-semibold text-[#1D1D1F] select-none">
            <input
              type="checkbox"
              v-model="mostrarInactivos"
              class="rounded border-[#D2D2D7] text-[#0071E3] focus:ring-0 cursor-pointer"
            />
            <span class="hidden sm:inline">Mostrar Inactivos</span>
            <span class="sm:hidden">Inactivos</span>
          </label>
        </div>

        <!-- Universo Evaluado Badge -->
        <div class="flex items-center gap-2 text-[11px]">
          <span class="px-2.5 py-1 rounded-[8px] bg-sky-50 border border-sky-200 text-[#0071E3] font-bold font-mono">
            Evaluado: <strong class="text-[#0071E3]">{{ clientesFiltrados.length }}</strong> cl.
          </span>
        </div>
      </section>

      <!-- SKELETON LOADER -->
      <div v-if="cargando" class="apple-glass-card rounded-[14px] p-4 animate-pulse space-y-2">
        <div class="h-8 bg-black/[0.04] rounded-lg"></div>
        <div v-for="row in 7" :key="row" class="h-10 bg-black/[0.02] rounded-lg flex items-center px-3 gap-3">
          <div class="h-3 bg-black/[0.08] rounded w-8"></div>
          <div class="h-3 bg-black/[0.08] rounded w-28"></div>
          <div class="h-3 bg-black/[0.08] rounded w-16"></div>
          <div class="h-3 bg-black/[0.08] rounded w-20"></div>
          <div class="h-3 bg-black/[0.08] rounded flex-1"></div>
        </div>
      </div>

      <!-- Error Banner -->
      <div v-else-if="errorMensaje" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold text-center">
        {{ errorMensaje }}
      </div>

      <!-- CONTENEDOR SPLIT: TABLA MULTICRITERIO + MAPA GEOESPACIAL GIS -->
      <div
        v-show="!cargando"
        id="paretoSplitContainer"
        class="relative z-10 flex flex-col lg:flex-row items-stretch w-full min-h-[640px] h-[calc(100vh-210px)] max-h-[calc(100vh-200px)] rounded-[14px] border border-black/[0.08] shadow-xs overflow-hidden bg-white/95"
      >
        <!-- PANEL IZQUIERDO: GRID MULTICRITERIO DE CARTERA -->
        <section
          v-show="anchoTablaPct > 0"
          class="flex flex-col h-full overflow-hidden transition-[width] duration-75 relative bg-white"
          :style="{ width: anchoTablaPct + '%' }"
        >
          <!-- Encabezado del Panel Tabla -->
          <div class="px-3 py-2 bg-slate-50/90 border-b border-black/[0.06] flex items-center justify-between gap-2 select-none shrink-0">
            <div class="flex items-center gap-2 min-w-0">
              <span class="w-2 h-2 rounded-full bg-[#0071E3] shrink-0"></span>
              <span class="font-bold text-[12px] text-[#1D1D1F] truncate">Grid Multicriterio de Clientes</span>
              <span class="text-[10px] font-mono text-[#86868B] hidden sm:inline">
                ({{ clientesFiltrados.length }} clientes)
              </span>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
              <button
                v-if="anchoTablaPct < 100"
                type="button"
                @click="setPresetLayout(100)"
                class="px-2 py-0.5 rounded border border-black/[0.08] bg-white hover:bg-slate-50 text-[10px] font-bold text-[#1D1D1F] cursor-pointer shadow-2xs"
                title="Expandir tabla al 100%"
              >
                Maximizar ▤
              </button>
              <button
                type="button"
                @click="setPresetLayout(0)"
                class="w-6 h-6 rounded border border-black/[0.08] bg-white hover:bg-slate-100 text-[#86868B] hover:text-[#1D1D1F] text-xs font-bold flex items-center justify-center cursor-pointer shadow-2xs"
                title="Minimizar tabla (ocultar)"
              >
                «
              </button>
            </div>
          </div>

          <!-- Tabla con Scroll Interno y Encabezado Fijo -->
          <div class="flex-1 overflow-x-auto overflow-y-auto custom-scroll relative">
            <table v-if="clientesFiltrados.length > 0" class="w-full text-left border-separate border-spacing-0 font-sans text-[11px]">
              <thead class="bg-slate-100 text-[#6E6E73] font-bold text-[9px] uppercase tracking-tight border-b border-black/[0.08] sticky top-0 z-20 select-none shadow-xs">
                <tr>
                  <th class="py-2 px-2 border-b border-r border-black/[0.06] w-7 text-center">#</th>
                  <th class="py-2 px-2 border-b border-r border-black/[0.06] w-10 text-center">Clase</th>
                  <th class="py-2 px-2.5 border-b border-r border-black/[0.06] min-w-[160px]">Cliente</th>
                  <th class="py-2 px-2 border-b border-r border-black/[0.06] w-16">Ruta</th>
                  <th class="py-2 px-2.5 border-b border-r border-black/[0.06] text-right min-w-[95px]" title="Monto Facturado en Bs MTD">Monto Bs</th>
                  <th class="py-2 px-2 border-b border-r border-black/[0.06] text-right min-w-[80px]" title="Unidades Físicas y Cajas Físicas">Unid / Cj</th>
                  <th class="py-2 px-2 border-b border-r border-black/[0.06] text-center min-w-[65px]" title="Facturas efectivas registradas">Facturas</th>
                  <th class="py-2 px-2 border-b border-r border-black/[0.06] text-center min-w-[70px]" title="Penetración de Portafolio: SKUs y Marcas distintas">Portafolio</th>
                  <th class="py-2 px-2 border-b border-r border-black/[0.06] text-right min-w-[80px]" title="Drop Size Promedio por Factura">Drop Size</th>
                  <th class="py-2 px-2 border-b border-r border-black/[0.06] text-center min-w-[65px]" title="Consistencia Histórica: Meses activos en 6M">Consist.</th>
                  <th class="py-2 px-2 border-b border-r border-black/[0.06] text-right min-w-[65px]" title="Variación vs Promedio 2 Meses">vs L2M</th>
                  <th class="py-2 px-2 border-b border-r border-black/[0.06] text-center w-14">GIS</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.04] bg-white">
                <tr
                  v-for="(c, idx) in clientesFiltrados"
                  :key="c.cliente_id"
                  @click="enfocarClienteEnMapa(c)"
                  class="hover:bg-blue-50/50 transition-colors cursor-pointer group"
                  :class="{ 'bg-blue-50/80 ring-1 ring-inset ring-[#0071E3] font-medium': clienteSeleccionadoId === c.cliente_id }"
                >
                  <!-- # -->
                  <td class="py-1.5 px-2 text-center text-[#86868B] text-[10px] border-r border-black/[0.04] font-mono">
                    {{ idx + 1 }}
                  </td>

                  <!-- Clase Badge -->
                  <td class="py-1.5 px-1.5 text-center border-r border-black/[0.04]">
                    <span
                      class="px-1.5 py-0.5 rounded text-[10px] font-bold font-mono"
                      :class="getClaseParetoBadge(c.clasificacion || c._clase_monto)"
                    >
                      {{ c.clasificacion || c._clase_monto || 'C' }}
                    </span>
                  </td>

                  <!-- Cliente -->
                  <td class="py-1.5 px-2.5 border-r border-black/[0.04]">
                    <div class="font-bold text-[#1D1D1F] truncate max-w-[200px]" :title="c.cliente">
                      {{ c.cliente }}
                    </div>
                    <div class="text-[9px] text-[#86868B] flex items-center gap-1 font-mono">
                      <span>ID: {{ c.cliente_id }}</span>
                      <span v-if="c.estado_cliente === 'INACTIVO'" class="px-1 py-0.2 rounded bg-rose-50 text-rose-700 font-bold">
                        INACTIVO
                      </span>
                    </div>
                  </td>

                  <!-- Ruta -->
                  <td class="py-1.5 px-2 text-[#1D1D1F] border-r border-black/[0.04] font-bold whitespace-nowrap text-[10px]">
                    <div>{{ c.ruta }}</div>
                    <div class="text-[8.5px] text-[#86868B] font-mono">{{ c.canal || 'S/C' }}</div>
                  </td>

                  <!-- Monto Bs MTD -->
                  <td class="py-1.5 px-2.5 text-right font-bold text-[#1D1D1F] border-r border-black/[0.04] whitespace-nowrap font-mono">
                    <div>Bs {{ formatNum(c.venta_mtd) }}</div>
                    <div class="text-[8.5px] text-[#86868B] font-mono">{{ (c.pct_acumulado || 0).toFixed(1) }}%</div>
                  </td>

                  <!-- Unidades / Cajas -->
                  <td class="py-1.5 px-2 text-right font-mono text-[#1D1D1F] border-r border-black/[0.04] whitespace-nowrap text-[10px]">
                    <div><strong>{{ c.unidades || 0 }}</strong> u.</div>
                    <div class="text-[8.5px] text-[#86868B]">{{ formatNum(c.cajas) }} cj.</div>
                  </td>

                  <!-- Facturas Efectivas (Frecuencia) -->
                  <td class="py-1.5 px-1.5 text-center border-r border-black/[0.04] whitespace-nowrap">
                    <button
                      type="button"
                      @click.stop="abrirModalFrecuencia(c)"
                      class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded hover:bg-slate-100 text-[#1D1D1F] cursor-pointer font-mono text-[10px]"
                      title="Ver detalle de facturas efectivas"
                    >
                      <span class="font-bold">{{ c.facturas_efectivas || 0 }} fac.</span>
                      <span class="text-[8px] font-bold px-1 rounded" :class="getMicroBadge(c._clase_frec)">
                        {{ c._clase_frec || 'C' }}
                      </span>
                    </button>
                  </td>

                  <!-- Portafolio (SKUs y Marcas) -->
                  <td class="py-1.5 px-1.5 text-center border-r border-black/[0.04] whitespace-nowrap">
                    <button
                      type="button"
                      @click.stop="abrirModalAmplitud(c)"
                      class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded hover:bg-slate-100 text-[#1D1D1F] cursor-pointer font-mono text-[10px]"
                      title="Ver desglose de SKUs y Marcas"
                    >
                      <span><strong>{{ c.skus_distintos || 0 }}</strong> sku</span>
                      <span class="text-slate-400">/</span>
                      <span><strong>{{ c.marcas_distintas || 0 }}</strong> mrc</span>
                    </button>
                  </td>

                  <!-- Drop Size -->
                  <td class="py-1.5 px-2 text-right font-mono text-[#1D1D1F] border-r border-black/[0.04] whitespace-nowrap text-[10px]">
                    <span>Bs {{ formatNum(c.drop_size) }}</span>
                  </td>

                  <!-- Consistencia Histórica -->
                  <td class="py-1.5 px-1.5 text-center border-r border-black/[0.04] whitespace-nowrap font-mono text-[10px]">
                    <span
                      class="px-1.5 py-0.5 rounded font-bold"
                      :class="(c.meses_activos_6m || 0) >= 5 ? 'bg-emerald-50 text-emerald-800' : ((c.meses_activos_6m || 0) >= 3 ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600')"
                      :title="`Activo ${c.meses_activos_6m || 0} de 6 meses (6M) y ${c.meses_activos_12m || 0} de 12 meses (12M)`"
                    >
                      {{ c.meses_activos_6m || 0 }}m / 6m
                    </span>
                  </td>

                  <!-- vs L2M -->
                  <td class="py-1.5 px-2 text-right border-r border-black/[0.04] whitespace-nowrap">
                    <span class="text-[10px] font-bold font-mono" :class="getClaseTendencia(c.var_l2m)">
                      {{ formatVarPct(c.var_l2m) }}
                    </span>
                  </td>

                  <!-- GIS Acciones -->
                  <td class="py-1.5 px-1.5 text-center whitespace-nowrap" @click.stop>
                    <div class="flex items-center justify-center gap-1">
                      <button
                        v-if="c.latitud && c.longitud"
                        type="button"
                        @click="enfocarClienteEnMapa(c)"
                        class="p-1 text-[#0071E3] hover:bg-sky-50 rounded cursor-pointer transition-colors"
                        title="Enfocar cliente en el Mapa"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                      </button>
                      <button
                        v-if="c.tiene_fotos"
                        type="button"
                        @click="abrirModalFotos(c)"
                        class="p-1 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded cursor-pointer transition-colors"
                        title="Ver evidencia fotográfica"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <div v-else class="p-10 text-center text-[#86868B] text-xs">
              No se encontraron clientes con los filtros seleccionados.
            </div>
          </div>

          <!-- Barra Inferior de la Tabla -->
          <div class="px-3 py-1.5 bg-slate-50 border-t border-black/[0.06] flex items-center justify-between gap-2 text-[10.5px] select-none font-mono shrink-0">
            <div class="text-[#86868B]">
              Mostrando: <strong class="text-[#0071E3] font-bold">{{ clientesFiltrados.length }}</strong> clientes
            </div>
            <div class="text-[#86868B] text-[10px]">
              Vista reactiva en memoria
            </div>
          </div>
        </section>

        <!-- DIVISOR DRAGGABLE (SPLITTER ENTRE TABLA Y MAPA) -->
        <div
          v-if="anchoTablaPct > 0 && anchoTablaPct < 100"
          @mousedown.prevent="iniciarArrastreSplitter"
          class="w-2.5 hover:w-3 bg-slate-200 hover:bg-[#0071E3] transition-all cursor-col-resize select-none flex items-center justify-center shrink-0 z-30 group"
          title="Arrastra para redimensionar tabla y mapa (Doble clic para 50/50)"
          @dblclick="setPresetLayout(50)"
        >
          <div class="w-1 h-8 bg-slate-400 group-hover:bg-white rounded-full transition-colors flex flex-col justify-between py-1">
            <span class="w-0.5 h-0.5 bg-slate-600 group-hover:bg-slate-800 rounded-full mx-auto"></span>
            <span class="w-0.5 h-0.5 bg-slate-600 group-hover:bg-slate-800 rounded-full mx-auto"></span>
            <span class="w-0.5 h-0.5 bg-slate-600 group-hover:bg-slate-800 rounded-full mx-auto"></span>
          </div>
        </div>

        <!-- PANEL DERECHO: MAPA GEOESPACIAL GIS (5 MAPAS DE CALOR ANALÍTICOS) -->
        <section
          v-show="anchoTablaPct < 100"
          class="flex flex-col h-full overflow-hidden transition-[width] duration-75 relative bg-slate-100"
          :style="{ width: (100 - anchoTablaPct) + '%' }"
        >
          <!-- Barra de Control Analítica del Mapa -->
          <div class="p-2 bg-white/95 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 text-[11px] select-none shrink-0 z-20">
            <!-- Selector Principal de los 5 Mapas de Calor -->
            <div class="flex items-center gap-1.5">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Mapa Analítico:</span>
              <select
                v-model="criterioColorMapa"
                @change="alCambiarCriterioMapa"
                class="apple-control-pill rounded-[8px] px-2.5 py-1 text-[11px] font-bold text-[#1D1D1F] cursor-pointer focus:outline-none"
              >
                <option value="volumen">1. Volumen Facturado (Bs / Unid / Cajas)</option>
                <option value="frecuencia">2. Frecuencia de Compra (Facturas Efectivas)</option>
                <option value="penetracion">3. Penetración de Portafolio (SKUs y Marcas)</option>
                <option value="drop_size">4. Drop Size Promedio (Bs / Factura)</option>
                <option value="consistencia">5. Consistencia Histórica (Meses Activos)</option>
              </select>
            </div>

            <!-- Sub-selectores Específicos según el Mapa de Calor Activo -->
            <div class="flex items-center gap-1.5">
              <!-- Sub-selector Volumen -->
              <div v-if="criterioColorMapa === 'volumen'" class="inline-flex apple-segmented p-0.5 rounded-[7px] text-[10.5px]">
                <button
                  type="button"
                  @click="subMetricaVolumen = 'bs'; actualizarCapasMapa(false)"
                  class="px-2 py-0.5 rounded-[5px] cursor-pointer font-bold transition-all"
                  :class="subMetricaVolumen === 'bs' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#86868B] hover:text-[#1D1D1F]'"
                >
                  Bs
                </button>
                <button
                  type="button"
                  @click="subMetricaVolumen = 'unidades'; actualizarCapasMapa(false)"
                  class="px-2 py-0.5 rounded-[5px] cursor-pointer font-bold transition-all"
                  :class="subMetricaVolumen === 'unidades' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#86868B] hover:text-[#1D1D1F]'"
                >
                  Unidades
                </button>
                <button
                  type="button"
                  @click="subMetricaVolumen = 'cajas'; actualizarCapasMapa(false)"
                  class="px-2 py-0.5 rounded-[5px] cursor-pointer font-bold transition-all"
                  :class="subMetricaVolumen === 'cajas' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#86868B] hover:text-[#1D1D1F]'"
                >
                  Cajas
                </button>
              </div>

              <!-- Sub-selector Penetración -->
              <div v-if="criterioColorMapa === 'penetracion'" class="inline-flex apple-segmented p-0.5 rounded-[7px] text-[10.5px]">
                <button
                  type="button"
                  @click="subMetricaPenetracion = 'skus'; actualizarCapasMapa(false)"
                  class="px-2 py-0.5 rounded-[5px] cursor-pointer font-bold transition-all"
                  :class="subMetricaPenetracion === 'skus' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#86868B] hover:text-[#1D1D1F]'"
                >
                  SKUs
                </button>
                <button
                  type="button"
                  @click="subMetricaPenetracion = 'marcas'; actualizarCapasMapa(false)"
                  class="px-2 py-0.5 rounded-[5px] cursor-pointer font-bold transition-all"
                  :class="subMetricaPenetracion === 'marcas' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#86868B] hover:text-[#1D1D1F]'"
                >
                  Marcas
                </button>
              </div>

              <!-- Sub-selector Consistencia -->
              <div v-if="criterioColorMapa === 'consistencia'" class="inline-flex apple-segmented p-0.5 rounded-[7px] text-[10.5px]">
                <button
                  type="button"
                  @click="subMetricaConsistencia = '6m'; actualizarCapasMapa(false)"
                  class="px-2 py-0.5 rounded-[5px] cursor-pointer font-bold transition-all"
                  :class="subMetricaConsistencia === '6m' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#86868B] hover:text-[#1D1D1F]'"
                >
                  6 Meses
                </button>
                <button
                  type="button"
                  @click="subMetricaConsistencia = '12m'; actualizarCapasMapa(false)"
                  class="px-2 py-0.5 rounded-[5px] cursor-pointer font-bold transition-all"
                  :class="subMetricaConsistencia === '12m' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#86868B] hover:text-[#1D1D1F]'"
                >
                  12 Meses
                </button>
              </div>
            </div>

            <!-- Modos de Capas (Calor + Puntos) -->
            <div class="flex items-center gap-2">
              <label class="flex items-center gap-1 cursor-pointer font-bold text-[#1D1D1F] text-[10.5px]">
                <input
                  type="checkbox"
                  v-model="mapaCapas.calor"
                  @change="actualizarCapasMapa(false)"
                  class="rounded text-[#0071E3] focus:ring-0 cursor-pointer"
                />
                <span>Mancha Térmica</span>
              </label>
              <label class="flex items-center gap-1 cursor-pointer font-bold text-[#1D1D1F] text-[10.5px]">
                <input
                  type="checkbox"
                  v-model="mapaCapas.puntos"
                  @change="actualizarCapasMapa(false)"
                  class="rounded text-[#0071E3] focus:ring-0 cursor-pointer"
                />
                <span>Puntos Clientes</span>
              </label>

              <!-- Menú Desplegable de Rangos y Colores -->
              <div ref="refMenuCapas" class="relative inline-block text-left" :class="menuCapasAbierto ? 'z-[1001]' : 'z-20'">
                <button
                  type="button"
                  @click="menuCapasAbierto = !menuCapasAbierto"
                  class="flex items-center gap-1 apple-control-pill rounded-[6px] px-2 py-0.5 text-[10.5px] font-bold text-[#1D1D1F] cursor-pointer"
                >
                  <span>{{ resumenTextoBotonCapas }}</span>
                  <svg class="w-3 h-3 text-[#86868B] transition-transform" :class="{ 'rotate-180': menuCapasAbierto }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>

                <div
                  v-if="menuCapasAbierto"
                  class="absolute right-0 top-full mt-1.5 w-64 glass-dropdown rounded-xl shadow-float z-[1002] p-2.5 space-y-2 border border-black/[0.08]"
                >
                  <div class="flex justify-between items-center pb-1.5 border-b border-black/[0.06] text-[10px] font-bold text-[#86868B]">
                    <span>{{ configCriterioActual.titulo }}</span>
                    <div class="space-x-1.5">
                      <button type="button" @click="seleccionarTodasRangos(true)" class="text-[#0071E3] hover:underline cursor-pointer">Todas</button>
                      <span>|</span>
                      <button type="button" @click="seleccionarTodasRangos(false)" class="text-[#0071E3] hover:underline cursor-pointer">Ninguna</button>
                    </div>
                  </div>
                  <div class="space-y-1 text-[11px]">
                    <label
                      v-for="op in configCriterioActual.opciones"
                      :key="op.key"
                      class="flex items-center justify-between p-1.5 rounded hover:bg-slate-50 cursor-pointer"
                    >
                      <div class="flex items-center gap-2">
                        <input
                          type="checkbox"
                          v-model="capasRangosActivas[op.key]"
                          @change="actualizarCapasMapa(false)"
                          class="rounded text-[#0071E3] focus:ring-0 cursor-pointer"
                        />
                        <span class="font-bold text-[#1D1D1F] text-[10.5px]">{{ op.label }}</span>
                      </div>
                      <span
                        class="w-3 h-3 rounded-full border border-black/20 shrink-0"
                        :style="{ backgroundColor: op.color }"
                      ></span>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Botones de Centrar / Maximizar Mapa -->
              <button
                type="button"
                @click="centrarMapa"
                class="px-2 py-0.5 rounded border border-black/[0.08] bg-white hover:bg-slate-50 text-[10px] font-bold text-[#1D1D1F] cursor-pointer shadow-2xs"
                title="Reajustar y centrar vista del mapa a los clientes"
              >
                Centrar ⌖
              </button>
              <button
                v-if="anchoTablaPct > 0"
                type="button"
                @click="setPresetLayout(0)"
                class="px-2 py-0.5 rounded border border-black/[0.08] bg-white hover:bg-slate-50 text-[10px] font-bold text-[#1D1D1F] cursor-pointer shadow-2xs"
                title="Expandir mapa al 100%"
              >
                Maximizar 🗺
              </button>
              <button
                type="button"
                @click="setPresetLayout(100)"
                class="w-6 h-6 rounded border border-black/[0.08] bg-white hover:bg-slate-100 text-[#86868B] hover:text-[#1D1D1F] text-xs font-bold flex items-center justify-center cursor-pointer shadow-2xs"
                title="Minimizar mapa (ocultar)"
              >
                »
              </button>
            </div>
          </div>

          <!-- Contenedor del Mapa Leaflet -->
          <div class="flex-1 w-full relative overflow-hidden z-0">
            <div id="mapaCalorParetoContainer" class="w-full h-full bg-slate-100 relative z-0"></div>

            <!-- Leyenda Flotante Dinámica: Cantidad de Clientes por Rango en Mancha -->
            <div
              class="absolute bottom-3 left-3 bg-white/95 backdrop-blur-md border border-black/[0.08] rounded-xl shadow-float text-[11px] z-[400] transition-all duration-200 max-w-[310px] select-none pointer-events-auto"
            >
              <!-- Encabezado de la Leyenda -->
              <div class="px-2.5 py-1.5 border-b border-black/[0.06] flex items-center justify-between gap-2.5 bg-slate-50/70 rounded-t-xl">
                <div class="flex items-center gap-1.5 min-w-0">
                  <span
                    class="w-2.5 h-2.5 rounded-full shrink-0"
                    :class="mapaCapas.calor ? 'bg-rose-500 animate-pulse' : (mapaCapas.puntos ? 'bg-[#0071E3]' : 'bg-slate-300')"
                  ></span>
                  <div class="min-w-0">
                    <div class="font-bold text-[#1D1D1F] text-[11px] leading-tight truncate">{{ configCriterioActual.titulo }}</div>
                    <div class="text-[9px] text-[#86868B]">Distribución de clientes</div>
                  </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                  <span class="text-[9.5px] font-mono font-bold px-1.5 py-0.5 rounded bg-white border border-black/[0.08] text-[#1D1D1F]">
                    {{ formatEntero(totalUsuariosCalorActivos) }} cl.
                  </span>
                  <button
                    type="button"
                    @click="leyendaCalorColapsada = !leyendaCalorColapsada"
                    class="w-5 h-5 rounded hover:bg-slate-200/60 flex items-center justify-center text-[#86868B] hover:text-[#1D1D1F] text-xs font-bold transition-colors cursor-pointer"
                    :title="leyendaCalorColapsada ? 'Expandir leyenda' : 'Minimizar leyenda'"
                  >
                    {{ leyendaCalorColapsada ? '+' : '–' }}
                  </button>
                </div>
              </div>

              <!-- Contenido Desplegado de la Leyenda -->
              <div v-show="!leyendaCalorColapsada" class="p-2.5 space-y-2">
                <div class="space-y-1">
                  <div
                    v-for="op in configCriterioActual.opciones"
                    :key="op.key"
                    class="flex items-center justify-between gap-2 p-1 rounded hover:bg-slate-50 transition-colors cursor-pointer border border-transparent hover:border-black/[0.04]"
                    :class="{ 'opacity-40 line-through bg-slate-50/50': capasRangosActivas[op.key] === false }"
                    @click="conmutarRangoDesdeLeyenda(op.key)"
                    :title="capasRangosActivas[op.key] === false ? 'Haz clic para activar' : 'Haz clic para ocultar'"
                  >
                    <div class="flex items-center gap-1.5 min-w-0">
                      <span
                        class="w-3 h-3 rounded-full border border-black/20 shrink-0"
                        :style="{ backgroundColor: op.color }"
                      ></span>
                      <span class="text-[10px] font-medium text-[#1D1D1F] truncate">{{ op.label }}</span>
                    </div>
                    <div class="flex items-center gap-1 shrink-0 font-mono text-[10px]">
                      <span class="font-bold text-[#1D1D1F]">{{ formatEntero(conteoUsuariosPorRango[op.key] || 0) }}</span>
                      <span class="text-[9px] text-[#86868B]">
                        ({{ calcularPorcentaje(conteoUsuariosPorRango[op.key], totalUsuariosCalorTotal) }}%)
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Pie de desglose métrico -->
                <div class="pt-1.5 border-t border-black/[0.06] flex items-center justify-between text-[9.5px] text-[#86868B] font-mono">
                  <span>Puntos con GPS:</span>
                  <span class="font-bold text-[#1D1D1F]">
                    {{ formatEntero(totalUsuariosCalorActivos) }} / {{ formatEntero(totalUsuariosCalorTotal) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- MODAL 1: AMPLITUD DE SKUs Y MARCAS (Productos Comprados) -->
      <div
        v-if="modalAmplitudVisible"
        class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
        @click.self="cerrarModalAmplitud"
      >
        <div class="bg-white rounded-2xl shadow-float max-w-2xl w-full p-4 space-y-3 max-h-[85vh] flex flex-col border border-black/[0.08]">
          <div class="flex items-center justify-between pb-2 border-b border-black/[0.06]">
            <div>
              <h3 class="text-[13px] font-bold text-[#1D1D1F]">3. Penetración de Portafolio: SKUs y Marcas Compradas</h3>
              <p class="text-[10px] text-[#86868B]">
                ID: <strong>{{ clienteAmplitudActivo?.cliente_id }}</strong> - {{ clienteAmplitudActivo?.cliente }} (Ruta: {{ clienteAmplitudActivo?.ruta }})
              </p>
            </div>
            <button type="button" @click="cerrarModalAmplitud" class="text-[#86868B] hover:text-[#1D1D1F] font-bold text-sm cursor-pointer">✕</button>
          </div>

          <div v-if="cargandoModalAmplitud" class="py-8 text-center text-[#86868B] text-xs animate-pulse">
            Cargando productos comprados...
          </div>

          <div v-else-if="itemsAmplitud.length > 0" class="overflow-y-auto custom-scroll flex-1">
            <table class="w-full text-left text-[11px] font-mono border-separate border-spacing-0">
              <thead class="bg-slate-100 text-[#6E6E73] font-bold text-[9px] uppercase sticky top-0 z-10 border-b border-black/[0.06]">
                <tr>
                  <th class="py-1.5 px-2.5">Jerarquía 1</th>
                  <th class="py-1.5 px-2.5">Marca (J3)</th>
                  <th class="py-1.5 px-2.5">Producto</th>
                  <th class="py-1.5 px-2.5 text-right">Unidades</th>
                  <th class="py-1.5 px-2.5 text-right">Total Bs</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.04]">
                <tr v-for="(it, itIdx) in itemsAmplitud" :key="itIdx" class="hover:bg-slate-50">
                  <td class="py-1.5 px-2.5 text-[#86868B] text-[10px] font-bold">{{ it.j1 }}</td>
                  <td class="py-1.5 px-2.5 font-bold text-[#0071E3] text-[10px]">{{ it.j3 || 'Sin Marca' }}</td>
                  <td class="py-1.5 px-2.5 font-medium text-[#1D1D1F]">{{ it.producto }}</td>
                  <td class="py-1.5 px-2.5 text-right font-medium text-[#1D1D1F]">{{ it.unidades }}</td>
                  <td class="py-1.5 px-2.5 text-right font-bold text-[#1D1D1F]">Bs {{ formatNum(it.total_bs) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="py-8 text-center text-[#86868B] text-xs">
            No se registraron productos comprados en este período.
          </div>
        </div>
      </div>

      <!-- MODAL 2: FRECUENCIA DE COMPRA (Facturas por Fecha) -->
      <div
        v-if="modalFrecuenciaVisible"
        class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
        @click.self="cerrarModalFrecuencia"
      >
        <div class="bg-white rounded-2xl shadow-float max-w-xl w-full p-4 space-y-3 max-h-[85vh] flex flex-col border border-black/[0.08]">
          <div class="flex items-center justify-between pb-2 border-b border-black/[0.06]">
            <div>
              <h3 class="text-[13px] font-bold text-[#1D1D1F]">2. Frecuencia de Compra: Facturas Efectivas</h3>
              <p class="text-[10px] text-[#86868B]">
                ID: <strong>{{ clienteFrecuenciaActivo?.cliente_id }}</strong> - {{ clienteFrecuenciaActivo?.cliente }} (Ruta: {{ clienteFrecuenciaActivo?.ruta }})
              </p>
            </div>
            <button type="button" @click="cerrarModalFrecuencia" class="text-[#86868B] hover:text-[#1D1D1F] font-bold text-sm cursor-pointer">✕</button>
          </div>

          <div v-if="cargandoModalFrecuencia" class="py-8 text-center text-[#86868B] text-xs animate-pulse">
            Cargando pedidos del cliente...
          </div>

          <div v-else-if="itemsFrecuencia.length > 0" class="overflow-y-auto custom-scroll flex-1">
            <table class="w-full text-left text-[11px] font-mono border-separate border-spacing-0">
              <thead class="bg-slate-100 text-[#6E6E73] font-bold text-[9px] uppercase sticky top-0 z-10 border-b border-black/[0.06]">
                <tr>
                  <th class="py-1.5 px-2.5">Fecha</th>
                  <th class="py-1.5 px-2.5">Factura / Ticket</th>
                  <th class="py-1.5 px-2.5 text-center">SKUs</th>
                  <th class="py-1.5 px-2.5 text-right">Unidades</th>
                  <th class="py-1.5 px-2.5 text-right">Total Bs</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.04]">
                <tr v-for="(f, fIdx) in itemsFrecuencia" :key="fIdx" class="hover:bg-slate-50">
                  <td class="py-1.5 px-2.5 font-bold text-[#1D1D1F]">{{ f.fecha }}</td>
                  <td class="py-1.5 px-2.5 text-[#86868B] font-mono">{{ f.nro_factura || '-' }}</td>
                  <td class="py-1.5 px-2.5 text-center font-medium text-[#1D1D1F]">{{ f.skus_dia }}</td>
                  <td class="py-1.5 px-2.5 text-right font-medium text-[#1D1D1F]">{{ f.unidades_dia }}</td>
                  <td class="py-1.5 px-2.5 text-right font-bold text-[#1D1D1F]">Bs {{ formatNum(f.total_bs) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="py-8 text-center text-[#86868B] text-xs">
            No se registraron compras en este período.
          </div>
        </div>
      </div>

      <!-- MODAL 3: EVIDENCIA FOTOGRÁFICA -->
      <ClienteFotosModal
        :show="modalFotosVisible"
        :cliente="clienteFotosActivo"
        @close="modalFotosVisible = false"
      />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import SubNavTabs from '@/Components/UI/SubNavTabs.vue';
import AvanceFiltrosBar from './AvanceFiltrosBar.vue';
import ClienteFotosModal from '@/Pages/Supervisor/Preventas/ClienteFotosModal.vue';
import { navigationConfig } from '@/Config/navigation.js';
import { useAvanceStore } from '@/Stores/avanceStore';
import { mapsConfig } from '@/Config/maps.js';

// Leaflet & Heat Plugin
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '@/Utils/leaflet-heat.js';

const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  meses: { type: Array, default: () => [] },
  fecha_inicio_default: { type: String, default: '' },
  fecha_corte_default: { type: String, default: '' },
  mes_default: { type: String, default: '' },
  dias_calendario: { type: Object, default: null },
});

// Subnavegación de Módulo Avance
const avanceMenu = navigationConfig.mainMenu.find(m => m.id === 'avance');
const avanceTabs = avanceMenu ? avanceMenu.submodules : [];

const store = useAvanceStore();

// Estado de datos
const cargando = ref(false);
const errorMensaje = ref('');
const datosPareto = ref(null);

// Control de División Split
const anchoTablaPct = ref(50);
const isDraggingSplitter = ref(false);

function setPresetLayout(pct) {
  anchoTablaPct.value = pct;
  nextTick(() => {
    if (mapaInstancia) {
      mapaInstancia.invalidateSize();
    }
  });
}

function iniciarArrastreSplitter() {
  isDraggingSplitter.value = true;
  document.body.style.cursor = 'col-resize';
  document.body.style.userSelect = 'none';

  const onMouseMove = (ev) => {
    if (!isDraggingSplitter.value) return;
    const container = document.getElementById('paretoSplitContainer');
    if (!container) return;
    const rect = container.getBoundingClientRect();
    const pct = ((ev.clientX - rect.left) / rect.width) * 100;
    anchoTablaPct.value = Math.min(Math.max(Math.round(pct), 15), 85);
    if (mapaInstancia) {
      mapaInstancia.invalidateSize();
    }
  };

  const onMouseUp = () => {
    isDraggingSplitter.value = false;
    document.body.style.cursor = '';
    document.body.style.userSelect = '';
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
    if (mapaInstancia) {
      setTimeout(() => mapaInstancia.invalidateSize(), 50);
    }
  };

  window.addEventListener('mousemove', onMouseMove);
  window.addEventListener('mouseup', onMouseUp);
}

// Filtros Locales de Pareto
const filtroClase = ref('TODAS');
const filtroTipoNegocio = ref('');
const mostrarInactivos = ref(false);
const clienteSeleccionadoId = ref(null);

// Configuración de los 5 Mapas de Calor Analíticos
const criterioColorMapa = ref('volumen');
const subMetricaVolumen = ref('bs');
const subMetricaPenetracion = ref('skus');
const subMetricaConsistencia = ref('6m');

const mapaCapas = ref({
  calor: false,
  puntos: true,
});

const menuCapasAbierto = ref(false);
const refMenuCapas = ref(null);
const leyendaCalorColapsada = ref(false);

const CONFIG_HEATMAPS = {
  volumen: {
    titulo: '1. Volumen Facturado',
    obtenerConfig: (sub) => {
      if (sub === 'unidades') {
        return {
          titulo: 'Volumen Facturado (Unidades Físicas)',
          prefijoBoton: 'Volumen (U.)',
          opciones: [
            { key: 'vol_u_alto', label: 'Alto (> 100 u.)', color: '#FF0000', test: c => (c.unidades || 0) > 100 },
            { key: 'vol_u_medio', label: 'Medio (30 a 100 u.)', color: '#FFD700', test: c => (c.unidades || 0) >= 30 && (c.unidades || 0) <= 100 },
            { key: 'vol_u_bajo', label: 'Bajo (< 30 u.)', color: '#0066FF', test: c => (c.unidades || 0) < 30 },
          ],
          getIntensity: c => Math.min(Math.max((c.unidades || 0) / 150, 0.25), 1.0),
        };
      }
      if (sub === 'cajas') {
        return {
          titulo: 'Volumen Facturado (Cajas Físicas)',
          prefijoBoton: 'Volumen (Cj.)',
          opciones: [
            { key: 'vol_c_alto', label: 'Alto (> 5 cj.)', color: '#FF0000', test: c => (c.cajas || 0) > 5 },
            { key: 'vol_c_medio', label: 'Medio (1.5 a 5 cj.)', color: '#FFD700', test: c => (c.cajas || 0) >= 1.5 && (c.cajas || 0) <= 5 },
            { key: 'vol_c_bajo', label: 'Bajo (< 1.5 cj.)', color: '#0066FF', test: c => (c.cajas || 0) < 1.5 },
          ],
          getIntensity: c => Math.min(Math.max((c.cajas || 0) / 8, 0.25), 1.0),
        };
      }
      return {
        titulo: 'Volumen Facturado (Bolivianos MTD)',
        prefijoBoton: 'Volumen (Bs)',
        opciones: [
          { key: 'vol_alto', label: 'Alto (> 1.000 Bs)', color: '#FF0000', test: c => (c.venta_mtd || 0) > 1000 },
          { key: 'vol_medio', label: 'Medio (500 a 1.000 Bs)', color: '#FFD700', test: c => (c.venta_mtd || 0) >= 500 && (c.venta_mtd || 0) <= 1000 },
          { key: 'vol_bajo', label: 'Bajo (< 500 Bs)', color: '#0066FF', test: c => (c.venta_mtd || 0) < 500 },
        ],
        getIntensity: c => Math.min(Math.max((c.venta_mtd || 0) / 1500, 0.25), 1.0),
      };
    },
  },
  frecuencia: {
    titulo: '2. Frecuencia de Compra',
    obtenerConfig: () => ({
      titulo: 'Frecuencia de Compra (Facturas Efectivas)',
      prefijoBoton: 'Frecuencia',
      opciones: [
        { key: 'frec_4', label: 'Alta (≥ 4 facturas)', color: '#FF0000', test: c => (c.facturas_efectivas || 0) >= 4 },
        { key: 'frec_3', label: 'Media-Alta (3 facturas)', color: '#FF6600', test: c => (c.facturas_efectivas || 0) === 3 },
        { key: 'frec_2', label: 'Media (2 facturas)', color: '#FFD700', test: c => (c.facturas_efectivas || 0) === 2 },
        { key: 'frec_1', label: 'Ocasional (1 factura)', color: '#0099FF', test: c => (c.facturas_efectivas || 0) === 1 },
        { key: 'frec_0', label: 'Sin facturas (0)', color: '#0011AA', test: c => (c.facturas_efectivas || 0) <= 0 },
      ],
      getIntensity: c => Math.min(Math.max((c.facturas_efectivas || 0) / 4, 0.25), 1.0),
    }),
  },
  penetracion: {
    titulo: '3. Penetración de Portafolio',
    obtenerConfig: (sub) => {
      if (sub === 'marcas') {
        return {
          titulo: 'Penetración de Portafolio (Marcas Distintas)',
          prefijoBoton: 'Marcas',
          opciones: [
            { key: 'marca_alta', label: 'Alta (≥ 5 marcas)', color: '#FF0000', test: c => (c.marcas_distintas || 0) >= 5 },
            { key: 'marca_media', label: 'Media (2 a 4 marcas)', color: '#FFD700', test: c => (c.marcas_distintas || 0) >= 2 && (c.marcas_distintas || 0) <= 4 },
            { key: 'marca_baja', label: 'Baja (1 marca)', color: '#0099FF', test: c => (c.marcas_distintas || 0) === 1 },
            { key: 'marca_cero', label: 'Sin marcas (0)', color: '#0011AA', test: c => (c.marcas_distintas || 0) <= 0 },
          ],
          getIntensity: c => Math.min(Math.max((c.marcas_distintas || 0) / 6, 0.25), 1.0),
        };
      }
      return {
        titulo: 'Penetración de Portafolio (SKUs Distintos)',
        prefijoBoton: 'SKUs',
        opciones: [
          { key: 'sku_alto', label: 'Alto (> 8 SKUs)', color: '#FF0000', test: c => (c.skus_distintos || 0) > 8 },
          { key: 'sku_medio', label: 'Medio (4 a 8 SKUs)', color: '#FFD700', test: c => (c.skus_distintos || 0) >= 4 && (c.skus_distintos || 0) <= 8 },
          { key: 'sku_bajo', label: 'Bajo (1 a 3 SKUs)', color: '#0099FF', test: c => (c.skus_distintos || 0) >= 1 && (c.skus_distintos || 0) <= 3 },
          { key: 'sku_cero', label: 'Sin SKUs (0)', color: '#0011AA', test: c => (c.skus_distintos || 0) <= 0 },
        ],
        getIntensity: c => Math.min(Math.max((c.skus_distintos || 0) / 12, 0.25), 1.0),
      };
    },
  },
  drop_size: {
    titulo: '4. Drop Size Promedio',
    obtenerConfig: () => ({
      titulo: 'Drop Size Promedio (Bs / Factura Efectiva)',
      prefijoBoton: 'Drop Size',
      opciones: [
        { key: 'drop_alto', label: 'Alto (> 500 Bs/fac)', color: '#FF0000', test: c => (c.drop_size || 0) > 500 },
        { key: 'drop_medio', label: 'Medio (200 a 500 Bs/fac)', color: '#FFD700', test: c => (c.drop_size || 0) >= 200 && (c.drop_size || 0) <= 500 },
        { key: 'drop_bajo', label: 'Bajo (< 200 Bs/fac)', color: '#0066FF', test: c => (c.drop_size || 0) > 0 && (c.drop_size || 0) < 200 },
        { key: 'drop_cero', label: 'Sin Venta (0 Bs)', color: '#0011AA', test: c => (c.drop_size || 0) <= 0 },
      ],
      getIntensity: c => Math.min(Math.max((c.drop_size || 0) / 600, 0.25), 1.0),
    }),
  },
  consistencia: {
    titulo: '5. Consistencia Histórica',
    obtenerConfig: (sub) => {
      if (sub === '12m') {
        return {
          titulo: 'Consistencia Histórica (Últimos 12 Meses)',
          prefijoBoton: 'Consistencia 12M',
          opciones: [
            { key: 'cons_12m_alta', label: 'Alta Fidelidad (10 a 12 meses)', color: '#FF0000', test: c => (c.meses_activos_12m || 0) >= 10 },
            { key: 'cons_12m_media', label: 'Media Fidelidad (6 a 9 meses)', color: '#FFD700', test: c => (c.meses_activos_12m || 0) >= 6 && (c.meses_activos_12m || 0) <= 9 },
            { key: 'cons_12m_baja', label: 'Baja / Esporádico (1 a 5 meses)', color: '#0099FF', test: c => (c.meses_activos_12m || 0) >= 1 && (c.meses_activos_12m || 0) <= 5 },
            { key: 'cons_12m_cero', label: 'Sin Actividad (0 meses)', color: '#0011AA', test: c => (c.meses_activos_12m || 0) <= 0 },
          ],
          getIntensity: c => Math.min(Math.max((c.meses_activos_12m || 0) / 12, 0.25), 1.0),
        };
      }
      return {
        titulo: 'Consistencia Histórica (Últimos 6 Meses)',
        prefijoBoton: 'Consistencia 6M',
        opciones: [
          { key: 'cons_6m_alta', label: 'Núcleo Fiel (5 a 6 meses)', color: '#FF0000', test: c => (c.meses_activos_6m || 0) >= 5 },
          { key: 'cons_6m_media', label: 'Intermitente (3 a 4 meses)', color: '#FFD700', test: c => (c.meses_activos_6m || 0) >= 3 && (c.meses_activos_6m || 0) <= 4 },
          { key: 'cons_6m_baja', label: 'Esporádico (1 a 2 meses)', color: '#0099FF', test: c => (c.meses_activos_6m || 0) >= 1 && (c.meses_activos_6m || 0) <= 2 },
          { key: 'cons_6m_cero', label: 'Sin Actividad (0 meses)', color: '#0011AA', test: c => (c.meses_activos_6m || 0) <= 0 },
        ],
        getIntensity: c => Math.min(Math.max((c.meses_activos_6m || 0) / 6, 0.25), 1.0),
      };
    },
  },
};

const capasRangosActivas = ref({});

const configCriterioActual = computed(() => {
  const criterio = CONFIG_HEATMAPS[criterioColorMapa.value] || CONFIG_HEATMAPS.volumen;
  let sub = null;
  if (criterioColorMapa.value === 'volumen') sub = subMetricaVolumen.value;
  if (criterioColorMapa.value === 'penetracion') sub = subMetricaPenetracion.value;
  if (criterioColorMapa.value === 'consistencia') sub = subMetricaConsistencia.value;
  return criterio.obtenerConfig(sub);
});

const resumenTextoBotonCapas = computed(() => {
  const cfg = configCriterioActual.value;
  const total = cfg.opciones.length;
  const activos = cfg.opciones.filter(op => capasRangosActivas.value[op.key] !== false).length;
  return `${cfg.prefijoBoton} (${activos}/${total})`;
});

function seleccionarTodasRangos(marcar) {
  const cfg = configCriterioActual.value;
  cfg.opciones.forEach(op => {
    capasRangosActivas.value[op.key] = marcar;
  });
  actualizarCapasMapaDebounced(false);
}

function alCambiarCriterioMapa() {
  const cfg = configCriterioActual.value;
  cfg.opciones.forEach(op => {
    if (capasRangosActivas.value[op.key] === undefined) {
      capasRangosActivas.value[op.key] = true;
    }
  });
  actualizarCapasMapaDebounced(false);
}

function conmutarRangoDesdeLeyenda(key) {
  capasRangosActivas.value[key] = !capasRangosActivas.value[key];
  actualizarCapasMapaDebounced(false);
}

function resolverGrupoYColorCliente(c) {
  const cfg = configCriterioActual.value;
  for (const op of cfg.opciones) {
    if (op.test(c)) {
      return { key: op.key, color: op.color, label: op.label, intensity: cfg.getIntensity(c) };
    }
  }
  const lastOp = cfg.opciones[cfg.opciones.length - 1];
  return { key: lastOp.key, color: lastOp.color, label: lastOp.label, intensity: 0.25 };
}

// Instancias de Mapa Leaflet
let mapaInstancia = null;
let capaTermica = null;
let capaPuntos = null;
let marcadorEnfocado = null;
let mapaAjustadoInicial = false;
let debounceMapaTimer = null;

function centrarMapa() {
  if (!mapaInstancia) return;
  const clientes = clientesParaMapa.value;
  const bounds = [];
  for (const c of clientes) {
    const lat = parseFloat(c.latitud);
    const lon = parseFloat(c.longitud);
    if (lat && lon && (lat !== 0 || lon !== 0)) {
      bounds.push([lat, lon]);
    }
  }
  if (bounds.length > 0) {
    mapaInstancia.fitBounds(bounds, { padding: [25, 25], maxZoom: 14 });
  }
}

// Modales
const modalAmplitudVisible = ref(false);
const clienteAmplitudActivo = ref(null);
const itemsAmplitud = ref([]);
const cargandoModalAmplitud = ref(false);

const modalFrecuenciaVisible = ref(false);
const clienteFrecuenciaActivo = ref(null);
const itemsFrecuencia = ref([]);
const cargandoModalFrecuencia = ref(false);

const modalFotosVisible = ref(false);
const clienteFotosActivo = ref(null);

const tiposNegocioDisponibles = computed(() => {
  return datosPareto.value?.tipos_negocio_disponibles || [];
});

// Filtrado Reactivo de Clientes Pareto
const clientesFiltrados = computed(() => {
  if (!datosPareto.value?.clientes) return [];
  let lista = datosPareto.value.clientes;

  if (!mostrarInactivos.value) {
    lista = lista.filter(c => c.estado_cliente === 'ACTIVO');
  }

  const canales = store.filtrosAplicados?.canalesSeleccionados || [];
  if (canales.length > 0) {
    const selCanales = new Set(canales.map(c => String(c).trim().toUpperCase()));
    lista = lista.filter(c => selCanales.has(String(c.canal || '').trim().toUpperCase()));
  }

  const rutas = store.filtrosAplicados?.rutasSeleccionadas || [];
  if (rutas.length > 0) {
    const selRutas = new Set(rutas.map(r => String(r).trim().toUpperCase()));
    lista = lista.filter(r => selRutas.has(String(r.ruta || '').trim().toUpperCase()));
  }

  if (filtroTipoNegocio.value) {
    lista = lista.filter(c => c.tipo_negocio === filtroTipoNegocio.value);
  }

  if (filtroClase.value !== 'TODAS') {
    lista = lista.filter(c => (c.clasificacion || c._clase_monto) === filtroClase.value);
  }

  const busqueda = store.filtrosAplicados?.busqueda || '';
  if (busqueda.trim()) {
    const q = busqueda.toLowerCase().trim();
    lista = lista.filter(c =>
      String(c.cliente || '').toLowerCase().includes(q) ||
      String(c.cliente_id || '').toLowerCase().includes(q) ||
      String(c.ruta || '').toLowerCase().includes(q) ||
      String(c.vendedor || '').toLowerCase().includes(q)
    );
  }

  return lista;
});

const clientesParaMapa = computed(() => {
  return clientesFiltrados.value.filter(c =>
    c.latitud && c.longitud && (c.latitud !== 0 || c.longitud !== 0)
  );
});

const conteoUsuariosPorRango = computed(() => {
  const clientes = clientesParaMapa.value;
  const conteos = { total: 0 };
  const opciones = configCriterioActual.value?.opciones || [];
  opciones.forEach(op => {
    conteos[op.key] = 0;
  });

  for (const c of clientes) {
    conteos.total++;
    const info = resolverGrupoYColorCliente(c);
    if (info && info.key) {
      conteos[info.key] = (conteos[info.key] || 0) + 1;
    }
  }

  return conteos;
});

const totalUsuariosCalorActivos = computed(() => {
  const opciones = configCriterioActual.value?.opciones || [];
  return opciones.reduce((acc, op) => {
    const activo = capasRangosActivas.value[op.key] !== false;
    return activo ? acc + (conteoUsuariosPorRango.value[op.key] || 0) : acc;
  }, 0);
});

const totalUsuariosCalorTotal = computed(() => {
  return conteoUsuariosPorRango.value.total || 0;
});

function actualizarCapasMapaDebounced(forzarAjuste = false) {
  if (debounceMapaTimer) clearTimeout(debounceMapaTimer);
  debounceMapaTimer = setTimeout(() => {
    actualizarCapasMapa(forzarAjuste);
  }, 100);
}

watch([filtroClase, filtroTipoNegocio, mostrarInactivos, () => store.filtrosAplicados?.busqueda], () => {
  actualizarCapasMapaDebounced(false);
});

watch([() => store.filtrosAplicados?.canalesSeleccionados, () => store.filtrosAplicados?.rutasSeleccionadas], () => {
  actualizarCapasMapaDebounced(true);
});

function inicializarMapa() {
  const container = document.getElementById('mapaCalorParetoContainer');
  if (!container || mapaInstancia) return;

  const loc = mapsConfig.defaultLocation;
  mapaInstancia = L.map(container, {
    center: [loc.lat, loc.lng],
    zoom: loc.zoom,
    zoomControl: true,
    preferCanvas: true,
  });

  const tileCfg = mapsConfig.tileLayers.openStreetMap;
  L.tileLayer(tileCfg.url, {
    attribution: tileCfg.attribution,
    maxZoom: tileCfg.maxZoom,
  }).addTo(mapaInstancia);

  mapaInstancia.on('zoomend', () => {
    const z = mapaInstancia.getZoom();
    const canvasHeat = document.querySelectorAll('.leaflet-heatmap-layer');
    canvasHeat.forEach(c => {
      if (z >= 16) {
        c.style.opacity = '0.25';
      } else if (z === 15) {
        c.style.opacity = '0.55';
      } else {
        c.style.opacity = '0.85';
      }
    });
  });

  actualizarCapasMapa(true);
}

function generarPopupHtml(c) {
  const mtdFormateado = Number(c.venta_mtd || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 });
  const dropFormateado = Number(c.drop_size || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 });
  const colEstado = c.estado_cliente === 'ACTIVO' ? '#059669' : '#DC2626';

  return `
    <div style="font-family:-apple-system,BlinkMacSystemFont,sans-serif; min-width:240px; line-height:1.4; font-size:11px; padding:2px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
        <div style="display:flex; align-items:center; gap:4px;">
          <span style="font-size:9px; font-weight:800; padding:2px 6px; border-radius:4px; background:#EFF6FF; color:#1E3A8A; border:1px solid #BFDBFE;">
            Clase ${c.clasificacion || c._clase_monto || 'C'} · ${c.tipo_negocio || 'Cliente'}
          </span>
          <span style="font-size:9px; font-weight:800; padding:2px 6px; border-radius:4px; background:#F1F5F9; color:#334155; border:1px solid #CBD5E1; font-family:ui-monospace, monospace;">
            Cód: ${c.cliente_id}
          </span>
        </div>
        <span style="font-size:9px; font-weight:bold; color:${colEstado};">
          ${c.estado_cliente || 'ACTIVO'}
        </span>
      </div>

      <div style="font-size:12px; font-weight:800; color:#0F172A;">${c.cliente}</div>
      <div style="font-size:10px; color:#475569;">${c.direccion || 'Sin Dirección'}</div>
      <div style="font-size:10px; color:#64748B; margin-top:2px;">Ruta: <b>${c.ruta}</b> · Prev: ${c.vendedor || 'S/A'}</div>

      <div style="margin-top:6px; padding:6px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:6px; font-size:10px; display:grid; grid-template-columns:1fr 1fr; gap:4px;">
        <div><b>1. Volumen Bs:</b><br><span style="color:#0F172A; font-weight:bold;">Bs ${mtdFormateado}</span></div>
        <div><b>1. Unid / Cajas:</b><br><span style="color:#0F172A;">${c.unidades || 0} u. / ${c.cajas || 0} cj.</span></div>
        <div><b>2. Frecuencia:</b><br><span style="color:#0F172A; font-weight:bold;">${c.facturas_efectivas || 0} fac. efectivas</span></div>
        <div><b>3. Portafolio:</b><br><span style="color:#0F172A;">${c.skus_distintos || 0} SKUs · ${c.marcas_distintas || 0} Mrc</span></div>
        <div><b>4. Drop Size:</b><br><span style="color:#0F172A; font-weight:bold;">Bs ${dropFormateado}</span></div>
        <div><b>5. Consistencia:</b><br><span style="color:#0F172A;">${c.meses_activos_6m || 0}m (6M) / ${c.meses_activos_12m || 0}m (12M)</span></div>
      </div>

      <div style="margin-top:6px; display:flex; gap:4px; flex-wrap:wrap;">
        <button onclick="window.__abrirModalAmplitudPorId(${c.cliente_id})"
                style="flex:1; background:#0071E3; color:#FFF; font-size:9px; font-weight:bold; padding:4px 6px; border-radius:4px; border:none; cursor:pointer;">
          Ver SKUs / Marcas
        </button>
        <button onclick="window.__abrirModalFrecuenciaPorId(${c.cliente_id})"
                style="flex:1; background:#0F766E; color:#FFF; font-size:9px; font-weight:bold; padding:4px 6px; border-radius:4px; border:none; cursor:pointer;">
          Ver Facturas
        </button>
        ${c.tiene_fotos ? `
          <button onclick="window.__abrirModalFotosPorId(${c.cliente_id})"
                  style="flex:1; background:#D97706; color:#FFF; font-size:9px; font-weight:bold; padding:4px 6px; border-radius:4px; border:none; cursor:pointer;">
            Fotos
          </button>
        ` : ''}
      </div>
    </div>
  `;
}

function actualizarCapasMapa(forzarAjusteBounds = false) {
  if (!mapaInstancia) return;

  if (capaTermica && mapaInstancia) {
    mapaInstancia.removeLayer(capaTermica);
    capaTermica = null;
  }

  if (capaPuntos && mapaInstancia) {
    mapaInstancia.removeLayer(capaPuntos);
    capaPuntos = null;
  }

  const clientes = clientesParaMapa.value;
  if (clientes.length === 0) return;

  const bounds = [];
  const ptsCalor = [];
  const markers = [];

  for (const c of clientes) {
    const lat = parseFloat(c.latitud);
    const lon = parseFloat(c.longitud);
    if (!lat || !lon || (lat === 0 && lon === 0)) continue;

    bounds.push([lat, lon]);
    const info = resolverGrupoYColorCliente(c);
    const permiso = capasRangosActivas.value[info.key] !== false;

    if (!permiso) continue;

    if (mapaCapas.value.calor) {
      ptsCalor.push([lat, lon, info.intensity || 0.5]);
    }

    if (mapaCapas.value.puntos) {
      const marker = L.circleMarker([lat, lon], {
        radius: 4.5,
        fillColor: info.color,
        fillOpacity: 0.9,
        color: '#FFFFFF',
        weight: 1.5,
      });
      marker.bindPopup(() => generarPopupHtml(c));
      markers.push(marker);
    }
  }

  if (mapaCapas.value.calor && ptsCalor.length > 0 && typeof L.heatLayer === 'function') {
    capaTermica = L.heatLayer(ptsCalor, {
      radius: 20,
      blur: 15,
      maxZoom: 15,
      max: 1.0,
      minOpacity: 0.35,
      gradient: {
        0.2: '#0066FF',
        0.45: '#00CCFF',
        0.65: '#FFD700',
        0.85: '#FF6600',
        1.0: '#FF0000',
      },
    }).addTo(mapaInstancia);
  }

  if (mapaCapas.value.puntos && markers.length > 0) {
    capaPuntos = L.layerGroup(markers);
    mapaInstancia.addLayer(capaPuntos);
  }

  if (bounds.length > 0 && !marcadorEnfocado && (!mapaAjustadoInicial || forzarAjusteBounds)) {
    mapaInstancia.fitBounds(bounds, { padding: [25, 25], maxZoom: 14 });
    mapaAjustadoInicial = true;
  }
}

function enfocarClienteEnMapa(cliente) {
  clienteSeleccionadoId.value = cliente.cliente_id;

  if (!cliente.latitud || !cliente.longitud || (cliente.latitud === 0 && cliente.longitud === 0)) {
    return;
  }

  if (anchoTablaPct.value === 100) {
    setPresetLayout(50);
  }

  nextTick(() => {
    if (mapaInstancia) {
      mapaInstancia.invalidateSize();
      mapaInstancia.flyTo([cliente.latitud, cliente.longitud], 17, { animate: true, duration: 0.6 });

      if (marcadorEnfocado) {
        mapaInstancia.removeLayer(marcadorEnfocado);
      }

      marcadorEnfocado = L.circleMarker([cliente.latitud, cliente.longitud], {
        radius: 9,
        fillColor: '#0071E3',
        fillOpacity: 1.0,
        color: '#FFFFFF',
        weight: 3,
      }).addTo(mapaInstancia);

      marcadorEnfocado.bindPopup(generarPopupHtml(cliente)).openPopup();
    }
  });
}

function getClaseParetoBadge(clase) {
  if (clase === 'A') return 'bg-emerald-50 text-emerald-800 border border-emerald-300 font-bold';
  if (clase === 'B') return 'bg-amber-50 text-amber-800 border border-amber-300 font-bold';
  return 'bg-slate-100 text-slate-600 border border-slate-300';
}

function getMicroBadge(clase) {
  if (clase === 'A') return 'bg-emerald-100 text-emerald-900 font-bold';
  if (clase === 'B') return 'bg-amber-100 text-amber-900 font-bold';
  return 'bg-slate-200 text-slate-700';
}

function getClaseTendencia(v) {
  if (!v || v.var_pct === undefined) return 'text-slate-400';
  if (v.tendencia === 'up') return 'text-emerald-700 font-bold';
  if (v.tendencia === 'down') return 'text-rose-700 font-bold';
  return 'text-slate-500';
}

function formatVarPct(v) {
  if (!v || v.var_pct === undefined) return '-';
  if (v.estado === 'NUEVO') return 'NUEVO';
  if (v.estado === 'DESERCIÓN') return 'DESERC.';
  if (v.estado === 'SIN VENTA') return '-';
  const flecha = v.tendencia === 'up' ? '+' : '';
  return `${flecha}${v.var_pct.toFixed(0)}%`;
}

function formatNum(v) {
  if (v === null || v === undefined) return '0.00';
  return Number(v).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatEntero(n) {
  return Number(n || 0).toLocaleString('es-BO', { maximumFractionDigits: 0 });
}

function calcularPorcentaje(parcial, total) {
  if (!total || total <= 0) return '0.0';
  return ((Number(parcial || 0) / total) * 100).toFixed(1);
}

// Modales
async function abrirModalAmplitud(cliente) {
  clienteAmplitudActivo.value = cliente;
  modalAmplitudVisible.value = true;
  cargandoModalAmplitud.value = true;
  itemsAmplitud.value = [];
  try {
    const res = await axios.post('/supervisor/avance/paretos/detalle-amplitud', {
      cliente_id: cliente.cliente_id,
      fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
      fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
    });
    itemsAmplitud.value = res.data?.items || [];
  } catch (err) {
    console.error('Error cargando amplitud:', err);
  } finally {
    cargandoModalAmplitud.value = false;
  }
}

function cerrarModalAmplitud() {
  modalAmplitudVisible.value = false;
  clienteAmplitudActivo.value = null;
  itemsAmplitud.value = [];
}

async function abrirModalFrecuencia(cliente) {
  clienteFrecuenciaActivo.value = cliente;
  modalFrecuenciaVisible.value = true;
  cargandoModalFrecuencia.value = true;
  itemsFrecuencia.value = [];
  try {
    const res = await axios.post('/supervisor/avance/paretos/detalle-frecuencia', {
      cliente_id: cliente.cliente_id,
      fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
      fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
    });
    itemsFrecuencia.value = res.data?.compras || [];
  } catch (err) {
    console.error('Error cargando frecuencia:', err);
  } finally {
    cargandoModalFrecuencia.value = false;
  }
}

function cerrarModalFrecuencia() {
  modalFrecuenciaVisible.value = false;
  clienteFrecuenciaActivo.value = null;
  itemsFrecuencia.value = [];
}

function abrirModalFotos(cliente) {
  clienteFotosActivo.value = cliente;
  modalFotosVisible.value = true;
}

async function ejecutarConsulta() {
  if (marcadorEnfocado && mapaInstancia) {
    mapaInstancia.removeLayer(marcadorEnfocado);
    marcadorEnfocado = null;
  }

  cargando.value = true;
  errorMensaje.value = '';

  try {
    const res = await axios.post('/supervisor/avance/paretos/data', {
      fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
      fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
    });
    datosPareto.value = res.data;
  } catch (err) {
    console.error('Error al cargar datos de Pareto:', err);
    errorMensaje.value = err?.response?.data?.message || 'Error al consultar los datos de análisis Pareto.';
  } finally {
    cargando.value = false;
    nextTick(() => {
      if (!mapaInstancia) {
        inicializarMapa();
      } else {
        mapaInstancia.invalidateSize();
        actualizarCapasMapa(true);
      }
    });
  }
}

function handleClickOutside(e) {
  if (refMenuCapas.value && !refMenuCapas.value.contains(e.target)) {
    menuCapasAbierto.value = false;
  }
}

function handleResize() {
  if (mapaInstancia) {
    mapaInstancia.invalidateSize();
  }
}

onMounted(() => {
  store.inicializarDesdeStorage(props);

  document.addEventListener('click', handleClickOutside);
  window.addEventListener('resize', handleResize);

  window.__abrirModalAmplitudPorId = (clienteId) => {
    const c = datosPareto.value?.clientes?.find(x => x.cliente_id === clienteId);
    if (c) abrirModalAmplitud(c);
  };
  window.__abrirModalFrecuenciaPorId = (clienteId) => {
    const c = datosPareto.value?.clientes?.find(x => x.cliente_id === clienteId);
    if (c) abrirModalFrecuencia(c);
  };
  window.__abrirModalFotosPorId = (clienteId) => {
    const c = datosPareto.value?.clientes?.find(x => x.cliente_id === clienteId);
    if (c) abrirModalFotos(c);
  };

  nextTick(() => {
    inicializarMapa();
  });

  ejecutarConsulta();
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  window.removeEventListener('resize', handleResize);
  delete window.__abrirModalAmplitudPorId;
  delete window.__abrirModalFrecuenciaPorId;
  delete window.__abrirModalFotosPorId;

  if (mapaInstancia) {
    mapaInstancia.remove();
    mapaInstancia = null;
  }
});
</script>

