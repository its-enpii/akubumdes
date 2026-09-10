<script setup>
import { Head } from "@inertiajs/vue3";
import { ref } from "vue";
import { computed } from "vue";
import AppCard from "../../../Components/AppCard.vue";
import AppTabs from "../../../Components/AppTabs.vue";
import ReportPeriodFilter from "../../../Components/ReportPeriodFilter.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import { useMoney } from "../../../composables/useMoney";
import ProvinceLayout from "../../../Layouts/ProvinceLayout.vue";

const props = defineProps({
  pack: { type: Object, required: true },
  year: { type: Number, required: true },
  month: { type: [Number, String], default: "" },
  province_name: { type: String, default: "Provinsi" },
});

const { money } = useMoney();
const activeTab = ref("balance");
const baseUrl = "/province/reports/pack";

const tabs = [
  { key: "balance", label: "1. Neraca Konsolidasi" },
  { key: "income", label: "2. Laba Rugi Konsolidasi" },
  { key: "cash", label: "3. Arus Kas" },
  { key: "equity", label: "4. Perubahan Ekuitas" },
  { key: "calk", label: "5. CALK" },
];

const balanceSheetColumns = [
  { key: "code", label: "Kode" },
  { key: "name", label: "Nama Akun" },
  { key: "balance", label: "Saldo (Rp)", align: "right" },
];
const incomeStatementColumns = [
  { key: "code", label: "Kode" },
  { key: "name", label: "Uraian" },
  { key: "amount", label: "Jumlah (Rp)", align: "right" },
];
const cashFlowColumns = [
  { key: "name", label: "Aktivitas" },
  { key: "amount", label: "Jumlah (Rp)", align: "right" },
];
const equityColumns = [
  { key: "name", label: "Komponen Ekuitas" },
  { key: "amount", label: "Jumlah (Rp)", align: "right" },
];

const balanceSheetSections = computed(() => [
  {
    title: "ASET",
    rows: props.pack.balance_sheet.assets.rows,
    subtotalLabel: "TOTAL ASET",
    subtotal: props.pack.balance_sheet.assets.total,
    tone: "primary",
  },
  {
    title: "KEWAJIBAN & EKUITAS",
    rows: [
      ...props.pack.balance_sheet.liabilities.rows,
      {
        sectionLabel: "TOTAL KEWAJIBAN",
        sectionValue: props.pack.balance_sheet.liabilities.total,
        isSectionTotal: true,
      },
      ...props.pack.balance_sheet.equity.rows,
      {
        sectionLabel: "TOTAL EKUITAS",
        sectionValue: props.pack.balance_sheet.equity.total,
        isSectionTotal: true,
      },
    ],
  },
  {
    rows: [],
    subtotalLabel: "TOTAL KEWAJIBAN & EKUITAS",
    subtotal: props.pack.balance_sheet.total_liabilities_and_equity,
    tone: "primary",
  },
]);

const incomeSections = computed(() => [
  {
    title: "PENDAPATAN OPERASIONAL",
    rows: props.pack.income_statement.revenue_ops.rows,
    subtotalLabel: "SUBTOTAL PENDAPATAN OPS",
    subtotal: props.pack.income_statement.revenue_ops.total,
  },
  {
    title: "BEBAN OPERASIONAL",
    rows: props.pack.income_statement.expense_ops.rows,
    subtotalLabel: "SUBTOTAL BEBAN OPS",
    subtotal: props.pack.income_statement.expense_ops.total,
  },
  {
    rows: [],
    subtotalLabel: "LABA OPERASIONAL",
    subtotal: props.pack.income_statement.summary.operating_profit.ytd,
    tone: "secondary",
  },
  {
    rows: [],
    subtotalLabel: "LABA BERSIH (NET PROFIT)",
    subtotal: props.pack.income_statement.summary.after_tax.ytd,
    tone: "secondary",
  },
]);

const cashFlowRows = computed(() => [
  {
    name: "Arus Kas Aktivitas Operasional",
    amount: props.pack.cash_flow.operating_activities,
  },
  {
    name: "Arus Kas Aktivitas Investasi",
    amount: props.pack.cash_flow.investing_activities,
  },
  {
    name: "Arus Kas Aktivitas Pendanaan",
    amount: props.pack.cash_flow.financing_activities,
  },
  {
    name: "Kenaikan / (Penurunan) Kas Bersih",
    amount: props.pack.cash_flow.net_cash_change,
    tone: "tertiary",
  },
  {
    name: "SALDO KAS & BANK AKHIR PERIODE",
    amount: props.pack.cash_flow.ending_cash,
    tone: "secondary",
  },
]);

