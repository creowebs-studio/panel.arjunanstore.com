import React from 'react';
import { App, Button, Card, Checkbox, Col, Form, Input, Row, Select, Space, Table, Typography } from 'antd';
import { Link, router, useForm } from '@inertiajs/react';
import StatCard from '../../components/StatCard';

/** Satu baris pemetaan yang bisa disunting langsung (PATCH per baris, tercatat audit). */
function MappingRow({ m, internal }) {
    const { data, setData, patch, processing } = useForm({
        keterangan: m.keterangan ?? '',
        status_internal: m.status_internal,
        is_active: Boolean(m.is_active),
    });

    return (
        <Form
            layout="inline"
            style={{ rowGap: 8, marginBottom: 8 }}
            onFinish={() => patch(`/pemetaan-status/${m.id}`)}
        >
            <Form.Item style={{ width: 100, marginBottom: 0 }}>
                <Typography.Text style={{ textTransform: 'capitalize' }}>{m.platform}</Typography.Text>
            </Form.Item>
            <Form.Item style={{ width: 150, marginBottom: 0 }}>
                <Typography.Text code>{m.status_system}</Typography.Text>
            </Form.Item>
            <Form.Item style={{ minWidth: 180, marginBottom: 0 }}>
                <Input
                    value={data.keterangan}
                    maxLength={255}
                    placeholder="keterangan"
                    onChange={(e) => setData('keterangan', e.target.value)}
                />
            </Form.Item>
            <Form.Item style={{ width: 130, marginBottom: 0 }}>
                <Select
                    value={data.status_internal}
                    onChange={(v) => setData('status_internal', v)}
                    options={internal.map((s) => ({ value: s, label: s }))}
                />
            </Form.Item>
            <Form.Item style={{ marginBottom: 0 }}>
                <Checkbox checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)}>
                    Aktif
                </Checkbox>
            </Form.Item>
            <Form.Item style={{ marginBottom: 0 }}>
                <Button type="primary" htmlType="submit" loading={processing}>
                    Simpan
                </Button>
            </Form.Item>
        </Form>
    );
}

/**
 * Pemetaan Status (prompt.md §7): agregator → 5 state internal, mengikuti
 * workbook; perubahan tercatat audit. Termasuk daftar kerja status belum
 * terpetakan + sinkronisasi ke resi lama.
 */
