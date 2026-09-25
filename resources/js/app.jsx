import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ConfigProvider, App as AntdApp } from 'antd';
import idID from 'antd/locale/id_ID';
import dayjs from 'dayjs';
import 'dayjs/locale/id';
import AppLayout from './Layouts/AppLayout';
import 'antd/dist/reset.css';
import '../css/app.css';

dayjs.locale('id');

createInertiaApp({
    title: (title) => (title ? `${title} · ARJ Panel` : 'ARJ Panel'),
    resolve: async (name) => {
        const page = (await resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx'))).default;
        // @inertiajs/react v2 tidak mendukung opsi defaultLayout; pasang layout via Component.layout.
        page.layout = page.layout ?? ((pageEl) => <AppLayout>{pageEl}</AppLayout>);
        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <ConfigProvider locale={idID} theme={{ token: { colorPrimary: '#2563eb', borderRadius: 8 } }}>
                {/* AntdApp menyediakan konteks untuk message/modal via App.useApp() (lint antd: API statis tak membaca ConfigProvider). */}
                <AntdApp>
                    <App {...props} />
                </AntdApp>
            </ConfigProvider>,
        );
    },
});
