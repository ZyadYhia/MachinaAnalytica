import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    ChartOptions,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { Bar, Line, Pie } from 'react-chartjs-2';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
);

interface ChartData {
    type: 'line' | 'bar' | 'area' | 'pie';
    title: string;
    data: Array<Record<string, any>>;
    xKey: string;
    yKey: string;
    xLabel?: string;
    yLabel?: string;
}

interface ChartRendererProps {
    chartData: ChartData;
}

const COLORS = [
    'rgba(99, 102, 241, 0.8)', // Indigo
    'rgba(234, 88, 12, 0.8)', // Orange
    'rgba(34, 197, 94, 0.8)', // Green
    'rgba(239, 68, 68, 0.8)', // Red
    'rgba(168, 85, 247, 0.8)', // Purple
    'rgba(14, 165, 233, 0.8)', // Sky
    'rgba(236, 72, 153, 0.8)', // Pink
    'rgba(251, 191, 36, 0.8)', // Amber
];

const BORDER_COLORS = [
    'rgba(99, 102, 241, 1)',
    'rgba(234, 88, 12, 1)',
    'rgba(34, 197, 94, 1)',
    'rgba(239, 68, 68, 1)',
    'rgba(168, 85, 247, 1)',
    'rgba(14, 165, 233, 1)',
    'rgba(236, 72, 153, 1)',
    'rgba(251, 191, 36, 1)',
];

export function ChartRenderer({ chartData }: ChartRendererProps) {
    const { type, title, data, xKey, yKey, xLabel, yLabel } = chartData;

    // Extract labels and values from data
    const labels = data.map((item) => item[xKey]);
    const values = data.map((item) => item[yKey]);

    // Common chart options
    const commonOptions: ChartOptions<any> = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top' as const,
                labels: {
                    color: 'hsl(var(--foreground))',
                    font: {
                        size: 12,
                        weight: '500',
                    },
                    padding: 15,
                },
            },
            tooltip: {
                backgroundColor: 'hsl(var(--popover))',
                titleColor: 'hsl(var(--popover-foreground))',
                bodyColor: 'hsl(var(--popover-foreground))',
                borderColor: 'hsl(var(--border))',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
                displayColors: true,
            },
        },
    };

    const renderChart = () => {
        switch (type) {
            case 'line': {
                const lineData = {
                    labels,
                    datasets: [
                        {
                            label: yLabel || yKey,
                            data: values,
                            borderColor: BORDER_COLORS[0],
                            backgroundColor: COLORS[0],
                            borderWidth: 2,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: false,
                        },
                    ],
                };

                const lineOptions: ChartOptions<'line'> = {
                    ...commonOptions,
                    scales: {
                        x: {
                            title: {
                                display: !!xLabel,
                                text: xLabel,
                                color: 'hsl(var(--foreground))',
                                font: { size: 12, weight: '600' },
                            },
                            ticks: {
                                color: 'hsl(var(--muted-foreground))',
                            },
                            grid: {
                                color: 'hsl(var(--border) / 0.2)',
                            },
                        },
                        y: {
                            title: {
                                display: !!yLabel,
                                text: yLabel,
                                color: 'hsl(var(--foreground))',
                                font: { size: 12, weight: '600' },
                            },
                            ticks: {
                                color: 'hsl(var(--muted-foreground))',
                            },
                            grid: {
                                color: 'hsl(var(--border) / 0.2)',
                            },
                        },
                    },
                };

                return (
                    <div className="h-[400px]">
                        <Line data={lineData} options={lineOptions} />
                    </div>
                );
            }

            case 'bar': {
                const barData = {
                    labels,
                    datasets: [
                        {
                            label: yLabel || yKey,
                            data: values,
                            backgroundColor: COLORS[0],
                            borderColor: BORDER_COLORS[0],
                            borderWidth: 2,
                            borderRadius: 8,
                        },
                    ],
                };

                const barOptions: ChartOptions<'bar'> = {
                    ...commonOptions,
                    scales: {
                        x: {
                            title: {
                                display: !!xLabel,
                                text: xLabel,
                                color: 'hsl(var(--foreground))',
                                font: { size: 12, weight: '600' },
                            },
                            ticks: {
                                color: 'hsl(var(--muted-foreground))',
                            },
                            grid: {
                                display: false,
                            },
                        },
                        y: {
                            title: {
                                display: !!yLabel,
                                text: yLabel,
                                color: 'hsl(var(--foreground))',
                                font: { size: 12, weight: '600' },
                            },
                            ticks: {
                                color: 'hsl(var(--muted-foreground))',
                            },
                            grid: {
                                color: 'hsl(var(--border) / 0.2)',
                            },
                        },
                    },
                };

                return (
                    <div className="h-[400px]">
                        <Bar data={barData} options={barOptions} />
                    </div>
                );
            }

            case 'area': {
                const areaData = {
                    labels,
                    datasets: [
                        {
                            label: yLabel || yKey,
                            data: values,
                            borderColor: BORDER_COLORS[0],
                            backgroundColor: COLORS[0],
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        },
                    ],
                };

                const areaOptions: ChartOptions<'line'> = {
                    ...commonOptions,
                    scales: {
                        x: {
                            title: {
                                display: !!xLabel,
                                text: xLabel,
                                color: 'hsl(var(--foreground))',
                                font: { size: 12, weight: '600' },
                            },
                            ticks: {
                                color: 'hsl(var(--muted-foreground))',
                            },
                            grid: {
                                color: 'hsl(var(--border) / 0.2)',
                            },
                        },
                        y: {
                            title: {
                                display: !!yLabel,
                                text: yLabel,
                                color: 'hsl(var(--foreground))',
                                font: { size: 12, weight: '600' },
                            },
                            ticks: {
                                color: 'hsl(var(--muted-foreground))',
                            },
                            grid: {
                                color: 'hsl(var(--border) / 0.2)',
                            },
                        },
                    },
                };

                return (
                    <div className="h-[400px]">
                        <Line data={areaData} options={areaOptions} />
                    </div>
                );
            }

            case 'pie': {
                const pieData = {
                    labels,
                    datasets: [
                        {
                            label: yLabel || yKey,
                            data: values,
                            backgroundColor: COLORS,
                            borderColor: BORDER_COLORS,
                            borderWidth: 2,
                        },
                    ],
                };

                const pieOptions: ChartOptions<'pie'> = {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        legend: {
                            position: 'right' as const,
                            labels: {
                                color: 'hsl(var(--foreground))',
                                font: {
                                    size: 12,
                                    weight: '500',
                                },
                                padding: 15,
                                generateLabels: (chart) => {
                                    const datasets = chart.data.datasets;
                                    return (
                                        chart.data.labels?.map((label, i) => ({
                                            text: `${label}: ${datasets[0].data[i]}`,
                                            fillStyle:
                                                COLORS[i % COLORS.length],
                                            strokeStyle:
                                                BORDER_COLORS[
                                                    i % BORDER_COLORS.length
                                                ],
                                            lineWidth: 2,
                                            hidden: false,
                                            index: i,
                                        })) || []
                                    );
                                },
                            },
                        },
                    },
                };

                return (
                    <div className="h-[400px]">
                        <Pie data={pieData} options={pieOptions} />
                    </div>
                );
            }

            default:
                return null;
        }
    };

    return (
        <div className="my-7 rounded-xl border-2 border-border/60 bg-card p-6 shadow-xl">
            <h3 className="mb-4 text-lg font-extrabold text-primary">
                {title}
            </h3>
            {renderChart()}
        </div>
    );
}
