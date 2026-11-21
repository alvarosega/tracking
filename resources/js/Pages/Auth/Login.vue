<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-[#07080a] text-slate-100 px-4 font-sans selection:bg-white/20 selection:text-white antialiased">
    <div class="w-full max-w-[280px]">
      
      <!-- Minimal Mark -->
      <div class="flex justify-center mb-7">
        <div class="w-10 h-10 rounded-2xl bg-white/[0.04] border border-white/[0.08] flex items-center justify-center text-white/70 shadow-2xl backdrop-blur-md">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
          </svg>
        </div>
      </div>

      <!-- Formulario Ultra Minimalista -->
      <form @submit.prevent="submit" class="space-y-2.5">
        
        <!-- Usuario -->
        <div class="relative">
          <input
            v-model="form.username"
            type="text"
            required
            autocomplete="username"
            autofocus
            aria-label="Usuario"
            placeholder="usuario"
            :disabled="form.processing"
            class="w-full h-10 px-3.5 bg-white/[0.03] hover:bg-white/[0.05] focus:bg-white/[0.07] border border-white/[0.08] focus:border-white/30 rounded-xl text-xs text-white placeholder:text-white/25 transition-all outline-none"
          />
        </div>

        <!-- Contraseña -->
        <div class="relative">
          <input
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            required
            autocomplete="current-password"
            aria-label="Contraseña"
            placeholder="••••••••"
            :disabled="form.processing"
            class="w-full h-10 pl-3.5 pr-9 bg-white/[0.03] hover:bg-white/[0.05] focus:bg-white/[0.07] border border-white/[0.08] focus:border-white/30 rounded-xl text-xs text-white placeholder:text-white/25 transition-all outline-none"
          />
          <button
            type="button"
            @click="showPassword = !showPassword"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-white/25 hover:text-white/60 transition-colors p-1"
            tabindex="-1"
            aria-label="Mostrar contraseña"
          >
            <svg v-if="!showPassword" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
          </button>
        </div>

        <!-- Errores discretos -->
        <div v-if="form.errors.username || form.errors.password" class="text-rose-400 text-[11px] text-center px-1 font-mono pt-0.5">
          {{ form.errors.username || form.errors.password }}
        </div>

        <!-- Botón de Envío -->
        <button
          type="submit"
          :disabled="form.processing"
          aria-label="Entrar"
          class="w-full h-10 mt-1 bg-white hover:bg-neutral-200 active:scale-[0.98] text-neutral-950 font-medium rounded-xl transition-all flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer shadow-lg shadow-white/5"
        >
          <svg
            v-if="form.processing"
            class="animate-spin h-4 w-4 text-neutral-950"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg
            v-else
            class="w-4 h-4 text-neutral-950"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
          </svg>
        </button>
      </form>

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