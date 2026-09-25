import React from 'react';
import { Alert, Button, Card, Form, Input, Typography } from 'antd';
import { LockOutlined, MailOutlined, ShopOutlined } from '@ant-design/icons';
import { useForm, usePage } from '@inertiajs/react';

/** Halaman masuk — dirender tanpa shell aplikasi (guest). */
export default function Login() {
    const { errors } = usePage().props;
    const { data, setData, post, processing } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = () => post('/login');

    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: 'linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%)',
                padding: 16,
            }}
        >
            <Card style={{ width: 380, boxShadow: '0 8px 24px rgba(0,0,0,.25)' }}>
                <div style={{ textAlign: 'center', marginBottom: 20 }}>
                    <ShopOutlined style={{ fontSize: 32, color: '#2563eb' }} />
                    <Typography.Title level={4} style={{ margin: '8px 0 0' }}>
                        ARJ Panel
                    </Typography.Title>
                    <Typography.Text type="secondary">Panel operasional arjunanstore</Typography.Text>
                </div>

                {errors.email && (
                    <Alert type="error" message={errors.email} showIcon style={{ marginBottom: 16 }} />
                )}

                <Form layout="vertical" onFinish={submit}>
                    <Form.Item label="Email" required>
                        <Input
                            prefix={<MailOutlined />}
                            type="email"
                            autoFocus
                            placeholder="email@contoh.id"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Kata sandi" required>
                        <Input.Password
                            prefix={<LockOutlined />}
                            placeholder="••••••••"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    </Form.Item>
                    <Button type="primary" htmlType="submit" block loading={processing}>
                        Masuk
                    </Button>
                </Form>
            </Card>
        </div>
    );
}
