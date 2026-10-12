<script setup lang="ts">
export type TaskPriorityColor =
    | 'red'
    | 'orange'
    | 'amber'
    | 'yellow'
    | 'lime'
    | 'green'
    | 'teal'
    | 'cyan'
    | 'blue'
    | 'indigo'
    | 'purple'
    | 'pink'
    | 'rose'
    | 'gray';

export type TaskPriorityShade = 'light' | 'dark';

export type TaskPriorityBadgeData = {
    id: number;
    name: string;
    color: TaskPriorityColor;
    shade: TaskPriorityShade;
};

const lightClasses: Record<TaskPriorityColor, string> = {
    red: 'border-red-500/25 bg-red-500/15 text-red-700 dark:text-red-300',
    orange: 'border-orange-500/25 bg-orange-500/15 text-orange-700 dark:text-orange-300',
    amber: 'border-amber-500/25 bg-amber-500/15 text-amber-700 dark:text-amber-300',
    yellow: 'border-yellow-500/25 bg-yellow-500/15 text-yellow-700 dark:text-yellow-300',
    lime: 'border-lime-500/25 bg-lime-500/15 text-lime-700 dark:text-lime-300',
    green: 'border-green-500/25 bg-green-500/15 text-green-700 dark:text-green-300',
    teal: 'border-teal-500/25 bg-teal-500/15 text-teal-700 dark:text-teal-300',
    cyan: 'border-cyan-500/25 bg-cyan-500/15 text-cyan-700 dark:text-cyan-300',
    blue: 'border-blue-500/25 bg-blue-500/15 text-blue-700 dark:text-blue-300',
    indigo: 'border-indigo-500/25 bg-indigo-500/15 text-indigo-700 dark:text-indigo-300',
    purple: 'border-purple-500/25 bg-purple-500/15 text-purple-700 dark:text-purple-300',
    pink: 'border-pink-500/25 bg-pink-500/15 text-pink-700 dark:text-pink-300',
    rose: 'border-rose-500/25 bg-rose-500/15 text-rose-700 dark:text-rose-300',
    gray: 'border-gray-500/25 bg-gray-500/15 text-gray-700 dark:text-gray-300',
};

const darkClasses: Record<TaskPriorityColor, string> = {
    red: 'border-transparent bg-red-600 text-white dark:bg-red-500',
    orange: 'border-transparent bg-orange-600 text-white dark:bg-orange-500',
    amber: 'border-transparent bg-amber-600 text-white dark:bg-amber-500',
    yellow: 'border-transparent bg-yellow-500 text-yellow-950',
    lime: 'border-transparent bg-lime-600 text-white dark:bg-lime-500',
    green: 'border-transparent bg-green-600 text-white dark:bg-green-500',
    teal: 'border-transparent bg-teal-600 text-white dark:bg-teal-500',
    cyan: 'border-transparent bg-cyan-600 text-white dark:bg-cyan-500',
    blue: 'border-transparent bg-blue-600 text-white dark:bg-blue-500',
    indigo: 'border-transparent bg-indigo-600 text-white dark:bg-indigo-500',
    purple: 'border-transparent bg-purple-600 text-white dark:bg-purple-500',
    pink: 'border-transparent bg-pink-600 text-white dark:bg-pink-500',
    rose: 'border-transparent bg-rose-600 text-white dark:bg-rose-500',
    gray: 'border-transparent bg-gray-700 text-white dark:bg-gray-500',
};

const colorClasses: Record<TaskPriorityShade, Record<TaskPriorityColor, string>> = {
    light: lightClasses,
    dark: darkClasses,
};

defineProps<{
    priority: TaskPriorityBadgeData | null;
}>();
</script>

<template>
    <span
        v-if="priority !== null"
        class="inline-flex w-fit items-center rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="colorClasses[priority.shade][priority.color]"
    >
        {{ priority.name }}
    </span>
</template>
