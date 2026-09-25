import React, { useEffect, useMemo } from 'react';
import {
    Badge,
    Button,
    Card,
    Col,
    Collapse,
    DatePicker,
    Divider,
    Form,
    Progress,
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
    BarcodeOutlined,
    CheckCircleOutlined,
    DollarOutlined,
    FundOutlined,
    NotificationOutlined,
    ReloadOutlined,
    RollbackOutlined,
    ShoppingCartOutlined,
    WarningOutlined,
} from '@ant-design/icons';
import dayjs from 'dayjs';
import { Link, router } from '@inertiajs/react';
import { fmtDate, rupiah } from '../lib/format';

const { RangePicker } = DatePicker;

const STATUS_LABEL = { packing: 'Packing', dikirim: 'Dikirim', undel: 'Undel', diterima: 'Diterima', retur: 'Retur' };
const STATUS_COLOR = { packing: 'default', dikirim: 'blue', undel: 'orange', diterima: 'green', retur: 'red' };
// Warna palet antd untuk Progress (Tag tetap memakai token semantik di atas).
const STATUS_HEX = { packing: '#8c8c8c', dikirim: '#1677ff', undel: '#fa8c16', diterima: '#52c41a', retur: '#ff4d4f' };
const CLASS_LABEL = { positif: 'Positif', negatif: 'Negatif', perlu_ditinjau: 'Perlu Ditinjau' };
const CLASS_HEX = { positif: '#52c41a', negatif: '#ff4d4f', perlu_ditinjau: '#fa8c16' };

const PRESETS = [
    { label: '7 Hari', value: '7d' },
    { label: '30 Hari', value: '30d' },
    { label: 'Bulan Ini', value: 'month' },
];

/**
 * Dashboard (prompt.md §9): filter tanggal/ADV/CS/produk/agregator/ekspedisi/status.
 * Kartu dan tabel detail memakai kumpulan resi & rantai OutputResi yang sama
 * (lihat DashboardController) sehingga angka selalu cocok.
 */
