<script setup>

import { Head } from '@inertiajs/vue3';
import AppBadge from '../../Components/AppBadge.vue';
import AppCard from '../../Components/AppCard.vue';
import AppEmptyState from '../../Components/AppEmptyState.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    officers: { type: Array, default: () => [] },
    activeGroups: { type: Array, default: () => [] },
});

const positionLabels = { chair: 'Ketua', secretary: 'Sekretaris', treasurer: 'Bendahara' };

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Portal Saya" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex items-start gap-4">
                <AppIcon name="account_circle" tone="primary" :container-size="12" container-shape="pill" />
                <div>
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">Portal Saya</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">Ringkasan data keanggotaan Anda.</p>
                </div>
            </header>

            <AppCard>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div><p class="text-xs font-bold uppercase text-on-surface-variant">Nama</p><p class="mt-1 font-semibold text-primary">{{ profile.name || '—' }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-on-surface-variant">Nomor Anggota</p><p class="mt-1 font-semibold">{{ profile.member_number || '—' }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-on-surface-variant">Status</p><AppBadge class="mt-2" :tone="profile.status === 'active' ? 'success' : 'neutral'">{{ profile.status === 'active' ? 'Aktif' : (profile.status || '—') }}</AppBadge></div>
                    <div><p class="text-xs font-bold uppercase text-on-surface-variant">Bergabung</p><p class="mt-1 font-semibold">{{ formatDate(profile.registered_at) }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-on-surface-variant">Unit Organisasi</p><p class="mt-1 font-semibold">{{ profile.organization_unit || '—' }}</p></div>
                </div>
            </AppCard>

            <AppCard>
                <template #header><h2 class="font-bold text-primary">Riwayat Pengurus</h2></template>
                <ul v-if="officers.length" class="space-y-3">
                    <li v-for="officer in officers" :key="`${officer.group_row_id}-${officer.position}-${officer.started_at}`" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-container-low p-4">
                        <div><p class="font-semibold">{{ officer.group_name || '—' }}</p><p class="text-xs text-on-surface-variant">{{ positionLabels[officer.position] || officer.position }} · {{ formatDate(officer.started_at) }} s.d. {{ formatDate(officer.ended_at) }}</p></div>
                        <AppBadge :tone="officer.ended_at ? 'neutral' : 'success'">{{ officer.ended_at ? 'Selesai' : 'Aktif' }}</AppBadge>
                    </li>
                </ul>
                <AppEmptyState v-else icon="groups" title="Belum ada riwayat pengurus" description="Riwayat kepengurusan kelompok akan tampil di sini." />
            </AppCard>

            <AppCard>
                <template #header><h2 class="font-bold text-primary">Anggota Kelompok Yang Dipimpin</h2></template>
                <div v-if="activeGroups.length" class="space-y-5">
                    <section v-for="group in activeGroups" :key="group.group_name">
                        <h3 class="text-sm font-bold uppercase text-on-surface-variant">{{ group.group_name }}</h3>
                        <ul class="mt-3 space-y-2">
                            <li v-for="member in group.members" :key="`${group.group_name}-${member.member_number}`" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-container-low p-4">
                                <div><p class="font-semibold">{{ member.name || '—' }}</p><p class="text-xs text-on-surface-variant">{{ member.member_number || '—' }}</p></div>
                                <AppBadge :tone="member.status === 'active' ? 'success' : 'neutral'">{{ member.status === 'active' ? 'Aktif' : (member.status || '—') }}</AppBadge>
                            </li>
                        </ul>
                        <AppEmptyState v-if="!group.members.length" icon="group" title="Belum ada anggota lain" description="Kelompok aktif ini belum memiliki anggota lain." />
                    </section>
                </div>
                <AppEmptyState v-else icon="groups" title="Tidak ada kepengurusan aktif" description="Daftar anggota kelompok hanya tampil saat Anda pengurus aktif." />
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
