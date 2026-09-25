import React from 'react';
import {
    Alert,
    Button,
    Card,
    Col,
    Form,
    Input,
    Row,
    Select,
    Space,
    Table,
    Tag,
    Tooltip,
    Typography,
} from 'antd';
import { Link, router } from '@inertiajs/react';
import {
    CheckCircleOutlined,
    CloseCircleOutlined,
    ExclamationCircleOutlined,
    PlusOutlined,
} from '@ant-design/icons';
import StatCard from '../../components/StatCard';
import { CLASS_COLOR, CLASS_LABEL } from '../../lib/constants';
import { fmtDate } from '../../lib/format';

/**
 * Daftar Order (prompt.md §4): pencarian nomor telepon/kode resi/nama,
 * filter klasifikasi + agregator, kartu hasil klasifikasi dari input terakhir.
 */
export default function Index({ orders, filters, counts, orderResult }) {
    const apply = (values) => {
        router.get(
            '/orders',
            {
                q: values.q || undefined,
                classification: values.classification || undefined,
                aggregator: values.aggregator || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const columns = [
        {
            title: 'Kode Resi',
            dataIndex: 'reference_code',
            width: 170,
            render: (v, o) => (
                <Link href={`/orders/${o.id}`}>
                    <Typography.Text code>{v}</Typography.Text>
                </Link>
            ),
        },
        { title: 'Tgl', dataIndex: 'order_date', width: 100, render: fmtDate },
        {
            title: 'Customer',
            key: 'customer',
            render: (_, o) => (
                <>
                    <div>{o.customer?.name ?? '—'}</div>
                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                        {o.customer_phone_normalized}
                    </Typography.Text>
                </>
            ),
        },
        { title: 'CS', key: 'cs', width: 120, render: (_, o) => o.cs_agent?.name ?? '—' },
        { title: 'Produk', key: 'produk', width: 150, render: (_, o) => o.product?.name ?? o.product_detail ?? '—' },
        { title: 'Agregator', key: 'agg', width: 110, render: (_, o) => <Typography.Text style={{ textTransform: 'capitalize' }}>{o.aggregator}</Typography.Text> },
        { title: 'Bayar', dataIndex: 'payment_method', width: 100 },
        {
            title: 'Status',
            dataIndex: 'classification',
            width: 130,
            render: (v, o) => {
                const last = [...(o.validations ?? [])].sort((a, b) => (b.checked_at ?? '').localeCompare(a.checked_at ?? ''))[0];
                return (
                    <Tooltip title={last?.reason}>
                        <Tag color={CLASS_COLOR[v]} style={{ textTransform: 'capitalize' }}>
                            {CLASS_LABEL[v] ?? v}
                        </Tag>
                    </Tooltip>
                );
            },
        },
    ];

    return (
        <>
            <Card style={{ marginBottom: 16 }} styles={{ body: { paddingBottom: 12 } }}>
                <Row justify="space-between" align="middle" wrap gutter={[12, 12]}>
                    <Col flex="auto">
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Order
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Daftar order &amp; klasifikasi nomor telepon (Alur A). Cari lewat nomor telepon, kode resi, atau nama.
                        </Typography.Text>
                    </Col>
                    <Col>
                        <Link href="/orders/baru">
                            <Button type="primary" icon={<PlusOutlined />}>
                                Input Order Baru
                            </Button>
                        </Link>
                    </Col>
                </Row>
            </Card>

            {orderResult && (
                <Card
                    style={{
                        marginBottom: 16,
                        borderLeft: `5px solid ${
                            orderResult.classification === 'positif' ? '#16a34a' : orderResult.classification === 'negatif' ? '#dc2626' : '#d97706'
                        }`,
                    }}
                >
                    <Space direction="vertical" size={4}>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Hasil Klasifikasi:{' '}
                            <Tag color={CLASS_COLOR[orderResult.classification]}>
                                {String(orderResult.classification).toUpperCase()}
                            </Tag>
                        </Typography.Title>
                        <Typography.Paragraph style={{ margin: 0 }}>{orderResult.reason}</Typography.Paragraph>
                        <Typography.Text type="secondary" style={{ fontSize: 13 }}>
                            Output akhir: <b>{orderResult.final || '—'}</b> · By WA (AP): <b>{orderResult.by_wa || '—'}</b> ·
                            Last order (AK): <b>{orderResult.last_order_verdict || '—'}</b> · Retur: <b>{orderResult.retur_count}</b> ·
                            Diterima: <b>{orderResult.terima_count}</b> · Resi proses: <b>{orderResult.in_progress_resi || '—'}</b>
                        </Typography.Text>
                    </Space>
                </Card>
            )}

            <Card style={{ marginBottom: 16 }}>
                <Row gutter={[16, 16]}>
                    <Col xs={24} sm={8}>
                        <StatCard
                            title="Positif"
                            value={counts.positif}
                            valueStyle={{ color: '#16a34a' }}
                            prefix={<CheckCircleOutlined />}
                        />
                    </Col>
                    <Col xs={24} sm={8}>
                        <StatCard
                            title="Negatif"
                            value={counts.negatif}
                            valueStyle={{ color: '#dc2626' }}
                            prefix={<CloseCircleOutlined />}
                        />
                    </Col>
                    <Col xs={24} sm={8}>
                        <StatCard
                            title="Perlu Ditinjau"
                            value={counts.perlu_ditinjau}
                            valueStyle={{ color: '#d97706' }}
                            prefix={<ExclamationCircleOutlined />}
                        />
                    </Col>
                </Row>
            </Card>

            <Card title="Daftar Order" style={{ marginBottom: 16 }}>
                <Form
                    layout="inline"
                    onFinish={apply}
                    style={{ rowGap: 12, marginBottom: 16 }}
                    initialValues={{
                        q: filters.q || '',
                        classification: filters.classification || undefined,
                        aggregator: filters.aggregator || undefined,
                    }}
                >
                    <Form.Item name="q" style={{ flex: 1, minWidth: 220 }}>
                        <Input allowClear placeholder="No. telepon / kode resi / nama" />
                    </Form.Item>
                    <Form.Item name="classification">
                        <Select
                            allowClear
                            placeholder="Semua status"
                            style={{ width: 160 }}
                            options={['positif', 'negatif', 'perlu_ditinjau'].map((c) => ({ value: c, label: CLASS_LABEL[c] }))}
                        />
                    </Form.Item>
                    <Form.Item name="aggregator">
                        <Select
                            allowClear
                            placeholder="Semua agregator"
                            style={{ width: 150 }}
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
                        <Button onClick={() => router.get('/orders', {}, { preserveState: true, replace: true })}>
                            Reset
                        </Button>
                    </Form.Item>
                </Form>

                <Table
                    size="small"
                    rowKey="id"
                    columns={columns}
                    dataSource={orders.data}
                    locale={{ emptyText: 'Belum ada order.' }}
                    pagination={{
                        current: orders.current_page,
                        pageSize: orders.per_page,
                        total: orders.total,
                        showSizeChanger: false,
                    }}
                    onChange={(p) =>
                        router.get(
                            '/orders',
                            { q: filters.q, classification: filters.classification, aggregator: filters.aggregator, page: p.current },
                            { preserveState: true, replace: true },
                        )
                    }
                />
            </Card>
        </>
    );
}
