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
    <section ref="root" class="rounded-lg border border-outline-variant bg-surface-container-lowest" :class="[padded && 'p-6']">
        <header v-if="$slots.header" class="mb-6 flex items-center justify-between gap-4" :class="[accentBar && 'accent-bar pl-4']">
            <slot name="header" />
        </header>
        <slot />
    </section>
</template>
