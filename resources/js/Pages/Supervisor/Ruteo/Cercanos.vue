<template>
  <RuteoLayout>
    <div class="flex-1 flex flex-col min-h-0 bg-[#F5F5F7]">
      <!-- Barra Superior de Control -->
      <header class="bg-white border-b border-[#E5E5EA] px-3 md:px-4 py-2 flex items-center justify-between gap-2 shrink-0 relative z-30">
        
        <!-- Backdrop invisible para cerrar menús al tocar afuera -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- Contenedor con Scroll Horizontal de Filtros -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
          
          <!-- Botón de GPS Actual -->
          <button
            type="button"
            @click="obtenerUbicacionActual"
            :disabled="obteniendoGps"
            class="h-7 px-2.5 bg-[#F2F2F7] hover:bg-[#E5E5EA] border border-[#E5E5EA] rounded-[6px] text-xs font-semibold text-[#1D1D1F] flex items-center gap-1.5 transition-colors shrink-0 disabled:opacity-50 cursor-pointer"
            title="Usar ubicación satelital"
          >
            <span class="w-2 h-2 rounded-full" :class="obteniendoGps ? 'bg-[#FF9500] animate-ping' : 'bg-[#0071E3]'"></span>
            <span class="whitespace-nowrap">{{ obteniendoGps ? 'Localizando...' : 'Mi Ubicación' }}</span>
          </button>

          <!-- Filtro Radio: Nativo Compacto -->
          <div class="flex items-center h-7 px-2 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] shrink-0">
            <span class="text-[#86868B] mr-1">Radio:</span>
            <select
              v-model="radioSeleccionado"
              @change="seleccionarRadio(Number(radioSeleccionado))"
              class="bg-transparent font-semibold font-mono text-xs border-0 p-0 pr-4 focus:ring-0 cursor-pointer"
            >
              <option v-for="r in opcionesRadio" :key="r.valor" :value="r.valor">{{ r.etiqueta }}</option>
            </select>
          </div>

          <!-- Filtro Año: Nativo Compacto -->
          <div class="flex items-center h-7 px-2 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] shrink-0">
            <span class="text-[#86868B] mr-1">Año:</span>
            <select
              v-model="anioSeleccionado"
              @change="reconsultar"
              class="bg-transparent font-semibold font-mono text-xs border-0 p-0 pr-4 focus:ring-0 cursor-pointer"
            >
              <option v-for="a in anios_disponibles" :key="a" :value="a">{{ a }}</option>
            </select>
          </div>

          <!-- Botón Disparador: Meses -->
          <button
            type="button"
            @click.stop="toggleMenu('meses', $event)"
            class="h-7 px-2 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span>Meses:</span>
            <span class="font-semibold">{{ labelMeses }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Botón Disparador: Canales -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-7 px-2 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span>Canal:</span>
            <span class="font-semibold">{{ labelCanales }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Botón Disparador: Rutas -->
          <button
            type="button"
            @click.stop="toggleMenu('rutas', $event)"
            class="h-7 px-2 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span>Rutas:</span>
            <span class="font-semibold">{{ labelRutas }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

        </div>

        <!-- Selector Móvil Segmentado y Contador de Clientes -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <div class="flex md:hidden items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA]">
            <button
              type="button"
              @click="vistaMovil = 'mapa'"
              class="px-2 py-1 text-[11px] font-semibold rounded-[4px] transition-all cursor-pointer flex items-center gap-1"
              :class="vistaMovil === 'mapa' ? 'bg-white text-[#1D1D1F] shadow-2xs' : 'text-[#6E6E73]'"
            >
              Mapa
            </button>
            <button
              type="button"
              @click="vistaMovil = 'lista'"
              class="px-2 py-1 text-[11px] font-semibold rounded-[4px] transition-all cursor-pointer flex items-center gap-1"
              :class="vistaMovil === 'lista' ? 'bg-white text-[#1D1D1F] shadow-2xs' : 'text-[#6E6E73]'"
            >
              Lista ({{ clientesCercanos.length }})
            </button>
          </div>

          <div class="hidden sm:flex items-center gap-1 px-2 py-0.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px]">
            <span class="text-[#86868B]">Encontrados:</span>
            <span class="font-semibold text-[#1D1D1F] tabular-nums">{{ clientesCercanos.length }}</span>
          </div>
        </div>
      </header>

      <!-- Menús Desplegables Flotantes con Teleport al Body (Se abren justo debajo del botón y flotan sobre todo) -->
      <Teleport to="body">
        <!-- Menú Flotante: Meses -->
        <div
          v-if="menuAbierto === 'meses'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-56 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[99999] text-xs"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
            <button type="button" @click="seleccionarTodosMeses" class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer">Todos</button>
            <button type="button" @click="limpiarMeses" class="text-[11px] text-[#6E6E73] font-medium hover:underline cursor-pointer">Ninguno</button>
          </div>
          <div class="max-h-56 overflow-y-auto space-y-1">
            <label v-for="m in mesesLista" :key="m.numero" class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer select-none">
              <input type="checkbox" :value="m.numero" v-model="filtroMeses" @change="reconsultar" class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0 cursor-pointer" />
              <span class="font-mono text-[#86868B] w-5">{{ m.corto }}</span>
              <span class="truncate font-medium">{{ m.nombre }}</span>
            </label>
          </div>
        </div>

        <!-- Menú Flotante: Canales -->
        <div
          v-if="menuAbierto === 'canales'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-52 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[99999] text-xs"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
            <button type="button" @click="seleccionarTodosCanales" class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer">Todos</button>
            <button type="button" @click="limpiarCanales" class="text-[11px] text-[#6E6E73] font-medium hover:underline cursor-pointer">Ninguno</button>
          </div>
          <div class="max-h-48 overflow-y-auto space-y-1">
            <label v-for="c in canales" :key="c" class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer select-none">
              <input type="checkbox" :value="c" v-model="filtroCanales" @change="onCanalesChange" class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0 cursor-pointer" />
              <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(c) }"></span>
              <span class="truncate">{{ c }}</span>
            </label>
          </div>
        </div>

        <!-- Menú Flotante: Rutas -->
        <div
          v-if="menuAbierto === 'rutas'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-64 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[99999] text-xs"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
            <button type="button" @click="seleccionarTodasRutas" class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer">Todas ({{ rutasDisponibles.length }})</button>
            <button type="button" @click="limpiarRutas" class="text-[11px] text-[#6E6E73] font-medium hover:underline cursor-pointer">Ninguna</button>
          </div>
          <input
            v-model="busquedaRuta"
            type="text"
            placeholder="Buscar ruta..."
            class="w-full h-6 px-2 mb-1.5 text-xs bg-[#FBFBFD] border border-[#E5E5EA] rounded-[4px] focus:outline-none focus:border-[#86868B]"
          />
          <div class="max-h-56 overflow-y-auto space-y-1">
            <label v-for="r in rutasFiltradasEnDropdown" :key="r.ruta" class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer select-none">
              <input type="checkbox" :value="r.ruta" v-model="filtroRutas" @change="reconsultar" class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0 cursor-pointer" />
              <div class="truncate flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-black/20" :style="{ backgroundColor: getRutaColor(r.ruta) }"></span>
                <span class="font-medium">{{ r.ruta }}</span>
                <span class="text-[#86868B] text-[10px]">({{ r.canal }})</span>
              </div>
            </label>
          </div>
        </div>
      </Teleport>

      <!-- Split Screen Principal -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative z-10">
        
        <!-- Panel Izquierdo: Lista o Expediente -->
        <aside
          class="w-full md:w-[420px] bg-white border-r border-[#E5E5EA] flex flex-col shrink-0 relative z-20 overflow-hidden"
          :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex'"
        >
          <!-- Listado General de Clientes -->
          <template v-if="!clienteSeleccionado">
            <div class="p-2 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
              <span class="text-[11px] font-semibold text-[#86868B] uppercase tracking-wider">Clientes en Radio</span>
              <span v-if="origenConsulta" class="text-[10px] font-mono text-[#6E6E73]">
                {{ origenConsulta.lat.toFixed(4) }}, {{ origenConsulta.lng.toFixed(4) }}
              </span>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA]">
              <div v-if="!origenConsulta" class="p-8 text-center text-xs text-[#86868B] space-y-2">
                <svg class="w-8 h-8 mx-auto text-[#86868B]/60 stroke-[1.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                </svg>
                <p>Toca el mapa o pulsa <strong>"Mi Ubicación"</strong> para buscar clientes cercanos.</p>
              </div>

              <div v-else-if="clientesCercanos.length === 0 && !cargando" class="p-8 text-center text-xs text-[#86868B]">
                No se encontraron clientes dentro de {{ radioSeleccionado }}m.
              </div>

              <button
                v-for="c in clientesCercanos"
                :key="c.id"
                type="button"
                @click="abrirExpedienteCliente(c)"
                class="w-full text-left p-3 hover:bg-[#FBFBFD] transition-colors focus:outline-none cursor-pointer"
              >
                <div class="flex items-start justify-between gap-1.5">
                  <span class="text-xs font-semibold text-[#1D1D1F] line-clamp-1">{{ c.cliente }}</span>
                  <div class="flex items-center gap-1 shrink-0">
                    <span
                      class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded text-white"
                      :style="{ backgroundColor: getCanalColor(c.canal_calculado) }"
                    >
                      {{ c.canal_calculado }}
                    </span>
                    <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-[#EBF9EF] text-[#248A3D] border border-[#E5E5EA] tabular-nums">
                      {{ c.distancia_metros }} m
                    </span>
                  </div>
                </div>

                <p class="text-[11px] text-[#6E6E73] mt-0.5 line-clamp-1">{{ c.direccion || 'Sin dirección' }}</p>

                <div class="mt-2 pt-2 border-t border-[#E5E5EA]/60 flex items-center justify-between text-[11px] font-mono">
                  <div class="flex items-center gap-1 text-[#6E6E73]">
                    <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getRutaColor(c.ruta) }"></span>
                    <span>{{ c.ruta }}</span>
                    <span>•</span>
                    <span>ID: {{ c.cliente_id }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <span class="text-[#86868B] text-[10px]">{{ c.total_pedidos }} ped.</span>
                    <span class="font-bold text-[#1D1D1F] tabular-nums">Bs. {{ c.total_compras.toFixed(2) }}</span>
                  </div>
                </div>
              </button>
            </div>
          </template>

          <!-- Expediente del Cliente -->
          <template v-else>
            <div class="p-2.5 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
              <button
                type="button"
                @click="cerrarExpediente"
                class="text-xs font-semibold text-[#0071E3] hover:underline flex items-center gap-1 cursor-pointer"
              >
                <span>&larr;</span> Volver al listado
              </button>
              
              <button
                type="button"
                @click="vistaMovil = 'mapa'"
                class="md:hidden text-xs text-[#1D1D1F] font-semibold bg-white border border-[#E5E5EA] px-2 py-0.5 rounded-[4px]"
              >
                Ver en mapa
              </button>
            </div>

            <!-- Ficha Resumen -->
            <div class="p-3 bg-white border-b border-[#E5E5EA] shrink-0">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <h3 class="text-xs font-bold text-[#1D1D1F] leading-tight">{{ clienteSeleccionado.cliente }}</h3>
                  <p class="text-[11px] text-[#6E6E73] mt-0.5">{{ clienteSeleccionado.direccion || 'Sin dirección' }}</p>
                </div>
                <span
                  class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded text-white shrink-0"
                  :style="{ backgroundColor: getCanalColor(clienteSeleccionado.canal_calculado) }"
                >
                  {{ clienteSeleccionado.canal_calculado }}
                </span>
              </div>

              <!-- Pestañas del Expediente -->
              <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA] mt-3">
                <button
                  type="button"
                  @click="pestañaExpediente = 'ventas'"
                  class="flex-1 py-1 text-[11px] font-medium rounded-[4px] transition-all text-center cursor-pointer"
                  :class="pestañaExpediente === 'ventas' ? 'bg-white text-[#1D1D1F] shadow-2xs font-semibold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                >
                  Histórico Compras
                </button>
                <button
                  type="button"
                  @click="pestañaExpediente = 'visitas'"
                  class="flex-1 py-1 text-[11px] font-medium rounded-[4px] transition-all text-center flex items-center justify-center gap-1 cursor-pointer"
                  :class="pestañaExpediente === 'visitas' ? 'bg-white text-[#1D1D1F] shadow-2xs font-semibold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                >
                  <span>Fotos y Visitas</span>
                  <span class="px-1 text-[9px] font-mono rounded bg-[#E5E5EA] text-[#1D1D1F]">
                    {{ visitasHistoricas.length }}
                  </span>
                </button>
              </div>
            </div>

            <!-- Ventas -->
            <div v-if="pestañaExpediente === 'ventas'" class="flex-1 overflow-y-auto p-2 space-y-2">
              <div class="grid grid-cols-2 gap-2 mb-2">
                <div class="p-2 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px]">
                  <span class="text-[9px] font-semibold text-[#86868B] uppercase tracking-wider block">Total ({{ anioSeleccionado }})</span>
                  <span class="text-sm font-bold text-[#1D1D1F] font-mono tabular-nums">
                    Bs. {{ detalleVentasCliente.total_general ? detalleVentasCliente.total_general.toFixed(2) : '0.00' }}
                  </span>
                </div>
                <div class="p-2 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px]">
                  <span class="text-[9px] font-semibold text-[#86868B] uppercase tracking-wider block">Transacciones</span>
                  <span class="text-sm font-bold text-[#1D1D1F] font-mono tabular-nums">
                    {{ detalleVentasCliente.total_ventas || 0 }} compras
                  </span>
                </div>
              </div>

              <div v-if="cargandoVentas" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Cargando histórico de facturas...
              </div>

              <div v-else-if="!detalleVentasCliente.ventas || detalleVentasCliente.ventas.length === 0" class="p-8 text-center text-xs text-[#86868B]">
                No se registraron compras en el año {{ anioSeleccionado }} para los meses seleccionados.
              </div>

              <div
                v-for="v in detalleVentasCliente.ventas"
                :key="v.venta_id"
                class="bg-white border border-[#E5E5EA] rounded-[8px] overflow-hidden"
              >
                <button
                  type="button"
                  @click="toggleTicket(v.venta_id)"
                  class="w-full text-left p-2.5 hover:bg-[#FBFBFD] transition-colors flex items-center justify-between cursor-pointer"
                >
                  <div>
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-[#1D1D1F]">
                      <span class="font-mono text-[#0071E3]">Ticket #{{ v.venta_id }}</span>
                      <span v-if="v.nro_factura" class="text-[10px] text-[#86868B] font-mono">(Fact: {{ v.nro_factura }})</span>
                    </div>
                    <div class="text-[10px] font-mono text-[#6E6E73] mt-0.5">
                      {{ v.fecha }} • {{ v.hora }} • {{ v.vendedor }}
                    </div>
                  </div>
                  <div class="text-right">
                    <span class="text-xs font-bold text-[#1D1D1F] font-mono tabular-nums block">
                      Bs. {{ v.monto_ticket.toFixed(2) }}
                    </span>
                    <span class="text-[9px] text-[#86868B] font-mono">
                      {{ v.productos.length }} prods. {{ ticketAbiertoId === v.venta_id ? '▲' : '▼' }}
                    </span>
                  </div>
                </button>

                <div v-if="ticketAbiertoId === v.venta_id" class="border-t border-[#E5E5EA] bg-[#FBFBFD] p-2 divide-y divide-[#E5E5EA]">
                  <div
                    v-for="p in v.productos"
                    :key="p.id"
                    class="py-1.5 text-[11px] flex items-start justify-between gap-2"
                  >
                    <div>
                      <p class="font-medium text-[#1D1D1F] line-clamp-1 leading-tight">{{ p.producto }}</p>
                      <p class="text-[9px] font-mono text-[#86868B]">
                        {{ p.cantidad }} unid. × Bs. {{ p.precio_unitario.toFixed(2) }}
                        <span v-if="p.descuento > 0" class="text-[#B25E00]">(-Bs. {{ p.descuento.toFixed(2) }})</span>
                      </p>
                    </div>
                    <span class="font-mono font-semibold text-[#1D1D1F] tabular-nums shrink-0">
                      Bs. {{ p.monto_final.toFixed(2) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Visitas y Fotos -->
            <div v-else class="flex-1 overflow-y-auto p-2 space-y-2">
              <div v-if="cargandoVisitas" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Cargando histórico de fotografías...
              </div>

              <div v-else-if="visitasHistoricas.length === 0" class="p-8 text-center text-xs text-[#86868B]">
                No hay fotografías registradas para este cliente.
              </div>

              <div
                v-for="(vis, idx) in visitasHistoricas"
                :key="vis.id"
                class="bg-white border border-[#E5E5EA] rounded-[8px] p-2.5 flex items-start gap-3 hover:border-[#86868B] transition-colors"
              >
                <div
                  @click="abrirCarruselEn(idx)"
                  class="w-16 h-16 rounded-[6px] overflow-hidden bg-[#F2F2F7] border border-[#E5E5EA] shrink-0 cursor-pointer relative group"
                >
                  <img
                    v-if="vis.photo_url"
                    :src="vis.photo_url"
                    alt="Visita"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                    loading="lazy"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center text-[10px] text-[#86868B]">
                    Sin foto
                  </div>
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1">
                    <span
                      class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded border border-[#E5E5EA]"
                      :class="vis.status === 'PREVENTA' ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FFF5E5] text-[#B25E00]'"
                    >
                      {{ vis.status }}
                    </span>
                    <span class="text-[10px] font-mono text-[#86868B]">{{ vis.fecha }} {{ vis.hora }}</span>
                  </div>
                  <p class="text-[11px] font-medium text-[#1D1D1F] mt-1 font-mono">
                    Ruta: {{ vis.route }}
                  </p>
                  <p v-if="vis.comments" class="text-[10px] text-[#6E6E73] mt-0.5 italic line-clamp-2">
                    "{{ vis.comments }}"
                  </p>
                </div>
              </div>
            </div>
          </template>
        </aside>

        <!-- Panel Derecho: Visor Cartográfico Leaflet -->
        <main
          class="flex-1 relative min-h-0 bg-[#E5E5EA]"
          :class="vistaMovil === 'lista' ? 'hidden md:block' : 'block h-full w-full'"
        >
          <div id="map-cercanos" class="absolute inset-0 w-full h-full"></div>

          <!-- Leyenda en el Mapa -->
          <div class="hidden sm:block absolute bottom-4 right-4 bg-white/95 backdrop-blur-xs border border-[#E5E5EA] rounded-[8px] p-2.5 shadow-md z-[500] text-[11px] max-w-[240px]">
            <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider mb-1.5">Leyenda (Relleno = Canal)</p>
            <div class="grid grid-cols-2 gap-x-2 gap-y-1 pb-1.5 border-b border-[#E5E5EA]">
              <div v-for="c in canales" :key="c" class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(c) }"></span>
                <span class="text-[#1D1D1F] font-mono text-[10px]">{{ c }}</span>
              </div>
            </div>
            
            <div class="mt-1.5">
              <p class="text-[9px] font-semibold text-[#86868B] uppercase tracking-wider mb-1">Borde = Ruta</p>
              <div class="max-h-24 overflow-y-auto space-y-0.5 no-scrollbar">
                <div v-for="r in filtroRutas.slice(0, 8)" :key="r" class="flex items-center gap-1.5 text-[10px] font-mono text-[#1D1D1F]">
                  <span class="w-2 h-2 rounded-full shrink-0 border border-black/20" :style="{ backgroundColor: getRutaColor(r) }"></span>
                  <span class="truncate">{{ r }}</span>
                </div>
                <div v-if="filtroRutas.length > 8" class="text-[9px] text-[#86868B] italic">
                  +{{ filtroRutas.length - 8 }} rutas más...
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>

      <!-- Modal de Fotos Lightbox -->
      <Teleport to="body">
        <div
          v-if="modalCarruselAbierto && fotoActiva"
          class="fixed inset-0 z-[99999] bg-black/85 backdrop-blur-md flex flex-col items-center justify-between p-4 select-none"
        >
          <div class="w-full max-w-4xl flex items-center justify-between text-white pb-2 border-b border-white/10">
            <div>
              <span class="text-xs font-bold uppercase tracking-wider text-[#86868B]">Auditoría Fotográfica</span>
              <h4 class="text-sm font-semibold text-white">{{ clienteSeleccionado?.cliente }}</h4>
            </div>
            <div class="flex items-center gap-4 text-xs font-mono">
              <span class="text-white/60">{{ indiceFotoActiva + 1 }} de {{ visitasHistoricas.length }}</span>
              <button
                type="button"
                @click="cerrarCarrusel"
                class="p-1 rounded-full text-white/80 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
              >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <div class="relative w-full max-w-4xl flex-1 flex items-center justify-center my-3 overflow-hidden">
            <button
              v-if="visitasHistoricas.length > 1"
              type="button"
              @click.stop="fotoAnterior"
              class="absolute left-2 z-10 p-2.5 rounded-full bg-black/50 text-white/80 hover:text-white hover:bg-black/80 backdrop-blur-xs transition-all cursor-pointer"
              title="Anterior"
            >
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
              </svg>
            </button>

            <div class="max-h-[70vh] flex flex-col items-center justify-center">
              <img
                :src="fotoActiva.photo_url"
                :alt="fotoActiva.status"
                class="max-h-[68vh] max-w-full rounded-[8px] object-contain shadow-2xl border border-white/10"
              />
            </div>

            <button
              v-if="visitasHistoricas.length > 1"
              type="button"
              @click.stop="fotoSiguiente"
              class="absolute right-2 z-10 p-2.5 rounded-full bg-black/50 text-white/80 hover:text-white hover:bg-black/80 backdrop-blur-xs transition-all cursor-pointer"
              title="Siguiente"
            >
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <div class="w-full max-w-4xl bg-white/10 backdrop-blur-sm rounded-[8px] p-3 text-white text-xs border border-white/10 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <span
                class="px-2 py-0.5 rounded font-mono font-bold text-[10px]"
                :class="fotoActiva.status === 'PREVENTA' ? 'bg-[#248A3D] text-white' : 'bg-[#B25E00] text-white'"
              >
                {{ fotoActiva.status }}
              </span>
              <span class="font-mono text-white/80">{{ fotoActiva.fecha }} a las {{ fotoActiva.hora }}</span>
              <span class="text-white/40">•</span>
              <span class="font-mono text-white/80">Ruta: {{ fotoActiva.route }}</span>
            </div>

            <p v-if="fotoActiva.comments" class="text-white/70 italic text-[11px] max-w-md truncate">
              "{{ fotoActiva.comments }}"
            </p>

            <a
              :href="fotoActiva.photo_url"
              target="_blank"
              class="text-[11px] text-[#0071E3] hover:underline font-medium"
            >
              Abrir original &rarr;
            </a>
          </div>
        </div>
      </Teleport>
    </div>
  </RuteoLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, Teleport } from 'vue';
import axios from 'axios';
import RuteoLayout from '@/Pages/Supervisor/Ruteo/Layout.vue';
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
    default: () => [],
  },
  anio_default: {
    type: Number,
    default: 2024,
  },
  meses_default: {
    type: Array,
    default: () => [4, 5, 6],
  },
});

const CANAL_COLORS = {
  'MAY': '#0071E3',
  'TDB': '#34C759',
  'MZO': '#FF9500',
  'PROV': '#AF52DE',
  'PRT': '#8E8E93',
};

function getCanalColor(canal) {
  if (!canal) return '#8E8E93';
  return CANAL_COLORS[canal] || '#5856D6';
}

const RUTA_PALETTE = ['#1D1D1F', '#00C7BE', '#A2845E', '#30B0C7', '#6366F1', '#EC4899', '#14B8A6', '#F59E0B', '#8B5CF6', '#10B981'];
const rutaColorCache = new Map();

function getRutaColor(ruta) {
  if (!ruta) return '#1D1D1F';
  if (!rutaColorCache.has(ruta)) {
    const colorIndex = rutaColorCache.size % RUTA_PALETTE.length;
    rutaColorCache.set(ruta, RUTA_PALETTE[colorIndex]);
  }
  return rutaColorCache.get(ruta);
}

const rutaToCanalMap = computed(() => {
  const map = {};
  if (props.catalogo_rutas) {
    props.catalogo_rutas.forEach(r => {
      map[r.ruta] = r.canal;
    });
  }
  return map;
});

const mesesLista = [
  { numero: 1, corto: '01', nombre: 'Enero' },
  { numero: 2, corto: '02', nombre: 'Febrero' },
  { numero: 3, corto: '03', nombre: 'Marzo' },
  { numero: 4, corto: '04', nombre: 'Abril' },
  { numero: 5, corto: '05', nombre: 'Mayo' },
  { numero: 6, corto: '06', nombre: 'Junio' },
  { numero: 7, corto: '07', nombre: 'Julio' },
  { numero: 8, corto: '08', nombre: 'Agosto' },
  { numero: 9, corto: '09', nombre: 'Septiembre' },
  { numero: 10, corto: '10', nombre: 'Octubre' },
  { numero: 11, corto: '11', nombre: 'Noviembre' },
  { numero: 12, corto: '12', nombre: 'Diciembre' },
];

const opcionesRadio = [
  { etiqueta: '100m', valor: 100 },
  { etiqueta: '250m', valor: 250 },
  { etiqueta: '300m', valor: 300 },
  { etiqueta: '500m', valor: 500 },
  { etiqueta: '1km', valor: 1000 },
  { etiqueta: '2km', valor: 2000 },
];

// Estado de Filtros
const radioSeleccionado = ref(300);
const anioSeleccionado = ref(props.anio_default || 2024);
const filtroCanales = ref([...props.canales]);
const filtroRutas = ref(props.catalogo_rutas.map(r => r.ruta));
const filtroMeses = ref([...props.meses_default]);

const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });
const busquedaRuta = ref('');
const vistaMovil = ref('mapa');

