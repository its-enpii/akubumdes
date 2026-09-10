<script setup>
import { Head } from "@inertiajs/vue3";
import AppBadge from "../../../Components/AppBadge.vue";
import AppCard from "../../../Components/AppCard.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import ReportPeriodFilter from "../../../Components/ReportPeriodFilter.vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

import { useMoney } from "../../../composables/useMoney";

defineProps({
  period: { type: Object, required: true },
  identity: { type: Object, required: true },
  rows: { type: Array, required: true },
  totals: { type: Object, required: true },
  net_income: { type: Number, required: true },
  balanced: { type: Boolean, required: true },
  monthLabels: { type: Object, required: true },
  filters: { type: Object, required: true },
});

const { money } = useMoney();

const trialColumns = [
  { key: "account", label: "Rekening" },
  { key: "ns_debit", label: "Neraca Saldo — Debit", align: "right" },
  { key: "ns_credit", label: "Neraca Saldo — Kredit", align: "right" },
  { key: "lr_debit", label: "Laba Rugi — Debit", align: "right" },
  { key: "lr_credit", label: "Laba Rugi — Kredit", align: "right" },
  { key: "bs_debit", label: "Neraca — Debit", align: "right" },
  { key: "bs_credit", label: "Neraca — Kredit", align: "right" },
];
</script>

<template>
  <Head title="Neraca Saldo" />
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
          <h1 class="mt-1 text-2xl font-bold text-primary">Neraca Saldo</h1>
          <p class="text-sm text-on-surface-variant">
            {{ period.period_label }} · per {{ period.as_of }}
          </p>
        </div>
        <AppBadge :tone="balanced ? 'success' : 'error'">
          {{ balanced ? "Seimbang" : "Tidak seimbang" }}
        </AppBadge>
      </div>

      <AppCard class="p-4">
        <ReportPeriodFilter
          :year="filters.year"
          :month="filters.month"
          base-url="/accounting/reports/trial-balance"
          pdf-url="/accounting/reports/trial-balance/pdf"
          excel-url="/accounting/reports/trial-balance/excel"
        />
      </AppCard>

      <AppCard class="overflow-hidden p-0">
        <div class="overflow-x-auto">
          <ReportTable
            :columns="trialColumns"
            :rows="rows"
            row-key="row_id"
            size="compact"
            empty-title="Belum ada mutasi."
          >
            <template #cell-account="{ row }">
              <span class="font-medium">{{ row.code }}</span>
              <span class="text-on-surface-variant"> · {{ row.name }}</span>
            </template>
            <template #cell-ns_debit="{ row }">{{
              money(row.ns_debit)
            }}</template>
            <template #cell-ns_credit="{ row }">{{
              money(row.ns_credit)
            }}</template>
            <template #cell-lr_debit="{ row }">{{
              money(row.lr_debit)
            }}</template>
            <template #cell-lr_credit="{ row }">{{
              money(row.lr_credit)
            }}</template>
            <template #cell-bs_debit="{ row }">{{
              money(row.bs_debit)
            }}</template>
            <template #cell-bs_credit="{ row }">{{
              money(row.bs_credit)
            }}</template>
            <template #footer>
              <tr
                class="border-t-2 border-outline bg-surface-container-low font-semibold"
              >
                <td class="px-3 py-2">Jumlah</td>
                <td class="px-3 py-2 text-right tabular-nums">
                  {{ money(totals.ns_debit) }}
                </td>
                <td class="px-3 py-2 text-right tabular-nums">
                  {{ money(totals.ns_credit) }}
                </td>
                <td class="px-3 py-2 text-right tabular-nums">
                  {{ money(totals.lr_debit) }}
                </td>
                <td class="px-3 py-2 text-right tabular-nums">
                  {{ money(totals.lr_credit) }}
                </td>
                <td class="px-3 py-2 text-right tabular-nums">
                  {{ money(totals.bs_debit) }}
                </td>
                <td class="px-3 py-2 text-right tabular-nums">
                  {{ money(totals.bs_credit) }}
                </td>
              </tr>
            </template>
          </ReportTable>
        </div>
        <p
          class="border-t border-outline-variant/40 px-4 py-3 text-xs text-on-surface-variant"
        >
          Laba/Rugi berjalan:
          <span class="font-semibold text-on-surface">{{
            money(net_income)
          }}</span>
          (plug ke footer agar kolom seimbang, seperti legacy)
        </p>
      </AppCard>
    </div>
  </AuthenticatedLayout>
</template>
