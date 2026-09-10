<script setup>
import { Head } from "@inertiajs/vue3";
import AppCard from "../../../Components/AppCard.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import ReportPeriodFilter from "../../../Components/ReportPeriodFilter.vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

import { computed } from "vue";
import { useMoney } from "../../../composables/useMoney";

const props = defineProps({
  period: { type: Object, required: true },
  identity: { type: Object, required: true },
  header_lalu: { type: String, required: true },
  header_sekarang: { type: String, required: true },
  groups: { type: Array, required: true },
  summary: { type: Object, required: true },
  monthLabels: { type: Object, required: true },
  filters: { type: Object, required: true },
});

const { money } = useMoney();

const incomeColumns = [
  { key: "code", label: "Rekening" },
  { key: "prior", label: `s.d. ${props.header_lalu}`, align: "right" },
  { key: "current", label: props.header_sekarang, align: "right" },
  { key: "ytd", label: `s.d. ${props.header_sekarang}`, align: "right" },
];

const incomeSections = computed(() =>
  props.groups.map((group) => ({
    title: `${group.code}. ${group.name}`,
    rows: group.children,
    subtotalLabel: `Jumlah ${group.name}`,
    prior: group.prior,
    current: group.current,
    ytd: group.ytd,
  })),
);
</script>

<template>
  <Head title="Laba Rugi" />
  <AuthenticatedLayout>
    <div class="mx-auto max-w-7xl space-y-6">
      <div>
        <p
          class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant"
        >
          Pelaporan
        </p>
        <h1 class="mt-1 text-2xl font-bold text-primary">Laporan Laba Rugi</h1>
        <p class="text-sm text-on-surface-variant">{{ period.period_label }}</p>
      </div>

      <AppCard class="p-4">
        <ReportPeriodFilter
          :year="filters.year"
          :month="filters.month"
          base-url="/accounting/reports/income-statement"
          pdf-url="/accounting/reports/income-statement/pdf"
          excel-url="/accounting/reports/income-statement/excel"
        />
      </AppCard>

      <AppCard class="overflow-hidden p-0">
        <div class="overflow-x-auto">
          <ReportTable
            :columns="incomeColumns"
            :rows="[]"
            :sections="incomeSections"
            size="compact"
          >
            <template #cell-code="{ row }">
              <span class="font-medium">{{ row.code }}</span>
              <span class="text-on-surface-variant"> · {{ row.name }}</span>
            </template>
            <template #cell-prior="{ row }">{{ money(row.prior) }}</template>
            <template #cell-current="{ row }">{{
              money(row.current)
            }}</template>
            <template #cell-ytd="{ row }">{{ money(row.ytd) }}</template>
            <template #subtotal="{ section }">
              {{
                [section.prior, section.current, section.ytd]
                  .map(money)
                  .join(" | ")
              }}
            </template>
          </ReportTable>
        </div>
      </AppCard>
    </div>
  </AuthenticatedLayout>
</template>
