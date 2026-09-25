import React from 'react';
import { Alert, App, Button, Card, Row, Col, Space, Table, Tag, Tooltip, Typography } from 'antd';
import { Link, router } from '@inertiajs/react';
import StatCard from '../../components/StatCard';

const RESULT_COLOR = { error: 'red', new: 'green', duplicate: 'orange' };

/**
 * Pratinjau Impor (prompt.md §6): hasil baca/validasi file sebelum diproses,
 * atau laporan setelah batch diproses + tombol proses ulang baris error.
 */
export default function Preview({ batch, rows, summary }) {
    const { modal } = App.useApp();
    const confirmProcess = () =>
        modal.confirm({
            title: 'Proses impor dan perbarui master resi?',
            onOk: () => router.post(`/impor/${batch.id}/proses`),
        });

    const confirmReprocess = () =>
        modal.confirm({
            title: 'Proses ulang HANYA baris error? Baris yang sudah berhasil tidak diulang.',
            onOk: () => router.post(`/impor/${batch.id}/proses-ulang`),
        });

    const columns = [
        { title: '#', dataIndex: 'row_number', width: 50 },
        {
            title: 'Resi/Tracking',
            key: 'resi',
            width: 170,
            render: (_, r) => <Typography.Text code>{r.mapped_data?.tracking_id ?? '—'}</Typography.Text>,
        },
        { title: 'Order ID', key: 'oid', width: 120, render: (_, r) => r.mapped_data?.platform_order_id ?? '—' },
        { title: 'Telepon', key: 'telp', width: 130, render: (_, r) => r.mapped_data?.customer_phone ?? '—' },
        { title: 'Status Mentah', key: 'straw', render: (_, r) => r.mapped_data?.status_raw ?? '—' },
        {
            title: 'Status Internal',
            key: 'sinternal',
            width: 160,
            render: (_, r) => {
                const m = r.mapped_data ?? {};
                if (m._unknown_status) {
                    return (
                        <Tooltip title="Belum dipetakan">
                            <Tag color="red">?? {m.status_internal || 'belum dipetakan'}</Tag>
                        </Tooltip>
                    );
                }
                return m.status_internal ?? '—';
            },
        },
        {
            title: 'Remark',
            key: 'remark',
            width: 160,
            render: (_, r) => {
                const m = r.mapped_data ?? {};
                if (m._remark_unmapped) return <Tag color="orange">{m.remark}</Tag>;
                return <Typography.Text code>{m.remark ?? '—'}</Typography.Text>;
            },
        },
        {
            title: 'Hasil',
            key: 'hasil',
            width: 140,
            render: (_, r) => (
                <>
                    <Tag color={RESULT_COLOR[r.result] ?? 'default'}>{r.result}</Tag>
                    {r.result === 'error' && r.error_reason && (
                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                            {r.error_reason}
                        </Typography.Text>
                    )}
                </>
            ),
        },
    ];

    return (
        <>
            <Card style={{ marginBottom: 16 }} styles={{ body: { paddingBottom: 12 } }}>
                <Row justify="space-between" align="middle" wrap={false} gutter={[12, 12]}>
                    <Col flex={1} style={{ minWidth: 0 }}>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Pratinjau: {batch.source_filename}
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Platform <b style={{ textTransform: 'capitalize' }}>{batch.platform}</b> · Status <b>{batch.status}</b> ·{' '}
                            {batch.total_rows} baris terbaca.
                        </Typography.Text>
                    </Col>
                    <Col flex="none">
                        <Link href="/impor">
                            <Button>Kembali ke Impor</Button>
                        </Link>
                    </Col>
                </Row>

                <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
                    <Col xs={24} md={8}>
                        <StatCard title="Terdeteksi Baru" value={summary.new ?? 0} background="#f0fdf4" valueStyle={{ color: '#16a34a' }} />
                    </Col>
                    <Col xs={24} md={8}>
                        <StatCard title="Duplikat/Double Resi" value={summary.duplicate ?? 0} background="#fff7ed" valueStyle={{ color: '#d97706' }} />
                    </Col>
                    <Col xs={24} md={8}>
                        <StatCard title="Baris Error" value={summary.error ?? 0} background="#fef2f2" valueStyle={{ color: '#dc2626' }} />
                    </Col>
                </Row>

                {batch.status !== 'done' ? (
                    <Space style={{ marginTop: 16 }}>
                        <Button type="primary" onClick={confirmProcess}>
                            Konfirmasi &amp; Proses Impor
                        </Button>
                    </Space>
                ) : (
                    <>
                        <Alert
                            style={{ marginTop: 16 }}
                            type="success"
                            showIcon
                            message={`Batch ini sudah diproses — baru: ${batch.new_rows}, diperbarui: ${batch.updated_rows}, duplikat: ${batch.duplicate_rows}, error: ${batch.error_rows}.`}
                        />
                        <Space style={{ marginTop: 16 }} wrap>
                            <Link href="/impor">
                                <Button>Impor lagi</Button>
                            </Link>
                            {batch.error_rows > 0 && (
                                <Button onClick={confirmReprocess}>
                                    Proses Ulang Baris Error ({batch.error_rows})
                                </Button>
                            )}
                        </Space>
                        <Typography.Paragraph type="secondary" style={{ fontSize: 13, marginTop: 8, marginBottom: 0 }}>
                            Baris dipetakan ulang dari data asli — perbaiki dulu{' '}
                            <Link href="/pemetaan-status">pemetaan status</Link> bila penyebabnya status yang belum
                            dikenal, lalu proses ulang.
                        </Typography.Paragraph>
                    </>
                )}
            </Card>

            <Card>
                <Table
                    size="small"
                    rowKey="row_number"
                    columns={columns}
                    dataSource={rows}
                    pagination={false}
                />
                {batch.total_rows > 100 && (
                    <Typography.Paragraph type="secondary" style={{ marginTop: 8, marginBottom: 0 }}>
                        Menampilkan 100 baris pertama.
                    </Typography.Paragraph>
                )}
            </Card>
        </>
    );
}
