<script setup>
import { Head, router } from "@inertiajs/vue3";
import AppBadge from "../../../Components/AppBadge.vue";
import AppButton from "../../../Components/AppButton.vue";
import AppCard from "../../../Components/AppCard.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import ReportPeriodFilter from "../../../Components/ReportPeriodFilter.vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

const props = defineProps({
  period: { type: Object, required: true },
  identity: { type: Object, required: true },
  rows: { type: Array, required: true },
  pagination: { type: Object, required: true },
  totals: { type: Object, required: true },
  page_totals: { type: Object, required: true },
  balanced: { type: Boolean, required: true },
  truncated: { type: Boolean, default: false },
  monthLabels: { type: Object, required: true },
  filters: { type: Object, required: true },
  day: { type: String, default: null },
});

import { useMoney } from "../../../composables/useMoney";

const { money } = useMoney();

const journalColumns = [
  { key: "no", label: "No" },
  { key: "date", label: "Tanggal" },
  { key: "journal_number", label: "No. Jurnal" },
  { key: "description", label: "Kode / Keterangan" },
  { key: "debit", label: "Debit", align: "right" },
  { key: "credit", label: "Kredit", align: "right" },
  { key: "balance", label: "Saldo", align: "right" },
];

function goPage(page) {
  router.get(
    "/accounting/reports/journals",
    {
      year: props.filters.year,
      month: props.filters.month,
      day: props.filters.day || undefined,
      page,
    },
    { preserveScroll: true, replace: true },
  );
}
</script>

<template>
  <Head title="Jurnal Transaksi" />
  <AuthenticatedLayout>
    <div class="mx-auto max-w-7xl space-y-6">
      <div
        class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
      >
        <div>
          <p
            class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant"
          >
            Pelaporan
          </p>
          <h1 class="mt-1 text-2xl font-bold text-primary">Jurnal Transaksi</h1>
          <p class="text-sm text-on-surface-variant">
            {{ period.period_label }}
          </p>
        </div>
        <AppBadge :tone="balanced ? 'success' : 'error'">
          {{ balanced ? "Debit = Kredit" : "Tidak seimbang" }}
        </AppBadge>
      </div>

      <AppCard class="p-4">
        <ReportPeriodFilter
          :year="filters.year"
          :month="filters.month"
          :day="filters.day || null"
          show-day
          base-url="/accounting/reports/journals"
          pdf-url="/accounting/reports/journals/pdf"
          excel-url="/accounting/reports/journals/excel"
        />
      </AppCard>

      <AppCard class="overflow-hidden p-0">
        <div class="overflow-x-auto">
          <ReportTable
            :columns="journalColumns"
            :rows="rows"
            row-key="entry_row_id"
            size="compact"
          >
            <template #cell-description="{ row }">
              <span class="font-medium">{{ row.code }}</span>
              <span class="text-on-surface-variant">
                · {{ row.description }}</span
              >
            </template>
            <template #cell-debit="{ row }">{{
              row.debit ? money(row.debit) : ""
            }}</template>
            <template #cell-credit="{ row }">{{
              row.credit ? money(row.credit) : ""
            }}</template>
            <template #footer>
              <tr
                class="border-t-2 border-outline bg-surface-container-low font-semibold"
              >
                <td colspan="4" class="px-3 py-2">Total halaman</td>
                <td class="px-3 py-2 text-right tabular-nums">
                  {{ money(page_totals.debit) }}
                </td>
                <td class="px-3 py-2 text-right tabular-nums">
                  {{ money(page_totals.credit) }}
                </td>
                <td class="px-3 py-2 text-right tabular-nums">
                  {{ money(totals.debit) }} / {{ money(totals.credit) }}
                </td>
              </tr>
            </template>
          </ReportTable>
        </div>
        <div
          v-if="pagination.last_page > 1"
          class="flex items-center justify-between border-t border-outline-variant/40 px-4 py-3 text-sm"
        >
          <span class="text-on-surface-variant">
            Halaman {{ pagination.page }} / {{ pagination.last_page }} ·
            {{ pagination.total }} baris
          </span>
          <div class="flex gap-2">
            <AppButton
              type="button"
              variant="secondary"
              size="compact"
              icon="chevron_left"
              :disabled="pagination.page <= 1"
              @click="goPage(pagination.page - 1)"
            >
              Prev
            </AppButton>
            <AppButton
              type="button"
              variant="secondary"
              size="compact"
              class="!flex-row-reverse"
              icon="chevron_right"
              :disabled="pagination.page >= pagination.last_page"
              @click="goPage(pagination.page + 1)"
            >
              Next
            </AppButton>
          </div>
        </div>
      </AppCard>
    </div>
  </AuthenticatedLayout>
</template>
