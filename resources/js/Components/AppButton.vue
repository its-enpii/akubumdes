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
    primary: 'bg-primary text-on-primary hover:bg-primary-container disabled:bg-primary/60',
    success: 'bg-secondary text-on-secondary hover:bg-secondary/90 disabled:bg-secondary/60',
    secondary: 'border border-primary bg-transparent text-primary hover:bg-primary/10 disabled:border-primary/40',
    outline: 'border border-primary bg-transparent text-primary hover:bg-primary/10 disabled:border-primary/40',
    ghost: 'bg-transparent text-primary hover:bg-primary/10',
    danger: 'bg-error text-on-error hover:bg-error/90 disabled:bg-error/60',
    tertiary: 'bg-tertiary text-on-tertiary hover:bg-tertiary/90 disabled:bg-tertiary/60',
};

const sizes = {
    compact: 'h-9 px-3 text-sm',
    default: 'h-10 px-5',
    large: 'h-12 px-6 text-base',
};
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center gap-2 rounded-lg font-display font-semibold tracking-[-0.01em] transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-60"
        :class="[variants[variant] || variants.primary, iconOnly ? 'aspect-square p-0' : sizes[size] || sizes.default]"
        :aria-busy="loading"
        :aria-label="ariaLabel || undefined"
        v-bind="$attrs"
    >
        <span v-if="loading" class="size-4 animate-spin rounded-full border-2 border-current/30 border-t-current" aria-hidden="true" />
        <AppIcon v-else-if="icon" :name="icon" class="text-xl" />
        <slot />
    </button>
</template>
