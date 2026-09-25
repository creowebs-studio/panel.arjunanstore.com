import React from 'react';
import { Button, Card, Col, Form, Input, Row, Select, Space, Table, Tag, Typography } from 'antd';
import { Link, useForm, usePage } from '@inertiajs/react';
import { CLASS_COLOR, CLASS_LABEL, STATUS_COLOR, STATUS_LABEL } from '../../lib/constants';
import { fmtDate, fmtDateTime, rupiah } from '../../lib/format';

/**
 * Detail Order (prompt.md §4.6): informasi lengkap, koreksi klasifikasi
 * ber-audit (superadmin/admin_order), riwayat validasi, resi + event status,
 * linimasa ekspor & audit.
 */
export default function Show({ order, exportable, shipments }) {
    const { auth } = usePage().props;
    const roles = auth?.user?.roles ?? [];
    const canEdit = roles.includes('superadmin') || roles.includes('admin_order');

    const { data, setData, post, processing, errors } = useForm({
        classification: order.classification,
        reason: '',
    });

    const cls = order.classification;
    const validations = [...(order.validations ?? [])].sort((a, b) => (b.checked_at ?? '').localeCompare(a.checked_at ?? ''));
    const auditLogs = [...(order.audit_logs ?? [])].sort((a, b) => (b.created_at ?? '').localeCompare(a.created_at ?? ''));

    const timeline = [
        ...(order.export_batch_items ?? []).map((i) => ({
            key: `exp-${i.id}`,
            time: i.created_at,
            event: `Ekspor ${String(i.export_batch?.platform ?? '—')}`,
            detail: `${i.export_batch?.filename ?? '—'}${i.export_batch?.is_reexport ? ' · ekspor ulang' : ''}`,
            by: i.export_batch?.user?.name ?? '—',
        })),
        ...auditLogs.map((l) => ({
            key: `log-${l.id}`,
            time: l.created_at,
            event: l.action,
            detail: l.reason,
            by: l.user?.name ?? '—',
        })),
    ];

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
                            Order <Typography.Text code>{order.reference_code}</Typography.Text>{' '}
                            <Tag color={CLASS_COLOR[cls]} style={{ textTransform: 'capitalize' }}>
                                {CLASS_LABEL[cls]}
                            </Tag>
                            {order.is_manual_override && <Tag color="orange">KOREKSI MANUAL</Tag>}
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Tanggal {fmtDate(order.order_date)} · Agregator {String(order.aggregator).charAt(0).toUpperCase() + String(order.aggregator).slice(1)} ·
                            {order.payment_method} · {rupiah(order.price)} · {order.qty} pcs
                        </Typography.Text>
                    </Col>
                    <Col flex="none">
                        <Link href="/orders">
                            <Button>Kembali ke Daftar Order</Button>
                        </Link>
                    </Col>
                </Row>
                <Row>
                    {info('Customer', order.customer?.name ?? '—')}
                    {info('Telepon (ternormalisasi)', order.customer_phone_normalized)}
                    {info('CS / ADV', `${order.cs_agent?.name ?? '—'} / ${order.cs_agent?.advertiser?.name ?? '—'}`)}
                    {info('Produk', order.product?.name ?? order.product_detail ?? '—')}
                    {info('Alamat', order.address_detail ?? '—')}
                    {info('Kel / Kec / Kota', `${order.kelurahan ?? '—'} / ${order.kecamatan ?? '—'} / ${order.kota ?? '—'}`)}
                    {info('Provinsi / Kode Pos', `${order.provinsi ?? '—'} / ${order.zip_code ?? '—'}`)}
                    {info('Ekspedisi', order.expedition ?? '—')}
                    {info('Siap Ekspor?', exportable ? 'Ya — lolos gerbang positif + data lengkap' : 'Belum')}
                </Row>
            </Card>

            {order.is_manual_override && (
                <Card style={{ marginBottom: 16, borderLeft: '5px solid #d97706' }}>
                    <Typography.Title level={5} style={{ marginTop: 0 }}>
                        Koreksi Manual
                    </Typography.Title>
                    <Typography.Paragraph style={{ marginBottom: 4 }}>{order.override_reason}</Typography.Paragraph>
                    <Typography.Text type="secondary" style={{ fontSize: 13 }}>
                        oleh {order.override_by?.name ?? '—'} · {fmtDateTime(order.override_at)}
                    </Typography.Text>
                </Card>
            )}

            {canEdit && (
                <Card title="Koreksi Klasifikasi (ber-audit)" style={{ marginBottom: 16 }}>
                    <Form
                        layout="inline"
                        onFinish={() => post(`/orders/${order.id}/koreksi`)}
                        style={{ rowGap: 12 }}
                        initialValues={{ classification: cls, reason: '' }}
                    >
                        <Form.Item
                            label="Klasifikasi Baru"
                            name="classification"
                            rules={[{ required: true, message: 'Pilih klasifikasi' }]}
                        >
                            <Select
                                style={{ width: 180 }}
                                value={data.classification}
                                onChange={(v) => setData('classification', v)}
                                options={['positif', 'negatif', 'perlu_ditinjau'].map((c) => ({ value: c, label: CLASS_LABEL[c] }))}
                            />
                        </Form.Item>
                        <Form.Item
                            label="Alasan (wajib, tercatat audit)"
                            name="reason"
                            validateStatus={errors.reason ? 'error' : undefined}
                            help={errors.reason}
                            rules={[{ required: true, min: 5, message: 'Minimal 5 karakter' }]}
                            style={{ flex: 1, minWidth: 260 }}
                        >
                            <Input
                                maxLength={255}
                                placeholder="mis. pelanggan konfirmasi ganti nomor"
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                            />
                        </Form.Item>
                        <Form.Item>
                            <Button type="primary" htmlType="submit" loading={processing}>
                                Simpan Koreksi
                            </Button>
                        </Form.Item>
                    </Form>
                </Card>
            )}

            <Card title="Riwayat Validasi Nomor" style={{ marginBottom: 16 }}>
                <Table
                    size="small"
                    rowKey="id"
                    pagination={false}
                    locale={{ emptyText: 'Belum ada validasi.' }}
                    dataSource={validations}
                    columns={[
                        { title: 'Waktu', dataIndex: 'checked_at', width: 130, render: fmtDateTime },
                        { title: 'Hasil', dataIndex: 'result' },
                        { title: 'AK (last order)', dataIndex: 'last_order_status', width: 120, render: (v) => v ?? '—' },
                        { title: 'AP (by WA)', dataIndex: 'by_wa', width: 90, render: (v) => v ?? '—' },
                        { title: 'Retur', dataIndex: 'retur_count', width: 70 },
                        { title: 'Diterima', dataIndex: 'terima_count', width: 80 },
                        {
                            title: 'Alasan',
                            dataIndex: 'reason',
                            render: (v) => <Typography.Text type="secondary">{v}</Typography.Text>,
                        },
                        { title: 'Aturan', key: 'aturan', width: 100, render: (_, v) => v.rule_version?.version ?? '—' },
                    ]}
                />
            </Card>

            <Card title="Resi & Riwayat Status (impor hasil platform)" style={{ marginBottom: 16 }}>
                {shipments.length === 0 ? (
                    <Typography.Text type="secondary">
                        Belum ada resi untuk order ini. Impor hasil platform akan menautkan resi lewat Remark1 = kode resi.
                    </Typography.Text>
                ) : (
                    shipments.map((s) => (
                        <div key={s.id} style={{ border: '1px solid #f0f0f0', borderRadius: 8, padding: 12, marginBottom: 12 }}>
                            <div>
                                <Link href={`/resi/${s.id}`}>
                                    <Typography.Text code>{s.tracking_id ?? '—'}</Typography.Text>
                                </Link>
                                {' · '}
                                <Typography.Text style={{ textTransform: 'capitalize' }}>{s.platform}</Typography.Text>
                                {' · saat ini: '}
                                {s.status_internal ? (
                                    <Tag color={STATUS_COLOR[s.status_internal]}>{STATUS_LABEL[s.status_internal] ?? s.status_internal}</Tag>
                                ) : (
                                    <Tag color="red">belum dipetakan</Tag>
                                )}
                                {' · ongkir net '}
                                {rupiah(s.net_shipping_cost)}
                            </div>
                            <Table
                                size="small"
                                style={{ marginTop: 8 }}
                                rowKey="id"
                                pagination={false}
                                dataSource={s.status_events ?? []}
                                columns={[
                                    { title: 'Tanggal', dataIndex: 'status_date', width: 150, render: (v) => fmtDateTime(v) },
                                    { title: 'Status Sistem', dataIndex: 'status_raw', render: (v) => v ?? '—' },
                                    { title: 'Internal', dataIndex: 'status_internal', render: (v) => v ?? 'belum dipetakan' },
                                ]}
                            />
                        </div>
                    ))
                )}
            </Card>

            <Card title="Linimasa Ekspor & Audit" style={{ marginBottom: 16 }}>
                <Table
                    size="small"
                    rowKey="key"
                    pagination={false}
                    locale={{ emptyText: 'Belum ada peristiwa ekspor/koreksi.' }}
                    dataSource={timeline}
                    columns={[
                        { title: 'Waktu', dataIndex: 'time', width: 140, render: fmtDateTime },
                        { title: 'Peristiwa', dataIndex: 'event' },
                        {
                            title: 'Rincian',
                            dataIndex: 'detail',
                            render: (v) => <Typography.Text type="secondary">{v}</Typography.Text>,
                        },
                        { title: 'Oleh', dataIndex: 'by', width: 150 },
                    ]}
                />
            </Card>

            <Space>
                <Link href="/orders">
                    <Button>← Kembali ke Daftar Order</Button>
                </Link>
            </Space>
        </>
    );
}
