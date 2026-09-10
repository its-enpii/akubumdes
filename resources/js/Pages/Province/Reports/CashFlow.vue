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
const baseUrl = "/province/reports/cash-flow";

const cashFlowColumns = [
  { key: "name", label: "Aktivitas" },
  { key: "amount", label: "Jumlah (Rp)", align: "right" },
];

const cashFlowRows = computed(() => [
  {
    name: "Arus Kas Aktivitas Operasional",
    amount: props.report.operating_activities,
  },
  {
    name: "Arus Kas Aktivitas Investasi",
    amount: props.report.investing_activities,
  },
  {
    name: "Arus Kas Aktivitas Pendanaan",
    amount: props.report.financing_activities,
  },
  {
    name: "Kenaikan / (Penurunan) Kas Bersih",
    amount: props.report.net_cash_change,
    tone: "tertiary",
  },
  {
    name: "SALDO KAS & BANK AKHIR PERIODE",
    amount: props.report.ending_cash,
    tone: "secondary",
  },
]);
</script>

<template>
  <Head :title="`Arus Kas Konsolidasi - Provinsi ${province_name}`" />
  <ProvinceLayout>
    <div class="space-y-6">
      <div
        class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">
            Arus Kas Konsolidasi Provinsi {{ province_name }}
          </h1>
          <p class="mt-1 text-sm text-on-surface-variant">
            Arus Kas Operasional, Investasi, dan Pendanaan ({{
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

      <AppCard :padded="false">
        <div class="border-b border-outline-variant px-6 py-4">
          <h2 class="font-bold text-primary text-lg">
            LAPORAN ARUS KAS KONSOLIDASI
          </h2>
        </div>
        <div class="p-6 overflow-x-auto">
          <ReportTable
            :columns="cashFlowColumns"
            :rows="cashFlowRows"
            size="compact"
          >
            <template #cell-amount="{ row }">
              <span :class="row.tone ? 'font-bold' : ''">{{
                money(row.amount)
              }}</span>
            </template>
          </ReportTable>
        </div>
      </AppCard>
    </div>
  </ProvinceLayout>
</template>