const equityRows = computed(() => [
  {
    name: "Ekuitas Awal Periode",
    amount: props.pack.equity_changes.opening_equity,
  },
  {
    name: "Laba Bersih Berjalan",
    amount: props.pack.equity_changes.net_income,
  },
  {
    name: "TOTAL EKUITAS AKHIR PERIODE",
    amount: props.pack.equity_changes.ending_equity,
    tone: "secondary",
  },
]);
</script>

<template>
  <Head :title="`Paket 5 Laporan Keuangan - ${province_name}`" />
  <ProvinceLayout>
    <div class="space-y-6">
      <div
        class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">
            Paket Laporan Keuangan Konsolidasi
          </h1>
          <p class="mt-1 text-sm text-on-surface-variant">
            5 Laporan Keuangan Standar Provinsi {{ province_name }} ({{
              pack.period?.period_label
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

      <!-- Tab Buttons -->
      <div class="border-b border-outline-variant pb-3">
        <AppTabs
          v-model="activeTab"
          :items="tabs"
          variant="underline"
          aria-label="Tab paket laporan konsolidasi"
        />
      </div>

      <!-- Content Tab 1: Neraca -->
      <AppCard v-if="activeTab === 'balance'" :padded="false">
        <div class="border-b border-outline-variant px-6 py-4">
          <h2 class="font-bold text-primary text-lg">
            1. NERACA KONSOLIDASI PROVINSI
          </h2>
        </div>
        <div class="p-6">
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

      <!-- Content Tab 2: Laba Rugi -->
      <AppCard v-if="activeTab === 'income'" :padded="false">
        <div class="border-b border-outline-variant px-6 py-4">
          <h2 class="font-bold text-primary text-lg">
            2. LABA RUGI KONSOLIDASI PROVINSI
          </h2>
        </div>
        <div class="p-6">
          <ReportTable
            :columns="incomeStatementColumns"
            :rows="[]"
            :sections="incomeSections"
            size="compact"
          >
            <template #cell-code="{ row }"
              ><span class="font-mono text-xs">{{ row.code }}</span></template
            >
            <template #cell-amount="{ row }"
              ><span :class="row.level === 1 ? 'font-bold' : ''">{{
                money(row.amount)
              }}</span></template
            >
            <template #subtotal="{ section }">{{
              money(section.subtotal)
            }}</template>
          </ReportTable>
        </div>
      </AppCard>

      <!-- Content Tab 3: Arus Kas -->
      <AppCard v-if="activeTab === 'cash'" :padded="false">
        <div class="border-b border-outline-variant px-6 py-4">
          <h2 class="font-bold text-primary text-lg">
            3. LAPORAN ARUS KAS KONSOLIDASI
          </h2>
        </div>
        <div class="p-6">
          <ReportTable
            :columns="cashFlowColumns"
            :rows="cashFlowRows"
            size="compact"
          >
            <template #cell-amount="{ row }">
              <span
                :class="
                  row.tone === 'secondary'
                    ? 'font-bold'
                    : row.tone === 'tertiary'
                      ? 'font-bold'
                      : ''
                "
                >{{ money(row.amount) }}</span
              >
            </template>
          </ReportTable>
        </div>
      </AppCard>

      <!-- Content Tab 4: Perubahan Ekuitas -->
      <AppCard v-if="activeTab === 'equity'" :padded="false">
        <div class="border-b border-outline-variant px-6 py-4">
          <h2 class="font-bold text-primary text-lg">
            4. LAPORAN PERUBAHAN EKUITAS
          </h2>
        </div>
        <div class="p-6">
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

      <!-- Content Tab 5: CALK -->
      <AppCard v-if="activeTab === 'calk'">
        <div class="border-b border-outline-variant pb-4 mb-4">
          <h2 class="font-bold text-primary text-lg">
            5. CATATAN ATAS LAPORAN KEUANGAN (CALK)
          </h2>
        </div>
        <div class="space-y-4 text-sm text-on-surface">
          <div>
            <h3 class="font-bold text-primary">1. Gambaran Umum</h3>
            <p class="mt-1 text-on-surface-variant">
              {{ pack.calk.general_notes }}
            </p>
          </div>
          <div>
            <h3 class="font-bold text-primary">2. Kebijakan Akuntansi</h3>
            <p class="mt-1 text-on-surface-variant">
              {{ pack.calk.accounting_policies }}
            </p>
          </div>
          <div>
            <h3 class="font-bold text-primary">3. Cakupan Konsolidasi</h3>
            <p class="mt-1 text-on-surface-variant">
              Laporan ini mencakup {{ pack.calk.tenants_count }} Unit Pengelola
              Kegiatan se-Provinsi.
            </p>
          </div>
        </div>
      </AppCard>
    </div>
  </ProvinceLayout>
</template>
