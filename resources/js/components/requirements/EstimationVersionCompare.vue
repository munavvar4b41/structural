<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import FormSelect from '@/components/FormSelect.vue';
import { Label } from '@/components/ui/label';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { show as estimationShow } from '@/routes/admin/projects/requirements/estimation/index';

type VersionHistoryEntry = {
    id: number;
    version: number;
    status: string;
    status_label: string;
    reviewed_at: string | null;
    created_at: string | null;
};

type LineBrief = {
    id: number;
    title: string;
    description: string | null;
    estimated_minutes: number | null;
    phase: number | null;
    sort_order: number;
};

type VersionCompare = {
    from: { id: number; version: number };
    to: { id: number; version: number };
    diff: {
        added: LineBrief[];
        removed: LineBrief[];
        modified: Array<{
            from: LineBrief;
            to: LineBrief;
            changes: Record<string, { from: unknown; to: unknown }>;
        }>;
        summary: {
            added_count: number;
            removed_count: number;
            modified_count: number;
            minutes_from: number;
            minutes_to: number;
            minutes_delta: number;
        };
    };
};

const props = defineProps<{
    projectId: number;
    requirementId: number;
    versionHistory: VersionHistoryEntry[];
    versionCompare: VersionCompare | null;
}>();

const versionOptions = computed(() =>
    props.versionHistory.map((entry) => ({
        value: String(entry.id),
        label: `v${entry.version} · ${entry.status_label}`,
    })),
);

const compareFromId = ref(
    props.versionCompare?.from.id !== undefined
        ? String(props.versionCompare.from.id)
        : props.versionHistory.length > 1
            ? String(props.versionHistory[0]?.id ?? '')
            : '',
);

const compareToId = ref(
    props.versionCompare?.to.id !== undefined
        ? String(props.versionCompare.to.id)
        : props.versionHistory.length > 1
            ? String(props.versionHistory[props.versionHistory.length - 1]?.id ?? '')
            : '',
);

watch(
    () => props.versionCompare,
    (compare) => {
        if (compare === null) {
            return;
        }

        compareFromId.value = String(compare.from.id);
        compareToId.value = String(compare.to.id);
    },
);

function loadCompare(): void {
    if (compareFromId.value === '' || compareToId.value === '' || compareFromId.value === compareToId.value) {
        return;
    }

    router.get(
        estimationShow.url({
            project: props.projectId,
            requirement: props.requirementId,
        }),
        {
            compare_from: compareFromId.value,
            compare_to: compareToId.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['version_compare'],
        },
    );
}

function formatFieldChange(field: string, change: { from: unknown; to: unknown }): string {
    if (field === 'estimated_minutes') {
        const fromMinutes = change.from === null ? '—' : formatTaskMinutes(Number(change.from));
        const toMinutes = change.to === null ? '—' : formatTaskMinutes(Number(change.to));

        return `${fromMinutes} → ${toMinutes}`;
    }

    const fromValue = change.from === null || change.from === '' ? '—' : String(change.from);
    const toValue = change.to === null || change.to === '' ? '—' : String(change.to);

    return `${fromValue} → ${toValue}`;
}
</script>

<template>
    <GlassCard v-if="versionHistory.length > 1" class="flex flex-col gap-4">
        <div>
            <h2 class="text-lg font-semibold">Version comparison</h2>
            <p class="text-sm text-muted-foreground">
                Compare changes between any two estimation versions.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-1">
                <Label class="text-xs text-muted-foreground" for="compare-from-version">From version</Label>
                <FormSelect
                    id="compare-from-version"
                    name="compare_from_version"
                    v-model="compareFromId"
                    :options="versionOptions"
                    placeholder="Select version"
                    exclude-from-submit
                    @update:model-value="loadCompare"
                />
            </div>
            <div class="grid gap-1">
                <Label class="text-xs text-muted-foreground" for="compare-to-version">To version</Label>
                <FormSelect
                    id="compare-to-version"
                    name="compare_to_version"
                    v-model="compareToId"
                    :options="versionOptions"
                    placeholder="Select version"
                    exclude-from-submit
                    @update:model-value="loadCompare"
                />
            </div>
        </div>

        <template v-if="versionCompare">
            <p class="text-sm text-muted-foreground">
                v{{ versionCompare.from.version }} → v{{ versionCompare.to.version }}:
                {{ versionCompare.diff.summary.added_count }} added,
                {{ versionCompare.diff.summary.removed_count }} removed,
                {{ versionCompare.diff.summary.modified_count }} modified.
                Total time:
                {{ formatTaskMinutes(versionCompare.diff.summary.minutes_from) }}
                →
                {{ formatTaskMinutes(versionCompare.diff.summary.minutes_to) }}
                <span v-if="versionCompare.diff.summary.minutes_delta !== 0">
                    ({{ versionCompare.diff.summary.minutes_delta > 0 ? '+' : '' }}{{ formatTaskMinutes(Math.abs(versionCompare.diff.summary.minutes_delta)) }})
                </span>
            </p>

            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead>
                        <tr class="border-b border-border bg-muted/40 text-left">
                            <th class="px-3 py-2 font-medium">Change</th>
                            <th class="px-3 py-2 font-medium">Line</th>
                            <th class="px-3 py-2 font-medium">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="line in versionCompare.diff.added"
                            :key="`added-${line.id}`"
                            class="border-b border-border/60 bg-success/5"
                        >
                            <td class="px-3 py-2 text-success">Added</td>
                            <td class="px-3 py-2 font-medium">{{ line.title }}</td>
                            <td class="px-3 py-2 text-muted-foreground">
                                Phase {{ line.phase ?? '—' }} ·
                                {{ line.estimated_minutes === null ? '—' : formatTaskMinutes(line.estimated_minutes) }}
                            </td>
                        </tr>
                        <tr
                            v-for="line in versionCompare.diff.removed"
                            :key="`removed-${line.id}`"
                            class="border-b border-border/60 bg-destructive/5"
                        >
                            <td class="px-3 py-2 text-destructive">Removed</td>
                            <td class="px-3 py-2 font-medium">{{ line.title }}</td>
                            <td class="px-3 py-2 text-muted-foreground">
                                Phase {{ line.phase ?? '—' }} ·
                                {{ line.estimated_minutes === null ? '—' : formatTaskMinutes(line.estimated_minutes) }}
                            </td>
                        </tr>
                        <tr
                            v-for="(row, index) in versionCompare.diff.modified"
                            :key="`modified-${row.to.id}-${index}`"
                            class="border-b border-border/60 bg-warning/5"
                        >
                            <td class="px-3 py-2 text-warning">Modified</td>
                            <td class="px-3 py-2 font-medium">{{ row.to.title }}</td>
                            <td class="px-3 py-2 text-muted-foreground">
                                <span
                                    v-for="(change, field) in row.changes"
                                    :key="field"
                                    class="mr-3 inline-block"
                                >
                                    {{ field }}: {{ formatFieldChange(String(field), change) }}
                                </span>
                            </td>
                        </tr>
                        <tr
                            v-if="
                                versionCompare.diff.added.length === 0
                                    && versionCompare.diff.removed.length === 0
                                    && versionCompare.diff.modified.length === 0
                            "
                        >
                            <td colspan="3" class="px-3 py-4 text-center text-muted-foreground">
                                No line changes between these versions.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </GlassCard>
</template>
