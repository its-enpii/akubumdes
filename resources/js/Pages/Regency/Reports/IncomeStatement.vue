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

const baseUrl = "/regency/reports/income-statement";

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

const consolidated = computed(
  () =>
    props.report.is_consolidated && (props.report.kecamatans || []).length > 1,
);

const incomeColumns = computed(() => [
  { key: "code", label: "Kode" },
  { key: "name", label: "Nama Rekening" },
  { key: "prior", label: `s.d. ${props.report.header_lalu}`, align: "right" },
  { key: "current", label: props.report.header_sekarang, align: "right" },
  ...(consolidated.value
    ? props.report.kecamatans.map((k) => ({
        key: `tenant_${k.id}`,
        label: k.name,
        align: "right",
      }))
    : []),
  { key: "ytd", label: `s.d. ${props.report.header_sekarang}`, align: "right" },
]);

const incomeRows = computed(
  () =>
    props.report.groups?.flatMap((group) => [
      {
        row_id: `group-${group.code}`,
        isGroup: true,
        code: group.code,
        name: group.name,
        prior: group.prior,
        current: group.current,
        ytd: group.ytd,
      },
      ...group.children.map((row) => ({ ...row, row_id: row.row_id })),
    ]) ?? [],
);
</script>

<template>
  <Head :title="`Laba Rugi Konsolidasi - ${regency_name}`" />
  <RegencyLayout>
    <div class="space-y-6">
      <!-- Header & Filters -->
      <div
        class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">
            Laba Rugi Konsolidasi Kabupaten {{ regency_name }}
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
          pdf-url="/regency/reports/income-statement/pdf"
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
            >Pendapatan Operasional (YTD)</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.summary?.revenue_ops?.ytd) }}
          </p>
        </AppCard>
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Beban Operasional (YTD)</span
          >
          <p class="mt-2 text-2xl font-bold text-primary">
            {{ money(report.summary?.expense_ops?.ytd) }}
          </p>
        </AppCard>
        <AppCard>
          <span class="text-sm font-semibold text-on-surface-variant"
            >Laba (Rugi) Bersih (YTD)</span
          >
          <p
            class="mt-2 text-2xl font-bold"
            :class="
              (report.summary?.after_tax?.ytd || 0) >= 0
                ? 'text-primary'
                : 'text-error'
            "
          >
            {{ money(report.summary?.after_tax?.ytd) }}
          </p>
        </AppCard>
      </div>

      <!-- Table -->
      <AppCard :padded="false">
        <div class="overflow-x-auto">
          <ReportTable
            :columns="incomeColumns"
            :rows="incomeRows"
            size="compact"
            row-key="row_id"
          >
            <template #cell-code="{ row }"
              ><span class="font-mono text-xs">{{ row.code }}</span></template
            >
            <template #cell-name="{ row }"
              ><span :class="row.isGroup ? 'font-bold text-primary' : ''">{{
                row.name
              }}</span></template
            >
            <template #cell-prior="{ row }">{{ money(row.prior) }}</template>
            <template #cell-current="{ row }">{{
              money(row.current)
            }}</template>
            <template #cell-ytd="{ row }"
              ><span :class="row.isGroup ? 'font-bold text-primary' : ''">{{
                money(row.ytd)
              }}</span></template
            >
            <template
              v-for="kec in consolidated ? report.kecamatans : []"
              :key="kec.id"
              #[`cell-tenant_${kec.id}`]="{ row }"
            >
              {{ row.tenants?.[kec.id] ? money(row.tenants[kec.id]) : "—" }}
            </template>
          </ReportTable>
        </div>
      </AppCard>
    </div>
  </RegencyLayout>
</template>
