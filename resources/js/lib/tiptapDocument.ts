/** TipTap/ProseMirror JSON stored as string in `project_requirements.description`. */
export function emptyTipTapDocumentJson(): string {
    return JSON.stringify({ type: 'doc', content: [] });
}

export function plainTextToTipTapDocJson(text: string): string {
    return JSON.stringify({
        type: 'doc',
        content: [
            {
                type: 'paragraph',
                content: [{ type: 'text', text }],
            },
        ],
    });
}

/** Parse stored JSON or wrap legacy plain text as a single paragraph. */
export function parseTipTapDocument(json: string | null | undefined): Record<string, unknown> {
    if (json === null || json === undefined || json === '') {
        return { type: 'doc', content: [] };
    }

    try {
        const o = JSON.parse(json) as unknown;

        if (o && typeof o === 'object' && (o as { type?: string }).type === 'doc') {
            return o as Record<string, unknown>;
        }
    } catch {
        // legacy plain text
    }

    return {
        type: 'doc',
        content: [
            {
                type: 'paragraph',
                content: [{ type: 'text', text: String(json) }],
            },
        ],
    };
}
