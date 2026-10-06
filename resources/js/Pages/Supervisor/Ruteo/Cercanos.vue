<template>
  <RuteoLayout>
    <div class="flex-1 flex flex-col min-h-0 bg-slate-900 font-sans text-slate-900">
      <!-- Barra Superior de Control & Filtros -->
      <header class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 py-2 flex items-center justify-between gap-2 shrink-0 relative z-30">
        
        <!-- Backdrop invisible para menús flotantes -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- Scroll Horizontal de Filtros -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
          
          <!-- Botón de GPS Actual -->
          <button
            type="button"
            @click="obtenerUbicacionActual"
            :disabled="obteniendoGps"
            class="h-8 px-3 bg-slate-100 hover:bg-slate-200 text-slate-900 border border-slate-200/90 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all shrink-0 disabled:opacity-50 cursor-pointer shadow-2xs"
            title="Usar ubicación GPS del dispositivo"
          >
            <span class="w-2 h-2 rounded-full" :class="obteniendoGps ? 'bg-amber-500 animate-ping' : 'bg-sky-500'"></span>
            <span class="whitespace-nowrap">{{ obteniendoGps ? 'Localizando...' : 'Mi Ubicación' }}</span>
          </button>

          <!-- Filtro Radio: Selector Compacto -->
          <div class="flex items-center h-8 px-2.5 bg-slate-50 border border-slate-200/90 rounded-lg text-xs text-slate-800 shrink-0">
            <span class="text-slate-400 mr-1.5 font-mono">Radio:</span>
            <select
              v-model="radioSeleccionado"
              @change="seleccionarRadio(Number(radioSeleccionado))"
              class="bg-transparent font-bold font-mono text-xs border-0 p-0 pr-4 focus:ring-0 cursor-pointer text-slate-900"
            >
              <option v-for="r in opcionesRadio" :key="r.valor" :value="r.valor">{{ r.etiqueta }}</option>
            </select>
          </div>

          <!-- Filtro Año -->
          <div class="flex items-center h-8 px-2.5 bg-slate-50 border border-slate-200/90 rounded-lg text-xs text-slate-800 shrink-0">
            <span class="text-slate-400 mr-1.5 font-mono">Año:</span>
            <select
              v-model="anioSeleccionado"
              @change="reconsultar"
              class="bg-transparent font-bold font-mono text-xs border-0 p-0 pr-4 focus:ring-0 cursor-pointer text-slate-900"
            >
              <option v-for="a in anios_disponibles" :key="a" :value="a">{{ a }}</option>
            </select>
          </div>

          <!-- Botón Disparador: Meses -->
          <button
            type="button"
            @click.stop="toggleMenu('meses', $event)"
            class="h-8 px-2.5 bg-slate-50 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 hover:bg-slate-100 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0"
          >
            <span class="text-slate-400">Meses:</span>
            <span class="font-semibold text-slate-900">{{ labelMeses }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Botón Disparador: Canales -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-8 px-2.5 bg-slate-50 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 hover:bg-slate-100 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0"
          >
            <span class="text-slate-400">Canal:</span>
            <span class="font-semibold text-slate-900">{{ labelCanales }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Botón Disparador: Rutas -->
          <button
            type="button"
            @click.stop="toggleMenu('rutas', $event)"
            class="h-8 px-2.5 bg-slate-50 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 hover:bg-slate-100 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0"
          >
            <span class="text-slate-400">Rutas:</span>
            <span class="font-semibold text-slate-900">{{ labelRutas }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>
        </div>

        <!-- Selector Móvil Segmentado y Contador de Clientes -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <div class="flex md:hidden items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200">
            <button
              type="button"
              @click="vistaMovil = 'mapa'"
              class="px-2.5 py-1 text-[11px] font-semibold rounded-md transition-all cursor-pointer"
              :class="vistaMovil === 'mapa' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500'"
            >
              Mapa
            </button>
            <button
              type="button"
              @click="vistaMovil = 'lista'"
              class="px-2.5 py-1 text-[11px] font-semibold rounded-md transition-all cursor-pointer"
              :class="vistaMovil === 'lista' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500'"
            >
              Lista ({{ clientesCercanos.length }})
            </button>
          </div>

          <div class="hidden sm:flex items-center gap-1 px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg shadow-2xs">
            <span class="text-slate-400">En radio:</span>
            <span class="font-bold text-slate-900 tabular-nums">{{ clientesCercanos.length }}</span>
          </div>
        </div>
      </header>

      <!-- Menús Desplegables Flotantes con Teleport al Body -->
      <Teleport to="body">
        <!-- Menú: Meses -->
        <div
          v-if="menuAbierto === 'meses'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-56 bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 z-[99999] text-xs font-sans"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
            <button type="button" @click="seleccionarTodosMeses" class="text-[11px] text-sky-600 font-semibold hover:underline cursor-pointer">Todos</button>
            <button type="button" @click="limpiarMeses" class="text-[11px] text-slate-500 font-medium hover:underline cursor-pointer">Limpiar</button>
          </div>
          <div class="max-h-56 overflow-y-auto space-y-1">
            <label v-for="m in mesesLista" :key="m.numero" class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none">
              <input type="checkbox" :value="m.numero" v-model="filtroMeses" @change="reconsultar" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <span class="font-mono text-slate-400 text-[11px] w-6">{{ m.corto }}</span>
              <span class="truncate font-medium text-slate-700">{{ m.nombre }}</span>
            </label>
          </div>
        </div>

        <!-- Menú: Canales -->
        <div
          v-if="menuAbierto === 'canales'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-56 bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 z-[99999] text-xs font-sans"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
            <button type="button" @click="seleccionarTodosCanales" class="text-[11px] text-sky-600 font-semibold hover:underline cursor-pointer">Todos</button>
            <button type="button" @click="limpiarCanales" class="text-[11px] text-slate-500 font-medium hover:underline cursor-pointer">Limpiar</button>
          </div>
          <div class="max-h-48 overflow-y-auto space-y-1">
            <label v-for="c in canales" :key="c" class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none">
              <input type="checkbox" :value="c" v-model="filtroCanales" @change="onCanalesChange" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(c) }"></span>
              <span class="truncate font-medium text-slate-700">{{ c }}</span>
            </label>
          </div>
        </div>

        <!-- Menú: Rutas -->
        <div
          v-if="menuAbierto === 'rutas'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-64 bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 z-[99999] text-xs font-sans"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
            <button type="button" @click="seleccionarTodasRutas" class="text-[11px] text-sky-600 font-semibold hover:underline cursor-pointer">Todas ({{ rutasDisponibles.length }})</button>
            <button type="button" @click="limpiarRutas" class="text-[11px] text-slate-500 font-medium hover:underline cursor-pointer">Limpiar</button>
          </div>
          <input
            v-model="busquedaRuta"
            type="text"
            placeholder="Buscar ruta..."
            class="w-full h-7 px-2.5 mb-2 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 font-mono"
          />
          <div class="max-h-56 overflow-y-auto space-y-1">
            <label v-for="r in rutasFiltradasEnDropdown" :key="r.ruta" class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none">
              <input type="checkbox" :value="r.ruta" v-model="filtroRutas" @change="reconsultar" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <div class="truncate flex items-center gap-2 flex-1">
                <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-slate-300" :style="{ backgroundColor: getRutaColor(r.ruta) }"></span>
                <span class="font-mono font-semibold text-slate-800">{{ r.ruta }}</span>
                <span class="text-slate-400 text-[10px]">({{ r.canal }})</span>
              </div>
            </label>
          </div>
        </div>
      </Teleport>

      <!-- Split Screen Principal -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative z-10">
        
        <!-- Panel Lateral: Lista o Expediente del Cliente -->
        <aside
          class="w-full md:w-[400px] bg-white border-r border-slate-200 flex flex-col shrink-0 relative z-20 overflow-hidden"
          :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex'"
        >
          <!-- Vista 1: Listado General -->
          <template v-if="!clienteSeleccionado">
            <div class="p-2.5 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between shrink-0 font-mono text-xs">
              <span class="font-bold text-slate-500 uppercase tracking-wider text-[11px]">Clientes en Radar</span>
              <span v-if="origenConsulta" class="text-slate-400 text-[10px]">
                {{ origenConsulta.lat.toFixed(4) }}, {{ origenConsulta.lng.toFixed(4) }}
              </span>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
              <div v-if="!origenConsulta" class="p-8 text-center text-xs text-slate-400 space-y-2">
                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                  </svg>
                </div>
                <p class="font-semibold text-slate-700">Explorador de Radio</p>
                <p class="text-[11px] text-slate-400">Toca cualquier punto en el mapa de El Alto o pulsa <strong>"Mi Ubicación"</strong> para rastrear clientes cercanos.</p>
              </div>

              <div v-else-if="clientesCercanos.length === 0 && !cargando" class="p-8 text-center text-xs text-slate-400">
                No se encontraron clientes dentro del radio de {{ radioSeleccionado }}m.
              </div>

              <button
                v-for="c in clientesCercanos"
                :key="c.id"
                type="button"
                @click="abrirExpedienteCliente(c)"
                class="w-full text-left p-3 hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer group"
              >
                <div class="flex items-start justify-between gap-2">
                  <span class="text-xs font-semibold text-slate-900 group-hover:text-sky-600 transition-colors line-clamp-1">{{ c.cliente }}</span>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <span
                      class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded text-white"
                      :style="{ backgroundColor: getCanalColor(c.canal_calculado) }"
                    >
                      {{ c.canal_calculado }}
                    </span>
                    <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 tabular-nums">
                      {{ c.distancia_metros }} m
                    </span>
                  </div>
                </div>

                <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{{ c.direccion || 'Sin dirección registrada' }}</p>

                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] font-mono">
                  <div class="flex items-center gap-1.5 text-slate-500">
                    <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getRutaColor(c.ruta) }"></span>
                    <span class="font-semibold text-slate-800">{{ c.ruta }}</span>
                    <span>•</span>
                    <span class="text-slate-400">ID: {{ c.cliente_id }}</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="text-slate-400 text-[10px]">{{ c.total_pedidos }} ped.</span>
                    <span class="font-bold text-slate-900 tabular-nums">Bs. {{ c.total_compras.toFixed(2) }}</span>
                  </div>
                </div>
              </button>
            </div>
          </template>

          <!-- Vista 2: Expediente y Auditoría del Cliente Seleccionado -->
          <template v-else>
            <div class="p-2.5 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between shrink-0">
              <button
                type="button"
                @click="cerrarExpediente"
                class="text-xs font-semibold text-sky-600 hover:text-sky-700 flex items-center gap-1 cursor-pointer transition-colors"
              >
                <span>&larr;</span> Volver al radar
              </button>
              
              <button
                type="button"
                @click="vistaMovil = 'mapa'"
                class="md:hidden text-xs text-slate-800 font-semibold bg-white border border-slate-200 px-2.5 py-1 rounded-md shadow-2xs"
              >
                Ver en mapa
              </button>
            </div>

            <!-- Ficha Resumen del Cliente -->
            <div class="p-3.5 bg-white border-b border-slate-100 shrink-0 space-y-2.5">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <h3 class="text-xs font-bold text-slate-900 leading-snug">{{ clienteSeleccionado.cliente }}</h3>
                  <p class="text-[11px] text-slate-500 mt-0.5">{{ clienteSeleccionado.direccion || 'Sin dirección' }}</p>
                </div>
                <span
                  class="text-[9px] font-mono font-bold px-2 py-0.5 rounded text-white shrink-0"
                  :style="{ backgroundColor: getCanalColor(clienteSeleccionado.canal_calculado) }"
                >
                  {{ clienteSeleccionado.canal_calculado }}
                </span>
              </div>

              <!-- Pestañas de Expediente -->
              <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200/80">
                <button
                  type="button"
                  @click="tabExpediente = 'ventas'"
                  class="flex-1 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer text-center"
                  :class="tabExpediente === 'ventas' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500 hover:text-slate-900'"
                >
                  Compras ({{ anioSeleccionado }})
                </button>
                <button
                  type="button"
                  @click="tabExpediente = 'visitas'"
                  class="flex-1 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer text-center"
                  :class="tabExpediente === 'visitas' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500 hover:text-slate-900'"
                >
                  Visitas & Fotos
                </button>
              </div>
            </div>

            <!-- Contenido Pestaña: Ventas -->
            <div v-show="tabExpediente === 'ventas'" class="flex-1 overflow-y-auto p-3 space-y-2.5">
              <div v-if="cargandoVentas" class="py-8 text-center text-xs text-slate-400 animate-pulse">
                Cargando historial de compras...
              </div>
              <div v-else-if="ventasCliente.length === 0" class="py-8 text-center text-xs text-slate-400">
                No hay compras registradas para este periodo.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="v in ventasCliente"
                  :key="v.venta_id"
                  class="p-2.5 bg-slate-50 border border-slate-200/80 rounded-xl text-xs space-y-1.5"
                >
                  <div class="flex items-center justify-between font-mono">
                    <span class="text-slate-500 text-[11px]">{{ v.fecha }} {{ v.hora }}</span>
                    <span class="font-bold text-slate-900">Bs. {{ v.monto_ticket.toFixed(2) }}</span>
                  </div>
                  <div class="text-[11px] text-slate-600">
                    <span class="text-slate-400">Factura:</span> {{ v.nro_factura || 'S/N' }} • <span class="text-slate-400">Vendedor:</span> {{ v.vendedor }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Contenido Pestaña: Visitas -->
            <div v-show="tabExpediente === 'visitas'" class="flex-1 overflow-y-auto p-3 space-y-2.5">
              <div v-if="cargandoVisitas" class="py-8 text-center text-xs text-slate-400 animate-pulse">
                Cargando historial de visitas...
              </div>
              <div v-else-if="visitasCliente.length === 0" class="py-8 text-center text-xs text-slate-400">
                Sin visitas registradas para este cliente.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="vis in visitasCliente"
                  :key="vis.id"
                  class="p-2.5 bg-slate-50 border border-slate-200/80 rounded-xl text-xs space-y-2"
                >
                  <div class="flex items-center justify-between">
                    <span class="font-mono text-slate-500 text-[11px]">{{ vis.fecha }} {{ vis.hora }}</span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-mono uppercase font-bold" :class="vis.status === 'VISITED' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-700'">
                      {{ vis.status || 'VISITADO' }}
                    </span>
                  </div>
                  <p v-if="vis.comments" class="text-[11px] text-slate-600 italic">"{{ vis.comments }}"</p>
                  <div v-if="vis.photo_url" class="mt-1.5">
                    <img :src="vis.photo_url" alt="Foto de visita" class="w-full h-32 object-cover rounded-lg border border-slate-200" />
                  </div>
                </div>
              </div>
            </div>
          </template>
        </aside>

        <!-- Mapa Leaflet Interactivo -->
        <div
          class="flex-1 relative min-h-[350px] md:min-h-0 bg-slate-950"
          :class="vistaMovil === 'mapa' ? 'block' : 'hidden md:block'"
        >
          <div id="map-cercanos" class="absolute inset-0 w-full h-full z-10"></div>

          <!-- Selector de Capas de Mapa -->
          <div class="absolute top-3 left-3 z-400 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl p-1 shadow-md flex items-center space-x-1 text-xs">
            <button
              type="button"
              @click="cambiarCapaMapa('minimalLight')"
              class="px-2.5 py-1 rounded-lg font-medium text-[11px] transition-all cursor-pointer"
              :class="capaActual === 'minimalLight' ? 'bg-slate-900 text-white font-semibold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Minimal
            </button>
            <button
              type="button"
              @click="cambiarCapaMapa('openStreetMap')"
              class="px-2.5 py-1 rounded-lg font-medium text-[11px] transition-all cursor-pointer"
              :class="capaActual === 'openStreetMap' ? 'bg-slate-900 text-white font-semibold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Calles
            </button>
            <button
              type="button"
              @click="cambiarCapaMapa('satellite')"
              class="px-2.5 py-1 rounded-lg font-medium text-[11px] transition-all cursor-pointer"
              :class="capaActual === 'satellite' ? 'bg-slate-900 text-white font-semibold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Satélite
            </button>
          </div>
        </div>

      </div>
    </div>
  </RuteoLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import RuteoLayout from '@/Pages/Supervisor/Ruteo/Layout.vue';
import { mapsConfig } from '@/Config/maps';
import L from 'leaflet';

const props = defineProps({
  catalogo_rutas: {
    type: Array,
    default: () => [],
  },
  canales: {
    type: Array,
    default: () => [],
  },
  anios_disponibles: {
    type: Array,
    default: () => [2026],
  },
  anio_default: {
    type: Number,
    default: 2026,
  },
  meses_default: {
    type: Array,
    default: () => [1, 2, 3],
  },
});

const opcionesRadio = [
  { valor: 100, etiqueta: '100 m' },
  { valor: 250, etiqueta: '250 m' },
  { valor: 500, etiqueta: '500 m' },
  { valor: 1000, etiqueta: '1 km' },
  { valor: 2000, etiqueta: '2 km' },
  { valor: 3000, etiqueta: '3 km' },
];

const mesesLista = [
  { numero: 1, corto: 'ENE', nombre: 'Enero' },
  { numero: 2, corto: 'FEB', nombre: 'Febrero' },
  { numero: 3, corto: 'MAR', nombre: 'Marzo' },
  { numero: 4, corto: 'ABR', nombre: 'Abril' },
  { numero: 5, corto: 'MAY', nombre: 'Mayo' },
  { numero: 6, corto: 'JUN', nombre: 'Junio' },
  { numero: 7, corto: 'JUL', nombre: 'Julio' },
  { numero: 8, corto: 'AGO', nombre: 'Agosto' },
  { numero: 9, corto: 'SEP', nombre: 'Septiembre' },
  { numero: 10, corto: 'OCT', nombre: 'Octubre' },
  { numero: 11, corto: 'NOV', nombre: 'Noviembre' },
  { numero: 12, corto: 'DIC', nombre: 'Diciembre' },
];

const CANAL_COLORS = {
  'SUPERMERCADOS': '#0284C7',
  'TRADICIONAL': '#10B981',
  'MAYORISTAS': '#8B5CF6',
  'INSTITUCIONAL': '#F59E0B',
  'HORECA': '#EC4899',
  'DEFAULT': '#0F172A',
};

function getCanalColor(canal) {
  if (!canal) return CANAL_COLORS.DEFAULT;
  const c = canal.toUpperCase().trim();
  return CANAL_COLORS[c] || CANAL_COLORS.DEFAULT;
}

const RUTA_PALETTE = ['#0F172A', '#0284C7', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#14B8A6'];
const rutaColorCache = new Map();

function getRutaColor(ruta) {
  if (!ruta) return '#0F172A';
  if (!rutaColorCache.has(ruta)) {
    const idx = rutaColorCache.size % RUTA_PALETTE.length;
    rutaColorCache.set(ruta, RUTA_PALETTE[idx]);
  }
  return rutaColorCache.get(ruta);
}

// Estados reactivos
const radioSeleccionado = ref(500);
const anioSeleccionado = ref(props.anio_default || new Date().getFullYear());
const filtroMeses = ref([...props.meses_default]);
const filtroCanales = ref([]);
const filtroRutas = ref([]);
const busquedaRuta = ref('');

const obteniendoGps = ref(false);
const cargando = ref(false);
const vistaMovil = ref('mapa');
const capaActual = ref('minimalLight');

const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });

const origenConsulta = ref(null);
const clientesCercanos = ref([]);
const clienteSeleccionado = ref(null);
const tabExpediente = ref('ventas');
const ventasCliente = ref([]);
const visitasCliente = ref([]);
const cargandoVentas = ref(false);
const cargandoVisitas = ref(false);

let map = null;
let currentTileLayer = null;
let centerMarker = null;
let radiusCircle = null;
let clientsLayer = null;
const clientMarkersMap = new Map();

const rutasDisponibles = computed(() => {
  if (filtroCanales.value.length === 0) return props.catalogo_rutas;
  return props.catalogo_rutas.filter(r => filtroCanales.value.includes(r.canal));
});

const rutasFiltradasEnDropdown = computed(() => {
  if (!busquedaRuta.value.trim()) return rutasDisponibles.value;
  const q = busquedaRuta.value.toLowerCase();
  return rutasDisponibles.value.filter(r =>
    r.ruta.toLowerCase().includes(q) || (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

const labelMeses = computed(() => {
  if (filtroMeses.value.length === 0) return 'Ninguno';
  if (filtroMeses.value.length === 12) return 'Todos';
  return `${filtroMeses.value.length} mes(es)`;
});

const labelCanales = computed(() => {
  if (filtroCanales.value.length === 0) return 'Todos';
  if (filtroCanales.value.length === 1) return filtroCanales.value[0];
  return `${filtroCanales.value.length} sel.`;
});

const labelRutas = computed(() => {
  if (filtroRutas.value.length === 0) return 'Todas';
  if (filtroRutas.value.length === 1) return filtroRutas.value[0];
  return `${filtroRutas.value.length} sel.`;
});

function toggleMenu(nombre, e) {
  if (menuAbierto.value === nombre) {
    menuAbierto.value = null;
    return;
  }
  const rect = e.currentTarget.getBoundingClientRect();
  posicionMenu.value = {
    top: rect.bottom + 6,
    left: Math.min(rect.left, window.innerWidth - 270),
  };
  menuAbierto.value = nombre;
}

function onCanalesChange() {
  const validRutas = new Set(rutasDisponibles.value.map(r => r.ruta));
  filtroRutas.value = filtroRutas.value.filter(r => validRutas.has(r));
  reconsultar();
}

function seleccionarTodosCanales() {
  filtroCanales.value = [...props.canales];
  onCanalesChange();
}

function limpiarCanales() {
  filtroCanales.value = [];
  onCanalesChange();
}

function seleccionarTodasRutas() {
  filtroRutas.value = rutasDisponibles.value.map(r => r.ruta);
  reconsultar();
}

function limpiarRutas() {
  filtroRutas.value = [];
  reconsultar();
}

function seleccionarTodosMeses() {
  filtroMeses.value = mesesLista.map(m => m.numero);
  reconsultar();
}

function limpiarMeses() {
  filtroMeses.value = [];
  reconsultar();
}

function seleccionarRadio(r) {
  radioSeleccionado.value = r;
  if (origenConsulta.value) {
    consultarCercanos(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

function obtenerUbicacionActual() {
  if (!navigator.geolocation) {
    alert('Tu navegador no soporta geolocalización');
    return;
  }
  obteniendoGps.value = true;
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      obteniendoGps.value = false;
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      map.flyTo([lat, lng], 15);
      consultarCercanos(lat, lng);
    },
    (err) => {
      obteniendoGps.value = false;
      alert('No se pudo obtener tu ubicación GPS: ' + err.message);
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}

function reconsultar() {
  if (origenConsulta.value) {
    consultarCercanos(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

async function consultarCercanos(lat, lng) {
  origenConsulta.value = { lat, lng };
  cargando.value = true;

  try {
    const res = await axios.post(route('supervisor.ruteo.cercanos.data'), {
      latitud: lat,
      longitud: lng,
      radio: radioSeleccionado.value,
      canales: filtroCanales.value,
      rutas: filtroRutas.value,
      meses: filtroMeses.value,
      anio: anioSeleccionado.value,
    });

    const list = res.data.clientes || [];
    // Mapear canal calculado
    list.forEach(c => {
      const rutaInfo = props.catalogo_rutas.find(r => r.ruta === c.ruta);
      c.canal_calculado = rutaInfo ? rutaInfo.canal : 'GENERAL';
    });

    clientesCercanos.value = list;
    renderizarEnMapa();
  } catch (error) {
    console.error('Error al consultar clientes cercanos:', error);
  } finally {
    cargando.value = false;
  }
}

function cambiarCapaMapa(nombreCapa) {
  if (!map || !mapsConfig.tileLayers[nombreCapa]) return;
  capaActual.value = nombreCapa;

  if (currentTileLayer) {
    map.removeLayer(currentTileLayer);
  }

  const layerConf = mapsConfig.tileLayers[nombreCapa];
  currentTileLayer = L.tileLayer(layerConf.url, {
    maxZoom: layerConf.maxZoom || 19,
    subdomains: layerConf.subdomains || 'abc',
    attribution: layerConf.attribution,
  }).addTo(map);
}

function initMap() {
  const defaultLoc = mapsConfig.defaultLocation;

  map = L.map('map-cercanos', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom);

  L.control.zoom({ position: 'topright' }).addTo(map);
  cambiarCapaMapa('minimalLight');

  clientsLayer = L.featureGroup().addTo(map);

  map.on('click', (e) => {
    menuAbierto.value = null;
    consultarCercanos(e.latlng.lat, e.latlng.lng);
  });
}

function renderizarEnMapa() {
  if (!map || !origenConsulta.value) return;

  if (centerMarker) map.removeLayer(centerMarker);
  if (radiusCircle) map.removeLayer(radiusCircle);
  clientsLayer.clearLayers();
  clientMarkersMap.clear();

  const center = [origenConsulta.value.lat, origenConsulta.value.lng];

  centerMarker = L.circleMarker(center, {
    radius: 7,
    fillColor: '#0284C7',
    color: '#FFFFFF',
    weight: 2,
    opacity: 1,
    fillOpacity: 1,
  }).addTo(map);

  radiusCircle = L.circle(center, {
    radius: radioSeleccionado.value,
    color: '#0284C7',
    fillColor: '#0284C7',
    fillOpacity: 0.06,
    weight: 1.5,
    dashArray: '4, 6',
  }).addTo(map);

  clientesCercanos.value.forEach(c => {
    const marker = L.circleMarker([c.latitud, c.longitud], {
      radius: 6,
      fillColor: getCanalColor(c.canal_calculado),
      color: '#FFFFFF',
      weight: 1.5,
      opacity: 1,
      fillOpacity: 0.95,
    });

    marker.bindPopup(`
      <div style="font-family: inherit; font-size: 11px; line-height: 1.4; color: #0F172A; max-width: 220px;">
        <div style="font-weight: 700; font-size: 12px; margin-bottom: 2px;">${c.cliente}</div>
        <div style="color: #64748B; margin-bottom: 4px;">${c.direccion}</div>
        <div style="font-family: monospace; font-size: 10px; border-top: 1px solid #E2E8F0; padding-top: 4px; display: flex; justify-content: space-between;">
          <span>Ruta: <b>${c.ruta}</b></span>
          <span style="color: #047857; font-weight: bold;">${c.distancia_metros} m</span>
        </div>
      </div>
    `);

    marker.on('click', () => {
      abrirExpedienteCliente(c);
    });

    marker.addTo(clientsLayer);
    clientMarkersMap.set(c.id, marker);
  });
}

async function abrirExpedienteCliente(c) {
  clienteSeleccionado.value = c;
  tabExpediente.value = 'ventas';
  ventasCliente.value = [];
  visitasCliente.value = [];

  if (map && c.latitud && c.longitud) {
    map.flyTo([c.latitud, c.longitud], 17, { duration: 0.6 });
    const m = clientMarkersMap.get(c.id);
    if (m) m.openPopup();
  }

  // Cargar ventas
  cargandoVentas.value = true;
  try {
    const resVentas = await axios.post(`/supervisor/ruteo/cercanos/cliente/${c.cliente_id}/ventas`, {
      meses: filtroMeses.value,
      anio: anioSeleccionado.value,
    });
    ventasCliente.value = resVentas.data.ventas || [];
  } catch (e) {
    console.error('Error al cargar ventas:', e);
  } finally {
    cargandoVentas.value = false;
  }

  // Cargar visitas
  cargandoVisitas.value = true;
  try {
    const resVisitas = await axios.post(`/supervisor/ruteo/cercanos/cliente/${c.cliente_id}/visitas`);
    visitasCliente.value = resVisitas.data.visitas || [];
  } catch (e) {
    console.error('Error al cargar visitas:', e);
  } finally {
    cargandoVisitas.value = false;
  }
}

function cerrarExpediente() {
  clienteSeleccionado.value = null;
}

onMounted(() => {
  initMap();
});
</script>