<script setup>
import { Head, router } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
import AppBadge from "../../../Components/AppBadge.vue";
import AppCard from "../../../Components/AppCard.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import ReportPeriodFilter from "../../../Components/ReportPeriodFilter.vue";
import SmartSelect from "../../../Components/SmartSelect.vue";
import RegencyLayout from "../../../Layouts/RegencyLayout.vue";
import { useMoney } from "../../../composables/useMoney";

const { money } = useMoney();

const props = defineProps({
  report: { type: Object, required: true },
  year: { type: Number, required: true },
  month: { type: [Number, String], default: "" },
  day: { type: String, default: "" },
  selected_tenant_id: { type: [Number, String], default: "" },
  selected_account_id: { type: [Number, String], default: "" },
  regency_name: { type: String, default: "Kabupaten" },
});

const selectedTenant = ref(props.selected_tenant_id || "");
const selectedAccountId = ref(
  props.selected_account_id || props.report.account?.row_id || "",
);

const tenantOptions = computed(() => [
  { value: "", label: "Semua Kecamatan (Gabungan)" },
  ...(props.report.kecamatans || []).map((kec) => ({
    value: kec.id,
    label: kec.name,
  })),
]);

const accountOptions = computed(() =>
  (props.report.accounts || []).map((acc) => ({
    value: acc.row_id,
    label: `${acc.code} - ${acc.name}`,
  })),
);

watch([selectedTenant, selectedAccountId], () => {
  router.get(
    "/regency/reports/general-ledger",
    {
      year: props.year,
      month: props.month || "",
      day: props.day || "",
      tenant_id: selectedTenant.value || "",
      account_id: selectedAccountId.value || "",
    },
    { preserveState: true },
  );
});

const ledgerColumns = [
  { key: "no", label: "No" },
  { key: "date", label: "Tanggal" },
  { key: "journal_number", label: "No. Jurnal" },
  { key: "description", label: "Keterangan" },
  { key: "debit", label: "Debit", align: "right" },
  { key: "credit", label: "Kredit", align: "right" },
  { key: "balance", label: "Saldo", align: "right" },
];

const ledgerRows = computed(() => [
  ...(props.report.opening?.year
    ? [
        {
          row_id: "opening-year",
          isOpening: true,
          date: props.report.opening.year.date,
          description: props.report.opening.year.label,
          debit: props.report.opening.year.debit,
          credit: props.report.opening.year.credit,
          balance: props.report.opening.year.balance,
        },
      ]
    : []),
  ...(props.report.opening?.prior
    ? [
        {
          row_id: "opening-prior",
          isOpening: true,
          date: props.report.opening.prior.date,
          description: props.report.opening.prior.label,
          debit: props.report.opening.prior.debit,
          credit: props.report.opening.prior.credit,
          balance: props.report.opening.prior.balance,
        },
      ]
    : []),
  ...(props.report.rows || []),
]);
</script>

<template>
  <Head :title="`Buku Besar Konsolidasi - ${regency_name}`" />
  <RegencyLayout>
    <div class="space-y-6">
      <!-- Header & Filter -->
      <div
        class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">
            Buku Besar Kabupaten {{ regency_name }}
          </h1>
          <p class="mt-1 text-sm text-on-surface-variant">
            {{ report.account?.code }} - {{ report.account?.name }} ·
            {{ report.period?.period_label }}
          </p>
        </div>
      </div>

      <ReportPeriodFilter
        :year="year"
        :month="month"
        :day="day"
        show-day
        baseUrl="/regency/reports/general-ledger"
        pdfUrl="/regency/reports/general-ledger/pdf"
        :extra="{
          tenant_id: selectedTenant || '',
          account_id: selectedAccountId || '',
        }"
      >
        <template #extra>
          <SmartSelect
            v-model="selectedAccountId"
            :options="accountOptions"
            label="Akun"
            value-key="value"
            label-key="label"
            hide-label
            searchable
          />
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

      <!-- Summary Cards -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Saldo Awal</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.opening_balance) }}
          </p>
        </AppCard>
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Total Debit</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.total_debit) }}
          </p>
        </AppCard>
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Total Kredit</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.total_credit) }}
          </p>
        </AppCard>
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Saldo Akhir</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.closing_balance) }}
          </p>
        </AppCard>
      </div>

      <!-- Table Entries -->
      <AppCard :padded="false">
        <div class="overflow-x-auto">
          <ReportTable
            :columns="ledgerColumns"
            :rows="ledgerRows"
            size="compact"
            row-key="row_id"
          >
            <template #cell-description="{ row }"
              ><span :class="row.isOpening ? 'font-medium' : ''">{{
                row.description
              }}</span></template
            >
            <template #cell-debit="{ row }">{{ money(row.debit) }}</template>
            <template #cell-credit="{ row }">{{ money(row.credit) }}</template>
            <template #cell-balance="{ row }"
              ><span :class="row.isOpening ? 'font-semibold' : 'font-medium'">{{
                money(row.balance)
              }}</span></template
            >
          </ReportTable>
        </div>
      </AppCard>
    </div>
  </RegencyLayout>
</template>
