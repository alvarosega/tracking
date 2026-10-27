<template>
  <SupervisorLayout title="Administración de Usuarios">
    <div class="space-y-4 max-w-7xl mx-auto pb-12 font-sans">
      <!-- Navegación de Submódulos Admin (Permisos & Usuarios) -->
      <AdminSubTabs active-tab="usuarios" />

      <!-- Encabezado de la Vista -->
      <div class="apple-glass-card rounded-[18px] p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm border border-black/[0.04]">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-[17px] font-bold text-[#1D1D1F] tracking-tight">
              Administración de Usuarios
            </h1>
            <span class="text-[9px] font-mono font-bold px-2 py-0.5 rounded bg-purple-50 text-purple-700 border border-purple-200">
              ROL SUPERADMIN
            </span>
          </div>
          <p class="text-[11px] text-[#86868B] mt-0.5">
            Creación de cuentas, cambio de credenciales, asignación de rutas y control de estado de usuarios del sistema
          </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
          <button
            type="button"
            @click="recargar"
            :disabled="cargando"
            class="px-3 py-1.5 text-[11px] font-semibold rounded-[10px] bg-white text-[#1D1D1F] border border-black/[0.12] hover:bg-black/[0.03] transition-all cursor-pointer flex items-center gap-1.5 shadow-sm"
          >
            <span :class="cargando ? 'animate-spin' : ''">↻</span>
            <span>Recargar</span>
          </button>

          <button
            type="button"
            @click="abrirModalCrear"
            class="px-4 py-1.5 text-[11px] font-bold rounded-[10px] bg-[#0071E3] text-white hover:bg-[#0077ED] active:scale-95 transition-all shadow-[0_2px_8px_rgba(0,113,227,0.35)] cursor-pointer flex items-center gap-1.5"
          >
            <span class="text-[13px] leading-none">+</span>
            <span>Nuevo Usuario</span>
          </button>
        </div>
      </div>

      <!-- Alertas de Éxito / Error -->
      <div
        v-if="mensajeExito"
        class="p-3.5 rounded-[14px] bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-semibold shadow-sm flex items-center justify-between animate-fade-in"
      >
        <div class="flex items-center gap-2">
          <span>✓</span>
          <span>{{ mensajeExito }}</span>
        </div>
        <button type="button" @click="mensajeExito = null" class="text-emerald-800 hover:opacity-70 p-0.5 cursor-pointer">✕</button>
      </div>

      <div
        v-if="mensajeError"
        class="p-3.5 rounded-[14px] bg-red-50 text-red-700 border border-red-200 text-[11px] font-semibold shadow-sm flex items-center justify-between animate-fade-in"
      >
        <div class="flex items-center gap-2">
          <span>⚠</span>
          <span>{{ mensajeError }}</span>
        </div>
        <button type="button" @click="mensajeError = null" class="text-red-700 hover:opacity-70 p-0.5 cursor-pointer">✕</button>
      </div>

      <!-- KPIs Resumen de Cuentas -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
        <div class="apple-glass-card rounded-[14px] p-3 border border-black/[0.04] shadow-xs">
          <div class="text-[10px] font-medium text-[#86868B] uppercase tracking-wider">Total Usuarios</div>
          <div class="text-[20px] font-bold text-[#1D1D1F] tracking-tight mt-0.5 font-mono">
            {{ usuariosList.length }}
          </div>
          <div class="text-[9.5px] text-[#86868B] mt-0.5">Cuentas en base de datos</div>
        </div>

        <div class="apple-glass-card rounded-[14px] p-3 border border-black/[0.04] shadow-xs">
          <div class="text-[10px] font-medium text-blue-700 uppercase tracking-wider">Supervisores</div>
          <div class="text-[20px] font-bold text-blue-700 tracking-tight mt-0.5 font-mono">
            {{ conteoRoles.supervisores }}
          </div>
          <div class="text-[9.5px] text-[#86868B] mt-0.5">Gestión operativa</div>
        </div>

        <div class="apple-glass-card rounded-[14px] p-3 border border-black/[0.04] shadow-xs">
          <div class="text-[10px] font-medium text-emerald-700 uppercase tracking-wider">Vendedores</div>
          <div class="text-[20px] font-bold text-emerald-700 tracking-tight mt-0.5 font-mono">
            {{ conteoRoles.vendedores }}
          </div>
          <div class="text-[9.5px] text-[#86868B] mt-0.5">Fuerza de ventas</div>
        </div>

        <div class="apple-glass-card rounded-[14px] p-3 border border-black/[0.04] shadow-xs">
          <div class="text-[10px] font-medium text-purple-700 uppercase tracking-wider">Superadmins</div>
          <div class="text-[20px] font-bold text-purple-700 tracking-tight mt-0.5 font-mono">
            {{ conteoRoles.superadmins }}
          </div>
          <div class="text-[9.5px] text-[#86868B] mt-0.5">Control maestro</div>
        </div>

        <div class="apple-glass-card rounded-[14px] p-3 border border-black/[0.04] shadow-xs col-span-2 sm:col-span-1">
          <div class="text-[10px] font-medium text-red-600 uppercase tracking-wider">Inactivos</div>
          <div class="text-[20px] font-bold text-red-600 tracking-tight mt-0.5 font-mono">
            {{ conteoRoles.inactivos }}
          </div>
          <div class="text-[9.5px] text-[#86868B] mt-0.5">Acceso bloqueado</div>
        </div>
      </div>

      <!-- Barra de Filtros y Búsqueda -->
      <div class="apple-glass-card rounded-[16px] p-3 shadow-xs border border-black/[0.04] flex flex-col md:flex-row items-stretch md:items-center justify-between gap-2.5">
        <!-- Buscador -->
        <div class="relative flex-1">
          <input
            v-model="busqueda"
            type="text"
            placeholder="Buscar por usuario o ruta (ej. R01, supervisor)..."
            class="w-full px-3 py-1.5 pl-8 text-[11px] rounded-[10px] border border-black/[0.12] bg-white focus:outline-none focus:border-[#0071E3] font-sans"
          />
          <svg class="w-3.5 h-3.5 text-[#86868B] absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Filtros Rápidos -->
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Filtro por Rol -->
          <div class="flex items-center gap-1 text-[11px]">
            <span class="text-[#86868B] text-[10px] font-semibold">Rol:</span>
            <select
              v-model="filtroRol"
              class="px-2.5 py-1 text-[11px] rounded-[8px] border border-black/[0.12] bg-white font-sans focus:outline-none focus:border-[#0071E3]"
            >
              <option value="todos">Todos los roles</option>
              <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>

          <!-- Filtro por Estado -->
          <div class="flex items-center gap-1 text-[11px]">
            <span class="text-[#86868B] text-[10px] font-semibold">Estado:</span>
            <select
              v-model="filtroEstado"
              class="px-2.5 py-1 text-[11px] rounded-[8px] border border-black/[0.12] bg-white font-sans focus:outline-none focus:border-[#0071E3]"
            >
              <option value="todos">Todos</option>
              <option value="activos">Solo Activos</option>
              <option value="inactivos">Solo Inactivos</option>
            </select>
          </div>

          <!-- Contador -->
          <span class="text-[10px] font-mono font-semibold text-[#86868B] bg-black/[0.04] px-2 py-1 rounded-full">
            {{ usuariosFiltrados.length }} de {{ usuariosList.length }}
          </span>
        </div>
      </div>

      <!-- Tabla Principal de Usuarios (Desktop) y Cards (Móvil) -->
      <div class="apple-glass-card rounded-[18px] shadow-sm border border-black/[0.04] overflow-hidden bg-white/90">
        <!-- Vista Desktop: Tabla -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-left border-collapse text-[11px]">
            <thead>
              <tr class="border-b border-black/[0.06] bg-black/[0.015] text-[#86868B] text-[10px] font-bold uppercase tracking-wider select-none">
                <th class="py-3 px-4">Usuario</th>
                <th class="py-3 px-4">Rol en Sistema</th>
                <th class="py-3 px-4">Ruta Asignada</th>
                <th class="py-3 px-4">Reglas Permisos</th>
                <th class="py-3 px-4 text-center">Estado</th>
                <th class="py-3 px-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04]">
              <tr
                v-if="usuariosFiltrados.length === 0"
                class="text-center text-[#86868B] py-8"
              >
                <td colspan="6" class="py-8">
                  No se encontraron usuarios con los filtros aplicados.
                </td>
              </tr>

              <tr
                v-for="u in usuariosFiltrados"
                :key="u.id"
                class="hover:bg-black/[0.015] transition-colors"
                :class="!u.is_active ? 'opacity-60 bg-black/[0.01]' : ''"
              >
                <!-- Usuario -->
                <td class="py-3 px-4">
                  <div class="flex items-center gap-2.5">
                    <div
                      class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-[11px] shrink-0 font-mono shadow-2xs"
                      :class="obtenerAvatarClase(u.role)"
                    >
                      {{ (u.username || '?').substring(0, 2).toUpperCase() }}
                    </div>
                    <div class="truncate">
                      <div class="font-bold text-[#1D1D1F] truncate text-[12px]">{{ u.username }}</div>
                      <div class="text-[9.5px] text-[#86868B] font-mono">
                        ID #{{ u.id }} · Creado: {{ formatearFecha(u.created_at) }}
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Rol -->
                <td class="py-3 px-4">
                  <span
                    class="text-[9.5px] font-mono font-bold px-2 py-0.5 rounded border inline-flex items-center gap-1"
                    :class="obtenerBadgeRol(u.role)"
                  >
                    <span>●</span>
                    <span>{{ u.role }}</span>
                  </span>
                </td>

                <!-- Ruta Asignada -->
                <td class="py-3 px-4 font-mono">
                  <span
                    v-if="u.ruta"
                    class="px-2 py-0.5 rounded bg-black/[0.04] text-[#1D1D1F] border border-black/[0.06] text-[10px] font-semibold"
                  >
                    {{ u.ruta }}
                  </span>
                  <span v-else class="text-[#86868B] italic text-[10px]">
                    Sin ruta
                  </span>
                </td>

                <!-- Reglas Permisos -->
                <td class="py-3 px-4">
                  <div v-if="puedeTenerPermisos(u.role)" class="flex items-center gap-1.5">
                    <span
                      class="text-[10px] font-mono font-semibold px-2 py-0.5 rounded"
                      :class="u.total_custom_rules > 0 ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-black/[0.04] text-[#86868B]'"
                    >
                      {{ u.total_custom_rules }} reglas
                    </span>
                    <Link
                      :href="route('supervisor.admin.permisos.index')"
                      class="text-[10px] font-medium text-[#0071E3] hover:underline"
                      title="Configurar matriz de permisos"
                    >
                      Configurar
                    </Link>
                  </div>
                  <span v-else class="text-[#86868B] italic text-[10px]">
                    {{ u.role === 'superadministrador' ? 'Acceso Global' : 'N/A' }}
                  </span>
                </td>

                <!-- Estado Activo / Inactivo -->
                <td class="py-3 px-4 text-center">
                  <button
                    type="button"
                    @click="alternarEstado(u)"
                    :disabled="u.id === usuarioActual?.id || guardandoId === u.id"
                    class="relative inline-flex items-center cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                    :title="u.id === usuarioActual?.id ? 'No puedes desactivar tu propia cuenta' : 'Clic para alternar estado'"
                  >
                    <div
                      class="w-7 h-4 bg-neutral-200 rounded-full transition-all peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all"
                      :class="u.is_active ? 'bg-emerald-500 after:translate-x-3' : 'bg-neutral-300'"
                    ></div>
                    <span class="ml-1.5 text-[10px] font-mono font-semibold" :class="u.is_active ? 'text-emerald-700' : 'text-neutral-500'">
                      {{ u.is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                  </button>
                </td>

                <!-- Acciones -->
                <td class="py-3 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      type="button"
                      @click="abrirModalEditar(u)"
                      class="px-2.5 py-1 text-[10px] font-semibold rounded-[8px] bg-white text-[#1D1D1F] border border-black/[0.12] hover:bg-black/[0.04] transition-all cursor-pointer flex items-center gap-1 shadow-2xs"
                      title="Editar usuario y contraseña"
                    >
                      <svg class="w-3 h-3 text-[#6E6E73]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                      </svg>
                      <span>Editar</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Vista Móvil: Tarjetas Apiladas -->
        <div class="md:hidden divide-y divide-black/[0.06] p-2 space-y-2">
          <div
            v-if="usuariosFiltrados.length === 0"
            class="py-8 text-center text-[#86868B] text-[11px]"
          >
            No se encontraron usuarios.
          </div>

          <div
            v-for="u in usuariosFiltrados"
            :key="u.id"
            class="p-3 rounded-[12px] bg-white border border-black/[0.05] space-y-2 shadow-2xs"
            :class="!u.is_active ? 'opacity-70 bg-black/[0.01]' : ''"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2 truncate">
                <div
                  class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-[10px] shrink-0 font-mono shadow-2xs"
                  :class="obtenerAvatarClase(u.role)"
                >
                  {{ (u.username || '?').substring(0, 2).toUpperCase() }}
                </div>
                <div class="truncate">
                  <div class="font-bold text-[#1D1D1F] text-[12px] truncate">{{ u.username }}</div>
                  <div class="text-[9px] text-[#86868B] font-mono">ID #{{ u.id }}</div>
                </div>
              </div>

              <span
                class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded border"
                :class="obtenerBadgeRol(u.role)"
              >
                {{ u.role }}
              </span>
            </div>

            <div class="flex items-center justify-between text-[10px] pt-1 border-t border-black/[0.04]">
              <span class="text-[#86868B]">
                {{ u.ruta ? 'Ruta: ' + u.ruta : 'Sin ruta' }}
              </span>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="alternarEstado(u)"
                  :disabled="u.id === usuarioActual?.id || guardandoId === u.id"
                  class="text-[10px] font-mono font-bold px-2 py-0.5 rounded cursor-pointer"
                  :class="u.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
                >
                  {{ u.is_active ? 'Activo' : 'Inactivo' }}
                </button>

                <button
                  type="button"
                  @click="abrirModalEditar(u)"
                  class="px-2.5 py-0.5 text-[10px] font-semibold rounded bg-black/[0.04] text-[#1D1D1F] border border-black/[0.08]"
                >
                  Editar
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal de Creación / Edición de Usuario -->
      <div
        v-if="mostrarModal"
        class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4 font-sans select-none animate-fade-in"
      >
        <div class="apple-glass-card rounded-[20px] bg-white p-5 max-w-md w-full shadow-2xl border border-black/[0.08] space-y-4">
          <!-- Encabezado Modal -->
          <div class="flex items-center justify-between border-b border-black/[0.06] pb-3">
            <div>
              <h3 class="text-[14px] font-bold text-[#1D1D1F]">
                {{ esModoEdicion ? `Editar Usuario: ${usuarioEditando?.username}` : 'Nuevo Usuario del Sistema' }}
              </h3>
              <p class="text-[10px] text-[#86868B] mt-0.5">
                {{ esModoEdicion ? 'Actualiza los datos, credenciales y rol del usuario seleccionado.' : 'Completa la información para crear una nueva cuenta de acceso.' }}
              </p>
            </div>
            <button
              type="button"
              @click="cerrarModal"
              class="p-1 rounded-full text-[#86868B] hover:text-[#1D1D1F] hover:bg-black/[0.05] cursor-pointer"
            >
              ✕
            </button>
          </div>

          <!-- Alerta de Error en Modal -->
          <div
            v-if="modalError"
            class="p-2.5 rounded-[10px] bg-red-50 text-red-700 border border-red-200 text-[10px] font-medium"
          >
            {{ modalError }}
          </div>

          <!-- Formulario -->
          <form @submit.prevent="guardarUsuario" class="space-y-3">
            <!-- Username -->
            <div>
              <label class="text-[11px] font-bold text-[#1D1D1F] block mb-1">
                Nombre de Usuario: <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.username"
                type="text"
                required
                maxlength="50"
                placeholder="ej. supervisor.centro, R01, etc."
                class="w-full px-3 py-2 text-[11px] rounded-[10px] border border-black/[0.12] bg-white font-sans focus:outline-none focus:border-[#0071E3]"
              />
              <span class="text-[9.5px] text-[#86868B] mt-0.5 block">
                Identificador único de inicio de sesión.
              </span>
            </div>

            <!-- Rol -->
            <div>
              <label class="text-[11px] font-bold text-[#1D1D1F] block mb-1">
                Rol del Sistema: <span class="text-red-500">*</span>
              </label>
              <select
                v-model="form.role_id"
                required
                class="w-full px-3 py-2 text-[11px] rounded-[10px] border border-black/[0.12] bg-white font-sans focus:outline-none focus:border-[#0071E3]"
              >
                <option :value="null" disabled>Selecciona un rol...</option>
                <option v-for="r in roles" :key="r.id" :value="r.id">
                  {{ r.name.toUpperCase() }} - {{ r.description || 'Sin descripción' }}
                </option>
              </select>
            </div>

            <!-- Ruta -->
            <div>
              <label class="text-[11px] font-bold text-[#1D1D1F] block mb-1">
                Ruta Asignada: <span class="text-[#86868B] font-normal">(Opcional)</span>
              </label>
              <input
                v-model="form.ruta"
                type="text"
                maxlength="30"
                placeholder="ej. R01, R02, o vacío para administración central"
                class="w-full px-3 py-2 text-[11px] rounded-[10px] border border-black/[0.12] bg-white font-sans focus:outline-none focus:border-[#0071E3]"
              />
              <span class="text-[9.5px] text-[#86868B] mt-0.5 block">
                Código de ruta para vendedores o supervisores sectorizados.
              </span>
            </div>

            <!-- Contraseña -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-[11px] font-bold text-[#1D1D1F]">
                  Contraseña:
                  <span v-if="!esModoEdicion" class="text-red-500">*</span>
                  <span v-else class="text-[#86868B] font-normal">(Dejar en blanco para no cambiar)</span>
                </label>
                <button
                  type="button"
                  @click="generarPasswordAleatorio"
                  class="text-[9.5px] font-semibold text-[#0071E3] hover:underline cursor-pointer"
                >
                  ⚡ Generar segura
                </button>
              </div>

              <div class="relative">
                <input
                  v-model="form.password"
                  :type="mostrarPassword ? 'text' : 'password'"
                  :required="!esModoEdicion"
                  minlength="6"
                  maxlength="100"
                  placeholder="Mínimo 6 caracteres"
                  class="w-full px-3 py-2 pr-16 text-[11px] rounded-[10px] border border-black/[0.12] bg-white font-mono focus:outline-none focus:border-[#0071E3]"
                />
                <button
                  type="button"
                  @click="mostrarPassword = !mostrarPassword"
                  class="absolute right-2 top-2 text-[10px] font-medium text-[#86868B] hover:text-[#1D1D1F] px-1 cursor-pointer"
                >
                  {{ mostrarPassword ? 'Ocultar' : 'Ver' }}
                </button>
              </div>
            </div>

            <!-- Estado de la Cuenta -->
            <div class="flex items-center justify-between pt-1">
              <div>
                <span class="text-[11px] font-bold text-[#1D1D1F] block">Estado de la Cuenta:</span>
                <span class="text-[9.5px] text-[#86868B]">
                  {{ form.is_active ? 'El usuario puede iniciar sesión en la plataforma.' : 'Acceso bloqueado de forma inmediata.' }}
                </span>
              </div>
              <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input
                  type="checkbox"
                  v-model="form.is_active"
                  class="sr-only peer"
                />
                <div class="w-8 h-4.5 bg-neutral-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-emerald-500"></div>
              </label>
            </div>

            <!-- Botones de Acción -->
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-black/[0.06]">
              <button
                type="button"
                @click="cerrarModal"
                :disabled="guardandoModal"
                class="px-3 py-1.5 text-[11px] font-semibold rounded-[10px] bg-white text-[#6E6E73] border border-black/[0.12] hover:bg-black/[0.03] cursor-pointer"
              >
                Cancelar
              </button>
              <button
                type="submit"
                :disabled="guardandoModal"
                class="px-4 py-1.5 text-[11px] font-bold rounded-[10px] bg-[#0071E3] text-white hover:bg-[#0077ED] transition-all shadow-sm cursor-pointer flex items-center gap-1.5 disabled:opacity-50"
              >
                <span v-if="guardandoModal" class="animate-spin text-[10px]">↻</span>
                <span>{{ esModoEdicion ? 'Actualizar Usuario' : 'Crear Usuario' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import AdminSubTabs from './Components/AdminSubTabs.vue'

const props = defineProps({
  usuarios: {
    type: Array,
    default: () => [],
  },
  roles: {
    type: Array,
    default: () => [],
  },
  submoduloActivo: {
    type: String,
    default: 'usuarios',
  },
})

const page = usePage()
const usuarioActual = computed(() => page.props.auth?.user)

const usuariosList = ref([...props.usuarios])
const busqueda = ref('')
const filtroRol = ref('todos')
const filtroEstado = ref('todos')
const cargando = ref(false)
const guardandoId = ref(null)

const mensajeExito = ref(null)
const mensajeError = ref(null)

// Estado del Modal
const mostrarModal = ref(false)
const esModoEdicion = ref(false)
const usuarioEditando = ref(null)
const guardandoModal = ref(false)
const modalError = ref(null)
const mostrarPassword = ref(false)

const form = reactive({
  username: '',
  role_id: null,
  ruta: '',
  password: '',
  is_active: true,
})

// Conteo por Roles para KPIs
const conteoRoles = computed(() => {
  let supervisores = 0
  let vendedores = 0
  let superadmins = 0
  let inactivos = 0

  usuariosList.value.forEach(u => {
    if (!u.is_active) inactivos++
    const rol = (u.role || '').toLowerCase()
    if (rol === 'supervisor') supervisores++
    else if (rol === 'vendedor') vendedores++
    else if (rol === 'superadministrador') superadmins++
  })

  return { supervisores, vendedores, superadmins, inactivos }
})

// Lista Filtrada
const usuariosFiltrados = computed(() => {
  const q = busqueda.value.toLowerCase().trim()

  return usuariosList.value.filter(u => {
    // Filtro texto
    const coincideTexto = !q
      || u.username.toLowerCase().includes(q)
      || (u.ruta && u.ruta.toLowerCase().includes(q))
      || (u.role && u.role.toLowerCase().includes(q))

    if (!coincideTexto) return false

    // Filtro rol
    if (filtroRol.value !== 'todos') {
      if (u.role_id !== Number(filtroRol.value)) return false
    }

    // Filtro estado
    if (filtroEstado.value === 'activos' && !u.is_active) return false
    if (filtroEstado.value === 'inactivos' && u.is_active) return false

    return true
  })
})

function puedeTenerPermisos(roleName) {
  const r = (roleName || '').toLowerCase()
  return ['supervisor', 'administrador', 'admin'].includes(r)
}

function obtenerAvatarClase(roleName) {
  const r = (roleName || '').toLowerCase()
  if (r === 'superadministrador') return 'bg-purple-100 text-purple-700'
  if (r === 'supervisor') return 'bg-blue-100 text-blue-700'
  if (r === 'vendedor') return 'bg-emerald-100 text-emerald-700'
  return 'bg-neutral-100 text-neutral-700'
}

function obtenerBadgeRol(roleName) {
  const r = (roleName || '').toLowerCase()
  if (r === 'superadministrador') return 'bg-purple-50 text-purple-700 border-purple-200'
  if (r === 'supervisor') return 'bg-blue-50 text-blue-700 border-blue-200'
  if (r === 'vendedor') return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  return 'bg-neutral-50 text-neutral-700 border-neutral-200'
}

function formatearFecha(dateStr) {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
  } catch {
    return dateStr
  }
}

function abrirModalCrear() {
  esModoEdicion.value = false
  usuarioEditando.value = null
  modalError.value = null
  mostrarPassword.value = false

  form.username = ''
  form.role_id = props.roles.length > 0 ? props.roles[0].id : null
  form.ruta = ''
  form.password = ''
  form.is_active = true

  mostrarModal.value = true
}

function abrirModalEditar(u) {
  esModoEdicion.value = true
  usuarioEditando.value = u
  modalError.value = null
  mostrarPassword.value = false

  form.username = u.username
  form.role_id = u.role_id
  form.ruta = u.ruta || ''
  form.password = ''
  form.is_active = !!u.is_active

  mostrarModal.value = true
}

function cerrarModal() {
  mostrarModal.value = false
  usuarioEditando.value = null
  modalError.value = null
}

function generarPasswordAleatorio() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$'
  let pass = ''
  for (let i = 0; i < 10; i++) {
    pass += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  form.password = pass
  mostrarPassword.value = true
}

async function guardarUsuario() {
  guardandoModal.value = true
  modalError.value = null
  mensajeExito.value = null
  mensajeError.value = null

  try {
    if (esModoEdicion.value) {
      const url = route('supervisor.admin.usuarios.update', { userId: usuarioEditando.value.id })
      const payload = {
        username: form.username,
        role_id: form.role_id,
        ruta: form.ruta,
        is_active: form.is_active,
      }
      if (form.password && form.password.trim() !== '') {
        payload.password = form.password
      }

      const { data } = await axios.put(url, payload)

      // Actualizar en lista local
      const idx = usuariosList.value.findIndex(u => u.id === usuarioEditando.value.id)
      if (idx !== -1) {
        usuariosList.value[idx] = data.user
      }

      mensajeExito.value = data.message || 'Usuario actualizado correctamente.'
      cerrarModal()
    } else {
      const url = route('supervisor.admin.usuarios.store')
      const { data } = await axios.post(url, {
        username: form.username,
        role_id: form.role_id,
        ruta: form.ruta,
        password: form.password,
        is_active: form.is_active,
      })

      // Agregar a lista local al inicio
      usuariosList.value.unshift(data.user)

      mensajeExito.value = data.message || 'Usuario creado correctamente.'
      cerrarModal()
    }
  } catch (err) {
    modalError.value = err.response?.data?.message || err.message || 'Error al procesar la solicitud.'
  } finally {
    guardandoModal.value = false
  }
}

async function alternarEstado(u) {
  if (u.id === usuarioActual.value?.id) return
  guardandoId.value = u.id
  mensajeExito.value = null
  mensajeError.value = null

  try {
    const url = route('supervisor.admin.usuarios.toggle-status', { userId: u.id })
    const { data } = await axios.patch(url)

    u.is_active = data.is_active
    mensajeExito.value = data.message || `Estado de ${u.username} actualizado.`
  } catch (err) {
    mensajeError.value = err.response?.data?.message || err.message || 'Error al cambiar estado del usuario.'
  } finally {
    guardandoId.value = null
  }
}

function recargar() {
  cargando.value = true
  router.reload({
    only: ['usuarios', 'roles'],
    onFinish: () => { cargando.value = false }
  })
}
</script>

