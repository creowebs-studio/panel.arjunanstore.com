import React from 'react';
import { Button, Card, Col, DatePicker, Form, Row, Space, Table, Tag, Typography } from 'antd';
import dayjs from 'dayjs';
import { router } from '@inertiajs/react';
import StatCard from '../../components/StatCard';
import { fmtDate, fmtDateTime } from '../../lib/format';

/**
 * Siap Ekspor (Alur B, prompt.md §5): daftar order Positif dengan data lengkap
 * per agregator + tombol unduh CSV. Ekspor ulang ditandai di riwayat batch.
 */
export default function Index({ mengantar, lincah, batches, filters }) {
    const apply = (values) => {
        router.get(
            '/ekspor',
            {
                from: values.from ? values.from.format('YYYY-MM-DD') : undefined,
                to: values.to ? values.to.format('YYYY-MM-DD') : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const qs = new URLSearchParams();
    if (filters.from) qs.set('from', filters.from);
    if (filters.to) qs.set('to', filters.to);
    const q = qs.toString();

    const card = (platform, label, rows) => {
        const columns = [
            { title: 'Kode', dataIndex: 'reference_code', width: 180, render: (v) => <Typography.Text code>{v}</Typography.Text> },
            { title: 'Tujuan', key: 'tujuan', render: (_, o) => o.customer?.name ?? '—' },
            { title: 'Telepon', dataIndex: 'customer_phone_normalized', width: 140 },
            { title: 'Qty', dataIndex: 'qty', width: 60, align: 'right' },
        ];

        return (
            <Col xs={24} lg={12}>
                <Card
                    title={label}
                    extra={
                        <Button
                            type={rows.length ? 'primary' : 'default'}
                            href={`/ekspor/${platform}${q ? `?${q}` : ''}`}
                        >
                            Unduh CSV ({rows.length})
                        </Button>
                    }
                >
                    <Typography.Text type="secondary">{rows.length} order siap diekspor.</Typography.Text>
                    <Table
                        size="small"
                        style={{ marginTop: 8 }}
                        rowKey="id"
                        columns={columns}
                        dataSource={rows}
                        pagination={false}
                        locale={{ emptyText: '— kosong —' }}
                    />
                </Card>
            </Col>
        );
    };

    return (
        <>
            <Card style={{ marginBottom: 16 }} styles={{ body: { paddingBottom: 12 } }}>
                <Row justify="space-between" align="middle" wrap={false} gutter={[12, 12]} style={{ marginBottom: 16 }}>
                    <Col flex={1} style={{ minWidth: 0 }}>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Siap Ekspor
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Hanya order <b>Positif</b> dengan data wajib lengkap yang dapat diunduh. Negatif/tidak lengkap tidak ikut.
                        </Typography.Text>
                    </Col>
                    <Col flex="none">
                        <Space size={12}>
                            <StatCard title="Siap Mengantar" value={mengantar.length} background="#eff6ff" />
                            <StatCard title="Siap Lincah" value={lincah.length} background="#f0fdf4" />
                            <StatCard title="Riwayat Batch" value={batches.length} />
                        </Space>
                    </Col>
                </Row>
                <Form
                    layout="inline"
                    onFinish={apply}
                    style={{ rowGap: 12 }}
                    initialValues={{
                        from: filters.from ? dayjs(filters.from) : null,
                        to: filters.to ? dayjs(filters.to) : null,
                    }}
                >
                    <Form.Item label="Dari" name="from">
                        <DatePicker style={{ width: 140 }} />
                    </Form.Item>
                    <Form.Item label="Sampai" name="to">
                        <DatePicker style={{ width: 140 }} />
                    </Form.Item>
                    <Form.Item>
                        <Button type="primary" htmlType="submit">
                            Filter
                        </Button>
                    </Form.Item>
                    <Form.Item>
                        <Button onClick={() => router.get('/ekspor', {}, { preserveState: true, replace: true })}>
                            Reset
                        </Button>
                    </Form.Item>
                </Form>
            </Card>

            <Row gutter={16}>
                {card('mengantar', 'Mengantar', mengantar)}
                {card('lincah', 'Lincah', lincah)}
            </Row>

            <Card title="Batch Ekspor Terakhir" style={{ marginTop: 16 }}>
                <Table
                    size="small"
                    rowKey="id"
                    pagination={false}
                    locale={{ emptyText: 'Belum ada ekspor.' }}
                    dataSource={batches}
                    columns={[
                        { title: 'Waktu', dataIndex: 'created_at', width: 130, render: fmtDateTime },
                        { title: 'Platform', key: 'platform', width: 100, render: (_, b) => <Typography.Text style={{ textTransform: 'capitalize' }}>{b.platform}</Typography.Text> },
                        { title: 'File', dataIndex: 'filename', render: (v) => <Typography.Text code>{v}</Typography.Text> },
                        { title: 'Jml', dataIndex: 'order_count', width: 60, align: 'right' },
                        { title: 'Oleh', key: 'oleh', width: 130, render: (_, b) => b.user?.name ?? '—' },
                        {
                            title: 'Status',
                            key: 'status',
                            width: 110,
                            render: (_, b) => (b.is_reexport ? <Tag color="orange">Re-export</Tag> : <Tag color="green">{b.status}</Tag>),
                        },
                    ]}
                />
            </Card>

            <Space style={{ marginTop: 16 }}>
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                    Unduh memakai filter tanggal yang sama dengan tabel di atas.
                </Typography.Text>
            </Space>
        </>
    );
}
