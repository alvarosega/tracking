<template>
  <SupervisorLayout>
    <div class="space-y-6">
      <!-- Tarjetas KPI -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
          <p class="text-xs font-semibold text-gray-500 uppercase">Fuerza Activa Hoy</p>
          <div class="flex items-baseline space-x-2 mt-2">
            <span class="text-3xl font-extrabold text-gray-900">{{ kpis.vendedores_activos }}</span>
            <span class="text-xs text-gray-400">de {{ kpis.vendedores_total }} rutas</span>
          </div>
        </div>

        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
          <p class="text-xs font-semibold text-gray-500 uppercase">Visitas Realizadas</p>
          <div class="flex items-baseline space-x-2 mt-2">
            <span class="text-3xl font-extrabold text-blue-600">{{ kpis.visitas_hoy }}</span>
            <span class="text-xs text-gray-400">de {{ kpis.clientes_planificados }} planificadas</span>
          </div>
        </div>

        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
          <p class="text-xs font-semibold text-gray-500 uppercase">Avance Cobertura</p>
          <p class="text-3xl font-extrabold text-amber-600 mt-2">
            {{ kpis.clientes_planificados > 0 ? Math.round((kpis.visitas_hoy / kpis.clientes_planificados) * 100) : 0 }}%
          </p>
        </div>

        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
          <p class="text-xs font-semibold text-gray-500 uppercase">Alertas GPS Falso</p>
          <p class="text-3xl font-extrabold text-red-600 mt-2">{{ kpis.alertas_mock }}</p>
        </div>
      </div>

      <!-- Mapa Flota en Vivo -->
      <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <div class="flex justify-between items-center mb-3">
          <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wide">Última Posición Transmitida en Vivo</h3>
          <span class="text-xs text-gray-500 font-mono">{{ kpis.dia_actual }}, {{ kpis.fecha_actual }}</span>
        </div>
        <div id="map-live" class="h-96 w-full rounded-lg border border-gray-200"></div>
      </div>

      <!-- Tabla Detallada -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 border-b border-gray-100">
          <h3 class="text-sm font-bold text-gray-800 uppercase">Estado de Conexión de Vendedores</h3>
        </div>
        <table class="w-full text-left text-sm text-gray-600">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase border-b border-gray-100">
            <tr>
              <th class="p-4">Ruta / Vendedor</th>
              <th class="p-4">Último Reporte</th>
              <th class="p-4">Batería</th>
              <th class="p-4">Movimiento</th>
              <th class="p-4">Precisión GPS</th>
              <th class="p-4">Integridad</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 font-medium">
            <tr v-for="v in vendedores_en_vivo" :key="v.user_id" class="hover:bg-gray-50">
              <td class="p-4 font-bold text-gray-900">{{ v.vendedor_ruta }}</td>
              <td class="p-4 font-mono text-xs">{{ v.recorded_at }}</td>
              <td class="p-4">
                <span :class="v.battery_level <= 15 ? 'text-red-600 font-bold' : 'text-gray-700'">
                  {{ v.battery_level }}%
                </span>
              </td>
              <td class="p-4">
                <span class="px-2 py-1 text-xs rounded-full font-bold" :class="v.is_moving ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                  {{ v.is_moving ? 'En Movimiento' : 'Detenido' }}
                </span>
              </td>
              <td class="p-4">±{{ Math.round(v.accuracy) }}m</td>
              <td class="p-4">
                <span v-if="v.is_mock" class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded-full font-bold">MOCK DETECTADO</span>
                <span v-else class="text-xs text-green-600 font-bold">Genuino</span>
              </td>
            </tr>
            <tr v-if="vendedores_en_vivo.length === 0">
              <td colspan="6" class="p-6 text-center text-gray-400">Sin transmisiones satelitales recibidas hoy.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { onMounted } from 'vue';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import L from 'leaflet';

const props = defineProps({
  kpis: Object,
  vendedores_en_vivo: Array,
});

onMounted(() => {
  const map = L.map('map-live').setView([-16.5000, -68.1500], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
  }).addTo(map);

  if (props.vendedores_en_vivo.length > 0) {
    const group = [];
    props.vendedores_en_vivo.forEach(v => {
      const marker = L.circleMarker([v.latitude, v.longitude], {
        radius: 8,
        fillColor: v.is_mock ? '#dc2626' : (v.is_moving ? '#16a34a' : '#2563eb'),
        color: '#ffffff',
        weight: 2,
        opacity: 1,
        fillOpacity: 0.9
      }).addTo(map);

      marker.bindPopup(`
        <div class="font-sans text-xs">
          <strong>Ruta:</strong> ${v.vendedor_ruta}<br/>
          <strong>Hora:</strong> ${v.recorded_at}<br/>
          <strong>Batería:</strong> ${v.battery_level}%<br/>
          <strong>Precisión:</strong> ±${Math.round(v.accuracy)}m
        </div>
      `);
      group.push([v.latitude, v.longitude]);
    });
    map.fitBounds(group, { padding: [40, 40] });
  }
});
</script>