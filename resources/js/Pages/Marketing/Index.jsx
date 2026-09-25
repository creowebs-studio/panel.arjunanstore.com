import React from 'react';
import { Button, Card, Col, Form, Input, Row, Segmented, Select, Table, Tag, Typography, Upload } from 'antd';
import {
    BarChartOutlined,
    CheckCircleOutlined,
    CloseCircleOutlined,
    FileTextOutlined,
    NotificationOutlined,
    UploadOutlined,
    WalletOutlined,
} from '@ant-design/icons';
import { Link, router, useForm } from '@inertiajs/react';
import StatCard from '../../components/StatCard';
import { fmtDateTime, rupiah } from '../../lib/format';

/** Tokenisasi nama kampanye AMxx-ARyy-PZ → adv / cs / produk (panduan kerja manual). */
function tokensOf(name) {
    const m = /-(.*?)-/.exec(name ?? '');
    return {
        adv: (name ?? '').slice(0, 4),
        cs: m ? m[1] : '-',
        product: (name ?? '').slice(-2),
    };
}

/** Baris worklist — petakan kampanye kode tak dikenal ke master ADV/CS/Produk. */
function CampaignMapRow({ c, advs, css, products }) {
    const { data, setData, post, processing } = useForm({
        advertiser_id: c.advertiser_id ?? null,
        cs_agent_id: c.cs_agent_id ?? null,
        product_id: c.product_id ?? null,
        note: '',
    });

    const t = tokensOf(c.name);

    return (
        <Form layout="inline" style={{ rowGap: 8, marginBottom: 8 }} onFinish={() => post(`/marketing/kampanye/${c.id}/petakan`)}>
            <Form.Item style={{ width: 190, marginBottom: 0 }}>
                <Typography.Text code>{c.name}</Typography.Text>
                <Typography.Text type="secondary" style={{ fontSize: 12, display: 'block' }}>
                    ADV: {t.adv} · CS: {t.cs} · Produk: {t.product}
                </Typography.Text>
            </Form.Item>
            <Form.Item style={{ width: 170, marginBottom: 0 }}>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="— pilih ADV —"
                    value={data.advertiser_id}
                    onChange={(v) => setData('advertiser_id', v)}
                    options={advs.map((a) => ({ value: a.id, label: `${a.name} (${a.code})` }))}
                />
            </Form.Item>
            <Form.Item style={{ width: 160, marginBottom: 0 }}>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="— pilih CS —"
                    value={data.cs_agent_id}
                    onChange={(v) => setData('cs_agent_id', v)}
                    options={css.map((s) => ({ value: s.id, label: `${s.name} (${s.code})` }))}
                />
            </Form.Item>
            <Form.Item style={{ width: 160, marginBottom: 0 }}>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="— pilih produk —"
                    value={data.product_id}
                    onChange={(v) => setData('product_id', v)}
                    options={products.map((p) => ({ value: p.id, label: `${p.name} (${p.code})` }))}
                />
            </Form.Item>
            <Form.Item style={{ minWidth: 160, marginBottom: 0 }}>
                <Input placeholder="alasan pemetaan (opsional)" value={data.note} onChange={(e) => setData('note', e.target.value)} />
            </Form.Item>
            <Form.Item style={{ marginBottom: 0 }}>
                <Button type="primary" htmlType="submit" loading={processing}>
                    Petakan
                </Button>
            </Form.Item>
        </Form>
    );
}

/**
 * Marketing (Alur E, prompt.md §8): impor laporan kampanye Meta Ads, worklist
 * kode tak dikenal, daftar kampanye + riwayat impor.
 */
