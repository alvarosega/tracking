<template>
  <AuditoriaSaneamientoSubTabs :active-tab="activeTab" />
</template>

<script setup>
import AuditoriaSaneamientoSubTabs from '@/Components/AuditoriaSaneamientoSubTabs.vue';

defineProps({
  activeTab: {
    type: String,
    required: true,
  },
});
</script>