const cargando = ref(false);
const obteniendoGps = ref(false);
const origenConsulta = ref(null);
const clientesCercanos = ref([]);

// Estado del Inspector de Cliente
const clienteSeleccionado = ref(null);
const pestañaExpediente = ref('ventas');
const cargandoVentas = ref(false);
const detalleVentasCliente = ref({ total_general: 0, total_ventas: 0, ventas: [] });
const ticketAbiertoId = ref(null);

// Estado de Visitas y Carrusel
const cargandoVisitas = ref(false);
const visitasHistoricas = ref([]);
const modalCarruselAbierto = ref(false);
const indiceFotoActiva = ref(0);

const fotoActiva = computed(() => {
  if (!visitasHistoricas.value || visitasHistoricas.value.length === 0) return null;
  return visitasHistoricas.value[indiceFotoActiva.value] || null;
});

let map = null;
let centerMarker = null;
let radiusCircle = null;
let markersLayer = null;
const markersMap = new Map();

const rutasDisponibles = computed(() => {
  if (!props.catalogo_rutas) return [];
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
  if (filtroMeses.value.length === 12) return 'Todos';
  if (filtroMeses.value.length === 0) return 'Ninguno';
  if (filtroMeses.value.length <= 3) {
    return filtroMeses.value.map(m => String(m).padStart(2, '0')).join(', ');
  }
  return `${filtroMeses.value.length} sel.`;
});

