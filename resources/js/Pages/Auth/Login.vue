<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-900 px-4">
    <div class="max-w-md w-full bg-slate-800 rounded-xl shadow-xl p-8 border border-slate-700">
      <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-white tracking-tight">Supervisión de Campo</h1>
        <p class="text-slate-400 text-sm mt-1">Panel de control y telemetría de ventas</p>
      </div>

      <form @submit.prevent="submit" class="space-y-5">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
            Usuario
          </label>
          <input
            v-model="form.username"
            type="text"
            required
            autocomplete="username"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
            placeholder="supervisor1"
          />
          <p v-if="form.errors.username" class="text-rose-400 text-xs mt-1.5 font-medium">
            {{ form.errors.username }}
          </p>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
            Contraseña
          </label>
          <input
            v-model="form.password"
            type="password"
            required
            autocomplete="current-password"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
            placeholder="••••••••"
          />
          <p v-if="form.errors.password" class="text-rose-400 text-xs mt-1.5 font-medium">
            {{ form.errors.password }}
          </p>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 disabled:bg-blue-800 disabled:cursor-not-allowed text-white font-medium rounded-lg shadow-md transition duration-150"
        >
          <span v-if="form.processing">Autenticando...</span>
          <span v-else>Iniciar Sesión</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

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