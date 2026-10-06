<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
      >
        <!-- Backdrop Glass -->
        <div
          class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
          @click="closeOnBackdrop && $emit('close')"
        ></div>

        <!-- Modal Container -->
        <div class="flex min-h-full items-center justify-center p-3 sm:p-4 text-center">
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-2"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-2"
          >
            <div
              v-if="show"
              :class="[
                'w-full bg-white text-left align-middle shadow-xl rounded-2xl border border-slate-200/90 overflow-hidden transform transition-all',
                maxWidthClasses[maxWidth] || maxWidthClasses.md
              ]"
            >
              <!-- Modal Header -->
              <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center space-x-2">
                  <span v-if="icon" class="text-slate-500">
                    <slot name="icon" />
                  </span>
                  <div>
                    <h3 class="text-sm font-semibold text-slate-900 tracking-tight">
                      {{ title }}
                    </h3>
                    <p v-if="subtitle" class="text-[11px] text-slate-500 mt-0.5">
                      {{ subtitle }}
                    </p>
                  </div>
                </div>

                <button
                  type="button"
                  @click="$emit('close')"
                  class="p-1 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors"
                  aria-label="Cerrar modal"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- Modal Body -->
              <div class="p-4 sm:p-5 max-h-[calc(85vh-8rem)] overflow-y-auto">
                <slot />
              </div>

              <!-- Modal Footer -->
              <div
                v-if="$slots.footer"
                class="px-4 py-3 sm:px-5 border-t border-slate-100 bg-slate-50/60 flex items-center justify-end space-x-2"
              >
                <slot name="footer" />
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: null,
  },
  icon: {
    type: Boolean,
    default: false,
  },
  maxWidth: {
    type: String,
    default: 'md', // sm, md, lg, xl, 2xl, full
  },
  closeOnBackdrop: {
    type: Boolean,
    default: true,
  }
});

const emit = defineEmits(['close']);

const maxWidthClasses = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
  '4xl': 'max-w-4xl',
  '6xl': 'max-w-6xl',
  full: 'max-w-[95vw]',
};

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && props.show) {
    emit('close');
  }
};

watch(() => props.show, (newVal) => {
  if (newVal) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
  document.body.style.overflow = '';
});
</script>