const labelCanales = computed(() => {
  if (!props.canales || props.canales.length === 0) return 'Todos';
  if (filtroCanales.value.length === props.canales.length) return 'Todos';
  if (filtroCanales.value.length === 0) return 'Ninguno';
  return `${filtroCanales.value.length} sel.`;
});

const labelRutas = computed(() => {
  if (!rutasDisponibles.value || rutasDisponibles.value.length === 0) return 'Ninguna';
  if (filtroRutas.value.length === rutasDisponibles.value.length) return 'Todas';
  if (filtroRutas.value.length === 0) return 'Ninguna';
  return `${filtroRutas.value.length} sel.`;
});

// Calcula dinámicamente la posición exacta debajo del botón disparador
function toggleMenu(nombre, event) {
  if (menuAbierto.value === nombre) {
    menuAbierto.value = null;
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  const anchoMenu = nombre === 'rutas' ? 256 : (nombre === 'meses' ? 224 : 208);
  
  // Evitar que el menú desborde el margen derecho de la pantalla
  let leftPos = rect.left;
  if (leftPos + anchoMenu > window.innerWidth - 8) {
    leftPos = Math.max(8, window.innerWidth - anchoMenu - 8);
  }

  posicionMenu.value = {
    top: rect.bottom + 4,
    left: leftPos,
  };
  menuAbierto.value = nombre;
}

function seleccionarTodosMeses() {
  filtroMeses.value = mesesLista.map(m => m.numero);
  reconsultar();
}

function limpiarMeses() {
  filtroMeses.value = [];
  reconsultar();
}

function seleccionarTodosCanales() {
  filtroCanales.value = [...props.canales];
  filtroRutas.value = rutasDisponibles.value.map(r => r.ruta);
  reconsultar();
}

function limpiarCanales() {
  filtroCanales.value = [];
  filtroRutas.value = [];
  reconsultar();
}

function onCanalesChange() {
  const rutasValidas = new Set(rutasDisponibles.value.map(r => r.ruta));
  filtroRutas.value = filtroRutas.value.filter(r => rutasValidas.has(r));
  if (filtroRutas.value.length === 0 && rutasDisponibles.value.length > 0) {
    filtroRutas.value = rutasDisponibles.value.map(r => r.ruta);
  }
  reconsultar();
}

function seleccionarTodasRutas() {
  filtroRutas.value = rutasDisponibles.value.map(r => r.ruta);
  reconsultar();
}

function limpiarRutas() {
  filtroRutas.value = [];
  reconsultar();
}

function seleccionarRadio(nuevoRadio) {
  radioSeleccionado.value = nuevoRadio;
  if (radiusCircle) {
    radiusCircle.setRadius(nuevoRadio);
  }
  if (origenConsulta.value) {
    ejecutarBusqueda(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

function reconsultar() {
  if (origenConsulta.value) {
    ejecutarBusqueda(origenConsulta.value.lat, origenConsulta.value.lng);
  }
  if (clienteSeleccionado.value) {
    cargarHistoricoVentas(clienteSeleccionado.value.cliente_id);
    cargarHistoricoVisitas(clienteSeleccionado.value.cliente_id);
  }
}

async function ejecutarBusqueda(lat, lng) {
  origenConsulta.value = { lat, lng };
  cargando.value = true;

  if (centerMarker) {
    centerMarker.setLatLng([lat, lng]);
  } else {
    centerMarker = L.circleMarker([lat, lng], {
      radius: 7,
      fillColor: '#0071E3',
      color: '#FFFFFF',
      weight: 2,
      opacity: 1,
      fillOpacity: 1,
    }).addTo(map);
  }

  if (radiusCircle) {
    radiusCircle.setLatLng([lat, lng]);
    radiusCircle.setRadius(radioSeleccionado.value);
  } else {
    radiusCircle = L.circle([lat, lng], {
      radius: radioSeleccionado.value,
      color: '#0071E3',
      weight: 1.5,
      dashArray: '4, 4',
      fillColor: '#0071E3',
      fillOpacity: 0.08,
    }).addTo(map);
  }

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

    clientesCercanos.value = res.data.clientes.map(c => ({
      ...c,
      canal_calculado: rutaToCanalMap.value[c.ruta] || 'PRT'
    }));

    renderizarClientesEnMapa();
  } catch (err) {
    console.error('Error calculando proximidad:', err);
  } finally {
    cargando.value = false;
  }
}

function renderizarClientesEnMapa() {
  if (!map) return;
  markersLayer.clearLayers();
  markersMap.clear();

  const unicaRuta = filtroRutas.value.length === 1;

  clientesCercanos.value.forEach(c => {
    const fillColor = getCanalColor(c.canal_calculado);
    const strokeColor = unicaRuta ? '#FFFFFF' : getRutaColor(c.ruta);

    const marker = L.circleMarker([c.latitud, c.longitud], {
      radius: 5.5,
      fillColor: fillColor,
      color: strokeColor,
      weight: unicaRuta ? 1.5 : 2.5,
      opacity: 1,
      fillOpacity: 0.95,
    });

    marker.on('click', () => {
      abrirExpedienteCliente(c);
    });

    marker.bindPopup(`
      <div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; line-height: 1.4; color: #1D1D1F; max-width: 220px;">
        <div style="font-weight: 600; font-size: 12px; margin-bottom: 2px;">${c.cliente}</div>
        <div style="color: ${fillColor}; font-weight: bold; margin-bottom: 2px;">
          ${c.canal_calculado} • a ${c.distancia_metros} m
        </div>
        <div style="color: #6E6E73; margin-bottom: 4px;">${c.direccion || 'Sin dirección'}</div>
        <div style="font-family: monospace; font-size: 10px; color: #1D1D1F; border-top: 1px solid #E5E5EA; padding-top: 4px;">
          Compras (${anioSeleccionado.value}): <strong>Bs. ${c.total_compras.toFixed(2)}</strong> (${c.total_pedidos} ped.)
        </div>
      </div>
    `);

    marker.addTo(markersLayer);
    markersMap.set(c.id, marker);
  });
}

async function abrirExpedienteCliente(c) {
  clienteSeleccionado.value = c;
  pestañaExpediente.value = 'ventas';
  ticketAbiertoId.value = null;

  if (map) {
    map.flyTo([c.latitud, c.longitud], 18, { duration: 0.6 });
    const marker = markersMap.get(c.id);
    if (marker) {
      marker.openPopup();
    }
  }

  await Promise.all([
    cargarHistoricoVentas(c.cliente_id),
    cargarHistoricoVisitas(c.cliente_id)
  ]);
}

async function cargarHistoricoVentas(clienteId) {
  if (!clienteId) return;
  cargandoVentas.value = true;
  try {
    const res = await axios.post(route('supervisor.ruteo.cercanos.cliente.ventas', { clienteId }), {
      meses: filtroMeses.value,
      anio: anioSeleccionado.value,
    });
    detalleVentasCliente.value = res.data;
  } catch (err) {
    console.error('Error cargando ventas:', err);
  } finally {
    cargandoVentas.value = false;
  }
}

async function cargarHistoricoVisitas(clienteId) {
  if (!clienteId) return;
  cargandoVisitas.value = true;
  try {
    const res = await axios.post(route('supervisor.ruteo.cercanos.cliente.visitas', { clienteId }));
    visitasHistoricas.value = res.data.visitas;
  } catch (err) {
    console.error('Error cargando visitas:', err);
  } finally {
    cargandoVisitas.value = false;
  }
}

function cerrarExpediente() {
  clienteSeleccionado.value = null;
  detalleVentasCliente.value = { total_general: 0, total_ventas: 0, ventas: [] };
  visitasHistoricas.value = [];
  ticketAbiertoId.value = null;
}

function toggleTicket(ventaId) {
  ticketAbiertoId.value = ticketAbiertoId.value === ventaId ? null : ventaId;
}

function abrirCarruselEn(index) {
  indiceFotoActiva.value = index;
  modalCarruselAbierto.value = true;
}

function cerrarCarrusel() {
  modalCarruselAbierto.value = false;
}

function fotoSiguiente() {
  if (indiceFotoActiva.value < visitasHistoricas.value.length - 1) {
    indiceFotoActiva.value++;
  } else {
    indiceFotoActiva.value = 0;
  }
}

function fotoAnterior() {
  if (indiceFotoActiva.value > 0) {
    indiceFotoActiva.value--;
  } else {
    indiceFotoActiva.value = visitasHistoricas.value.length - 1;
  }
}

function manejarTeclado(e) {
  if (!modalCarruselAbierto.value) return;
  if (e.key === 'ArrowRight') fotoSiguiente();
  if (e.key === 'ArrowLeft') fotoAnterior();
  if (e.key === 'Escape') cerrarCarrusel();
}

function obtenerUbicacionActual() {
  if (!navigator.geolocation) {
    alert('Tu navegador no soporta geolocalización satelital.');
    return;
  }

  obteniendoGps.value = true;
  navigator.geolocation.getCurrentPosition(
    (position) => {
      obteniendoGps.value = false;
      const { latitude, longitude } = position.coords;
      map.flyTo([latitude, longitude], 16, { duration: 0.8 });
      ejecutarBusqueda(latitude, longitude);
      vistaMovil.value = 'mapa';
    },
    () => {
      obteniendoGps.value = false;
      alert('No se pudo obtener tu ubicación. Verifica los permisos de GPS.');
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
}

function initMap() {
  map = L.map('map-cercanos', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([-16.5000, -68.1500], 13);

  L.control.zoom({ position: 'topright' }).addTo(map);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  markersLayer = L.featureGroup().addTo(map);

  map.on('click', (e) => {
    menuAbierto.value = null;
    ejecutarBusqueda(e.latlng.lat, e.latlng.lng);
  });
}

onMounted(() => {
  initMap();
  window.addEventListener('keydown', manejarTeclado);
});

onUnmounted(() => {
  window.removeEventListener('keydown', manejarTeclado);
});
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>