import { onMounted, onUnmounted, ref } from 'vue';

const DISMISS_KEY = 'structural-pwa-install-dismissed-at';
const DISMISS_MS = 14 * 24 * 60 * 60 * 1000;

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

function isStandaloneDisplay(): boolean {
    if (window.matchMedia('(display-mode: standalone)').matches) {
        return true;
    }

    const nav = window.navigator as Navigator & { standalone?: boolean };

    return nav.standalone === true;
}

function wasRecentlyDismissed(): boolean {
    const raw = localStorage.getItem(DISMISS_KEY);

    if (!raw) {
        return false;
    }

    const dismissedAt = Number(raw);

    if (Number.isNaN(dismissedAt)) {
        return false;
    }

    return Date.now() - dismissedAt < DISMISS_MS;
}

export function usePwaInstall() {
    const canInstall = ref(false);
    const isInstalled = ref(false);
    let deferredPrompt: BeforeInstallPromptEvent | null = null;

    function syncInstalledState(): void {
        isInstalled.value = isStandaloneDisplay();
        canInstall.value = !isInstalled.value && deferredPrompt !== null;
    }

    function onBeforeInstallPrompt(event: Event): void {
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;

        if (!wasRecentlyDismissed() && !isStandaloneDisplay()) {
            canInstall.value = true;
        }
    }

    function onAppInstalled(): void {
        deferredPrompt = null;
        canInstall.value = false;
        isInstalled.value = true;
        localStorage.removeItem(DISMISS_KEY);
    }

    async function install(): Promise<boolean> {
        if (!deferredPrompt) {
            return false;
        }

        await deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        deferredPrompt = null;
        canInstall.value = false;

        return outcome === 'accepted';
    }

    function dismiss(): void {
        canInstall.value = false;
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
    }

    onMounted(() => {
        syncInstalledState();
        window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
        window.addEventListener('appinstalled', onAppInstalled);
    });

    onUnmounted(() => {
        window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
        window.removeEventListener('appinstalled', onAppInstalled);
    });

    return {
        canInstall,
        isInstalled,
        install,
        dismiss,
    };
}
