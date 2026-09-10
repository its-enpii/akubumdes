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
const baseUrl = "/province/reports/income-statement";

const incomeStatementColumns = [
  { key: "code", label: "Kode" },
  { key: "name", label: "Uraian" },
  { key: "amount", label: "Jumlah (Rp)", align: "right" },
];

const incomeSections = computed(() => [
  {
    title: "PENDAPATAN OPERASIONAL",
    rows: props.report.revenue_ops.rows,
    subtotalLabel: "SUBTOTAL PENDAPATAN OPERASIONAL",
    subtotal: props.report.revenue_ops.total,
  },
  {
    title: "BEBAN OPERASIONAL",
    rows: props.report.expense_ops.rows,
    subtotalLabel: "SUBTOTAL BEBAN OPERASIONAL",
    subtotal: props.report.expense_ops.total,
  },
  {
    rows: [],
    subtotalLabel: "LABA OPERASIONAL",
    subtotal: props.report.summary.operating_profit.ytd,
    tone: "secondary",
  },
  {
    rows: [],
    subtotalLabel: "LABA BERSIH (NET PROFIT)",
    subtotal: props.report.summary.after_tax.ytd,
    tone: "secondary",
  },
]);
</script>

<template>
  <Head :title="`Laba Rugi Konsolidasi - Provinsi ${province_name}`" />
  <ProvinceLayout>
    <div class="space-y-6">
      <div
        class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">
            Laba Rugi Konsolidasi Provinsi {{ province_name }}
          </h1>
          <p class="mt-1 text-sm text-on-surface-variant">
            Pendapatan, Beban, dan Hasil Usaha Konsolidasi ({{
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
            >Pendapatan Operasional</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.revenue_ops?.total) }}
          </p>
        </AppCard>
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Beban Operasional</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.expense_ops?.total) }}
          </p>
        </AppCard>
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Laba Bersih YTD</span
          >
          <p class="mt-2 text-2xl font-bold text-secondary">
            {{ money(report.summary?.after_tax?.ytd) }}
          </p>
        </AppCard>
      </div>

      <AppCard :padded="false">
        <div class="border-b border-outline-variant px-6 py-4">
          <h2 class="font-bold text-primary text-lg">
            LAPORAN LABA RUGI KONSOLIDASI
          </h2>
        </div>
        <div class="p-6 overflow-x-auto">
          <ReportTable
            :columns="incomeStatementColumns"
            :rows="[]"
            :sections="incomeSections"
            size="compact"
          >
            <template #cell-code="{ row }"
              ><span class="font-mono text-xs">{{ row.code }}</span></template
            >
            <template #cell-amount="{ row }">{{ money(row.amount) }}</template>
            <template #subtotal="{ section }">{{
              money(section.subtotal)
            }}</template>
          </ReportTable>
        </div>
      </AppCard>
    </div>
  </ProvinceLayout>
</template>
