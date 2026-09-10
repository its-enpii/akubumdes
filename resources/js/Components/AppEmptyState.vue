<script setup>
defineProps({
    icon: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="grid min-h-48 place-items-center rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/60 p-8 text-center text-on-surface-variant">
        <div>
            <svg class="mx-auto h-20 w-32 text-primary" viewBox="0 0 128 80" fill="none" aria-hidden="true">
                <rect x="24" y="20" width="80" height="52" rx="10" stroke="currentColor" stroke-width="2.5" class="text-outline-variant" />
                <path d="M44 52h40M52 42h24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="text-secondary" />
                <circle cx="52" cy="31" r="5" stroke="currentColor" stroke-width="2.5" class="text-primary" />
                <path d="M92 26h16M96 18h20M80 8h24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="text-outline-variant" />
            </svg>
            <p class="mt-4 text-lg font-bold text-primary">{{ title }}</p>
            <p v-if="description" class="mx-auto mt-1 max-w-sm text-sm">{{ description }}</p>
            <div v-if="$slots.default" class="mt-4"><slot /></div>
        </div>
    </div>
</template>
