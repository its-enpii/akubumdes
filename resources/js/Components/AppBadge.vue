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
    neutral: 'bg-surface-container-low text-on-surface-variant ring-1 ring-outline-variant',
    success: 'bg-secondary text-on-secondary',
    warning: 'bg-tertiary text-on-tertiary',
    error: 'bg-error text-on-error',
    primary: 'bg-primary text-on-primary',
    // Soft (medium-emphasis — tinted bg, strong fg)
    'success-soft': 'bg-secondary-container text-[#065f46]',
    'warning-soft': 'bg-tertiary-container text-[#92400e]',
    'error-soft': 'bg-error-container text-[#991b1b]',
    'info-soft': 'bg-primary-fixed text-primary',
    'primary-soft': 'bg-primary-fixed text-primary',
};
</script>

<template>
    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.06em]" :class="tones[tone] || tones.neutral"><slot /></span>
</template>
