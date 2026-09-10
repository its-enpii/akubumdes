<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref } from "vue";
import { computed } from "vue";
import AppBadge from "../../Components/AppBadge.vue";
import AppButton from "../../Components/AppButton.vue";
import AppCard from "../../Components/AppCard.vue";
import AppIcon from "../../Components/AppIcon.vue";
import RegencyMap from "../../Components/RegencyMap.vue";
import SmartSelect from "../../Components/SmartSelect.vue";
import ReportTable from "../../Components/Reports/ReportTable.vue";
import { useMoney } from "../../composables/useMoney";
import { usePeriodOptions } from "../../composables/usePeriodOptions";
import RegencyLayout from "../../Layouts/RegencyLayout.vue";

const props = defineProps({
  metrics: { type: Object, required: true },
  year: { type: Number, required: true },
  month: { type: Number, required: true },
  regency_name: { type: String, default: "Kabupaten" },
  regency_code: { type: String, default: "" },
  regency_center: {
    type: Object,
    default: () => ({ lat: -7.5, lng: 109.5, zoom: 10 }),
  },
});

const { money } = useMoney();
const { monthOptions, yearOptions } = usePeriodOptions();

const districtColumns = [
  { key: "district_code", label: "Kode" },
  { key: "name", label: "Nama Kecamatan" },
  { key: "turnover", label: "Perputaran Dana", align: "right" },
  { key: "total_assets", label: "Total Aset", align: "right" },
  { key: "cash", label: "Kas & Bank", align: "right" },
  { key: "groups_count", label: "Kelompok", align: "right" },
  { key: "members_count", label: "Anggota", align: "right" },
  { key: "actions", label: "Aksi", align: "center" },
];

const selectedYear = ref(props.year);
const selectedMonth = ref(props.month || "");
const selectedTenantId = ref("");

function applyFilter() {
  router.get(
    "/regency/dashboard",
    {
      year: selectedYear.value,
      month: selectedMonth.value || "",
    },
    { preserveState: true },
  );
}

function onMapSelectTenant(tenantId) {
  selectedTenantId.value = tenantId;
}

function resetTenantFilter() {
  selectedTenantId.value = "";
}
</script>

