import React from 'react';
import { Button, Card, Col, Form, InputNumber, Row, Select, Table, Tag, Typography } from 'antd';
import { CalendarOutlined, ProfileOutlined, ScheduleOutlined } from '@ant-design/icons';
import { Link, router, useForm } from '@inertiajs/react';
import StatCard from '../../components/StatCard';
import { fmtDate, fmtDateTime, rupiah } from '../../lib/format';

const monthOptions = (months) => Object.entries(months).map(([num, label]) => ({ value: Number(num), label }));

/**
 * Komisi CS & ADV (Alur F, prompt.md §9): buka/hitung periode + daftar periode.
 * Periode CS memakai jendela 16–15, ADV bulan kalender — terpisah, tak digabung.
 */
export default function Index({ periods, rows, ownerType, year, month, months, css, advs, csWindow, advWindow }) {
    const csForm = useForm({ owner_type: 'cs', owner_id: null, year, month });
    const advForm = useForm({ owner_type: 'adv', owner_id: null, year, month });

    const apply = (values) => {
        router.get(
            '/komisi',
            { owner_type: values.owner_type, month: values.month, year: values.year },
            { preserveState: true, replace: true },
        );
    };

    const openForm = (title, form, owners, hint, key) => (
        <Col xs={24} lg={12}>
            <Card title={title} style={{ marginBottom: 16 }}>
                <Form
                    layout="vertical"
                    onFinish={() => form.post('/komisi/periode', { onSuccess: () => form.reset() })}
                    initialValues={{ owner_id: null, month, year }}
                >
                    <Form.Item label={key === 'cs' ? 'CS' : 'ADV'} required>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            placeholder={key === 'cs' ? '— pilih CS —' : '— pilih ADV —'}
                            value={form.data.owner_id}
                            onChange={(v) => form.setData('owner_id', v)}
                            options={owners.map((o) => ({ value: o.id, label: `${o.name} (${o.code})` }))}
                        />
                    </Form.Item>
                    <Row gutter={12}>
                        <Col span={12}>
                            <Form.Item label="Bulan" required>
                                <Select
                                    value={form.data.month}
                                    onChange={(v) => form.setData('month', v)}
                                    options={monthOptions(months)}
                                />
                            </Form.Item>
                        </Col>
                        <Col span={12}>
                            <Form.Item label="Tahun" required>
                                <InputNumber
                                    min={2020}
                                    max={2100}
                                    style={{ width: '100%' }}
                                    value={form.data.year}
                                    onChange={(v) => form.setData('year', v)}
                                />
                            </Form.Item>
                        </Col>
                    </Row>
                    <Button type="primary" htmlType="submit" loading={form.processing}>
                        Buka &amp; Hitung
                    </Button>
                </Form>
                <Typography.Paragraph type="secondary" style={{ fontSize: 12, marginBottom: 0 }}>
                    {hint}
                </Typography.Paragraph>
            </Card>
        </Col>
    );

    return (
        <>
            <Card style={{ marginBottom: 16 }} styles={{ body: { paddingBottom: 12 } }}>
                <Row justify="space-between" align="middle" wrap={false} gutter={[12, 12]} style={{ marginBottom: 16 }}>
                    <Col flex={1} style={{ minWidth: 0 }}>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Komisi CS &amp; ADV
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Padanan <Typography.Text code>Komisi CS &amp; ADV New</Typography.Text> (Alur F). Isi komisi
                            dihitung dari rantai <Typography.Text code>OutputResi</Typography.Text> per resi. Periode{' '}
                            <strong>CS memakai jendela 16–15</strong>, periode{' '}
                            <strong>ADV memakai bulan kalender</strong> — keduanya terpisah dan tidak pernah digabung
                            (audit §9.4).
                        </Typography.Text>
                    </Col>
                    <Col>
                        <Link href="/aturan-komisi">
                            <Button type="primary" ghost icon={<ProfileOutlined />}>
                                Aturan Komisi
                            </Button>
                        </Link>
                    </Col>
                </Row>
                <Row gutter={[16, 16]}>
                    <Col xs={24} md={12}>
                        <StatCard
                            title={`Jendela CS 16–15 · ${months[month]} ${year}`}
                            value={`${fmtDate(csWindow[0])} – ${fmtDate(csWindow[1])}`}
                            prefix={<CalendarOutlined />}
                            valueStyle={{ fontSize: 18 }}
                        />
                    </Col>
                    <Col xs={24} md={12}>
                        <StatCard
                            title={`Jendela ADV bulan kalender · ${months[month]} ${year}`}
                            value={`${fmtDate(advWindow[0])} – ${fmtDate(advWindow[1])}`}
                            prefix={<ScheduleOutlined />}
                            valueStyle={{ fontSize: 18 }}
                        />
                    </Col>
                </Row>
            </Card>

            <Row gutter={16}>
                {openForm(
                    'Buka / Hitung Periode CS (16–15)',
                    csForm,
                    css,
                    'Contoh: MEI 2026 → resi create-date 16/04/2026 s/d 15/05/2026.',
                    'cs',
                )}
                {openForm(
                    'Buka / Hitung Periode ADV (Bulan Kalender)',
                    advForm,
                    advs,
                    'Contoh: MEI 2026 → resi create-date 01/05/2026 s/d 31/05/2026.',
                    'adv',
                )}
            </Row>

            <Card title="Daftar Periode">
                <Form
                    layout="inline"
                    onFinish={apply}
                    style={{ rowGap: 12, marginBottom: 16 }}
                    initialValues={{ owner_type: ownerType, month, year }}
                >
                    <Form.Item label="Tipe pemilik" name="owner_type">
                        <Select
                            style={{ width: 200 }}
                            options={[
                                { value: 'cs', label: 'CS (16–15)' },
                                { value: 'adv', label: 'ADV (bulan kalender)' },
                            ]}
                        />
                    </Form.Item>
                    <Form.Item label="Bulan (pratinjau jendela)" name="month">
                        <Select style={{ width: 150 }} options={monthOptions(months)} />
                    </Form.Item>
                    <Form.Item label="Tahun" name="year">
                        <InputNumber min={2020} max={2100} style={{ width: 110 }} />
                    </Form.Item>
                    <Form.Item>
                        <Button type="primary" htmlType="submit">
                            Terapkan
                        </Button>
                    </Form.Item>
                </Form>

                <Table
                    size="small"
                    rowKey={(r) => r.period.id}
                    dataSource={rows}
                    locale={{ emptyText: `Belum ada periode ${ownerType.toUpperCase()}. Buka periode lewat form di atas.` }}
                    pagination={{
                        current: periods.current_page,
                        pageSize: periods.per_page,
                        total: periods.total,
                        showSizeChanger: false,
                    }}
                    onChange={(p) =>
                        router.get(
                            '/komisi',
                            { owner_type: ownerType, month, year, page: p.current },
                            { preserveState: true, replace: true },
                        )
                    }
                    columns={[
                        {
                            title: 'Periode',
                            key: 'periode',
                            width: 190,
                            render: (_, r) => (
                                <>
                                    {fmtDate(r.period.start_date)} – {fmtDate(r.period.end_date)}
                                    <Typography.Text type="secondary" style={{ fontSize: 12, display: 'block' }}>
                                        {r.period.period_type}
                                    </Typography.Text>
                                </>
                            ),
                        },
                        { title: 'Pemilik', key: 'owner', width: 150, render: (_, r) => r.owner },
                        {
                            title: 'Status',
                            key: 'status',
                            width: 130,
                            render: (_, r) =>
                                r.period.closed_at ? (
                                    <>
                                        <Tag color="orange">ditutup</Tag>
                                        <Typography.Text type="secondary" style={{ fontSize: 12, display: 'block' }}>
                                            {fmtDateTime(r.period.closed_at)}
                                        </Typography.Text>
                                    </>
                                ) : (
                                    <Tag color="green">terbuka</Tag>
                                ),
                        },
                        { title: 'Resi', key: 'qty', width: 60, align: 'right', render: (_, r) => r.stats.qty },
                        { title: 'On Progress', key: 'op', width: 110, align: 'right', render: (_, r) => rupiah(r.stats.on_progress) },
                        { title: 'Payable', key: 'pay', width: 110, align: 'right', render: (_, r) => rupiah(r.stats.payable) },
                        { title: 'Saldo Lalu', key: 'carried', width: 110, align: 'right', render: (_, r) => rupiah(r.stats.carried) },
                        { title: 'Dibayar', key: 'paid', width: 110, align: 'right', render: (_, r) => rupiah(r.stats.paid) },
                        { title: 'Belum Dibayar', key: 'unpaid', width: 120, align: 'right', render: (_, r) => <b>{rupiah(r.stats.unpaid)}</b> },
                        {
                            title: 'Komisi Total',
                            key: 'total',
                            width: 150,
                            align: 'right',
                            render: (_, r) => (
                                <>
                                    {rupiah(r.stats.komisi_total)}
                                    <Typography.Text type="secondary" style={{ fontSize: 12, display: 'block' }}>
                                        + input admin {rupiah(r.stats.admin_input)}
                                    </Typography.Text>
                                </>
                            ),
                        },
                        {
                            title: '',
                            key: 'aksi',
                            width: 70,
                            render: (_, r) => <Link href={`/komisi/periode/${r.period.id}`}>detail</Link>,
                        },
                    ]}
                />
            </Card>
        </>
    );
}
