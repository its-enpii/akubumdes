<script setup>
defineOptions({ inheritAttrs: false });

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: null },
    size: { type: String, default: 'default', validator: (value) => ['compact', 'default'].includes(value) },
    stickyHeader: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'Data belum tersedia' },
    sections: { type: Array, default: null },
});

defineEmits(['row-click']);

const toneClasses = {
    primary: 'bg-secondary-container text-on-secondary-container',
    tertiary: 'bg-surface-container-low font-semibold',
    error: 'bg-error-container text-on-error-container',
    neutral: 'bg-surface-container-low font-semibold',
};

const sizeClasses = {
    compact: 'px-3 py-2',
    default: 'px-4 py-3',
};

function cellClass(column) {
    const alignment = column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left';
    return [alignment, sizeClasses[props.size], column.class].filter(Boolean);
}
</script>

<template>
    <div class="overflow-x-auto" v-bind="$attrs">
        <table class="w-full border-collapse text-left text-sm">
            <thead class="border-b-2 border-primary bg-surface-container-low text-[11px] font-semibold uppercase tracking-[0.08em] text-on-surface-variant">
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        class="border-b-0 font-semibold"
                        :class="cellClass(column)"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <template v-if="sections">
                    <template v-for="section in sections" :key="section.title ?? section.totalLabel">
                        <tr v-if="section.title" :class="toneClasses[section.tone] || 'bg-surface-container-lowest'">
                            <td :colspan="columns.length" class="font-bold" :class="sizeClasses[props.size]">
                                {{ section.title }}
                            </td>
                        </tr>
                        <tr
                            v-for="(row, index) in section.rows || []"
                            :key="rowKey ? row[rowKey] ?? index : index"
                            class="border-b border-outline-variant/70 transition-colors duration-200 hover:bg-surface-container-low/60"
                            :class="row.isSectionTotal && 'bg-surface-container-low font-bold'"
                        >
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="text-on-surface"
                                :class="cellClass(column)"
                            >
                                <slot
                                    :name="`cell-${column.key}`"
                                    :row="row"
                                    :value="row[column.key]"
                                    :index="index"
                                >
                                    <template v-if="row.isSectionTotal && column.key === 'name'">{{ row.sectionLabel }}</template>
                                    <template v-else-if="row.isSectionTotal && column.key === 'balance'">{{ row.sectionValue }}</template>
                                    <template v-else>{{ row[column.key] ?? '—' }}</template>
                                </slot>
                            </td>
                        </tr>
                        <tr v-if="section.subtotalLabel" class="font-bold" :class="toneClasses[section.tone] || 'bg-surface-container-low'">
                            <td :colspan="columns.length - 1" class="text-right" :class="sizeClasses[props.size]">
                                {{ section.subtotalLabel }}
                            </td>
                            <td class="text-right" :class="sizeClasses[props.size]">
                                <slot name="subtotal" :section="section">{{ section.subtotal }}</slot>
                            </td>
                        </tr>
                    </template>
                </template>
                <template v-else>
                    <tr
                        v-for="(row, index) in rows"
                        :key="rowKey ? row[rowKey] ?? index : index"
                        class="border-b border-outline-variant/70 transition-colors duration-200 hover:bg-surface-container-low/60"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="text-on-surface"
                            :class="cellClass(column)"
                        >
                            <slot
                                :name="`cell-${column.key}`"
                                :row="row"
                                :value="row[column.key]"
                                :index="index"
                            >
                                {{ row[column.key] ?? '—' }}
                            </slot>
                        </td>
                    </tr>
                </template>
                <slot name="footer" />
            </tbody>
        </table>
        <p v-if="!sections && !rows.length" class="border-t border-outline-variant px-4 py-6 text-center text-sm text-on-surface-variant">
            {{ emptyTitle }}
        </p>
    </div>
</template>
