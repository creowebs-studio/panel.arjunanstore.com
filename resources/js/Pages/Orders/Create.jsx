import React from 'react';
import { Button, Card, Col, DatePicker, Form, Input, InputNumber, Row, Select, Space, Typography } from 'antd';
import dayjs from 'dayjs';
import { router, useForm } from '@inertiajs/react';

/**
 * Input Order (prompt.md §4.5): nomor telepon dinormalisasi & diklasifikasi
 * otomatis oleh backend; hasil klasifikasi tampil di halaman Daftar Order.
 */
export default function Create({ csAgents, products }) {
    const { data, setData, post, processing, errors } = useForm({
        order_date: dayjs().format('YYYY-MM-DD'),
        cs_agent_id: null,
        customer_name: '',
        phone: '',
        address_detail: '',
        kelurahan: '',
        kecamatan: '',
        kabupaten: '',
        kota: '',
        provinsi: '',
        zip_code: '',
        product_id: null,
        product_detail: '',
        qty: 1,
        payment_method: 'COD',
        price: null,
        weight: 1,
        aggregator: 'mengantar',
        expedition: '',
    });

    const submit = () => post('/orders');

    const item = (name, label, required, children, span = 8) => (
        <Col xs={24} md={span}>
            <Form.Item
                label={label}
                required={required}
                validateStatus={errors[name] ? 'error' : undefined}
                help={errors[name]}
            >
                {children}
            </Form.Item>
        </Col>
    );

    return (
        <Card>
            <Row justify="space-between" align="middle" wrap={false} gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col flex={1} style={{ minWidth: 0 }}>
                    <Typography.Title level={4} style={{ margin: 0 }}>
                        Input Order
                    </Typography.Title>
                    <Typography.Text type="secondary">
                        Nomor telepon akan dinormalisasi &amp; diklasifikasi otomatis (Positif/Negatif/Perlu Ditinjau)
                        berdasarkan riwayat pengiriman.
                    </Typography.Text>
                </Col>
                <Col flex="none">
                    <Button onClick={() => router.get('/orders')}>Kembali ke Daftar Order</Button>
                </Col>
            </Row>

            <Form layout="vertical" onFinish={submit}>
                <Row gutter={16}>
                    {item(
                        'order_date',
                        'Tanggal Order *',
                        true,
                        <DatePicker
                            style={{ width: '100%' }}
                            value={data.order_date ? dayjs(data.order_date) : null}
                            onChange={(v) => setData('order_date', v ? v.format('YYYY-MM-DD') : null)}
                        />,
                    )}
                    {item(
                        'cs_agent_id',
                        'Kode CS',
                        false,
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="— pilih CS —"
                            value={data.cs_agent_id}
                            onChange={(v) => setData('cs_agent_id', v)}
                            options={csAgents.map((c) => ({ value: c.id, label: `${c.code} · ${c.name}` }))}
                        />,
                    )}
                    {item(
                        'product_id',
                        'Produk',
                        false,
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="— pilih produk —"
                            value={data.product_id}
                            onChange={(v) => setData('product_id', v)}
                            options={products.map((p) => ({ value: p.id, label: `${p.code} · ${p.name}` }))}
                        />,
                    )}
                </Row>

                <Row gutter={16}>
                    {item(
                        'customer_name',
                        'Nama Customer *',
                        true,
                        <Input value={data.customer_name} onChange={(e) => setData('customer_name', e.target.value)} />,
                    )}
                    {item(
                        'phone',
                        'No. Telepon *',
                        true,
                        <Input placeholder="08xx / 628xx" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />,
                    )}
                    {item(
                        'product_detail',
                        'Detail Pesanan',
                        false,
                        <Input
                            placeholder="mis. 2 PCS Sepatu"
                            value={data.product_detail}
                            onChange={(e) => setData('product_detail', e.target.value)}
                        />,
                    )}
                </Row>

                {item(
                    'address_detail',
                    'Detail Alamat *',
                    true,
                    <Input value={data.address_detail} onChange={(e) => setData('address_detail', e.target.value)} />,
                    24,
                )}

                <Row gutter={16}>
                    {item(
                        'kelurahan',
                        'Kelurahan',
                        false,
                        <Input value={data.kelurahan} onChange={(e) => setData('kelurahan', e.target.value)} />,
                    )}
                    {item(
                        'kecamatan',
                        'Kecamatan',
                        false,
                        <Input value={data.kecamatan} onChange={(e) => setData('kecamatan', e.target.value)} />,
                    )}
                    {item(
                        'kabupaten',
                        'Kabupaten/Kota',
                        false,
                        <Input value={data.kabupaten} onChange={(e) => setData('kabupaten', e.target.value)} />,
                    )}
                </Row>

                <Row gutter={16}>
                    {item(
                        'provinsi',
                        'Provinsi',
                        false,
                        <Input value={data.provinsi} onChange={(e) => setData('provinsi', e.target.value)} />,
                    )}
                    {item(
                        'zip_code',
                        'Kode Pos',
                        false,
                        <Input value={data.zip_code} onChange={(e) => setData('zip_code', e.target.value)} />,
                    )}
                    {item(
                        'qty',
                        'Jumlah (Qty) *',
                        true,
                        <InputNumber min={1} style={{ width: '100%' }} value={data.qty} onChange={(v) => setData('qty', v)} />,
                    )}
                </Row>

                <Row gutter={16}>
                    {item(
                        'payment_method',
                        'Jenis Pembayaran *',
                        true,
                        <Select
                            value={data.payment_method}
                            onChange={(v) => setData('payment_method', v)}
                            options={[
                                { value: 'COD', label: 'COD' },
                                { value: 'NON COD', label: 'NON COD' },
                            ]}
                        />,
                    )}
                    {item(
                        'price',
                        'Harga *',
                        true,
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={data.price}
                            onChange={(v) => setData('price', v)}
                            formatter={(v) => `Rp ${String(v).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`}
                            parser={(v) => v.replace(/Rp\s?|\./g, '')}
                        />,
                    )}
                    {item(
                        'weight',
                        'Berat (kg)',
                        false,
                        <InputNumber min={0} style={{ width: '100%' }} value={data.weight} onChange={(v) => setData('weight', v)} />,
                    )}
                    {item(
                        'aggregator',
                        'Agregator *',
                        true,
                        <Select
                            value={data.aggregator}
                            onChange={(v) => setData('aggregator', v)}
                            options={[
                                { value: 'mengantar', label: 'Mengantar' },
                                { value: 'lincah', label: 'Lincah' },
                            ]}
                        />,
                    )}
                </Row>

                <Row gutter={16}>
                    {item(
                        'expedition',
                        'Ekspedisi / Kurir',
                        false,
                        <Input placeholder="mis. JNE" value={data.expedition} onChange={(e) => setData('expedition', e.target.value)} />,
                    )}
                </Row>

                <Space>
                    <Button type="primary" htmlType="submit" loading={processing}>
                        Simpan &amp; Klasifikasikan
                    </Button>
                    <Button onClick={() => router.get('/orders')}>Batal</Button>
                </Space>
            </Form>
        </Card>
    );
}
