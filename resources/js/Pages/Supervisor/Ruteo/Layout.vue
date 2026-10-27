<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-900">
      <!-- Pestañas Unificadas (Auditoría, Saneamiento, Ruteo) -->
      <div class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 py-2 shrink-0 relative z-30">
        <AuditoriaSaneamientoSubTabs :active-tab="currentActiveTab" :compact="true" />
      </div>

      <div class="flex-1 flex flex-col min-h-0 overflow-hidden relative">
        <slot />
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import AuditoriaSaneamientoSubTabs from '@/Components/AuditoriaSaneamientoSubTabs.vue';

const props = defineProps({
  activeTab: {
    type: String,
    default: null,
  },
});

const page = usePage();

const currentActiveTab = computed(() => {
  if (props.activeTab) return props.activeTab;
  if (page.component === 'Supervisor/Ruteo/Cercanos') return 'cercanos';
  return 'visor';
});
</script>
