import React from 'react';
import { Card, Statistic } from 'antd';

/**
 * Ubin statistik ringkas — pola visual konsisten sepanel (dipakai Dashboard/Rekap
 * dan halaman daftar). Card borderless berlatar lembut + Statistic di dalamnya.
 */
export default function StatCard({
    title,
    value,
    formatter,
    prefix,
    suffix,
    precision,
    valueStyle,
    background = '#fafafa',
    style,
    styles,
    ...rest
}) {
    return (
        <Card
            size="small"
            variant="borderless"
            style={{ background, height: '100%', ...style }}
            styles={{ body: { padding: '12px 16px', ...styles?.body } }}
            {...rest}
        >
            <Statistic
                title={title}
                value={value}
                formatter={formatter}
                prefix={prefix}
                suffix={suffix}
                precision={precision}
                valueStyle={{ fontSize: 22, ...valueStyle }}
            />
        </Card>
    );
}
