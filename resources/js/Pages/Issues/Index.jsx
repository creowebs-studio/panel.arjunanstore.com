import React from 'react';
import { Button, Card, Col, Form, Input, Row, Select, Space, Table, Tag, Typography } from 'antd';
import { Link, router, useForm } from '@inertiajs/react';
import StatCard from '../../components/StatCard';
import { fmtDate } from '../../lib/format';

const ISSUE_COLOR = { open: 'red', resolved: 'green', ignored: 'orange' };
const TYPES = ['status_unmapped', 'remark_unmapped', 'required_missing', 'double_resi', 'not_found'];

/** Aksi resolusi per baris (Selesai/Abaikan dengan catatan, tercatat audit). */
function IssueAction({ issue }) {
    const { data, setData, patch, processing } = useForm({ action: '', note: '' });

    return (
        <Form layout="inline" size="small" style={{ rowGap: 4 }}>
            <Form.Item style={{ marginBottom: 0 }}>
                <Input
                    style={{ width: 120 }}
                    placeholder="catatan"
                    value={data.note}
                    onChange={(e) => setData('note', e.target.value)}
                />
            </Form.Item>
            <Form.Item style={{ marginBottom: 0 }}>
                <Button loading={processing && data.action === 'resolved'} onClick={() => patch(`/data-error/${issue.id}`, { action: 'resolved', note: data.note })}>
                    Selesai
                </Button>
            </Form.Item>
            <Form.Item style={{ marginBottom: 0 }}>
                <Button loading={processing && data.action === 'ignored'} onClick={() => patch(`/data-error/${issue.id}`, { action: 'ignored', note: data.note })}>
                    Abaikan
                </Button>
            </Form.Item>
        </Form>
    );
}

/**
 * Data Error (prompt.md §6/§7.3): baris bermasalah dari impor + resolusi tercatat.
 */
export default function Index({ issues, open, filters }) {
    const apply = (values) => {
        router.get(
            '/data-error',
            { status: values.status || undefined, type: values.type || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Card style={{ marginBottom: 16 }} styles={{ body: { paddingBottom: 12 } }}>
                <Row justify="space-between" align="middle" wrap={false} gutter={[12, 12]} style={{ marginBottom: 16 }}>
                    <Col flex={1} style={{ minWidth: 0 }}>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Data Error
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Baris bermasalah dari impor yang perlu direview. Resolusi (selesai/abaikan) tercatat audit.
                        </Typography.Text>
                    </Col>
                    <Col flex="none">
                        <Space size={12}>
                            <StatCard title="Terbuka" value={open} background="#fef2f2" valueStyle={{ color: '#dc2626' }} />
                            <StatCard title="Total" value={issues.total} />
                        </Space>
                    </Col>
                </Row>
                <Form
                    layout="inline"
                    onFinish={apply}
                    style={{ rowGap: 12 }}
                    initialValues={{ status: filters.status || undefined, type: filters.type || undefined }}
                >
                    <Form.Item name="status">
                        <Select
                            allowClear
                            placeholder="Hanya Terbuka"
                            style={{ width: 160 }}
                            options={[
                                { value: 'open', label: 'Open' },
                                { value: 'resolved', label: 'Resolved' },
                                { value: 'ignored', label: 'Ignored' },
                            ]}
                        />
                    </Form.Item>
                    <Form.Item name="type">
                        <Select
                            allowClear
                            placeholder="Semua jenis"
                            style={{ width: 200 }}
                            options={TYPES.map((t) => ({ value: t, label: t.replace(/_/g, ' ') }))}
                        />
                    </Form.Item>
                    <Form.Item>
                        <Button type="primary" htmlType="submit">
                            Terapkan
                        </Button>
                    </Form.Item>
                </Form>
            </Card>

            <Card>
                <Table
                    size="small"
                    rowKey="id"
                    dataSource={issues.data}
                    locale={{ emptyText: 'Tidak ada data error. 🎉' }}
                    pagination={{
                        current: issues.current_page,
                        pageSize: issues.per_page,
                        total: issues.total,
                        showSizeChanger: false,
                    }}
                    onChange={(p) =>
                        router.get(
                            '/data-error',
                            { status: filters.status, type: filters.type, page: p.current },
                            { preserveState: true, replace: true },
                        )
                    }
                    columns={[
                        {
                            title: 'Jenis',
                            dataIndex: 'type',
                            width: 160,
                            render: (v) => <Tag color="orange">{v.replace(/_/g, ' ')}</Tag>,
                        },
                        { title: 'Pesan', dataIndex: 'message' },
                        {
                            title: 'Resi',
                            key: 'resi',
                            width: 160,
                            render: (_, i) =>
                                i.shipment ? (
                                    <Link href={`/resi/${i.shipment.id}`}>
                                        <Typography.Text code>{i.shipment.tracking_id}</Typography.Text>
                                    </Link>
                                ) : (
                                    '—'
                                ),
                        },
                        {
                            title: 'Batch/Baris',
                            key: 'batch',
                            width: 180,
                            render: (_, i) =>
                                `${i.import_row?.batch?.source_filename ?? '—'} #${i.import_row?.row_number ?? '—'}`,
                        },
                        {
                            title: 'Status',
                            dataIndex: 'status',
                            width: 100,
                            render: (v) => <Tag color={ISSUE_COLOR[v] ?? 'default'}>{v}</Tag>,
                        },
                        {
                            title: 'Aksi',
                            key: 'aksi',
                            width: 260,
                            render: (_, i) =>
                                i.status === 'open' ? (
                                    <IssueAction issue={i} />
                                ) : (
                                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                        {i.resolved_by?.name ?? '—'} · {fmtDate(i.resolved_at)}
                                    </Typography.Text>
                                ),
                        },
                    ]}
                />
            </Card>
        </>
    );
}
