<template>
  <div class="min-h-screen relative flex items-center justify-center bg-[#0B0F19] p-4 sm:p-6 overflow-hidden selection:bg-sky-500 selection:text-white font-sans">
    <!-- Fondo Futurista con Grid y Luces de Ambiente -->
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b15_1px,transparent_1px),linear-gradient(to_bottom,#1e293b15_1px,transparent_1px)] bg-[size:32px_32px]"></div>
    
    <!-- Resplandor ambiental de fondo -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-sky-500/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Contenedor Principal del Login -->
    <div class="relative w-full max-w-md z-10">
      
      <!-- Insignia Superior de Estado del Sistema -->
      <div class="flex items-center justify-center mb-6">
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-slate-900/80 border border-slate-700/60 backdrop-blur-md shadow-xs text-[11px] font-mono">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span class="text-slate-300 font-medium tracking-wide">CENTRO DE SUPERVISIÓN</span>
          <span class="text-slate-500">|</span>
          <span class="text-sky-400 font-semibold">EL ALTO</span>
        </div>
      </div>

      <!-- Tarjeta Glassmorphism Futurista -->
      <div class="bg-slate-900/80 backdrop-blur-xl rounded-2xl border border-slate-800/80 shadow-2xl p-6 sm:p-8 relative overflow-hidden">
        
        <!-- Borde superior con línea de luz sutil -->
        <div class="absolute top-0 left-0 right-0 h-[1.5px] bg-gradient-to-r from-transparent via-sky-500 to-transparent opacity-70"></div>

        <!-- Encabezado de la Tarjeta -->
        <div class="text-center mb-7">
          <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-500 to-slate-900 border border-sky-400/30 text-white flex items-center justify-center mx-auto mb-3.5 shadow-lg shadow-sky-500/20">
            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
          </div>
          <h1 class="text-xl font-bold text-white tracking-tight">Acceso a Supervisión</h1>
          <p class="text-slate-400 text-xs mt-1 font-normal">Panel de telemetría, ruteo y control de preventas</p>
        </div>

        <!-- Formulario -->
        <form @submit.prevent="submit" class="space-y-4">
          <!-- Campo Usuario -->
          <div class="space-y-1.5">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-300 font-mono">
              Usuario Operador
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
              </div>
              <input
                v-model="form.username"
                type="text"
                required
                autocomplete="username"
                class="w-full pl-9 pr-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-white text-xs placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-mono"
                placeholder="supervisor"
                :disabled="form.processing"
              />
            </div>
            <p v-if="form.errors.username" class="text-rose-400 text-xs mt-1 font-medium flex items-center gap-1">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
              </svg>
              {{ form.errors.username }}
            </p>
          </div>

          <!-- Campo Contraseña -->
          <div class="space-y-1.5">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-300 font-mono">
              Clave de Acceso
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
              </div>
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                class="w-full pl-9 pr-10 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-white text-xs placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-mono"
                placeholder="••••••••"
                :disabled="form.processing"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300 transition-colors cursor-pointer"
                aria-label="Mostrar/Ocultar contraseña"
              >
                <svg v-if="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
              </button>
            </div>
            <p v-if="form.errors.password" class="text-rose-400 text-xs mt-1 font-medium flex items-center gap-1">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
              </svg>
              {{ form.errors.password }}
            </p>
          </div>

          <!-- Botón de Entrada -->
          <div class="pt-2">
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full py-2.5 px-4 bg-gradient-to-r from-sky-600 to-sky-500 hover:from-sky-500 hover:to-sky-400 text-white font-semibold text-xs rounded-xl shadow-lg shadow-sky-600/25 border border-sky-400/30 transition-all duration-150 flex items-center justify-center space-x-2 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed active:scale-[0.98]"
            >
              <svg
                v-if="form.processing"
                class="animate-spin h-3.5 w-3.5 text-white"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ form.processing ? 'Autenticando...' : 'Iniciar Sesión Segura' }}</span>
              <svg v-if="!form.processing" class="w-3.5 h-3.5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
              </svg>
            </button>
          </div>
        </form>

        <!-- Pie de Seguridad Informativo -->
        <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between text-[10px] text-slate-500 font-mono">
          <div class="flex items-center space-x-1.5">
            <svg class="w-3 h-3 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            </svg>
            <span>Sesión Encriptada TLS</span>
          </div>
          <span>GPS Alva v2.0</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const showPassword = ref(false);

const form = useForm({
  username: '',
  password: '',
});

const submit = () => {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
};
</script>