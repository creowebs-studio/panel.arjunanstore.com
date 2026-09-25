import React, { useMemo } from 'react';
import {
    Alert,
    Button,
    Card,
    Col,
    DatePicker,
    Form,
    Row,
    Segmented,
    Select,
    Space,
    Statistic,
    Table,
    Tabs,
    Tag,
    Typography,
} from 'antd';
import {
    FallOutlined,
    RiseOutlined,
    SafetyCertificateOutlined,
    ShoppingOutlined,
    WalletOutlined,
} from '@ant-design/icons';
import dayjs from 'dayjs';
import { Link, router } from '@inertiajs/react';
import { fmtDate, rupiah } from '../../lib/format';

const { RangePicker } = DatePicker;

const pct = (r) => `${(Number(r.pct_close) * 100).toFixed(1).replace('.', ',')}%`;

const money = (v) => ({ formatter: () => rupiah(v) });

/**
 * Rekap ADV ↔ Resi (prompt.md §8): padanan RekapADVtoResi (All).
 * Profit = laba kotor − komisi CS − spend (PPN 12%) — kolom V = S − U − Y.
 */
export default function Rekap({ rows, withoutResi, unmatched, totals, from, to, advertiserId, advertisers }) {
    const [form] = Form.useForm();

    const dFrom = String(from).slice(0, 10);
    const dTo = String(to).slice(0, 10);

    // Sorot preset aktif berdasarkan rentang terpilih (sama seperti Dashboard).
    const currentPreset = useMemo(() => {
        const today = dayjs().format('YYYY-MM-DD');
        const monthStart = dayjs().startOf('month').format('YYYY-MM-DD');
        const monthEnd = dayjs().endOf('month').format('YYYY-MM-DD');
        if (dFrom === monthStart && (dTo === today || dTo === monthEnd)) {
            return 'month';
        }
        if (dTo === today) {
            if (dFrom === dayjs().subtract(29, 'day').format('YYYY-MM-DD')) {
                return '30d';
            }
            if (dFrom === dayjs().subtract(6, 'day').format('YYYY-MM-DD')) {
                return '7d';
            }
        }
        return undefined;
    }, [dFrom, dTo]);

    const submit = (values) => {
        router.get(
            '/rekap-adv',
            {
                from: values.period?.[0] ? values.period[0].format('YYYY-MM-DD') : undefined,
                to: values.period?.[1] ? values.period[1].format('YYYY-MM-DD') : undefined,
                advertiser_id: values.advertiser_id || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const applyPreset = (key) => {
        const today = dayjs();
        const range =
            key === '7d'
                ? [today.subtract(6, 'day'), today]
                : key === '30d'
                  ? [today.subtract(29, 'day'), today]
                  : [today.startOf('month'), today];
        form.setFieldsValue({ period: range });
        submit({ period: range, advertiser_id: form.getFieldValue('advertiser_id') });
    };

    const onValuesChange = (changed) => {
        // Terapkan otomatis saat ADV berubah; rentang butuh tombol Terapkan/preset.
        if ('advertiser_id' in changed) {
            submit(form.getFieldsValue());
        }
    };

    const totalsRow = [
        { title: 'Packing', value: totals.packing },
        { title: 'Dikirim', value: totals.dikirim },
        { title: 'Undel', value: totals.undel },
        { title: 'Diterima', value: totals.diterima },
        { title: 'Retur', value: totals.retur },
    ];

    const detailColumns = [
        {
            title: 'Tanggal',
            key: 'tgl',
            width: 130,
            fixed: 'left',
            render: (_, r) => `${fmtDate(r.report?.date_start)}–${fmtDate(r.report?.date_end)}`,
        },
        {
            title: 'Kampanye',
            key: 'kampanye',
            width: 220,
            render: (_, r) => (
                <Space size={4}>
                    <Typography.Text code>{r.campaign?.name}</Typography.Text>
                    {r.campaign?.is_mapped ? (
                        <Tag color="green" style={{ fontSize: 10, marginInlineEnd: 0 }}>
                            ok
                        </Tag>
                    ) : (
                        <Tag color="red" style={{ fontSize: 10, marginInlineEnd: 0 }}>
                            kode tak dikenal
                        </Tag>
                    )}
                </Space>
            ),
        },
        {
            title: 'ADV / CS / Produk',
            key: 'codes',
            width: 150,
            render: (_, r) => (
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                    {r.campaign?.advertiser?.code ?? '—'} / {r.campaign?.cs_agent?.code ?? '—'} /{' '}
                    {r.campaign?.product?.code ?? '—'}
                </Typography.Text>
            ),
        },
        {
            title: 'P/D/U/T/R*',
            key: 'counts',
            width: 120,
            render: (_, r) =>
                `${r.counts.packing}/${r.counts.dikirim}/${r.counts.undel}/${r.counts.diterima}/${r.counts.retur}`,
        },
        { title: 'Total', dataIndex: 'total', width: 70, align: 'right', render: (v) => <b>{v}</b> },
        { title: '%Close', key: 'pct', width: 85, align: 'right', render: (_, r) => pct(r) },
        { title: 'Est Return', dataIndex: 'est_return', width: 95, align: 'right' },
        { title: 'Est Diterima', dataIndex: 'est_diterima', width: 105, align: 'right' },
        { title: 'Spend', dataIndex: 'spend', width: 120, align: 'right', render: (v) => rupiah(v) },
        { title: 'Laba Kotor', dataIndex: 'laba_kotor', width: 120, align: 'right', render: (v) => rupiah(v) },
        { title: 'Komisi CS', dataIndex: 'komisi_cs', width: 120, align: 'right', render: (v) => rupiah(v) },
        {
            title: 'Profit',
            dataIndex: 'profit',
            width: 120,
            align: 'right',
            fixed: 'right',
            render: (v) => (
                <Typography.Text type={v >= 0 ? 'success' : 'danger'} strong>
                    {rupiah(v)}
                </Typography.Text>
            ),
        },
    ];

    const detailTable = (
        <>
            <Table
                size="small"
                rowKey={(r) => `${r.report?.id}|${r.campaign?.id}`}
                pagination={{ pageSize: 25, showSizeChanger: true, size: 'small' }}
                scroll={{ x: 1500 }}
                dataSource={rows}
                locale={{
                    emptyText: 'Tidak ada laporan kampanye pada rentang ini. Impor dulu di halaman Marketing.',
                }}
                columns={detailColumns}
                summary={() =>
                    rows.length === 0 ? null : (
                        <Table.Summary fixed>
                            <Table.Summary.Row>
                                <Table.Summary.Cell index={0} colSpan={5}>
                                    <b>Total {rows.length} baris laporan</b>
                                </Table.Summary.Cell>
                                <Table.Summary.Cell index={5} align="right">
                                    <b>
                                        {totals.packing + totals.dikirim + totals.undel + totals.diterima + totals.retur}
                                    </b>
                                </Table.Summary.Cell>
                                <Table.Summary.Cell index={6} />
                                <Table.Summary.Cell index={7} />
                                <Table.Summary.Cell index={8} />
                                <Table.Summary.Cell index={9} align="right">
                                    {rupiah(totals.spend)}
                                </Table.Summary.Cell>
                                <Table.Summary.Cell index={10} align="right">
                                    {rupiah(totals.laba_kotor)}
                                </Table.Summary.Cell>
                                <Table.Summary.Cell index={11} align="right">
                                    {rupiah(totals.komisi_cs)}
                                </Table.Summary.Cell>
                                <Table.Summary.Cell index={12} align="right">
                                    <Typography.Text
                                        type={totals.profit >= 0 ? 'success' : 'danger'}
                                        strong
                                    >
                                        {rupiah(totals.profit)}
                                    </Typography.Text>
                                </Table.Summary.Cell>
                            </Table.Summary.Row>
                        </Table.Summary>
                    )
                }
            />
            <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                * P/D/U/T/R = Packing/Dikirim/Undel/Diterima/Retur (COUNTIF per hari create-date resi).
            </Typography.Text>
        </>
    );

    const withoutResiTable = (
        <Table
            size="small"
            rowKey={(r) => `${r.report?.id}`}
            pagination={false}
            dataSource={withoutResi}
            locale={{ emptyText: 'Semua laporan kampanye pada rentang ini punya resi terkait.' }}
            columns={[
                { title: 'Tanggal', key: 'tgl', width: 110, render: (_, r) => fmtDate(r.report?.date_start) },
                {
                    title: 'Kampanye',
                    key: 'k',
                    render: (_, r) => <Typography.Text code>{r.campaign?.name}</Typography.Text>,
                },
                { title: 'ADV', key: 'adv', width: 150, render: (_, r) => r.campaign?.advertiser?.name ?? '—' },
                { title: 'Spend', key: 'spend', width: 120, align: 'right', render: (_, r) => rupiah(r.spend) },
                { title: 'Total Resi', key: 'resi', width: 100, render: () => <Tag color="orange">0</Tag> },
            ]}
        />
    );

    const unmatchedTable = (
        <>
            <Typography.Paragraph type="secondary">
                Resi pada rentang tanggal ini yang kode ADV/CS/Produknya tidak cocok dengan kampanye mana pun.
            </Typography.Paragraph>
            <Table
                size="small"
                rowKey="id"
                pagination={false}
                dataSource={unmatched.items}
                locale={{ emptyText: 'Semua resi pada rentang ini dapat dicocokkan.' }}
                columns={[
                    {
                        title: 'Resi',
                        key: 'resi',
                        width: 160,
                        render: (_, s) => <Link href={`/resi/${s.id}`}>{s.tracking_id ?? s.platform_order_id}</Link>,
                    },
                    { title: 'Platform', dataIndex: 'platform', width: 100 },
                    { title: 'Adv', dataIndex: 'adv_resi_code', width: 70, render: (v) => v ?? '—' },
                    { title: 'CS', dataIndex: 'cs_resi_code', width: 70, render: (v) => v ?? '—' },
                    { title: 'Produk', dataIndex: 'product_resi_code', width: 70, render: (v) => v ?? '—' },
                    { title: 'Status', dataIndex: 'status_internal', width: 100, render: (v) => v ?? '—' },
                    { title: 'Create', dataIndex: 'create_date', width: 100, render: fmtDate },
                ]}
            />
        </>
    );

    return (
        <>
            <Card styles={{ body: { paddingBottom: 8 } }} style={{ marginBottom: 16 }}>
                <Row justify="space-between" align="middle" wrap gutter={[12, 12]}>
                    <Col flex="auto">
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Rekap ADV ↔ Resi
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Padanan <Typography.Text code>RekapADVtoResi (All)</Typography.Text>: per kampanye × ADV ×
                            CS × Produk × tanggal · Profit = laba kotor − komisi CS − spend (PPN 12%).
                        </Typography.Text>
                    </Col>
                    <Col>
                        <Segmented
                            value={currentPreset}
                            onChange={applyPreset}
                            options={[
                                { label: '7 Hari', value: '7d' },
                                { label: '30 Hari', value: '30d' },
                                { label: 'Bulan Ini', value: 'month' },
                            ]}
                        />
                    </Col>
                </Row>
                <Form
                    form={form}
                    layout="inline"
                    onFinish={submit}
                    onValuesChange={onValuesChange}
                    style={{ rowGap: 12, marginTop: 16 }}
                    initialValues={{
                        period: [dayjs(from), dayjs(to)],
                        advertiser_id: advertiserId || undefined,
                    }}
                >
                    <Form.Item label="Periode" name="period">
                        <RangePicker style={{ width: 240 }} allowClear={false} />
                    </Form.Item>
                    <Form.Item label="ADV" name="advertiser_id">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="— semua ADV —"
                            style={{ width: 200 }}
                            options={advertisers.map((a) => ({ value: a.id, label: a.name }))}
                        />
                    </Form.Item>
                    <Form.Item>
                        <Button type="primary" htmlType="submit">
                            Terapkan
                        </Button>
                    </Form.Item>
                </Form>
            </Card>

            <Card title="Total Rentang" style={{ marginBottom: 16 }}>
                <Row gutter={[16, 16]}>
                    {totalsRow.map((t) => (
                        <Col key={t.title} xs={12} sm={8} md={4}>
                            <Card size="small" variant="borderless" style={{ background: '#fafafa' }}>
                                <Statistic title={t.title} value={t.value} />
                            </Card>
                        </Col>
                    ))}
                    <Col xs={12} sm={8} md={6}>
                        <Card size="small" variant="borderless" style={{ background: '#fafafa' }}>
                            <Statistic
                                title="Spend + PPN"
                                value={totals.spend}
                                {...money(totals.spend)}
                                prefix={<ShoppingOutlined />}
                            />
                        </Card>
                    </Col>
                    <Col xs={12} sm={8} md={6}>
                        <Card size="small" variant="borderless" style={{ background: '#fafafa' }}>
                            <Statistic
                                title="Laba Kotor"
                                value={totals.laba_kotor}
                                {...money(totals.laba_kotor)}
                                prefix={<RiseOutlined />}
                            />
                        </Card>
                    </Col>
                    <Col xs={12} sm={8} md={6}>
                        <Card size="small" variant="borderless" style={{ background: '#fafafa' }}>
                            <Statistic
                                title="Komisi CS"
                                value={totals.komisi_cs}
                                {...money(totals.komisi_cs)}
                                prefix={<SafetyCertificateOutlined />}
                            />
                        </Card>
                    </Col>
                    <Col xs={12} sm={8} md={6}>
                        <Card
                            size="small"
                            variant="borderless"
                            style={{ background: totals.profit >= 0 ? '#f0fdf4' : '#fef2f2' }}
                        >
                            <Statistic
                                title="Profit"
                                value={totals.profit}
                                {...money(totals.profit)}
                                prefix={<WalletOutlined />}
                                valueStyle={{ color: totals.profit >= 0 ? '#16a34a' : '#dc2626', fontWeight: 600 }}
                            />
                        </Card>
                    </Col>
                </Row>
            </Card>

            {rows.length === 0 ? (
                <Alert
                    type="info"
                    showIcon
                    message="Belum ada data"
                    description="Tidak ada laporan kampanye pada rentang ini. Impor laporan marketing terlebih dahulu."
                    style={{ marginBottom: 16 }}
                />
            ) : null}

            <Card>
                <Tabs
                    defaultActiveKey="detail"
                    items={[
                        {
                            key: 'detail',
                            label: `Rincian Laporan (${rows.length})`,
                            children: detailTable,
                        },
                        {
                            key: 'withoutResi',
                            label: (
                                <span>
                                    ADV Tanpa Resi{' '}
                                    {withoutResi.length > 0 ? (
                                        <Tag color="orange" style={{ marginInlineStart: 4 }}>
                                            {withoutResi.length}
                                        </Tag>
                                    ) : null}
                                </span>
                            ),
                            children: withoutResiTable,
                        },
                        {
                            key: 'unmatched',
                            label: (
                                <span>
                                    Resi Tak Tercocokkan{' '}
                                    {unmatched.count > 0 ? (
                                        <Tag color="red" style={{ marginInlineStart: 4 }}>
                                            {unmatched.count}
                                        </Tag>
                                    ) : null}
                                </span>
                            ),
                            children: unmatchedTable,
                        },
                    ]}
                />
            </Card>
        </>
    );
}
