export interface QueueNode {
    id: number;
    title: string;
    children?: QueueNode[];
}

export interface SavedSearch {
    id: number;
    title: string;
}

export interface QueueNavigation {
    queues: QueueNode[];
    personal: QueueNode[];
    savedSearches: SavedSearch[];
}

export interface QueueFilters {
    state: string[];
    source: string[];
    priority: number[];
    created_from: string | null;
    created_to: string | null;
}

export interface QueueFilterOptions {
    states: string[];
    sources: string[];
    priorities: { id: number; name: string }[];
}

export interface QueueSortState {
    by: string;
    dir: string;
}

export interface TicketRow {
    id: number;
    number: string;
    created: string | null;
    subject: string | null;
    from: string | null;
    priority: string | null;
    assignee: string | null;
    status?: string | null;
    status_state?: string | null;
    source?: string | null;
}

export interface PaginationState {
    page: number;
    perPage: number;
    total: number;
}
