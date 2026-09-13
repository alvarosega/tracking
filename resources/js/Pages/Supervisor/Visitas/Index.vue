<template>
  <SupervisorLayout>
    <!-- BARRA LATERAL: Filtros y Lista de Visitas -->
    <aside class="w-[440px] border-r border-slate-800 bg-slate-900 flex flex-col flex-shrink-0 z-10">
      <!-- Filtros -->
      <div class="p-4 border-b border-slate-800 space-y-3">
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Vendedor</label>
            <select
              v-model="filters.user_id"
              @change="applyFilters"
              class="w-full bg-slate-950 border border-slate-700 text-xs text-white rounded p-2 focus:ring-1 focus:ring-blue-500"
            >
              <option v-for="s in sellers" :key="s.id" :value="s.id">{{ s.username }}</option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Fecha</label>
            <input
              v-model="filters.date"
              @change="applyFilters"
              type="date"
              class="w-full bg-slate-950 border border-slate-700 text-xs text-white rounded p-2 focus:ring-1 focus:ring-blue-500"
            />
          </div>
        </div>
        <div class="text-xs text-slate-400 font-semibold">
          Total registradas: <span class="text-white">{{ visitas.length }}</span>
        </div>
      </div>

      <!-- Lista de Visitas -->
      <div class="flex-1 overflow-y-auto p-2 space-y-2">
        <div v-if="visitas.length === 0" class="p-8 text-center text-xs text-slate-500">
          No hay visitas registradas para este vendedor en la fecha indicada.
        </div>
        <div
          v-for="v in visitas"
          :key="v.id"
          @click="selectVisita(v)"
          :class="selectedVisita?.id === v.id ? 'border-blue-500 bg-slate-800' : 'border-slate-800/80 hover:bg-slate-800/40'"
          class="p-3 rounded-lg border transition cursor-pointer"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <span class="text-xs font-bold text-white block">{{ v.client_name }}</span>
              <span class="text-[10px] text-slate-400">Ruta: {{ v.route }} • {{ formatTime(v.visited_at) }}</span>
            </div>
            <span
              :class="getStatusBadgeClass(v.status)"
              class="px-2 py-0.5 text-[9px] font-black rounded uppercase"
            >
              {{ v.status }}
            </span>
          </div>

          <div class="mt-2.5 flex items-center justify-between text-[11px]">
            <div class="flex items-center gap-1.5">
              <span class="text-slate-400">Desvío:</span>
              <span
                v-if="v.distance_meters !== null"
                :class="v.distance_meters > 20 ? 'text-rose-400 font-bold' : 'text-emerald-400 font-bold'"
              >
                {{ v.distance_meters }} m
              </span>
              <span v-else class="text-amber-400 italic text-[10px]">Oportunidad</span>
            </div>
            <span class="text-slate-500 text-[10px]">Precisión: ±{{ v.accuracy }}m</span>
          </div>
        </div>
      </div>
    </aside>

    <!-- MAPA Y VISOR FOTOGRÁFICO -->
    <section class="flex-1 relative">
      <div id="visitas-map" class="w-full h-full z-0"></div>

      <!-- Tarjeta Flotante de Detalle -->
      <div
        v-if="selectedVisita"
        class="absolute top-4 right-4 w-96 bg-slate-900/95 border border-slate-700 backdrop-blur rounded-xl p-4 shadow-2xl z-20 space-y-3"
      >
        <div class="flex justify-between items-start">
          <div>
            <h2 class="text-sm font-bold text-white">{{ selectedVisita.client_name }}</h2>
            <p class="text-[11px] text-slate-400">{{ selectedVisita.visited_at }}</p>
          </div>
          <button @click="selectedVisita = null" class="text-slate-400 hover:text-white text-xs">✕</button>
        </div>

        <div
          v-if="selectedVisita.photo_url"
          class="relative group cursor-pointer overflow-hidden rounded-lg bg-black aspect-video flex items-center justify-center border border-slate-800"
          @click="enlargePhoto = true"
        >
          <img :src="selectedVisita.photo_url" alt="Evidencia" class="object-cover w-full h-full group-hover:scale-105 transition" />
          <span class="absolute bottom-2 right-2 bg-black/70 px-2 py-0.5 rounded text-[10px] text-white">Click para ampliar</span>
        </div>

        <div class="text-xs space-y-1.5 border-t border-slate-800 pt-2">
          <div class="flex justify-between">
            <span class="text-slate-400">Estado:</span>
            <span class="font-bold text-white">{{ selectedVisita.status }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Distancia al cliente:</span>
            <span :class="selectedVisita.distance_meters > 20 ? 'text-rose-400 font-bold' : 'text-emerald-400 font-bold'">
              {{ selectedVisita.distance_meters !== null ? `${selectedVisita.distance_meters} m` : 'Cliente Oportunidad' }}
            </span>
          </div>
          <div v-if="selectedVisita.comments" class="text-slate-300 italic pt-1">
            "{{ selectedVisita.comments }}"
          </div>
        </div>
      </div>

      <!-- Modal Zoom Foto -->
      <div
        v-if="enlargePhoto && selectedVisita?.photo_url"
        class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4"
        @click="enlargePhoto = false"
      >
        <img :src="selectedVisita.photo_url" class="max-h-[90vh] max-w-[90vw] rounded-lg shadow-2xl object-contain" />
      </div>
    </section>
  </SupervisorLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import L from 'leaflet';

const props = defineProps({
  sellers: Array,
  filters: Object,
  visitas: Array,
});

const filters = ref({
  date: props.filters.date,
  user_id: props.filters.user_id,
});

const selectedVisita = ref(null);
const enlargePhoto = ref(false);

let map = null;
let markers = [];
let discrepancyLine = null;

onMounted(() => {
  initMap();
  renderMarkers();
});

watch(() => props.visitas, () => renderMarkers());

const initMap = () => {
  map = L.map('visitas-map', { zoomControl: false }).setView([-16.5000, -68.1500], 13);
  L.control.zoom({ position: 'bottomright' }).addTo(map);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 19,
  }).addTo(map);
};

