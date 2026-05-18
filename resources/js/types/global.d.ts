import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            companyRegistration: {
                name: string;
                registration_email_domain: string;
            };
            sidebarOpen: boolean;
            flash: {
                toast: string | null;
            };
            active_time_entry: {
                id: number;
                task_id: number;
                project_id: number;
                task_title: string;
                project_name: string;
                project_code: string | null;
                started_at: string;
                is_paused: boolean;
                elapsed_seconds: number;
                task_today_seconds: number;
            } | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
