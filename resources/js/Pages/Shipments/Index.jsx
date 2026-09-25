import React from 'react';
import { Button, Card, Col, Form, Input, Row, Select, Table, Tag, Typography } from 'antd';
import { Link, router } from '@inertiajs/react';
import StatCard from '../../components/StatCard';
import { STATUS_COLOR, STATUS_LABEL } from '../../lib/constants';
import { fmtDateTime } from '../../lib/format';

/**
 * Master Resi (Alur D, prompt.md §7): pengganti DBMengantar + OutputResi.
 * Pencarian resi / order id / telepon, filter status internal + platform.
 */
export default function Index({ shipments, filters }) {
    const apply = (values) => {
        router.get(
            '/resi',
            {
                q: values.q || undefined,
                status: values.status || undefined,
                platform: values.platform || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const columns = [
        {
            title: 'Resi',
            dataIndex: 'tracking_id',
            width: 180,
            render: (v, s) => (
                <Link href={`/resi/${s.id}`}>
                    <Typography.Text code>{v ?? '—'}</Typography.Text>
                </Link>
            ),
        },
        { title: 'Platform', key: 'platform', width: 110, render: (_, s) => <Typography.Text style={{ textTransform: 'capitalize' }}>{s.platform}</Typography.Text> },
        { title: 'Ekspedisi', dataIndex: 'expedition', width: 110, render: (v) => v ?? '—' },
        { title: 'Penerima', dataIndex: 'customer_name', render: (v) => v ?? '—' },
        { title: 'Telepon', dataIndex: 'customer_phone', width: 130, render: (v) => v ?? '—' },
        { title: 'Status Sistem', dataIndex: 'status_raw', render: (v) => v ?? '—' },
        {
            title: 'Status Internal',
            dataIndex: 'status_internal',
            width: 130,
            render: (v) =>
                v ? <Tag color={STATUS_COLOR[v]}>{STATUS_LABEL[v] ?? v}</Tag> : <Tag color="red">belum dipetakan</Tag>,
        },
        { title: 'Update', dataIndex: 'last_update', width: 130, render: fmtDateTime },
    ];

    return (
        <>
            <Card style={{ marginBottom: 16 }} styles={{ body: { paddingBottom: 12 } }}>
                <Row justify="space-between" align="middle" wrap={false} gutter={[12, 12]}>
                    <Col flex={1} style={{ minWidth: 0 }}>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Master Resi
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Menggantikan <Typography.Text code>DBMengantar</Typography.Text> +{' '}
                            <Typography.Text code>OutputResi</Typography.Text>. Nomor disimpan sebagai string.
                        </Typography.Text>
                    </Col>
                    <Col flex="none">
                        <StatCard title="Total Resi" value={shipments.total} background="#eff6ff" />
                    </Col>
                </Row>
            </Card>

            <Card>
            <Form
                layout="inline"
                onFinish={apply}
                style={{ rowGap: 12, marginBottom: 16 }}
                initialValues={{
                    q: filters.q || '',
                    status: filters.status || undefined,
                    platform: filters.platform || undefined,
                }}
            >
                <Form.Item name="q" style={{ flex: 1, minWidth: 220 }}>
                    <Input allowClear placeholder="resi / order id / telepon" />
                </Form.Item>
                <Form.Item name="status">
                    <Select
                        allowClear
                        placeholder="Semua status"
                        style={{ width: 140 }}
                        options={Object.keys(STATUS_LABEL).map((s) => ({ value: s, label: STATUS_LABEL[s] }))}
                    />
                </Form.Item>
                <Form.Item name="platform">
                    <Select
                        allowClear
                        placeholder="Semua platform"
                        style={{ width: 140 }}
                        options={[
                            { value: 'mengantar', label: 'Mengantar' },
                            { value: 'lincah', label: 'Lincah' },
                        ]}
                    />
                </Form.Item>
                <Form.Item>
                    <Button type="primary" htmlType="submit">
                        Terapkan
                    </Button>
                </Form.Item>
                <Form.Item>
                    <Button onClick={() => router.get('/resi', {}, { preserveState: true, replace: true })}>Reset</Button>
                </Form.Item>
            </Form>

            <Table
                size="small"
                rowKey="id"
                columns={columns}
                dataSource={shipments.data}
                locale={{ emptyText: 'Belum ada resi. Impor file hasil platform untuk mengisi master resi.' }}
                pagination={{
                    current: shipments.current_page,
                    pageSize: shipments.per_page,
                    total: shipments.total,
                    showSizeChanger: false,
                }}
                onChange={(p) =>
                    router.get(
                        '/resi',
                        { q: filters.q, status: filters.status, platform: filters.platform, page: p.current },
                        { preserveState: true, replace: true },
                    )
                }
            />
            </Card>
        </>
    );
}
