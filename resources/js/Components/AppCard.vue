<script setup>
import { ref } from 'vue';

const props = defineProps({
    padded: { type: Boolean, default: true },
    bordered: { type: Boolean, default: false },
    accentBar: { type: Boolean, default: false },
});

const root = ref(null);
defineExpose({ root });
</script>

<template>
    <section ref="root" class="card-shadow rounded-2xl border border-white/60 bg-surface-container-lowest dark:border-white/5" :class="[padded && 'p-6', bordered && 'border-outline-variant dark:border-outline-variant']">
        <header v-if="$slots.header" class="mb-6 flex items-center justify-between gap-4" :class="[accentBar && 'accent-bar pl-4']">
            <slot name="header" />
        </header>
        <slot />
    </section>
</template>
