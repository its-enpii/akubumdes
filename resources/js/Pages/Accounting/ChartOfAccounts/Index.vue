<script setup>
import { Head, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import AppBadge from "../../../Components/AppBadge.vue";
import AppCard from "../../../Components/AppCard.vue";
import AppInput from "../../../Components/AppInput.vue";
import SmartSelect from "../../../Components/SmartSelect.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

const props = defineProps({
  rows: { type: Array, required: true },
  filters: { type: Object, required: true },
  type_options: { type: Array, required: true },
  counts: { type: Object, required: true },
});

const q = ref(props.filters.q || "");
const type = ref(props.filters.type || "all");
const status = ref(props.filters.status || "all");
const syncing = ref(false);

watch(
  () => props.filters,
  (f) => {
    syncing.value = true;
    q.value = f.q || "";
    type.value = f.type || "all";
    status.value = f.status || "all";
    queueMicrotask(() => {
      syncing.value = false;
    });
  },
  { deep: true },
);

const statusOptions = [
  { value: "all", label: "Semua status" },
  { value: "active", label: "Aktif" },
  { value: "inactive", label: "Nonaktif" },
];

const accountColumns = [
  { key: "code", label: "Kode" },
  { key: "name", label: "Nama" },
  { key: "type", label: "Jenis" },
  { key: "normal_balance", label: "Saldo normal" },
  { key: "postable", label: "Posting" },
  { key: "status", label: "Status" },
  { key: "created_at", label: "Tgl Ditambah" },
  { key: "deactivated_at", label: "Tgl Nonaktif" },
];

function apply() {
  if (syncing.value) return;
  router.get(
    "/accounting/chart-of-accounts",
    {
      q: q.value || undefined,
      type: type.value === "all" ? undefined : type.value,
      status: status.value === "all" ? undefined : status.value,
    },
    { preserveState: false, preserveScroll: true, replace: true },
  );
}

let searchTimer;
watch(q, () => {
  if (syncing.value) return;
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => apply(), 350);
});
watch([type, status], () => {
  if (syncing.value) return;
  apply();
});
</script>

<template>
  <Head title="Bagan Akun" />
  <AuthenticatedLayout>
    <div class="mx-auto max-w-7xl space-y-6">
      <header
        class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">Bagan Akun</h1>
          <p class="mt-1 text-on-surface-variant">
            Tampilan saja. Tambah/ubah/hapus akun hanya lewat persetujuan pusat.
          </p>
        </div>
        <div class="flex flex-wrap gap-3 text-sm">
          <div class="rounded-xl bg-surface-container-low px-3 py-2">
            <span class="text-on-surface-variant">Total</span>
            <span class="ml-2 font-bold text-primary">{{ counts.total }}</span>
          </div>
          <div class="rounded-xl bg-surface-container-low px-3 py-2">
            <span class="text-on-surface-variant">Aktif</span>
            <span class="ml-2 font-bold text-primary">{{ counts.active }}</span>
          </div>
          <div class="rounded-xl bg-surface-container-low px-3 py-2">
            <span class="text-on-surface-variant">Bisa di-post</span>
            <span class="ml-2 font-bold text-primary">{{
              counts.postable
            }}</span>
          </div>
        </div>
      </header>

      <AppCard>
        <div class="grid gap-3 sm:grid-cols-3">
          <AppInput
            v-model="q"
            label="Cari"
            placeholder="Kode atau nama akun"
          />
          <SmartSelect v-model="type" label="Jenis" :options="type_options" />
          <SmartSelect
            v-model="status"
            label="Status"
            :options="statusOptions"
          />
        </div>
      </AppCard>

      <AppCard :padded="false">
        <div class="overflow-x-auto">
          <ReportTable
            :columns="accountColumns"
            :rows="rows"
            row-key="row_id"
            sticky-header
            empty-title="Tidak ada akun yang cocok."
          >
            <template #cell-code="{ row }">
              <span
                class="whitespace-nowrap font-mono text-xs font-semibold text-primary"
                >{{ row.code }}</span
              >
            </template>
            <template #cell-name="{ row }">
              <span
                :style="{
                  paddingLeft: `${12 + Math.max(0, row.level - 1) * 16}px`,
                }"
                :class="
                  row.is_postable
                    ? 'font-medium'
                    : 'font-semibold text-on-surface'
                "
                >{{ row.name }}</span
              >
            </template>
            <template #cell-type="{ row }"
              ><span class="whitespace-nowrap text-on-surface-variant">{{
                row.type_label
              }}</span></template
            >
            <template #cell-normal_balance="{ row }"
              ><span class="font-mono text-xs">{{
                row.normal_balance === "D" ? "Debit" : "Kredit"
              }}</span></template
            >
            <template #cell-postable="{ row }"
              ><AppBadge :tone="row.is_postable ? 'success' : 'neutral'">{{
                row.is_postable ? "Ya" : "Header"
              }}</AppBadge></template
            >
            <template #cell-status="{ row }"
              ><AppBadge :tone="row.is_active ? 'success' : 'warning'">{{
                row.is_active ? "Aktif" : "Nonaktif"
              }}</AppBadge></template
            >
            <template #cell-created_at="{ row }"
              ><span class="whitespace-nowrap font-mono text-xs">{{
                row.created_at || "—"
              }}</span></template
            >
            <template #cell-deactivated_at="{ row }">
              <span
                class="whitespace-nowrap font-mono text-xs"
                :class="
                  row.deactivated_at
                    ? 'text-on-surface-variant'
                    : 'text-outline'
                "
                >{{ row.deactivated_at || "—" }}</span
              >
            </template>
          </ReportTable>
        </div>
        <p
          class="border-t border-outline-variant/20 px-4 py-3 text-xs text-on-surface-variant"
        >
          {{ rows.length }} baris ditampilkan
          <span v-if="filters.q || filters.type || filters.status !== 'all'">
            (terfilter)</span
          >.
        </p>
      </AppCard>
    </div>
  </AuthenticatedLayout>
</template>
