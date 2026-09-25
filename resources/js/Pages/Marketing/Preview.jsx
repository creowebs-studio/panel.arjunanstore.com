import React from 'react';
import { Alert, App, Button, Card, Col, Row, Space, Statistic, Table, Tag, Typography } from 'antd';
import { Link, router } from '@inertiajs/react';
import StatCard from '../../components/StatCard';
import { rupiah } from '../../lib/format';

const RESULT_COLOR = { error: 'red', duplicate: 'orange', new: 'green', updated: 'blue' };
const num = (v) => Number(v ?? 0);

/**
 * Pratinjau Impor Marketing (prompt.md §8): hasil baca file laporan kampanye
 * sebelum diproses, atau laporan setelah batch selesai.
 */
export default function Preview({ batch, rows, summary }) {
    const { modal } = App.useApp();
    const confirmProcess = () =>
        modal.confirm({
            title: 'Proses impor laporan kampanye?',
            onOk: () => router.post(`/marketing/impor/${batch.id}/proses`),
        });

    return (
        <>
            <Card style={{ marginBottom: 16 }} styles={{ body: { paddingBottom: 12 } }}>
                <Row justify="space-between" align="middle" wrap={false} gutter={[12, 12]}>
                    <Col flex={1} style={{ minWidth: 0 }}>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Pratinjau Impor Kampanye
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            File: <strong>{batch.source_filename}</strong> · status{' '}
                            <Tag color={batch.status === 'done' ? 'green' : 'orange'}>{batch.status}</Tag> · {batch.total_rows}{' '}
                            baris
                        </Typography.Text>
                    </Col>
                    <Col flex="none">
                        <Link href="/marketing">
                            <Button>Kembali ke Marketing</Button>
                        </Link>
                    </Col>
                </Row>
                <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
                    <Col xs={12} md={6}>
                        <StatCard title="Baru" value={batch.new_rows} background="#f0fdf4" valueStyle={{ color: '#16a34a' }} />
                    </Col>
                    <Col xs={12} md={6}>
                        <StatCard title="Diperbarui" value={batch.updated_rows} background="#eff6ff" />
                    </Col>
                    <Col xs={12} md={6}>
                        <StatCard title="Duplikat" value={batch.duplicate_rows} background="#fff7ed" valueStyle={{ color: '#d97706' }} />
                    </Col>
                    <Col xs={12} md={6}>
                        <StatCard title="Error" value={batch.error_rows} background="#fef2f2" valueStyle={{ color: '#dc2626' }} />
                    </Col>
                </Row>

                {!['done', 'processing'].includes(batch.status) ? (
                    <Space style={{ marginTop: 16 }}>
                        <Button type="primary" onClick={confirmProcess}>
                            Konfirmasi &amp; Proses Impor
                        </Button>
                        <Link href="/marketing">
                            <Button>Batal</Button>
                        </Link>
                    </Space>
                ) : (
                    <Space style={{ marginTop: 16 }}>
                        <Link href="/rekap-adv">
                            <Button type="primary">Lihat Rekap ADV</Button>
                        </Link>
                    </Space>
                )}
            </Card>

            {Object.keys(summary).length > 0 && (
                <Card title="Ringkasan per Hasil" style={{ marginBottom: 16 }}>
                    <Row gutter={16}>
                        {Object.entries(summary).map(([result, count]) => (
                            <Col xs={12} md={4} key={result}>
                                <Statistic
                                    title={result}
                                    value={count}
                                    valueStyle={{ color: RESULT_COLOR[result] === 'red' ? '#dc2626' : undefined }}
                                />
                            </Col>
                        ))}
                    </Row>
                </Card>
            )}

            <Card title="Baris (maks. 100 pertama)">
                <Table
                    size="small"
                    rowKey="row_number"
                    pagination={false}
                    dataSource={rows}
                    locale={{ emptyText: 'Tidak ada baris terbaca.' }}
                    columns={[
                        { title: '#', dataIndex: 'row_number', width: 50 },
                        {
                            title: 'Kampanye',
                            key: 'kampanye',
                            width: 190,
                            render: (_, r) => <Typography.Text code>{r.mapped_data?.campaign_name ?? '—'}</Typography.Text>,
                        },
                        {
                            title: 'Periode',
                            key: 'periode',
                            width: 170,
                            render: (_, r) => `${r.mapped_data?.date_start ?? '?'} → ${r.mapped_data?.date_end ?? '?'}`,
                        },
                        { title: 'Hasil', key: 'hasil', width: 70, render: (_, r) => r.mapped_data?.results ?? '—' },
                        { title: 'Jangkauan', key: 'reach', width: 100, align: 'right', render: (_, r) => num(r.mapped_data?.reach).toLocaleString('id-ID') },
                        { title: 'Impressi', key: 'imp', width: 100, align: 'right', render: (_, r) => num(r.mapped_data?.impressions).toLocaleString('id-ID') },
                        { title: 'Klik', key: 'klik', width: 80, align: 'right', render: (_, r) => num(r.mapped_data?.clicks_link).toLocaleString('id-ID') },
                        { title: 'Spend', key: 'spend', width: 120, align: 'right', render: (_, r) => rupiah(num(r.mapped_data?.spend_raw)) },
                        { title: 'Spend+PPN', key: 'spendppn', width: 120, align: 'right', render: (_, r) => rupiah(num(r.mapped_data?.spend_raw) * 1.12) },
                        {
                            title: 'Status',
                            key: 'status',
                            width: 130,
                            render: (_, r) => (
                                <>
                                    <Tag color={RESULT_COLOR[r.result] ?? 'default'}>{r.result}</Tag>
                                    {r.error_reason && (
                                        <Typography.Text type="danger" style={{ fontSize: 12 }}>
                                            {r.error_reason}
                                        </Typography.Text>
                                    )}
                                </>
                            ),
                        },
                    ]}
                />
            </Card>
        </>
    );
}
