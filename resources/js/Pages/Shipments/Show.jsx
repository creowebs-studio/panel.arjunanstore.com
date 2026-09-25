import React from 'react';
import { Button, Card, Col, Row, Table, Tag, Typography } from 'antd';
import { Link } from '@inertiajs/react';
import StatCard from '../../components/StatCard';
import { CLASS_COLOR, CLASS_LABEL, STATUS_COLOR, STATUS_LABEL } from '../../lib/constants';
import { fmtDateTime, rupiah } from '../../lib/format';

/**
 * Detail Resi (prompt.md §7): informasi lengkap + linimasa status.
 * Riwayat penuh dipertahankan; status terkini = event terbaru.
 */
export default function Show({ shipment: s, events, net_shipping }) {
    const info = (label, value) => (
        <Col xs={12} md={8} style={{ marginBottom: 8 }}>
            <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                {label}
            </Typography.Text>
            <div>{value}</div>
        </Col>
    );

    return (
        <>
            <Card style={{ marginBottom: 16 }} styles={{ body: { paddingBottom: 12 } }}>
                <Row justify="space-between" align="middle" wrap={false} gutter={[12, 12]}>
                    <Col flex={1} style={{ minWidth: 0 }}>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Resi <Typography.Text code>{s.tracking_id ?? '—'}</Typography.Text>
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            <span style={{ textTransform: 'capitalize' }}>{s.platform}</span> · Ekspedisi {s.expedition ?? '—'} ·
                            Order ID platform <Typography.Text code>{s.platform_order_id ?? '—'}</Typography.Text>
                        </Typography.Text>
                    </Col>
                    <Col flex="none">
                        <Link href="/resi">
                            <Button>Kembali ke Master Resi</Button>
                        </Link>
                    </Col>
                </Row>
                <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
                    <Col xs={12} md={6}>
                        <StatCard title="Ongkir (net)" value={rupiah(net_shipping)} background="#f0fdf4" />
                    </Col>
                    <Col xs={12} md={6}>
                        <StatCard title="Return Fee" value={rupiah(s.return_fee)} background="#fff7ed" />
                    </Col>
                    <Col xs={12} md={6}>
                        <StatCard title="COD" value={rupiah(s.cod_value)} />
                    </Col>
                    <Col xs={12} md={6}>
                        <StatCard title="Nilai Produk" value={rupiah(s.product_value)} />
                    </Col>
                </Row>
            </Card>

            <Card style={{ marginBottom: 16 }}>
                <Row>
                    {info('Penerima', s.customer_name ?? '—')}
                    {info('Telepon', s.customer_phone ?? '—')}
                    {info(
                        'Terkait Order',
                        s.order ? (
                            <>
                                <Typography.Text code>{s.order.reference_code}</Typography.Text>{' '}
                                <Tag color={CLASS_COLOR[s.order.classification]}>{CLASS_LABEL[s.order.classification] ?? s.order.classification}</Tag>
                            </>
                        ) : (
                            '—'
                        ),
                    )}
                    {info('Alamat', s.address ?? '—')}
                    {info('Kec / Kel', `${s.district ?? '—'} / ${s.subdistrict ?? '—'}`)}
                    {info('Kode ADV/CS/Produk', `${s.adv_resi_code ?? '—'} · ${s.cs_resi_code ?? '—'} · ${s.product_resi_code ?? '—'}`)}
                </Row>
            </Card>

            <Card title="Linimasa Status" style={{ marginBottom: 16 }}>
                <Typography.Paragraph type="secondary" style={{ marginTop: -8 }}>
                    Riwayat penuh dipertahankan; status terkini = event terbaru (impor data lama tidak membalik status baru).
                </Typography.Paragraph>
                <Table
                    size="small"
                    rowKey="id"
                    pagination={false}
                    locale={{ emptyText: 'Belum ada event status.' }}
                    dataSource={events}
                    columns={[
                        { title: 'Tanggal Status', dataIndex: 'status_date', width: 140, render: (v) => fmtDateTime(v) },
                        { title: 'Status Sistem', dataIndex: 'status_raw', render: (v) => v ?? '—' },
                        {
                            title: 'Status Internal',
                            dataIndex: 'status_internal',
                            width: 130,
                            render: (v) =>
                                v ? <Tag color={STATUS_COLOR[v]}>{STATUS_LABEL[v] ?? v}</Tag> : <Tag color="red">belum</Tag>,
                        },
                        { title: 'POD', dataIndex: 'pod_status', render: (v) => v ?? '—' },
                        { title: 'Ongkir', dataIndex: 'shipping_fee', width: 110, align: 'right', render: (v) => rupiah(v ?? 0) },
                        { title: 'Sumber', key: 'sumber', render: (_, e) => (e.import_row_id ? `impor #${e.import_row_id}` : 'manual') },
                    ]}
                />
            </Card>
        </>
    );
}
