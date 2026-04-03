import { AppPageProps } from '@/types/index';

// Extend ImportMeta interface for Vite with React
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: {
            <T = Record<string, unknown>>(
                glob: string,
                options?: { eager?: boolean }
            ): Record<string, () => Promise<T>> | Record<string, T>;
        };
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps, AppPageProps {}
}
