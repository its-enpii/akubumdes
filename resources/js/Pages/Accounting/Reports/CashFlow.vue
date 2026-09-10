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
  cash_accounts: { type: Array, required: true },
  opening_cash: { type: Number, required: true },
  closing_cash: { type: Number, required: true },
  implied_closing: { type: Number, required: true },
  net_change: { type: Number, required: true },
  reconciled: { type: Boolean, required: true },
  sections: { type: Array, required: true },
  monthLabels: { type: Object, required: true },
  filters: { type: Object, required: true },
});

const { money: formatMoney } = useMoney();

const cashFlowColumns = [
  { key: "label", label: "Uraian" },
  { key: "amount", label: "Jumlah", align: "right" },
];

const cashFlowSections = computed(() => [
  {
    rows: [{ label: "Saldo kas awal periode", amount: props.opening_cash }],
    tone: "tertiary",
  },
  ...props.sections.map((section) => ({
    title: section.label,
    rows: section.lines.length
      ? section.lines.map((line) => ({
          label: line.label,
          amount: line.amount,
          count: line.count,
        }))
      : [{ label: "Tidak ada mutasi", amount: null, isEmpty: true }],
    subtotalLabel: `Jumlah ${section.label.toLowerCase()}`,
    subtotal: section.total,
    tone: section.total < 0 ? "error" : "tertiary",
  })),
  {
    rows: [],
    subtotalLabel: "Kenaikan (penurunan) bersih kas",
    subtotal: props.net_change,
    tone: props.net_change < 0 ? "error" : "tertiary",
  },
  {
    rows: [],
    subtotalLabel: "Saldo kas akhir periode",
    subtotal: props.closing_cash,
    tone: "tertiary",
  },
]);
</script>

<template>
  <Head title="Arus Kas" />
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
          <h1 class="mt-1 text-2xl font-bold text-primary">Laporan Arus Kas</h1>
          <p class="text-sm text-on-surface-variant">
            {{ period.period_label }} · metode langsung dari jurnal akun kas
            (1.1.01*)
          </p>
        </div>
        <AppBadge :tone="reconciled ? 'success' : 'error'">
          {{ reconciled ? "Rekonsiliasi OK" : "Selisih vs saldo kas" }}
        </AppBadge>
      </div>

      <AppCard class="p-4">
        <ReportPeriodFilter
          :year="filters.year"
          :month="filters.month"
          base-url="/accounting/reports/cash-flow"
          pdf-url="/accounting/reports/cash-flow/pdf"
          excel-url="/accounting/reports/cash-flow/excel"
        />
      </AppCard>

      <div class="grid gap-3 sm:grid-cols-3">
        <AppCard>
          <p
            class="text-xs font-bold uppercase tracking-wider text-on-surface-variant"
          >
            Saldo kas awal
          </p>
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ formatMoney(opening_cash) }}
          </p>
        </AppCard>
        <AppCard>
          <p
            class="text-xs font-bold uppercase tracking-wider text-on-surface-variant"
          >
            Perubahan bersih
          </p>
          <p
            class="mt-2 text-2xl font-bold"
            :class="net_change >= 0 ? 'text-primary' : 'text-error'"
          >
            {{ formatMoney(net_change) }}
          </p>
        </AppCard>
        <AppCard>
          <p
            class="text-xs font-bold uppercase tracking-wider text-on-surface-variant"
          >
            Saldo kas akhir
          </p>
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ formatMoney(closing_cash) }}
          </p>
          <p v-if="!reconciled" class="mt-1 text-xs text-error">
            Implied {{ formatMoney(implied_closing) }}
          </p>
        </AppCard>
      </div>

      <AppCard class="overflow-hidden p-0">
        <div class="overflow-x-auto">
          <ReportTable
            :columns="cashFlowColumns"
            :rows="[]"
            :sections="cashFlowSections"
            size="compact"
          />
        </div>
        <p
          class="border-t border-outline-variant/40 px-4 py-3 text-xs text-on-surface-variant"
        >
          Klasifikasi dari lawan akun kas di tiap jurnal (operasi / investasi /
          pendanaan). Sumber pendanaan non-operasi digabung sesuai klasifikasi
          arus kas.
          <span v-if="cash_accounts.length">
            · Kas:
            <template v-for="(a, i) in cash_accounts" :key="a.row_id">
              {{ a.code }}<span v-if="i < cash_accounts.length - 1">, </span>
            </template>
          </span>
        </p>
      </AppCard>
    </div>
  </AuthenticatedLayout>
</template>
