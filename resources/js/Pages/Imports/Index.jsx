import React from 'react';
import { Button, Card, Col, Form, Row, Select, Space, Table, Tag, Typography, Upload } from 'antd';
import { UploadOutlined } from '@ant-design/icons';
import { Link, useForm } from '@inertiajs/react';
import StatCard from '../../components/StatCard';

const BATCH_COLOR = { done: 'green', preview: 'orange', processing: 'blue' };

/**
 * Impor Hasil Pengiriman (Alur C, prompt.md §6): unggah file CSV/TSV hasil
 * Mengantar/Lincah. File yang sama diimpor ulang tidak menggandakan resi.
 */
export default function Index({ batches }) {
    const { data, setData, post, processing, errors } = useForm({
        platform: 'mengantar',
        file: null,
    });

    const submit = () => post('/impor');

    const columns = [
        { title: 'File', dataIndex: 'source_filename' },
        { title: 'Platform', key: 'platform', width: 110, render: (_, b) => <Typography.Text style={{ textTransform: 'capitalize' }}>{b.platform}</Typography.Text> },
        {
            title: 'Status',
            dataIndex: 'status',
            width: 110,
            render: (v) => <Tag color={BATCH_COLOR[v] ?? 'default'}>{v}</Tag>,
        },
        { title: 'Total', dataIndex: 'total_rows', width: 70, align: 'right' },
        { title: 'Baru', dataIndex: 'new_rows', width: 60, align: 'right' },
        { title: 'Update', dataIndex: 'updated_rows', width: 70, align: 'right' },
        { title: 'Duplikat', dataIndex: 'duplicate_rows', width: 80, align: 'right' },
        { title: 'Error', dataIndex: 'error_rows', width: 60, align: 'right' },
        { title: 'Oleh', key: 'oleh', width: 130, render: (_, b) => b.uploader?.name ?? '—' },
        {
            title: '',
            key: 'aksi',
            width: 90,
            render: (_, b) => (
                <Link href={`/impor/${b.id}`}>
                    <Button size="small">Detail</Button>
                </Link>
            ),
        },
    ];

    return (
        <>
            <Card style={{ marginBottom: 16 }} styles={{ body: { paddingBottom: 12 } }}>
                <Row justify="space-between" align="middle" wrap={false} gutter={[12, 12]} style={{ marginBottom: 16 }}>
                    <Col flex={1} style={{ minWidth: 0 }}>
                        <Typography.Title level={4} style={{ margin: 0 }}>
                            Impor Hasil Pengiriman
                        </Typography.Title>
                        <Typography.Text type="secondary">
                            Unggah file hasil Mengantar/Lincah. Alur: unggah → baca → pratinjau → konfirmasi → proses.
                            File yang sama diimpor ulang tidak akan menggandakan resi.
                        </Typography.Text>
                    </Col>
                    <Col flex="none">
                        <Space size={12}>
                            <StatCard title="Total Batch" value={batches.length} />
                            <StatCard
                                title="Baru Diproses"
                                value={batches.reduce((s, b) => s + Number(b.new_rows ?? 0), 0)}
                                background="#f0fdf4"
                            />
                            <StatCard
                                title="Baris Error"
                                value={batches.reduce((s, b) => s + Number(b.error_rows ?? 0), 0)}
                                background="#fef2f2"
                                valueStyle={{ color: '#dc2626' }}
                            />
                        </Space>
                    </Col>
                </Row>
                <Form layout="inline" onFinish={submit} style={{ rowGap: 12 }}>
                    <Form.Item label="Platform" required validateStatus={errors.platform ? 'error' : undefined} help={errors.platform}>
                        <Select
                            style={{ width: 300 }}
                            value={data.platform}
                            onChange={(v) => setData('platform', v)}
                            options={[
                                { value: 'mengantar', label: 'Mengantar' },
                                { value: 'lincah', label: 'Lincah (butuh contoh file utk finalisasi)' },
                            ]}
                        />
                    </Form.Item>
                    <Form.Item label="File CSV/TSV" required validateStatus={errors.file ? 'error' : undefined} help={errors.file}>
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
                            Unggah &amp; Baca
                        </Button>
                    </Form.Item>
                </Form>
            </Card>

            <Card title="Riwayat Batch Impor">
                <Table
                    size="small"
                    rowKey="id"
                    columns={columns}
                    dataSource={batches}
                    pagination={false}
                    locale={{ emptyText: 'Belum ada impor.' }}
                />
            </Card>
        </>
    );
}
