<template>
  <SupervisorLayout>
    <div class="space-y-4">
      <!-- Filtros -->
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-wrap gap-4 items-center justify-between">
        <div class="flex items-center space-x-3">
          <label class="text-xs font-bold text-gray-600 uppercase">Vendedor:</label>
          <select v-model="selectedUser" @change="applyFilter" class="border border-gray-300 rounded-lg p-2 text-sm font-semibold">
            <option :value="null">-- Mapa General Flota --</option>
            <option v-for="u in vendedores" :key="u.id" :value="u.id">{{ u.username }}</option>
          </select>

          <label class="text-xs font-bold text-gray-600 uppercase ml-2">Fecha:</label>
          <input type="date" v-model="selectedDate" @change="applyFilter" class="border border-gray-300 rounded-lg p-1.5 text-sm font-semibold" />
        </div>

        <div class="flex items-center space-x-4 text-xs font-bold">
          <span class="flex items-center space-x-1"><span class="w-3 h-3 rounded-full bg-green-600 inline-block"></span><span>Legítimo (σ² ≥ 0.60)</span></span>
          <span class="flex items-center space-x-1"><span class="w-3 h-3 rounded-full bg-red-600 inline-block"></span><span>Sospechoso / Mock</span></span>
          <span class="flex items-center space-x-1"><span class="w-3 h-3 rounded-full bg-blue-600 inline-block"></span><span>Detenido</span></span>
        </div>
      </div>

      <!-- Mapa de Tracking -->
      <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100">
        <div id="map-tracking" class="h-[600px] w-full rounded-lg"></div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import L from 'leaflet';

const props = defineProps({
  vendedores: Array,
  selected_date: String,
  selected_user_id: Number,
  puntos: Array,
});

const selectedUser = ref(props.selected_user_id);
const selectedDate = ref(props.selected_date);

function applyFilter() {
  router.get(route('supervisor.tracking.index'), {
    user_id: selectedUser.value,
    date: selectedDate.value
  }, { preserveState: true });
}

onMounted(() => {
  const map = L.map('map-tracking').setView([-16.5000, -68.1500], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
  }).addTo(map);

  if (props.puntos.length > 0) {
    const latlngs = [];
    props.puntos.forEach(p => {
      latlngs.push([p.latitude, p.longitude]);

      // Color codificado por auditoría de telemetría inercial
      let color = '#2563eb'; // Detenido por defecto
      if (p.is_mock) {
        color = '#dc2626'; // Mock GPS explícito
      } else if (p.is_moving) {
        color = p.motion_variance >= 0.60 ? '#16a34a' : '#ea580c'; // Verde = Legítimo, Naranja = Sospechoso
      }

      L.circleMarker([p.latitude, p.longitude], {
        radius: 5,
        fillColor: color,
        color: '#ffffff',
        weight: 1,
        fillOpacity: 0.8
      }).bindPopup(`
        <div class="font-sans text-xs">
          <strong>Ruta:</strong> ${p.vendedor_ruta}<br/>
          <strong>Hora:</strong> ${p.recorded_at}<br/>
          <strong>Velocidad:</strong> ${(p.speed * 3.6).toFixed(1)} km/h<br/>
          <strong>Varianza Inercial:</strong> ${p.motion_variance.toFixed(4)}<br/>
          <strong>Batería:</strong> ${p.battery_level}%
        </div>
      `).addTo(map);
    });

    // Si es inspección individual, dibuja la polilínea secuencial
    if (props.selected_user_id) {
      L.polyline(latlngs, { color: '#0284c7', weight: 3, opacity: 0.7 }).addTo(map);
    }

    map.fitBounds(latlngs, { padding: [30, 30] });
  }
});
</script>