const renderMarkers = () => {
  if (!map) return;
  markers.forEach(m => map.removeLayer(m));
  markers = [];
  if (discrepancyLine) map.removeLayer(discrepancyLine);

  const bounds = [];

  props.visitas.forEach(v => {
    bounds.push([v.actual_lat, v.actual_lng]);
    const color = v.status === 'PREVENTA' ? '#10b981' : '#f59e0b';

    const marker = L.circleMarker([v.actual_lat, v.actual_lng], {
      radius: 8,
      fillColor: color,
      color: '#ffffff',
      weight: 2,
      opacity: 1,
      fillOpacity: 0.9,
    }).addTo(map);

    marker.on('click', () => selectVisita(v));
    markers.push(marker);
  });

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [60, 60] });
  }
};

const selectVisita = (v) => {
  selectedVisita.value = v;
  if (discrepancyLine) {
    map.removeLayer(discrepancyLine);
    discrepancyLine = null;
  }

  map.flyTo([v.actual_lat, v.actual_lng], 17, { duration: 1.0 });

  if (v.target_lat && v.target_lng) {
    discrepancyLine = L.polyline([
      [v.actual_lat, v.actual_lng],
      [v.target_lat, v.target_lng],
    ], {
      color: '#f43f5e',
      weight: 3,
      dashArray: '4, 4',
    }).addTo(map);
  }
};

const applyFilters = () => {
  router.get('/supervisor/visitas', filters.value, { preserveState: true });
};

const formatTime = (dateTime) => {
  return dateTime ? dateTime.substring(11, 19) : '--:--';
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'PREVENTA': return 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30';
    case 'SIN_DINERO': return 'bg-rose-500/20 text-rose-400 border border-rose-500/30';
    default: return 'bg-amber-500/20 text-amber-400 border border-amber-500/30';
  }
};
</script>