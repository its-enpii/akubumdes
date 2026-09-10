<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import AppCard from "../../../Components/AppCard.vue";
import ReportPeriodFilter from "../../../Components/ReportPeriodFilter.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import { useMoney } from "../../../composables/useMoney";
import ProvinceLayout from "../../../Layouts/ProvinceLayout.vue";

const props = defineProps({
  report: { type: Object, required: true },
  year: { type: Number, required: true },
  month: { type: [Number, String], default: "" },
  province_name: { type: String, default: "Provinsi" },
});

const { money } = useMoney();
const baseUrl = "/province/reports/balance-sheet";

const balanceSheetColumns = [
  { key: "code", label: "Kode" },
  { key: "name", label: "Nama Akun" },
  { key: "balance", label: "Saldo (Rp)", align: "right" },
];

const balanceSheetSections = computed(() => [
  {
    title: "ASET",
    rows: props.report.assets.rows,
    subtotalLabel: "TOTAL ASET",
    subtotal: props.report.assets.total,
    tone: "primary",
  },
  {
    title: "KEWAJIBAN & EKUITAS",
    rows: [
      ...props.report.liabilities.rows,
      {
        sectionLabel: "TOTAL KEWAJIBAN",
        sectionValue: props.report.liabilities.total,
        isSectionTotal: true,
      },
      ...props.report.equity.rows,
      {
        sectionLabel: "TOTAL EKUITAS",
        sectionValue: props.report.equity.total,
        isSectionTotal: true,
      },
    ],
  },
  {
    rows: [],
    subtotalLabel: "TOTAL KEWAJIBAN & EKUITAS",
    subtotal: props.report.total_liabilities_and_equity,
    tone: "primary",
  },
]);
</script>

<template>
  <Head :title="`Neraca Konsolidasi - Provinsi ${province_name}`" />
  <ProvinceLayout>
    <div class="space-y-6">
      <div
        class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">
            Neraca Konsolidasi Provinsi {{ province_name }}
          </h1>
          <p class="mt-1 text-sm text-on-surface-variant">
            Posisi Aset, Kewajiban, dan Ekuitas gabungan ({{
              report.period?.period_label
            }}).
          </p>
        </div>

        <ReportPeriodFilter
          :year="year"
          :month="month"
          :base-url="baseUrl"
          pdf-url="/province/reports/pdf"
        />
      </div>

      <!-- Summary Cards -->
      <div class="grid gap-4 sm:grid-cols-3">
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Total Aset</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.assets?.total) }}
          </p>
        </AppCard>
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Total Kewajiban</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.liabilities?.total) }}
          </p>
        </AppCard>
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Total Ekuitas</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.equity?.total) }}
          </p>
        </AppCard>
      </div>

      <AppCard :padded="false">
        <div class="border-b border-outline-variant px-6 py-4">
          <h2 class="font-bold text-primary text-lg">NERACA KONSOLIDASI</h2>
        </div>
        <div class="p-6 overflow-x-auto">
          <ReportTable
            :columns="balanceSheetColumns"
            :rows="[]"
            :sections="balanceSheetSections"
            size="compact"
          >
            <template #cell-code="{ row }"
              ><span class="font-mono text-xs">{{ row.code }}</span></template
            >
            <template #cell-name="{ row }"
              ><span :class="row.level === 1 ? 'font-bold' : ''">{{
                row.name
              }}</span></template
            >
            <template #cell-balance="{ row }"
              ><span :class="row.level === 1 ? 'font-bold' : ''">{{
                money(row.balance)
              }}</span></template
            >
          </ReportTable>
        </div>
      </AppCard>
    </div>
  </ProvinceLayout>
</template>
