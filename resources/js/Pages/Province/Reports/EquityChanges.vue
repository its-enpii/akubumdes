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
const baseUrl = "/province/reports/equity-changes";

const equityColumns = [
  { key: "name", label: "Komponen Ekuitas" },
  { key: "amount", label: "Jumlah (Rp)", align: "right" },
];

const equityRows = computed(() => [
  { name: "Ekuitas Awal Periode", amount: props.report.opening_equity },
  {
    name: "Laba Bersih Tahun / Periode Berjalan",
    amount: props.report.net_income,
  },
  {
    name: "TOTAL EKUITAS AKHIR PERIODE",
    amount: props.report.ending_equity,
    tone: "secondary",
  },
]);
</script>

<template>
  <Head :title="`Perubahan Ekuitas - Provinsi ${province_name}`" />
  <ProvinceLayout>
    <div class="space-y-6">
      <div
        class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">
            Laporan Perubahan Ekuitas Provinsi {{ province_name }}
          </h1>
          <p class="mt-1 text-sm text-on-surface-variant">
            Perubahan Modal dan Hasil Usaha Konsolidasi ({{
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
            LAPORAN PERUBAHAN EKUITAS
          </h2>
        </div>
        <div class="p-6 overflow-x-auto">
          <ReportTable
            :columns="equityColumns"
            :rows="equityRows"
            size="compact"
          >
            <template #cell-amount="{ row }">
              <span :class="row.tone === 'secondary' ? 'font-bold' : ''">{{
                money(row.amount)
              }}</span>
            </template>
          </ReportTable>
        </div>
      </AppCard>
    </div>
  </ProvinceLayout>
</template>
