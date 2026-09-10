<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import AppBadge from "../../Components/AppBadge.vue";
import AppButton from "../../Components/AppButton.vue";
import AppCard from "../../Components/AppCard.vue";
import AppDatePicker from "../../Components/AppDatePicker.vue";
import AppInput from "../../Components/AppInput.vue";
import SmartSelect from "../../Components/SmartSelect.vue";
import ReportTable from "../../Components/Reports/ReportTable.vue";
import AuthenticatedLayout from "../../Layouts/AuthenticatedLayout.vue";
import { useCan } from "../../composables/useCan";
import { useMoney } from "../../composables/useMoney";

const { can } = useCan();

const props = defineProps({
  assets: { type: Object, required: true },
  filters: { type: Object, required: true },
  categories: { type: Array, required: true },
  status_options: { type: Array, required: true },
  counts: { type: Object, required: true },
});

const q = ref(props.filters.q || "");
const status = ref(props.filters.status || "all");
const category = ref(
  props.filters.category ? String(props.filters.category) : "",
);
const asOf = ref(props.filters.as_of || "");
const syncing = ref(false);

watch(
  () => props.filters,
  (f) => {
    syncing.value = true;
    q.value = f.q || "";
    status.value = f.status || "all";
    category.value = f.category ? String(f.category) : "";
    asOf.value = f.as_of || "";
    queueMicrotask(() => {
      syncing.value = false;
    });
  },
  { deep: true },
);

const { money: formatMoney } = useMoney();

const categoryOptions = [
  { value: "", label: "Semua kategori" },
  ...props.categories.map((c) => ({ value: String(c.value), label: c.label })),
];

const assetColumns = [
  { key: "asset_code", label: "Kode" },
  { key: "name", label: "Nama" },
  { key: "purchased_at", label: "Tgl beli" },
  { key: "acquisition", label: "Perolehan", align: "right" },
  { key: "book_value", label: "Nilai buku", align: "right" },
  { key: "status", label: "Status" },
  { key: "actions", label: "Aksi", align: "right" },
];

function apply(page = 1) {
  if (syncing.value) return;
  router.get(
    "/accounting/assets",
    {
      q: q.value || undefined,
      status: status.value === "all" ? undefined : status.value,
      category: category.value || undefined,
      as_of: asOf.value || undefined,
      page: page > 1 ? page : undefined,
    },
    { preserveState: false, preserveScroll: true, replace: true },
  );
}

let searchTimer;
watch(q, () => {
  if (syncing.value) return;
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => apply(1), 350);
});
watch([status, category, asOf], () => {
  if (syncing.value) return;
  apply(1);
});
</script>

<template>
  <Head title="Inventaris" />
  <AuthenticatedLayout>
    <div class="mx-auto max-w-7xl space-y-6">
      <header
        class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">Inventaris</h1>
          <p class="mt-1 text-on-surface-variant">
            Register &amp; nilai buku. <strong>Beli aset</strong> lewat Jurnal
            Umum → tipe Pembelian Inventaris.
          </p>
        </div>
        <Link
          v-if="can('journals.create')"
          href="/accounting/journal-entries/create?type=pembelian_aset_peralatan"
        >
          <AppButton icon="receipt_long">Beli di Jurnal Umum</AppButton>
        </Link>
      </header>

      <div class="grid gap-3 sm:grid-cols-4">
        <div class="rounded-xl bg-surface-container-low px-4 py-3">
          <p class="text-xs text-on-surface-variant">Total item</p>
          <p class="text-xl font-bold text-primary">{{ counts.total }}</p>
        </div>
        <div class="rounded-xl bg-surface-container-low px-4 py-3">
          <p class="text-xs text-on-surface-variant">Kondisi baik</p>
          <p class="text-xl font-bold text-primary">{{ counts.good }}</p>
        </div>
        <div class="rounded-xl bg-surface-container-low px-4 py-3">
          <p class="text-xs text-on-surface-variant">Nilai perolehan</p>
          <p class="text-xl font-bold text-primary">
            {{ formatMoney(counts.acquisition) }}
          </p>
        </div>
        <div class="rounded-xl bg-surface-container-low px-4 py-3">
          <p class="text-xs text-on-surface-variant">Nilai buku (aktif)</p>
          <p class="text-xl font-bold text-primary">
            {{ formatMoney(counts.book) }}
          </p>
        </div>
      </div>

      <AppCard>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <AppInput v-model="q" label="Cari" placeholder="Kode atau nama" />
          <SmartSelect
            v-model="status"
            label="Status"
            :options="status_options"
          />
          <SmartSelect
            v-model="category"
            label="Kategori"
            :options="categoryOptions"
          />
          <AppDatePicker v-model="asOf" label="Nilai buku per" />
        </div>
      </AppCard>

      <AppCard :padded="false">
        <div>
          <ReportTable
            :columns="assetColumns"
            :rows="assets.data || []"
            row-key="row_id"
          >
            <template #cell-asset_code="{ row }">
              <span class="whitespace-nowrap font-mono text-xs">{{
                row.asset_code || "—"
              }}</span>
            </template>
            <template #cell-name="{ row }">
              <Link
                :href="`/accounting/assets/${row.row_id}`"
                class="font-semibold text-primary hover:underline"
                >{{ row.name }}</Link
              >
              <span
                v-if="row.category"
                class="block text-xs text-on-surface-variant"
                >{{ row.category.name }}</span
              >
            </template>
            <template #cell-acquisition="{ row }"
              ><span class="whitespace-nowrap">{{
                formatMoney(row.acquisition)
              }}</span></template
            >
            <template #cell-book_value="{ row }"
              ><span class="whitespace-nowrap">{{
                formatMoney(row.book_value)
              }}</span></template
            >
            <template #cell-status="{ row }">
              <AppBadge
                :tone="
                  row.status === 'good'
                    ? 'success'
                    : row.status === 'damaged'
                      ? 'warning'
                      : 'neutral'
                "
                >{{ row.status_label }}</AppBadge
              >
            </template>
            <template #cell-actions="{ row }">
              <div class="flex justify-end gap-1">
                <Link :href="`/accounting/assets/${row.row_id}`"
                  ><AppButton variant="ghost" size="compact" icon="visibility"
                    >Detail</AppButton
                  ></Link
                >
                <Link
                  v-if="can('assets.manage')"
                  :href="`/accounting/assets/${row.row_id}/edit`"
                  ><AppButton variant="ghost" size="compact" icon="edit"
                    >Edit</AppButton
                  ></Link
                >
              </div>
            </template>
          </ReportTable>
        </div>
        <div
          v-if="assets.last_page > 1"
          class="flex items-center justify-between border-t border-outline-variant/20 px-4 py-3 text-sm"
        >
          <p class="text-on-surface-variant">
            Halaman {{ assets.current_page }} / {{ assets.last_page }} ·
            {{ assets.total }} item
          </p>
          <div class="flex gap-2">
            <AppButton
              variant="ghost"
              size="compact"
              :disabled="assets.current_page <= 1"
              @click="apply(assets.current_page - 1)"
            >
              Prev
            </AppButton>
            <AppButton
              variant="ghost"
              size="compact"
              :disabled="assets.current_page >= assets.last_page"
              @click="apply(assets.current_page + 1)"
            >
              Next
            </AppButton>
          </div>
        </div>
      </AppCard>
    </div>
  </AuthenticatedLayout>
</template>
