<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import AppBadge from "../../../Components/AppBadge.vue";
import AppButton from "../../../Components/AppButton.vue";
import AppCard from "../../../Components/AppCard.vue";
import AppIcon from "../../../Components/AppIcon.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

const props = defineProps({
  roles: { type: Array, required: true },
});

function confirmDelete(role) {
  if (role.is_system || role.is_locked) return;
  if (
    confirm(`Apakah Anda yakin ingin menghapus role kustom "${role.name}"?`)
  ) {
    router.delete(`/access/roles/${role.row_id}`, { preserveScroll: true });
  }
}

const roleColumns = [
  { key: "name", label: "Nama Role" },
  { key: "code", label: "Kode" },
  { key: "type", label: "Tipe" },
  { key: "users", label: "Pengguna", align: "center" },
  { key: "permissions", label: "Hak Akses Aktif", align: "center" },
  { key: "actions", label: "Aksi", align: "right" },
];
</script>

<template>
  <Head title="Manajemen Role & Hak Akses" />
  <AuthenticatedLayout>
    <div class="mx-auto max-w-7xl space-y-6">
      <header
        class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary sm:text-3xl">
            Manajemen Role & Hak Akses
          </h1>
          <p class="mt-1 text-sm text-on-surface-variant">
            Atur paket peran (roles) dan perizinan modul (permissions) untuk
            staf unit tenant.
          </p>
        </div>
        <div class="flex items-center gap-3">
          <Link href="/access/users">
            <AppButton variant="secondary" icon="group"
              >Daftar Pengguna</AppButton
            >
          </Link>
          <Link href="/access/roles/create">
            <AppButton icon="add_moderator">Tambah Role Kustom</AppButton>
          </Link>
        </div>
      </header>

      <AppCard :padded="false">
        <div class="overflow-x-auto">
          <ReportTable :columns="roleColumns" :rows="roles" row-key="row_id">
            <template #cell-name="{ row }">
              <div class="flex items-center gap-2">
                <AppIcon
                  v-if="row.is_locked"
                  name="lock"
                  class="text-sm text-primary"
                />
                <span class="font-bold text-primary">{{ row.name }}</span>
              </div>
              <p
                v-if="row.description"
                class="mt-0.5 text-xs text-on-surface-variant"
              >
                {{ row.description }}
              </p>
            </template>
            <template #cell-code="{ row }">
              <code
                class="rounded bg-surface-container px-2 py-0.5 text-xs font-mono"
                >{{ row.code }}</code
              >
            </template>
            <template #cell-type="{ row }">
              <AppBadge v-if="row.is_locked" tone="primary"
                >Terkunci (Admin)</AppBadge
              >
              <AppBadge v-else-if="row.is_system" tone="neutral"
                >Bawaan Sistem</AppBadge
              >
              <AppBadge v-else tone="info">Kustom</AppBadge>
            </template>
            <template #cell-users="{ row }">
              <span
                class="inline-flex size-7 items-center justify-center rounded-full bg-surface-container text-xs font-semibold"
                >{{ row.user_count }}</span
              >
            </template>
            <template #cell-permissions="{ row }">
              <span
                v-if="row.is_locked"
                class="text-xs font-semibold text-primary"
                >Semua Akses (*)</span
              >
              <span v-else class="text-xs font-medium text-on-surface-variant"
                >{{ row.permissions_count }} izin</span
              >
            </template>
            <template #cell-actions="{ row }">
              <div class="flex items-center justify-end gap-1">
                <Link :href="`/access/roles/${row.row_id}/edit`"
                  ><AppButton
                    variant="ghost"
                    size="compact"
                    icon="edit"
                    tooltip="Lihat / Edit Hak Akses"
                /></Link>
                <AppButton
                  v-if="!row.is_system && !row.is_locked"
                  variant="ghost"
                  size="compact"
                  icon="delete"
                  tone="error"
                  tooltip="Hapus Role"
                  :disabled="row.user_count > 0"
                  @click="confirmDelete(row)"
                />
              </div>
            </template>
          </ReportTable>
        </div>
      </AppCard>
    </div>
  </AuthenticatedLayout>
</template>