export default function Index({ campaigns, status, stats, batches, unmapped, advs, css, products }) {
    const { data, setData, post, processing, errors } = useForm({ file: null });

    const filterStatus = (s) => router.get('/marketing', s ? { status: s } : {}, { preserveState: true, replace: true });

    return (
        <>
            <Card style={{ marginBottom: 16 }} styles={{ body: { paddingBottom: 12 } }}>
                <Row justify="space-between" align="middle" wrap={false} gutter={[12, 12]} style={{ marginBottom: 16 }}>
                    <Col flex={1} style={{ minWidth: 0 }}>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Marketing — Kampanye &amp; Laporan Iklan
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Impor laporan kampanye (export Meta Ads / tab <Typography.Text code>Rekap</Typography.Text>).
                            Kampanye dengan kode ADV/CS/Produk tak dikenal <strong>tidak disembunyikan</strong> — tampil
                            di worklist untuk dipetakan.
                        </Typography.Text>
                    </Col>
                    <Col>
                        <Link href="/rekap-adv">
                            <Button type="primary" ghost icon={<BarChartOutlined />}>
                                Rekap ADV
                            </Button>
                        </Link>
                    </Col>
                </Row>
                <Row gutter={[16, 16]}>
                    <Col xs={12} md={8} lg={4}>
                        <StatCard title="Kampanye" value={stats.total} prefix={<NotificationOutlined />} />
                    </Col>
                    <Col xs={12} md={8} lg={4}>
                        <StatCard
                            title="Terpetakan"
                            value={stats.mapped}
                            valueStyle={{ color: '#16a34a' }}
                            prefix={<CheckCircleOutlined />}
                        />
                    </Col>
                    <Col xs={12} md={8} lg={4}>
                        <StatCard
                            title="Kode Tak Dikenal"
                            value={stats.unmapped}
                            valueStyle={{ color: '#dc2626' }}
                            prefix={<CloseCircleOutlined />}
                        />
                    </Col>
                    <Col xs={12} md={8} lg={4}>
                        <StatCard title="Baris Laporan" value={stats.reports} prefix={<FileTextOutlined />} />
                    </Col>
                    <Col xs={24} md={8} lg={8}>
                        <StatCard
                            title="Spend + PPN"
                            value={stats.spend}
                            formatter={(v) => rupiah(v)}
                            prefix={<WalletOutlined />}
                        />
                    </Col>
                </Row>
            </Card>

            <Card title="Impor Laporan Kampanye" style={{ marginBottom: 16 }}>
                <Typography.Paragraph type="secondary">
                    Format kolom mengikuti tab <Typography.Text code>Rekap</Typography.Text>: Awal/Akhir Pelaporan, Nama
                    Kampanye (AMxx-ARyy-PZ), Jangkauan, Hasil, Jumlah Dibelanjakan (IDR), Impressi, Klik, dst.
                </Typography.Paragraph>
                <Form layout="inline" onFinish={() => post('/marketing/impor')} style={{ rowGap: 12 }}>
                    <Form.Item label="File CSV/TSV laporan kampanye" required validateStatus={errors.file ? 'error' : undefined} help={errors.file}>
                        <Upload
                            maxCount={1}
                            accept=".csv,.tsv,.txt"
                            beforeUpload={() => false}
                            onChange={(info) => setData('file', info.fileList[0]?.originFileObj ?? null)}
                            onRemove={() => setData('file', null)}
                        >
                            <Button icon={<UploadOutlined />}>Pilih File</Button>
                        </Upload>
                    </Form.Item>
                    <Form.Item>
                        <Button type="primary" htmlType="submit" loading={processing}>
                            Unggah &amp; Pratinjau
                        </Button>
                    </Form.Item>
                </Form>
            </Card>

            {unmapped.length > 0 && (
                <Card title={`Worklist — Kampanye Kode Tak Dikenal (${unmapped.length})`} style={{ marginBottom: 16 }}>
                    <Typography.Paragraph type="secondary">
                        Petakan manual ke master ADV/CS/Produk. Tindakan ini tercatat di audit log dan menutup Data
                        Issue terkait.
                    </Typography.Paragraph>
                    {unmapped.map((c) => (
                        <CampaignMapRow key={c.id} c={c} advs={advs} css={css} products={products} />
                    ))}
                </Card>
            )}

            <Card title="Daftar Kampanye" style={{ marginBottom: 16 }}>
                <Segmented
                    value={status || 'all'}
                    onChange={(v) => filterStatus(v === 'all' ? '' : v)}
                    options={[
                        { label: 'Semua', value: 'all' },
                        { label: 'Terpetakan', value: 'mapped' },
                        { label: 'Kode tak dikenal', value: 'unmapped' },
                    ]}
                    style={{ marginBottom: 12 }}
                />
                <Table
                    size="small"
                    rowKey="id"
                    dataSource={campaigns.data}
                    locale={{ emptyText: 'Belum ada kampanye. Impor laporan kampanye di atas.' }}
                    pagination={{
                        current: campaigns.current_page,
                        pageSize: campaigns.per_page,
                        total: campaigns.total,
                        showSizeChanger: false,
                    }}
                    onChange={(p) => router.get('/marketing', { status, page: p.current }, { preserveState: true, replace: true })}
                    columns={[
                        {
                            title: 'Nama Kampanye',
                            dataIndex: 'name',
                            width: 200,
                            render: (v, c) => (
                                <>
                                    <Typography.Text code>{v}</Typography.Text>
                                    <Typography.Text type="secondary" style={{ fontSize: 12, display: 'block' }}>
                                        {c.delivery_status} · {c.attribution}
                                    </Typography.Text>
                                </>
                            ),
                        },
                        {
                            title: 'ADV',
                            key: 'adv',
                            width: 140,
                            render: (_, c) => (
                                <>
                                    {c.advertiser?.name ?? '—'}
                                    <Typography.Text type="secondary" style={{ fontSize: 12, display: 'block' }}>
                                        {c.adv_code}
                                    </Typography.Text>
                                </>
                            ),
                        },
                        {
                            title: 'CS',
                            key: 'cs',
                            width: 140,
                            render: (_, c) => (
                                <>
                                    {c.cs_agent?.name ?? '—'}
                                    <Typography.Text type="secondary" style={{ fontSize: 12, display: 'block' }}>
                                        {c.cs_code}
                                    </Typography.Text>
                                </>
                            ),
                        },
                        {
                            title: 'Produk',
                            key: 'produk',
                            width: 140,
                            render: (_, c) => (
                                <>
                                    {c.product?.name ?? '—'}
                                    <Typography.Text type="secondary" style={{ fontSize: 12, display: 'block' }}>
                                        {c.product_resi_code}
                                    </Typography.Text>
                                </>
                            ),
                        },
                        { title: 'Laporan', dataIndex: 'daily_reports_count', width: 90, align: 'right' },
                        {
                            title: 'Status',
                            key: 'status',
                            width: 140,
                            render: (_, c) =>
                                c.is_mapped ? <Tag color="green">terpetakan</Tag> : <Tag color="red">kode tak dikenal</Tag>,
                        },
                    ]}
                />
            </Card>

            <Card title="Riwayat Impor Marketing">
                <Table
                    size="small"
                    rowKey="id"
                    pagination={false}
                    dataSource={batches}
                    locale={{ emptyText: 'Belum ada impor marketing.' }}
                    columns={[
                        { title: 'Waktu', dataIndex: 'created_at', width: 130, render: fmtDateTime },
                        { title: 'File', dataIndex: 'source_filename' },
                        { title: 'Oleh', key: 'oleh', width: 120, render: (_, b) => b.uploader?.name ?? '—' },
                        {
                            title: 'Status',
                            dataIndex: 'status',
                            width: 100,
                            render: (v) => <Tag color={v === 'done' ? 'green' : 'orange'}>{v}</Tag>,
                        },
                        { title: 'Baris', dataIndex: 'total_rows', width: 70, align: 'right' },
                        {
                            title: 'Hasil',
                            key: 'hasil',
                            width: 260,
                            render: (_, b) => (
                                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                    baru {b.new_rows} · ubah {b.updated_rows} · duplikat {b.duplicate_rows} · error {b.error_rows}
                                </Typography.Text>
                            ),
                        },
                        {
                            title: '',
                            key: 'aksi',
                            width: 90,
                            render: (_, b) => <Link href={`/marketing/impor/${b.id}`}>pratinjau</Link>,
                        },
                    ]}
                />
            </Card>
        </>
    );
}