export default function Index({ mappings, internal, unmapped }) {
    const { modal } = App.useApp();
    const { data, setData, post, processing, errors } = useForm({
        platform: 'mengantar',
        status_system: '',
        status_internal: 'packing',
        keterangan: '',
    });

    const confirmSync = () =>
        modal.confirm({
            title: 'Terapkan pemetaan terkini ke resi lama yang belum terpetakan?',
            onOk: () => router.post('/pemetaan-status/sinkron'),
        });

    return (
        <>
            <Card style={{ marginBottom: 16 }} styles={{ body: { paddingBottom: 12 } }}>
                <Row justify="space-between" align="middle" wrap={false} gutter={[12, 12]}>
                    <Col flex={1} style={{ minWidth: 0 }}>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Pemetaan Status Agregator → 5 State Internal
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Mengikuti pemetaan workbook (audit STAGE1 §7.2). State internal: <b>packing, dikirim, undel,
                            diterima, retur</b>. Perubahan dicatat di jejak audit. Platform <b>general</b> berlaku untuk semua
                            agregator bila status spesifik tidak ada.
                        </Typography.Text>
                    </Col>
                    <Col flex="none">
                        <Space size={12}>
                            <StatCard title="Total Pemetaan" value={mappings.length} />
                            <StatCard
                                title="Belum Terpetakan"
                                value={unmapped.length}
                                background={unmapped.length ? '#fef2f2' : '#f0fdf4'}
                                valueStyle={unmapped.length ? { color: '#dc2626' } : { color: '#16a34a' }}
                            />
                        </Space>
                    </Col>
                </Row>
            </Card>

            <Card title="Daftar Pemetaan" style={{ marginBottom: 16 }}>
                {mappings.length === 0 ? (
                    <Typography.Text type="secondary">
                        Belum ada pemetaan — jalankan seeder CarrierStatusMappingSeeder.
                    </Typography.Text>
                ) : (
                    mappings.map((m) => <MappingRow key={m.id} m={m} internal={internal} />)
                )}
            </Card>

            <Card title="Tambah Pemetaan Baru" style={{ marginBottom: 16 }}>
                <Form layout="inline" onFinish={() => post('/pemetaan-status')} style={{ rowGap: 12 }}>
                    <Form.Item label="Platform" required validateStatus={errors.platform ? 'error' : undefined} help={errors.platform}>
                        <Select
                            style={{ width: 160 }}
                            value={data.platform}
                            onChange={(v) => setData('platform', v)}
                            options={[
                                { value: 'mengantar', label: 'Mengantar' },
                                { value: 'lincah', label: 'Lincah' },
                                { value: 'general', label: 'General (semua)' },
                            ]}
                        />
                    </Form.Item>
                    <Form.Item
                        label="Status Sistem (apa adanya dari file)"
                        required
                        validateStatus={errors.status_system ? 'error' : undefined}
                        help={errors.status_system}
                    >
                        <Input
                            style={{ width: 220 }}
                            maxLength={64}
                            placeholder="mis. ON DELIVERY"
                            value={data.status_system}
                            onChange={(e) => setData('status_system', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="State Internal" required validateStatus={errors.status_internal ? 'error' : undefined} help={errors.status_internal}>
                        <Select
                            style={{ width: 130 }}
                            value={data.status_internal}
                            onChange={(v) => setData('status_internal', v)}
                            options={internal.map((s) => ({ value: s, label: s }))}
                        />
                    </Form.Item>
                    <Form.Item label="Keterangan">
                        <Input
                            style={{ width: 180 }}
                            maxLength={255}
                            value={data.keterangan}
                            onChange={(e) => setData('keterangan', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item>
                        <Button type="primary" htmlType="submit" loading={processing}>
                            Tambah Pemetaan
                        </Button>
                    </Form.Item>
                </Form>
            </Card>

            <Card title="Status Belum Terpetakan (daftar kerja)" style={{ marginBottom: 16 }}>
                <Typography.Paragraph type="secondary" style={{ fontSize: 13 }}>
                    Status mentah dari master resi yang belum punya pemetaan aktif. Setelah menambahkan pemetaan di
                    atas, klik <b>Sinkronkan ke Resi Lama</b> — resi yang statusnya kini punya pemetaan akan diperbarui
                    dan Data Error <i>status_unmapped</i>-nya ditutup otomatis. Untuk baris yang benar-benar error
                    (kunci kosong), gunakan <b>proses ulang baris error</b> pada batch impor terkait.
                </Typography.Paragraph>
                <Button type="primary" style={{ marginBottom: 12 }} onClick={confirmSync}>
                    Sinkronkan ke Resi Lama
                </Button>
                <Table
                    size="small"
                    rowKey={(u) => `${u.platform}|${u.status_raw}`}
                    pagination={false}
                    dataSource={unmapped}
                    locale={{ emptyText: 'Semua status yang pernah masuk sudah terpetakan.' }}
                    columns={[
                        {
                            title: 'Platform',
                            dataIndex: 'platform',
                            width: 120,
                            render: (v) => <Typography.Text style={{ textTransform: 'capitalize' }}>{v}</Typography.Text>,
                        },
                        { title: 'Status Mentah', dataIndex: 'status_raw', render: (v) => <Typography.Text code>{v}</Typography.Text> },
                        { title: 'Jumlah Resi', dataIndex: 'jumlah', width: 110, align: 'right' },
                    ]}
                />
            </Card>

            <Space>
                <Link href="/impor">← Ke Impor</Link>
            </Space>
        </>
    );
}
