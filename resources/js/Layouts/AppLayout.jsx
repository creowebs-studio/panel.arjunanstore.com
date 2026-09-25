import React, { useEffect, useState } from 'react';
import { App, Avatar, Dropdown, Layout, Menu, Space, Tag, Typography } from 'antd';
import {
    BarChartOutlined,
    BarcodeOutlined,
    DashboardOutlined,
    DollarOutlined,
    ExportOutlined,
    LogoutOutlined,
    NotificationOutlined,
    PlusCircleOutlined,
    SettingOutlined,
    ShoppingCartOutlined,
    SwapOutlined,
    UploadOutlined,
    UserOutlined,
    WarningOutlined,
} from '@ant-design/icons';
import { Link, router, usePage } from '@inertiajs/react';

const { Header, Sider, Content } = Layout;

/**
 * Shell Ant Design untuk seluruh halaman terautentikasi.
 * Item menu difilter berdasarkan peran/izin dari props bersama Inertia
 * (penegakan akses tetap di backend — menu hanya menyembunyikan entri).
 */
export default function AppLayout({ children }) {
    const { auth, flash } = usePage().props;
    const { url } = usePage();
    const { message } = App.useApp();
    const [collapsed, setCollapsed] = useState(false);

    useEffect(() => {
        if (flash?.success) message.success(flash.success);
        if (flash?.error) message.error(flash.error);
    }, [flash]);

    // Halaman tamu (login) dirender tanpa shell.
    if (!auth?.user) return <>{children}</>;

    const u = auth.user;
    const roles = u.roles ?? [];
    const perms = u.permissions ?? [];
    const has = (...r) => r.some((x) => roles.includes(x));
    // Backend (User::hasPermission) mem-bypass superadmin — samakan di menu.
    const can = (p) => roles.includes('superadmin') || perms.includes(p);

    const items = [
        { key: '/dashboard', icon: <DashboardOutlined />, label: 'Dashboard', show: true },
        { key: '/orders', icon: <ShoppingCartOutlined />, label: 'Order', show: true },
        { key: '/orders/baru', icon: <PlusCircleOutlined />, label: 'Input Order', show: has('superadmin', 'admin_order') },
        { key: '/ekspor', icon: <ExportOutlined />, label: 'Siap Ekspor', show: has('superadmin', 'admin_order') },
        { key: '/resi', icon: <BarcodeOutlined />, label: 'Master Resi', show: has('superadmin', 'admin_pengiriman', 'admin_order') },
        { key: '/impor', icon: <UploadOutlined />, label: 'Impor', show: has('superadmin', 'admin_pengiriman') },
        { key: '/pemetaan-status', icon: <SwapOutlined />, label: 'Pemetaan Status', show: can('carriers.mapping.manage') },
        { key: '/data-error', icon: <WarningOutlined />, label: 'Data Error', show: has('superadmin', 'admin_pengiriman') },
        { key: '/marketing', icon: <NotificationOutlined />, label: 'Marketing', show: has('superadmin', 'marketing_adv') },
        { key: '/rekap-adv', icon: <BarChartOutlined />, label: 'Rekap ADV', show: has('superadmin', 'marketing_adv', 'finance_owner') },
        { key: '/komisi', icon: <DollarOutlined />, label: 'Komisi', show: has('superadmin', 'finance_owner') },
        { key: '/aturan-komisi', icon: <SettingOutlined />, label: 'Aturan Komisi', show: has('superadmin', 'finance_owner') },
    ]
        .filter((i) => i.show)
        .map(({ key, icon, label }) => ({ key, icon, label: <Link href={key}>{label}</Link> }));

    const path = (url || '').split('?')[0];
    const selected = items.find((i) => path === i.key || (i.key !== '/' && path.startsWith(i.key)))?.key ?? path;

    return (
        <Layout style={{ minHeight: '100vh' }}>
            <Sider collapsible collapsed={collapsed} onCollapse={setCollapsed} theme="dark" width={232}>
                <div
                    style={{
                        height: 56,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: '#fff',
                        fontWeight: 700,
                        letterSpacing: 0.5,
                        fontSize: 16,
                    }}
                >
                    {collapsed ? 'ARJ' : 'ARJ Panel'}
                </div>
                <Menu theme="dark" mode="inline" selectedKeys={[selected]} items={items} />
            </Sider>
            <Layout>
                <Header
                    style={{
                        background: '#fff',
                        padding: '0 24px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'flex-end',
                        gap: 12,
                        borderBottom: '1px solid #f0f0f0',
                    }}
                >
                    <Dropdown
                        menu={{
                            items: [
                                { key: 'logout', icon: <LogoutOutlined />, label: 'Keluar', onClick: () => router.post('/logout') },
                            ],
                        }}
                    >
                        <Space style={{ cursor: 'pointer' }}>
                            <Avatar size="small" icon={<UserOutlined />} />
                            <Typography.Text strong>{u.name}</Typography.Text>
                            <Tag color="blue">{roles.join(', ') || '—'}</Tag>
                        </Space>
                    </Dropdown>
                </Header>
                <Content style={{ margin: 24 }}>{children}</Content>
            </Layout>
        </Layout>
    );
}
