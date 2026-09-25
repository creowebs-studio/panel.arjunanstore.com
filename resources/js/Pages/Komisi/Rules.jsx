import React from 'react';
import { Button, Card, Col, DatePicker, Form, Input, Row, Select, Statistic, Table, Tag, Typography } from 'antd';
import dayjs from 'dayjs';
import { useForm } from '@inertiajs/react';
import { fmtDate, fmtDateTime } from '../../lib/format';

const PARAM_EXAMPLES = {
    order_tier: '{"basis":"laba_kotor_tanpa_diskon","brackets":[{"lt":26000,"amount":0},{"lt":89000,"amount":5000},{"gap_to":99001,"amount":0},{"gte":99001,"percent":10}]}',
    transfer_bonus: '{"amount":1000}',
    cod_rate: '{"percent":3}',
    spend_ppn: '{"multiplier":1.12}',
};

/**
 * Aturan Komisi (prompt.md §9): riwayat tarif effective-dated. Versi baru tidak
 * mengubah periode yang sudah dihitung/ditutup — kalkulator memilih tarif
 * yang berlaku pada TANGGAL RESI (audit §9.1/§9.2).
 */
export default function Rules({ rules, keys }) {
    const { data, setData, post, processing, errors } = useForm({
        key: keys[0] ?? 'order_tier',
        applies_to: 'cs',
        effective_from: dayjs().format('YYYY-MM-DD'),
        name: '',
        params: '',
    });

    const groups = Object.entries(rules ?? {});
    const versions = groups.flatMap(([, list]) => list);

    return (
        <>
            <Card style={{ marginBottom: 16 }}>
                <Typography.Title level={4} style={{ marginTop: 0 }}>
                    Aturan Komisi — Riwayat Tarif
                </Typography.Title>
                <Typography.Paragraph type="secondary">
                    Tarif <strong>bertanggal-berlaku</strong> (effective-dated): versi baru tidak mengubah periode yang
                    sudah dihitung/ditutup karena kalkulator selalu memilih tarif yang berlaku pada{' '}
                    <em>tanggal resi</em>. Menyimpan versi baru otomatis menutup rentang versi sebelumnya (audit
                    §9.1/§9.2).
                </Typography.Paragraph>
                <Row gutter={16}>
                    <Col xs={12} md={8}>
                        <Statistic title="Versi Tarif" value={versions.length} />
                    </Col>
                    <Col xs={12} md={8}>
                        <Statistic title="Kunci Aturan" value={groups.length} />
                    </Col>
                    <Col xs={12} md={8}>
                        <Statistic title="Aktif" value={versions.filter((r) => r.is_active && !r.effective_to).length} valueStyle={{ color: '#16a34a' }} />
                    </Col>
                </Row>
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                    ⚠️ Gap sumber §10 U5 (AF 89.000–99.001 → 0) dan U6 (multi paket tanpa formula) dipertahankan apa
                    adanya sampai pemilik memverifikasi.
                </Typography.Text>
            </Card>

            <Card title="Tambah Versi Tarif Baru" style={{ marginBottom: 16 }}>
                <Form layout="vertical" onFinish={() => post('/aturan-komisi')} style={{ maxWidth: 860 }}>
                    <Row gutter={16}>
                        <Col xs={24} md={8}>
                            <Form.Item label="Kunci" required validateStatus={errors.key ? 'error' : undefined} help={errors.key}>
                                <Select
                                    value={data.key}
                                    onChange={(v) => setData('key', v)}
                                    options={keys.map((k) => ({ value: k, label: k }))}
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <Form.Item label="Berlaku untuk" required validateStatus={errors.applies_to ? 'error' : undefined} help={errors.applies_to}>
                                <Select
                                    value={data.applies_to}
                                    onChange={(v) => setData('applies_to', v)}
                                    options={[
                                        { value: 'cs', label: 'CS' },
                                        { value: 'adv', label: 'ADV' },
                                        { value: 'shipment', label: 'Resi (shipment)' },
                                    ]}
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={8}>
                            <Form.Item label="Berlaku mulai" required validateStatus={errors.effective_from ? 'error' : undefined} help={errors.effective_from}>
                                <DatePicker
                                    style={{ width: '100%' }}
                                    value={data.effective_from ? dayjs(data.effective_from) : null}
                                    onChange={(v) => setData('effective_from', v ? v.format('YYYY-MM-DD') : null)}
                                />
                            </Form.Item>
                        </Col>
                    </Row>
                    <Form.Item label="Nama versi" required validateStatus={errors.name ? 'error' : undefined} help={errors.name}>
                        <Input
                            maxLength={255}
                            placeholder="mis. Tier Komisi CS Order (revisi 2026)"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Params (JSON objek)" required validateStatus={errors.params ? 'error' : undefined} help={errors.params}>
                        <Input.TextArea
                            rows={5}
                            placeholder={PARAM_EXAMPLES.order_tier}
                            value={data.params}
                            onChange={(e) => setData('params', e.target.value)}
                        />
                    </Form.Item>
                    <Typography.Paragraph type="secondary" style={{ fontSize: 12 }}>
                        Contoh:{' '}
                        {Object.entries(PARAM_EXAMPLES).map(([k, v], i) => (
                            <React.Fragment key={k}>
                                {i > 0 && ' · '}
                                <Typography.Text code>{k}</Typography.Text> → <Typography.Text code>{v}</Typography.Text>
                            </React.Fragment>
                        ))}
                    </Typography.Paragraph>
                    <Button type="primary" htmlType="submit" loading={processing}>
                        Simpan Versi Baru
                    </Button>
                </Form>
            </Card>

            {groups.length === 0 ? (
                <Card>
                    <Typography.Text type="secondary">
                        Belum ada aturan komisi. Jalankan seeder atau tambah versi baru di atas.
                    </Typography.Text>
                </Card>
            ) : (
                groups.map(([key, list]) => (
                    <Card key={key} title={<Typography.Text code>{key}</Typography.Text>} style={{ marginBottom: 16 }}>
                        <Typography.Paragraph type="secondary">{list[0]?.name}</Typography.Paragraph>
                        <Table
                            size="small"
                            rowKey="id"
                            pagination={false}
                            dataSource={list}
                            columns={[
                                {
                                    title: 'Berlaku',
                                    key: 'berlaku',
                                    width: 190,
                                    render: (_, r) => `${fmtDate(r.effective_from)} s/d ${r.effective_to ? fmtDate(r.effective_to) : '∞'}`,
                                },
                                { title: 'Untuk', dataIndex: 'applies_to', width: 100 },
                                {
                                    title: 'Params',
                                    key: 'params',
                                    render: (_, r) => (
                                        <Typography.Text code style={{ fontSize: 12 }}>
                                            {JSON.stringify(r.params)}
                                        </Typography.Text>
                                    ),
                                },
                                {
                                    title: 'Status',
                                    key: 'status',
                                    width: 100,
                                    render: (_, r) =>
                                        r.is_active && !r.effective_to ? <Tag color="green">aktif</Tag> : <Tag color="orange">historis</Tag>,
                                },
                                { title: 'Direview oleh', key: 'reviewer', width: 140, render: (_, r) => r.reviewer?.name ?? '—' },
                                { title: 'Dibuat', dataIndex: 'created_at', width: 130, render: fmtDateTime },
                            ]}
                        />
                    </Card>
                ))
            )}
        </>
    );
}
