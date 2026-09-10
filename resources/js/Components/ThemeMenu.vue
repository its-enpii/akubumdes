<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import { useTheme } from '../composables/useTheme';

const model = defineModel({ type: Boolean, default: false });
const { theme, themes, setTheme } = useTheme();
const panel = ref(null);

function choose(id) {
    setTheme(id);
    setTimeout(() => {
        model.value = false;
    }, 120);
}

function onDocMouseDown(e) {
    if (!model.value) return;
    if (panel.value?.contains(e.target)) return;
    if (e.target.closest('[data-theme-trigger]')) return;
    model.value = false;
}

function onEsc(e) {
    if (e.key === 'Escape' && model.value) {
        model.value = false;
    }
}

watch(model, (open) => {
    if (open) {
        document.addEventListener('mousedown', onDocMouseDown);
        document.addEventListener('keydown', onEsc);
    } else {
        document.removeEventListener('mousedown', onDocMouseDown);
        document.removeEventListener('keydown', onEsc);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocMouseDown);
    document.removeEventListener('keydown', onEsc);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="theme-menu">
            <div
                v-if="model"
                ref="panel"
                role="menu"
                aria-label="Pilih tema tampilan"
                class="fixed right-4 top-[4.5rem] z-50 w-64 origin-top-right overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest shadow-xl"
            >
                <p class="border-b border-outline-variant/60 bg-surface-container-low/40 px-4 py-2.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-on-surface-variant">
                    Tema tampilan
                </p>
                <div class="p-1">
                    <button
                        v-for="t in themes"
                        :key="t.id"
                        type="button"
                        role="menuitemradio"
                        :aria-checked="theme === t.id"
                        :aria-label="`Pilih tema ${t.label}`"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left transition-colors duration-200 hover:bg-surface-container-low"
                        :class="theme === t.id && 'bg-surface-container-low font-semibold'"
                        @click="choose(t.id)"
                    >
                        <span class="theme-swatch shrink-0" :data-for="t.id" aria-hidden="true"><i /><i /><i /></span>
                        <span class="min-w-0 flex-1 truncate text-sm text-on-surface">{{ t.label }}</span>
                        <AppIcon v-if="theme === t.id" name="check_circle" filled class="text-secondary transition-transform duration-200 scale-105" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.theme-menu-enter-active,
.theme-menu-leave-active {
    transition: opacity 150ms ease, transform 150ms cubic-bezier(0.16, 1, 0.3, 1);
}
.theme-menu-enter-from,
.theme-menu-leave-to {
    opacity: 0;
    transform: translateY(-0.35rem) scale(0.95);
}
</style>
