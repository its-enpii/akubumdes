<script setup>
import { computed, ref, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AppCard from "../../../Components/AppCard.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import ReportPeriodFilter from "../../../Components/ReportPeriodFilter.vue";
import SmartSelect from "../../../Components/SmartSelect.vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

const props = defineProps({
  period: { type: Object, required: true },
  identity: { type: Object, required: true },
  account: { type: Object, default: null },
  opening: { type: Object, default: null },
  rows: { type: Array, required: true },
  totals: { type: Object, required: true },
  account_options: { type: Array, required: true },
  monthLabels: { type: Object, required: true },
  filters: { type: Object, required: true },
  error: { type: String, default: null },
});

import { useMoney } from "../../../composables/useMoney";

const { money } = useMoney();
const selectedAccount = ref(
  props.filters.account ? String(props.filters.account) : "",
);

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
  ...(props.opening
    ? [
        {
          ...props.opening.year,
          row_id: "opening-year",
          isOpening: true,
          debit: props.opening.year.debit,
          credit: props.opening.year.credit,
          balance: props.opening.year.balance,
        },
        {
          ...props.opening.prior,
          row_id: "opening-prior",
          isOpening: true,
          debit: props.opening.prior.debit,
          credit: props.opening.prior.credit,
          balance: props.opening.prior.balance,
        },
      ]
    : []),
  ...props.rows.map((row) => ({
    ...row,
    row_id: `${row.entry_row_id}-${row.no}`,
  })),
]);

watch(
  () => props.filters.account,
  (v) => {
    selectedAccount.value = v ? String(v) : "";
  },
);

const accountOptions = computed(() =>
  props.account_options.map((a) => ({
    value: String(a.row_id),
    label: a.label,
  })),
);

const extraFilters = computed(() => ({
  account: selectedAccount.value || undefined,
}));

function onAccountChange() {
  router.get(
    "/accounting/reports/general-ledger",
    {
      year: props.filters.year,
      month: props.filters.month,
      day: props.filters.day || undefined,
      account: selectedAccount.value || undefined,
    },
    { preserveScroll: true, replace: true },
  );
}
</script>

<template>
  <Head title="Buku Besar" />
  <AuthenticatedLayout>
    <div class="mx-auto max-w-7xl space-y-6">
      <div>
        <p
          class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant"
        >
          Pelaporan
        </p>
        <h1 class="mt-1 text-2xl font-bold text-primary">
          Buku Besar
          <span
            v-if="account"
            class="text-lg font-semibold text-on-surface-variant"
            >— {{ account.name }}</span
          >
        </h1>
        <p class="text-sm text-on-surface-variant">
          {{ period.period_label }}
          <span v-if="account"> · {{ account.code }}</span>
        </p>
      </div>

      <AppCard class="space-y-4 p-4">
        <ReportPeriodFilter
          :year="filters.year"
          :month="filters.month"
          :day="filters.day || null"
          show-day
          :extra="extraFilters"
          base-url="/accounting/reports/general-ledger"
          :pdf-url="account ? '/accounting/reports/general-ledger/pdf' : null"
          :excel-url="
            account ? '/accounting/reports/general-ledger/excel' : null
          "
        >
          <template #extra>
            <div class="w-full min-w-0 flex-1 lg:max-w-sm">
              <SmartSelect
                v-model="selectedAccount"
                label="Akun"
                :options="accountOptions"
                placeholder="Pilih akun postable"
                searchable
                @update:model-value="onAccountChange"
              />
            </div>
          </template>
        </ReportPeriodFilter>
        <p v-if="error" class="text-sm text-error">{{ error }}</p>
      </AppCard>

      <AppCard v-if="!account" class="p-8 text-center text-on-surface-variant">
        Pilih akun untuk menampilkan buku besar.
      </AppCard>

      <AppCard v-else class="overflow-hidden p-0">
        <div class="overflow-x-auto">
          <ReportTable
            :columns="ledgerColumns"
            :rows="ledgerRows"
            row-key="row_id"
            size="compact"
          >
            <template #cell-date="{ row }"
              ><span
                :class="
                  row.isOpening ? 'tabular-nums font-medium' : 'tabular-nums'
                "
                >{{ row.date }}</span
              ></template
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
            <template #footer>
              <tr
                class="border-t-2 border-outline bg-surface-container-low font-semibold"
              >
                <td colspan="4" class="px-3 py-2">
                  {{ totals.period?.label || "Total Transaksi Periode" }}
                </td>
                <td class="px-3 py-2 text-right tabular-nums">
                  {{ money(totals.period?.debit ?? totals.debit) }}
                </td>
                <td class="px-3 py-2 text-right tabular-nums">
                  {{ money(totals.period?.credit ?? totals.credit) }}
                </td>
                <td></td>
              </tr>
            </template>
          </ReportTable>
        </div>
      </AppCard>
    </div>
  </AuthenticatedLayout>
</template>
