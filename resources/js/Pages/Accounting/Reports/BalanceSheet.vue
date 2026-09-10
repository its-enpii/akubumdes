<script setup>
import { Head } from "@inertiajs/vue3";
import AppBadge from "../../../Components/AppBadge.vue";
import AppCard from "../../../Components/AppCard.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import ReportPeriodFilter from "../../../Components/ReportPeriodFilter.vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";

import { computed } from "vue";
import { useMoney } from "../../../composables/useMoney";

const props = defineProps({
  period: { type: Object, required: true },
  identity: { type: Object, required: true },
  sections: { type: Array, required: true },
  totals: { type: Object, required: true },
  balanced: { type: Boolean, required: true },
  monthLabels: { type: Object, required: true },
  filters: { type: Object, required: true },
});

const { money } = useMoney();

const balanceColumns = [
  { key: "code", label: "Kode" },
  { key: "name", label: "Nama Akun" },
  { key: "balance", label: "Saldo", align: "right" },
];

const flatRows = computed(() =>
  props.sections.flatMap((l1) => [
    { sectionTitle: `${l1.code}. ${l1.name}`, isSectionTitle: true, level: 0 },
    ...l1.children.flatMap((l2) => [
      {
        code: l2.code,
        name: l2.name,
        balance: null,
        isSubtotal: false,
        level: 1,
      },
      ...l2.children.map((l3) => ({
        code: l3.code,
        name: l3.name,
        balance: l3.balance,
        isSubtotal: false,
        level: 2,
      })),
    ]),
    {
      sectionTitle: sectionTotalLabel(l1),
      isSectionTitle: true,
      balance: l1.balance,
      level: 0,
    },
  ]),
);

function fmt(value) {
  if (value < 0) return `(${money(Math.abs(value))})`;
  return money(value);
}

function sectionTotalLabel(l1) {
  if (l1.account_type === "asset") return "Jumlah Aset";
  if (l1.account_type === "liability") return "Jumlah Utang";
  if (l1.account_type === "equity") return "Jumlah Modal";
  return `Jumlah ${l1.name}`;
}
</script>

<template>
  <Head title="Neraca" />
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
          <h1 class="mt-1 text-2xl font-bold text-primary">Neraca</h1>
          <p class="text-sm text-on-surface-variant">
            {{ period.period_label }} · per {{ period.as_of }}
          </p>
        </div>
        <AppBadge :tone="balanced ? 'success' : 'error'">
          {{ balanced ? "Aset = Liabilitas+Ekuitas" : "Tidak seimbang" }}
        </AppBadge>
      </div>

      <AppCard class="p-4">
        <ReportPeriodFilter
          :year="filters.year"
          :month="filters.month"
          base-url="/accounting/reports/balance-sheet"
          pdf-url="/accounting/reports/balance-sheet/pdf"
          excel-url="/accounting/reports/balance-sheet/excel"
        />
      </AppCard>

      <AppCard class="overflow-hidden p-0">
        <ReportTable
          :columns="balanceColumns"
          :rows="flatRows"
          row-key="code"
          size="compact"
          empty-title="Belum ada data neraca."
        >
          <template #cell-code="{ row }">
            <span
              :class="row.isSectionTitle ? 'font-bold' : 'tabular-nums'"
              :style="{ paddingLeft: `${row.level * 16}px` }"
              >{{ row.isSectionTitle ? "" : row.code }}</span
            >
          </template>
          <template #cell-name="{ row }">
            <span
              :class="
                row.isSectionTitle
                  ? 'font-bold'
                  : row.level === 1
                    ? 'font-semibold'
                    : ''
              "
              >{{ row.isSectionTitle ? row.sectionTitle : row.name }}</span
            >
          </template>
          <template #cell-balance="{ row }">
            <span v-if="row.isSectionTitle" class="tabular-nums font-bold">{{
              row.balance !== undefined ? fmt(row.balance) : ""
            }}</span>
            <span
              v-else-if="row.balance !== null"
              class="tabular-nums"
              :class="row.balance < 0 ? 'text-error' : ''"
              >{{ fmt(row.balance) }}</span
            >
          </template>
        </ReportTable>
        <p
          class="border-t border-outline-variant/40 px-4 py-3 text-xs text-on-surface-variant"
        >
          Laba/Rugi tahun berjalan ({{ "3.2.02.01" }}):
          <span class="font-semibold text-on-surface">{{
            fmt(totals.net_income)
          }}</span>
        </p>
      </AppCard>
    </div>
  </AuthenticatedLayout>
</template>
