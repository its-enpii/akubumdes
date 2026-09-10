<script setup>
defineProps({
    tone: {
        type: String,
        default: 'neutral',
        validator: (value) => [
            // Solid tones (default)
            'neutral', 'success', 'warning', 'error', 'primary',
            // Soft tones — lighter background, stronger text. Useful when paired
            // next to other UI elements (rows, cells, banners).
            'success-soft', 'warning-soft', 'error-soft', 'info-soft', 'primary-soft',
        ].includes(value),
    },
});

const tones = {
    // Solid (high-emphasis)
    neutral: 'bg-surface-container-low text-on-surface-variant ring-1 ring-outline-variant/60',
    success: 'bg-secondary text-on-secondary',
    warning: 'bg-tertiary text-on-tertiary',
    error: 'bg-error-container text-on-error-container',
    primary: 'bg-primary-fixed text-primary',
    // Soft (medium-emphasis — tinted bg, strong fg)
    'success-soft': 'bg-secondary-container text-[#046137]',
    'warning-soft': 'bg-tertiary-fixed text-[#5c3100]',
    'error-soft': 'bg-error-container/40 text-error',
    'info-soft': 'bg-primary-container/40 text-primary',
    'primary-soft': 'bg-primary-fixed text-primary',
};
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide" :class="tones[tone] || tones.neutral"><slot /></span>
</template>
