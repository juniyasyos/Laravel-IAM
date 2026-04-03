import './styles/index.css';

import ReactDOM from 'react-dom/client';
import { StrictMode } from 'react';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = import.meta.env.VITE_APP_NAME || 'IAM';

createInertiaApp({
  title: (title) => `${title} - ${appName}`,

  resolve: (name) =>
    resolvePageComponent(
      `./pages/${name}.tsx`,
      import.meta.glob('./pages/**/*.tsx')
    ),

  setup({ el, App, props }) {
    const root = ReactDOM.createRoot(el);

    root.render(
      <StrictMode>
        <App {...props} />
      </StrictMode>
    );
  },

  progress: {
    color: '#4f46e5',
  },
});