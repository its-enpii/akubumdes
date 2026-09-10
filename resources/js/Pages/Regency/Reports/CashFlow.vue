<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import AppCard from "../../../Components/AppCard.vue";
import RegencyLayout from "../../../Layouts/RegencyLayout.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import ReportPeriodFilter from "../../../Components/ReportPeriodFilter.vue";
import SmartSelect from "../../../Components/SmartSelect.vue";
import { useMoney } from "../../../composables/useMoney";

const { money } = useMoney();

const props = defineProps({
  report: { type: Object, required: true },
  year: { type: Number, required: true },
  month: { type: [Number, String], default: "" },
  selected_tenant_id: { type: [Number, String], default: "" },
  regency_name: { type: String, default: "Kabupaten" },
});

const selectedTenant = ref(props.selected_tenant_id || "");

const baseUrl = "/regency/reports/cash-flow";

const tenantOptions = computed(() => [
  { value: "", label: "Semua Kecamatan (Gabungan)" },
  ...(props.report.kecamatans || []).map((kec) => ({
    value: kec.id,
    label: kec.name,
  })),
]);

watch(selectedTenant, () => {
  router.get(
    baseUrl,
    {
      year: props.year,
      month: props.month || "",
      tenant_id: selectedTenant.value || "",
    },
    { preserveState: true },
  );
});

const cashColumns = [
  { key: "label", label: "Uraian" },
  { key: "amount", label: "Jumlah", align: "right" },
];

const cashRows = computed(() => [
  {
    row_id: "opening",
    isHeader: true,
    label: "Saldo kas awal periode",
    amount: props.report.opening_cash,
  },
  ...(props.report.sections || []).flatMap((section) => [
    { row_id: `section-${section.key}`, isHeader: true, label: section.label },
    ...(section.lines || []).map((line, idx) => ({
      row_id: `${section.key}-${idx}`,
      label: line.label,
      amount: line.amount,
      count: line.count,
    })),
    {
      row_id: `subtotal-${section.key}`,
      isSubtotal: true,
      label: `Jumlah ${section.label.toLowerCase()}`,
      amount: section.total,
    },
  ]),
  {
    row_id: "net",
    isTotal: true,
    label: "Kenaikan (penurunan) bersih kas",
    amount: props.report.net_change,
  },
  {
    row_id: "closing",
    isTotal: true,
    label: "Saldo kas akhir periode",
    amount: props.report.closing_cash,
  },
]);
</script>

<template>
  <Head :title="`Arus Kas Konsolidasi - ${regency_name}`" />
  <RegencyLayout>
    <div class="space-y-6">
      <!-- Header & Filters -->
      <div
        class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">
            Arus Kas Konsolidasi Kabupaten {{ regency_name }}
          </h1>
          <p class="mt-1 text-sm text-on-surface-variant">
            {{ report.period?.period_label }} ·
            {{
              report.is_consolidated
                ? "Gabungan Seluruh Kecamatan"
                : "Kecamatan Terpilih"
            }}
          </p>
        </div>

        <ReportPeriodFilter
          :year="year"
          :month="month"
          :base-url="baseUrl"
          pdf-url="/regency/reports/cash-flow/pdf"
          :extra="{ tenant_id: selectedTenant || '' }"
        >
          <template #extra>
            <SmartSelect
              v-model="selectedTenant"
              :options="tenantOptions"
              label="Kecamatan"
              value-key="value"
              label-key="label"
              hide-label
            />
          </template>
        </ReportPeriodFilter>
      </div>

      <!-- Summary Cards -->
      <div class="grid gap-4 sm:grid-cols-3">
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Saldo Kas Awal</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.reconciliation?.cash_opening) }}
          </p>
        </AppCard>
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Kenaikan / Penurunan Bersih</span
          >
          <p
            class="mt-2 text-2xl font-bold"
            :class="
              (report.reconciliation?.net_change || 0) >= 0
                ? 'text-primary'
                : 'text-error'
            "
          >
            {{ money(report.reconciliation?.net_change) }}
          </p>
        </AppCard>
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Saldo Kas Akhir</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.reconciliation?.cash_closing) }}
          </p>
        </AppCard>
      </div>

      <!-- Cash Flow Table -->
      <AppCard :padded="false">
        <div class="overflow-x-auto">
          <ReportTable
            :columns="cashColumns"
            :rows="cashRows"
            size="compact"
            row-key="row_id"
          >
            <template #cell-label="{ row }"
              ><span
                :class="
                  row.isHeader || row.isSubtotal || row.isTotal
                    ? 'font-bold'
                    : ''
                "
                >{{ row.label }}</span
              ></template
            >
            <template #cell-amount="{ row }"
              ><span
                :class="[
                  row.amount < 0 ? 'text-error' : '',
                  row.isSubtotal || row.isTotal ? 'font-bold' : '',
                ]"
                >{{ money(row.amount) }}</span
              ></template
            >
          </ReportTable>
        </div>
      </AppCard>
    </div>
  </RegencyLayout>
</template>