export default function Dashboard({
    from,
    to,
    filters,
    advertisers,
    csAgents,
    products,
    platforms,
    expeditions,
    statuses,
    counts,
    withoutStatus,
    shipmentTotal,
    totals,
    spendRows,
    orderCounts,
    orderTotal,
    latestShipments,
    rowCalcs,
}) {
    const [form] = Form.useForm();

    const dFrom = String(from).slice(0, 10);
    const dTo = String(to).slice(0, 10);
    const filtersKey = JSON.stringify([
        filters.advertiser?.id,
        filters.csAgent?.id,
        filters.product?.id,
        filters.platform,
        filters.expedition,
        filters.status,
    ]);

    // Sorot preset aktif dari rentang terpilih (agar Segmented selalu sinkron).
    const currentPreset = useMemo(() => {
        const today = dayjs().format('YYYY-MM-DD');
        const monthStart = dayjs().startOf('month').format('YYYY-MM-DD');
        const monthEnd = dayjs().endOf('month').format('YYYY-MM-DD');
        // Default controller = awal..akhir bulan; preset Bulan Ini = awal bulan..hari ini.
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

    // Sinkronkan kontrol filter setiap props berubah (preset/reset/filter URL).
    useEffect(() => {
        form.setFieldsValue({
            range: [dayjs(dFrom), dayjs(dTo)],
            advertiser_id: filters.advertiser?.id ?? null,
            cs_agent_id: filters.csAgent?.id ?? null,
            product_id: filters.product?.id ?? null,
            platform: filters.platform ?? null,
            expedition: filters.expedition ?? null,
            status: filters.status ?? null,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [dFrom, dTo, filtersKey, form]);

    const apply = (values) => {
        router.get(
            '/dashboard',
            {
                from: values.range?.[0] ? values.range[0].format('YYYY-MM-DD') : undefined,
                to: values.range?.[1] ? values.range[1].format('YYYY-MM-DD') : undefined,
                advertiser_id: values.advertiser_id || undefined,
                cs_agent_id: values.cs_agent_id || undefined,
                product_id: values.product_id || undefined,
                platform: values.platform || undefined,
                expedition: values.expedition || undefined,
                status: values.status || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const applyPreset = (key) => {
        let f;
        const t = dayjs();
        if (key === '7d') f = t.subtract(6, 'day');
        else if (key === '30d') f = t.subtract(29, 'day');
        else f = t.startOf('month');
        router.get(
            '/dashboard',
            { from: f.format('YYYY-MM-DD'), to: t.format('YYYY-MM-DD') },
            { preserveState: true, replace: true },
        );
    };

    const reset = () => router.get('/dashboard', {}, { preserveState: true, replace: true });

    const selectOptions = (rows) => rows.map((r) => ({ value: r.id, label: r.name }));
    const strOptions = (rows) => rows.map((r) => ({ value: r, label: r }));

    const pctOf = (n) => (shipmentTotal > 0 ? (n / shipmentTotal) * 100 : 0);
    const pctTxt = (n) => `${pctOf(n).toFixed(1).replace('.', ',')}%`;

    const closed = (counts.diterima ?? 0) + (counts.retur ?? 0);
    const closePct = shipmentTotal > 0 ? Math.round((closed / shipmentTotal) * 100) : 0;

    // ---- Kartu KPI utama ------------------------------------------------------------------
    const kpis = [
        { title: 'Total Resi', value: shipmentTotal, icon: <BarcodeOutlined />, color: '#1677ff' },
        { title: 'Diterima', value: counts.diterima ?? 0, icon: <CheckCircleOutlined />, color: '#52c41a', suffix: ` · ${pctTxt(counts.diterima ?? 0)}` },
        { title: 'Retur', value: counts.retur ?? 0, icon: <RollbackOutlined />, color: '#ff4d4f', suffix: ` · ${pctTxt(counts.retur ?? 0)}` },
        { title: 'Tanpa Status', value: withoutStatus, icon: <WarningOutlined />, color: '#fa8c16' },
        { title: 'Spend Iklan', value: totals.spend, icon: <NotificationOutlined />, color: '#722ed1', format: rupiah },
        { title: 'Profit', value: totals.profit, icon: <DollarOutlined />, color: totals.profit >= 0 ? '#16a34a' : '#dc2626', format: rupiah },
    ];

    // ---- Tabel status (tab detail) --------------------------------------------------------
    const statusTable = [
        ...statuses.map((st) => ({
            key: st,
            status: <Badge color={STATUS_HEX[st]} text={STATUS_LABEL[st]} />,
            jumlah: counts[st] ?? 0,
            pct: pctTxt(counts[st] ?? 0),
            aksi: (
                <Link href={`/dashboard?status=${st}`} preserveScroll>
                    lihat resi
                </Link>
            ),
        })),
        {
            key: '_total',
            status: <b>Total</b>,
            jumlah: <b>{statuses.reduce((a, st) => a + (counts[st] ?? 0), 0)}</b>,
            pct: '100,0%',
            aksi: <Typography.Text type="secondary">+ {withoutStatus} tanpa status</Typography.Text>,
        },
    ];

    // ---- Finansial ------------------------------------------------------------------------
    const financialItems = [
        { ukuran: 'Nilai Penjualan', nilai: totals.nilai_penjualan, padanan: 'Σ (P + Q) per resi berstatus' },
        { ukuran: 'Ongkir', nilai: totals.ongkir, padanan: 'Σ R (ongkir sebelum diskon)' },
        { ukuran: 'Laba Kotor', nilai: totals.laba_eksplisit, padanan: 'Σ AM = (P+Q) − R − (T+U) − AD − AK − AL' },
        { ukuran: 'Komisi CS', nilai: totals.komisi_cs, padanan: 'Σ AK per resi (order+transfer+ongkir+multi)' },
        { ukuran: 'Input Admin', nilai: totals.admin_input, padanan: 'Σ AL = 500 flat per resi berstatus' },
        { ukuran: 'Spend Iklan', nilai: totals.spend, padanan: 'Σ spend + PPN dari tabel laporan kampanye' },
    ];

    const shipmentColumns = [
        { title: 'Tanggal', dataIndex: 'create_date', render: fmtDate, width: 100 },
        {
            title: 'Resi',
            dataIndex: 'id',
            render: (_, s) => (
                <Link href={`/resi/${s.id}`}>{s.tracking_id || s.platform_order_id || `#${s.id}`}</Link>
            ),
        },
        { title: 'Agregator', dataIndex: 'platform', render: (v) => v || '—', width: 110 },
        { title: 'Ekspedisi', dataIndex: 'expedition', render: (v) => v || '—', width: 110 },
        {
            title: 'Status',
            dataIndex: 'status_internal',
            width: 110,
            render: (v) =>
                v ? <Tag color={STATUS_COLOR[v]}>{STATUS_LABEL[v] ?? v}</Tag> : <Tag color="red">tanpa status</Tag>,
        },
        {
            title: 'ADV/CS/Produk',
            key: 'codes',
            width: 180,
            render: (_, s) => (
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                    {s.adv_resi_code || '—'} / {s.cs_resi_code || '—'} / {s.product_resi_code || '—'}
                </Typography.Text>
            ),
        },
        {
            title: 'Nilai (P+Q)',
            key: 'nilai',
            align: 'right',
            render: (_, s) => (rowCalcs[s.id] ? rupiah(rowCalcs[s.id].p + rowCalcs[s.id].q) : '—'),
        },
        { title: 'Ongkir', key: 'ongkir', align: 'right', render: (_, s) => (rowCalcs[s.id] ? rupiah(rowCalcs[s.id].r) : '—') },
        {
            title: 'Laba (AM)',
            key: 'laba',
            align: 'right',
            render: (_, s) =>
                rowCalcs[s.id] ? (
                    <Typography.Text type={rowCalcs[s.id].am_laba_eksplisit >= 0 ? 'success' : 'danger'}>
                        {rupiah(rowCalcs[s.id].am_laba_eksplisit)}
                    </Typography.Text>
                ) : (
                    '—'
                ),
        },
        {
            title: 'Komisi (AK)',
            key: 'komisi',
            align: 'right',
            render: (_, s) => (rowCalcs[s.id] ? rupiah(rowCalcs[s.id].ak_komisi_total) : '—'),
        },
    ];

    const spendColumns = [
        {
            title: 'Periode',
            key: 'periode',
            width: 130,
            render: (_, r) => `${fmtDate(r.date_start)}–${fmtDate(r.date_end)}`,
        },
        {
            title: 'Kampanye',
            dataIndex: 'name',
            render: (_, r) => <Typography.Text code>{r.campaign?.name ?? '—'}</Typography.Text>,
        },
        { title: 'ADV', key: 'adv', render: (_, r) => r.campaign?.advertiser?.name ?? '—' },
        { title: 'Spend', dataIndex: 'spend_raw', align: 'right', render: (v) => rupiah(v) },
        { title: 'Spend + PPN', dataIndex: 'spend_ppn', align: 'right', render: (v) => rupiah(v) },
    ];

    const cardTitle = (icon, text, extra) => (
        <Space>
            {icon}
            {text}
            {extra}
        </Space>
    );

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            {/* ---- Header + filter ---------------------------------------------------------- */}
            <Card>
                <Row align="middle" justify="space-between" gutter={[16, 12]}>
                    <Col>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Dashboard
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            {fmtDate(from)} – {fmtDate(to)} (tanggal resi) · rantai{' '}
                            <Typography.Text code>OutputResi</Typography.Text> sama dengan halaman Komisi
                        </Typography.Text>
                    </Col>
                    <Col>
                        <Segmented options={PRESETS} value={currentPreset} onChange={applyPreset} />
                    </Col>
                </Row>
                <Divider style={{ margin: '16px 0' }} />
                <Form
                    form={form}
                    layout="inline"
                    onFinish={apply}
                    style={{ rowGap: 12 }}
                >
                    <Form.Item label="Periode" name="range">
                        <RangePicker />
                    </Form.Item>
                    <Form.Item label="ADV" name="advertiser_id">
                        <Select allowClear showSearch optionFilterProp="label" placeholder="Semua ADV" options={selectOptions(advertisers)} style={{ width: 150 }} />
                    </Form.Item>
                    <Form.Item label="CS" name="cs_agent_id">
                        <Select allowClear showSearch optionFilterProp="label" placeholder="Semua CS" options={selectOptions(csAgents)} style={{ width: 140 }} />
                    </Form.Item>
                    <Form.Item label="Produk" name="product_id">
                        <Select allowClear showSearch optionFilterProp="label" placeholder="Semua produk" options={selectOptions(products)} style={{ width: 150 }} />
                    </Form.Item>
                    <Form.Item label="Agregator" name="platform">
                        <Select allowClear placeholder="Semua agregator" options={strOptions(platforms)} style={{ width: 140 }} />
                    </Form.Item>
                    <Form.Item label="Ekspedisi" name="expedition">
                        <Select allowClear placeholder="Semua ekspedisi" options={strOptions(expeditions)} style={{ width: 140 }} />
                    </Form.Item>
                    <Form.Item label="Status" name="status">
                        <Select
                            allowClear
                            placeholder="Semua status"
                            options={statuses.map((s) => ({ value: s, label: STATUS_LABEL[s] }))}
                            style={{ width: 120 }}
                        />
                    </Form.Item>
                    <Form.Item>
                        <Space>
                            <Button type="primary" htmlType="submit">
                                Terapkan
                            </Button>
                            <Button icon={<ReloadOutlined />} onClick={reset}>
                                Reset
                            </Button>
                        </Space>
                    </Form.Item>
                </Form>
            </Card>

            {/* ---- KPI ----------------------------------------------------------------------- */}
            <Row gutter={[16, 16]}>
                {kpis.map((k) => (
                    <Col xs={12} sm={12} md={8} xl={4} key={k.title}>
                        <Card size="small">
                            <Statistic
                                title={k.title}
                                value={k.value}
                                prefix={<span style={{ color: k.color, marginRight: 4 }}>{k.icon}</span>}
                                suffix={k.suffix}
                                formatter={k.format ? (v) => <span style={{ color: k.color }}>{k.format(v)}</span> : undefined}
                                valueStyle={{ color: k.format ? k.color : undefined }}
                            />
                        </Card>
                    </Col>
                ))}
            </Row>

            {/* ---- Status & Order ------------------------------------------------------------ */}
            <Row gutter={[16, 16]}>
                <Col xs={24} lg={14}>
                    <Card
                        title={cardTitle(<BarcodeOutlined />, 'Status Resi', (
                            <Typography.Text type="secondary" style={{ fontWeight: 400, fontSize: 13 }}>
                                close rate
                            </Typography.Text>
                        ))}
                    >
                        <Row gutter={[16, 8]} align="middle">
                            <Col xs={8} style={{ textAlign: 'center' }}>
                                <Progress type="circle" percent={closePct} strokeColor="#52c41a" size={96} />
                                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                    Diterima + Retur
                                </Typography.Text>
                            </Col>
                            <Col xs={16}>
                                {statuses.map((st) => (
                                    <div key={st} style={{ marginBottom: 10 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                                            <span>{STATUS_LABEL[st]}</span>
                                            <span>
                                                <b>{counts[st] ?? 0}</b>
                                                <Typography.Text type="secondary"> · {pctTxt(counts[st] ?? 0)}</Typography.Text>
                                            </span>
                                        </div>
                                        <Progress
                                            percent={pctOf(counts[st] ?? 0)}
                                            showInfo={false}
                                            strokeColor={STATUS_HEX[st]}
                                            size={{ height: 6 }}
                                        />
                                    </div>
                                ))}
                            </Col>
                        </Row>
                    </Card>
                </Col>
                <Col xs={24} lg={10}>
                    <Card title={cardTitle(<ShoppingCartOutlined />, 'Klasifikasi Order')}>
                        <Row gutter={16} style={{ marginBottom: 8 }}>
                            <Col span={12}>
                                <Statistic title="Total Order" value={orderTotal} />
                            </Col>
                            <Col span={12}>
                                <Statistic
                                    title="Perlu Ditinjau"
                                    value={orderCounts.perlu_ditinjau ?? 0}
                                    valueStyle={{ color: '#d97706' }}
                                />
                            </Col>
                        </Row>
                        {['positif', 'negatif', 'perlu_ditinjau'].map((cls) => {
                            const n = orderCounts[cls] ?? 0;
                            const p = orderTotal > 0 ? (n / orderTotal) * 100 : 0;
                            return (
                                <div key={cls} style={{ marginBottom: 10 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                                        <Link href={`/orders?classification=${cls}`} preserveScroll>
                                            {CLASS_LABEL[cls]}
                                        </Link>
                                        <span>
                                            <b>{n}</b>
                                            <Typography.Text type="secondary"> · {p.toFixed(1).replace('.', ',')}%</Typography.Text>
                                        </span>
                                    </div>
                                    <Progress percent={p} showInfo={false} strokeColor={CLASS_HEX[cls]} size={{ height: 6 }} />
                                </div>
                            );
                        })}
                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                            Filter tanggal order memakai tanggal order (bukan tanggal resi).
                        </Typography.Text>
                    </Card>
                </Col>
            </Row>

            {/* ---- Finansial ------------------------------------------------------------------ */}
            <Card title={cardTitle(<FundOutlined />, 'Finansial', (
                <Typography.Text type="secondary" style={{ fontWeight: 400, fontSize: 13 }}>
                    kartu vs detail — angka selalu cocok
                </Typography.Text>
            ))}>
                <Row gutter={[16, 16]}>
                    {financialItems.map((f) => (
                        <Col xs={12} md={8} xl={4} key={f.ukuran}>
                            <Statistic title={f.ukuran} value={f.nilai} formatter={(v) => rupiah(v)} />
                        </Col>
                    ))}
                    <Col xs={24}>
                        <Divider style={{ margin: '4px 0 12px' }} />
                        <Statistic
                            title="Profit = Laba Kotor − Komisi CS − Spend Iklan"
                            value={totals.profit}
                            valueStyle={{ color: totals.profit >= 0 ? '#16a34a' : '#dc2626', fontSize: 28 }}
                            formatter={(v) => rupiah(v)}
                        />
                    </Col>
                </Row>
                <Collapse
                    ghost
                    style={{ marginTop: 8 }}
                    items={[
                        {
                            key: 'rumus',
                            label: 'Rincian & Rumus',
                            children: (
                                <Table
                                    size="small"
                                    pagination={false}
                                    columns={[
                                        { title: 'Ukuran', dataIndex: 'ukuran' },
                                        {
                                            title: 'Nilai',
                                            dataIndex: 'nilai',
                                            align: 'right',
                                            render: (v, r) => (r.bold ? <b>{rupiah(v)}</b> : rupiah(v)),
                                        },
                                        {
                                            title: 'Padanan / Rumus',
                                            dataIndex: 'padanan',
                                            render: (v) => <Typography.Text type="secondary">{v}</Typography.Text>,
                                        },
                                    ]}
                                    dataSource={[
                                        ...financialItems,
                                        {
                                            ukuran: 'Profit',
                                            nilai: totals.profit,
                                            padanan: 'Laba Kotor − Komisi CS − Spend Iklan (V = S−U−Y)',
                                            bold: true,
                                        },
                                    ]}
                                    rowKey="ukuran"
                                />
                            ),
                        },
                    ]}
                />
            </Card>

            {/* ---- Detail (tabs) ---------------------------------------------------------------- */}
            <Card>
                <Tabs
                    defaultActiveKey="resi"
                    items={[
                        {
                            key: 'resi',
                            label: `Resi Terbaru (${latestShipments.total})`,
                            children: (
                                <Table
                                    size="small"
                                    rowKey="id"
                                    columns={shipmentColumns}
                                    dataSource={latestShipments.data}
                                    pagination={{
                                        current: latestShipments.current_page,
                                        pageSize: latestShipments.per_page,
                                        total: latestShipments.total,
                                        showSizeChanger: false,
                                    }}
                                    onChange={(p) =>
                                        router.get(
                                            '/dashboard',
                                            {
                                                from,
                                                to,
                                                advertiser_id: filters.advertiser?.id,
                                                cs_agent_id: filters.csAgent?.id,
                                                product_id: filters.product?.id,
                                                platform: filters.platform,
                                                expedition: filters.expedition,
                                                status: filters.status,
                                                page: p.current,
                                            },
                                            { preserveState: true, replace: true },
                                        )
                                    }
                                />
                            ),
                        },
                        {
                            key: 'spend',
                            label: `Laporan Kampanye (${spendRows.length})`,
                            children: (
                                <Table
                                    size="small"
                                    pagination={false}
                                    rowKey="id"
                                    columns={spendColumns}
                                    dataSource={spendRows}
                                    locale={{ emptyText: 'Tidak ada laporan kampanye pada rentang ini. Impor di halaman Marketing.' }}
                                    summary={(rows) =>
                                        rows.length ? (
                                            <Table.Summary.Row>
                                                <Table.Summary.Cell index={0} colSpan={4}>
                                                    <b>Total Spend + PPN</b>
                                                </Table.Summary.Cell>
                                                <Table.Summary.Cell index={1} align="right">
                                                    <b>{rupiah(rows.reduce((a, r) => a + Number(r.spend_ppn || 0), 0))}</b>
                                                </Table.Summary.Cell>
                                            </Table.Summary.Row>
                                        ) : null
                                    }
                                />
                            ),
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            children: (
                                <Table
                                    size="small"
                                    pagination={false}
                                    columns={[
                                        { title: 'Status Internal', dataIndex: 'status' },
                                        { title: 'Jumlah', dataIndex: 'jumlah' },
                                        { title: '% dari Total', dataIndex: 'pct' },
                                        { title: '', dataIndex: 'aksi' },
                                    ]}
                                    dataSource={statusTable}
                                />
                            ),
                        },
                    ]}
                />
            </Card>
        </Space>
    );
}
