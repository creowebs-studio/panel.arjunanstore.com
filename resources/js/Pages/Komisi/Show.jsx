import React from 'react';
import { App, Button, Card, Col, DatePicker, Form, Input, InputNumber, Row, Space, Statistic, Table, Tag, Typography } from 'antd';
import dayjs from 'dayjs';
import { Link, router, useForm } from '@inertiajs/react';
import { fmtDate, fmtDateTime, rupiah } from '../../lib/format';

const COMPONENT_LABELS = {
    order: 'Order',
    transfer: 'Transfer',
    ongkir: 'Ongkir',
    multi_paket: 'Multi Paket',
    admin_input: 'Input Admin',
};

/**
 * Detail Komisi (prompt.md §9): ringkasan statistik, pembayaran, entri komisi.
 * Periode OPEN dapat dihitung ulang / ditutup; penutupan memindahkan sisa
 * belum dibayar sebagai saldo periode berikutnya (Z/U11).
 */
export default function Show({ period, owner, stats, entries, payments }) {
    const { modal } = App.useApp();
    const payForm = useForm({
        paid_date: dayjs().format('YYYY-MM-DD'),
        amount: stats.unpaid > 0 ? stats.unpaid : null,
        reference: '',
        method: '',
        note: '',
    });

    const closed = Boolean(period.closed_at);

    const confirmRecompute = () =>
        modal.confirm({
            title: 'Hitung ulang komisi periode ini dari data resi terkini?',
            onOk: () => router.post(`/komisi/periode/${period.id}/hitung-ulang`),
        });

    const confirmClose = () =>
        modal.confirm({
            title: 'Tutup periode ini? Sisa belum dibayar dipindahkan sebagai saldo periode berikutnya.',
            onOk: () => router.post(`/komisi/periode/${period.id}/tutup`),
        });

    const kpi = (title, value, span = 6, color) => (
        <Col xs={12} md={span}>
            <Statistic title={title} value={value} formatter={(v) => rupiah(v)} valueStyle={color ? { color } : undefined} />
        </Col>
    );

    return (
        <>
            <Card style={{ marginBottom: 16 }}>
                <Typography.Title level={4} style={{ marginTop: 0 }}>
                    {period.label}
                </Typography.Title>
                <Typography.Paragraph type="secondary">
                    Pemilik: <strong>{owner}</strong> · {fmtDate(period.start_date)} – {fmtDate(period.end_date)} · tipe{' '}
                    <Typography.Text code>{period.period_type}</Typography.Text> ·{' '}
                    {closed ? (
                        <>
                            <Tag color="orange">ditutup</Tag> {fmtDateTime(period.closed_at)} oleh{' '}
                            {period.closed_by?.name ?? '—'}
                        </>
                    ) : (
                        <Tag color="green">terbuka</Tag>
                    )}
                </Typography.Paragraph>
                <Space wrap>
                    <Link href={`/komisi?owner_type=${period.owner_type}`}>
                        <Button>Kembali</Button>
                    </Link>
                    {!closed && (
                        <>
                            <Button onClick={confirmRecompute}>Hitung Ulang</Button>
                            <Button type="primary" danger onClick={confirmClose}>
                                Tutup Periode
                            </Button>
                        </>
                    )}
                </Space>
            </Card>

            <Card title="Ringkasan" style={{ marginBottom: 16 }}>
                <Row gutter={16}>
                    {kpi('Komisi Order+Transfer+Ongkir+Multi', stats.komisi_total)}
                    {kpi('Input Admin (berdiri sendiri)', stats.admin_input)}
                    {kpi('On Progress (belum layak bayar)', stats.on_progress)}
                    {kpi('Payable (diterima/retur)', stats.payable)}
                    {kpi('Saldo Lalu', stats.carried)}
                    {kpi('Dibayar', stats.paid)}
                    {kpi('Belum Dibayar', stats.unpaid, 6, stats.unpaid > 0 ? '#dc2626' : '#16a34a')}
                    {kpi('Komponen Order', stats.komisi_order)}
                    {kpi('Komponen Transfer', stats.komisi_transfer)}
                    {kpi('Komponen Ongkir', stats.komisi_ongkir)}
                    {kpi('Multi Paket (U6: formula belum ada, tetap 0)', stats.komisi_multi_paket)}
                    <Col xs={12} md={3}>
                        <Statistic title="Resi berstatus" value={stats.qty} />
                    </Col>
                    <Col xs={12} md={3}>
                        <Statistic title="Undel" value={`${Number(stats.pct_undel).toFixed(1).replace('.', ',')}%`} />
                    </Col>
                    <Col xs={12} md={3}>
                        <Statistic title="Retur" value={`${Number(stats.pct_retur).toFixed(1).replace('.', ',')}%`} />
                    </Col>
                    <Col xs={12} md={3}>
                        <Statistic title="%Close (diterima+retur)" value={`${Number(stats.pct_close).toFixed(1).replace('.', ',')}%`} />
                    </Col>
                </Row>
                <Typography.Paragraph type="secondary" style={{ fontSize: 12, marginTop: 12, marginBottom: 0 }}>
                    Status per resi:{' '}
                    {Object.entries(stats.counts ?? {}).length === 0
                        ? '—'
                        : Object.entries(stats.counts).map(([status, count]) => (
                              <Tag color="orange" key={status}>
                                  {status}: {count}
                              </Tag>
                          ))}
                </Typography.Paragraph>
            </Card>

            <Card title={`Pembayaran (${payments.length})`} style={{ marginBottom: 16 }}>
                <Table
                    size="small"
                    rowKey="id"
                    pagination={false}
                    dataSource={payments}
                    locale={{ emptyText: 'Belum ada pembayaran dicatat.' }}
                    columns={[
                        { title: 'Tanggal', dataIndex: 'paid_date', width: 100, render: fmtDate },
                        { title: 'Jumlah', dataIndex: 'amount', width: 120, align: 'right', render: (v) => rupiah(v) },
                        { title: 'Referensi', dataIndex: 'reference', width: 140, render: (v) => v ?? '—' },
                        { title: 'Metode', dataIndex: 'method', width: 100, render: (v) => v ?? '—' },
                        { title: 'Dicatat oleh', key: 'oleh', width: 130, render: (_, p) => p.approved_by?.name ?? '—' },
                        { title: 'Catatan', dataIndex: 'note', render: (v) => v ?? '—' },
                    ]}
                />

                <Typography.Title level={5} style={{ marginTop: 18 }}>
                    Catat Pembayaran
                </Typography.Title>
                <Form
                    layout="inline"
                    onFinish={() => payForm.post(`/komisi/periode/${period.id}/bayar`)}
                    style={{ rowGap: 12 }}
                    initialValues={{ paid_date: dayjs(payForm.data.paid_date) }}
                >
                    <Form.Item label="Tanggal bayar" required validateStatus={payForm.errors.paid_date ? 'error' : undefined} help={payForm.errors.paid_date}>
                        <DatePicker
                            style={{ width: 140 }}
                            value={payForm.data.paid_date ? dayjs(payForm.data.paid_date) : null}
                            onChange={(v) => payForm.setData('paid_date', v ? v.format('YYYY-MM-DD') : null)}
                        />
                    </Form.Item>
                    <Form.Item label="Jumlah (Rp)" required validateStatus={payForm.errors.amount ? 'error' : undefined} help={payForm.errors.amount}>
                        <InputNumber
                            min={0.01}
                            style={{ width: 160 }}
                            value={payForm.data.amount}
                            onChange={(v) => payForm.setData('amount', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Referensi">
                        <Input
                            style={{ width: 170 }}
                            maxLength={100}
                            placeholder="no. transfer / bukti bayar"
                            value={payForm.data.reference}
                            onChange={(e) => payForm.setData('reference', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Metode">
                        <Input
                            style={{ width: 130 }}
                            maxLength={32}
                            placeholder="transfer / cash"
                            value={payForm.data.method}
                            onChange={(e) => payForm.setData('method', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Catatan">
                        <Input
                            style={{ width: 160 }}
                            maxLength={255}
                            value={payForm.data.note}
                            onChange={(e) => payForm.setData('note', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item>
                        <Button type="primary" htmlType="submit" loading={payForm.processing}>
                            Catat
                        </Button>
                    </Form.Item>
                </Form>
            </Card>

            <Card title={`Entri Komisi (${entries.total})`}>
                <Table
                    size="small"
                    rowKey="id"
                    dataSource={entries.data}
                    locale={{ emptyText: 'Belum ada entri — klik "Hitung Ulang" untuk menghitung dari data resi.' }}
                    pagination={{
                        current: entries.current_page,
                        pageSize: entries.per_page,
                        total: entries.total,
                        showSizeChanger: false,
                    }}
                    onChange={(p) => router.get(`/komisi/periode/${period.id}`, { page: p.current }, { preserveState: true, replace: true })}
                    columns={[
                        {
                            title: 'Resi',
                            key: 'resi',
                            width: 170,
                            render: (_, e) => (
                                <Link href={`/resi/${e.shipment_id}`}>
                                    {e.shipment?.tracking_id ?? e.shipment?.platform_order_id ?? `#${e.shipment_id}`}
                                </Link>
                            ),
                        },
                        {
                            title: 'Status',
                            dataIndex: 'status_internal',
                            width: 110,
                            render: (v) => <Tag color="orange">{v ?? '—'}</Tag>,
                        },
                        {
                            title: 'Komponen',
                            dataIndex: 'component',
                            width: 120,
                            render: (v) => COMPONENT_LABELS[v] ?? v,
                        },
                        { title: 'Amount', dataIndex: 'amount', width: 110, align: 'right', render: (v) => rupiah(v) },
                        {
                            title: 'Kelompok',
                            key: 'kel',
                            width: 110,
                            render: (_, e) => (e.is_payable ? <Tag color="green">payable</Tag> : <Tag color="orange">on progress</Tag>),
                        },
                        { title: 'Dihitung', dataIndex: 'computed_at', width: 130, render: fmtDateTime },
                    ]}
                />
            </Card>
        </>
    );
}