<template>
  <Head :title="`Dashboard - ${regency_name}`" />
  <RegencyLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div
        class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">
            Dashboard Kabupaten {{ regency_name }}
          </h1>
          <p class="mt-1 text-sm text-on-surface-variant">
            Rekapitulasi dan monitoring keuangan gabungan seluruh kecamatan (UPK
            DBM).
          </p>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3">
          <div class="w-60">
            <SmartSelect
              v-model="selectedMonth"
              :options="monthOptions"
              label="Bulan"
              value-key="value"
              label-key="label"
              hide-label
              @update:model-value="applyFilter"
            />
          </div>

          <div class="w-36">
            <SmartSelect
              v-model="selectedYear"
              :options="yearOptions"
              label="Tahun"
              value-key="value"
              label-key="label"
              hide-label
              @update:model-value="applyFilter"
            />
          </div>

          <AppButton variant="secondary" icon="refresh" @click="applyFilter"
            >Muat Ulang</AppButton
          >
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <!-- Total Perputaran Dana -->
        <AppCard class="relative overflow-hidden">
          <div class="flex items-center justify-between">
            <span class="text-sm font-semibold text-on-surface-variant"
              >Perputaran Dana (YTD)</span
            >
            <AppIcon name="sync_alt" tone="primary" :container-size="9" />
          </div>
          <p class="mt-3 text-2xl font-bold text-primary">
            {{ money(metrics.summary.total_turnover) }}
          </p>
          <p class="mt-1 text-xs text-on-surface-variant">
            Total dana bergulir gabungan
            {{ metrics.summary.total_kecamatans }} Kecamatan
          </p>
        </AppCard>

        <!-- Akumulasi Aset Gabungan -->
        <AppCard class="relative overflow-hidden">
          <div class="flex items-center justify-between">
            <span class="text-sm font-semibold text-on-surface-variant"
              >Akumulasi Aset Gabungan</span
            >
            <AppIcon
              name="account_balance"
              tone="success"
              :container-size="9"
            />
          </div>
          <p class="mt-3 text-2xl font-bold text-primary">
            {{ money(metrics.summary.total_assets) }}
          </p>
          <p class="mt-1 text-xs text-on-surface-variant">
            Kas, Bank, & Investasi Gabungan
          </p>
        </AppCard>

        <!-- Laba Bersih YTD -->
        <AppCard class="relative overflow-hidden">
          <div class="flex items-center justify-between">
            <span class="text-sm font-semibold text-on-surface-variant"
              >Kas & Bank Saat Ini</span
            >
            <AppIcon
              name="account_balance_wallet"
              tone="primary"
              :container-size="9"
            />
          </div>
          <p class="mt-3 text-2xl font-bold text-primary">
            {{ money(metrics.summary.total_cash) }}
          </p>
          <p class="mt-1 text-xs text-on-surface-variant">
            Total kas dan bank pada posisi terakhir.
          </p>
        </AppCard>
      </div>

      <!-- Map Visualization -->
      <RegencyMap
        :kecamatans="metrics.kecamatans"
        :regency-name="regency_name"
        :regency-center="regency_center"
        :year="selectedYear"
        :month="selectedMonth || ''"
        :selected-tenant-id="selectedTenantId"
        @select-tenant="onMapSelectTenant"
      />

      <!-- Quick Links to Consolidated Reports -->
      <AppCard>
        <div
          class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
        >
          <div>
            <h2 class="font-bold text-primary">Laporan Keuangan Konsolidasi</h2>
            <p class="text-xs text-on-surface-variant">
              Buka laporan detail gabungan seluruh kecamatan atau filter per
              kecamatan.
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Link
              :href="`/regency/reports/balance-sheet?year=${selectedYear}&month=${selectedMonth || ''}`"
            >
              <AppButton variant="secondary" icon="account_balance"
                >Neraca</AppButton
              >
            </Link>
            <Link
              :href="`/regency/reports/income-statement?year=${selectedYear}&month=${selectedMonth || ''}`"
            >
              <AppButton variant="secondary" icon="trending_up"
                >Laba Rugi</AppButton
              >
            </Link>
            <Link
              :href="`/regency/reports/general-ledger?year=${selectedYear}&month=${selectedMonth || ''}`"
            >
              <AppButton variant="secondary" icon="menu_book"
                >Buku Besar</AppButton
              >
            </Link>
            <Link
              :href="`/regency/reports/cash-flow?year=${selectedYear}&month=${selectedMonth || ''}`"
            >
              <AppButton variant="secondary" icon="payments"
                >Arus Kas</AppButton
              >
            </Link>
            <Link
              :href="`/regency/reports/calk?year=${selectedYear}&month=${selectedMonth || ''}`"
            >
              <AppButton variant="secondary" icon="description">CALK</AppButton>
            </Link>
          </div>
        </div>
      </AppCard>

      <!-- Table Recap per Kecamatan -->
      <AppCard :padded="false">
        <div
          class="flex items-center justify-between border-b border-outline-variant px-6 py-4"
        >
          <div>
            <h2 class="font-bold text-primary">
              Rekapitulasi Kinerja per Kecamatan
            </h2>
            <p class="text-xs text-on-surface-variant mt-0.5">
              Daftar detail performa keuangan masing-masing kecamatan.
            </p>
          </div>
          <div class="flex items-center gap-2">
            <AppBadge v-if="selectedTenantId" tone="primary-soft">
              Filter Aktif
            </AppBadge>
            <AppButton
              v-if="selectedTenantId"
              variant="ghost"
              size="compact"
              icon="close"
              @click="resetTenantFilter"
            >
              Reset Filter Peta
            </AppButton>
          </div>
        </div>
        <div>
          <ReportTable
            :columns="districtColumns"
            :rows="metrics.kecamatans"
            row-key="tenant_id"
            empty-title="Belum ada kecamatan yang terdaftar pada database shard kabupaten ini."
          >
            <template #cell-district_code="{ row }"
              ><span class="font-mono text-xs">{{
                row.district_code || row.code
              }}</span></template
            >
            <template #cell-name="{ row }"
              ><span class="font-bold text-primary">{{
                row.name
              }}</span></template
            >
            <template #cell-turnover="{ row }"
              ><span class="text-on-surface-variant">{{
                money(row.turnover)
              }}</span></template
            >
            <template #cell-total_assets="{ row }"
              ><span class="text-on-surface-variant">{{
                money(row.total_assets)
              }}</span></template
            >
            <template #cell-cash="{ row }"
              ><span class="font-semibold text-primary">{{
                money(row.cash)
              }}</span></template
            >
            <template #cell-actions="{ row }">
              <Link
                :href="`/regency/reports/balance-sheet?tenant_id=${row.tenant_id}&year=${selectedYear}&month=${selectedMonth || ''}`"
                class="text-xs font-semibold text-primary hover:underline"
              >
                Lihat Neraca →
              </Link>
            </template>
          </ReportTable>
        </div>
      </AppCard>
    </div>
  </RegencyLayout>
</template>
