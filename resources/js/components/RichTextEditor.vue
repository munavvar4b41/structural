<script setup lang="ts">
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Bold, Heading2, Heading3, Italic, Link2, List, ListOrdered } from 'lucide-vue-next';
import { onBeforeUnmount, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { emptyTipTapDocumentJson, parseTipTapDocument } from '@/lib/tiptapDocument';

const props = withDefaults(
    defineProps<{
        /** Serialized TipTap JSON document */
        modelValue: string | null;
        inputName: string;
        editable?: boolean;
        id?: string;
    }>(),
    {
        editable: true,
        id: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = useEditor({
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: { class: 'text-primary underline' },
        }),
    ],
    content: parseTipTapDocument(props.modelValue),
    editable: props.editable,
    editorProps: {
        attributes: {
            class:
                'tiptap-content max-w-none h-[300px] overflow-y-auto px-3 py-2 text-sm focus:outline-none',
        },
    },
    onUpdate: ({ editor: ed }) => {
        emit('update:modelValue', JSON.stringify(ed.getJSON()));
    },
});

watch(
    () => props.modelValue,
    (v) => {
        const ed = editor.value;

        if (!ed) {
            return;
        }

        const next = JSON.stringify(parseTipTapDocument(v));
        const cur = JSON.stringify(ed.getJSON());

        if (next !== cur) {
            ed.commands.setContent(parseTipTapDocument(v));
        }
    },
);

watch(
    () => props.editable,
    (v) => {
        editor.value?.setEditable(v);
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

function setLink(): void {
    const ed = editor.value;

    if (!ed) {
        return;
    }

    const prev = ed.getAttributes('link').href as string | undefined;
    const url = window.prompt('Link URL', prev ?? 'https://');

    if (url === null) {
        return;
    }

    if (url === '') {
        ed.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    ed.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}
</script>

<template>
    <div :id="id" class="grid">
        <div v-if="editor && editable"
            class="flex flex-wrap gap-0.5 rounded-t-md border border-b-0 border-input bg-muted/30 px-1.5 py-1">
            <Button type="button" variant="ghost" size="icon-sm" class="shrink-0" aria-label="Bold"
                @click="editor.chain().focus().toggleBold().run()">
                <Bold />
            </Button>
            <Button type="button" variant="ghost" size="icon-sm" class="shrink-0" aria-label="Italic"
                @click="editor.chain().focus().toggleItalic().run()">
                <Italic />
            </Button>
            <Button type="button" variant="ghost" size="icon-sm" class="shrink-0" aria-label="Heading 2"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">
                <Heading2 />
            </Button>
            <Button type="button" variant="ghost" size="icon-sm" class="shrink-0" aria-label="Heading 3"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">
                <Heading3 />
            </Button>
            <Button type="button" variant="ghost" size="icon-sm" class="shrink-0" aria-label="Bullet list"
                @click="editor.chain().focus().toggleBulletList().run()">
                <List />
            </Button>
            <Button type="button" variant="ghost" size="icon-sm" class="shrink-0" aria-label="Number list"
                @click="editor.chain().focus().toggleOrderedList().run()">
                <ListOrdered />
            </Button>
            <Button type="button" variant="ghost" size="icon-sm" class="shrink-0" aria-label="Link" @click="setLink">
                <Link2 />
            </Button>
        </div>
        <div class="rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30"
            :class="{ 'rounded-t-none': editor && editable }">
            <EditorContent v-if="editor" :editor="editor" />
        </div>
        <input type="hidden" :name="inputName" :value="modelValue ?? emptyTipTapDocumentJson()" />
    </div>
</template>

<style scoped>
:deep(.tiptap-content p) {
    margin: 0.35em 0;
}

:deep(.tiptap-content p:first-child) {
    margin-top: 0;
}

:deep(.tiptap-content ul),
:deep(.tiptap-content ol) {
    margin: 0.35em 0;
    padding-left: 1.25rem;
    list-style: revert;
}

:deep(.tiptap-content h2) {
    margin: 0.5em 0 0.25em;
    font-size: 1.125rem;
    font-weight: 600;
}

:deep(.tiptap-content h3) {
    margin: 0.5em 0 0.25em;
    font-size: 1rem;
    font-weight: 600;
}

:deep(.tiptap-content a) {
    color: var(--color-primary);
    text-decoration: underline;
}
</style>
