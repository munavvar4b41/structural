export type PhaseSelectOption = { value: string; label: string };

export function buildPhaseSelectOptions(maxGeneratedPhase: number): PhaseSelectOption[] {
    if (maxGeneratedPhase <= 1) {
        return [];
    }

    return Array.from({ length: maxGeneratedPhase }, (_, index) => {
        const phase = index + 1;

        return {
            value: String(phase),
            label: `Phase ${phase}`,
        };
    });
}

export function requiresPhaseSelection(maxGeneratedPhase: number): boolean {
    return maxGeneratedPhase > 1;
}
