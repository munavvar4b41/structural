<script setup lang="ts">
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, watch } from 'vue';
import { parseTipTapDocument } from '@/lib/tiptapDocument';

const props = defineProps<{
    json: string | null;
}>();

const editor = useEditor({
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
        }),
        Link.configure({ openOnClick: true }),
    ],
    content: parseTipTapDocument(props.json),
    editable: false,
    editorProps: {
        attributes: {
            class: 'tiptap-content max-w-none px-3 py-2 text-sm',
        },
    },
});

watch(
    () => props.json,
    (v) => {
        editor.value?.commands.setContent(parseTipTapDocument(v));
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div v-if="editor" class="rounded-md border border-input bg-muted/20">
        <EditorContent :editor="editor" />
    </div>
    <p v-else class="text-sm text-muted-foreground">No description.</p>
</template>

<style scoped>
:deep(.tiptap-content p) {
    margin: 0.35em 0;
}
:deep(.tiptap-content ul),
:deep(.tiptap-content ol) {
    margin: 0.35em 0;
    padding-left: 1.25rem;
    list-style: revert;
}
:deep(.tiptap-content h2) {
    margin: 0.5em 0 0.25em;
    font-size: 1.5em;
    font-weight: 600;
}
:deep(.tiptap-content h3) {
    margin: 0.5em 0 0.25em;
    font-size: 1.17em;
    font-weight: 600;
}
:deep(.tiptap-content a) {
    color: var(--color-primary);
    text-decoration: underline;
}
</style>
