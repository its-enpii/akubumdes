<script setup>
defineOptions({ inheritAttrs: false });

import AppIcon from './AppIcon.vue';

defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'default' },
    icon: { type: String, default: null },
    iconOnly: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
    ariaLabel: { type: String, default: null },
});

const variants = {
    primary: 'bg-primary text-on-primary shadow-[0_8px_20px_-10px_rgb(6_45_77/70%)] hover:bg-primary-container disabled:bg-primary/60',
    success: 'bg-secondary text-on-secondary shadow-[0_8px_20px_-10px_rgb(4_120_63/70%)] hover:bg-secondary/90 disabled:bg-secondary/60',
    secondary: 'border border-primary/15 bg-surface-container-lowest text-primary shadow-[0_2px_8px_-4px_var(--color-shadow)] hover:border-secondary/45 hover:text-secondary',
    outline: 'border border-primary/70 bg-transparent text-primary hover:bg-primary/10 disabled:border-primary/40',
    ghost: 'bg-transparent text-primary hover:bg-primary/8',
    danger: 'bg-error text-on-error hover:bg-error/90 disabled:bg-error/60',
    tertiary: 'bg-tertiary text-on-tertiary hover:brightness-110 disabled:bg-tertiary/60',
};

const sizes = {
    compact: 'min-h-10 px-3 text-sm',
    default: 'min-h-12 px-5',
    large: 'min-h-14 px-6 text-lg',
};
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center gap-2 rounded-xl font-bold tracking-[-0.01em] transition-all duration-150 hover:-translate-y-px active:translate-y-0 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-secondary focus-visible:ring-offset-2 focus-visible:ring-offset-surface-container-lowest disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none disabled:hover:translate-y-0 disabled:active:scale-100"
        :class="[variants[variant] || variants.primary, iconOnly ? 'aspect-square p-0' : sizes[size] || sizes.default]"
        :aria-busy="loading"
        :aria-label="ariaLabel || undefined"
        v-bind="$attrs"
    >
        <span v-if="loading" class="size-5 animate-spin rounded-full border-2 border-current/30 border-t-current" aria-hidden="true" />
        <AppIcon v-else-if="icon" :name="icon" class="text-xl" />
        <slot />
    </button>
</template>
