<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import AppIconButton from '../Components/AppIconButton.vue';
import AppConfirmDialog from '../Components/AppConfirmDialog.vue';
import AppToast from '../Components/AppToast.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const appName = computed(() => page.props.appName || 'akubumdes');
const currentPath = computed(() => page.url.split('?')[0]);
const mobileMenuOpen = ref(false);
const logoutForm = useForm({});
const avatarError = ref(false);

const navigation = [
    { label: 'Dashboard', icon: 'dashboard', href: '/admin', exact: true },
    { label: 'Tenant', icon: 'domain', href: '/admin/tenants' },
    { label: 'Plan', icon: 'workspace_premium', href: '/admin/plans' },
    { label: 'Invoice', icon: 'receipt_long', href: '/admin/invoices' },
    { label: 'Pendapatan', icon: 'monitoring', href: '/admin/revenue' },
    { label: 'Log Audit', icon: 'history', href: '/admin/audit-logs' },
    { label: 'Pengguna Platform', icon: 'group', href: '/admin/users' },
    { label: 'Shard & Cutover', icon: 'storage', href: '/admin/shards' },
    { label: 'WhatsApp', icon: 'chat', href: '/admin/whatsapp' },
    { label: 'Platform Settings', icon: 'tune', href: '/admin/settings' },
    { label: 'Payment Gateway', icon: 'payments', href: '/admin/payment-gateways' },
    { label: 'AI Assistant', icon: 'smart_toy', href: '/admin/ai-assistant' },
    { label: 'Migrasi Data', icon: 'transform', href: '/admin/migration' },
];

function isActive(item) {
    if (!item.href) return false;
    return item.exact ? currentPath.value === item.href : currentPath.value.startsWith(item.href);
}

function logout() {
    logoutForm.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-surface">
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <button
                v-if="mobileMenuOpen"
                type="button"
                class="fixed inset-0 z-40 bg-primary-deep/40 backdrop-blur-xs lg:hidden"
                aria-label="Tutup navigasi"
                @click="mobileMenuOpen = false"
            />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-outline-variant bg-surface-container-lowest py-6 transition-transform duration-300 ease-in-out lg:translate-x-0"
            :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="mb-8 flex items-center gap-3 px-6">
                <div class="grid size-10 shrink-0 place-items-center rounded-lg border border-outline-variant bg-surface-container-lowest"><AppIcon name="eco" class="text-2xl text-secondary" /></div>
                <div class="min-w-0">
                    <p class="font-display text-lg font-bold leading-none tracking-[-0.02em] text-primary">akubumdes</p>
                    <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-on-surface-variant">{{ appName }} · Platform Admin</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3">
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    class="relative flex items-center gap-3 rounded-lg px-4 py-2.5 transition-colors duration-200"
                    :class="isActive(item) ? 'bg-surface-container-low font-semibold text-primary' : 'text-on-surface-variant hover:bg-surface-container-low/70 hover:text-on-surface'"
                    @click="mobileMenuOpen = false"
                >
                    <span v-if="isActive(item)" class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-full bg-secondary" aria-hidden="true" />
                    <AppIcon :name="item.icon" :filled="isActive(item)" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="mt-4 border-t border-outline-variant px-4 pt-4">
                <div class="flex items-center gap-3 rounded-lg border border-outline-variant bg-surface-container-lowest p-3">
                    <div class="relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-full border border-outline-variant bg-primary-fixed text-sm font-bold text-primary"><img v-if="user?.photo_url && !avatarError" :src="user.photo_url" :alt="user?.name || 'Admin'" class="size-full object-cover" @error="avatarError = true" /><span v-else>{{ user?.name?.charAt(0).toUpperCase() || 'A' }}</span></div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-on-surface">{{ user?.name || 'Admin' }}</p>
                        <p class="truncate text-xs text-on-surface-variant">Superadmin</p>
                    </div>
                    <AppIconButton name="logout" tone="neutral" size="sm" rounded="lg" aria-label="Keluar" class="text-on-surface-variant hover:text-primary" @click="logout" />
                </div>
            </div>
        </aside>

        <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-outline-variant bg-surface/85 px-4 backdrop-blur-md lg:ml-64 lg:px-6">
            <div class="flex items-center gap-3">
                <AppIconButton name="menu" tone="primary" size="sm" rounded="lg" aria-label="Buka navigasi" class="lg:hidden" @click="mobileMenuOpen = true" />
                <p class="accent-bar pl-4 text-sm font-semibold text-on-surface">Panel Admin Platform</p>
            </div>
            <div class="flex items-center gap-1">
                <Link
                    href="/changelog"
                    class="grid size-10 shrink-0 place-items-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                    :class="currentPath === '/changelog' && 'bg-surface-container text-primary'"
                    title="Catatan Rilis & Changelog"
                    aria-label="Catatan Rilis & Changelog"
                >
                    <AppIcon name="history_edu" class="text-2xl leading-none" />
                </Link>
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:ml-64 lg:p-8">
            <Transition name="page" mode="out-in" appear>
                <div :key="currentPath" class="min-w-0 flex-1">
                    <slot />
                </div>
            </Transition>
        </main>

        <AppConfirmDialog />
        <AppToast />
    </div>
</template>
