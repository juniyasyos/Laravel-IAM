import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';

const appName = import.meta.env.VITE_APP_NAME || 'IAM';

export default function render(page: any) {
    return createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.tsx`,
                import.meta.glob<{ default: React.ComponentType }>('./pages/**/*.tsx'),
            ),
        setup: ({ App, props }) => <App { ...props } />,
        title: (title) => `${title} - ${appName}`,
    });
}
