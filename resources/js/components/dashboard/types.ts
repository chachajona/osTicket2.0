export type DashboardMetrics = {
    open: number;
    assignedToMe: number;
    unassigned: number;
    overdue: number;
    trend: Record<MetricKey, MetricTrend>;
    statusComparison: StatusComparison;
    channelDistribution: ChannelDistribution;
    recentActivity: ActivityItem[];
    generatedAt: string;
};

export type MetricKey = 'open' | 'assignedToMe' | 'unassigned' | 'overdue';

export type MetricTrend = {
    previous: number;
    change: number;
    percent: number | null;
    direction: 'up' | 'down' | 'flat' | 'new';
};

export type StatusComparison = {
    rangeStart: string;
    rangeEnd: string;
    openTotal: number;
    solvedTotal: number;
    months: Array<{
        month: string;
        label: string;
        open: number;
        solved: number;
    }>;
};

export type ChannelDistribution = {
    rangeStart: string;
    rangeEnd: string;
    total: number;
    channels: Array<{
        key: string;
        label: string;
        count: number;
        percent: number;
    }>;
};

export type ActivityItem = {
    id: number;
    thread_id: number;
    event_id: number;
    event: string | null;
    ticket_id: number | null;
    ticket_number: string | null;
    ticket_subject: string | null;
    username: string | null;
    timestamp: string | null;
};

export type MetricCard = {
    label: string;
    value: number;
    helper: string;
    trend: MetricTrend;
};

export interface DashboardProps {
    metrics: DashboardMetrics;
    range?: string;
}
